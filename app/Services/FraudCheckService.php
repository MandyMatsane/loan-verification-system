<?php

namespace App\Services;

use App\Models\Document;
use App\Models\LoanApplication;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

class FraudCheckService
{
    public const ID_DOCUMENT_TYPE = 'id_document';

    public const PAYSLIP_TYPE = 'payslip';

    public const BANK_STATEMENT_TYPE = 'bank_statement';

    public const NOTE_PAYSLIP_UNREADABLE = 'Income could not be read from the payslip';

    public const NOTE_BANK_STATEMENT_UNREADABLE = 'Bank statement could not be read';

    public const NOTE_BANK_BALANCE_UNREADABLE = 'Balance could not be read from the bank statement';

    public const FLAG_BANK_STATEMENT_NAME = 'Name on the bank statement does not match the applicant';

    public const FLAG_PAYSLIP_NAME = 'Name on the payslip does not match the applicant';

    public const DOCUMENT_LABELS = [
        self::ID_DOCUMENT_TYPE => 'ID document',
        self::PAYSLIP_TYPE => 'Payslip',
        self::BANK_STATEMENT_TYPE => 'Bank statement',
    ];

    public const FLAG_DUPLICATE_FILE = 'This file was also submitted by another applicant (%s)';

    public const NAME_SIMILARITY_THRESHOLD = 85;

    public const ID_NUMBER_LENGTH = 13;

    public const DATE_OF_BIRTH_DIGITS = 6;

    public const DATE_OF_BIRTH_FORMAT = 'ymd';

    public const FLAG_UNREADABLE = 'ID document could not be read';

    public const FLAG_ID_LENGTH = 'ID number is not 13 digits';

    public const FLAG_ID_CHECKSUM = 'ID number fails the Luhn checksum';

    public const FLAG_ID_NOT_ON_DOCUMENT = 'ID number was not found on the ID document';

    public const FLAG_DATE_OF_BIRTH = 'ID number does not match the registered date of birth';

    public function __construct(
        protected PayslipIncomeExtractor $payslipIncomeExtractor,
        protected BankStatementExtractor $bankStatementExtractor,
    ) {
    }

    /**
     * Compare the applicant's registered details with the OCR text of their ID document, payslip and bank statement,
     * their stated income with the payslip, and their stated bank assets with the bank statement.
     * Flags send the application to manual review; notes are neutral and only recorded.
     *
     * @return array{flags: string[], notes: string[], name_similarity: float}
     */
    public function check(LoanApplication $application): array
    {
        $result = $this->checkIdDocument($application);
        $payslipName = $this->checkPayslipName($application);
        $income = $this->checkPayslipIncome($application);
        $bankStatement = $this->checkBankStatement($application);

        return [
            'flags' => array_merge($result['flags'], $payslipName, $income['flags'], $bankStatement['flags'], $this->checkDuplicateFiles($application)),
            'notes' => array_merge($income['notes'], $bankStatement['notes']),
            'name_similarity' => $result['name_similarity'],
        ];
    }

    /**
     * Flag each document type whose latest file was also submitted on another user's application.
     * The applicant's own earlier uploads and reused documents never count, and rows without a hash never match.
     * Only the type is named: nothing about the other applicant is shown.
     *
     * @return string[]
     */
    protected function checkDuplicateFiles(LoanApplication $application): array
    {
        if (! config('loan.duplicate_check_enabled') || $application->user_id === null) {
            return [];
        }

        $flags = [];

        foreach (self::DOCUMENT_LABELS as $type => $label) {
            // the latest upload wins when a document was uploaded more than once
            $hash = $application->documents()
                ->where('type', $type)
                ->latest('id')
                ->value('content_hash');

            if (blank($hash)) {
                continue;
            }

            $elsewhere = Document::query()
                ->where('content_hash', $hash)
                ->whereHas('LoanApplication', fn (Builder $query) => $query->where('user_id', '!=', $application->user_id))
                ->exists();

            if ($elsewhere) {
                $flags[] = sprintf(self::FLAG_DUPLICATE_FILE, $label);
            }
        }

        return $flags;
    }

