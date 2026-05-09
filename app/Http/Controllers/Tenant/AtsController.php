<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\AtsXmlImportService;
use App\Services\AtsXmlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class AtsController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('Tenant/Sri/Index');
    }

    public function import(Request $request, AtsXmlImportService $service): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:xml'],
        ]);

        $company = company();
        $content = file_get_contents($request->file('file')->getRealPath());

        try {
            ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors] = $service->import($content, $company->id, $company->ruc);
        } catch (\RuntimeException $e) {
            return redirect()->route('tenant.sri.index')->with('error', $e->getMessage());
        }

        $msg = "ATS importado: {$imported} compras importadas, {$skipped} omitidas";
        if ($errors > 0) {
            $msg .= ", {$errors} con error";
        }

        $flashKey = ($errors > 0 || ($imported === 0 && $skipped > 0)) ? 'error' : 'success';

        return redirect()->route('tenant.sri.index')->with($flashKey, $msg.'.');
    }

    public function export(Request $request, AtsXmlService $service): Response
    {
        $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2099'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $company = company();
        $year = (int) $request->input('year');
        $month = (int) $request->input('month');

        $xml = $service->generate($company, $year, $month);

        $filename = sprintf('ATS_%s_%d_%02d.xml', $company->ruc, $year, $month);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
