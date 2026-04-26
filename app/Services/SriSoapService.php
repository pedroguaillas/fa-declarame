<?php

namespace App\Services;

class SriSoapService
{
    public function authorize(string $claveAccesoComprobante)
    {
        $wsdlAuthorization = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';

        $options = [
            'soap_version' => SOAP_1_1,
            'trace' => 1, // used for __getLastResponse return result in XML
            'connection_timeout' => 3,
            'exceptions' => 0, // used for detect error in SOAP is_soap_fault
        ];

        $soapClientValidation = new \SoapClient($wsdlAuthorization, $options);

        // Parameters SOAP
        $user_param = ['claveAccesoComprobante' => $claveAccesoComprobante];

        try {
            $response = $soapClientValidation->autorizacionComprobante($user_param);

            // Verificar si la peticion llego al SRI sino abandonar el proceso
            if (! property_exists($response, 'RespuestaAutorizacionComprobante')) {
                return;
            }

            return $response->RespuestaAutorizacionComprobante->autorizaciones->autorizacion;

        } catch (\Exception $e) {
            info(' CODE: '.$e->getCode());
        }
    }
}
