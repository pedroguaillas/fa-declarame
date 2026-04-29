<?php

namespace App\Http\Controllers;

use App\Models\ModelEntity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ModelEntityController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ModelEntities/Index', [
            'modelEntities' => ModelEntity::withCount('modelPermissions')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:model_entities,slug',
            'description' => 'nullable|string|max:500',
        ]);

        ModelEntity::create($validated);

        return back()->with('success', 'Módulo creado correctamente.');
    }

    public function update(Request $request, ModelEntity $modelEntity): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('model_entities', 'slug')->ignore($modelEntity->id)],
            'description' => 'nullable|string|max:500',
        ]);

        $modelEntity->update($validated);

        return back()->with('success', 'Módulo actualizado correctamente.');
    }

    public function destroy(ModelEntity $modelEntity): RedirectResponse
    {
        if ($modelEntity->modelPermissions()->exists()) {
            return back()->with('error', 'No se puede eliminar un módulo asignado a roles.');
        }

        $modelEntity->delete();

        return back()->with('success', 'Módulo eliminado correctamente.');
    }
}