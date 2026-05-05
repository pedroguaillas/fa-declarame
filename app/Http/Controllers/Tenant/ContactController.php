<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contact;
use App\Models\Tenant\IdentificationType;
use App\Services\SriResolveNameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        $contacts = Contact::with('identificationType')
            ->when($search, function ($query, $search) {
                $query->where('name', 'ilike', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%')
                    ->orWhere('identification', 'ilike', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%');
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Tenant/Contacts/Index', [
            'contacts' => $contacts,
            'filters' => ['search' => $search],
            'identificationTypes' => IdentificationType::orderBy('description')->get(['id', 'description']),
        ]);
    }

    public function search(string $identification): JsonResponse
    {
        $contact = Contact::with('identificationType')->where('identification', $identification)->first();

        if ($contact) {
            return response()->json([
                'found' => true,
                'id' => $contact->id,
                'name' => $contact->name,
                'identification' => $contact->identification,
                'type_identification' => $contact->identificationType?->code_shop,
                'supplier_type' => $contact->provider_type ?? '',
            ]);
        }

        return response()->json(['found' => false]);
    }

    public function resolve(string $identification, SriResolveNameService $sri): JsonResponse
    {
        $contact = Contact::where('identification', $identification)->first();

        if ($contact) {
            return response()->json([
                'id' => $contact->id,
                'name' => $contact->name,
                'identification' => $contact->identification,
            ]);
        }

        if ((strlen($identification) === 13 || strlen($identification) === 10) && ctype_digit($identification)) {
            $contact = $sri->searchByIdentificationSRI($identification);

            return response()->json($contact);
        } else {
            return response()->json(['message' => 'Contacto no encontrado.'], 404);
        }
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'identification_type_id' => ['required', 'exists:identification_types,id'],
            'identification' => ['required', 'string', 'max:20', 'unique:contacts,identification'],
            'name' => ['required', 'string', 'max:300'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:50'],
            'address' => ['nullable', 'string', 'max:300'],
            'passport_type' => ['nullable', 'string', 'in:01,02,03'],
        ]);

        if (strlen($data['identification']) === 13) {
            $thirdDigit = substr($data['identification'], 2, 1);

            if (in_array($thirdDigit, ['6', '9'])) {
                $data['provider_type'] = '02';
            } else {
                $data['provider_type'] = '01';
            }
        }

        if (! empty($data['passport_type'])) {
            $data['data_additional'] = ['passport_type' => $data['passport_type']];
        }
        unset($data['passport_type']);

        $contact = Contact::create($data);

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $contact->id,
                'name' => $contact->name,
                'identification' => $contact->identification,
            ]);
        }

        return to_route('tenant.contacts.index')->with('success', 'Contacto creado.');
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        abort_if($contact->identification === '9999999999999', 403);

        $data = $request->validate([
            'identification_type_id' => ['required', 'exists:identification_types,id'],
            'identification' => ['required', 'string', 'max:20', 'unique:contacts,identification,'.$contact->id],
            'name' => ['required', 'string', 'max:300'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:50'],
            'address' => ['nullable', 'string', 'max:300'],
            'passport_type' => ['nullable', 'string', 'in:01,02,03'],
        ]);

        if (! empty($data['passport_type'])) {
            $data['data_additional'] = ['passport_type' => $data['passport_type']];
        } else {
            $data['data_additional'] = null;
        }
        unset($data['passport_type']);

        if (strlen($data['identification']) === 13) {
            $thirdDigit = substr($data['identification'], 2, 1);
            $data['provider_type'] = in_array($thirdDigit, ['6', '9']) ? '02' : '01';
        }

        $contact->update($data);

        return to_route('tenant.contacts.index')->with('success', 'Contacto actualizado.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        abort_if($contact->identification === '9999999999999', 403);

        $contact->delete();

        return to_route('tenant.contacts.index')->with('success', 'Contacto eliminado.');
    }
}
