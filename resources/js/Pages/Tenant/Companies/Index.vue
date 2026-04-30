<script setup lang="ts">
import AppLayout from "@/layouts/AppLayout.vue";
import { router } from "@inertiajs/vue3";
import type { Paginator } from "@/types";
import type { ActionPayload, ColumnDef } from "@/types/shared";
import { Company } from "@/types/tenant";

import DataTableDesktop from "@/components/Shared/DataTableDesktop.vue";
import DataTableMobile from "@/components/Shared/DataTableMobile.vue";
import HeaderList from "@/components/Shared/HeaderList.vue";
import Pagination from "@/components/Shared/Pagination.vue";
import TenantLayout from "@/layouts/TenantLayout.vue";

const props = defineProps<{
    companies: Paginator<Company>;
}>();

const columns: ColumnDef<Company>[] = [
    { key: "ruc", label: "RUC" },
    { key: "name", label: "Nombre" },
    {
        key: "accounting",
        label: "Contabilidad",
        badge: {
            value: (item) => (item.accounting ? "Sí" : "No"),
            variant: (item) => (item.accounting ? "default" : "secondary"),
        },
    },
    {
        key: "retention_agent",
        label: "Ag. Retención",
        badge: {
            value: (item) => (item.retention_agent ? "Sí" : "No"),
            variant: (item) => (item.retention_agent ? "default" : "secondary"),
        },
    },
    { key: "phone", label: "Teléfono" },
    { key: "email", label: "Email" },
];

const actions = [
    {
        event: "edit",
        label: "Editar",
    },
];

function handleAction({ event, item }: ActionPayload<Company>) {
    if (event === "edit") {
        router.visit(route("tenant.companies.edit", { id: item.id }));
    }
}

function handleSelect(item: Company) {
    router.visit(route("tenant.companies.edit", { id: item.id }));
}

function handlePageChange(page: number) {
    const link = props.companies.links[page];
    if (link?.url) router.visit(link.url, { preserveScroll: true });
}
</script>

<template>
    <TenantLayout>
        <div
            class="flex flex-col gap-4 w-full"
        >
            <HeaderList
                title="Contribuyentes"
                :description="`${companies.total} contribuyente${companies.total !== 1 ? 's' : ''} registrado${companies.total !== 1 ? 's' : ''}`"
                link-label="Nuevo contribuyente"
                :link-href="route('tenant.companies.create')"
            />

            <div
                class="border-border bg-card flex-1 overflow-hidden rounded-lg border"
            >
                <!-- Desktop -->
                <div class="hidden h-full md:block">
                    <DataTableDesktop
                        :columns="columns"
                        :items="companies.data"
                        :actions="actions"
                        empty-text="No hay contribuyentes registrados."
                        @select="handleSelect"
                        @action="handleAction"
                    />
                </div>

                <!-- Mobile -->
                <div class="h-full md:hidden">
                    <DataTableMobile
                        :columns="columns"
                        :items="companies.data"
                        :actions="actions"
                        empty-text="No hay contribuyentes registrados."
                        @select="handleSelect"
                        @action="handleAction"
                    />
                </div>
            </div>

            <Pagination
                :paginator="companies"
                @change-page="handlePageChange"
            />
        </div>
    </TenantLayout>
</template>
