<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import {
    SidebarProvider,
    SidebarInset,
    SidebarTrigger,
} from "@/components/ui/sidebar";
import { Separator } from "@/components/ui/separator";
import TenantSidebar from "@/components/TenantSidebar.vue";
import FlashMessage from "@/components/FlashMessage.vue";
import CompanySelector from "@/components/CompanySelector.vue";
import UserNav from "@/components/UserNav.vue";
import type { PageProps } from "@/types";
import type { CompanyScope } from "@/types/tenant";
import { TooltipProvider } from "@/components/ui/tooltip";
import { Building2 } from "lucide-vue-next";
import { Input } from "@/components/ui/input";

const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user);
const currentCompany = computed(() => page.props.currentCompany);

const OVERLAY_EXEMPT = ["Tenant/Companies/Index", "Tenant/Companies/Create", "Tenant/Companies/Edit"];
const showOverlay = computed(() => !currentCompany.value && !OVERLAY_EXEMPT.includes(page.component as string));

const overlaySearch = ref("");
const overlayCompanies = ref<CompanyScope[]>([]);
const overlayLoading = ref(false);
const overlayHasAny = ref<boolean | null>(null); // null = aún no se ha cargado
let overlayDebounce: ReturnType<typeof setTimeout>;

async function loadOverlayCompanies(query: string) {
    overlayLoading.value = true;
    try {
        const url = new URL(route("tenant.company-scope.search"), window.location.origin);
        url.searchParams.set("search", query);
        const res = await fetch(url.toString(), { headers: { Accept: "application/json" } });
        const data: CompanyScope[] = await res.json();
        overlayCompanies.value = data;
        // Solo en la carga inicial (sin búsqueda) determinamos si existen contribuyentes
        if (query === "") {
            overlayHasAny.value = data.length > 0;
        }
    } finally {
        overlayLoading.value = false;
    }
}

watch(overlaySearch, (val) => {
    clearTimeout(overlayDebounce);
    overlayDebounce = setTimeout(() => loadOverlayCompanies(val), 300);
});

watch(currentCompany, (val) => {
    if (!val) {
        overlaySearch.value = "";
        loadOverlayCompanies("");
    }
}, { immediate: true });

function selectOverlayCompany(company: CompanyScope) {
    router.post(
        route("tenant.company-scope.store"),
        { company_id: company.id },
        { preserveScroll: true },
    );
}
</script>

<template>
    <TooltipProvider>
        <SidebarProvider>
            <TenantSidebar />
            <SidebarInset>
                <header
                    class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-border px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
                >
                    <div class="flex items-center gap-2">
                        <SidebarTrigger class="-ml-1" />
                        <Separator orientation="vertical" class="mr-2 h-4" />

                        <CompanySelector :current-company="currentCompany" />
                    </div>

                    <UserNav :user="user" />
                </header>

                <main class="relative flex flex-1 flex-col gap-4 p-6">
                    <slot />

                    <!-- Overlay when no company is selected -->
                    <div
                        v-if="showOverlay"
                        class="absolute inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-sm"
                    >
                        <div class="w-full max-w-sm rounded-xl border bg-card p-6 shadow-lg">
                            <!-- Sin contribuyentes registrados -->
                            <template v-if="overlayHasAny === false && !overlaySearch">
                                <div class="flex flex-col items-center gap-3 text-center">
                                    <div class="flex size-12 items-center justify-center rounded-full bg-primary/10">
                                        <Building2 class="size-6 text-primary" />
                                    </div>
                                    <div>
                                        <h2 class="text-base font-semibold">Aún no tienes contribuyentes</h2>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            Registra tu primer contribuyente para comenzar a usar la plataforma.
                                        </p>
                                    </div>
                                    <a
                                        :href="route('tenant.companies.create')"
                                        class="mt-1 inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
                                    >
                                        <Building2 class="size-4" />
                                        Registrar contribuyente
                                    </a>
                                </div>
                            </template>

                            <!-- Con contribuyentes: búsqueda y lista -->
                            <template v-else>
                                <div class="mb-4 flex flex-col items-center gap-2 text-center">
                                    <div class="flex size-12 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-950/40">
                                        <Building2 class="size-6 text-amber-600 dark:text-amber-400" />
                                    </div>
                                    <h2 class="text-base font-semibold">Selecciona un contribuyente</h2>
                                    <p class="text-sm text-muted-foreground">
                                        Necesitas seleccionar un contribuyente para acceder a esta sección.
                                    </p>
                                </div>

                                <Input
                                    v-model="overlaySearch"
                                    placeholder="Buscar contribuyente..."
                                    class="mb-3"
                                />

                                <div class="max-h-56 overflow-y-auto rounded-md border">
                                    <p
                                        v-if="overlayLoading"
                                        class="px-3 py-4 text-center text-sm text-muted-foreground"
                                    >
                                        Cargando...
                                    </p>
                                    <p
                                        v-else-if="overlayCompanies.length === 0"
                                        class="px-3 py-4 text-center text-sm text-muted-foreground"
                                    >
                                        No se encontró ningún contribuyente.
                                    </p>
                                    <button
                                        v-for="company in overlayCompanies"
                                        :key="company.id"
                                        type="button"
                                        class="flex w-full items-center gap-3 px-3 py-2.5 text-left transition-colors hover:bg-accent hover:text-accent-foreground"
                                        @click="selectOverlayCompany(company)"
                                    >
                                        <Building2 class="size-4 shrink-0 opacity-60" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium">{{ company.name }}</p>
                                            <p class="truncate font-mono text-xs text-muted-foreground">{{ company.ruc }}</p>
                                        </div>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </main>
            </SidebarInset>
            <FlashMessage />
        </SidebarProvider>
    </TooltipProvider>
</template>
