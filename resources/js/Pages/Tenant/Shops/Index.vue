<script setup lang="ts">
import { router, useForm } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";

import DataTableDesktop from "@/components/Shared/DataTableDesktop.vue";
import DataTableMobile from "@/components/Shared/DataTableMobile.vue";
import HeaderList from "@/components/Shared/HeaderList.vue";
import Pagination from "@/components/Shared/Pagination.vue";
import ConfirmDialog from "@/components/Shared/ConfirmDialog.vue";
import TenantLayout from "@/layouts/TenantLayout.vue";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";

import type { ActionDef, ActionPayload, ColumnDef } from "@/types/shared";
import { PaginatedData } from "@/types";
import { Account, RetentionItem, RetentionOption, Shop } from "@/types/tenant";

// ─── Props ─────────────────────────────────────────────────────────────────

const props = defineProps<{
    shops: PaginatedData<Shop>;
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
    { event: "edit", label: "Editar" },
    { event: "account", label: "Cuenta contable" },
    ...(props.isActiveRetention ? [{ event: "retention", label: "Retención" } as ActionDef<Shop>] : []),
    {
        event: "delete",
        label: "Eliminar",
        separator: true,
        class: "text-destructive focus:text-destructive",
    },
];

// ─── Actions ────────────────────────────────────────────────────────────────

const deleteTarget = ref<Shop | null>(null);
const deleteLoading = ref(false);

