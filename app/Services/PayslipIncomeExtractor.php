<?php

namespace App\Services;

use Throwable;

/**
 * Reads the gross and net pay, and the employee's name, from a payslip's OCR text.
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

    private const AMOUNT = AmountParser::PATTERN;

    // "Employee name : Sally Harley" anywhere on a line (the sample has another column before it), or "Name:" at its start
    private const NAME_LABEL = '(?:\bemployee\s*name|\bname\s*of\s*employee|^\s*name)\s*[:\-]\s*(.+)$';

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
     * The employee's name after its label, up to two spaces (the next column); null when none is found.
     * Digits are kept: OCR can read a letter as one ("M0KOENA").
     * Tesseract reads the sample payslip as "Date of Joining :2018-06-23 Employee name : Sally Harley".
     */
    public function employeeName(?string $text): ?string
    {
        try {
            foreach (preg_split('/\R/u', (string) $text) as $line) {
                if (! preg_match('/' . self::NAME_LABEL . '/iu', $line, $match)) {
                    continue;
                }

                $name = trim(preg_split('/\s{2,}/u', trim($match[1]))[0], " \t.,:;-");

                if ($name !== '') {
                    return $name;
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Parse "R12,345.67", "12 345.67", "12345,67", "12,345" and "12345" to a number; null if it is not an amount.
     */
    public function parseAmount(string $value): ?float
    {
        return (new AmountParser())->parse($value);
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
