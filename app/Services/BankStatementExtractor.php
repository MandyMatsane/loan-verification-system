<?php

namespace App\Services;

use Throwable;

/**
 * Reads the account holder and the balance from a bank statement's OCR text.
 *
 * Tesseract reads the sample statement's header as "Name: John Citizen Limit of overdraft 1.00" and its
 * balance as "Closing Balance: 350 ZAR" and "Account closing Balance 350 ZAR". The transaction rows lose
 * their decimal points ("7229" for 72.29), so nothing is read from them.
 */
class BankStatementExtractor
{
    // available balance is the more current figure, so it wins over the closing balance
    private const BALANCE_LABELS = [
        'available' => 'available\s*balance',
        'closing' => 'closing\s*balance',
    ];

    private const HOLDER_LABEL = '(?:account\s*holder|account\s*name|name)';

    public function __construct(protected AmountParser $amountParser)
    {
    }

    /**
     * @return array{holder: ?string, balance: ?float, balance_type: ?string} null for anything not found
     */
    public function extract(?string $text): array
    {
        $result = ['holder' => null, 'balance' => null, 'balance_type' => null];

        try {
            if (blank($text)) {
                return $result;
            }

            $lines = preg_split('/\R/u', (string) $text);
            $result['holder'] = $this->holder($lines);

            foreach (self::BALANCE_LABELS as $type => $label) {
                $balance = $this->balance($lines, $label);

                if ($balance !== null) {
                    $result['balance'] = $balance;
                    $result['balance_type'] = $type;

                    break;
                }
            }

            return $result;
        } catch (Throwable) {
            return ['holder' => null, 'balance' => null, 'balance_type' => null];
        }
    }

    /**
     * The text after "Name:" (or "Account holder:"), up to two spaces or the first digit.
     *
     * @param  string[]  $lines
     */
    private function holder(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (! preg_match('/^\s*' . self::HOLDER_LABEL . '\s*[:\-]\s*(.+)$/iu', $line, $match)) {
                continue;
            }

            $holder = preg_split('/\s{2,}|\d/u', trim($match[1]))[0];
            $holder = trim($holder, " \t.,:;-");

            if ($holder !== '') {
                return $holder;
            }
        }

        return null;
    }

    /**
     * The first amount after the label, on the same line or alone on the next non-empty line.
     * A negative balance (a minus sign, brackets or "Dr") is not read: it cannot be compared with stated assets.
     *
     * @param  string[]  $lines
     */
    private function balance(array $lines, string $label): ?float
    {
        $amount = AmountParser::PATTERN;
        $currency = '(?:ZAR\s*)?';

        foreach ($lines as $index => $line) {
            if (! preg_match('/\b' . $label . '\b(.*)$/iu', $line, $match)) {
                continue;
            }

            $candidates = [$match[1]];

            for ($next = $index + 1; $next < count($lines); $next++) {
                if (trim($lines[$next]) !== '') {
                    $candidates[] = $lines[$next];

                    break;
                }
            }

            foreach ($candidates as $position => $candidate) {
                // same line: after an optional ":"; next line: the amount must be all there is
                $pattern = $position === 0
                    ? '/^\s*:?\s*' . $currency . '(-|\()?\s*' . $amount . '\s*\)?\s*(Dr\b)?/iu'
                    : '/^\s*' . $currency . '(-|\()?\s*' . $amount . '\s*\)?\s*(Dr\b)?\s*(?:ZAR)?\s*$/iu';

                if (! preg_match($pattern, $candidate, $found)) {
                    continue;
                }

                if (($found[1] ?? '') !== '' || ($found[3] ?? '') !== '') {
                    return null;
                }

                return $this->amountParser->parse($found[2], allowZero: true);
            }
        }

        return null;
    }
}
