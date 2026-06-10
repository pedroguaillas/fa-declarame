<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contact;
use App\Models\Tenant\ProductAccount;
use App\Models\Tenant\ShopItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopItemController extends Controller
{
    public function link(): Response
    {
        $companyId = company()->id;

        $contacts = Contact::select(
            'contacts.id',
            'contacts.identification',
            'contacts.name',
        )
            ->join('shops', 'shops.contact_id', '=', 'contacts.id')
            ->join('shop_items', 'shop_items.shop_id', '=', 'shops.id')
            ->leftJoin('product_accounts AS pa', function ($join) use ($companyId) {
                $join->on('pa.product_id', '=', 'shop_items.product_id')
                    ->where('pa.company_id', '=', $companyId);
            })
            ->where('shops.company_id', $companyId)
            ->where('shop_items.total', '>', 0)
            ->groupBy('contacts.id', 'contacts.identification', 'contacts.name')
            ->selectRaw('COUNT(shop_items.id) AS total_count')
            ->selectRaw('COUNT(CASE WHEN pa.account_id IS NULL THEN 1 END) AS unlinked_count')
            ->orderByRaw('COUNT(CASE WHEN pa.account_id IS NULL THEN 1 END) DESC')
            ->orderBy('contacts.name')
            ->get();

        return Inertia::render('Tenant/ShopItems/Link', [
            'contacts' => $contacts,
        ]);
    }

    public function byContact(Request $request, Contact $contact): JsonResponse
    {
        $companyId = company()->id;

        $paginator = ShopItem::select([
            'shop_items.id',
            'shop_items.shop_id',
            'shop_items.product_id',
            'shop_items.quantity',
            'shop_items.unit_price',
            'shop_items.discount',
            'shop_items.total',
            'shop_items.tax_percentage',
            'shops.serie',
        ])
            ->selectRaw('pa.account_id AS pa_account_id')
            ->selectRaw('pa_acc.code AS pa_account_code')
            ->selectRaw('pa_acc.name AS pa_account_name')
            ->with(['product:id,code,description'])
            ->join('shops', 'shops.id', '=', 'shop_items.shop_id')
            ->leftJoin('product_accounts AS pa', function ($join) use ($companyId) {
                $join->on('pa.product_id', '=', 'shop_items.product_id')
                    ->where('pa.company_id', '=', $companyId);
            })
            ->leftJoin('accounts AS pa_acc', 'pa_acc.id', '=', 'pa.account_id')
            ->where('shops.contact_id', $contact->id)
            ->where('shops.company_id', $companyId)
            ->where('shop_items.total', '>', 0)
            ->orderByRaw('CASE WHEN pa.account_id IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByDesc('shops.emision')
            ->paginate(50);

        $paginator->getCollection()->transform(function ($item): mixed {
            $item->account = $item->pa_account_id !== null
                ? ['id' => $item->pa_account_id, 'code' => $item->pa_account_code, 'name' => $item->pa_account_name]
                : null;
            unset($item->pa_account_id, $item->pa_account_code, $item->pa_account_name);

            return $item;
        });

        return response()->json($paginator);
    }

    public function assignAccount(Request $request): JsonResponse
    {
        $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'exists:shop_items,id'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
        ]);

        $companyId = company()->id;

        $productIds = ShopItem::whereIn('id', $request->item_ids)
            ->pluck('product_id')
            ->unique()
            ->values();

        foreach ($productIds as $productId) {
            ProductAccount::updateOrCreate(
                ['product_id' => $productId, 'company_id' => $companyId],
                ['account_id' => $request->account_id],
            );
        }

        return response()->json(['message' => 'Cuentas asignadas correctamente.']);
    }
}
