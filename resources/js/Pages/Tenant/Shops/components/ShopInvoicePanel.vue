<script setup lang="ts">
import type { Shop, ShopItem } from "@/types/tenant";

defineProps<{
    shop: Shop;
    shopItems: ShopItem[];
    shopItemsLoading: boolean;
}>();
</script>

<template>
    <div class="border-border flex w-96 shrink-0 flex-col border-r">
        <div class="border-border border-b px-5 py-4">
            <p class="text-muted-foreground text-xs font-semibold tracking-widest uppercase">Factura</p>
            <p class="text-foreground mt-0.5 font-mono text-sm font-medium">
                {{ shop.serie }}
            </p>
        </div>
        <div class="flex-1 space-y-4 overflow-y-auto p-5">
            <div>
                <p class="text-muted-foreground mb-0.5 text-xs font-medium">Proveedor</p>
                <p class="text-foreground text-sm">
                    {{ shop.contact?.name }}
                </p>
            </div>
            <div>
                <p class="text-muted-foreground mb-0.5 text-xs font-medium">Fecha emisión</p>
                <p class="text-foreground text-sm tabular-nums">
                    {{ shop.emision }}
                </p>
            </div>
            <div>
                <p class="text-muted-foreground mb-0.5 text-xs font-medium">Clave de acceso</p>
                <p class="text-foreground font-mono text-xs break-all">
                    {{ shop.autorization }}
                </p>
            </div>

            <!-- IVA breakdown -->
            <div class="border-border overflow-hidden rounded-lg border">
                <table class="min-w-full text-xs">
                    <thead class="bg-muted">
                        <tr>
                            <th class="text-muted-foreground px-3 py-2 text-left font-medium">Concepto</th>
                            <th class="text-muted-foreground px-3 py-2 text-right font-medium">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-border divide-y">
                        <tr v-if="Number(shop.no_iva) > 0">
                            <td class="text-muted-foreground px-3 py-1.5">No IVA</td>
                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                ${{ Number(shop.no_iva).toFixed(2) }}
                            </td>
                        </tr>
                        <tr v-if="Number(shop.base0) > 0">
                            <td class="text-muted-foreground px-3 py-1.5">Base 0%</td>
                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                ${{ Number(shop.base0).toFixed(2) }}
                            </td>
                        </tr>
                        <tr v-if="Number(shop.base5) > 0">
                            <td class="text-muted-foreground px-3 py-1.5">Base 5%</td>
                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                ${{ Number(shop.base5).toFixed(2) }}
                            </td>
                        </tr>
                        <tr v-if="Number(shop.iva5) > 0">
                            <td class="text-muted-foreground px-3 py-1.5 pl-5">IVA 5%</td>
                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                ${{ Number(shop.iva5).toFixed(2) }}
                            </td>
                        </tr>
                        <tr v-if="Number(shop.base8) > 0">
                            <td class="text-muted-foreground px-3 py-1.5">Base 8%</td>
                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                ${{ Number(shop.base8).toFixed(2) }}
                            </td>
                        </tr>
                        <tr v-if="Number(shop.iva8) > 0">
                            <td class="text-muted-foreground px-3 py-1.5 pl-5">IVA 8%</td>
                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                ${{ Number(shop.iva8).toFixed(2) }}
                            </td>
                        </tr>
                        <tr v-if="Number(shop.base12) > 0">
                            <td class="text-muted-foreground px-3 py-1.5">Base 12%</td>
                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                ${{ Number(shop.base12).toFixed(2) }}
                            </td>
                        </tr>
                        <tr v-if="Number(shop.iva12) > 0">
                            <td class="text-muted-foreground px-3 py-1.5 pl-5">IVA 12%</td>
                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                ${{ Number(shop.iva12).toFixed(2) }}
                            </td>
                        </tr>
                        <tr v-if="Number(shop.base15) > 0">
                            <td class="text-muted-foreground px-3 py-1.5">Base 15%</td>
                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                ${{ Number(shop.base15).toFixed(2) }}
                            </td>
                        </tr>
                        <tr v-if="Number(shop.iva15) > 0">
                            <td class="text-muted-foreground px-3 py-1.5 pl-5">IVA 15%</td>
                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                ${{ Number(shop.iva15).toFixed(2) }}
                            </td>
                        </tr>
                        <tr v-if="Number(shop.discount) > 0">
                            <td class="text-muted-foreground px-3 py-1.5">Descuento</td>
                            <td class="text-destructive px-3 py-1.5 text-right font-mono">
                                -${{ Number(shop.discount).toFixed(2) }}
                            </td>
                        </tr>
                        <tr v-if="Number(shop.ice) > 0">
                            <td class="text-muted-foreground px-3 py-1.5">ICE</td>
                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                ${{ Number(shop.ice).toFixed(2) }}
                            </td>
                        </tr>
                        <tr class="bg-muted font-semibold">
                            <td class="text-foreground px-3 py-2">Total</td>
                            <td class="text-foreground px-3 py-2 text-right font-mono">
                                ${{ Number(shop.total).toFixed(2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Products section -->
            <div>
                <p class="text-muted-foreground mb-1.5 text-xs font-medium">Productos</p>
                <div v-if="shopItemsLoading" class="text-muted-foreground text-xs">Cargando…</div>
                <div v-else-if="shopItems.length === 0" class="text-muted-foreground text-xs">
                    Sin productos registrados
                </div>
                <div v-else class="border-border max-h-64 overflow-auto rounded-lg border">
                    <table class="w-full text-xs">
                        <thead class="bg-muted sticky top-0">
                            <tr>
                                <th class="text-muted-foreground whitespace-nowrap px-3 py-2 text-left font-medium">
                                    Código
                                </th>
                                <th class="text-muted-foreground whitespace-nowrap px-3 py-2 text-left font-medium">
                                    Descripción
                                </th>
                                <th class="text-muted-foreground whitespace-nowrap px-3 py-2 text-right font-medium">
                                    Cant.
                                </th>
                                <th class="text-muted-foreground whitespace-nowrap px-3 py-2 text-right font-medium">
                                    P. Unit.
                                </th>
                                <th class="text-muted-foreground whitespace-nowrap px-3 py-2 text-right font-medium">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-border divide-y">
                            <tr v-for="item in shopItems" :key="item.id">
                                <td class="text-muted-foreground whitespace-nowrap px-3 py-1.5 font-mono">
                                    {{ item.product.code }}
                                </td>
                                <td class="text-foreground min-w-[12rem] px-3 py-1.5">
                                    {{ item.product.description }}
                                </td>
                                <td class="text-foreground whitespace-nowrap px-3 py-1.5 text-right font-mono">
                                    {{ Number(item.quantity) }}
                                </td>
                                <td class="text-foreground whitespace-nowrap px-3 py-1.5 text-right font-mono">
                                    ${{ Number(item.unit_price).toFixed(2) }}
                                </td>
                                <td class="text-foreground whitespace-nowrap px-3 py-1.5 text-right font-mono">
                                    ${{ Number(item.total).toFixed(2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
