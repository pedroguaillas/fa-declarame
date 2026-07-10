<script setup lang="ts">
import TenantLayout from "@/layouts/TenantLayout.vue";
import HeaderList from "@/components/Shared/HeaderList.vue";
import { router, Link, useForm, usePage } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    ShoppingCart,
    ReceiptIndianRupee,
    Download,
    FileSpreadsheet,
    Percent,
    ArrowRight,
    BookOpen,
    FileText,
    Users,
    UserCheck,
    FileDown,
    FileUp,
    Loader2,
    CheckCircle2,
    XCircle,
} from "lucide-vue-next";
import FormCasilleros, { type FormPayload } from "./partials/FormCasilleros.vue";
import { Skeleton } from "@/components/ui/skeleton";

interface Summary {
    count: number;
    subtotal: number;
    iva: number;
    total: number;
    retentions: number;
    a_pagar?: number;
    a_cobrar?: number;
}

const props = defineProps<{
    year: number;
    month: number | null;
    semester: number | null;
    typeDeclaration: string;
    compras: Summary;
    ventas: Summary;
    f104?: FormPayload;
    f103?: FormPayload;
}>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string });

const isSemiannual = computed(() => props.typeDeclaration === "semestral");

const selectedYear = ref(props.year);
const selectedMonth = ref(props.month ?? 1);
const selectedSemester = ref(props.semester ?? 1);

const currentYear = new Date().getFullYear();
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

const semesters = [
    { value: 1, label: "Semestre 1 (Ene–Jun)" },
    { value: 2, label: "Semestre 2 (Jul–Dic)" },
];

const monthName = (m: number) => months.find((mo) => mo.value === m)?.label ?? m;

const periodLabel = computed(() =>
    isSemiannual.value
        ? `Semestre ${props.semester} ${props.year}`
        : `${monthName(props.month ?? 1)} ${props.year}`,
);

function applyPeriod() {
    const params = isSemiannual.value
        ? { year: selectedYear.value, semester: selectedSemester.value }
        : { year: selectedYear.value, month: selectedMonth.value };

    router.get(route("tenant.declaration.index"), params, {
        preserveState: true,
        onSuccess: () => {
            if (activeTab.value !== "resumen") {
                router.reload({ only: [activeTab.value] });
            }
        },
    });
}

// ─── Tabs Resumen / F104 / F103 ─────────────────────────────────────────────

const activeTab = ref<"resumen" | "f104" | "f103">("resumen");

const tabs = [
    { value: "resumen", label: "Resumen" },
    { value: "f104", label: "Formulario 104 (IVA)" },
    { value: "f103", label: "Formulario 103 (Retenciones)" },
] as const;

function selectTab(tab: "resumen" | "f104" | "f103") {
    activeTab.value = tab;

    if (tab !== "resumen" && props[tab] === undefined) {
        router.reload({ only: [tab] });
    }
}

const periodParams = computed<Record<string, string | number>>(() =>
    isSemiannual.value
        ? { year: props.year, semester: props.semester ?? 1 }
        : { year: props.year, month: props.month ?? 1 },
);

const sum = (get: (c: string) => number, casilleros: string[]) =>
    casilleros.reduce((total, c) => total + get(c), 0);

const f103Formulas: Record<string, (get: (c: string) => number) => number> = {
    "349": (g) => sum(g, ["302", "303", "3030", "304", "307", "308", "309", "310", "311", "312", "322", "3120", "3121", "3430", "343", "344", "332", "314", "3140", "319", "320", "323", "324", "3230", "325", "326", "327", "328", "329", "330", "333", "334", "335", "3481", "336", "337", "3370", "350", "3440", "346", "3400", "3380", "3480"]),
    "399": (g) => sum(g, ["352", "353", "3530", "354", "357", "358", "359", "360", "361", "362", "372", "3620", "3621", "3450", "393", "394", "364", "3640", "369", "370", "373", "374", "375", "376", "377", "378", "379", "380", "383", "384", "385", "3981", "386", "387", "3870", "400", "3940", "396", "3900", "3880", "3980"]),
    "498": (g) => sum(g, ["402", "410", "411", "412"]),
    "499": (g) => g("399") + sum(g, ["424", "431", "432", "433"]),
    "501": (g) => g("499") - g("500"),
    "902": (g) => g("501"),
    "905": (g) => g("902") + g("903") + g("904"),
};

