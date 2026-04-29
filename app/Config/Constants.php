<?php

class Constants
{
    // Tipo de identificación Compra
    const RUC_COMPRA = '01';
    const CEDULA_COMPRA = '02';
    const PASAPORTE_COMPRA = '03';

    // Tipo de identificación Venta
    const RUC_VENTA = '04';
    const CEDULA_VENTA = '05';
    const PASAPORTE_VENTA = '06';
    const CONSUMIDOR_FINAL = '07';

    // Tipos de comprobantes primarios
    const FACTURA = '01';
    const NOTA_VENTA = '02';
    const LIQUIDACION_COMPRA = '03';
    const NOTA_CREDITO = '04';
    const NOTA_DEBITO = '05';
    const RETENCION = '07';

    // Ivas
    const IVA0 = 0;
    const IVA12 = 2;
    const IVA15 = 4;
    const IVA5 = 5;
    const NO_IVA = 6;
    const IVA_EXENT0 = 7;
    const IVA_DIFERIDO = 8;
}
