<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ModelEntity\StoreModelEntityRequest;
use App\Http\Requests\Tenant\ModelEntity\UpdateModelEntityRequest;
use App\Models\Tenant\ModelEntity;
use App\Services\Tenant\ModelEntityService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ModelEntityController extends Controller
{
    public function __construct(
        private readonly ModelEntityService $modelEntitySvc,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Tenant/ModelEntities/Index', [
            'modelEntities' => $this->modelEntitySvc->allWithPermissions(),
        ]);
    }

    public function store(StoreModelEntityRequest $request): RedirectResponse
    {
        $this->modelEntitySvc->create($request->validated());

        return back()->with('success', 'Módulo creado correctamente.');
    }

    public function update(UpdateModelEntityRequest $request, ModelEntity $modelEntity): RedirectResponse
    {
        $this->modelEntitySvc->update($modelEntity, $request->validated());

        return back()->with('success', 'Módulo actualizado correctamente.');
    }

    public function destroy(ModelEntity $modelEntity): RedirectResponse
    {
        if ($modelEntity->modelPermissions()->exists()) {
            return back()->with('error', 'No se puede eliminar un módulo con permisos asignados en roles.');
        }

        $modelEntity->delete();

        return back()->with('success', 'Módulo eliminado correctamente.');
    }
}