    /**
     * The applicant's name must be on the bank statement, and the stated bank assets must be close to its balance.
     * An unreadable statement, or one without a balance, is only noted.
     *
     * @return array{flags: string[], notes: string[]}
     */
    protected function checkBankStatement(LoanApplication $application): array
    {
        $text = $this->documentText($application, self::BANK_STATEMENT_TYPE);

        if ($this->normalise($text) === '') {
            return ['flags' => [], 'notes' => [self::NOTE_BANK_STATEMENT_UNREADABLE]];
        }

        $statement = $this->bankStatementExtractor->extract($text);
        $flags = [];
        $notes = [];

        // the account holder line when there is one, otherwise anywhere on the statement
        $threshold = (float) config('loan.bank_statement_name_similarity');

        if (! $this->nameIsOnDocument((string) $application->user?->name, $statement['holder'] ?? $text, $threshold)) {
            $flags[] = self::FLAG_BANK_STATEMENT_NAME;
        }

        $stated = $application->bank_asset_value;

        if ($statement['balance'] === null) {
            $notes[] = self::NOTE_BANK_BALANCE_UNREADABLE;
        } elseif ($stated !== null) {
            $balance = $statement['balance'];

            // relative to the statement balance; a zero balance only matches zero
            if (abs((float) $stated - $balance) > (float) config('loan.bank_balance_tolerance') * $balance) {
                $flags[] = sprintf(
                    'Stated bank assets do not match the bank statement (stated R%s, statement R%s)',
                    number_format((float) $stated, 2),
                    number_format($balance, 2)
                );
            }
        }

        return ['flags' => $flags, 'notes' => $notes];
    }

    /**
     * The employee name on the payslip must match the applicant, the same way as on the bank statement.
     * A payslip without a readable name is not flagged.
     *
     * @return string[]
     */
    protected function checkPayslipName(LoanApplication $application): array
    {
        $name = $this->payslipIncomeExtractor->employeeName($this->documentText($application, self::PAYSLIP_TYPE));

        if ($name === null) {
            return [];
        }

        $threshold = (float) config('loan.payslip_name_similarity');

        return $this->nameIsOnDocument((string) $application->user?->name, $name, $threshold)
            ? []
            : [self::FLAG_PAYSLIP_NAME];
    }

    /**
     * The surname (last word of the name) must be found, and the first name or its initial.
     * Each is matched against every word with the same similarity measure as the ID check.
     */
    protected function nameIsOnDocument(string $name, string $text, float $threshold): bool
    {
        $nameWords = array_values(array_filter(explode(' ', $this->normalise($name)), 'strlen'));
        $words = array_unique(explode(' ', $this->normalise($text)));

        if ($nameWords === []) {
            return false;
        }

        $surname = $nameWords[count($nameWords) - 1];

        if ($this->bestWordMatch($surname, $words) < $threshold) {
            return false;
        }

        if (count($nameWords) === 1) {
            return true;
        }

        $firstName = $nameWords[0];

        return $this->bestWordMatch($firstName, $words) >= $threshold
            || in_array($firstName[0], $words, true);
    }

    /**
     * The balance read from the latest bank statement, or null when none was found.
     */
    public function bankStatementBalance(LoanApplication $application): ?float
    {
        return $this->bankStatementExtractor->extract($this->documentText($application, self::BANK_STATEMENT_TYPE))['balance'];
    }

    /**
     * @return array{flags: string[], name_similarity: float}
     */
    protected function checkIdDocument(LoanApplication $application): array
    {
        $user = $application->user;
        $text = $this->normalise($this->idDocumentText($application));
        $flags = [];
        $nameSimilarity = 0.0;

        if ($text === '') {
            $flags[] = self::FLAG_UNREADABLE;
        } else {
            $nameSimilarity = $this->nameSimilarity((string) $user?->name, $text);

            if ($nameSimilarity < self::NAME_SIMILARITY_THRESHOLD) {
                $flags[] = sprintf(
                    'Name on the ID document is a %.1f%% match with the registered name (below %d%%)',
                    $nameSimilarity,
                    self::NAME_SIMILARITY_THRESHOLD
                );
            }
        }

        $idNumber = trim((string) $user?->id_number);

        if (! preg_match('/^\d{' . self::ID_NUMBER_LENGTH . '}$/', $idNumber)) {
            // the checksum, document and date of birth checks need a 13-digit number
            $flags[] = self::FLAG_ID_LENGTH;

            return ['flags' => $flags, 'name_similarity' => $nameSimilarity];
        }

        if (! $this->passesLuhn($idNumber)) {
            $flags[] = self::FLAG_ID_CHECKSUM;
        }

        if ($text !== '' && ! str_contains(str_replace(' ', '', $text), $idNumber)) {
            $flags[] = self::FLAG_ID_NOT_ON_DOCUMENT;
        }

        if (! $this->matchesDateOfBirth($idNumber, $user?->date_of_birth)) {
            $flags[] = self::FLAG_DATE_OF_BIRTH;
        }

        return ['flags' => $flags, 'name_similarity' => $nameSimilarity];
    }

