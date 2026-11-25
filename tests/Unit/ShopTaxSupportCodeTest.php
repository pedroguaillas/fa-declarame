<?php

namespace Tests\Unit;

use App\Models\Tenant\Shop;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ShopTaxSupportCodeTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string, ?string, string}>
     */
    public static function cases(): array
    {
        return [
            'nueva nota de venta usa 02' => ['02', null, null, '02'],
            'nueva factura usa 01' => ['01', null, null, '01'],
            'nueva nota de crédito usa 01' => ['04', null, null, '01'],
            'factura cambiada a nota de venta pasa de 01 a 02' => ['02', '01', '01', '02'],
            'nota de venta con 01 se corrige a 02 al actualizar' => ['02', '01', '02', '02'],
            'nota de venta activo fijo 03 pasa a 04' => ['02', '03', '01', '04'],
            'nota de venta inventario 06 pasa a 07' => ['02', '06', '01', '07'],
            'nota de venta conserva sustento sin crédito tributario' => ['02', '05', '02', '05'],
            'nota de venta cambiada a factura revierte 02 a 01' => ['01', '02', '02', '01'],
            'nota de venta cambiada a factura revierte 07 a 06' => ['01', '07', '02', '06'],
            'factura conserva sustento 02 elegido' => ['01', '02', '01', '02'],
            'factura conserva sustento 06' => ['01', '06', '01', '06'],
        ];
    }

    #[DataProvider('cases')]
    public function test_resolves_tax_support_code(
        string $voucherCode,
        ?string $currentTaxSupportCode,
        ?string $previousVoucherCode,
        string $expected,
    ): void {
        $this->assertSame(
            $expected,
            Shop::taxSupportCodeFor($voucherCode, $currentTaxSupportCode, $previousVoucherCode),
        );
    }
}
