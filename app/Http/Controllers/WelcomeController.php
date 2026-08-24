<?php

namespace App\Http\Controllers;

use App\Support\Tenancy;
use Inertia\Inertia;

class WelcomeController extends Controller
{
    public function __invoke()
    {
        $tenant = Tenancy::tenantFromHost(request()->getHost());

        if ($tenant) {
            return redirect('/login');
        }

        return Inertia::render('Public/Welcome');
    }
}
