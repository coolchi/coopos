<?php

use App\Http\Controllers\Portal\AnnouncementController as PortalAnnouncementController;
use App\Http\Controllers\Portal\DashboardController as PortalDashboardController;
use App\Http\Controllers\Portal\LoanController as PortalLoanController;
use App\Http\Controllers\Portal\MandateController as PortalMandateController;
use App\Http\Controllers\Portal\WalletController as PortalWalletController;
use App\Http\Controllers\Tenant\AccountController;
use App\Http\Controllers\Tenant\ApprovalController;
use App\Http\Controllers\Tenant\AttendanceController;
use App\Http\Controllers\Tenant\BranchController;
use App\Http\Controllers\Tenant\CommunicationController;
use App\Http\Controllers\Tenant\ContributionController;
use App\Http\Controllers\Tenant\CurrentBranchController;
use App\Http\Controllers\Tenant\CurrentFiscalYearController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\IslamicFinanceController;
use App\Http\Controllers\Tenant\JournalController;
use App\Http\Controllers\Tenant\LoanController;
use App\Http\Controllers\Tenant\MemberController;
use App\Http\Controllers\Tenant\OpeningBalanceController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\SettingsController;
use App\Http\Controllers\Tenant\StaffImpersonationController;
use App\Http\Controllers\Tenant\SupportController;
use App\Http\Controllers\Tenant\VoucherController;
use Illuminate\Support\Facades\Route;

