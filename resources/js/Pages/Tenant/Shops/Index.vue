<script setup lang="ts">
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";

import DataTableDesktop from "@/components/Shared/DataTableDesktop.vue";
import DataTableMobile from "@/components/Shared/DataTableMobile.vue";
import HeaderList from "@/components/Shared/HeaderList.vue";
import Pagination from "@/components/Shared/Pagination.vue";
import ConfirmDialog from "@/components/Shared/ConfirmDialog.vue";
import TenantLayout from "@/layouts/TenantLayout.vue";

import { Button } from "@/components/ui/button";
import AccountSlideOver from "./components/AccountSlideOver.vue";
import RetentionSlideOver from "./components/RetentionSlideOver.vue";
import FilterBar from "./components/FilterBar.vue";
import ShopExportModal from "./components/ShopExportModal.vue";

import type { ActionDef, ActionPayload, ColumnDef } from "@/types/shared";
import { Paginator } from "@/types";
import { Shop } from "@/types/tenant";
import { Download, FileText, Pencil, Receipt, Trash2, ClipboardList } from "lucide-vue-next";

// ─── Props ─────────────────────────────────────────────────────────────────

interface ShopFilters {
    search: string;
    period: string;
    retention: string;
    voucher_type: string;
}

const props = defineProps<{
    shops: Paginator<Shop>;
    isActiveRetention: boolean;
    filters: ShopFilters;
}>();

// ─── Table columns ──────────────────────────────────────────────────────────

