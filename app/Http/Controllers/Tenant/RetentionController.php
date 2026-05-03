<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Retention;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RetentionController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $retentions = Retention::query()
            ->when($q, fn ($query) => $query->where(function ($query) use ($q) {
                $query->where('code', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            }))
            ->orderBy('type')
            ->orderBy('code')
            ->limit(15)
            ->get(['id', 'code', 'type', 'description', 'percentage']);

        return response()->json($retentions);
    }
}
