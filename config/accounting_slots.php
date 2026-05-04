<?php

return [
    'slots' => [
        'disbursement_cash_account' => [
            'label' => 'Disbursement Cash Account',
            'expected_account_types' => ['asset'],
        ],
        'collection_cash_account' => [
            'label' => 'Collection Cash Account',
            'expected_account_types' => ['asset'],
        ],
        'loan_portfolio_performing_account' => [
            'label' => 'Loan Portfolio Performing Account',
            'expected_account_types' => ['asset'],
        ],
        'loan_portfolio_npl_account' => [
            'label' => 'Loan Portfolio NPL Account',
            'expected_account_types' => ['asset'],
        ],
        'loan_portfolio_written_off_account' => [
            'label' => 'Loan Portfolio Written-Off Account',
            'expected_account_types' => ['asset'],
        ],
        'interest_income_account' => [
            'label' => 'Interest Income Account',
            'expected_account_types' => ['income'],
        ],
        'fee_income_account' => [
            'label' => 'Fee Income Account',
            'expected_account_types' => ['income'],
        ],
        'penalty_income_account' => [
            'label' => 'Penalty Income Account',
            'expected_account_types' => ['income'],
        ],
        'client_wallet_liability_account' => [
            'label' => 'Client Wallet Liability Account',
            'expected_account_types' => ['liability'],
        ],
        'adjustment_account' => [
            'label' => 'Wallet Adjustment Offset Account',
            'expected_account_types' => ['expense', 'asset'],
        ],
        'suspense_liability_account' => [
            'label' => 'Suspense Liability Account',
            'expected_account_types' => ['liability'],
        ],
        'operating_expense_account' => [
            'label' => 'Operating Expense Account',
            'expected_account_types' => ['expense'],
        ],
        'salary_expense_account' => [
            'label' => 'Salary Expense Account',
            'expected_account_types' => ['expense'],
        ],
        'staff_advances_account' => [
            'label' => 'Staff Advances Account',
            'expected_account_types' => ['asset'],
        ],
        'writeoff_expense_account' => [
            'label' => 'Write-Off Expense Account',
            'expected_account_types' => ['expense'],
        ],
        'retained_earnings_account' => [
            'label' => 'Retained Earnings Account',
            'expected_account_types' => ['equity'],
        ],
        'reversal_debit_slot' => [
            'label' => 'Reversal Debit Slot',
            'expected_account_types' => [],
            'allow_same_account' => true,
        ],
        'reversal_credit_slot' => [
            'label' => 'Reversal Credit Slot',
            'expected_account_types' => [],
            'allow_same_account' => true,
        ],
    ],
];
