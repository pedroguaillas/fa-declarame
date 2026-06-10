<script setup lang="ts">
import TenantLayout from "@/layouts/TenantLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useDateRangeFilter } from "@/composables/useDateRangeFilter";
import { Download } from "lucide-vue-next";
import { Button } from "@/components/ui/button";

interface Row {
    account_code: string | null;
    account_name: string;
    subtotal: number;
    iva: number;
    total: number;
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
        route("tenant.reports.shops-by-account"),
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
    router.get(route("tenant.reports.shops-by-account"), {}, { preserveState: true });
}

function download() {
    const params = new URLSearchParams();
    if (props.filters.start_date) params.set("start_date", props.filters.start_date);
    if (props.filters.end_date) params.set("end_date", props.filters.end_date);
    params.set("only_authorized", props.filters.only_authorized ? "1" : "0");
    window.location.href = route("tenant.reports.shops-by-account.export") + "?" + params.toString();
}

const fmt = (v: number) => v.toLocaleString("es-EC", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const sum = (key: keyof Row) => props.rows.reduce((s, r) => s + (r[key] as number), 0);

const totals = computed(() => ({
    subtotal: sum("subtotal"),
    iva: sum("iva"),
    total: sum("total"),
}));
</script>

<template>
    <Head title="Reporte de compras por cuenta contable" />

    <TenantLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-foreground text-2xl font-semibold">Compras por Cuentas</h1>
                <p class="text-muted-foreground mt-1 text-sm">Resumen de compras agrupado por cuenta contable</p>
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
                <input id="only-authorized-account" v-model="onlyAuthorized" type="checkbox" class="border-border size-4 rounded" />
                <label for="only-authorized-account" class="text-muted-foreground cursor-pointer text-xs font-medium">Solo autorizados</label>
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
                        <th class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase">Cuenta Contable</th>
                        <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">Subtotal</th>
                        <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">IVA</th>
                        <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-border bg-card divide-y">
                    <tr v-for="row in rows" :key="row.account_name">
                        <td class="px-4 py-3 text-sm">
                            <span v-if="row.account_code" class="text-muted-foreground mr-2 font-mono text-xs">{{ row.account_code }}</span>
                            <span class="text-foreground font-medium">{{ row.account_name }}</span>
                        </td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.subtotal) }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.iva) }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(row.total) }}</td>
                    </tr>
                </tbody>
                <tfoot class="bg-muted border-border border-t-2">
                    <tr>
                        <td class="text-foreground px-4 py-3 text-sm font-semibold">Total general</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.subtotal) }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.iva) }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-bold tabular-nums">{{ fmt(totals.total) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </TenantLayout>
</template>
