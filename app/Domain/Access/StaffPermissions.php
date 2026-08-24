<?php

namespace App\Domain\Access;

final class StaffPermissions
{
    /**
     * @return list<string>
     */
    public static function catalog(): array
    {
        return [
            'dashboard.view',
            'members.view', 'members.create', 'members.update', 'members.import', 'members.approve',
            'contributions.view', 'contributions.manage', 'contributions.record',
            'withdrawals.view', 'withdrawals.approve',
            'loans.view', 'loans.manage', 'loans.review', 'loans.approve', 'loans.disburse',
            'islamic.view', 'islamic.manage', 'islamic.approve',
            'finance.view', 'finance.manage',
            'reports.view', 'reports.export',
            'approvals.act',
            'settings.manage',
            'users.manage',
            'communication.send',
            'support.manage',
            'branches.view', 'branches.manage',
            'products.view', 'products.manage',
            'attendance.view', 'attendance.manage',
        ];
    }

    /**
     * Tenant HQ: view all branch records, manage the cooperative itself.
     *
     * @return list<string>
     */
    public static function administrator(): array
    {
        return [
            'dashboard.view',
            'members.view',
            'contributions.view',
            'withdrawals.view',
            'loans.view',
            'islamic.view',
            'finance.view', 'finance.manage',
            'reports.view', 'reports.export',
            'communication.send',
            'support.manage',
            'settings.manage',
            'users.manage',
            'branches.view', 'branches.manage',
            'products.view', 'products.manage',
            'attendance.view',
        ];
    }

    /**
     * Branch operations. No settings, support, or branch administration.
     *
     * @return list<string>
     */
    public static function branchAdmin(): array
    {
        return [
            'dashboard.view',
            'members.view', 'members.create', 'members.update', 'members.import', 'members.approve',
            'contributions.view', 'contributions.manage', 'contributions.record',
            'withdrawals.view', 'withdrawals.approve',
            'loans.view', 'loans.manage', 'loans.review', 'loans.approve', 'loans.disburse',
            'islamic.view', 'islamic.manage',
            'finance.view', 'finance.manage',
            'reports.view', 'reports.export',
            'approvals.act',
            'communication.send',
            'attendance.view', 'attendance.manage',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        return [
            'Administrator' => self::administrator(),
            'Branch Admin' => self::branchAdmin(),
            'Chairman' => ['dashboard.view', 'reports.view', 'loans.approve', 'withdrawals.approve', 'islamic.approve', 'approvals.act'],
            'Secretary' => ['dashboard.view', 'members.view', 'members.create', 'members.update', 'communication.send', 'attendance.view', 'attendance.manage'],
            'Treasurer' => ['dashboard.view', 'contributions.view', 'contributions.record', 'withdrawals.view', 'withdrawals.approve', 'finance.view', 'finance.manage', 'reports.view', 'loans.approve', 'approvals.act'],
            'Loan Officer' => ['dashboard.view', 'members.view', 'loans.view', 'loans.manage', 'loans.review', 'islamic.view', 'islamic.manage'],
            'Accountant' => ['dashboard.view', 'finance.view', 'finance.manage', 'reports.view', 'reports.export', 'contributions.view'],
            'Auditor' => ['dashboard.view', 'finance.view', 'reports.view', 'reports.export', 'members.view', 'loans.view', 'islamic.view', 'attendance.view'],
            'Committee Member' => ['dashboard.view', 'approvals.act', 'loans.view', 'islamic.view'],
            'Member' => ['dashboard.view'],
        ];
    }

    /**
     * Permissions the Administrator role cannot drop from Access control.
     *
     * @return list<string>
     */
    public static function administratorLocked(): array
    {
        return ['dashboard.view', 'settings.manage', 'users.manage', 'branches.manage'];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function groups(): array
    {
        return [
            'Overview' => ['dashboard.view'],
            'Membership' => ['members.view', 'members.create', 'members.update', 'members.import', 'members.approve'],
            'Savings' => ['contributions.view', 'contributions.manage', 'contributions.record', 'withdrawals.view', 'withdrawals.approve'],
            'Lending' => ['loans.view', 'loans.manage', 'loans.review', 'loans.approve', 'loans.disburse', 'islamic.view', 'islamic.manage', 'islamic.approve'],
            'Products' => ['products.view', 'products.manage'],
            'Attendance' => ['attendance.view', 'attendance.manage'],
            'Finance & reports' => ['finance.view', 'finance.manage', 'reports.view', 'reports.export'],
            'Governance' => ['approvals.act', 'communication.send', 'support.manage'],
            'Administration' => ['settings.manage', 'users.manage', 'branches.view', 'branches.manage'],
        ];
    }
}