const f104Formulas: Record<string, (get: (c: string) => number) => number> = {
    "409": (g) => sum(g, ["401", "402", "403", "405", "407", "408", "425"]),
    "419": (g) => sum(g, ["411", "413", "435"]),
    "429": (g) => g("421") + g("445"),
    "480": (g) => g("411") + g("435") - g("481"),
    "482": (g) => g("429"),
    "484": (g) => g("482") + g("483"),
    "499": (g) => g("484"),
    "509": (g) => sum(g, ["500", "501", "502", "503", "504", "505", "507", "508", "540"]),
    "519": (g) => sum(g, ["510", "511", "512", "517", "518", "550"]),
    "529": (g) => sum(g, ["520", "521", "522", "523", "524", "525", "560"]),
    "564": (g) => (g("520") + g("521") + g("523") + g("524") + g("525") + g("560")) * g("563"),
    "565": (g) => g("520") + g("521") + g("523") + g("524") + g("525") + g("560") - g("564"),
    "601": (g) => Math.max(0, g("499") - g("564")),
    "602": (g) => Math.max(0, g("564") - g("499")),
    "615": (g) => g("602") + Math.max(0, g("605") - g("601")),
    "617": (g) => Math.max(0, g("606") + g("609") - Math.max(0, g("601") - g("605"))),
    "620": (g) => Math.max(0, g("601") - g("605") - g("606") - g("609")),
    "699": (g) => g("620") + g("621"),
    "799": (g) => sum(g, ["721", "723", "725", "727", "729", "731"]),
    "801": (g) => g("799"),
    "859": (g) => g("699") + g("801"),
    "902": (g) => g("859"),
    "905": (g) => g("902") + g("903") + g("904"),
};

function downloadAts() {
    if (isSemiannual.value) {
        const params = new URLSearchParams({
            year: String(props.year),
            semester: String(props.semester ?? 1),
        });
        window.location.href = route("tenant.export-ats") + "?" + params.toString();
    } else {
        const params = new URLSearchParams({
            year: String(props.year),
            month: String(props.month ?? 1),
        });
        window.location.href = route("tenant.export-ats") + "?" + params.toString();
    }
}

function downloadSemesterReport() {
    const params = new URLSearchParams({ year: String(props.year) });
    if (isSemiannual.value) {
        params.set("semester", String(props.semester ?? 1));
    } else {
        params.set("month", String(props.month ?? 1));
    }
    window.location.href =
        route("tenant.declaration.export-semester") + "?" + params.toString();
}

function downloadReport(routeName: string) {
    let start: string;
    let end: string;

    if (isSemiannual.value) {
        const startMonth = props.semester === 1 ? 1 : 7;
        const endMonth = props.semester === 1 ? 6 : 12;
        start = `${props.year}-${String(startMonth).padStart(2, "0")}-01`;
        const lastDay = new Date(props.year, endMonth, 0).getDate();
        end = `${props.year}-${String(endMonth).padStart(2, "0")}-${lastDay}`;
    } else {
        start = `${props.year}-${String(props.month).padStart(2, "0")}-01`;
        const lastDay = new Date(props.year, props.month!, 0).getDate();
        end = `${props.year}-${String(props.month).padStart(2, "0")}-${lastDay}`;
    }

    const params = new URLSearchParams({ start_date: start, end_date: end });
    window.location.href = route(routeName) + "?" + params.toString();
}

// ─── ATS Import ─────────────────────────────────────────────────────────────

const importForm = useForm({ file: null as File | null });
const importFileInput = ref<HTMLInputElement | null>(null);

function onFileSelected(e: Event) {
    const input = e.target as HTMLInputElement;
    importForm.file = input.files?.[0] ?? null;
}

function submitImport() {
    importForm.post(route("tenant.import-ats"), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            importForm.reset();
            if (importFileInput.value) importFileInput.value.value = "";
        },
    });
}

// ─── Report links ────────────────────────────────────────────────────────────

