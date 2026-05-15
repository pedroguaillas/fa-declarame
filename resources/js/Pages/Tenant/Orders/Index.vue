<script setup lang="ts">
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { ref, watch } from "vue";

import ConfirmDialog from "@/components/Shared/ConfirmDialog.vue";
import DataTableDesktop from "@/components/Shared/DataTableDesktop.vue";
import DataTableMobile from "@/components/Shared/DataTableMobile.vue";
import HeaderList from "@/components/Shared/HeaderList.vue";
import Pagination from "@/components/Shared/Pagination.vue";
import TenantLayout from "@/layouts/TenantLayout.vue";

import { Button } from "@/components/ui/button";

import type { Paginator } from "@/types";
import type { Order } from "@/types/tenant";
import type { ActionDef, ActionPayload, ColumnDef } from "@/types/shared";
import { Pencil, Receipt, Trash2, Upload } from "lucide-vue-next";
import FilterBar from "./components/FilterBar.vue";
import OrderExportModal from "./components/OrderExportModal.vue";
import RetentionSlideOver from "./components/RetentionSlideOver.vue";

// ─── Props ──────────────────────────────────────────────────────────────────

interface OrderFilters {
    search: string;
    period: string;
    voucher_type: string;
}

const props = defineProps<{
    orders: Paginator<Order>;
    filters: OrderFilters;
}>();

// ─── Table columns ──────────────────────────────────────────────────────────

const voucherTypes: Record<string, { label: string; class: string }> = {
    "01": {
        label: "Factura",
        class: "bg-blue-100   text-blue-700   dark:bg-blue-900/30   dark:text-blue-400   border-0",
    },
    "04": {
        label: "Nota Créd.",
        class: "bg-red-100    text-red-700    dark:bg-red-900/30    dark:text-red-400    border-0",
    },
    "05": {
        label: "Nota Déb.",
        class: "bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400 border-0",
    },
};

// ─── Columns ────────────────────────────────────────────────────────────────

const columns: ColumnDef<Order>[] = [
    { key: "emision", label: "Emisión" },
    {
        key: "code",
        label: "Tipo",
        badge: {
            value: (item) => voucherTypes[item.code]?.label ?? "Otros",
            class: (item) => voucherTypes[item.code]?.class ?? "",
        },
    },
    {
        key: "serie",
        label: "Serie",
        format: (_, item) => item.serie,
        labelDescription: (_, item) => (item.serie_retention ? `Ret. ${item.serie_retention}` : ""),
    },
    {
        key: "contact",
        label: "Cliente",
        format: (_, item) => item.contact?.name ?? "—",
    },
    {
        key: "total",
        label: "Total",
        align: "right",
        format: (v) => `$${Number(v).toFixed(2)}`,
    },
];

const actions: ActionDef<Order>[] = [
    {
        event: "retention",
        label: "Retención",
        icon: Receipt,
        show: (item) => (item as any).code !== "04",
        class: (item) =>
            item.serie_retention
                ? "text-green-600! hover:bg-green-100!"
                : "bg-muted text-muted-foreground hover:bg-muted/80",
    },
    {
        event: "edit",
        label: "Editar",
        icon: Pencil,
    },
    {
        event: "delete",
        label: "Eliminar",
        icon: Trash2,
        separator: true,
        class: "text-destructive focus:text-destructive",
    },
];

// ─── Filters ────────────────────────────────────────────────────────────────