const voucherTypes: Record<string, { label: string; class: string }> = {
    "01": {
        label: "Factura",
        class: "bg-blue-100   text-blue-700   dark:bg-blue-900/30   dark:text-blue-400   border-0",
    },
    "02": {
        label: "Nota Venta",
        class: "bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400 border-0",
    },
    "03": {
        label: "Liq. Compra",
        class: "bg-amber-100  text-amber-700  dark:bg-amber-900/30  dark:text-amber-400  border-0",
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

const columns: ColumnDef<Shop>[] = [
    { key: "emision", label: "Emisión" },
    {
        key: "voucher_type_code",
        label: "Tipo",
        badge: {
            value: (item) => voucherTypes[item.voucher_type_code]?.label ?? item.voucher_type_code,
            class: (item) => voucherTypes[item.voucher_type_code]?.class ?? "",
        },
    },
    {
        key: "serie",
        label: "Serie",
        format: (_, item) => item.serie,
        labelDescription: (_, item) => {
            const parts: string[] = [];
            if (item.state === "NO_DECLARA") parts.push("No declara");
            if (item.data_additional?.with_cedula) parts.push("Cédula");
            if (item.serie_retention) parts.push(`Ret. ${item.serie_retention}`);
            return parts.join(" · ");
        },
    },
    {
        key: "contact",
        label: "Proveedor",
        format: (_, item) => item.contact?.name ?? "—",
    },
    {
        key: "total",
        label: "Total",
        align: "right",
        format: (v) => `$${Number(v).toFixed(2)}`,
    },
];

const columnsWithRetention: ColumnDef<Shop>[] = [
    ...columns,
    {
        key: "retention_items",
        label: "Retención",
        align: "right",
        format: (_, item) => `$${Number(item.retention_amount).toFixed(2)}`,
    },
    {
        key: "total",
        label: "A pagar",
        align: "right",
        format: (_, item) => `$${(Number(item.total) - Number(item.retention_amount)).toFixed(2)}`,
    },
];

const activeColumns = computed(() => (props.isActiveRetention ? columnsWithRetention : columns));

const actions = computed<ActionDef<Shop>[]>(() => [
    ...(props.isActiveRetention
        ? [
              {
                  event: "retention",
                  label: "Retención",
                  icon: FileText,
                  show: (item) => (item as any).voucher_type_code !== "04",
                  class: (item) =>
                      item.serie_retention
                          ? "text-green-600! hover:bg-green-100!"
                          : "bg-muted text-muted-foreground hover:bg-muted/80",
              } as ActionDef<Shop>,
          ]
        : []),
    {
        event: "account",
        label: "Cuenta contable",
        icon: Receipt,
        class: (item) =>
            item.account_id ? "text-blue-600! hover:bg-blue-100!" : "bg-muted text-muted-foreground hover:bg-muted/80",
    },
    { event: "edit", label: "Editar", icon: Pencil },
    {
        event: "delete",
        label: "Eliminar",
        separator: true,
        class: "text-destructive focus:text-destructive",
        icon: Trash2,
    },
]);

// ─── Filters ────────────────────────────────────────────────────────────────

function applyFilters(filters: ShopFilters) {
    router.get(route("tenant.shops.index"), filters as Record<string, string>, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

// ─── Slide-over refs ────────────────────────────────────────────────────────

const accountSlideOver = ref<InstanceType<typeof AccountSlideOver> | null>(null);
const retentionSlideOver = ref<InstanceType<typeof RetentionSlideOver> | null>(null);
const shopExportModal = ref<InstanceType<typeof ShopExportModal> | null>(null);

// ─── Actions ────────────────────────────────────────────────────────────────

const deleteTarget = ref<Shop | null>(null);
const deleteLoading = ref(false);

function handleAction({ event, item }: ActionPayload<Shop>) {
    if (event === "edit") {
        router.visit(route("tenant.shops.edit", { shop: item.id }));
    } else if (event === "account") {
        accountSlideOver.value?.open(item);
    } else if (event === "retention") {
        retentionSlideOver.value?.open(item);
    } else if (event === "delete") {
        deleteTarget.value = item;
    }
}

function handleSelect(item: Shop) {
    router.visit(route("tenant.shops.edit", { shop: item.id }));
}

function handlePageChange(page: number) {
    router.get(
        route("tenant.shops.index"),
        { ...props.filters, page },
        {
            preserveScroll: true,
        },
    );
}

function confirmDelete() {
    const target = deleteTarget.value;
    if (!target) return;
    deleteTarget.value = null;
    deleteLoading.value = true;
    router.delete(route("tenant.shops.destroy", { shop: target.id }), {
        preserveScroll: true,
        onFinish: () => {
            deleteLoading.value = false;
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

const completeRetentionsForm = useForm({});

const showCompleteRetentions = computed(
    () => props.isActiveRetention && props.filters.retention === "without" && props.shops.total > 0,
);

function completeRetentions() {
    completeRetentionsForm.post(
        route("tenant.shops.complete-retentions", {
            search: props.filters.search,
            period: props.filters.period,
            retention: props.filters.retention,
            voucher_type: props.filters.voucher_type,
        }),
        { preserveScroll: true },
    );
}

function handleFileSelected(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    importForm.file = file;
    importForm.post(route("tenant.shops.import"), {
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
    importRetentionsForm.post(route("tenant.shops.import-retentions"), {
        forceFormData: true,
        onFinish: () => {
            importRetentionsForm.reset();
            if (importRetentionsFileInput.value) importRetentionsFileInput.value.value = "";
        },
    });
}

// ─── Reset panels on page change ────────────────────────────────────────────

watch(
    () => props.shops.current_page,
    () => {
        accountSlideOver.value?.close();
        retentionSlideOver.value?.close();
    },
);
</script>

<template>
    <Head title="Compras — Listado de facturas" />
    <TenantLayout>
        <div class="flex flex-col gap-4 w-full">
            <!-- Header -->
            <HeaderList
                title="Compras"
                :description="`${shops.total} compra${shops.total !== 1 ? 's' : ''} registrada${shops.total !== 1 ? 's' : ''}`"
                link-label="Nueva compra"
                :link-href="route('tenant.shops.create')"
                :show-import="true"
                import-label="Importar SRI"
                @click-import="importFileInput?.click()"
            >
                <template #extra-actions>
                    <Button
                        v-if="showCompleteRetentions"
                        variant="outline"
                        size="sm"
                        :disabled="completeRetentionsForm.processing"
                        @click="completeRetentions"
                    >
                        <ClipboardList class="size-4" />
                        {{ completeRetentionsForm.processing ? "Completando…" : "Completar retenciones" }}
                    </Button>
                    <Button variant="outline" size="sm" @click="shopExportModal?.open()">
                        <Download class="size-4" />
                        Descargar
                    </Button>
                    <Button
                        v-if="isActiveRetention"
                        variant="outline"
                        size="sm"
                        class="hidden font-bold md:inline-flex"
                        :disabled="importRetentionsForm.processing"
                        @click="importRetentionsFileInput?.click()"
                    >
                        {{ importRetentionsForm.processing ? "Importando…" : "Importar retenciones" }}
                    </Button>
                </template>
            </HeaderList>

            <!-- Filters -->
            <FilterBar :filters="filters" :show-retention="isActiveRetention" @change="applyFilters" />

            <!-- Hidden file inputs -->
            <input ref="importFileInput" type="file" accept=".txt,.xml,.zip" class="hidden" @change="handleFileSelected" />
            <input
                ref="importRetentionsFileInput"
                type="file"
                accept=".txt,.zip"
                class="hidden"
                @change="handleRetentionsFileSelected"
            />

            <!-- Table -->
            <div class="border-border bg-card flex-1 overflow-hidden rounded-lg border">
                <div class="hidden h-full md:block">
                    <DataTableDesktop
                        :columns="activeColumns"
                        :items="shops.data"
                        :actions="actions"
                        empty-text="No hay compras registradas."
                        :actions-mode="'icons'"
                        :row-class="
                            (item: Shop) =>
                                item.serie_retention ? 'bg-green-100 hover:bg-green-200 dark:bg-green-900/20' : ''
                        "
                        @select="handleSelect"
                        @action="handleAction"
                    />
                </div>
                <div class="h-full md:hidden">
                    <DataTableMobile
                        :columns="activeColumns"
                        :items="shops.data"
                        :actions="actions"
                        empty-text="No hay compras registradas."
                        @select="handleSelect"
                        @action="handleAction"
                    />
                </div>
            </div>

            <Pagination :paginator="shops" @change-page="handlePageChange" />
        </div>

        <!-- Confirm delete -->
        <ConfirmDialog
            :open="!!deleteTarget"
            title="¿Eliminar compra?"
            :description="`Se eliminará la compra ${deleteTarget?.serie}. Esta acción no se puede deshacer.`"
            :loading="deleteLoading"
            @update:open="
                (v) => {
                    if (!v) deleteTarget.value = null;
                }
            "
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />

        <!-- Slide-overs -->
        <AccountSlideOver ref="accountSlideOver" />
        <RetentionSlideOver ref="retentionSlideOver" />

        <!-- Export modal -->
        <ShopExportModal ref="shopExportModal" :filters="filters" />
    </TenantLayout>
</template>
