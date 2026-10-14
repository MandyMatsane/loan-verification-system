<?php

namespace App\Services;

use App\Models\LoanApplication;
use DateTimeInterface;

class FraudCheckService
{
    public const ID_DOCUMENT_TYPE = 'id_document';

    public const NAME_SIMILARITY_THRESHOLD = 85;

    public const ID_NUMBER_LENGTH = 13;

    public const DATE_OF_BIRTH_DIGITS = 6;

    public const DATE_OF_BIRTH_FORMAT = 'ymd';

    public const FLAG_UNREADABLE = 'ID document could not be read';

    public const FLAG_ID_LENGTH = 'ID number is not 13 digits';

    public const FLAG_ID_CHECKSUM = 'ID number fails the Luhn checksum';

    public const FLAG_ID_NOT_ON_DOCUMENT = 'ID number was not found on the ID document';

    public const FLAG_DATE_OF_BIRTH = 'ID number does not match the registered date of birth';

    /**
     * Compare the applicant's registered details with the OCR text of their ID document.
     *
     * @return array{flags: string[], name_similarity: float}
     */
    public function check(LoanApplication $application): array
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
