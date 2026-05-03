<script setup lang="ts">
import { ref } from "vue";
import { useForm } from "@inertiajs/vue3";

import { Button } from "@/components/ui/button";
import ShopInvoicePanel from "./ShopInvoicePanel.vue";
import SlideOver from "./SlideOver.vue";

import type { Account, Shop, ShopItem } from "@/types/tenant";

// ─── State ───────────────────────────────────────────────────────────────────

const panelOpen = ref(false);
const panelEditing = ref(false);
const selectedShop = ref<Shop | null>(null);
const shopItems = ref<ShopItem[]>([]);
const shopItemsLoading = ref(false);

const accountQuery = ref("");
const accountDropdownOpen = ref(false);
const accountForm = useForm<{ acount_id: number | null }>({ acount_id: null });

const searchResults = ref<Account[]>([]);
let searchDebounce: ReturnType<typeof setTimeout> | null = null;

// ─── API search ──────────────────────────────────────────────────────────────

async function fetchAccounts(query: string) {
    const res = await fetch(route("tenant.accounts.search") + `?q=${encodeURIComponent(query)}`, {
        headers: { Accept: "application/json" },
    });
    if (res.ok) {
        searchResults.value = await res.json();
    }
}

function onAccountInput() {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => fetchAccounts(accountQuery.value), 300);
}

// ─── Exposed API ─────────────────────────────────────────────────────────────

async function open(shop: Shop) {
    selectedShop.value = shop;
    accountForm.acount_id = shop.acount_id;
    panelEditing.value = !shop.acount_id;
    accountQuery.value = "";
    panelOpen.value = true;

    fetchAccounts("");

    shopItems.value = [];
    shopItemsLoading.value = true;
    try {
        const res = await fetch(route("tenant.shops.show", { shop: shop.id }), {
            headers: { Accept: "application/json" },
        });
        if (res.ok) {
            const data: Shop = await res.json();
            selectedShop.value = data;
            shopItems.value = data.items ?? [];
            accountQuery.value = data.account ? `${data.account.code} – ${data.account.name}` : "";
        }
    } finally {
        shopItemsLoading.value = false;
    }
}

function close() {
    panelOpen.value = false;
    panelEditing.value = false;
    selectedShop.value = null;
    accountQuery.value = "";
    shopItems.value = [];
}

defineExpose({ open, close });

// ─── Helpers ─────────────────────────────────────────────────────────────────

function selectAccount(account: Account) {
    accountForm.acount_id = account.id;
    accountQuery.value = `${account.code} – ${account.name}`;
    accountDropdownOpen.value = false;
}

function submitAccount() {
    if (!selectedShop.value) return;
    accountForm.patch(
        route("tenant.shops.account.update", { shop: selectedShop.value.id }),
        { onSuccess: () => close() },
    );
}

function closeDropdownDelayed(closeFn: () => void) {
    setTimeout(() => closeFn(), 150);
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

                <!-- Right: account form -->
                <div class="flex flex-1 flex-col">
                    <div class="border-border flex items-start justify-between border-b px-6 py-4">
                        <div>
                            <h2 class="text-foreground text-base font-semibold">Cuenta contable</h2>
                            <p class="text-muted-foreground mt-0.5 font-mono text-sm">
                                {{ selectedShop.serie }}
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

                    <!-- View mode -->
                    <div
                        v-if="selectedShop.account && accountForm.acount_id === selectedShop.acount_id && !panelEditing"
                        class="flex flex-1 flex-col p-6"
                    >
                        <p class="text-muted-foreground mb-1 text-xs font-medium tracking-wider uppercase">
                            Cuenta asignada
                        </p>
                        <div class="border-border bg-muted flex items-start gap-3 rounded-lg border px-4 py-3">
                            <span class="text-foreground font-mono text-sm font-semibold">{{
                                selectedShop.account.code
                            }}</span>
                            <span class="text-foreground text-sm">{{ selectedShop.account.name }}</span>
                        </div>
                        <button
                            type="button"
                            class="text-primary hover:text-primary/70 mt-4 self-start text-sm font-medium"
                            @click="panelEditing = true"
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
                                    @input="onAccountInput"
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
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M6 18 18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>
                                <div
                                    v-if="accountDropdownOpen && searchResults.length > 0"
                                    class="border-border bg-popover absolute top-full right-0 left-0 z-10 mt-1 max-h-60 overflow-y-auto rounded-md border shadow-lg"
                                >
                                    <button
                                        v-for="account in searchResults"
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
                            <Button variant="outline" type="button" @click="close">Cancelar</Button>
                            <Button type="submit" :disabled="accountForm.processing || !accountForm.acount_id">
                                {{ accountForm.processing ? "Guardando…" : "Guardar cuenta" }}
                            </Button>
                        </div>
                    </form>
                </div>
        </template>
    </SlideOver>
</template>
