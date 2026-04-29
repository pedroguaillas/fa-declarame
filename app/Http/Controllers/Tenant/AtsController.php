<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\AtsXmlService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AtsController extends Controller
{
    public function export(Request $request, AtsXmlService $service): Response
    {
        $request->validate([
            'year'  => ['required', 'integer', 'min:2000', 'max:2099'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $company = company();
        $year    = (int) $request->input('year');
        $month   = (int) $request->input('month');

        $xml = $service->generate($company, $year, $month);

        $filename = sprintf('ATS_%s_%d_%02d.xml', $company->ruc, $year, $month);

        return response($xml, 200, [
            'Content-Type'        => 'application/xml; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
