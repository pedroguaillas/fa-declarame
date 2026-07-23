<script setup lang="ts">
import { Head, useForm, usePage, Link, router } from "@inertiajs/vue3";
import { ref, onMounted, onUnmounted, computed, watch } from "vue";

import TenantLayout from "@/layouts/TenantLayout.vue";
import HeaderList from "@/components/Shared/HeaderList.vue";
import Pagination from "@/components/Shared/Pagination.vue";

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
    HardDriveDownload,
    Loader2,
    CheckCircle2,
    XCircle,
    Clock,
    AlertTriangle,
    ShoppingCart,
    ReceiptIndianRupee,
    Sheet,
    ArrowRight,
    Bot,
    Copy,
    Check,
    RefreshCw,
    Terminal,
} from "lucide-vue-next";
import { Paginator } from "@/types";

// ─── Types ──────────────────────────────────────────────────────────────────

interface ScrapeJob {
    id: number;
    type: string;
    year: number;
    month: number;
    end_month: number | null;
    day: number | null;
    mode: string;
    source: string;
    voucher_types: string[] | null;
    status: string;
    progress: { step: string; message: string } | null;
    result: { imported: number; skipped: number; errors: number } | null;
    error_message: string | null;
    created_at: string;
    completed_at: string | null;
}

// ─── Props ──────────────────────────────────────────────────────────────────

const props = defineProps<{
    jobs: Paginator<ScrapeJob>;
    hasPassword: boolean;
    hasCaptchaKey: boolean;
    isRetentionAgent: boolean;
    typeDeclaration: string | null;
    agentInstallUrl: string;
}>();

// ─── State ──────────────────────────────────────────────────────────────────

const currentYear = new Date().getFullYear();
const currentMonth = new Date().getMonth(); // 0-indexed
const previousMonth = currentMonth === 0 ? 12 : currentMonth;
const previousYear = currentMonth === 0 ? currentYear - 1 : currentYear;

const form = useForm({
    type: "compras",
    year: previousYear,
    month: previousMonth,
    day: null as number | null,
    voucher_types: ["1", "3", "4"] as string[],
    full_semester: false,
});

const selectedVoucherTypes = ref<string[]>(["1"]);

const jobsList = ref<ScrapeJob[]>(props.jobs.data);
const jobsMeta = ref(props.jobs);
let pollInterval: ReturnType<typeof setInterval> | null = null;

// Sync when Inertia updates props (page navigation)
watch(() => props.jobs, (newJobs) => {
    jobsList.value = newJobs.data;
    jobsMeta.value = newJobs;
});

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

const voucherTypeOptions = computed(() => {
    const options = voucherTypesByMode[form.type] ?? voucherTypesByMode.compras;
    if (form.type === 'ventas' && !props.isRetentionAgent) {
        return options.filter((o) => o.value !== '6');
    }
    return options;
});

function toggleVoucherType(value: string) {
    if (selectedVoucherTypes.value.includes(value)) {
        selectedVoucherTypes.value = selectedVoucherTypes.value.filter((v) => v !== value);
    } else if (value === "6") {
        selectedVoucherTypes.value = ["6"];
    } else {
        selectedVoucherTypes.value = [...selectedVoucherTypes.value.filter((v) => v !== "6"), value];
    }
}

watch(() => form.type, () => {
    selectedVoucherTypes.value = ["1"];
    if (form.type !== "compras") {
        form.full_semester = false;
    }
});

// ─── Descarga semestral (solo compras + contribuyente semestral) ───────────

const canFullSemester = computed(
    () => form.type === "compras" && props.typeDeclaration === "semestral"
);

const semesterLabel = computed(() =>
    form.month <= 6 ? `1er semestre (Ene – Jun ${form.year})` : `2do semestre (Jul – Dic ${form.year})`
);

watch(() => form.full_semester, (enabled) => {
    if (enabled) {
        form.day = null;
    }
});

// ─── Years, Months & Days ───────────────────────────────────────────────────

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

const daysInMonth = computed(() => {
    return new Date(form.year, form.month, 0).getDate();
});

const days = computed(() =>
    Array.from({ length: daysInMonth.value }, (_, i) => i + 1)
);

