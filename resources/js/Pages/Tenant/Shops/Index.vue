<script setup lang="ts">
import { router, useForm } from "@inertiajs/vue3";
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

import type { ActionDef, ActionPayload, ColumnDef } from "@/types/shared";
import { Paginator } from "@/types";
import { Account, RetentionItem, RetentionOption, Shop } from "@/types/tenant";
import { FileText, Pencil, Receipt, Trash2 } from "lucide-vue-next";

// ─── Props ─────────────────────────────────────────────────────────────────

const props = defineProps<{
    shops: Paginator<Shop>;
    retentions: RetentionOption[];
    accounts: Account[];
    isActiveRetention: boolean;
}>();

// ─── Table columns ──────────────────────────────────────────────────────────

const columns: ColumnDef<Shop>[] = [
    { key: "emision", label: "Emisión" },
    {
        key: "serie",
        label: "Serie",
        format: (_, item) => `${item.initial}-${item.serie}`,
        labelDescription: (_, item) => (item.serie_retention ? `${item.serie_retention}` : ""),
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
        format: (_, item) =>
            `$${(item.retention_items ?? [])
                .reduce((s: number, i: RetentionItem) => s + Number(i.value), 0)
                .toFixed(2)}`,
    },
    {
        key: "total",
        label: "A pagar",
        align: "right",
        format: (_, item) => {
            const ret = (item.retention_items ?? []).reduce((s: number, i: RetentionItem) => s + Number(i.value), 0);
            return `$${(Number(item.total) - ret).toFixed(2)}`;
        },
    },
];

const activeColumns = computed(() => (props.isActiveRetention ? columnsWithRetention : columns));

const actions: ActionDef<Shop>[] = [
    { event: "edit", label: "Editar", icon: Pencil },
    { event: "account", label: "Cuenta contable", icon: Receipt },
    ...(props.isActiveRetention
        ? [
              {
                  event: "retention",
                  label: "Retención",
                  icon: FileText,
                  class: (item) =>
                      item.serie_retention
                          ? "text-green-600! hover:bg-green-100!"
                          : "bg-muted text-muted-foreground hover:bg-muted/80",
              } as ActionDef<Shop>,
          ]
        : []),
    {
        event: "delete",
        label: "Eliminar",
        separator: true,
        class: "text-destructive focus:text-destructive",
        icon: Trash2,
    },
];

// ─── Slide-over refs ────────────────────────────────────────────────────────

const accountSlideOver = ref<InstanceType<typeof AccountSlideOver> | null>(null);
const retentionSlideOver = ref<InstanceType<typeof RetentionSlideOver> | null>(null);

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
    router.visit(route("tenant.shops.index", { page }), {
        preserveScroll: true,
    });
}

function confirmDelete() {
    if (!deleteTarget.value) return;
    deleteLoading.value = true;
    router.delete(route("tenant.shops.destroy", { shop: deleteTarget.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            deleteLoading.value = false;
            deleteTarget.value = null;
        },
    });
}

// ─── Import ─────────────────────────────────────────────────────────────────

const importFileInput = ref<HTMLInputElement | null>(null);
const importForm = useForm<{ file: File | null }>({ file: null });

const importRetentionsFileInput = ref<HTMLInputElement | null>(null);
const importRetentionsForm = useForm<{ file: File | null }>({ file: null });

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
                        v-if="isActiveRetention"
                        variant="outline"
                        size="sm"
                        class="font-bold"
                        :disabled="importRetentionsForm.processing"
                        @click="importRetentionsFileInput?.click()"
                    >
                        {{ importRetentionsForm.processing ? "Importando…" : "Importar retenciones" }}
                    </Button>
                </template>
            </HeaderList>

            <!-- Hidden file inputs -->
            <input ref="importFileInput" type="file" accept=".txt" class="hidden" @change="handleFileSelected" />
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
            @update:open="(v) => { if (!v) deleteTarget = null; }"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />

        <!-- Slide-overs -->
        <AccountSlideOver ref="accountSlideOver" :accounts="accounts" />
        <RetentionSlideOver ref="retentionSlideOver" :retentions="retentions" />
    </TenantLayout>
</template>
