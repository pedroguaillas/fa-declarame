<script setup lang="ts">
import { reactive, computed, ref } from "vue";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FileSpreadsheet, Loader2 } from "lucide-vue-next";

export interface CasilleroRow {
    c: string;
    d: string;
    v: number | string | null;
    t: "auto" | "manual" | "formula";
}

export interface FormSection {
    section: string;
    rows: CasilleroRow[];
}

export interface FormPayload {
    sections: FormSection[];
    unmapped: { code: string; base: number; value: number }[];
}

const props = defineProps<{
    title: string;
    payload: FormPayload;
    formulas: Record<string, (get: (c: string) => number) => number>;
    exportRoute: string;
    periodParams: Record<string, string | number>;
}>();

// Valores editables: manuales inicializados desde el servidor
const manualValues = reactive<Record<string, number>>({});

for (const section of props.payload.sections) {
    for (const row of section.rows) {
        if (row.t === "manual" && typeof row.v !== "string") {
            manualValues[row.c] = Number(row.v ?? 0);
        }
    }
}

const autoValues: Record<string, number | string | null> = {};
for (const section of props.payload.sections) {
    for (const row of section.rows) {
        if (row.t !== "manual") {
            autoValues[row.c] = row.v;
        }
    }
}

// Resuelve el valor final de un casillero: fórmula > manual > auto
const resolved = computed<Record<string, number>>(() => {
    const values: Record<string, number> = {};
    const get = (c: string): number => {
        if (c in values) return values[c];
        if (c in props.formulas) {
            values[c] = 0; // corta ciclos
            values[c] = round2(props.formulas[c](get));
            return values[c];
        }
        if (c in manualValues) return manualValues[c];
        const v = autoValues[c];
        return typeof v === "number" ? v : Number(v) || 0;
    };

    for (const section of props.payload.sections) {
        for (const row of section.rows) {
            values[row.c] = get(row.c);
        }
    }

    return values;
});

const round2 = (n: number) => Math.round(n * 100) / 100;

function displayValue(row: CasilleroRow): string {
    if (typeof row.v === "string") return row.v;
    const value = resolved.value[row.c] ?? 0;
    return value.toLocaleString("es-EC", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ─── Export (POST con valores actuales → descarga xlsx) ─────────────────────

const exporting = ref(false);

function readXsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : "";
}

async function exportExcel() {
    exporting.value = true;

    const values: Record<string, number> = {};
    for (const section of props.payload.sections) {
        for (const row of section.rows) {
            if ((row.t === "manual" || row.t === "formula") && typeof row.v !== "string") {
                values[row.c] = resolved.value[row.c] ?? 0;
            }
        }
    }

    try {
        const response = await fetch(route(props.exportRoute), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-XSRF-TOKEN": readXsrfToken(),
                Accept: "application/octet-stream",
            },
            body: JSON.stringify({ ...props.periodParams, values }),
        });

        if (!response.ok) throw new Error(`Export falló (${response.status})`);

        const blob = await response.blob();
        const disposition = response.headers.get("Content-Disposition") ?? "";
        const fileName = disposition.match(/filename="?([^\";]+)"?/)?.[1] ?? `${props.title}.xlsx`;

        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = fileName;
        link.click();
        URL.revokeObjectURL(url);
    } finally {
        exporting.value = false;
    }
}
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-3">
            <CardTitle class="text-base">{{ title }}</CardTitle>
            <Button variant="outline" size="sm" :disabled="exporting" @click="exportExcel">
                <Loader2 v-if="exporting" class="size-3.5 animate-spin" />
                <FileSpreadsheet v-else class="size-3.5" />
                Exportar Excel
            </Button>
        </CardHeader>
        <CardContent class="space-y-5">
            <div v-if="payload.unmapped.length" class="rounded-md border border-amber-300 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-300">
                <p class="font-semibold">Códigos de retención sin casillero asignado (revisar manualmente):</p>
                <p v-for="u in payload.unmapped" :key="u.code">
                    • Código {{ u.code }}: base {{ u.base.toFixed(2) }}, retenido {{ u.value.toFixed(2) }}
                </p>
            </div>

            <section v-for="section in payload.sections" :key="section.section">
                <h3 class="text-muted-foreground mb-1.5 text-xs font-semibold tracking-widest uppercase">
                    {{ section.section }}
                </h3>
                <div class="divide-border divide-y rounded-md border">
                    <div
                        v-for="row in section.rows"
                        :key="row.c"
                        class="flex items-center gap-3 px-3 py-1.5"
                        :class="row.t === 'formula' ? 'bg-muted/50' : ''"
                    >
                        <span class="w-14 shrink-0 font-mono text-xs font-semibold">{{ row.c }}</span>
                        <span class="text-muted-foreground flex-1 text-xs">{{ row.d }}</span>

                        <input
                            v-if="row.t === 'manual' && typeof row.v !== 'string'"
                            v-model.number="manualValues[row.c]"
                            type="number"
                            step="0.01"
                            class="border-border bg-background h-7 w-32 rounded-md border px-2 text-right font-mono text-xs tabular-nums focus:ring-2 focus:ring-ring/30 focus:outline-none"
                        />
                        <span
                            v-else
                            class="w-32 text-right font-mono text-xs tabular-nums"
                            :class="row.t === 'formula' ? 'font-bold' : ''"
                        >
                            {{ displayValue(row) }}
                        </span>
                    </div>
                </div>
            </section>
        </CardContent>
    </Card>
</template>
