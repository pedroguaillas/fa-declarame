<script setup lang="ts">
import { ref } from "vue";
import { useForm } from "@inertiajs/vue3";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import ShopInvoicePanel from "./ShopInvoicePanel.vue";
import SlideOver from "./SlideOver.vue";

import type { RetentionItem, RetentionOption, Shop, ShopItem } from "@/types/tenant";

// ─── State ───────────────────────────────────────────────────────────────────

const today = new Date().toISOString().slice(0, 10);
const panelOpen = ref(false);
const selectedShop = ref<Shop | null>(null);
const shopItems = ref<ShopItem[]>([]);
const shopItemsLoading = ref(false);

interface ItemSearch {
    query: string;
    open: boolean;
    rect: { top: number; left: number; width: number } | null;
}
const itemSearches = ref<ItemSearch[]>([]);
const searchResults = ref<RetentionOption[]>([]);
let searchDebounce: ReturnType<typeof setTimeout> | null = null;

async function fetchRetentions(query: string) {
    const url = new URL(route("tenant.retentions.search"), window.location.origin);
    if (query) url.searchParams.set("q", query);
    const res = await fetch(url.toString(), { headers: { Accept: "application/json" } });
    if (res.ok) searchResults.value = await res.json();
}

function onRetentionInput(idx: number) {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => fetchRetentions(itemSearches.value[idx]?.query ?? ""), 300);
}

function emptyItem(subTotal: string | number = 0): RetentionItem {
    return { retention_id: null, base: subTotal, percentage: "", value: "" };
}

function emptySearch(): ItemSearch {
    return { query: "", open: false, rect: null };
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

const typeLabel: Record<string, string> = { iva: "IVA", renta: "Renta" };

// ─── Exposed API ─────────────────────────────────────────────────────────────

async function open(shop: Shop) {
    selectedShop.value = shop;
    shopItems.value = [];
    shopItemsLoading.value = true;
    panelOpen.value = true;

    // Inicializar sincrónicamente para que el template no encuentre itemSearches[idx] undefined
    if (!shop.serie_retention) {
        retentionForm.reset();
        retentionForm.items = [emptyItem()];
        itemSearches.value = [emptySearch()];
        fetchRetentions("");
    }

    try {
        const res = await fetch(route("tenant.shops.show", { shop: shop.id }), {
            headers: { Accept: "application/json" },
        });
        if (res.ok) {
            const data: Shop = await res.json();
            selectedShop.value = data;
            shopItems.value = data.items ?? [];

            // Actualizar base con el sub_total real una vez que llega del servidor
            if (!data.serie_retention && retentionForm.items.length > 0) {
                retentionForm.items[0].base = Number(data.sub_total) || 0;
                recalcValue(0);
            }
        }
    } finally {
        shopItemsLoading.value = false;
    }
}

function close() {
    panelOpen.value = false;
    selectedShop.value = null;
    shopItems.value = [];
}

defineExpose({ open, close });

// ─── Helpers ─────────────────────────────────────────────────────────────────

function addItem() {
    retentionForm.items.push(emptyItem(selectedShop.value?.sub_total ?? 0));
    itemSearches.value.push(emptySearch());
}

function removeItem(index: number) {
    retentionForm.items.splice(index, 1);
    itemSearches.value.splice(index, 1);
}

function filteredRetentions(_idx: number): RetentionOption[] {
    return searchResults.value;
}

function baseForRetention(type: string): number {
    const shop = selectedShop.value;
    if (!shop) return 0;
    if (type === "iva") {
        return (
            Number(shop.iva5 ?? 0) +
            Number(shop.iva8 ?? 0) +
            Number(shop.iva12 ?? 0) +
            Number(shop.iva15 ?? 0)
        );
    }
    return Number(shop.sub_total ?? 0);
}

function selectRetention(idx: number, retention: RetentionOption) {
    const base = baseForRetention(retention.type);
    const value = parseFloat(((base * retention.percentage) / 100).toFixed(2));

    retentionForm.items.splice(idx, 1, {
        ...retentionForm.items[idx],
        retention_id: retention.id,
        base,
        percentage: retention.percentage,
        value,
    });

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
        route("tenant.shops.retention.store", { shop: selectedShop.value.id }),
        { onSuccess: () => close() },
    );
}

function openSearch(idx: number, event: FocusEvent) {
    const input = event.target as HTMLInputElement;
    const rect = input.getBoundingClientRect();
    itemSearches.value[idx].rect = { top: rect.bottom, left: rect.left, width: rect.width };
    itemSearches.value[idx].open = true;
}

