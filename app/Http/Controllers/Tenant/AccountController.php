<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Imports\AccountsImport;
use App\Models\Tenant\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class AccountController extends Controller
{
    public function index(): Response
    {
        $accounts = Account::orderBy('code')->get(['id', 'parent_id', 'code', 'name', 'type', 'is_detail']);

        return Inertia::render('Tenant/Accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        Account::query()->forceDelete();

        Excel::import(new AccountsImport, $request->file('file'));

        return redirect()->route('tenant.accounts.index')
            ->with('success', 'Plan de cuentas importado correctamente.');
    }

    public function search(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $accounts = Account::where('code', 'like', '5%')
            ->where('is_detail', true)
            ->when($q, fn ($query) => $query->where(function ($query) use ($q) {
                $query->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            }))
            ->orderBy('code')
            ->limit(10)
            ->get(['id', 'code', 'name']);

        return response()->json($accounts);
    }
}
