<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import TenantLayout from "@/layouts/TenantLayout.vue";
import type { Account, ShopItem } from "@/types/tenant";

interface ContactWithCount {
    id: number;
    identification: string;
    name: string;
    total_count: number;
    unlinked_count: number;
}

const props = defineProps<{
    contacts: ContactWithCount[];
}>();

// ─── Contact list ─────────────────────────────────────────────────────────────

const contactSearch = ref("");
const selectedContact = ref<ContactWithCount | null>(null);
const localContacts = ref<ContactWithCount[]>([...props.contacts]);

watch(
    () => props.contacts,
    (newContacts) => {
        localContacts.value = [...newContacts];
        selectedContact.value = null;
        items.value = [];
        selectedIds.value = new Set();
        currentPage.value = 1;
        lastPage.value = 1;
        totalItems.value = 0;
        clearBulkAccount();
    },
);

const filteredContacts = computed(() => {
    const q = contactSearch.value.toLowerCase();
    if (!q) return localContacts.value;
    return localContacts.value.filter(
        (c) => c.name.toLowerCase().includes(q) || c.identification.includes(q),
    );
});

// ─── Items ────────────────────────────────────────────────────────────────────

const items = ref<ShopItem[]>([]);
const itemsLoading = ref(false);
const currentPage = ref(1);
const lastPage = ref(1);
const totalItems = ref(0);

async function selectContact(contact: ContactWithCount) {
    selectedContact.value = contact;
    selectedIds.value = new Set();
    clearBulkAccount();
    currentPage.value = 1;
    await loadItems(contact.id, 1);
}

async function loadItems(contactId: number, page = 1) {
    itemsLoading.value = true;
    items.value = [];
    try {
        const url = new URL(route("tenant.shop-items.by-contact", { contact: contactId }), window.location.origin);
        url.searchParams.set("page", String(page));
        const res = await fetch(url.toString(), { headers: { Accept: "application/json" } });
        if (res.ok) {
            const json = await res.json();
            items.value = json.data;
            currentPage.value = json.current_page;
            lastPage.value = json.last_page;
            totalItems.value = json.total;
        }
    } finally {
        itemsLoading.value = false;
    }
}

async function goToPage(page: number) {
    if (!selectedContact.value || page < 1 || page > lastPage.value) return;
    selectedIds.value = new Set();
    await loadItems(selectedContact.value.id, page);
}

const unlinkedItems = computed(() => items.value.filter((i) => !i.account));

// ─── Selection ────────────────────────────────────────────────────────────────

const selectedIds = ref<Set<number>>(new Set());

const selectedItems = computed(() => items.value.filter((i) => selectedIds.value.has(i.id)));

function toggleItem(id: number) {
    const next = new Set(selectedIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    selectedIds.value = next;
}

function toggleAll() {
    if (selectedIds.value.size === items.value.length) {
        selectedIds.value = new Set();
    } else {
        selectedIds.value = new Set(items.value.map((i) => i.id));
    }
}

function selectUnlinked() {
    selectedIds.value = new Set(unlinkedItems.value.map((i) => i.id));
}


function clearSelection() {
    selectedIds.value = new Set();
}

// ─── Account search (bulk) ────────────────────────────────────────────────────

const accountQuery = ref("");
const selectedAccount = ref<Account | null>(null);
const searchResults = ref<Account[]>([]);
const accountDropdownOpen = ref(false);
const accountDropdownRect = ref<{ top: number; left: number; width: number } | null>(null);
let searchDebounce: ReturnType<typeof setTimeout> | null = null;

async function fetchAccounts(q: string) {
    const url = new URL(route("tenant.accounts.search"), window.location.origin);
    if (q) url.searchParams.set("q", q);
    const res = await fetch(url.toString(), { headers: { Accept: "application/json" } });
    if (res.ok) {
        searchResults.value = await res.json();
    }
}

function onAccountInput() {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => fetchAccounts(accountQuery.value), 300);
}

function selectAccount(account: Account) {
    selectedAccount.value = account;
    accountQuery.value = `${account.code} – ${account.name}`;
    accountDropdownOpen.value = false;
}

