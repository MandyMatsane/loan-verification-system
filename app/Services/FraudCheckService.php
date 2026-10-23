<?php

namespace App\Services;

use App\Models\LoanApplication;
use DateTimeInterface;

class FraudCheckService
{
    public const ID_DOCUMENT_TYPE = 'id_document';

    public const PAYSLIP_TYPE = 'payslip';

    public const NOTE_PAYSLIP_UNREADABLE = 'Income could not be read from the payslip';

    public const NAME_SIMILARITY_THRESHOLD = 85;

    public const ID_NUMBER_LENGTH = 13;

    public const DATE_OF_BIRTH_DIGITS = 6;

    public const DATE_OF_BIRTH_FORMAT = 'ymd';

    public const FLAG_UNREADABLE = 'ID document could not be read';

    public const FLAG_ID_LENGTH = 'ID number is not 13 digits';

    public const FLAG_ID_CHECKSUM = 'ID number fails the Luhn checksum';

    public const FLAG_ID_NOT_ON_DOCUMENT = 'ID number was not found on the ID document';

    public const FLAG_DATE_OF_BIRTH = 'ID number does not match the registered date of birth';

    public function __construct(protected PayslipIncomeExtractor $payslipIncomeExtractor)
    {
    }

    /**
     * Compare the applicant's registered details with the OCR text of their ID document,
     * and their stated income with the payslip.
     * Flags send the application to manual review; notes are neutral and only recorded.
     *
     * @return array{flags: string[], notes: string[], name_similarity: float}
     */
    public function check(LoanApplication $application): array
    {
        $result = $this->checkIdDocument($application);
        $income = $this->checkPayslipIncome($application);

        return [
            'flags' => array_merge($result['flags'], $income['flags']),
            'notes' => $income['notes'],
            'name_similarity' => $result['name_similarity'],
        ];
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
            $best = 0.0;

            foreach ($ocrWords as $ocrWord) {
                similar_text($nameWord, $ocrWord, $percent);
                $best = max($best, $percent);
            }

            $total += $best;
        }

        return round($total / count($nameWords), 2);
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
