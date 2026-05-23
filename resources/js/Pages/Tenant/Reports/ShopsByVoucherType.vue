<script setup lang="ts">
import TenantLayout from "@/layouts/TenantLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useDateRangeFilter } from "@/composables/useDateRangeFilter";
import { IVA_NEW_RATES_START } from "@/constants/ecuador";
import { Download } from "lucide-vue-next";
import { Button } from "@/components/ui/button";

interface Row {
    description: string;
    count: number;
    subtotal: number;
    no_iva: number;
    exempt: number;
    base0: number;
    base5: number;
    base8: number;
    base12: number;
    base15: number;
    iva5: number;
    iva8: number;
    iva12: number;
    iva15: number;
    total: number;
    retentions: number;
    a_pagar: number;
}

interface Filters {
    start_date: string | null;
    end_date: string | null;
    only_authorized: boolean;
}

const props = defineProps<{
    rows: Row[];
    filters: Filters;
}>();

const { startDate, endDate, minDate, maxDate, dateRangeError } = useDateRangeFilter(props.filters.start_date, props.filters.end_date);
const onlyAuthorized = ref(props.filters.only_authorized ?? true);

function applyFilters() {
    if (dateRangeError.value) return;
    router.get(
        route("tenant.reports.shops-by-voucher-type"),
        {
            start_date: startDate.value || undefined,
            end_date: endDate.value || undefined,
            only_authorized: onlyAuthorized.value ? "1" : "0",
        },
        { preserveState: true },
    );
}

function clearFilters() {
    startDate.value = "";
    endDate.value = "";
    onlyAuthorized.value = true;
    router.get(route("tenant.reports.shops-by-voucher-type"), {}, { preserveState: true });
}

function download() {
    const params = new URLSearchParams();
    if (props.filters.start_date) params.set("start_date", props.filters.start_date);
    if (props.filters.end_date) params.set("end_date", props.filters.end_date);
    params.set("only_authorized", props.filters.only_authorized ? "1" : "0");
    window.location.href = route("tenant.reports.shops-by-voucher-type.export") + "?" + params.toString();
}

