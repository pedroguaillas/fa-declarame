<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contact;
use App\Services\SriResolveNameService;
use Illuminate\Http\JsonResponse;

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
}
