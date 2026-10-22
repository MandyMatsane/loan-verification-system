<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Annual interest rate
    |--------------------------------------------------------------------------
    |
    | Used only for the repayment estimate shown to applicants and admins.
    | This is an illustrative rate, not a real lender's rate. As a fraction:
    | 0.20 means 20% a year.
    |
    */

    'annual_interest_rate' => (float) env('LOAN_ANNUAL_INTEREST_RATE', 0.20),

    /*
    |--------------------------------------------------------------------------
    | Application limits
    |--------------------------------------------------------------------------
    |
    | The amount (in rand) and term (in months) an applicant may ask for.
    | Together with the rate cap in RepaymentCalculator these keep every
    | stored amount well inside the decimal(12,2) columns.
    |
    */

    'max_amount' => 100000,

    'min_term' => 1,

    'max_term' => 60,

];
