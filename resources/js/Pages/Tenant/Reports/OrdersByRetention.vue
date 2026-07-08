<script setup lang="ts">
import TenantLayout from "@/layouts/TenantLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { useDateRangeFilter } from "@/composables/useDateRangeFilter";
import { Download } from "lucide-vue-next";
import { Button } from "@/components/ui/button";

interface Row {
    code: string;
    description: string;
    percentage: number;
    base: number;
    value: number;
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
        route("tenant.reports.orders-by-retention"),
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
    router.get(route("tenant.reports.orders-by-retention"), {}, { preserveState: true });
}

function download() {
    const params = new URLSearchParams();
    if (props.filters.start_date) params.set("start_date", props.filters.start_date);
    if (props.filters.end_date) params.set("end_date", props.filters.end_date);
    params.set("only_authorized", props.filters.only_authorized ? "1" : "0");
    window.location.href = route("tenant.reports.orders-by-retention.export") + "?" + params.toString();
}

const fmt = (v: number) => v.toLocaleString("es-EC", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const totals = computed(() => ({
    base: props.rows.reduce((s, r) => s + r.base, 0),
    value: props.rows.reduce((s, r) => s + r.value, 0),
}));
</script>

<template>
    <Head title="Reporte de ventas por retención" />

    <TenantLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-foreground text-2xl font-semibold">Ventas por Retenciones</h1>
                <p class="text-muted-foreground mt-1 text-sm">Retenciones de renta recibidas agrupadas por código</p>
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
                <input id="only-authorized-order-retention" v-model="onlyAuthorized" type="checkbox" class="border-border size-4 rounded" />
                <label for="only-authorized-order-retention" class="text-muted-foreground cursor-pointer text-xs font-medium">Solo autorizados</label>
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
        <div class="border-border bg-card overflow-hidden rounded-lg border">
            <div v-if="rows.length === 0" class="text-muted-foreground p-6 text-sm">
                No hay retenciones de renta recibidas para los filtros seleccionados.
            </div>

            <table v-else class="divide-border min-w-full divide-y">
                <thead class="bg-muted">
                    <tr>
                        <th
                            class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
                        >
                            Código
                        </th>
                        <th
                            class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
                        >
                            Descripción
                        </th>
                        <th
                            class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase"
                        >
                            Retenido %
                        </th>
                        <th
                            class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase"
                        >
                            Base
                        </th>
                        <th
                            class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase"
                        >
                            Valor
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-border divide-y">
                    <tr v-for="row in rows" :key="row.code">
                        <td class="text-foreground px-4 py-3 font-mono text-sm font-medium">
                            {{ row.code }}
                        </td>
                        <td class="text-foreground px-4 py-3 text-sm">
                            {{ row.description }}
                        </td>
                        <td class="text-muted-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">
                            {{ row.percentage }}%
                        </td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">
                            {{ fmt(row.base) }}
                        </td>
                        <td
                            class="px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums"
                            :class="row.value > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground'"
                        >
                            {{ fmt(row.value) }}
                        </td>
                    </tr>
                </tbody>
                <tfoot class="border-border bg-muted border-t-2">
                    <tr>
                        <td colspan="3" class="text-foreground px-4 py-3 text-sm font-semibold">Total general</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">
                            {{ fmt(totals.base) }}
                        </td>
                        <td
                            class="px-4 py-3 text-right font-mono text-sm font-bold tabular-nums"
                            :class="totals.value > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground'"
                        >
                            {{ fmt(totals.value) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </TenantLayout>
</template>
