<script setup lang="ts">
import { ref } from "vue";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";

interface ShopFilters {
    search?: string;
    period?: string;
    state?: string;
    retention?: string;
}

const props = defineProps<{ filters: ShopFilters }>();

const open = ref(false);

const availableColumns: { key: string; label: string }[] = [
    { key: "emision", label: "Emisión" },
    { key: "voucher_type", label: "Tipo Comprobante" },
    { key: "serie", label: "Serie" },
    { key: "contact_identification", label: "RUC / Cédula" },
    { key: "contact_name", label: "Proveedor" },
    { key: "autorization", label: "Autorización" },
    { key: "sub_total", label: "Sub Total" },
    { key: "no_iva", label: "No IVA" },
    { key: "base0", label: "Base 0%" },
    { key: "base5", label: "Base 5%" },
    { key: "base8", label: "Base 8%" },
    { key: "base12", label: "Base 12%" },
    { key: "base15", label: "Base 15%" },
    { key: "iva5", label: "IVA 5%" },
    { key: "iva8", label: "IVA 8%" },
    { key: "iva12", label: "IVA 12%" },
    { key: "iva15", label: "IVA 15%" },
    { key: "discount", label: "Descuento" },
    { key: "ice", label: "ICE" },
    { key: "total", label: "Total" },
    { key: "state", label: "Estado" },
    { key: "account", label: "Cuenta Contable" },
    { key: "serie_retention", label: "Serie Retención" },
    { key: "date_retention", label: "Fecha Retención" },
    { key: "state_retention", label: "Estado Retención" },
    { key: "autorization_retention", label: "Autorización Retención" },
];

const selectedColumns = ref<string[]>(availableColumns.map((c) => c.key));

function toggleAll(checked: boolean) {
    selectedColumns.value = checked ? availableColumns.map((c) => c.key) : [];
}

const allSelected = () => selectedColumns.value.length === availableColumns.length;

function download() {
    const params = new URLSearchParams();
    selectedColumns.value.forEach((col) => params.append("columns[]", col));
    const f = props.filters;
    if (f.search) params.set("search", f.search);
    if (f.period) params.set("period", f.period);
    if (f.state) params.set("state", f.state);
    if (f.retention) params.set("retention", f.retention);
    window.location.href = route("tenant.shops.export") + "?" + params.toString();
}

defineExpose({ open: () => (open.value = true) });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Descargar compras</DialogTitle>
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