function applyFilters(filters: OrderFilters) {
    router.get(route("tenant.orders.index"), filters as Record<string, string>, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

// ─── Actions ────────────────────────────────────────────────────────────────

const deleteTarget = ref<Order | null>(null);
const deleteLoading = ref(false);
const retentionSlideOver = ref<InstanceType<typeof RetentionSlideOver> | null>(null);

function handleAction({ event, item }: ActionPayload<Order>) {
    if (event === "edit") {
        router.visit(route("tenant.orders.edit", { order: item.id }));
    } else if (event === "retention") {
        retentionSlideOver.value?.open(item);
    } else if (event === "delete") {
        deleteTarget.value = item;
    }
}

function handleSelect(item: Order) {
    router.visit(route("tenant.orders.edit", { order: item.id }));
}

function handlePageChange(page: number) {
    router.get(route("tenant.orders.index"), { ...props.filters, page }, { preserveScroll: true });
}

function confirmDelete() {
    if (!deleteTarget.value) return;
    deleteLoading.value = true;
    router.delete(route("tenant.orders.destroy", { order: deleteTarget.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            deleteLoading.value = false;
            deleteTarget.value = null;
        },
    });
}

// ─── Failed keys download ────────────────────────────────────────────────────

const page = usePage<{ flash: { failedKeys?: string[] } }>();

watch(
    () => page.props.flash.failedKeys,
    (keys) => {
        if (!keys || keys.length === 0) return;
        const blob = new Blob([keys.join("\n")], { type: "text/plain" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = "claves_no_importadas.txt";
        a.click();
        URL.revokeObjectURL(url);
    },
    { immediate: true },
);

// ─── Import ─────────────────────────────────────────────────────────────────

const importFileInput = ref<HTMLInputElement | null>(null);
const importForm = useForm<{ file: File | null }>({ file: null });

const importRetentionsFileInput = ref<HTMLInputElement | null>(null);
const importRetentionsForm = useForm<{ file: File | null }>({ file: null });

function handleFileSelected(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    importForm.file = file;
    importForm.post(route("tenant.orders.import"), {
        forceFormData: true,
        onFinish: () => {
            importForm.reset();
            if (importFileInput.value) importFileInput.value.value = "";
        },
    });
}

function handleRetentionsFileSelected(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    importRetentionsForm.file = file;
    importRetentionsForm.post(route("tenant.orders.import-retentions"), {
        forceFormData: true,
        onFinish: () => {
            importRetentionsForm.reset();
            if (importRetentionsFileInput.value) importRetentionsFileInput.value.value = "";
        },
    });
}

// ─── Export ──────────────────────────────────────────────────────────────────

const orderExportModal = ref<InstanceType<typeof OrderExportModal> | null>(null);
</script>

<template>
    <Head title="Ventas — Listado de facturas" />
    <TenantLayout>
        <div class="flex flex-col gap-4 w-full">
            <!-- Header -->
            <HeaderList
                title="Ventas"
                :description="`${orders.total} venta${orders.total !== 1 ? 's' : ''} registrada${orders.total !== 1 ? 's' : ''}`"
                link-label="Nueva venta"
                :link-href="route('tenant.orders.create')"
                :show-import="true"
                import-label="Importar SRI"
                @click-import="importFileInput?.click()"
            >
                <template #extra-actions>
                    <Button variant="outline" size="sm" @click="orderExportModal?.open()">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="size-4"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"
                            />
                        </svg>
                        <span class="hidden md:inline-block">Exportar Excel</span>
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="hidden font-bold md:inline-flex"
                        :disabled="importRetentionsForm.processing"
                        @click="importRetentionsFileInput?.click()"
                    >
                        <Upload class="size-4" />
                        {{ importRetentionsForm.processing ? "Importando…" : "Importar retenciones" }}
                    </Button>
                </template>
            </HeaderList>

            <!-- Filters -->
            <FilterBar :filters="filters" @change="applyFilters" />

            <!-- Hidden file inputs -->
            <input ref="importFileInput" type="file" accept=".txt,.zip" class="hidden" @change="handleFileSelected" />
            <input
                ref="importRetentionsFileInput"
                type="file"
                accept=".txt"
                class="hidden"
                @change="handleRetentionsFileSelected"
            />

            <!-- Table -->
            <div class="border-border bg-card overflow-hidden rounded-lg border">
                <div class="hidden h-full md:block">
                    <DataTableDesktop
                        :columns="columns"
                        :items="orders.data"
                        :actions="actions"
                        empty-text="No hay ventas registradas."
                        :actionsMode="'icons'"
                        @select="handleSelect"
                        @action="handleAction"
                    />
                </div>
                <div class="h-full md:hidden">
                    <DataTableMobile
                        :columns="columns"
                        :items="orders.data"
                        :actions="actions"
                        empty-text="No hay ventas registradas."
                        @select="handleSelect"
                        @action="handleAction"
                    />
                </div>
            </div>

            <Pagination :paginator="orders" @change-page="handlePageChange" />
        </div>

        <!-- Export modal -->
        <OrderExportModal ref="orderExportModal" :filters="filters" />

        <!-- Confirm delete -->
        <ConfirmDialog
            :open="!!deleteTarget"
            title="¿Eliminar venta?"
            :description="`Se eliminará la venta ${deleteTarget?.serie}. Esta acción no se puede deshacer.`"
            :loading="deleteLoading"
            @update:open="
                (v) => {
                    if (!v) deleteTarget = null;
                }
            "
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />

        <!-- Retention slide-over -->
        <RetentionSlideOver ref="retentionSlideOver" />
    </TenantLayout>
</template>
