<?php

namespace App\Http\Controllers\Api;

use App\Domain\Reporting\StatementService;
use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\Member;
use Illuminate\Http\Request;

class V1Controller extends Controller
{
    public function members(Request $request)
    {
        return Member::query()->paginate(50);
    }

    public function contributions()
    {
        return Contribution::query()->with('member')->paginate(50);
    }

    public function loans()
    {
        return Loan::query()->with('member')->paginate(50);
    }

    public function transactions()
    {
        return \App\Models\FinancialTransaction::query()->paginate(50);
    }

    public function reports(StatementService $statements)
    {
        return $statements->dashboardKpis();
    }
}