// Reset day when month/year changes if current day is out of range
watch([() => form.year, () => form.month], () => {
    if (form.day !== null && form.day > daysInMonth.value) {
        form.day = null;
    }
});

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

function jobDateLabel(job: ScrapeJob): string {
    if (job.end_month !== null && job.end_month !== job.month) {
        return `${monthName(job.month)} – ${monthName(job.end_month)} ${job.year}`;
    }
    if (job.day !== null) {
        return `${job.day} de ${monthName(job.month)} ${job.year}`;
    }
    return `${monthName(job.month)} ${job.year}`;
}

function isRetentionOnly(job: ScrapeJob): boolean {
    return job.voucher_types?.length === 1 && job.voucher_types[0] === '6';
}

function jobDisplayLabel(job: ScrapeJob): string {
    if (job.type === 'ambos') return 'Compras + Ventas';
    if (isRetentionOnly(job)) {
        return job.type === 'compras' ? 'Retenciones Recibidas' : 'Retenciones Emitidas';
    }
    return job.type === 'compras' ? 'Compras' : 'Ventas';
}

function jobNavigationRoute(job: ScrapeJob): string {
    if (isRetentionOnly(job)) {
        return job.type === 'compras' ? 'tenant.orders.index' : 'tenant.shops.index';
    }
    return job.type === 'compras' ? 'tenant.shops.index' : 'tenant.orders.index';
}

function jobNavigationLabel(job: ScrapeJob): string {
    if (isRetentionOnly(job)) {
        return job.type === 'compras' ? 'Ver Ventas' : 'Ver Compras';
    }
    return job.type === 'compras' ? 'Ver Compras' : 'Ver Ventas';
}

function jobNavigationIsCompras(job: ScrapeJob): boolean {
    if (isRetentionOnly(job)) {
        return job.type === 'ventas';
    }
    return job.type === 'compras';
}

const hasActiveJobs = computed(() =>
    jobsList.value.some((j) => j.status === "pending" || j.status === "running")
);

const hasSelectedVouchers = computed(() => selectedVoucherTypes.value.length > 0);

// ─── Polling ────────────────────────────────────────────────────────────────