    /**
     * Compare the stated monthly income with whichever payslip figure (gross or net) is closest.
     *
     * @return array{flags: string[], notes: string[]}
     */
    protected function checkPayslipIncome(LoanApplication $application): array
    {
        $stated = $application->statedMonthlyIncome();

        // without a stated income there is nothing to compare; the affordability check notes it
        if ($stated === null || $stated <= 0) {
            return ['flags' => [], 'notes' => []];
        }

        $figures = $this->payslipFigures($application);
        $candidates = array_values(array_filter([$figures['gross'] ?? null, $figures['net'] ?? null]));

        if ($candidates === []) {
            return ['flags' => [], 'notes' => [self::NOTE_PAYSLIP_UNREADABLE]];
        }

        usort($candidates, fn (float $a, float $b) => abs($a - $stated) <=> abs($b - $stated));
        $payslip = $candidates[0];

        if (abs($stated - $payslip) / $payslip <= (float) config('loan.income_tolerance')) {
            return ['flags' => [], 'notes' => []];
        }

        return [
            'flags' => [sprintf(
                'Stated income does not match the payslip (stated R%s, payslip R%s)',
                number_format($stated, 2),
                number_format($payslip, 2)
            )],
            'notes' => [],
        ];
    }

    /**
     * The gross and net pay read from the latest payslip, or null when neither was found.
     *
     * @return array{gross: ?float, net: ?float}|null
     */
    public function payslipFigures(LoanApplication $application): ?array
    {
        return $this->payslipIncomeExtractor->extract($this->documentText($application, self::PAYSLIP_TYPE));
    }

    protected function documentText(LoanApplication $application, string $type): string
    {
        // the latest upload wins when a document was uploaded more than once
        $document = $application->documents()
            ->where('type', $type)
            ->latest('id')
            ->first();

        return (string) $document?->ocrResult?->extracted_text;
    }

    protected function idDocumentText(LoanApplication $application): string
    {
        // the latest upload wins when the ID document was uploaded more than once
        $document = $application->documents()
            ->where('type', self::ID_DOCUMENT_TYPE)
            ->latest('id')
            ->first();

        return (string) $document?->ocrResult?->extracted_text;
    }

    /**
     * Uppercase, keep letters and digits, collapse spaces.
     */
    protected function normalise(string $text): string
    {
        $text = preg_replace('/[^A-Z0-9]+/', ' ', strtoupper($text));

        return trim((string) $text);
    }

    /**
     * Average, over the words of the registered name, of each word's best match in the OCR text.
     */
    protected function nameSimilarity(string $name, string $text): float
    {
        $nameWords = array_filter(explode(' ', $this->normalise($name)), 'strlen');
        $ocrWords = array_unique(explode(' ', $text));

        if ($nameWords === []) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($nameWords as $nameWord) {
            $total += $this->bestWordMatch($nameWord, $ocrWords);
        }

        return round($total / count($nameWords), 2);
    }

    /**
     * The highest similar_text percentage between the word and any of the words.
     *
     * @param  string[]  $words
     */
    protected function bestWordMatch(string $word, array $words): float
    {
        $best = 0.0;

        foreach ($words as $candidate) {
            similar_text($word, $candidate, $percent);
            $best = max($best, $percent);
        }

        return $best;
    }

    protected function passesLuhn(string $digits): bool
    {
        $sum = 0;

        foreach (array_reverse(str_split($digits)) as $index => $digit) {
            $digit = (int) $digit;

            if ($index % 2 === 1) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    protected function matchesDateOfBirth(string $idNumber, mixed $dateOfBirth): bool
    {
        if ($dateOfBirth instanceof DateTimeInterface) {
            $expected = $dateOfBirth->format(self::DATE_OF_BIRTH_FORMAT);
        } else {
            $timestamp = blank($dateOfBirth) ? false : strtotime((string) $dateOfBirth);

            if ($timestamp === false) {
                return false;
            }

            $expected = date(self::DATE_OF_BIRTH_FORMAT, $timestamp);
        }

        return substr($idNumber, 0, self::DATE_OF_BIRTH_DIGITS) === $expected;
    }
}
