<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contact;
use App\Services\SriResolveNameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function resolve(string $identification, SriResolveNameService $sri): JsonResponse
    {
        $contact = Contact::where('identification', $identification)->first();

        if (! $contact) {
            if (strlen($identification) !== 13) {
                return response()->json(['message' => 'Contacto no encontrado.'], 404);
            }

            $data = $sri->searchByIdentificationSRI($identification);

            $contact = Contact::create([
                'identification' => $identification,
                'name' => $data['name'],
            ]);
        }

        return response()->json([
            'id' => $contact->id,
            'name' => $contact->name,
            'identification' => $contact->identification,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identification_type_id' => ['required', 'exists:identification_types,id'],
            'identification' => ['required', 'string', 'max:13', 'unique:contacts,identification'],
            'name' => ['required', 'string', 'max:300'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:50'],
            'address' => ['nullable', 'string', 'max:300'],
        ]);

        // 🔥 lógica automática proveedor (según tu comentario en migración)
        if (strlen($data['identification']) === 13) {
            $thirdDigit = substr($data['identification'], 2, 1);

            if (in_array($thirdDigit, ['6', '9'])) {
                $data['provider_type'] = '02';
            } else {
                $data['provider_type'] = '01';
            }
        }

        $contact = Contact::create($data);

        return response()->json([
            'id' => $contact->id,
            'name' => $contact->name,
            'identification' => $contact->identification,
        ]);
    }
}