function getDropdownStyle(idx: number): Record<string, string> {
    const rect = itemSearches.value[idx]?.rect;
    if (!rect) return {};
    return {
        top: `${rect.top + 4}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
    };
}

function closeItemSearchDelayed(idx: number) {
    setTimeout(() => {
        if (itemSearches.value[idx]) {
            itemSearches.value[idx].open = false;
        }
    }, 150);
}
</script>

<template>
    <SlideOver :open="panelOpen" @close="close">
        <template v-if="selectedShop">
                <!-- Left: invoice info -->
                <ShopInvoicePanel
                    :shop="selectedShop"
                    :shop-items="shopItems"
                    :shop-items-loading="shopItemsLoading"
                />

                <!-- Right: retention panel -->
                <div class="flex min-w-0 flex-1 flex-col">
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
                            @click="close"
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
                    <div v-if="selectedShop.serie_retention" class="min-w-0 flex-1 overflow-y-auto p-6">
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
                                <p class="text-foreground text-sm">{{ selectedShop.date_retention }}</p>
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

                        <p class="text-muted-foreground mb-3 text-xs font-medium tracking-wider uppercase">Detalle</p>
                        <div class="border-border overflow-auto rounded-lg border">
                            <table class="divide-border min-w-full divide-y text-sm">
                                <thead class="bg-muted">
                                    <tr>
                                        <th
                                            class="text-muted-foreground whitespace-nowrap px-4 py-2.5 text-left text-xs font-medium uppercase"
                                        >
                                            Tipo
                                        </th>
                                        <th
                                            class="text-muted-foreground whitespace-nowrap px-4 py-2.5 text-left text-xs font-medium uppercase"
                                        >
                                            Código
                                        </th>
                                        <th
                                            class="text-muted-foreground whitespace-nowrap px-4 py-2.5 text-left text-xs font-medium uppercase"
                                        >
                                            Descripción
                                        </th>
                                        <th
                                            class="text-muted-foreground whitespace-nowrap px-4 py-2.5 text-right text-xs font-medium uppercase"
                                        >
                                            Base
                                        </th>
                                        <th
                                            class="text-muted-foreground whitespace-nowrap px-4 py-2.5 text-right text-xs font-medium uppercase"
                                        >
                                            %
                                        </th>
                                        <th
                                            class="text-muted-foreground whitespace-nowrap px-4 py-2.5 text-right text-xs font-medium uppercase"
                                        >
                                            Valor
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-border divide-y">
                                    <tr v-for="item in selectedShop.retention_items" :key="item.id">
                                        <td class="text-foreground whitespace-nowrap px-4 py-2.5">
                                            {{ typeLabel[item.retention?.type ?? ""] ?? item.retention?.type }}
                                        </td>
                                        <td class="text-foreground whitespace-nowrap px-4 py-2.5 font-mono">
                                            {{ item.retention?.code }}
                                        </td>
                                        <td class="text-muted-foreground px-4 py-2.5 text-sm">
                                            {{ item.retention?.description }}
                                        </td>
                                        <td class="text-foreground whitespace-nowrap px-4 py-2.5 text-right font-mono">
                                            ${{ Number(item.base).toFixed(2) }}
                                        </td>
                                        <td class="text-foreground whitespace-nowrap px-4 py-2.5 text-right font-mono">
                                            {{ item.percentage }}%
                                        </td>
                                        <td
                                            class="text-foreground whitespace-nowrap px-4 py-2.5 text-right font-mono font-medium"
                                        >
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
                                                @focus="openSearch(idx, $event)"
                                                @input="onRetentionInput(idx)"
                                                @blur="closeItemSearchDelayed(idx)"
                                            />
                                            <Teleport to="body">
                                                <div
                                                    v-if="itemSearches[idx].open && filteredRetentions(idx).length > 0"
                                                    :style="getDropdownStyle(idx)"
                                                    class="border-border bg-popover fixed z-[200] max-h-52 overflow-y-auto rounded-md border shadow-lg"
                                                >
                                                    <button
                                                        v-for="ret in filteredRetentions(idx)"
                                                        :key="ret.id"
                                                        type="button"
                                                        class="hover:bg-accent flex w-full items-center gap-3 px-3 py-2 text-left text-sm transition-colors"
                                                        @mousedown.prevent
                                                        @click="selectRetention(idx, ret)"
                                                    >
                                                        <span class="text-foreground w-14 shrink-0 font-mono font-medium">{{
                                                            ret.code
                                                        }}</span>
                                                        <span class="text-muted-foreground flex-1 truncate text-xs">{{
                                                            ret.description
                                                        }}</span>
                                                        <span class="text-foreground shrink-0 font-mono text-xs font-medium"
                                                            >{{ ret.percentage }}%</span
                                                        >
                                                    </button>
                                                </div>
                                            </Teleport>
                                        </div>
                                        <div class="grid grid-cols-3 gap-2">
                                            <div class="flex flex-col gap-1">
                                                <label class="text-muted-foreground text-xs font-medium">Base</label>
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
                            <Button variant="outline" type="button" @click="close">Cancelar</Button>
                            <Button type="submit" :disabled="retentionForm.processing">
                                {{ retentionForm.processing ? "Guardando…" : "Guardar retención" }}
                            </Button>
                        </div>
                    </form>
                </div>
        </template>
    </SlideOver>
</template>
