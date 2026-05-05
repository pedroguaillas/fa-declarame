<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import TenantLayout from "@/layouts/TenantLayout.vue";
import HeaderList from "@/components/Shared/HeaderList.vue";
import Pagination from "@/components/Shared/Pagination.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Search, X, Pencil, Trash2 } from "lucide-vue-next";
import type { Paginator } from "@/types";
import ContactFormDialog from "./components/ContactFormDialog.vue";
import ContactDeleteDialog from "./components/ContactDeleteDialog.vue";

interface IdentificationType {
    id: number;
    description: string;
}

interface Contact {
    id: number;
    identification_type_id: number;
    identification: string;
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    provider_type: string;
    data_additional: { passport_type?: string } | null;
    identification_type: { id: number; description: string } | null;
}

const props = defineProps<{
    contacts: Paginator<Contact>;
    filters: { search: string };
    identificationTypes: IdentificationType[];
}>();

const filteredIdentificationTypes = props.identificationTypes.filter(
    (type) => type.description !== "CONSUMIDOR FINAL",
);

// ── Search ────────────────────────────────────────────
const search = ref(props.filters.search);
let debounceTimer: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(
            route("tenant.contacts.index"),
            { search: value || undefined },
            { preserveState: true, replace: true },
        );
    }, 300);
});

function clearSearch() {
    search.value = "";
}

// ── Pagination ────────────────────────────────────────
function changePage(page: number) {
    router.get(
        route("tenant.contacts.index"),
        { search: search.value || undefined, page },
        { preserveState: true, replace: true },
    );
}

// ── Form Dialog ───────────────────────────────────────
const formDialog = ref(false);
const editingContact = ref<Contact | null>(null);

function openCreate() {
    editingContact.value = null;
    formDialog.value = true;
}

function openEdit(contact: Contact) {
    editingContact.value = contact;
    formDialog.value = true;
}

// ── Delete Dialog ─────────────────────────────────────
const deleteDialog = ref(false);
const toDelete = ref<Contact | null>(null);

function confirmDelete(contact: Contact) {
    toDelete.value = contact;
    deleteDialog.value = true;
}

// ── Helpers ───────────────────────────────────────────
function isConsumidorFinal(contact: Contact) {
    return contact.identification === "9999999999999";
}
</script>

<template>
    <Head title="Contactos" />
    <TenantLayout>
        <div class="flex flex-col gap-4 w-full">
            <HeaderList
                title="Contactos"
                :description="`${contacts.total} contacto${contacts.total !== 1 ? 's' : ''}`"
                link-label="Nuevo contacto"
                @click-link="openCreate"
            />

            <!-- Search -->
            <div class="relative w-full sm:w-80">
                <Search class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                <Input
                    v-model="search"
                    placeholder="Buscar por nombre o identificación..."
                    class="pl-10 pr-9"
                />
                <button
                    v-if="search"
                    type="button"
                    @click="clearSearch"
                    class="text-muted-foreground hover:text-foreground absolute top-1/2 right-2 -translate-y-1/2 cursor-pointer rounded-full p-1 transition-colors"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Table -->
            <div class="rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Identificación</TableHead>
                            <TableHead>Nombre</TableHead>
                            <TableHead class="hidden md:table-cell">Tipo</TableHead>
                            <TableHead class="hidden md:table-cell">Teléfono</TableHead>
                            <TableHead class="hidden lg:table-cell">Email</TableHead>
                            <TableHead class="w-20 text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="contact in contacts.data" :key="contact.id">
                            <TableCell class="font-mono text-sm">
                                {{ contact.identification }}
                            </TableCell>
                            <TableCell class="font-medium">
                                {{ contact.name }}
                            </TableCell>
                            <TableCell class="hidden md:table-cell">
                                {{ contact.identification_type?.description ?? "—" }}
                            </TableCell>
                            <TableCell class="hidden md:table-cell">
                                {{ contact.phone ?? "—" }}
                            </TableCell>
                            <TableCell class="hidden lg:table-cell">
                                {{ contact.email ?? "—" }}
                            </TableCell>
                            <TableCell class="text-right">
                                <div v-if="!isConsumidorFinal(contact)" class="flex items-center justify-end gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8"
                                        @click="openEdit(contact)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-destructive hover:text-destructive"
                                        @click="confirmDelete(contact)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="contacts.data.length === 0">
                            <TableCell colspan="6" class="text-muted-foreground py-8 text-center">
                                No se encontraron contactos.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Pagination -->
            <Pagination :paginator="contacts" @change-page="changePage" />
        </div>

        <!-- Form Dialog (Create / Edit) -->
        <ContactFormDialog
            v-model:open="formDialog"
            :contact="editingContact"
            :identification-types="filteredIdentificationTypes"
        />

        <!-- Delete Confirmation -->
        <ContactDeleteDialog
            v-model:open="deleteDialog"
            :contact="toDelete"
        />
    </TenantLayout>
</template>
