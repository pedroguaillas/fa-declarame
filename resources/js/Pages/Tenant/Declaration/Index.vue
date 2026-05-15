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
    month: number;
    compras: Summary;
    ventas: Summary;
}>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string });

const selectedYear = ref(props.year);
const selectedMonth = ref(props.month);

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

const monthName = (m: number) => months.find((mo) => mo.value === m)?.label ?? m;

function applyPeriod() {
    router.get(
        route("tenant.declaration.index"),
        { year: selectedYear.value, month: selectedMonth.value },
        { preserveState: true },
    );
}

function downloadAts() {
    const params = new URLSearchParams({
        year: String(props.year),
        month: String(props.month),
    });
    window.location.href = route("tenant.export-ats") + "?" + params.toString();
}

function downloadReport(routeName: string) {
    const start = `${props.year}-${String(props.month).padStart(2, "0")}-01`;
    const lastDay = new Date(props.year, props.month, 0).getDate();
    const end = `${props.year}-${String(props.month).padStart(2, "0")}-${lastDay}`;
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
];

defineOptions({ layout: TenantLayout });
</script>

<template>
    <HeaderList
        title="Declaración"
        :description="`Resumen del período ${monthName(month)} ${year}`"
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
            <div class="flex flex-col gap-1.5">
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
        </div>

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
                        <strong>{{ monthName(month) }} {{ year }}</strong> listo para subir al portal del SRI.
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
</template>