Route::middleware(['tenant', 'auth'])->group(function () {
    Route::middleware('tenant.staff')->name('tenant.')->group(function () {
        Route::get('/dashboard', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');
        Route::post('/current-branch', [CurrentBranchController::class, 'store'])->name('branch.switch');
        Route::post('/current-fiscal-year', [CurrentFiscalYearController::class, 'store'])->name('fiscal-year.switch');

        Route::middleware('can:branches.view')->group(function () {
            Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
            Route::get('/branches/{branch}', [BranchController::class, 'show'])->name('branches.show');
        });
        Route::middleware('can:branches.manage')->group(function () {
            Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
            Route::post('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
            Route::post('/branches/{branch}/status', [BranchController::class, 'setStatus'])->name('branches.status');
            Route::post('/branches/{branch}/admin', [BranchController::class, 'assignAdmin'])->name('branches.admin');
            Route::post('/branches/{branch}/staff', [BranchController::class, 'assignStaff'])->name('branches.staff');
            Route::post('/branches/{branch}/staff/{user}/remove', [BranchController::class, 'removeStaff'])->name('branches.staff.remove');
            Route::post('/branches/{branch}/transfer-member', [BranchController::class, 'transferMember'])->name('branches.transfer');
            Route::post('/branches/{branch}/login-as', [StaffImpersonationController::class, 'store'])->name('branches.login-as');
        });
        Route::post('/login-as/leave', [StaffImpersonationController::class, 'leave'])->name('login-as.leave');

        Route::get('/members', [MemberController::class, 'index'])->middleware('can:members.view')->name('members.index');
        Route::middleware('can:members.create')->group(function () {
            Route::get('/members/create', [MemberController::class, 'create'])->name('members.create');
            Route::post('/members', [MemberController::class, 'store'])->name('members.store');
        });
        Route::middleware('can:members.import')->group(function () {
            Route::get('/members/import/template', [MemberController::class, 'importTemplate'])->name('members.import.template');
            Route::post('/members/import', [MemberController::class, 'import'])->name('members.import');
        });
        Route::get('/members/{member}', [MemberController::class, 'show'])->middleware('can:members.view')->name('members.show');
        Route::middleware('can:members.update')->group(function () {
            Route::post('/members/{member}', [MemberController::class, 'update'])->name('members.update');
            Route::post('/members/{member}/virtual-account', [MemberController::class, 'issueVirtualAccount'])->name('members.virtual-account');
            Route::post('/members/{member}/guarantors', [MemberController::class, 'storeGuarantor'])->name('members.guarantors.store');
            Route::post('/members/{member}/guarantors/{guarantor}/remove', [MemberController::class, 'removeGuarantor'])->name('members.guarantors.remove');
        });
        Route::post('/members/{member}/approve', [MemberController::class, 'approve'])->middleware('can:members.approve')->name('members.approve');

        Route::middleware('can:products.view')->group(function () {
            Route::get('/products', [ProductController::class, 'index'])->name('products.index');
            Route::get('/products/monipay-options', [ProductController::class, 'monipayOptions'])->name('products.monipay');
            Route::get('/savings/products', [ContributionController::class, 'products'])->name('savings.products');
            Route::get('/loans/products', [LoanController::class, 'products'])->name('loans.products');
        });
        Route::middleware('can:products.manage')->group(function () {
            Route::post('/products', [ProductController::class, 'store'])->name('products.store');
            Route::post('/products/{kind}/{uuid}', [ProductController::class, 'update'])->name('products.update');
            Route::post('/products/{kind}/{uuid}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');
            Route::delete('/products/{kind}/{uuid}', [ProductController::class, 'destroy'])->name('products.destroy');
            Route::post('/savings/products', [ContributionController::class, 'storeProduct'])->name('savings.products.store');
            Route::post('/loans/products', [LoanController::class, 'storeProduct'])->name('loans.products.store');
        });

        Route::middleware('can:contributions.view')->group(function () {
            Route::get('/contributions', [ContributionController::class, 'index'])->name('contributions.index');
            Route::get('/contributions/enrollments', [ContributionController::class, 'enrollments'])->name('contributions.enrollments');
            Route::get('/opening-balances', [OpeningBalanceController::class, 'index'])->name('opening.index');
        });
        Route::post('/contributions', [ContributionController::class, 'record'])->middleware('can:contributions.record')->name('contributions.record');
        Route::middleware('can:contributions.manage')->group(function () {
            Route::post('/contributions/enroll', [ContributionController::class, 'enroll'])->name('contributions.enroll');
            Route::post('/contributions/enrollments/{enrollment}/cancel', [ContributionController::class, 'unenroll'])->name('contributions.unenroll');
            Route::put('/contributions/{contribution}', [ContributionController::class, 'update'])->name('contributions.update');
            Route::delete('/contributions/{contribution}', [ContributionController::class, 'destroy'])->name('contributions.destroy');
            Route::post('/opening-balances', [OpeningBalanceController::class, 'store'])->name('opening.store');
            Route::post('/opening-balances/lock', [OpeningBalanceController::class, 'lock'])->name('opening.lock');
        });
        Route::post('/opening-balances/unlock', [OpeningBalanceController::class, 'unlock'])->middleware('can:settings.manage')->name('opening.unlock');

        Route::get('/withdrawals', [ReportController::class, 'withdrawals'])->middleware('can:withdrawals.view')->name('withdrawals.index');
        Route::middleware('can:withdrawals.approve')->group(function () {
            Route::post('/withdrawals', [ReportController::class, 'requestWithdrawal'])->name('withdrawals.store');
            Route::put('/withdrawals/{withdrawal}', [ReportController::class, 'updateWithdrawal'])->name('withdrawals.update');
            Route::delete('/withdrawals/{withdrawal}', [ReportController::class, 'destroyWithdrawal'])->name('withdrawals.destroy');
        });

        Route::middleware('can:loans.view')->group(function () {
            Route::get('/loans/applications', [LoanController::class, 'applications'])->name('loans.applications');
            Route::get('/loans/eligibility', [LoanController::class, 'eligibility'])->name('loans.eligibility');
            Route::get('/loans/preview', [LoanController::class, 'preview'])->name('loans.preview');
            Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
            Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
        });
        Route::middleware('can:loans.manage')->group(function () {
            Route::post('/loans/applications', [LoanController::class, 'apply'])->name('loans.apply');
            Route::post('/loans/{loan}/repay', [LoanController::class, 'repay'])->name('loans.repay');
            Route::post('/guarantors/{guarantor}', [LoanController::class, 'guarantee'])->name('loans.guarantee');
        });
        Route::post('/loans/applications/{application}/disburse', [LoanController::class, 'disburse'])->middleware('can:loans.disburse')->name('loans.disburse');

        Route::prefix('loan')->name('islamic.')->group(function () {
            Route::get('/', [IslamicFinanceController::class, 'index'])->middleware('can:islamic.view')->name('index');
            Route::post('/products', [IslamicFinanceController::class, 'storeProduct'])->middleware('can:products.manage')->name('products.store');
            Route::middleware('can:islamic.manage')->group(function () {
                Route::post('/qard', [IslamicFinanceController::class, 'storeQard'])->name('qard.store');
                Route::post('/qard/{financing}/disburse', [IslamicFinanceController::class, 'disburseQard'])->name('qard.disburse');
                Route::post('/qard/{financing}/repay', [IslamicFinanceController::class, 'repayQard'])->name('qard.repay');
                Route::post('/murabaha', [IslamicFinanceController::class, 'storeMurabaha'])->name('murabaha.store');
                Route::post('/murabaha/{financing}/finalize', [IslamicFinanceController::class, 'finalizeMurabaha'])->name('murabaha.finalize');
                Route::post('/murabaha/{financing}/repay', [IslamicFinanceController::class, 'repayMurabaha'])->name('murabaha.repay');
            });
            Route::post('/shariah', [IslamicFinanceController::class, 'storeShariahApproval'])->middleware('can:islamic.approve')->name('shariah.store');
        });
        Route::any('/islamic/{path?}', function (string $tenant, ?string $path = null) {
            $target = tenant_path('loan'.($path ? '/'.$path : ''));
            $query = request()->getQueryString();

            return redirect()->to($query ? $target.'?'.$query : $target, 308);
        })->where('path', '.*');

        Route::get('/approvals', [ApprovalController::class, 'index'])->middleware('can:approvals.act')->name('approvals.index');
        Route::post('/approvals/{approval}', [ApprovalController::class, 'act'])->middleware('can:approvals.act')->name('approvals.act');

        Route::middleware('can:reports.view')->group(function () {
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/statement', [ReportController::class, 'statement'])->name('reports.statement');
            Route::get('/reports/accounts', [ReportController::class, 'accounts'])->name('reports.accounts');
            Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
            Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance'])->name('reports.trial-balance');
            Route::get('/reports/ledger', [ReportController::class, 'ledger'])->name('reports.ledger');
        });

        Route::middleware('can:finance.view')->group(function () {
            Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
            Route::get('/accounts/expenses', [VoucherController::class, 'expenses'])->name('accounts.expenses');
            Route::get('/accounts/income', [VoucherController::class, 'income'])->name('accounts.income');
            Route::get('/accounts/journals', [JournalController::class, 'index'])->name('accounts.journals');
            Route::get('/accounts/journals/{journal}', [JournalController::class, 'show'])->name('accounts.journals.show');
        });
        Route::middleware('can:finance.manage')->group(function () {
            Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
            Route::post('/accounts/expenses', [VoucherController::class, 'storeExpense'])->name('accounts.expenses.store');
            Route::post('/accounts/income', [VoucherController::class, 'storeIncome'])->name('accounts.income.store');
            Route::post('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
            Route::post('/accounts/{account}/toggle', [AccountController::class, 'toggle'])->name('accounts.toggle');
            Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
        });

        Route::middleware('can:attendance.view')->group(function () {
            Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
            Route::get('/attendance/{meeting}', [AttendanceController::class, 'show'])->name('attendance.show');
        });
        Route::middleware('can:attendance.manage')->group(function () {
            Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
            Route::post('/attendance/{meeting}', [AttendanceController::class, 'save'])->name('attendance.save');
        });

        Route::middleware('can:settings.manage')->group(function () {
            Route::get('/settings', [SettingsController::class, 'organization'])->name('settings.organization');
            Route::post('/settings', [SettingsController::class, 'updateOrganization'])->name('settings.organization.update');
            Route::get('/settings/policies', [SettingsController::class, 'policies'])->name('settings.policies');
            Route::post('/settings/policies', [SettingsController::class, 'updatePolicies'])->name('settings.policies.update');
            Route::get('/settings/users', [SettingsController::class, 'users'])->name('settings.users');
            Route::post('/settings/users', [SettingsController::class, 'storeUser'])->name('settings.users.store');
            Route::post('/settings/users/{user}/role', [SettingsController::class, 'updateUserRole'])->name('settings.users.role');
            Route::get('/settings/access', [SettingsController::class, 'access'])->name('settings.access');
            Route::post('/settings/access/{role}', [SettingsController::class, 'updateRolePermissions'])->name('settings.access.update');
            Route::get('/settings/activity', [SettingsController::class, 'activity'])->name('settings.activity');
            Route::get('/settings/workflows', [SettingsController::class, 'workflows'])->name('settings.workflows');
            Route::post('/settings/workflows', [SettingsController::class, 'storeWorkflow'])->name('settings.workflows.store');
            Route::put('/settings/workflows/{workflow}', [SettingsController::class, 'updateWorkflow'])->name('settings.workflows.update');
            Route::post('/settings/workflows/{workflow}', [SettingsController::class, 'toggleWorkflow'])->name('settings.workflows.toggle');
            Route::delete('/settings/workflows/{workflow}', [SettingsController::class, 'destroyWorkflow'])->name('settings.workflows.destroy');
            Route::get('/settings/demo', [SettingsController::class, 'demoData'])->name('settings.demo');
            Route::post('/settings/demo/mode', [SettingsController::class, 'updateAppMode'])->name('settings.demo.mode');
            Route::post('/settings/demo/seed', [SettingsController::class, 'seedDemo'])->name('settings.demo.seed');
            Route::post('/settings/demo/reset', [SettingsController::class, 'resetDemo'])->name('settings.demo.reset');
        });

        Route::get('/communication', [CommunicationController::class, 'index'])->middleware('can:communication.send')->name('communication.index');
        Route::middleware('can:communication.send')->group(function () {
            Route::post('/communication', [CommunicationController::class, 'store'])->name('communication.store');
            Route::post('/communication/{announcement}', [CommunicationController::class, 'update'])->name('communication.update');
            Route::post('/communication/{announcement}/publish', [CommunicationController::class, 'publish'])->name('communication.publish');
            Route::post('/communication/{announcement}/pin', [CommunicationController::class, 'pin'])->name('communication.pin');
            Route::delete('/communication/{announcement}', [CommunicationController::class, 'destroy'])->name('communication.destroy');
        });

        Route::middleware('can:support.manage')->group(function () {
            Route::get('/support', [SupportController::class, 'index'])->name('support.index');
            Route::post('/support', [SupportController::class, 'store'])->name('support.store');
            Route::get('/support/{ticket}', [SupportController::class, 'show'])->name('support.show');
            Route::post('/support/{ticket}/reply', [SupportController::class, 'reply'])->name('support.reply');
            Route::post('/support/{ticket}/close', [SupportController::class, 'close'])->name('support.close');
        });
    });

    Route::middleware('tenant.member')->prefix('portal')->name('portal.')->group(function () {
        Route::get('/', PortalDashboardController::class)->name('dashboard');
        Route::get('/announcements', [PortalAnnouncementController::class, 'index'])->name('announcements');
        Route::post('/contribute', [PortalDashboardController::class, 'contribute'])->name('contribute');
        Route::post('/mandates', [PortalMandateController::class, 'store'])->name('mandates.store');
        Route::patch('/mandates/{enrollment}', [PortalMandateController::class, 'update'])->name('mandates.update');
        Route::delete('/mandates/{enrollment}', [PortalMandateController::class, 'destroy'])->name('mandates.destroy');
        Route::get('/wallet', PortalWalletController::class)->name('wallet');
        Route::get('/loans', [PortalLoanController::class, 'index'])->name('loans');
        Route::get('/apply', [PortalDashboardController::class, 'applyFinance'])->name('apply');
        Route::get('/apply/eligibility', [PortalDashboardController::class, 'eligibility'])->name('apply.eligibility');
        Route::post('/apply/loan', [PortalDashboardController::class, 'applyLoan'])->name('apply.loan');
        Route::post('/apply/qard', [PortalDashboardController::class, 'applyQard'])->name('apply.qard');
        Route::get('/statement', [PortalDashboardController::class, 'statement'])->name('statement');
    });
});
