<?php

namespace App\Domain\Ledger;

use App\Enums\AccountType;

final class StandardChart
{
    /**
     * Cooperative chart of accounts. Existing system codes must stay stable.
     *
     * @return list<array{code: string, name: string, type: AccountType, sort: int, description?: string}>
     */
    public static function accounts(): array
    {
        return [
            ['code' => '1000', 'name' => 'Cash', 'type' => AccountType::Asset, 'sort' => 10],
            ['code' => '1010', 'name' => 'Bank', 'type' => AccountType::Asset, 'sort' => 20],
            ['code' => '1100', 'name' => 'Loans Receivable', 'type' => AccountType::Asset, 'sort' => 30],
            ['code' => '1110', 'name' => 'Qard Hasan Receivable', 'type' => AccountType::Asset, 'sort' => 40],
            ['code' => '1120', 'name' => 'Murabaha Receivable', 'type' => AccountType::Asset, 'sort' => 50],

            ['code' => '2100', 'name' => 'Member Savings', 'type' => AccountType::Liability, 'sort' => 10],
            ['code' => '2110', 'name' => 'Member Wallet', 'type' => AccountType::Liability, 'sort' => 15],
            ['code' => '2200', 'name' => 'Payables', 'type' => AccountType::Liability, 'sort' => 20],
            ['code' => '2210', 'name' => 'Honorarium Payable', 'type' => AccountType::Liability, 'sort' => 30],
            ['code' => '2220', 'name' => 'Profit Share Payable', 'type' => AccountType::Liability, 'sort' => 40],

            ['code' => '3000', 'name' => 'Member Shares', 'type' => AccountType::Equity, 'sort' => 10],
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => AccountType::Equity, 'sort' => 20],
            ['code' => '3200', 'name' => 'Reserve Fund', 'type' => AccountType::Equity, 'sort' => 30],

            ['code' => '4100', 'name' => 'Registration Form', 'type' => AccountType::Income, 'sort' => 10],
            ['code' => '4110', 'name' => 'Transaction Form', 'type' => AccountType::Income, 'sort' => 20],
            ['code' => '4120', 'name' => 'Development Levy', 'type' => AccountType::Income, 'sort' => 30],
            ['code' => '4030', 'name' => 'Lateness / Apology / Fines', 'type' => AccountType::Income, 'sort' => 40],
            ['code' => '4140', 'name' => 'AGM Fee', 'type' => AccountType::Income, 'sort' => 50],
            ['code' => '4150', 'name' => 'TFC Return', 'type' => AccountType::Income, 'sort' => 60],
            ['code' => '4160', 'name' => 'Zakat & Sadaqah', 'type' => AccountType::Income, 'sort' => 70],
            ['code' => '4170', 'name' => 'Donations', 'type' => AccountType::Income, 'sort' => 80],
            ['code' => '4180', 'name' => 'Identity Card', 'type' => AccountType::Income, 'sort' => 90],
            ['code' => '4190', 'name' => 'Stamp Duty', 'type' => AccountType::Income, 'sort' => 100],
            ['code' => '4200', 'name' => 'Business Income', 'type' => AccountType::Income, 'sort' => 110],
            ['code' => '4000', 'name' => 'Loan Income', 'type' => AccountType::Income, 'sort' => 120],
            ['code' => '4010', 'name' => 'Murabaha Profit', 'type' => AccountType::Income, 'sort' => 130],
            ['code' => '4020', 'name' => 'Service Charges', 'type' => AccountType::Income, 'sort' => 140],
            ['code' => '5100', 'name' => 'Withdrawal Fees', 'type' => AccountType::Income, 'sort' => 150],
            ['code' => '4210', 'name' => 'Other Income', 'type' => AccountType::Income, 'sort' => 160],

            ['code' => '5010', 'name' => 'Rent', 'type' => AccountType::Expense, 'sort' => 10],
            ['code' => '5020', 'name' => 'Reg Form FRM AGA', 'type' => AccountType::Expense, 'sort' => 20],
            ['code' => '5030', 'name' => 'Salary', 'type' => AccountType::Expense, 'sort' => 30],
            ['code' => '5040', 'name' => 'Printing', 'type' => AccountType::Expense, 'sort' => 40],
            ['code' => '5050', 'name' => 'Lighting / Electricity', 'type' => AccountType::Expense, 'sort' => 50],
            ['code' => '5060', 'name' => 'Stationery / Photocopy', 'type' => AccountType::Expense, 'sort' => 60],
            ['code' => '5070', 'name' => 'Repair / Maintenance', 'type' => AccountType::Expense, 'sort' => 70],
            ['code' => '5080', 'name' => 'Entertainment / Seminar', 'type' => AccountType::Expense, 'sort' => 80],
            ['code' => '5090', 'name' => 'Telephone Expenses', 'type' => AccountType::Expense, 'sort' => 90],
            ['code' => '5110', 'name' => 'Generator Fueling', 'type' => AccountType::Expense, 'sort' => 100],
            ['code' => '5120', 'name' => 'Transportation', 'type' => AccountType::Expense, 'sort' => 110],
            ['code' => '5130', 'name' => 'Dev. Levy to AGA (40%)', 'type' => AccountType::Expense, 'sort' => 120],
            ['code' => '5140', 'name' => 'Depreciation', 'type' => AccountType::Expense, 'sort' => 130],
            ['code' => '5150', 'name' => 'Provision for Bad Debt', 'type' => AccountType::Expense, 'sort' => 140],
            ['code' => '5160', 'name' => 'Bank Charges / Stamp Duty', 'type' => AccountType::Expense, 'sort' => 150],
            ['code' => '5170', 'name' => 'Zakat / Sadaqah', 'type' => AccountType::Expense, 'sort' => 160],
            ['code' => '5180', 'name' => 'AGM Expenses', 'type' => AccountType::Expense, 'sort' => 170],
            ['code' => '5000', 'name' => 'Operating Expenses', 'type' => AccountType::Expense, 'sort' => 180],
        ];
    }
}
