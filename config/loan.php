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

];
