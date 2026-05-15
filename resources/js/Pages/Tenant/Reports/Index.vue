<script setup lang="ts">
import TenantLayout from "@/layouts/TenantLayout.vue";
import HeaderList from "@/components/Shared/HeaderList.vue";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Link } from "@inertiajs/vue3";
import {
    BookOpen,
    FileText,
    Users,
    Percent,
    ReceiptText,
    UserCheck,
    ArrowRight,
} from "lucide-vue-next";

const comprasReports = [
    {
        title: "Mayor analítico",
        description: "Compras agrupadas por cuenta contable con totales de base imponible e IVA.",
        icon: BookOpen,
        route: "tenant.reports.shops-by-account",
    },
    {
        title: "Por tipo de comprobante",
        description: "Desglose de compras según el tipo de documento (factura, nota de crédito, liquidación, etc.).",
        icon: FileText,
        route: "tenant.reports.shops-by-voucher-type",
    },
    {
        title: "Por proveedor",
        description: "Compras agrupadas por proveedor con totales y conteo de comprobantes.",
        icon: Users,
        route: "tenant.reports.shops-by-provider",
    },
    {
        title: "Retenciones",
        description: "Retenciones en la fuente e IVA aplicadas a las compras por período.",
        icon: Percent,
        route: "tenant.reports.shops-by-retention",
    },
];

const ventasReports = [
    {
        title: "Por tipo de comprobante",
        description: "Desglose de ventas según el tipo de documento (factura, nota de crédito, débito, etc.).",
        icon: ReceiptText,
        route: "tenant.reports.orders-by-voucher-type",
    },
    {
        title: "Por cliente",
        description: "Ventas agrupadas por cliente con totales y conteo de comprobantes.",
        icon: UserCheck,
        route: "tenant.reports.orders-by-client",
    },
];

defineOptions({ layout: TenantLayout });
</script>

<template>
    <HeaderList title="Reportes" description="Análisis de compras y ventas por período" />

    <div class="mt-6 space-y-8 px-1">
        <!-- Compras -->
        <section>
            <h2 class="text-muted-foreground mb-3 text-xs font-semibold tracking-widest uppercase">
                Compras
            </h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="report in comprasReports"
                    :key="report.route"
                    :href="route(report.route)"
                    class="group block"
                >
                    <Card class="h-full transition-shadow group-hover:shadow-md">
                        <CardHeader class="pb-2">
                            <div class="flex items-start justify-between">
                                <div class="bg-primary/10 text-primary rounded-lg p-2">
                                    <component :is="report.icon" class="size-5" />
                                </div>
                                <ArrowRight class="text-muted-foreground/40 size-4 transition-transform group-hover:translate-x-0.5 group-hover:text-current" />
                            </div>
                            <CardTitle class="mt-3 text-base">{{ report.title }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-muted-foreground text-sm">{{ report.description }}</p>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </section>

        <!-- Ventas -->
        <section>
            <h2 class="text-muted-foreground mb-3 text-xs font-semibold tracking-widest uppercase">
                Ventas
            </h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="report in ventasReports"
                    :key="report.route"
                    :href="route(report.route)"
                    class="group block"
                >
                    <Card class="h-full transition-shadow group-hover:shadow-md">
                        <CardHeader class="pb-2">
                            <div class="flex items-start justify-between">
                                <div class="bg-primary/10 text-primary rounded-lg p-2">
                                    <component :is="report.icon" class="size-5" />
                                </div>
                                <ArrowRight class="text-muted-foreground/40 size-4 transition-transform group-hover:translate-x-0.5 group-hover:text-current" />
                            </div>
                            <CardTitle class="mt-3 text-base">{{ report.title }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-muted-foreground text-sm">{{ report.description }}</p>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </section>
    </div>
</template>
