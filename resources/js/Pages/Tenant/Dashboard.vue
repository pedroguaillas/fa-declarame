<script setup lang="ts">
import TenantLayout from "@/layouts/TenantLayout.vue";
import { Head } from "@inertiajs/vue3";
import { computed, ref } from "vue";

interface PeriodStats {
    count: number;
    total: number;
    iva: number;
}

interface Period {
    sales: PeriodStats;
    purchases: PeriodStats;
}

interface TrendPoint {
    month: string;
    sales: number;
    purchases: number;
}

interface Provider {
    name: string;
    identification: string;
    total: number;
    count: number;
}

const props = defineProps<{
    month: Period;
    year: Period;
    monthLabel: string;
    yearLabel: string;
    trend: TrendPoint[];
    topProviders: Provider[];
}>();

const activePeriod = ref<"month" | "year">("month");
const stats = computed(() => (activePeriod.value === "month" ? props.month : props.year));

const balance = computed(() => stats.value.sales.total - stats.value.purchases.total);
const ivaNet = computed(() => stats.value.sales.iva - stats.value.purchases.iva);

function money(n: number): string {
    return new Intl.NumberFormat("es-EC", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
}

// ── Chart ─────────────────────────────────────────────────────────────────────

const MONTHS_ES = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];

function chartMonthLabel(ym: string): string {
    const [, m] = ym.split("-");
    return MONTHS_ES[parseInt(m) - 1];
}

function formatPeriodLabel(ym: string): string {
    const [y, m] = ym.split("-");
    return `${MONTHS_ES[parseInt(m) - 1]} ${y}`;
}

const chartMax = computed(() => {
    const max = Math.max(...props.trend.flatMap((t) => [t.sales, t.purchases]), 1);
    // Round up to a nice number
    const magnitude = Math.pow(10, Math.floor(Math.log10(max)));
    return Math.ceil(max / magnitude) * magnitude;
});

function barHeight(value: number): number {
    return Math.round((value / chartMax.value) * 100);
}


</script>