const fmt = (v: number) =>
    v.toLocaleString("es-EC", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const reportLinks = [
    { title: "Mayor analítico", icon: BookOpen, route: "tenant.reports.shops-by-account" },
    { title: "Compras por tipo comprobante", icon: FileText, route: "tenant.reports.shops-by-voucher-type" },
    { title: "Por proveedor", icon: Users, route: "tenant.reports.shops-by-provider" },
    { title: "Retenciones", icon: Percent, route: "tenant.reports.shops-by-retention" },
    { title: "Ventas por tipo comprobante", icon: ReceiptIndianRupee, route: "tenant.reports.orders-by-voucher-type" },
    { title: "Por cliente", icon: UserCheck, route: "tenant.reports.orders-by-client" },
    { title: "Retenciones recibidas", icon: Percent, route: "tenant.reports.orders-by-retention" },
];

defineOptions({ layout: TenantLayout });
</script>

<template>
    <HeaderList
        title="Declaración"
        :description="`Resumen del período ${periodLabel}`"
    />

    <div class="mt-6 space-y-6 px-1">
        <!-- Flash alerts -->
        <Alert
            v-if="flash?.success"
            class="border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200"
        >
            <CheckCircle2 class="size-4" />
            <AlertDescription>{{ flash.success }}</AlertDescription>
        </Alert>
        <Alert v-if="flash?.error" variant="destructive">
            <XCircle class="size-4" />
            <AlertDescription>{{ flash.error }}</AlertDescription>
        </Alert>

        <!-- Period selector -->
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium">Año</label>
                <Select v-model="selectedYear">
                    <SelectTrigger class="w-[120px]">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="y in years" :key="y" :value="y">{{ y }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div v-if="isSemiannual" class="flex flex-col gap-1.5">
                <label class="text-sm font-medium">Semestre</label>
                <Select v-model="selectedSemester">
                    <SelectTrigger class="w-[220px]">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="s in semesters" :key="s.value" :value="s.value">
                            {{ s.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div v-else class="flex flex-col gap-1.5">
                <label class="text-sm font-medium">Mes</label>
                <Select v-model="selectedMonth">
                    <SelectTrigger class="w-[160px]">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="m in months" :key="m.value" :value="m.value">
                            {{ m.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <Button @click="applyPeriod">Ver período</Button>
            <Button variant="outline" @click="downloadSemesterReport">
                <FileSpreadsheet class="size-4" />
                {{ isSemiannual ? "Reporte Semestral Excel" : "Reporte Mensual Excel" }}
            </Button>
        </div>

        <!-- Tabs -->
        <div class="border-border inline-flex items-center gap-1 rounded-lg border p-1">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                :class="activeTab === tab.value
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted'"
                @click="selectTab(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Tab: F104 -->
        <template v-if="activeTab === 'f104'">
            <FormCasilleros
                v-if="f104"
                :title="`Formulario 104 — IVA · ${periodLabel}`"
                :payload="f104"
                :formulas="f104Formulas"
                export-route="tenant.declaration.export-f104"
                :period-params="periodParams"
            />
            <div v-else class="space-y-3">
                <Skeleton class="h-8 w-1/3" />
                <Skeleton class="h-64 w-full" />
                <Skeleton class="h-64 w-full" />
            </div>
        </template>

        <!-- Tab: F103 -->
        <template v-else-if="activeTab === 'f103'">
            <FormCasilleros
                v-if="f103"
                :title="`Formulario 103 — Retenciones en la Fuente · ${periodLabel}`"
                :payload="f103"
                :formulas="f103Formulas"
                export-route="tenant.declaration.export-f103"
                :period-params="periodParams"
            />
            <div v-else class="space-y-3">
                <Skeleton class="h-8 w-1/3" />
                <Skeleton class="h-64 w-full" />
                <Skeleton class="h-64 w-full" />
            </div>
        </template>

        <!-- Tab: Resumen -->
        <div v-show="activeTab === 'resumen'" class="space-y-6">
        <!-- Summary cards -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <!-- Compras -->
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <ShoppingCart class="size-4 text-primary" />
                        Compras
                        <span class="text-muted-foreground ml-auto font-mono text-xs font-normal">
                            {{ compras.count }} comprobantes
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div class="divide-border divide-y rounded-md border">
                        <div class="flex items-center justify-between px-3 py-2">
                            <span class="text-muted-foreground text-sm">Base imponible</span>
                            <span class="font-mono text-sm tabular-nums">{{ fmt(compras.subtotal) }}</span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-2">
                            <span class="text-muted-foreground text-sm">IVA</span>
                            <span class="font-mono text-sm tabular-nums">{{ fmt(compras.iva) }}</span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-2">
                            <span class="text-muted-foreground text-sm">Total compras</span>
                            <span class="font-mono text-sm font-semibold tabular-nums">{{ fmt(compras.total) }}</span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-2">
                            <span class="text-muted-foreground text-sm">Retenciones emitidas</span>
                            <span class="font-mono text-sm tabular-nums text-amber-600 dark:text-amber-400">
                                - {{ fmt(compras.retentions) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between bg-muted/50 px-3 py-2.5">
                            <span class="text-sm font-semibold">A pagar</span>
                            <span class="font-mono text-base font-bold tabular-nums text-primary">
                                {{ fmt(compras.a_pagar ?? 0) }}
                            </span>
                        </div>
                    </div>
                    <div class="flex justify-end pt-1">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="downloadReport('tenant.reports.shops-by-voucher-type.export')"
                        >
                            <Download class="size-3.5" />
                            Detalle Excel
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Ventas -->
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <ReceiptIndianRupee class="size-4 text-primary" />
                        Ventas
                        <span class="text-muted-foreground ml-auto font-mono text-xs font-normal">
                            {{ ventas.count }} comprobantes
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div class="divide-border divide-y rounded-md border">
                        <div class="flex items-center justify-between px-3 py-2">
                            <span class="text-muted-foreground text-sm">Base imponible</span>
                            <span class="font-mono text-sm tabular-nums">{{ fmt(ventas.subtotal) }}</span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-2">
                            <span class="text-muted-foreground text-sm">IVA</span>
                            <span class="font-mono text-sm tabular-nums">{{ fmt(ventas.iva) }}</span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-2">
                            <span class="text-muted-foreground text-sm">Total ventas</span>
                            <span class="font-mono text-sm font-semibold tabular-nums">{{ fmt(ventas.total) }}</span>
                        </div>
                        <div class="flex items-center justify-between px-3 py-2">
                            <span class="text-muted-foreground text-sm">Retenciones recibidas</span>
                            <span class="font-mono text-sm tabular-nums text-amber-600 dark:text-amber-400">
                                - {{ fmt(ventas.retentions) }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between bg-muted/50 px-3 py-2.5">
                            <span class="text-sm font-semibold">A cobrar</span>
                            <span class="font-mono text-base font-bold tabular-nums text-primary">
                                {{ fmt(ventas.a_cobrar ?? 0) }}
                            </span>
                        </div>
                    </div>
                    <div class="flex justify-end pt-1">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="downloadReport('tenant.reports.orders-by-voucher-type.export')"
                        >
                            <Download class="size-3.5" />
                            Detalle Excel
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ATS: Export + Import -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <!-- Export -->
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <FileDown class="size-4 text-primary" />
                        Exportar ATS
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-muted-foreground mb-4 text-sm">
                        Genera el XML del Anexo Transaccional para
                        <strong>{{ periodLabel }}</strong> listo para subir al portal del SRI.
                    </p>
                    <Button @click="downloadAts">
                        <FileSpreadsheet class="size-4" />
                        Descargar ATS XML
                    </Button>
                </CardContent>
            </Card>

            <!-- Import -->
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <FileUp class="size-4 text-primary" />
                        Importar ATS
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-muted-foreground mb-4 text-sm">
                        Importa un archivo XML o ZIP de ATS para registrar las compras automáticamente.
                    </p>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="flex flex-1 flex-col gap-1.5">
                            <label class="text-sm font-medium">Archivo XML o ZIP</label>
                            <input
                                ref="importFileInput"
                                type="file"
                                accept=".xml,.zip"
                                class="border-border bg-background text-foreground focus:ring-ring/30 h-9 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium"
                                @change="onFileSelected"
                            />
                            <p v-if="importForm.errors.file" class="text-destructive text-xs">
                                {{ importForm.errors.file }}
                            </p>
                        </div>
                        <Button
                            :disabled="!importForm.file || importForm.processing"
                            @click="submitImport"
                        >
                            <Loader2 v-if="importForm.processing" class="size-4 animate-spin" />
                            <FileUp v-else class="size-4" />
                            {{ importForm.processing ? "Importando..." : "Importar" }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Quick links to detailed reports -->
        <section>
            <h2 class="text-muted-foreground mb-3 text-xs font-semibold tracking-widest uppercase">
                Reportes detallados
            </h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="r in reportLinks"
                    :key="r.route"
                    :href="route(r.route)"
                    class="group flex items-center gap-3 rounded-lg border bg-card px-4 py-3 transition-shadow hover:shadow-md"
                >
                    <div class="bg-primary/10 text-primary rounded-md p-1.5">
                        <component :is="r.icon" class="size-4" />
                    </div>
                    <span class="flex-1 text-sm font-medium">{{ r.title }}</span>
                    <ArrowRight class="text-muted-foreground/40 size-4 transition-transform group-hover:translate-x-0.5 group-hover:text-current" />
                </Link>
            </div>
        </section>
        </div>
    </div>
</template>
