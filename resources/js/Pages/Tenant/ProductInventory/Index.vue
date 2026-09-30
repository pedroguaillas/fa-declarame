<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { computed, reactive, ref, watch } from "vue";
import { Download, X } from "lucide-vue-next";

import TenantLayout from "@/layouts/TenantLayout.vue";
import { Button } from "@/components/ui/button";
import HeaderList from "@/components/Shared/HeaderList.vue";
import Pagination from "@/components/Shared/Pagination.vue";
import DataTableDesktop from "@/components/Shared/DataTableDesktop.vue";
import DataTableMobile from "@/components/Shared/DataTableMobile.vue";
import type { Paginator } from "@/types";
import type { ColumnDef } from "@/types/shared";

interface InventoryProduct {
    id: number;
    code: string;
    aux_code: string | null;
    description: string;
    contact_id: number;
    contact_identification: string;
    contact_name: string;
    total_quantity: string;
    total_amount: string;
    min_price: string;
    max_price: string;
    avg_price: string | null;
    last_price: string | null;
    last_purchase_at: string | null;
    purchases_count: number;
}

interface Filters {
    search: string;
    from: string;
    to: string;
    sort: string;
}

const props = defineProps<{
    products: Paginator<InventoryProduct>;
    filters: Filters;
    summary: {
        products_count: number;
        suppliers_count: number;
        total_amount: number;
    };
}>();

// ── Filters ───────────────────────────────────────────
const search = ref(props.filters.search);
const local = reactive<Filters>({ ...props.filters });

let debounceTimer: ReturnType<typeof setTimeout>;

function reload(page?: number) {
    router.get(
        route("tenant.product-inventory.index"),
        {
            search: search.value || undefined,
            from: local.from || undefined,
            to: local.to || undefined,
            sort: local.sort || undefined,
            page,
        },
        { preserveState: true, replace: true, only: ["products", "filters", "summary"] },
    );
}

watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => reload(), 300);
});

const hasActiveFilters = computed(
    () => Boolean(search.value || local.from || local.to || local.sort),
);

function clearFilters() {
    search.value = "";
    local.from = "";
    local.to = "";
    local.sort = "";
    reload();
}

watch(
    () => props.filters,
    (f) => {
        Object.assign(local, f);
        search.value = f.search;
    },
);

// ── Export ────────────────────────────────────────────
function exportExcel() {
    const params = new URLSearchParams();
    if (search.value) params.set("search", search.value);
    if (local.from) params.set("from", local.from);
    if (local.to) params.set("to", local.to);
    if (local.sort) params.set("sort", local.sort);

    const query = params.toString();
    window.location.href = route("tenant.product-inventory.export") + (query ? `?${query}` : "");
}

// ── Formatting ────────────────────────────────────────
function money(value: string | number | null): string {
    return new Intl.NumberFormat("es-EC", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

function quantity(value: string | number | null): string {
    return new Intl.NumberFormat("es-EC", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

function purchaseDate(value: string | null): string {
    if (!value) return "—";
    return new Date(`${value.substring(0, 10)}T00:00:00`).toLocaleDateString("es-EC");
}

// ── Table ─────────────────────────────────────────────
const columns: ColumnDef<InventoryProduct>[] = [
    {
        key: "description",
        label: "Producto",
        labelDescription: (_value, item) =>
            item.aux_code ? `${item.code} · ${item.aux_code}` : item.code,
    },
    {
        key: "contact_name",
        label: "Proveedor",
        labelDescription: (_value, item) => item.contact_identification,
    },
    {
        key: "total_quantity",
        label: "Cantidad",
        align: "right",
        format: (value) => quantity(value),
    },
    {
        key: "last_price",
        label: "Último precio",
        align: "right",
        format: (value) => money(value),
    },
    {
        key: "avg_price",
        label: "Precio prom.",
        align: "right",
        format: (value) => money(value),
    },
    {
        key: "min_price",
        label: "Rango precio",
        align: "right",
        format: (_value, item) => `${money(item.min_price)} – ${money(item.max_price)}`,
    },
    {
        key: "total_amount",
        label: "Total comprado",
        align: "right",
        format: (value) => money(value),
    },
    {
        key: "last_purchase_at",
        label: "Última compra",
        align: "right",
        format: (value) => purchaseDate(value as string | null),
        labelDescription: (_value, item) =>
            `${item.purchases_count} compra${item.purchases_count !== 1 ? "s" : ""}`,
    },
];

const emptyText = computed(() =>
    hasActiveFilters.value
        ? "Ningún producto coincide con los filtros."
        : "No hay productos comprados registrados.",
);

function handlePageChange(page: number) {
    reload(page);
}
</script>

<template>
    <Head title="Inventario de productos" />
    <TenantLayout>
        <div class="flex w-full flex-col gap-4">
            <HeaderList
                v-model="search"
                title="Inventario de productos"
                :description="`${summary.products_count} producto${summary.products_count !== 1 ? 's' : ''} de ${summary.suppliers_count} proveedor${summary.suppliers_count !== 1 ? 'es' : ''} · $${money(summary.total_amount)} comprado`"
                :show-search="true"
                search-placeholder="Buscar por producto, código o proveedor..."
            >
                <template #extra-actions>
                    <Button
                        variant="outline"
                        size="sm"
                        class="cursor-pointer font-bold"
                        @click="exportExcel"
                    >
                        <Download class="size-4" />
                        Exportar
                    </Button>
                </template>
            </HeaderList>

            <!-- Filters -->
            <div class="border-border bg-card flex flex-wrap items-center gap-3 rounded-lg border px-4 py-3">
                <label class="text-muted-foreground flex items-center gap-2 text-sm">
                    Desde
                    <input
                        v-model="local.from"
                        type="date"
                        class="border-border bg-background text-foreground focus:ring-ring/30 h-9 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
                        @change="reload()"
                    />
                </label>

                <label class="text-muted-foreground flex items-center gap-2 text-sm">
                    Hasta
                    <input
                        v-model="local.to"
                        type="date"
                        class="border-border bg-background text-foreground focus:ring-ring/30 h-9 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
                        @change="reload()"
                    />
                </label>

                <select
                    v-model="local.sort"
                    class="border-border bg-background text-foreground focus:ring-ring/30 h-9 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
                    @change="reload()"
                >
                    <option value="">Última compra</option>
                    <option value="amount_desc">Mayor total comprado</option>
                    <option value="quantity_desc">Mayor cantidad</option>
                    <option value="price_desc">Precio más alto</option>
                    <option value="price_asc">Precio más bajo</option>
                    <option value="description_asc">Producto A → Z</option>
                    <option value="contact_asc">Proveedor A → Z</option>
                </select>

                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="text-muted-foreground hover:text-foreground flex items-center gap-1.5 text-sm transition-colors"
                    @click="clearFilters"
                >
                    <X class="size-4" />
                    Limpiar
                </button>
            </div>

            <div class="border-border bg-card flex-1 overflow-hidden rounded-lg border">
                <!-- Desktop -->
                <div class="hidden h-full md:block">
                    <DataTableDesktop
                        :columns="columns"
                        :items="products.data"
                        :empty-text="emptyText"
                    />
                </div>

                <!-- Mobile -->
                <div class="h-full md:hidden">
                    <DataTableMobile
                        :columns="columns"
                        :items="products.data"
                        :empty-text="emptyText"
                    />
                </div>
            </div>

            <Pagination :paginator="products" @change-page="handlePageChange" />
        </div>
    </TenantLayout>
</template>
