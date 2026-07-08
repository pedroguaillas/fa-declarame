<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";

const today = new Date();
const currentMonth = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}`;

interface Filters {
    search: string;
    period: string;
    retention: string;
    voucher_type: string;
    sort: string;
}

const props = defineProps<{
    filters: Filters;
    showRetention?: boolean;
    semiannual?: boolean;
}>();

const emit = defineEmits<{
    change: [filters: Filters];
}>();

const local = reactive<Filters>({ ...props.filters });

// Semester period (YYYY-S1 / YYYY-S2)
const years = computed(() => Array.from({ length: 10 }, (_, i) => today.getFullYear() - i));

function parseSemesterPeriod(period: string): { year: number; semester: number } {
    const match = /^(\d{4})-S([12])$/.exec(period);
    if (match) return { year: Number(match[1]), semester: Number(match[2]) };
    return { year: today.getFullYear(), semester: today.getMonth() < 6 ? 1 : 2 };
}

const initial = parseSemesterPeriod(props.filters.period);
const semesterYear = ref(initial.year);
const semesterValue = ref(initial.semester);

function onSemesterChange() {
    local.period = `${semesterYear.value}-S${semesterValue.value}`;
    emit("change", { ...local });
}

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
    local.retention = "";
    local.voucher_type = "";
    local.sort = "";
    emit("change", { ...local });
}

// Sync if parent navigates back with different filters
watch(
    () => props.filters,
    (f) => {
        Object.assign(local, f);
        if (props.semiannual) {
            const parsed = parseSemesterPeriod(f.period);
            semesterYear.value = parsed.year;
            semesterValue.value = parsed.semester;
        }
    },
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

        <!-- Period (semester) -->
        <template v-if="semiannual">
            <select
                v-model.number="semesterYear"
                class="border-border bg-background text-foreground focus:ring-ring/30 h-9 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
                @change="onSemesterChange"
            >
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
            <select
                v-model.number="semesterValue"
                class="border-border bg-background text-foreground focus:ring-ring/30 h-9 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
                @change="onSemesterChange"
            >
                <option :value="1">Semestre 1 (Ene–Jun)</option>
                <option :value="2">Semestre 2 (Jul–Dic)</option>
            </select>
        </template>

        <!-- Period (month/year) -->
        <input
            v-else
            v-model="local.period"
            type="month"
            min="2015-01"
            :max="currentMonth"
            class="border-border bg-background text-foreground focus:ring-ring/30 h-9 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
            @change="onFilterChange"
        />

        <!-- Voucher type -->
        <select
            v-model="local.voucher_type"
            class="border-border bg-background text-foreground focus:ring-ring/30 h-9 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
            @change="onFilterChange"
        >
            <option value="">Todos los tipos</option>
            <option value="01">Factura</option>
            <option value="02">Nota de Venta</option>
            <option value="03">Liq. Compra</option>
            <option value="04">Nota de Crédito</option>
            <option value="05">Nota de Débito</option>
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

        <!-- Sort -->
        <select
            v-model="local.sort"
            class="border-border bg-background text-foreground focus:ring-ring/30 h-9 rounded-md border px-3 text-sm focus:ring-2 focus:outline-none"
            @change="onFilterChange"
        >
            <option value="">Fecha reciente</option>
            <option value="emision_asc">Fecha antigua</option>
            <option value="contact_asc">Proveedor A → Z</option>
            <option value="contact_desc">Proveedor Z → A</option>
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
