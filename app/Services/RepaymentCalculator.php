<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * An illustrative repayment estimate for a fixed-rate loan repaid in equal monthly instalments.
 * The same formula runs in the browser on the application form (resources/views/applications/create.blade.php).
 */
class RepaymentCalculator
{
    /** 100% a year: keeps interest_rate inside decimal(5,4) and totals inside decimal(12,2). */
    public const MAX_ANNUAL_RATE = 1.0;

    /**
     * @return array{monthly_repayment: float, total_repayable: float, total_interest: float, rate: float}
     */
    public function estimate(float $amount, int $termMonths, ?float $annualRate = null): array
    {
        $annualRate ??= (float) config('loan.annual_interest_rate');

        if (! is_finite($amount) || $amount <= 0) {
            throw new InvalidArgumentException('The loan amount must be greater than 0.');
        }

        if ($termMonths < 1) {
            throw new InvalidArgumentException('The loan term must be at least 1 month.');
        }

        if (! is_finite($annualRate) || $annualRate < 0 || $annualRate > self::MAX_ANNUAL_RATE) {
            throw new InvalidArgumentException('The interest rate must be between 0 and ' . (self::MAX_ANNUAL_RATE * 100) . '% a year.');
        }

        $monthlyRate = $annualRate / 12;

        // amortised instalment: P * r / (1 - (1 + r)^-n)
        $monthly = $monthlyRate == 0
            ? $amount / $termMonths
            : $amount * $monthlyRate / (1 - (1 + $monthlyRate) ** -$termMonths);

        // total from the unrounded instalment, so a 0% loan never shows negative interest
        $totalRepayable = round($monthly * $termMonths, 2);

        return [
            'monthly_repayment' => round($monthly, 2),
            'total_repayable' => $totalRepayable,
            'total_interest' => round($totalRepayable - $amount, 2),
            'rate' => $annualRate,
        ];
    }
}
