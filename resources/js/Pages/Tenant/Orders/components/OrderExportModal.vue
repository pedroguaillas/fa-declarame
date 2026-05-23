<script setup lang="ts">
import { computed, ref } from "vue";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { IVA_NEW_RATES_START_MONTH } from "@/constants/ecuador";

interface OrderFilters {
    search?: string;
    period?: string;
    voucher_type?: string;
}

const props = defineProps<{ filters: OrderFilters }>();

const open = ref(false);

const NEW_RATES_START = IVA_NEW_RATES_START_MONTH;
const OLD_RATE_COLUMNS = ["base12", "iva12"];
const NEW_RATE_COLUMNS = ["base5", "iva5", "base15", "iva15"];

const allColumns: { key: string; label: string }[] = [
    { key: "emision", label: "Emisión" },
    { key: "voucher_type", label: "Tipo Comprobante" },
    { key: "serie", label: "Serie" },
    { key: "contact_identification", label: "RUC / Cédula" },
    { key: "contact_name", label: "Cliente" },
    { key: "autorization", label: "Autorización" },
    { key: "exempt", label: "Excenta" },
    { key: "sub_total", label: "Sub Total" },
    { key: "no_iva", label: "No IVA" },
    { key: "base0", label: "Base 0%" },
    { key: "base5", label: "Base 5%" },
    { key: "base12", label: "Base 12%" },
    { key: "base15", label: "Base 15%" },
    { key: "iva5", label: "IVA 5%" },
    { key: "iva12", label: "IVA 12%" },
    { key: "iva15", label: "IVA 15%" },
    { key: "discount", label: "Descuento" },
    { key: "ice", label: "ICE" },
    { key: "total", label: "Total" },
    { key: "state", label: "Estado" },
];

const availableColumns = computed(() => {
    const period = props.filters.period;
    if (!period) return allColumns;
    if (period < NEW_RATES_START) return allColumns.filter((c) => !NEW_RATE_COLUMNS.includes(c.key));
    return allColumns.filter((c) => !OLD_RATE_COLUMNS.includes(c.key));
});

const selectedColumns = ref<string[]>(availableColumns.value.map((c) => c.key));

function toggleAll(checked: boolean) {
    selectedColumns.value = checked ? availableColumns.value.map((c) => c.key) : [];
}

const allSelected = () => selectedColumns.value.length === availableColumns.value.length;

function download() {
    const params = new URLSearchParams();
    selectedColumns.value.forEach((col) => params.append("columns[]", col));
    const f = props.filters;
    if (f.search) params.set("search", f.search);
    if (f.period) params.set("period", f.period);
    if (f.voucher_type) params.set("voucher_type", f.voucher_type);
    window.location.href = route("tenant.orders.export") + "?" + params.toString();
}

defineExpose({ open: () => (open.value = true) });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Descargar ventas</DialogTitle>
            </DialogHeader>

            <div class="space-y-3 py-2">
                <div class="flex items-center justify-between">
                    <p class="text-muted-foreground text-xs font-medium tracking-wider uppercase">
                        Columnas a incluir
                    </p>
                    <button
                        type="button"
                        class="text-primary hover:text-primary/70 text-xs font-medium"
                        @click="toggleAll(!allSelected())"
                    >
                        {{ allSelected() ? "Desmarcar todo" : "Seleccionar todo" }}
                    </button>
                </div>

                <div class="border-border max-h-72 overflow-y-auto rounded-lg border">
                    <label
                        v-for="col in availableColumns"
                        :key="col.key"
                        class="hover:bg-accent flex cursor-pointer items-center gap-3 px-3 py-2 text-sm"
                    >
                        <input
                            v-model="selectedColumns"
                            type="checkbox"
                            :value="col.key"
                            class="accent-primary size-4 shrink-0"
                        />
                        <span class="text-foreground">{{ col.label }}</span>
                    </label>
                </div>

                <p class="text-muted-foreground text-xs">
                    {{ selectedColumns.length }} de {{ availableColumns.length }} columnas seleccionadas
                </p>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false">Cancelar</Button>
                <Button :disabled="selectedColumns.length === 0" @click="download">
                    Descargar Excel
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
