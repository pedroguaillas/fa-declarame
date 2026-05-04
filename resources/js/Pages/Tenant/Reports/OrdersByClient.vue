<script setup lang="ts">
import TenantLayout from "@/layouts/TenantLayout.vue";
import { Head, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import { Download } from "lucide-vue-next";
import { Button } from "@/components/ui/button";

interface Row {
    identification: string;
    name: string;
    subtotal: number;
    iva: number;
    total: number;
    retentions: number;
    a_cobrar: number;
}

interface Filters {
    start_date: string | null;
    end_date: string | null;
}

const props = defineProps<{
    rows: Row[];
    filters: Filters;
}>();

const startDate = ref(props.filters.start_date ?? "");
const endDate = ref(props.filters.end_date ?? "");

function applyFilters() {
    router.get(
        route("tenant.reports.orders-by-client"),
        {
            start_date: startDate.value || undefined,
            end_date: endDate.value || undefined,
        },
        { preserveState: true },
    );
}

function clearFilters() {
    startDate.value = "";
    endDate.value = "";
    router.get(route("tenant.reports.orders-by-client"), {}, { preserveState: true });
}

function download() {
    const params = new URLSearchParams();
    if (props.filters.start_date) params.set("start_date", props.filters.start_date);
    if (props.filters.end_date) params.set("end_date", props.filters.end_date);
    window.location.href = route("tenant.reports.orders-by-client.export") + "?" + params.toString();
}

const fmt = (v: number) => v.toLocaleString("es-EC", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const totals = computed(() => ({
    subtotal: props.rows.reduce((s, r) => s + r.subtotal, 0),
    iva: props.rows.reduce((s, r) => s + r.iva, 0),
    total: props.rows.reduce((s, r) => s + r.total, 0),
    retentions: props.rows.reduce((s, r) => s + r.retentions, 0),
    a_cobrar: props.rows.reduce((s, r) => s + r.a_cobrar, 0),
}));
</script>

<template>
    <Head title="Ventas por Cliente" />

    <TenantLayout>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-foreground text-2xl font-semibold">Ventas por Cliente</h1>
                <p class="text-muted-foreground mt-1 text-sm">Resumen de ventas agrupado por cliente</p>
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
                    class="border-border bg-background text-foreground focus:ring-ring/30 h-8 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
                />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-muted-foreground text-xs font-medium">Fecha hasta</label>
                <input
                    v-model="endDate"
                    type="date"
                    class="border-border bg-background text-foreground focus:ring-ring/30 h-8 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
                />
            </div>
            <button
                type="button"
                class="bg-primary text-primary-foreground hover:bg-primary/90 h-8 rounded-md px-4 text-sm font-medium"
                @click="applyFilters"
            >
                Filtrar
            </button>
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
                No hay ventas registradas para los filtros seleccionados.
            </div>

            <table v-else class="divide-border min-w-full divide-y">
                <thead class="bg-muted">
                    <tr>
                        <th class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase">
                            Cliente
                        </th>
                        <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">
                            Subtotal
                        </th>
                        <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">
                            IVA
                        </th>
                        <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">
                            Total
                        </th>
                        <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">
                            Retenciones
                        </th>
                        <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase">
                            A Cobrar
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-border bg-card divide-y">
                    <tr v-for="row in rows" :key="row.identification">
                        <td class="px-4 py-3 text-sm">
                            <p class="text-foreground font-medium">{{ row.name }}</p>
                            <p class="text-muted-foreground font-mono text-xs">{{ row.identification }}</p>
                        </td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.subtotal) }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.iva) }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm tabular-nums">{{ fmt(row.total) }}</td>
                        <td
                            class="px-4 py-3 text-right font-mono text-sm tabular-nums"
                            :class="row.retentions > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'"
                        >
                            {{ fmt(row.retentions) }}
                        </td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">
                            {{ fmt(row.a_cobrar) }}
                        </td>
                    </tr>
                </tbody>
                <tfoot class="bg-muted border-border border-t-2">
                    <tr>
                        <td class="text-foreground px-4 py-3 text-sm font-semibold">Total general</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.subtotal) }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.iva) }}</td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums">{{ fmt(totals.total) }}</td>
                        <td
                            class="px-4 py-3 text-right font-mono text-sm font-semibold tabular-nums"
                            :class="totals.retentions > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'"
                        >
                            {{ fmt(totals.retentions) }}
                        </td>
                        <td class="text-foreground px-4 py-3 text-right font-mono text-sm font-bold tabular-nums">
                            {{ fmt(totals.a_cobrar) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </TenantLayout>
</template>
