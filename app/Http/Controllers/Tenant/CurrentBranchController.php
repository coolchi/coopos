<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CurrentBranchController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isCentralAdmin(), 403);

        $uuid = $request->validate([
            'branch_uuid' => ['nullable', 'string'],
        ])['branch_uuid'] ?? null;

        if (! $uuid || $uuid === 'all') {
            $request->session()->forget('branch_context_uuid');

            return back();
        }

        abort_unless($user->accessibleBranches()->contains(fn ($branch) => $branch->uuid === $uuid), 403);

        $request->session()->put('branch_context_uuid', $uuid);

        return back();
    }
}
