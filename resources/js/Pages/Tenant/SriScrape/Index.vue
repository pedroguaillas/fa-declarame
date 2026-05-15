<script setup lang="ts">
import { Head, useForm, usePage, Link } from "@inertiajs/vue3";
import { ref, onMounted, onUnmounted, computed, watch } from "vue";

import TenantLayout from "@/layouts/TenantLayout.vue";
import HeaderList from "@/components/Shared/HeaderList.vue";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import {
    CloudDownload,
    Loader2,
    CheckCircle2,
    XCircle,
    Clock,
    AlertTriangle,
    ShoppingCart,
    ReceiptIndianRupee,
    Sheet,
    ArrowRight,
} from "lucide-vue-next";

// ─── Types ──────────────────────────────────────────────────────────────────

interface ScrapeJob {
    id: number;
    type: string;
    year: number;
    month: number;
    mode: string;
    status: string;
    progress: { step: string; message: string } | null;
    result: { imported: number; skipped: number; errors: number } | null;
    error_message: string | null;
    created_at: string;
    completed_at: string | null;
}

// ─── Props ──────────────────────────────────────────────────────────────────

const props = defineProps<{
    jobs: ScrapeJob[];
    hasPassword: boolean;
    hasCaptchaKey: boolean;
}>();

// ─── State ──────────────────────────────────────────────────────────────────

const currentYear = new Date().getFullYear();
const currentMonth = new Date().getMonth(); // 0-indexed, so previous month
const previousMonth = currentMonth === 0 ? 12 : currentMonth;
const previousYear = currentMonth === 0 ? currentYear - 1 : currentYear;

const form = useForm({
    type: "compras",
    year: previousYear,
    month: previousMonth,
    voucher_types: ["1", "3", "4"] as string[],
});

const selectedVoucherTypes = ref<string[]>(["1"]);

const jobsList = ref<ScrapeJob[]>(props.jobs);
let pollInterval: ReturnType<typeof setInterval> | null = null;

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string });

// ─── Voucher type options ──────────────────────────────────────────────────

const voucherTypesByMode: Record<string, { value: string; label: string }[]> = {
    compras: [
        { value: "1", label: "Facturas" },
        { value: "3", label: "Notas de Crédito" },
        { value: "4", label: "Notas de Débito" },
        { value: "6", label: "Retenciones Recibidas" },
    ],
    ventas: [
        { value: "1", label: "Facturas" },
        { value: "3", label: "Notas de Crédito" },
        { value: "4", label: "Notas de Débito" },
        { value: "6", label: "Retenciones Emitidas" },
    ],
};

const voucherTypeOptions = computed(() => voucherTypesByMode[form.type] ?? voucherTypesByMode.compras);

function toggleVoucherType(value: string) {
    if (selectedVoucherTypes.value.includes(value)) {
        selectedVoucherTypes.value = selectedVoucherTypes.value.filter((v) => v !== value);
    } else if (value === "6") {
        // Retenciones es un proceso exclusivo: deseleccionar todo lo demás
        selectedVoucherTypes.value = ["6"];
    } else {
        // Al seleccionar otro tipo, quitar retenciones si estaba activa
        selectedVoucherTypes.value = [...selectedVoucherTypes.value.filter((v) => v !== "6"), value];
    }
}

// Al cambiar tipo, resetear selección por defecto
watch(() => form.type, () => {
    selectedVoucherTypes.value = ["1", "3", "4"];
});

// ─── Years & Months ─────────────────────────────────────────────────────────

const years = Array.from({ length: 5 }, (_, i) => currentYear - i);

const months = [
    { value: 1, label: "Enero" },
    { value: 2, label: "Febrero" },
    { value: 3, label: "Marzo" },
    { value: 4, label: "Abril" },
    { value: 5, label: "Mayo" },
    { value: 6, label: "Junio" },
    { value: 7, label: "Julio" },
    { value: 8, label: "Agosto" },
    { value: 9, label: "Septiembre" },
    { value: 10, label: "Octubre" },
    { value: 11, label: "Noviembre" },
    { value: 12, label: "Diciembre" },
];