async function pollStatus() {
    try {
        const response = await fetch(route("tenant.sri-scrape.status"));
        const data = await response.json();
        const fresh = data.jobs as ScrapeJob[];

        // En la primera página, insertar jobs nuevos (p.ej. recién despachados
        // por el agente) al tope — si no, no aparecen hasta recargar.
        const onFirstPage = (jobsMeta.value.current_page ?? 1) === 1;
        if (onFirstPage) {
            const known = new Set(jobsList.value.map((j) => j.id));
            const additions = fresh
                .filter((j) => !known.has(j.id))
                .sort((a, b) => b.id - a.id);
            if (additions.length) {
                jobsList.value.unshift(...additions);
            }
        }

        // Actualizar en sitio los jobs ya visibles (estado/progreso)
        fresh.forEach((freshJob) => {
            const idx = jobsList.value.findIndex((j) => j.id === freshJob.id);
            if (idx !== -1) {
                jobsList.value[idx] = freshJob;
            }
        });
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
    checkAgent();
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

// ─── Pagination ──────────────────────────────────────────────────────────────

function handlePageChange(page: number) {
    router.get(route("tenant.sri-scrape.index"), { page }, {
        preserveState: true,
        preserveScroll: true,
        only: ["jobs"],
    });
}

// ─── Local Agent ─────────────────────────────────────────────────────────────

const MIN_AGENT_VERSION = "1.0.0";

type AgentStatus = "checking" | "available" | "outdated" | "unavailable";
const agentStatus = ref<AgentStatus>("checking");
const agentVersion = ref<string | null>(null);
const agentDispatching = ref(false);
const agentError = ref<string | null>(null);

function readXsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : "";
}

function compareVersion(a: string | null, b: string): number {
    if (!a) return -1;
    const pa = a.split(".").map(Number);
    const pb = b.split(".").map(Number);
    for (let i = 0; i < 3; i++) {
        if ((pa[i] ?? 0) > (pb[i] ?? 0)) return 1;
        if ((pa[i] ?? 0) < (pb[i] ?? 0)) return -1;
    }
    return 0;
}

async function checkAgent(): Promise<void> {
    agentStatus.value = "checking";
    try {
        const resp = await fetch("http://localhost:8765/health", {
            signal: AbortSignal.timeout(3000),
        });
        if (!resp.ok) { agentStatus.value = "unavailable"; return; }
        const data = await resp.json();
        agentVersion.value = data.version ?? null;
        agentStatus.value =
            compareVersion(agentVersion.value, MIN_AGENT_VERSION) >= 0
                ? "available"
                : "outdated";
    } catch {
        agentStatus.value = "unavailable";
    }
}

// ─── Install Panel ────────────────────────────────────────────────────────────

type OS = "mac" | "windows" | "linux";

const detectedOS = computed<OS>(() => {
    if (typeof navigator === "undefined") return "windows";
    const ua = navigator.userAgent;
    if (/Mac/i.test(ua)) return "mac";
    if (/Linux/i.test(ua) && !/Win/i.test(ua)) return "linux";
    return "windows";
});

const selectedOS = ref<OS | null>(null);
const activeOS = computed<OS>(() => selectedOS.value ?? detectedOS.value);

const showInstallPanel = computed(
    () => agentStatus.value === "unavailable" || agentStatus.value === "outdated"
);

const copiedKey = ref<string | null>(null);

async function copyCommand(text: string, key: string): Promise<void> {
    await navigator.clipboard.writeText(text);
    copiedKey.value = key;
    setTimeout(() => { copiedKey.value = null; }, 2000);
}


const installCommands = computed(() => {
    const base = props.agentInstallUrl;
    return {
        mac: `curl -sSL ${base}/install.sh | bash`,
        linux: `curl -sSL ${base}/install.sh | bash`,
        windows: `Set-ExecutionPolicy Bypass -Scope Process -Force\niwr ${base}/install.ps1 -UseBasicParsing | iex`,
    };
});

async function submitAgent(): Promise<void> {
    agentDispatching.value = true;
    agentError.value = null;
    try {
        const dispatchResp = await fetch(route("tenant.sri-scrape.agent-dispatch"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-XSRF-TOKEN": readXsrfToken(),
            },
            body: JSON.stringify({
                type: form.type,
                year: form.year,
                month: form.month,
                day: form.day,
                voucher_types: selectedVoucherTypes.value,
                full_semester: form.full_semester,
            }),
        });

        const dispatchData = await dispatchResp.json();

        if (!dispatchResp.ok) {
            agentError.value = dispatchData.error ?? "Error al preparar la descarga.";
            return;
        }

        const agentResp = await fetch("http://localhost:8765/scrape", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(dispatchData.config),
        });

        if (!agentResp.ok) {
            const agentData = await agentResp.json();
            agentError.value = agentData.error ?? "Error al comunicar con el agente local.";
            return;
        }

        // Agente aceptó el job. Marcarlo "running" — el agente solo hace callback
        // al final, así que sin esto quedaría en "pending" todo el proceso.
        if (dispatchData.jobId) {
            fetch(route("tenant.sri-scrape.mark-running", { job: dispatchData.jobId }), {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "X-XSRF-TOKEN": readXsrfToken(),
                },
            }).catch(() => {});
        }

        // Job async con callbackUrl — empezar polling para reflejar el avance
        pollStatus();
        startPolling();
    } catch {
        agentError.value = "No se pudo conectar con el agente local. ¿Está corriendo?";
    } finally {
        agentDispatching.value = false;
    }
}

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

    <div
        class="mt-6 px-1"
        :class="showInstallPanel ? 'grid grid-cols-1 gap-6 lg:grid-cols-3' : 'space-y-6'"
    >
        <!-- Main column -->
        <div class="space-y-6" :class="{ 'lg:col-span-2': showInstallPanel }">

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

        <Alert v-if="agentError" variant="destructive">
            <XCircle class="size-4" />
            <AlertDescription>{{ agentError }}</AlertDescription>
        </Alert>

        <!-- Form -->
        <Card>
            <CardHeader>
                <CardTitle class="text-lg">Nueva descarga</CardTitle>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="flex flex-wrap gap-4 sm:items-end">
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

                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium">Día</label>
                            <Select
                                :model-value="form.day !== null ? String(form.day) : 'all'"
                                :disabled="form.full_semester"
                                @update:model-value="(v) => form.day = v === 'all' ? null : Number(v)"
                            >
                                <SelectTrigger class="w-[140px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos los días</SelectItem>
                                    <SelectItem
                                        v-for="d in days"
                                        :key="d"
                                        :value="String(d)"
                                    >
                                        {{ d }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- <Button
                            type="submit"
                            :disabled="form.processing || !hasPassword || !hasSelectedVouchers"
                            class="font-bold"
                        >
                            <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                            <CloudDownload v-else class="size-4" />
                            Descargar del SRI
                        </Button> -->

                        <Button
                            type="button"
                            :disabled="agentDispatching || !hasPassword || !hasSelectedVouchers || agentStatus !== 'available'"
                            class="font-bold"
                            @click="submitAgent"
                        >
                            <Loader2 v-if="agentDispatching" class="size-4 animate-spin" />
                            <CloudDownload v-else class="size-4" />
                            Descargar del SRI
                        </Button>
                    </div>

                    <!-- Agent status indicator -->
                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                        <template v-if="agentStatus === 'checking'">
                            <Loader2 class="size-3 animate-spin" />
                            Detectando agente local...
                        </template>
                        <template v-else-if="agentStatus === 'available'">
                            <span class="inline-block size-2 rounded-full bg-green-500" />
                            Agente local v{{ agentVersion }} detectado
                        </template>
                        <template v-else-if="agentStatus === 'outdated'">
                            <span class="inline-block size-2 rounded-full bg-yellow-500" />
                            Agente desactualizado (v{{ agentVersion }}) — actualiza el agente para usar esta función
                        </template>
                        <template v-else>
                            <span class="inline-block size-2 rounded-full bg-gray-400" />
                            Sin agente local instalado
                        </template>
                    </div>

                    <!-- Descarga semestral (contribuyentes con declaración semestral) -->
                    <div v-if="canFullSemester" class="flex flex-col gap-1.5">
                        <label class="flex cursor-pointer items-center gap-2">
                            <Switch v-model="form.full_semester" />
                            <span class="text-sm font-medium">Descargar todo el semestre</span>
                        </label>
                        <p v-if="form.full_semester" class="text-muted-foreground text-xs">
                            Se descargará el {{ semesterLabel }} en una sola sesión del SRI.
                        </p>
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
            <CardContent class="space-y-4">
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
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold uppercase">
                                    {{ jobDisplayLabel(job) }}
                                </span>
                                <span class="text-muted-foreground text-sm">
                                    {{ jobDateLabel(job) }}
                                </span>
                                <Badge :class="statusConfig[job.status]?.class">
                                    {{ statusConfig[job.status]?.label ?? job.status }}
                                </Badge>
                                <Badge v-if="job.source === 'automatic'" class="gap-1 border-0 bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                    <Bot class="size-3" />
                                    Auto
                                </Badge>
                                <Badge v-else-if="job.source === 'agent'" class="gap-1 border-0 bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                    <HardDriveDownload class="size-3" />
                                    Local
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
                            <div v-if="job.status === 'completed'" class="flex flex-wrap gap-2">
                                <!-- ambos: two separate navigation links -->
                                <template v-if="job.type === 'ambos'">
                                    <Link
                                        :href="route('tenant.shops.index')"
                                        class="inline-flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1 text-xs font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                                    >
                                        <ShoppingCart class="size-3" />
                                        Ver Compras
                                        <ArrowRight class="size-3 opacity-60" />
                                    </Link>
                                    <Link
                                        :href="route('tenant.orders.index')"
                                        class="inline-flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1 text-xs font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                                    >
                                        <ReceiptIndianRupee class="size-3" />
                                        Ver Ventas
                                        <ArrowRight class="size-3 opacity-60" />
                                    </Link>
                                </template>
                                <!-- single type: one navigation link -->
                                <template v-else>
                                    <Link
                                        :href="route(jobNavigationRoute(job))"
                                        class="inline-flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1 text-xs font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                                    >
                                        <ShoppingCart v-if="jobNavigationIsCompras(job)" class="size-3" />
                                        <ReceiptIndianRupee v-else class="size-3" />
                                        {{ jobNavigationLabel(job) }}
                                        <ArrowRight class="size-3 opacity-60" />
                                    </Link>
                                </template>
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

                <Pagination
                    v-if="jobsMeta.last_page > 1"
                    :paginator="jobsMeta"
                    @change-page="handlePageChange"
                />
            </CardContent>
        </Card>

        </div><!-- /main column -->

        <!-- Install Panel (right column) -->
        <div v-if="showInstallPanel" class="lg:col-span-1">
            <Card class="sticky top-4">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <HardDriveDownload class="size-4" />
                        Instalar Agente Local
                    </CardTitle>
                    <p class="text-muted-foreground text-sm">
                        El agente corre en tu computadora y descarga los comprobantes
                        directamente desde el portal del SRI.
                    </p>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- OS selector -->
                    <div class="flex gap-1 rounded-lg border p-1">
                        <button
                            v-for="os in (['windows', 'mac', 'linux'] as OS[])"
                            :key="os"
                            type="button"
                            class="flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition-colors"
                            :class="activeOS === os
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted'"
                            @click="selectedOS = os"
                        >
                            {{ os === 'mac' ? 'macOS' : os === 'linux' ? 'Linux' : 'Windows' }}
                            <span
                                v-if="os === detectedOS && selectedOS === null"
                                class="ml-1 opacity-60"
                            >✓</span>
                        </button>
                    </div>

                    <!-- Commands -->
                    <div class="space-y-3">
                        <!-- Windows: two commands -->
                        <template v-if="activeOS === 'windows'">
                            <div
                                v-for="(line, i) in installCommands.windows.split('\n')"
                                :key="i"
                                class="group relative"
                            >
                                <div class="flex items-center gap-1.5 rounded-md bg-muted px-3 py-2 pr-10 font-mono text-xs break-all">
                                    <Terminal class="size-3 shrink-0 text-muted-foreground" />
                                    {{ line }}
                                </div>
                                <button
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:text-foreground"
                                    @click="copyCommand(line, `win-${i}`)"
                                >
                                    <Check v-if="copiedKey === `win-${i}`" class="size-3 text-green-500" />
                                    <Copy v-else class="size-3" />
                                </button>
                            </div>
                            <p class="text-muted-foreground text-xs">
                                Abre PowerShell como usuario normal y ejecuta los comandos en orden.
                                Si la descarga de Chromium falla, verifica que la fecha y hora del equipo sean correctas.
                            </p>
                        </template>

                        <!-- macOS / Linux: one command -->
                        <template v-else>
                            <div class="group relative">
                                <div class="flex items-center gap-1.5 rounded-md bg-muted px-3 py-2 pr-10 font-mono text-xs break-all">
                                    <Terminal class="size-3 shrink-0 text-muted-foreground" />
                                    {{ installCommands[activeOS] }}
                                </div>
                                <button
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:text-foreground"
                                    @click="copyCommand(installCommands[activeOS], 'cmd')"
                                >
                                    <Check v-if="copiedKey === 'cmd'" class="size-3 text-green-500" />
                                    <Copy v-else class="size-3" />
                                </button>
                            </div>
                            <p class="text-muted-foreground text-xs">
                                <template v-if="activeOS === 'mac'">
                                    Abre Terminal y pega el comando. Instala automáticamente Python, Playwright y Chrome.
                                </template>
                                <template v-else>
                                    Ejecuta en la terminal. Requiere Python 3.9+ y conexión a internet.
                                </template>
                            </p>
                        </template>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="w-full"
                        :disabled="agentStatus === 'checking'"
                        @click="checkAgent"
                    >
                        <Loader2 v-if="agentStatus === 'checking'" class="size-3.5 animate-spin" />
                        <RefreshCw v-else class="size-3.5" />
                        {{ agentStatus === 'outdated' ? 'Verificar actualización' : 'Ya instalé el agente' }}
                    </Button>

                    <p v-if="agentStatus === 'outdated'" class="text-xs text-amber-600 dark:text-amber-400">
                        Versión desactualizada (v{{ agentVersion }}). Ejecuta el mismo comando de instalación para actualizar.
                    </p>
                </CardContent>
            </Card>
        </div><!-- /install panel -->

    </div>
</template>
