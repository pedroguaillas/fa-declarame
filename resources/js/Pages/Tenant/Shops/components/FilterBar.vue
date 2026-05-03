<script setup lang="ts">
import { reactive, watch } from "vue";

interface Filters {
    search: string;
    period: string;
    state: string;
    retention: string;
}

const props = defineProps<{
    filters: Filters;
    showRetention?: boolean;
}>();

const emit = defineEmits<{
    change: [filters: Filters];
}>();

const local = reactive<Filters>({ ...props.filters });

const hasActiveFilters = () => Object.values(local).some(Boolean);

// Debounce for search field
let searchTimer: ReturnType<typeof setTimeout>;
function onSearchInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => emit("change", { ...local }), 400);
}

// Immediate emit for selects and period
function onFilterChange() {
    emit("change", { ...local });
}

function clearFilters() {
    local.search = "";
    local.period = "";
    local.state = "";
    local.retention = "";
    emit("change", { ...local });
}

// Sync if parent navigates back with different filters
watch(
    () => props.filters,
    (f) => Object.assign(local, f),
);
</script>

<template>
    <div class="border-border bg-card flex flex-wrap items-center gap-3 rounded-lg border px-4 py-3">
        <!-- Search -->
        <div class="relative min-w-48 flex-1">
            <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803a7.5 7.5 0 0 0 10.607 0Z"
                />
            </svg>
            <input
                v-model="local.search"
                type="text"
                placeholder="Proveedor, serie o autorización…"
                class="border-border bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring/30 h-9 w-full rounded-md border py-2 pr-3 pl-9 text-sm focus:ring-2 focus:outline-none"
                @input="onSearchInput"
            />
        </div>

        <!-- Period (month/year) -->
        <input
            v-model="local.period"
            type="month"
            class="border-border bg-background text-foreground focus:ring-ring/30 h-9 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
            @change="onFilterChange"
        />

        <!-- State -->
        <select
            v-model="local.state"
            class="border-border bg-background text-foreground focus:ring-ring/30 h-9 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
            @change="onFilterChange"
        >
            <option value="">Todos los estados</option>
            <option value="PENDIENTE">Pendiente</option>
            <option value="AUTORIZADO">Autorizado</option>
        </select>

        <!-- Retention -->
        <select
            v-if="showRetention"
            v-model="local.retention"
            class="border-border bg-background text-foreground focus:ring-ring/30 h-9 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
            @change="onFilterChange"
        >
            <option value="">Todas las retenciones</option>
            <option value="with">Con retención</option>
            <option value="without">Sin retención</option>
        </select>

        <!-- Clear -->
        <button
            v-if="hasActiveFilters()"
            type="button"
            class="text-muted-foreground hover:text-foreground flex items-center gap-1.5 text-sm transition-colors"
            @click="clearFilters"
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
            Limpiar
        </button>
    </div>
</template>