<template>
    <Head title="Panel de control — Resumen tributario" />

    <TenantLayout>
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-foreground text-2xl font-semibold">Panel de control</h1>

            <div class="flex items-center gap-3">
                <!-- Period toggle -->
                <div class="border-border flex rounded-lg border p-0.5">
                    <button
                        type="button"
                        class="rounded-md px-4 py-1.5 text-sm font-medium transition-colors"
                        :class="
                            activePeriod === 'month'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="activePeriod = 'month'"
                    >
                        {{ formatPeriodLabel(monthLabel) }}
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-4 py-1.5 text-sm font-medium transition-colors"
                        :class="
                            activePeriod === 'year'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="activePeriod = 'year'"
                    >
                        {{ yearLabel }}
                    </button>
                </div>
            </div>
        </div>

        <!-- KPI row 1: Ventas / Compras / Balance -->
        <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <!-- Ventas -->
            <div class="border-border bg-card rounded-xl border p-5">
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-muted-foreground text-sm font-medium">Ventas</p>
                    <div class="bg-blue-100 dark:bg-blue-900/30 rounded-lg p-2">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="text-blue-600 dark:text-blue-400 size-4"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"
                            />
                        </svg>
                    </div>
                </div>
                <p class="text-foreground text-2xl font-bold tabular-nums">${{ money(stats.sales.total) }}</p>
                <p class="text-muted-foreground mt-1 text-sm">
                    {{ stats.sales.count }} comprobante{{ stats.sales.count !== 1 ? "s" : "" }}
                </p>
            </div>

            <!-- Compras -->
            <div class="border-border bg-card rounded-xl border p-5">
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-muted-foreground text-sm font-medium">Compras</p>
                    <div class="bg-orange-100 dark:bg-orange-900/30 rounded-lg p-2">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="text-orange-600 dark:text-orange-400 size-4"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"
                            />
                        </svg>
                    </div>
                </div>
                <p class="text-foreground text-2xl font-bold tabular-nums">${{ money(stats.purchases.total) }}</p>
                <p class="text-muted-foreground mt-1 text-sm">
                    {{ stats.purchases.count }} comprobante{{ stats.purchases.count !== 1 ? "s" : "" }}
                </p>
            </div>

            <!-- Balance -->
            <div class="border-border bg-card rounded-xl border p-5">
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-muted-foreground text-sm font-medium">Balance comercial</p>
                    <div
                        class="rounded-lg p-2"
                        :class="balance >= 0 ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30'"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="size-4"
                            :class="
                                balance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
                            "
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"
                            />
                        </svg>
                    </div>
                </div>
                <p
                    class="text-2xl font-bold tabular-nums"
                    :class="balance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                >
                    {{ balance >= 0 ? "+" : "" }}${{ money(balance) }}
                </p>
                <p class="text-muted-foreground mt-1 text-sm">ventas − compras</p>
            </div>
        </div>

        <!-- KPI row 2: IVA cobrado / IVA pagado / IVA neto -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="border-border bg-card rounded-xl border p-5">
                <p class="text-muted-foreground mb-1 text-xs font-medium uppercase tracking-wider">IVA cobrado</p>
                <p class="text-foreground text-xl font-semibold tabular-nums">${{ money(stats.sales.iva) }}</p>
                <p class="text-muted-foreground mt-0.5 text-xs">en ventas</p>
            </div>

            <div class="border-border bg-card rounded-xl border p-5">
                <p class="text-muted-foreground mb-1 text-xs font-medium uppercase tracking-wider">IVA pagado</p>
                <p class="text-foreground text-xl font-semibold tabular-nums">${{ money(stats.purchases.iva) }}</p>
                <p class="text-muted-foreground mt-0.5 text-xs">en compras</p>
            </div>

            <div class="border-border bg-card rounded-xl border p-5">
                <p class="text-muted-foreground mb-1 text-xs font-medium uppercase tracking-wider">IVA neto</p>
                <p
                    class="text-xl font-semibold tabular-nums"
                    :class="ivaNet >= 0 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400'"
                >
                    ${{ money(Math.abs(ivaNet)) }}
                </p>
                <p class="text-muted-foreground mt-0.5 text-xs">
                    {{ ivaNet >= 0 ? "a pagar al SRI" : "a favor" }}
                </p>
            </div>
        </div>

        <!-- Bottom row: Chart + Top Providers -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
            <!-- Monthly trend chart -->
            <div class="border-border bg-card lg:col-span-3 rounded-xl border p-5">
                <p class="text-foreground mb-4 text-sm font-semibold">Tendencia 12 meses</p>

                <!-- Legend -->
                <div class="mb-4 flex items-center gap-4">
                    <div class="flex items-center gap-1.5">
                        <span class="bg-blue-500 inline-block h-2.5 w-2.5 rounded-sm"></span>
                        <span class="text-muted-foreground text-xs">Ventas</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="bg-orange-400 inline-block h-2.5 w-2.5 rounded-sm"></span>
                        <span class="text-muted-foreground text-xs">Compras</span>
                    </div>
                </div>

                <!-- Bars -->
                <div class="flex h-40 items-end gap-1.5">
                    <div
                        v-for="point in trend"
                        :key="point.month"
                        class="group flex flex-1 flex-col items-center gap-0.5"
                    >
                        <!-- Bars container -->
                        <div class="flex h-32 w-full items-end gap-0.5">
                            <div
                                class="bg-blue-500 hover:bg-blue-600 relative flex-1 cursor-default rounded-t-sm transition-colors"
                                :style="{ height: barHeight(point.sales) + '%' }"
                                :title="`Ventas: $${money(point.sales)}`"
                            >
                                <!-- Tooltip on hover -->
                                <div
                                    class="absolute bottom-full left-1/2 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-foreground px-2 py-1 text-xs text-background group-hover:block"
                                >
                                    ${{ money(point.sales) }}
                                </div>
                            </div>
                            <div
                                class="bg-orange-400 hover:bg-orange-500 relative flex-1 cursor-default rounded-t-sm transition-colors"
                                :style="{ height: barHeight(point.purchases) + '%' }"
                                :title="`Compras: $${money(point.purchases)}`"
                            >
                                <div
                                    class="absolute bottom-full left-1/2 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-foreground px-2 py-1 text-xs text-background group-hover:block"
                                >
                                    ${{ money(point.purchases) }}
                                </div>
                            </div>
                        </div>
                        <!-- Month label -->
                        <span class="text-muted-foreground text-[10px]">{{ chartMonthLabel(point.month) }}</span>
                    </div>
                </div>
            </div>

            <!-- Top providers -->
            <div class="border-border bg-card lg:col-span-2 rounded-xl border p-5">
                <p class="text-foreground mb-4 text-sm font-semibold">Top proveedores {{ yearLabel }}</p>

                <div v-if="topProviders.length === 0" class="text-muted-foreground py-8 text-center text-sm">
                    Sin compras registradas
                </div>

                <ul v-else class="space-y-3">
                    <li
                        v-for="(provider, idx) in topProviders"
                        :key="provider.identification"
                        class="flex items-center gap-3"
                    >
                        <span class="text-muted-foreground w-4 shrink-0 text-right text-xs font-medium">{{
                            idx + 1
                        }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-foreground truncate text-sm font-medium">{{ provider.name }}</p>
                            <p class="text-muted-foreground font-mono text-xs">{{ provider.identification }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-foreground text-sm font-semibold tabular-nums">
                                ${{ money(provider.total) }}
                            </p>
                            <p class="text-muted-foreground text-xs">
                                {{ provider.count }} doc{{ provider.count !== 1 ? "s" : "" }}
                            </p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

    </TenantLayout>
</template>
