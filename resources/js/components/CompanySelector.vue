<script setup lang="ts">
import { ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { Building2, ChevronsUpDown, Check } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import type { CompanyScope } from "@/types/tenant";

const props = defineProps<{
    currentCompany?: CompanyScope | null;
}>();

const open = ref(false);
const search = ref("");
const companies = ref<CompanyScope[]>([]);
const loading = ref(false);
let debounceTimer: ReturnType<typeof setTimeout>;

async function loadCompanies(query: string) {
    loading.value = true;
    try {
        const url = new URL(route("tenant.company-scope.search"), window.location.origin);
        url.searchParams.set("search", query);
        const res = await fetch(url.toString(), {
            headers: { Accept: "application/json" },
        });
        companies.value = await res.json();
    } finally {
        loading.value = false;
    }
}

watch(open, (val) => {
    if (val) {
        search.value = "";
        loadCompanies("");
    }
});

watch(search, (val) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => loadCompanies(val), 300);
});

function selectCompany(company: CompanyScope) {
    open.value = false;
    router.post(
        route("tenant.company-scope.store"),
        { company_id: company.id },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <!-- Contribuyente seleccionado: chip outline con nombre + RUC -->
            <Button
                v-if="currentCompany"
                variant="outline"
                size="sm"
                class="hidden h-9 max-w-72 gap-2 px-3 sm:flex"
            >
                <Building2 class="size-4 shrink-0 text-primary" />
                <div class="flex min-w-0 flex-1 flex-col items-start leading-tight">
                    <span class="max-w-44 truncate text-sm font-semibold leading-none">
                        {{ currentCompany.name }}
                    </span>
                    <span class="font-mono text-[10px] leading-none text-muted-foreground">
                        {{ currentCompany.ruc }}
                    </span>
                </div>
                <ChevronsUpDown class="size-3.5 shrink-0 opacity-50" />
            </Button>

            <!-- Móvil: solo ícono cuando hay selección -->
            <Button
                v-if="currentCompany"
                variant="outline"
                size="icon"
                class="size-9 sm:hidden"
                :title="currentCompany.name"
            >
                <Building2 class="size-4 text-primary" />
            </Button>

            <!-- Sin contribuyente: botón amber pulsante -->
            <Button
                v-if="!currentCompany"
                variant="outline"
                size="sm"
                class="h-9 animate-pulse gap-2 border-amber-400 bg-amber-50 px-3 text-amber-700 hover:bg-amber-100 hover:text-amber-800 dark:border-amber-600 dark:bg-amber-950/40 dark:text-amber-400 dark:hover:bg-amber-950/60"
            >
                <Building2 class="size-4 shrink-0" />
                <span class="hidden sm:inline">Seleccionar contribuyente</span>
                <ChevronsUpDown class="size-3.5 shrink-0 opacity-70" />
            </Button>
        </PopoverTrigger>

        <PopoverContent class="w-80 p-0" align="start">
            <Command :filter-function="() => true">
                <CommandInput
                    v-model="search"
                    placeholder="Buscar contribuyente..."
                />
                <CommandList>
                    <CommandEmpty>
                        {{ loading ? "Cargando…" : "No se encontró ningún contribuyente." }}
                    </CommandEmpty>
                    <CommandGroup heading="Contribuyentes">
                        <CommandItem
                            v-for="company in companies"
                            :key="company.id"
                            :value="company.name"
                            class="cursor-pointer"
                            @select="selectCompany(company)"
                        >
                            <Building2 class="mr-2 size-4 shrink-0 opacity-60" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ company.name }}
                                </p>
                                <p class="truncate font-mono text-xs text-muted-foreground">
                                    {{ company.ruc }}
                                </p>
                            </div>
                            <Check
                                v-if="currentCompany?.id === company.id"
                                class="ml-2 size-4 shrink-0 text-primary"
                            />
                        </CommandItem>
                    </CommandGroup>
                </CommandList>
            </Command>
        </PopoverContent>
    </Popover>
</template>
