<script setup lang="ts">
import { useForm, usePage } from "@inertiajs/vue3";
import { ref, computed } from "vue";

import TenantLayout from "@/layouts/TenantLayout.vue";
import HeaderList from "@/components/Shared/HeaderList.vue";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { FileDown, FileUp, Loader2, CheckCircle2, XCircle } from "lucide-vue-next";

// ─── State ──────────────────────────────────────────────────────────────────

const currentYear = new Date().getFullYear();
const currentMonth = new Date().getMonth();
const previousMonth = currentMonth === 0 ? 12 : currentMonth;
const previousYear = currentMonth === 0 ? currentYear - 1 : currentYear;

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string });

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

// ─── ATS Export ─────────────────────────────────────────────────────────────

const exportYear = ref(previousYear);
const exportMonth = ref(previousMonth);

function downloadAts() {
    const params = new URLSearchParams({
        year: String(exportYear.value),
        month: String(exportMonth.value),
    });
    window.location.href = route("tenant.export-ats") + "?" + params.toString();
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

defineOptions({ layout: TenantLayout });
</script>

<template>
    <HeaderList title="SRI" description="Exportar e importar Anexo Transaccional Simplificado" />

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

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <!-- ATS Export -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <FileDown class="size-5" />
                        Exportar ATS
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-muted-foreground mb-4 text-sm">
                        Genera el archivo XML del Anexo Transaccional Simplificado para el periodo seleccionado.
                    </p>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium">Año</label>
                            <Select v-model="exportYear">
                                <SelectTrigger class="w-[120px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="y in years" :key="y" :value="y">
                                        {{ y }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium">Mes</label>
                            <Select v-model="exportMonth">
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

                        <Button @click="downloadAts" class="font-bold">
                            <FileDown class="size-4" />
                            Descargar XML
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- ATS Import -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <FileUp class="size-5" />
                        Importar ATS
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-muted-foreground mb-4 text-sm">
                        Importa un archivo XML de ATS para registrar las compras automáticamente.
                    </p>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
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
                            @click="submitImport"
                            :disabled="!importForm.file || importForm.processing"
                            class="font-bold"
                        >
                            <Loader2 v-if="importForm.processing" class="size-4 animate-spin" />
                            <FileUp v-else class="size-4" />
                            {{ importForm.processing ? "Importando..." : "Importar" }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