// ─── Status helpers ─────────────────────────────────────────────────────────

const statusConfig: Record<
    string,
    { label: string; class: string; icon: any }
> = {
    pending: {
        label: "Pendiente",
        class: "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400 border-0",
        icon: Clock,
    },
    running: {
        label: "En progreso",
        class: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-0",
        icon: Loader2,
    },
    completed: {
        label: "Completado",
        class: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 border-0",
        icon: CheckCircle2,
    },
    failed: {
        label: "Error",
        class: "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 border-0",
        icon: XCircle,
    },
};

const monthName = (m: number) => months.find((mo) => mo.value === m)?.label ?? m;

const hasActiveJobs = computed(() =>
    jobsList.value.some((j) => j.status === "pending" || j.status === "running")
);

const hasSelectedVouchers = computed(() => selectedVoucherTypes.value.length > 0);

// ─── Polling ────────────────────────────────────────────────────────────────

async function pollStatus() {
    try {
        const response = await fetch(route("tenant.sri-scrape.status"));
        const data = await response.json();
        jobsList.value = data.jobs;
    } catch {
        // Ignore polling errors
    }
}

function startPolling() {
    if (pollInterval) return;
    pollInterval = setInterval(pollStatus, 3000);
}

function stopPolling() {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

onMounted(() => {
    if (hasActiveJobs.value) {
        startPolling();
    }
});

onUnmounted(() => {
    stopPolling();
});

watch(hasActiveJobs, (active) => {
    if (active) {
        startPolling();
    } else {
        stopPolling();
    }
});

// ─── Submit ─────────────────────────────────────────────────────────────────

function submit() {
    form.voucher_types = selectedVoucherTypes.value;
    form.post(route("tenant.sri-scrape.store"), {
        preserveScroll: true,
        onSuccess: () => {
            pollStatus();
            startPolling();
        },
    });
}

defineOptions({ layout: TenantLayout });
</script>

<template>
    <HeaderList title="Comprobantes SRI" description="Descarga masiva" />

    <div class="mt-6 space-y-6 px-1">
        <!-- Alerts -->
        <Alert v-if="!hasPassword" variant="destructive">
            <AlertTriangle class="size-4" />
            <AlertTitle>Clave SRI no configurada</AlertTitle>
            <AlertDescription>
                Configure la clave del SRI en la configuración de la empresa antes de usar esta función.
            </AlertDescription>
        </Alert>

        <Alert v-if="flash?.success" class="border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
            <CheckCircle2 class="size-4" />
            <AlertDescription>{{ flash.success }}</AlertDescription>
        </Alert>

        <Alert v-if="flash?.error" variant="destructive">
            <XCircle class="size-4" />
            <AlertDescription>{{ flash.error }}</AlertDescription>
        </Alert>

        <!-- Form -->
        <Card>
            <CardHeader>
                <CardTitle class="text-lg">Nueva descarga</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium">Tipo</label>
                            <Select v-model="form.type">
                                <SelectTrigger class="w-[180px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="compras">Compras (Recibidos)</SelectItem>
                                    <SelectItem value="ventas">Ventas (Emitidos)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium">Año</label>
                            <Select v-model="form.year">
                                <SelectTrigger class="w-[120px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="y in years"
                                        :key="y"
                                        :value="y"
                                    >
                                        {{ y }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium">Mes</label>
                            <Select v-model="form.month">
                                <SelectTrigger class="w-[160px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="m in months"
                                        :key="m.value"
                                        :value="m.value"
                                    >
                                        {{ m.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <Button
                            type="submit"
                            :disabled="form.processing || !hasPassword || !hasSelectedVouchers"
                            class="font-bold"
                        >
                            <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                            <CloudDownload v-else class="size-4" />
                            Descargar del SRI
                        </Button>
                    </div>

                    <!-- Voucher type switches -->
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium">Comprobantes</label>
                        <div class="flex flex-wrap gap-6">
                            <label
                                v-for="vt in voucherTypeOptions"
                                :key="vt.value"
                                class="flex cursor-pointer items-center gap-2"
                            >
                                <Switch
                                    :model-value="selectedVoucherTypes.includes(vt.value)"
                                    @update:model-value="toggleVoucherType(vt.value)"
                                />
                                <span class="text-sm">{{ vt.label }}</span>
                            </label>
                        </div>
                        <p v-if="form.type === 'ventas'" class="text-muted-foreground text-xs">
                            En emitidos se consulta día por día. Seleccione solo los tipos necesarios para mayor agilidad.
                        </p>
                        <p v-if="selectedVoucherTypes.includes('6') && form.type === 'compras'" class="text-xs text-amber-600 dark:text-amber-400">
                            Las retenciones recibidas se importarán en Ventas. No se pueden combinar con otros tipos.
                        </p>
                        <p v-if="selectedVoucherTypes.includes('6') && form.type === 'ventas'" class="text-xs text-amber-600 dark:text-amber-400">
                            Las retenciones emitidas se importarán en Compras. No se pueden combinar con otros tipos.
                        </p>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Jobs List -->
        <Card>
            <CardHeader>
                <CardTitle class="text-lg">Descargas recientes</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="jobsList.length === 0" class="text-muted-foreground py-8 text-center text-sm">
                    No hay descargas recientes.
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="job in jobsList"
                        :key="job.id"
                        class="flex flex-col gap-2 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold uppercase">
                                    {{ job.type }}
                                </span>
                                <span class="text-muted-foreground text-sm">
                                    {{ monthName(job.month) }} {{ job.year }}
                                </span>
                                <Badge :class="statusConfig[job.status]?.class">
                                    {{ statusConfig[job.status]?.label ?? job.status }}
                                </Badge>
                            </div>

                            <!-- Progress message -->
                            <p
                                v-if="job.status === 'running' && job.progress"
                                class="text-muted-foreground flex items-center gap-1.5 text-xs"
                            >
                                <Loader2 class="size-3 animate-spin" />
                                {{ job.progress.message }}
                            </p>

                            <!-- Result -->
                            <p
                                v-if="job.status === 'completed' && job.result"
                                class="text-xs text-green-600 dark:text-green-400"
                            >
                                {{ job.result.imported }} importados,
                                {{ job.result.skipped }} omitidos,
                                {{ job.result.errors }} errores
                            </p>

                            <!-- Error -->
                            <p
                                v-if="job.status === 'failed' && job.error_message"
                                class="text-xs text-red-600 dark:text-red-400"
                            >
                                {{ job.error_message }}
                            </p>
                        </div>

                        <div class="flex flex-col items-end gap-2">
                            <span class="text-muted-foreground text-xs">
                                {{ new Date(job.created_at).toLocaleString("es-EC") }}
                            </span>

                            <!-- Quick access buttons for completed jobs -->
                            <div v-if="job.status === 'completed'" class="flex gap-2">
                                <Link
                                    :href="route(job.type === 'compras' ? 'tenant.shops.index' : 'tenant.orders.index')"
                                    class="inline-flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1 text-xs font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                                >
                                    <ShoppingCart v-if="job.type === 'compras'" class="size-3" />
                                    <ReceiptIndianRupee v-else class="size-3" />
                                    {{ job.type === 'compras' ? 'Ver Compras' : 'Ver Ventas' }}
                                    <ArrowRight class="size-3 opacity-60" />
                                </Link>
                                <Link
                                    :href="route('tenant.reports.index')"
                                    class="inline-flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1 text-xs font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                                >
                                    <Sheet class="size-3" />
                                    Reportes
                                    <ArrowRight class="size-3 opacity-60" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