function handleAction({ event, item }: ActionPayload<Shop>) {
    if (event === "edit") {
        router.visit(route("tenant.shops.edit", { shop: item.id }));
    } else if (event === "account") {
        openAccountPanel(item);
    } else if (event === "retention") {
        openRetentionPanel(item);
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

// ─── Account panel ──────────────────────────────────────────────────────────

const accountPanelOpen = ref(false);
const accountPanelEditing = ref(false);
const selectedShopForAccount = ref<Shop | null>(null);
const accountQuery = ref("");
const accountDropdownOpen = ref(false);
const accountForm = useForm<{ acount_id: number | null }>({ acount_id: null });

function openAccountPanel(shop: Shop) {
    selectedShopForAccount.value = shop;
    accountForm.acount_id = shop.acount_id;
    accountPanelEditing.value = !shop.acount_id;
    accountQuery.value = shop.account ? `${shop.account.code} – ${shop.account.name}` : "";
    accountPanelOpen.value = true;
}

function closeAccountPanel() {
    accountPanelOpen.value = false;
    accountPanelEditing.value = false;
    selectedShopForAccount.value = null;
    accountQuery.value = "";
}

function filteredAccounts(): Account[] {
    const q = accountQuery.value.trim().toLowerCase();
    if (!q) return props.accounts.slice(0, 8);
    return props.accounts
        .filter((a) => a.code.toLowerCase().includes(q) || a.name.toLowerCase().includes(q))
        .slice(0, 8);
}

function selectAccount(account: Account) {
    accountForm.acount_id = account.id;
    accountQuery.value = `${account.code} – ${account.name}`;
    accountDropdownOpen.value = false;
}

function submitAccount() {
    if (!selectedShopForAccount.value) return;
    accountForm.patch(
        route("tenant.shops.account.update", {
            shop: selectedShopForAccount.value.id,
        }),
        { onSuccess: () => closeAccountPanel() },
    );
}

// ─── Retention panel ─────────────────────────────────────────────────────────

const today = new Date().toISOString().slice(0, 10);
const retentionPanelOpen = ref(false);
const selectedShop = ref<Shop | null>(null);

interface ItemSearch {
    query: string;
    open: boolean;
}
const itemSearches = ref<ItemSearch[]>([]);

function emptyItem(subTotal: string | number = 0): RetentionItem {
    return { retention_id: null, base: subTotal, percentage: "", value: "" };
}

function emptySearch(): ItemSearch {
    return { query: "", open: false };
}

const retentionForm = useForm<{
    serie_retention: string;
    date_retention: string;
    autorization_retention: string;
    items: RetentionItem[];
}>({
    serie_retention: "",
    date_retention: "",
    autorization_retention: "",
    items: [emptyItem()],
});

function openRetentionPanel(shop: Shop) {
    selectedShop.value = shop;
    if (!shop.serie_retention) {
        retentionForm.reset();
        retentionForm.items = [emptyItem(shop.sub_total)];
        itemSearches.value = [emptySearch()];
    }
    retentionPanelOpen.value = true;
}

function closeRetentionPanel() {
    retentionPanelOpen.value = false;
    selectedShop.value = null;
}

function addItem() {
    retentionForm.items.push(emptyItem(selectedShop.value?.sub_total ?? 0));
    itemSearches.value.push(emptySearch());
}

function removeItem(index: number) {
    retentionForm.items.splice(index, 1);
    itemSearches.value.splice(index, 1);
}

function filteredRetentions(idx: number): RetentionOption[] {
    const q = itemSearches.value[idx]?.query.trim().toLowerCase();
    if (!q) return props.retentions.slice(0, 8);
    return props.retentions
        .filter((r) => r.code.toLowerCase().includes(q) || r.description.toLowerCase().includes(q))
        .slice(0, 8);
}

function selectRetention(idx: number, retention: RetentionOption) {
    const item = retentionForm.items[idx];
    item.retention_id = retention.id;
    item.percentage = retention.percentage;
    recalcValue(idx);
    itemSearches.value[idx].query = `${retention.code} – ${retention.description}`;
    itemSearches.value[idx].open = false;
}

function recalcValue(idx: number) {
    const item = retentionForm.items[idx];
    const base = parseFloat(String(item.base)) || 0;
    const pct = parseFloat(String(item.percentage)) || 0;
    item.value = parseFloat(((base * pct) / 100).toFixed(2));
}

function submitRetention() {
    if (!selectedShop.value) return;
    retentionForm.post(
        route("tenant.shops.retention.store", {
            shop: selectedShop.value.id,
        }),
        { onSuccess: () => closeRetentionPanel() },
    );
}

const typeLabel: Record<string, string> = { iva: "IVA", renta: "Renta" };

function closeDropdownDelayed(close: () => void) {
    setTimeout(() => close(), 150);
}

function closeItemSearchDelayed(idx: number) {
    setTimeout(() => {
        if (itemSearches.value[idx]) {
            itemSearches.value[idx].open = false;
        }
    }, 150);
}

watch(
    () => props.shops.current_page,
    () => {
        // reset any open panels on page change
        closeAccountPanel();
        closeRetentionPanel();
    },
);
</script>

<template>
    <TenantLayout>
        <div class="flex flex-col gap-4 w-full max-w-full md:max-w-2xl xl:max-w-7xl mx-auto">
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
                <!-- Extra button for retentions import -->
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
                    if (!v) deleteTarget = null;
                }
            "
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />

        <!-- Account slide-over -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="accountPanelOpen" class="fixed inset-0 z-40 bg-black/40" @click="closeAccountPanel" />
            </Transition>

            <Transition
                enter-active-class="transition-transform duration-200 ease-out"
                enter-from-class="translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform duration-150 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="translate-x-full"
            >
                <div
                    v-if="accountPanelOpen && selectedShopForAccount"
                    class="bg-background border-border fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col border-l shadow-xl"
                >
                    <div class="border-border flex items-start justify-between border-b px-6 py-4">
                        <div>
                            <h2 class="text-foreground text-base font-semibold">Cuenta contable</h2>
                            <p class="text-muted-foreground mt-0.5 font-mono text-sm">
                                {{ selectedShopForAccount.serie }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-foreground -mr-1 rounded-md p-1 transition-colors"
                            @click="closeAccountPanel"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                class="size-5"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- View mode -->
                    <div
                        v-if="
                            selectedShopForAccount.account &&
                            accountForm.acount_id === selectedShopForAccount.acount_id &&
                            !accountPanelEditing
                        "
                        class="flex flex-1 flex-col p-6"
                    >
                        <p class="text-muted-foreground mb-1 text-xs font-medium tracking-wider uppercase">
                            Cuenta asignada
                        </p>
                        <div class="border-border bg-muted flex items-start gap-3 rounded-lg border px-4 py-3">
                            <span class="text-foreground font-mono text-sm font-semibold">{{
                                selectedShopForAccount.account.code
                            }}</span>
                            <span class="text-foreground text-sm">{{ selectedShopForAccount.account.name }}</span>
                        </div>
                        <button
                            type="button"
                            class="text-primary hover:text-primary/70 mt-4 self-start text-sm font-medium"
                            @click="accountPanelEditing = true"
                        >
                            Cambiar cuenta
                        </button>
                    </div>

                    <!-- Assign form -->
                    <form v-else class="flex flex-1 flex-col overflow-hidden" @submit.prevent="submitAccount">
                        <div class="flex-1 overflow-y-auto p-6">
                            <p class="text-muted-foreground mb-3 text-xs font-medium tracking-wider uppercase">
                                Buscar cuenta de costo o gasto
                            </p>
                            <div class="relative">
                                <input
                                    v-model="accountQuery"
                                    type="text"
                                    placeholder="Buscar por código o nombre…"
                                    class="border-border bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring/30 h-9 w-full rounded-md border px-3 pr-8 text-sm focus:ring-2 focus:outline-none"
                                    @focus="accountDropdownOpen = true"
                                    @blur="closeDropdownDelayed(() => (accountDropdownOpen = false))"
                                />
                                <button
                                    v-if="accountForm.acount_id"
                                    type="button"
                                    class="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2"
                                    @mousedown.prevent="
                                        () => {
                                            accountForm.acount_id = null;
                                            accountQuery = '';
                                        }
                                    "
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor"
                                        class="size-4"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                                <div
                                    v-if="accountDropdownOpen && filteredAccounts().length > 0"
                                    class="border-border bg-popover absolute top-full right-0 left-0 z-10 mt-1 max-h-60 overflow-y-auto rounded-md border shadow-lg"
                                >
                                    <button
                                        v-for="account in filteredAccounts()"
                                        :key="account.id"
                                        type="button"
                                        class="hover:bg-accent flex w-full items-center gap-3 px-3 py-2 text-left text-sm transition-colors"
                                        @mousedown.prevent="selectAccount(account)"
                                    >
                                        <span class="text-foreground w-28 shrink-0 font-mono text-xs font-semibold">{{
                                            account.code
                                        }}</span>
                                        <span class="text-muted-foreground flex-1 truncate text-xs">{{
                                            account.name
                                        }}</span>
                                    </button>
                                </div>
                            </div>
                            <div
                                v-if="accountForm.acount_id"
                                class="border-border bg-muted mt-3 flex items-center gap-3 rounded-lg border px-4 py-2.5"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="text-primary size-4 shrink-0"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <span class="text-foreground text-sm">{{ accountQuery }}</span>
                            </div>
                        </div>
                        <div class="border-border flex items-center justify-end gap-3 border-t px-6 py-4">
                            <Button variant="outline" type="button" @click="closeAccountPanel">Cancelar</Button>
                            <Button type="submit" :disabled="accountForm.processing || !accountForm.acount_id">
                                {{ accountForm.processing ? "Guardando…" : "Guardar cuenta" }}
                            </Button>
                        </div>
                    </form>
                </div>
            </Transition>
        </Teleport>

        <!-- Retention slide-over -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="retentionPanelOpen" class="fixed inset-0 z-40 bg-black/40" @click="closeRetentionPanel" />
            </Transition>

            <Transition
                enter-active-class="transition-transform duration-200 ease-out"
                enter-from-class="translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform duration-150 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="translate-x-full"
            >
                <div
                    v-if="retentionPanelOpen && selectedShop"
                    class="bg-background border-border fixed inset-y-0 right-0 z-50 flex w-full max-w-4xl border-l shadow-xl"
                >
                    <!-- Left: invoice info -->
                    <div class="border-border flex w-72 shrink-0 flex-col border-r">
                        <div class="border-border border-b px-5 py-4">
                            <p class="text-muted-foreground text-xs font-semibold tracking-widest uppercase">Factura</p>
                            <p class="text-foreground mt-0.5 font-mono text-sm font-medium">
                                {{ selectedShop.serie }}
                            </p>
                        </div>
                        <div class="flex-1 space-y-4 overflow-y-auto p-5">
                            <div>
                                <p class="text-muted-foreground mb-0.5 text-xs font-medium">Proveedor</p>
                                <p class="text-foreground text-sm">
                                    {{ selectedShop.contact?.name }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground mb-0.5 text-xs font-medium">Fecha emisión</p>
                                <p class="text-foreground text-sm tabular-nums">
                                    {{ selectedShop.emision }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground mb-0.5 text-xs font-medium">Clave de acceso</p>
                                <p class="text-foreground font-mono text-xs break-all">
                                    {{ selectedShop.autorization }}
                                </p>
                            </div>
                            <!-- IVA breakdown -->
                            <div class="border-border overflow-hidden rounded-lg border">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-muted">
                                        <tr>
                                            <th class="text-muted-foreground px-3 py-2 text-left font-medium">
                                                Concepto
                                            </th>
                                            <th class="text-muted-foreground px-3 py-2 text-right font-medium">
                                                Valor
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-border divide-y">
                                        <tr v-if="Number(selectedShop.no_iva) > 0">
                                            <td class="text-muted-foreground px-3 py-1.5">No IVA</td>
                                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                                ${{ Number(selectedShop.no_iva).toFixed(2) }}
                                            </td>
                                        </tr>
                                        <tr v-if="Number(selectedShop.base0) > 0">
                                            <td class="text-muted-foreground px-3 py-1.5">Base 0%</td>
                                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                                ${{ Number(selectedShop.base0).toFixed(2) }}
                                            </td>
                                        </tr>
                                        <tr v-if="Number(selectedShop.base12) > 0">
                                            <td class="text-muted-foreground px-3 py-1.5">Base 12%</td>
                                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                                ${{ Number(selectedShop.base12).toFixed(2) }}
                                            </td>
                                        </tr>
                                        <tr v-if="Number(selectedShop.iva12) > 0">
                                            <td class="text-muted-foreground px-3 py-1.5 pl-5">IVA 12%</td>
                                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                                ${{ Number(selectedShop.iva12).toFixed(2) }}
                                            </td>
                                        </tr>
                                        <tr v-if="Number(selectedShop.base15) > 0">
                                            <td class="text-muted-foreground px-3 py-1.5">Base 15%</td>
                                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                                ${{ Number(selectedShop.base15).toFixed(2) }}
                                            </td>
                                        </tr>
                                        <tr v-if="Number(selectedShop.iva15) > 0">
                                            <td class="text-muted-foreground px-3 py-1.5 pl-5">IVA 15%</td>
                                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                                ${{ Number(selectedShop.iva15).toFixed(2) }}
                                            </td>
                                        </tr>
                                        <tr v-if="Number(selectedShop.discount) > 0">
                                            <td class="text-muted-foreground px-3 py-1.5">Descuento</td>
                                            <td class="text-destructive px-3 py-1.5 text-right font-mono">
                                                -${{ Number(selectedShop.discount).toFixed(2) }}
                                            </td>
                                        </tr>
                                        <tr v-if="Number(selectedShop.ice) > 0">
                                            <td class="text-muted-foreground px-3 py-1.5">ICE</td>
                                            <td class="text-foreground px-3 py-1.5 text-right font-mono">
                                                ${{ Number(selectedShop.ice).toFixed(2) }}
                                            </td>
                                        </tr>
                                        <tr class="bg-muted font-semibold">
                                            <td class="text-foreground px-3 py-2">Total</td>
                                            <td class="text-foreground px-3 py-2 text-right font-mono">
                                                ${{ Number(selectedShop.total).toFixed(2) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Right: retention panel -->
                    <div class="flex flex-1 flex-col">
                        <div class="border-border flex items-start justify-between border-b px-6 py-4">
                            <div>
                                <h2 class="text-foreground text-base font-semibold">Retención</h2>
                                <p class="text-muted-foreground mt-0.5 font-mono text-sm">
                                    {{ selectedShop.serie_retention ?? "001-001-000000001" }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground -mr-1 rounded-md p-1 transition-colors"
                                @click="closeRetentionPanel"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                    class="size-5"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Registered view -->
                        <div v-if="selectedShop.serie_retention" class="flex-1 overflow-y-auto p-6">
                            <div class="mb-6 grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-muted-foreground mb-1 text-xs font-medium tracking-wider uppercase">
                                        Serie
                                    </p>
                                    <p class="text-foreground font-mono text-sm font-medium">
                                        {{ selectedShop.serie_retention }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground mb-1 text-xs font-medium tracking-wider uppercase">
                                        Fecha
                                    </p>
                                    <p class="text-foreground text-sm">
                                        {{ selectedShop.date_retention }}
                                    </p>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-muted-foreground mb-1 text-xs font-medium tracking-wider uppercase">
                                        Autorización
                                    </p>
                                    <p class="text-foreground font-mono text-sm break-all">
                                        {{ selectedShop.autorization_retention }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground mb-1 text-xs font-medium tracking-wider uppercase">
                                        Estado
                                    </p>
                                    <Badge>{{ selectedShop.state_retention }}</Badge>
                                </div>
                            </div>

                            <p class="text-muted-foreground mb-3 text-xs font-medium tracking-wider uppercase">
                                Detalle
                            </p>
                            <div class="border-border overflow-hidden rounded-lg border">
                                <table class="divide-border min-w-full divide-y text-sm">
                                    <thead class="bg-muted">
                                        <tr>
                                            <th
                                                class="text-muted-foreground px-4 py-2.5 text-left text-xs font-medium uppercase"
                                            >
                                                Tipo
                                            </th>
                                            <th
                                                class="text-muted-foreground px-4 py-2.5 text-left text-xs font-medium uppercase"
                                            >
                                                Código
                                            </th>
                                            <th
                                                class="text-muted-foreground px-4 py-2.5 text-left text-xs font-medium uppercase"
                                            >
                                                Descripción
                                            </th>
                                            <th
                                                class="text-muted-foreground px-4 py-2.5 text-right text-xs font-medium uppercase"
                                            >
                                                Base
                                            </th>
                                            <th
                                                class="text-muted-foreground px-4 py-2.5 text-right text-xs font-medium uppercase"
                                            >
                                                %
                                            </th>
                                            <th
                                                class="text-muted-foreground px-4 py-2.5 text-right text-xs font-medium uppercase"
                                            >
                                                Valor
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-border divide-y">
                                        <tr v-for="item in selectedShop.retention_items" :key="item.id">
                                            <td class="text-foreground px-4 py-2.5">
                                                {{ typeLabel[item.retention?.type ?? ""] ?? item.retention?.type }}
                                            </td>
                                            <td class="text-foreground px-4 py-2.5 font-mono">
                                                {{ item.retention?.code }}
                                            </td>
                                            <td class="text-muted-foreground px-4 py-2.5 text-sm">
                                                {{ item.retention?.description }}
                                            </td>
                                            <td class="text-foreground px-4 py-2.5 text-right font-mono">
                                                ${{ Number(item.base).toFixed(2) }}
                                            </td>
                                            <td class="text-foreground px-4 py-2.5 text-right font-mono">
                                                {{ item.percentage }}%
                                            </td>
                                            <td class="text-foreground px-4 py-2.5 text-right font-mono font-medium">
                                                ${{ Number(item.value).toFixed(2) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-muted">
                                        <tr>
                                            <td
                                                colspan="5"
                                                class="text-muted-foreground px-4 py-2.5 text-right text-xs font-medium uppercase"
                                            >
                                                Total retención
                                            </td>
                                            <td class="text-foreground px-4 py-2.5 text-right font-mono font-semibold">
                                                ${{
                                                    selectedShop.retention_items
                                                        .reduce((s, i) => s + Number(i.value), 0)
                                                        .toFixed(2)
                                                }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Registration form -->
                        <form v-else class="flex flex-1 flex-col overflow-hidden" @submit.prevent="submitRetention">
                            <div class="flex-1 space-y-5 overflow-y-auto p-6">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-foreground text-sm font-medium"
                                            >Serie <span class="text-destructive">*</span></label
                                        >
                                        <input
                                            v-model="retentionForm.serie_retention"
                                            type="text"
                                            maxlength="17"
                                            placeholder="001-001-000000001"
                                            class="border-border bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring/30 h-9 rounded-md border px-3 font-mono text-sm focus:ring-2 focus:outline-none"
                                        />
                                        <p v-if="retentionForm.errors.serie_retention" class="text-destructive text-xs">
                                            {{ retentionForm.errors.serie_retention }}
                                        </p>
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="text-foreground text-sm font-medium"
                                            >Fecha <span class="text-destructive">*</span></label
                                        >
                                        <input
                                            v-model="retentionForm.date_retention"
                                            type="date"
                                            min="2015-01-01"
                                            :max="today"
                                            class="border-border bg-background text-foreground focus:ring-ring/30 h-9 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
                                        />
                                        <p v-if="retentionForm.errors.date_retention" class="text-destructive text-xs">
                                            {{ retentionForm.errors.date_retention }}
                                        </p>
                                    </div>
                                    <div class="col-span-2 flex flex-col gap-1.5">
                                        <label class="text-foreground text-sm font-medium"
                                            >Autorización <span class="text-destructive">*</span></label
                                        >
                                        <input
                                            v-model="retentionForm.autorization_retention"
                                            type="text"
                                            maxlength="49"
                                            class="border-border bg-background text-foreground focus:ring-ring/30 h-9 rounded-md border px-3 font-mono text-sm focus:ring-2 focus:outline-none"
                                        />
                                        <p
                                            v-if="retentionForm.errors.autorization_retention"
                                            class="text-destructive text-xs"
                                        >
                                            {{ retentionForm.errors.autorization_retention }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Items -->
                                <div>
                                    <div class="mb-3 flex items-center justify-between">
                                        <p class="text-muted-foreground text-xs font-medium tracking-wider uppercase">
                                            Detalle de retenciones
                                        </p>
                                        <button
                                            type="button"
                                            class="text-primary hover:text-primary/70 text-xs font-medium"
                                            @click="addItem"
                                        >
                                            + Agregar fila
                                        </button>
                                    </div>
                                    <div class="space-y-2">
                                        <div
                                            v-for="(item, idx) in retentionForm.items"
                                            :key="idx"
                                            class="border-border bg-card rounded-lg border p-3"
                                        >
                                            <div class="relative mb-2">
                                                <label class="text-muted-foreground mb-1 block text-xs font-medium"
                                                    >Código / Concepto</label
                                                >
                                                <input
                                                    v-model="itemSearches[idx].query"
                                                    type="text"
                                                    placeholder="Buscar por código o concepto…"
                                                    class="border-border bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring/30 h-9 w-full rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
                                                    @focus="itemSearches[idx].open = true"
                                                    @blur="closeItemSearchDelayed(idx)"
                                                />
                                                <div
                                                    v-if="itemSearches[idx].open && filteredRetentions(idx).length > 0"
                                                    class="border-border bg-popover absolute top-full right-0 left-0 z-10 mt-1 max-h-52 overflow-y-auto rounded-md border shadow-lg"
                                                >
                                                    <button
                                                        v-for="ret in filteredRetentions(idx)"
                                                        :key="ret.id"
                                                        type="button"
                                                        class="hover:bg-accent flex w-full items-center gap-3 px-3 py-2 text-left text-sm transition-colors"
                                                        @mousedown.prevent="selectRetention(idx, ret)"
                                                    >
                                                        <span
                                                            class="text-foreground w-14 shrink-0 font-mono font-medium"
                                                            >{{ ret.code }}</span
                                                        >
                                                        <span class="text-muted-foreground flex-1 truncate text-xs">{{
                                                            ret.description
                                                        }}</span>
                                                        <span
                                                            class="text-foreground shrink-0 font-mono text-xs font-medium"
                                                            >{{ ret.percentage }}%</span
                                                        >
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-3 gap-2">
                                                <div class="flex flex-col gap-1">
                                                    <label class="text-muted-foreground text-xs font-medium"
                                                        >Base</label
                                                    >
                                                    <input
                                                        v-model="item.base"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        class="border-border bg-background text-foreground focus:ring-ring/30 h-8 w-full rounded border px-2 text-right font-mono text-xs focus:ring-1 focus:outline-none"
                                                        @input="recalcValue(idx)"
                                                    />
                                                </div>
                                                <div class="flex flex-col gap-1">
                                                    <label class="text-muted-foreground text-xs font-medium"
                                                        >% Retención</label
                                                    >
                                                    <input
                                                        v-model="item.percentage"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        class="border-border bg-background text-foreground focus:ring-ring/30 h-8 w-full rounded border px-2 text-right font-mono text-xs focus:ring-1 focus:outline-none"
                                                        @input="recalcValue(idx)"
                                                    />
                                                </div>
                                                <div class="flex flex-col gap-1">
                                                    <label class="text-muted-foreground text-xs font-medium"
                                                        >Valor retenido</label
                                                    >
                                                    <input
                                                        :value="Number(item.value).toFixed(2)"
                                                        type="text"
                                                        readonly
                                                        class="border-border bg-muted text-foreground h-8 w-full cursor-default rounded border px-2 text-right font-mono text-xs font-semibold"
                                                    />
                                                </div>
                                            </div>
                                            <div v-if="retentionForm.items.length > 1" class="mt-2 flex justify-end">
                                                <button
                                                    type="button"
                                                    class="text-muted-foreground hover:text-destructive flex items-center gap-1 text-xs transition-colors"
                                                    @click="removeItem(idx)"
                                                >
                                                    <svg
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                        stroke-width="1.5"
                                                        stroke="currentColor"
                                                        class="size-3.5"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            d="M6 18 18 6M6 6l12 12"
                                                        />
                                                    </svg>
                                                    Quitar fila
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-if="retentionForm.errors.items" class="text-destructive mt-1 text-xs">
                                        {{ retentionForm.errors.items }}
                                    </p>
                                </div>
                            </div>
                            <div class="border-border flex items-center justify-end gap-3 border-t px-6 py-4">
                                <Button variant="outline" type="button" @click="closeRetentionPanel">Cancelar</Button>
                                <Button type="submit" :disabled="retentionForm.processing">
                                    {{ retentionForm.processing ? "Guardando…" : "Guardar retención" }}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </TenantLayout>
</template>
