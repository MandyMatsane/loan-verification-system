<?php

namespace App\Services;

use Throwable;

/**
 * Reads the gross and net pay from a payslip's OCR text.
 *
 * Looks for a label (e.g. "Net Pay", "Total Earnings") followed by an amount on the same line,
 * or on the next line when that line holds only the amount. Tesseract reads the sample payslip
 * as "Total Earnings 11600" and "Net Pay 9500".
 */
class PayslipIncomeExtractor
{
    private const LABELS = [
        'net' => ['net\s*pay', 'net\s*salary', 'nett\s*pay', 'take[\s-]*home(?:\s*pay)?'],
        'gross' => ['gross\s*pay', 'gross\s*salary', 'total\s*earnings'],
    ];

    // R12,345.67 / 12 345.67 / 12345,67 / 12345 / 12,345
    private const AMOUNT = '(?:R\s?)?(\d{1,3}(?:[ ,\x{00A0}]\d{3})+(?:[.,]\d{1,2})?|\d+(?:[.,]\d{1,2})?)';

    /**
     * @return array{gross: ?float, net: ?float}|null null when neither figure is found
     */
    public function extract(?string $text): ?array
    {
        try {
            if (blank($text)) {
                return null;
            }

            $lines = preg_split('/\R/u', $text);
            $figures = ['gross' => null, 'net' => null];

            foreach (self::LABELS as $kind => $labels) {
                $figures[$kind] = $this->find($lines, '(?:' . implode('|', $labels) . ')');
            }

            return $figures['gross'] === null && $figures['net'] === null ? null : $figures;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Parse "R12,345.67", "12 345.67", "12345,67", "12,345" and "12345" to a number; null if it is not an amount.
     */
    public function parseAmount(string $value): ?float
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

            return $amount > 0 ? $amount : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  string[]  $lines
     */
    private function find(array $lines, string $label): ?float
    {
        foreach ($lines as $index => $line) {
            if (! preg_match('/\b' . $label . '\b(.*)$/iu', $line, $match)) {
                continue;
            }

            // the amount on the same line, after an optional ":" or "-"
            if (preg_match('/^\s*[:\-]?\s*' . self::AMOUNT . '/iu', $match[1], $amount)) {
                return $this->parseAmount($amount[1]);
            }

            // or alone on the next non-empty line
            for ($next = $index + 1; $next < count($lines); $next++) {
                if (trim($lines[$next]) === '') {
                    continue;
                }

                if (preg_match('/^\s*' . self::AMOUNT . '\s*$/iu', $lines[$next], $amount)) {
                    return $this->parseAmount($amount[1]);
                }

                break;
            }
        }

        return null;
    }
}
