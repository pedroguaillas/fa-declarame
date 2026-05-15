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
            'file' => ['required', 'file', 'max:20480', 'mimes:xml,zip'],
        ]);

        $company = company();
        $uploadedFile = $request->file('file');

        try {
            $content = $this->extractXmlContent($uploadedFile->getRealPath(), $uploadedFile->getClientOriginalExtension());
        } catch (\RuntimeException $e) {
            return redirect()->route('tenant.sri.index')->with('error', $e->getMessage());
        }

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

    /**
     * Extract XML content from an uploaded file (XML or ZIP containing one XML).
     *
     * @throws \RuntimeException
     */
    private function extractXmlContent(string $realPath, string $extension): string
    {
        if (strtolower($extension) !== 'zip') {
            return file_get_contents($realPath);
        }

        $zip = new \ZipArchive;

        if ($zip->open($realPath) !== true) {
            throw new \RuntimeException('No se pudo abrir el archivo ZIP.');
        }

        $xmlContent = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'xml') {
                continue;
            }

            $xmlContent = $zip->getFromIndex($i);
            break;
        }

        $zip->close();

        if ($xmlContent === false || $xmlContent === null) {
            throw new \RuntimeException('El ZIP no contiene ningún archivo XML.');
        }

        return $xmlContent;
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
