<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Support\FiscalPeriod;
use App\Support\FiscalYearContext;
use Illuminate\Http\Request;

class CurrentFiscalYearController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(tenant(), 404);

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:1990', 'max:2100'],
        ]);

        $year = FiscalYearContext::clamp((int) $data['year']);
        $request->session()->put('fiscal_year_start_year', $year);

        return back()->with('success', FiscalPeriod::forYear($year)->displayLabel().' selected.');
    }
}