function openAccountDropdown(e: FocusEvent) {
    const rect = (e.target as HTMLInputElement).getBoundingClientRect();
    accountDropdownRect.value = { top: rect.bottom, left: rect.left, width: rect.width };
    accountDropdownOpen.value = true;
    fetchAccounts(accountQuery.value);
}

function closeDropdownDelayed() {
    setTimeout(() => (accountDropdownOpen.value = false), 150);
}

function getDropdownStyle() {
    const r = accountDropdownRect.value;
    if (!r) return {};
    return { top: `${r.top + 4}px`, left: `${r.left}px`, width: `${r.width}px` };
}

function clearBulkAccount() {
    selectedAccount.value = null;
    accountQuery.value = "";
}

// ─── Assign ───────────────────────────────────────────────────────────────────

const isAssigning = ref(false);

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "";
}

async function patchAssign(itemIds: number[], accountId: number): Promise<boolean> {
    const res = await fetch(route("tenant.shop-items.assign-account"), {
        method: "PATCH",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": csrfToken(),
        },
        body: JSON.stringify({ item_ids: itemIds, account_id: accountId }),
    });
    return res.ok;
}

async function assignToSelected() {
    if (!selectedAccount.value || selectedIds.value.size === 0 || isAssigning.value) return;
    isAssigning.value = true;
    const account = { ...selectedAccount.value };
    const assignedIds = new Set(selectedIds.value);
    try {
        const ok = await patchAssign(Array.from(assignedIds), account.id);
        if (ok) {
            // Count how many items on this page were newly linked
            const newlyLinked = items.value.filter((i) => assignedIds.has(i.id) && !i.account).length;

            // Update rows in-place — no reload, no flash
            items.value = items.value.map((item) =>
                assignedIds.has(item.id) ? { ...item, account } : item,
            );

            // Update contact badge locally
            const idx = localContacts.value.findIndex((c) => c.id === selectedContact.value?.id);
            if (idx !== -1) {
                localContacts.value[idx] = {
                    ...localContacts.value[idx],
                    unlinked_count: Math.max(0, localContacts.value[idx].unlinked_count - newlyLinked),
                };
            }

            selectedIds.value = new Set();
            clearBulkAccount();
        }
    } finally {
        isAssigning.value = false;
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatAmount(val: string) {
    return `$${Number(val).toFixed(2)}`;
}

const allSelected = computed(
    () => items.value.length > 0 && selectedIds.value.size === items.value.length,
);

const paginationPages = computed<(number | "…")[]>(() => {
    if (lastPage.value <= 7) return Array.from({ length: lastPage.value }, (_, i) => i + 1);
    const cur = currentPage.value;
    const last = lastPage.value;
    const pages: (number | "…")[] = [1];
    if (cur > 3) pages.push("…");
    for (let p = Math.max(2, cur - 1); p <= Math.min(last - 1, cur + 1); p++) pages.push(p);
    if (cur < last - 2) pages.push("…");
    pages.push(last);
    return pages;
});
</script>

<template>
    <Head title="Vinculación de cuentas" />
    <TenantLayout>
        <div class="flex h-full min-h-0 flex-1 overflow-hidden">
            <!-- Left panel: contacts -->
            <div class="border-border flex w-72 shrink-0 flex-col border-r">
                <div class="border-border border-b px-4 py-3">
                    <p class="text-foreground text-sm font-semibold">Proveedores</p>
                    <input
                        v-model="contactSearch"
                        type="text"
                        placeholder="Buscar proveedor…"
                        class="border-border bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring/30 mt-2 h-8 w-full rounded-md border px-3 text-xs focus:ring-2 focus:outline-none"
                    />
                </div>
                <div class="flex-1 overflow-y-auto">
                    <button
                        v-for="contact in filteredContacts"
                        :key="contact.id"
                        type="button"
                        class="hover:bg-accent w-full border-b px-4 py-3 text-left transition-colors"
                        :class="selectedContact?.id === contact.id ? 'bg-accent' : 'border-border'"
                        @click="selectContact(contact)"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-foreground truncate text-xs font-medium">
                                {{ contact.name }}
                            </span>
                            <span
                                v-if="contact.unlinked_count > 0"
                                class="shrink-0 rounded-full bg-orange-100 px-1.5 py-0.5 text-xs font-semibold text-orange-700 dark:bg-orange-900/40 dark:text-orange-400"
                            >
                                {{ contact.unlinked_count }}
                            </span>
                        </div>
                        <p class="text-muted-foreground mt-0.5 font-mono text-xs">
                            {{ contact.identification }}
                        </p>
                    </button>
                    <p v-if="filteredContacts.length === 0" class="text-muted-foreground px-4 py-6 text-center text-xs">
                        Sin resultados
                    </p>
                </div>
            </div>

            <!-- Right panel: items -->
            <div class="flex min-w-0 flex-1 flex-col">
                <!-- Empty state -->
                <div v-if="!selectedContact" class="flex flex-1 items-center justify-center">
                    <p class="text-muted-foreground text-sm">Selecciona un proveedor para ver sus ítems</p>
                </div>

                <template v-else>
                    <!-- Header -->
                    <div class="border-border flex items-center justify-between border-b px-5 py-3">
                        <div>
                            <p class="text-foreground text-sm font-semibold">{{ selectedContact.name }}</p>
                            <p class="text-muted-foreground font-mono text-xs">{{ selectedContact.identification }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground text-xs">
                                {{ selectedContact.unlinked_count }} sin vincular de {{ selectedContact.total_count }}
                            </span>
                        </div>
                    </div>

                    <!-- Toolbar -->
                    <div class="border-border flex flex-wrap items-center gap-2 border-b px-5 py-2">
                        <!-- Selection actions -->
                        <button
                            type="button"
                            class="text-primary hover:text-primary/70 text-xs font-medium transition-colors"
                            @click="selectUnlinked"
                        >
                            Seleccionar sin vincular
                        </button>
                        <span class="text-border text-xs">·</span>
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-foreground text-xs transition-colors"
                            @click="clearSelection"
                        >
                            Limpiar selección
                        </button>

                        <template v-if="selectedIds.size > 0">
                            <span class="text-border text-xs">·</span>
                            <span class="text-muted-foreground text-xs font-medium">
                                {{ selectedIds.size }} seleccionados
                            </span>

                            <!-- Bulk account assign -->
                            <div class="ml-auto flex items-center gap-2">
                                <div class="relative">
                                    <input
                                        v-model="accountQuery"
                                        type="text"
                                        placeholder="Buscar cuenta…"
                                        class="border-border bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring/30 h-8 w-56 rounded-md border px-3 pr-7 text-xs focus:ring-2 focus:outline-none"
                                        @focus="openAccountDropdown"
                                        @blur="closeDropdownDelayed"
                                        @input="onAccountInput"
                                    />
                                    <button
                                        v-if="selectedAccount"
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground absolute top-1/2 right-2 -translate-y-1/2"
                                        @mousedown.prevent="clearBulkAccount"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                    <Teleport to="body">
                                        <div
                                            v-if="accountDropdownOpen && searchResults.length > 0"
                                            :style="getDropdownStyle()"
                                            class="border-border bg-popover fixed z-[200] max-h-60 overflow-y-auto rounded-md border shadow-lg"
                                        >
                                            <button
                                                v-for="acc in searchResults"
                                                :key="acc.id"
                                                type="button"
                                                class="hover:bg-accent flex w-full items-center gap-3 px-3 py-2 text-left text-sm transition-colors"
                                                @mousedown.prevent
                                                @click="selectAccount(acc)"
                                            >
                                                <span class="text-foreground w-24 shrink-0 font-mono text-xs font-semibold">{{ acc.code }}</span>
                                                <span class="text-muted-foreground flex-1 truncate text-xs">{{ acc.name }}</span>
                                            </button>
                                        </div>
                                    </Teleport>
                                </div>
                                <button
                                    type="button"
                                    :disabled="!selectedAccount || isAssigning"
                                    class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-40"
                                    @click="assignToSelected"
                                >
                                    {{ isAssigning ? "Asignando…" : `Asignar a ${selectedIds.size}` }}
                                </button>
                            </div>
                        </template>

                    </div>

                    <!-- Loading skeleton -->
                    <div v-if="itemsLoading" class="flex-1 space-y-2 p-5">
                        <div v-for="n in 5" :key="n" class="bg-muted h-10 animate-pulse rounded-md" />
                    </div>

                    <!-- Items table -->
                    <div v-else-if="items.length > 0" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                        <div class="flex-1 overflow-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-muted border-border sticky top-0 border-b">
                                <tr>
                                    <th class="px-3 py-2 text-left">
                                        <input
                                            type="checkbox"
                                            class="rounded"
                                            :checked="allSelected"
                                            :indeterminate="selectedIds.size > 0 && !allSelected"
                                            @change="toggleAll"
                                        />
                                    </th>
                                    <th class="text-muted-foreground px-3 py-2 text-left font-medium">Factura</th>
                                    <th class="text-muted-foreground px-3 py-2 text-left font-medium">Código</th>
                                    <th class="text-muted-foreground px-3 py-2 text-left font-medium">Descripción</th>
                                    <th class="text-muted-foreground px-3 py-2 text-right font-medium">Total</th>
                                    <th class="text-muted-foreground px-3 py-2 text-left font-medium">Cuenta contable</th>
                                </tr>
                            </thead>
                            <tbody class="divide-border divide-y">
                                <tr
                                    v-for="item in items"
                                    :key="item.id"
                                    class="hover:bg-accent/40 cursor-pointer transition-colors"
                                    :class="selectedIds.has(item.id) ? 'bg-accent/60' : ''"
                                    @click="toggleItem(item.id)"
                                >
                                    <td class="px-3 py-2.5" @click.stop>
                                        <input
                                            type="checkbox"
                                            class="rounded"
                                            :checked="selectedIds.has(item.id)"
                                            @change="toggleItem(item.id)"
                                        />
                                    </td>
                                    <td class="text-muted-foreground px-3 py-2.5 font-mono">{{ item.serie }}</td>
                                    <td class="text-muted-foreground px-3 py-2.5 font-mono">{{ item.product.code }}</td>
                                    <td class="text-foreground min-w-[14rem] px-3 py-2.5">{{ item.product.description }}</td>
                                    <td class="text-foreground px-3 py-2.5 text-right font-mono tabular-nums">
                                        {{ formatAmount(item.total) }}
                                    </td>
                                    <td class="px-3 py-2.5" @click.stop>
                                        <!-- Linked -->
                                        <span
                                            v-if="item.account"
                                            class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-400"
                                        >
                                            <svg class="size-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414 0L8 12.586 4.707 9.293a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8a1 1 0 000-1.414z" clip-rule="evenodd" />
                                            </svg>
                                            {{ item.account.code }} – {{ item.account.name }}
                                        </span>

                                        <!-- Unlinked -->
                                        <span v-else class="text-muted-foreground text-xs">Sin cuenta</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="lastPage > 1" class="border-border bg-card flex items-center justify-between border-t px-5 py-2">
                            <span class="text-muted-foreground text-xs">
                                Página {{ currentPage }} de {{ lastPage }} · {{ totalItems }} ítems
                            </span>
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    :disabled="currentPage <= 1"
                                    class="border-border rounded border px-2 py-1 text-xs disabled:opacity-40"
                                    @click="goToPage(currentPage - 1)"
                                >
                                    ‹
                                </button>
                                <template v-for="(p, i) in paginationPages" :key="i">
                                    <span v-if="p === '…'" class="text-muted-foreground px-1 text-xs">…</span>
                                    <button
                                        v-else
                                        type="button"
                                        :class="[
                                            'rounded border px-2 py-1 text-xs',
                                            p === currentPage
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-border hover:bg-accent',
                                        ]"
                                        @click="goToPage(p)"
                                    >
                                        {{ p }}
                                    </button>
                                </template>
                                <button
                                    type="button"
                                    :disabled="currentPage >= lastPage"
                                    class="border-border rounded border px-2 py-1 text-xs disabled:opacity-40"
                                    @click="goToPage(currentPage + 1)"
                                >
                                    ›
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty items -->
                    <div v-else class="flex flex-1 items-center justify-center">
                        <p class="text-muted-foreground text-sm">Este proveedor no tiene ítems registrados</p>
                    </div>
                </template>
            </div>
        </div>
    </TenantLayout>
</template>
