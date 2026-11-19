<?php

namespace App\Services;

use Throwable;

/**
 * Reads rand amounts as Tesseract gives them: "R12,345.67", "12 345.67", "12345,67", "12,345", "12345".
 * Shared by the payslip and bank statement extractors.
 */
class AmountParser
{
    // R12,345.67 / 12 345.67 / 12345,67 / 12345 / 12,345
    public const PATTERN = '(?:R\s?)?(\d{1,3}(?:[ ,\x{00A0}]\d{3})+(?:[.,]\d{1,2})?|\d+(?:[.,]\d{1,2})?)';

    /**
     * The amount as a number; null if it is not an amount, or is 0 and $allowZero is false.
     */
    public function parse(string $value, bool $allowZero = false): ?float
    {
        try {
            $value = trim(preg_replace('/^R\s?/i', '', trim($value)));
            $value = str_replace(["\u{00A0}", ' '], '', $value);

            if (! preg_match('/^\d[\d.,]*$/', $value)) {
                return null;
            }

            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');

            if ($lastComma !== false && $lastDot !== false) {
                // the separator that comes last is the decimal one
                $decimal = $lastComma > $lastDot ? ',' : '.';
                $thousands = $decimal === ',' ? '.' : ',';
                $value = str_replace([$thousands, $decimal], ['', '.'], $value);
            } elseif ($lastComma !== false) {
                // one comma with 1-2 digits after it is a decimal comma; otherwise commas group thousands
                $value = substr_count($value, ',') === 1 && preg_match('/,\d{1,2}$/', $value)
                    ? str_replace(',', '.', $value)
                    : str_replace(',', '', $value);
            } elseif ($lastDot !== false && substr_count($value, '.') > 1) {
                $value = str_replace('.', '', $value);
            }

            if (! is_numeric($value)) {
                return null;
            }

            $amount = round((float) $value, 2);

            return $amount > 0 || ($allowZero && $amount == 0) ? $amount : null;
        } catch (Throwable) {
            return null;
        }
    }
}