const fmt = (v: number) => v.toLocaleString("es-EC", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const NEW_RATES_START = IVA_NEW_RATES_START;

const showNewRates = computed(() => {
    const end = props.filters.end_date;
    if (!end) return true;
    return end >= NEW_RATES_START;
});

const showOldRates = computed(() => {
    const start = props.filters.start_date;
    if (!start) return true;
    return start < NEW_RATES_START;
});

const sum = (key: keyof Row) => props.rows.reduce((s, r) => s + (r[key] as number), 0);

const totals = computed(() => ({
    count: sum("count"),
    subtotal: sum("subtotal"),
    no_iva: sum("no_iva"),
    exempt: sum("exempt"),
    base0: sum("base0"),
    base5: sum("base5"),
    base8: sum("base8"),
    base12: sum("base12"),
    base15: sum("base15"),
    iva5: sum("iva5"),
    iva8: sum("iva8"),
    iva12: sum("iva12"),
    iva15: sum("iva15"),
    total: sum("total"),
    retentions: sum("retentions"),
    a_pagar: sum("a_pagar"),
}));
</script>

<template>
    <Head title="Reporte de compras por tipo de comprobante" />

    <TenantLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-foreground text-2xl font-semibold">Compras por Tipo de Comprobante</h1>
                <p class="text-muted-foreground mt-1 text-sm">Resumen de compras agrupado por tipo de comprobante</p>
            </div>
            <Button variant="outline" size="sm" @click="download">
                <Download class="size-4" />
                Descargar
            </Button>
        </div>

        <!-- Filters -->
        <div class="border-border bg-card mb-4 flex flex-wrap items-end gap-3 rounded-lg border p-4">
            <div class="flex flex-col gap-1">
                <label class="text-muted-foreground text-xs font-medium">Fecha desde</label>
                <input
                    v-model="startDate"
                    type="date"
                    :min="minDate"
                    :max="maxDate"
                    class="border-border bg-background text-foreground focus:ring-ring/30 h-8 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
                />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-muted-foreground text-xs font-medium">Fecha hasta</label>
                <input
                    v-model="endDate"
                    type="date"
                    :min="minDate"
                    :max="maxDate"
                    class="border-border bg-background text-foreground focus:ring-ring/30 h-8 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
                />
            </div>
            <div class="flex items-center gap-2 self-center">
                <input id="only-authorized-vt" v-model="onlyAuthorized" type="checkbox" class="border-border size-4 rounded" />
                <label for="only-authorized-vt" class="text-muted-foreground cursor-pointer text-xs font-medium">Solo autorizados</label>
            </div>
            <button
                type="button"
                class="bg-primary text-primary-foreground hover:bg-primary/90 h-8 rounded-md px-4 text-sm font-medium"
                @click="applyFilters"
            >
                Filtrar
            </button>
            <span v-if="dateRangeError" class="text-destructive self-center text-xs">{{ dateRangeError }}</span>
            <button
                v-if="filters.start_date || filters.end_date"
                type="button"
                class="text-muted-foreground hover:text-foreground h-8 rounded-md px-3 text-sm"
                @click="clearFilters"
            >
                Limpiar
            </button>
        </div>

        <!-- Table -->
        <div class="border-border bg-card overflow-x-auto rounded-lg border">
            <div v-if="rows.length === 0" class="text-muted-foreground p-6 text-sm">
                No hay compras registradas para los filtros seleccionados.
            </div>

            <table v-else class="divide-border min-w-full divide-y">
                <thead class="bg-muted">
                    <tr>
                        <th class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase">Tipo de Comprobante</th>
                        <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">Cantidad</th>
                        <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">Subtotal</th>
                        <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">No IVA</th>
                        <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">Excenta</th>
                        <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">Base 0%</th>
                        <th v-if="showNewRates" class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">Base 5%</th>
                        <th v-if="showNewRates" class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">Base 8%</th>
                        <th v-if="showOldRates" class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">Base 12%</th>
                        <th v-if="showNewRates" class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">Base 15%</th>
                        <th v-if="showNewRates" class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">IVA 5%</th>
                        <th v-if="showNewRates" class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">IVA 8%</th>
                        <th v-if="showOldRates" class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">IVA 12%</th>
                        <th v-if="showNewRates" class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">IVA 15%</th>
                        <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">Total</th>
                        <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">Retenciones</th>
                        <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">A Pagar</th>
                    </tr>
                </thead>
                <tbody class="divide-border bg-card divide-y">
                    <tr v-for="row in rows" :key="row.description">
                        <td class="px-4 py-3 text-sm">
                            <span class="text-foreground font-medium">{{ row.description }}</span>
                        </td>
                        <td class="text-muted-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ row.count }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.subtotal) }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.no_iva) }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.exempt) }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.base0) }}</td>
                        <td v-if="showNewRates" class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.base5) }}</td>
                        <td v-if="showNewRates" class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.base8) }}</td>
                        <td v-if="showOldRates" class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.base12) }}</td>
                        <td v-if="showNewRates" class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.base15) }}</td>
                        <td v-if="showNewRates" class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.iva5) }}</td>
                        <td v-if="showNewRates" class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.iva8) }}</td>
                        <td v-if="showOldRates" class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.iva12) }}</td>
                        <td v-if="showNewRates" class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.iva15) }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.total) }}</td>
                        <td
                            class="px-4 py-3 text-right font-mono text-sm tabular-nums"
                            :class="row.retentions > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'"
                        >
                            {{ fmt(row.retentions) }}
                        </td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(row.a_pagar) }}</td>
                    </tr>
                </tbody>
                <tfoot class="bg-muted border-border border-t-2">
                    <tr>
                        <td class="text-foreground px-4 py-3 text-sm font-semibold">Total general</td>
                        <td class="text-muted-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ totals.count }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.subtotal) }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.no_iva) }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.exempt) }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.base0) }}</td>
                        <td v-if="showNewRates" class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.base5) }}</td>
                        <td v-if="showNewRates" class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.base8) }}</td>
                        <td v-if="showOldRates" class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.base12) }}</td>
                        <td v-if="showNewRates" class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.base15) }}</td>
                        <td v-if="showNewRates" class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.iva5) }}</td>
                        <td v-if="showNewRates" class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.iva8) }}</td>
                        <td v-if="showOldRates" class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.iva12) }}</td>
                        <td v-if="showNewRates" class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.iva15) }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.total) }}</td>
                        <td
                            class="px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums"
                            :class="totals.retentions > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'"
                        >
                            {{ fmt(totals.retentions) }}
                        </td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-bold tabular-nums">{{ fmt(totals.a_pagar) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </TenantLayout>
</template>
