<script setup lang="ts">
import { useForm, router } from "@inertiajs/vue3";
import { ref } from "vue";
import type { Tenant, Paginator } from "@/types";
import AppLayout from "@/layouts/AppLayout.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    Building2,
    Plus,
    Pencil,
    Trash2,
    Globe,
    ExternalLink,
} from "lucide-vue-next";

const props = defineProps<{
    tenants: Paginator<Tenant>;
}>();

// ── Crear ──────────────────────────────────────────────
const createDialog = ref(false);
const createForm = useForm({
    name: "",
    subdomain: "",
});

function autoSubdomain() {
    createForm.subdomain = createForm.name
        .toLowerCase()
        .replace(/\s+/g, "-")
        .replace(/[^a-z0-9-]/g, "");
}

function openCreate() {
    createForm.reset();
    createDialog.value = true;
}

function store() {
    createForm.post(route("tenants.store"), {
        onSuccess: () => {
            createDialog.value = false;
            createForm.reset();
        },
    });
}

// ── Editar ─────────────────────────────────────────────
const editDialog = ref(false);
const editing = ref<Tenant | null>(null);
const editForm = useForm({ name: "" });

function openEdit(tenant: Tenant) {
    editing.value = tenant;
    editForm.name = tenant.name;
    editDialog.value = true;
}

function update() {
    editForm.put(route("tenants.update", editing.value!.id), {
        onSuccess: () => {
            editDialog.value = false;
            editing.value = null;
        },
    });
}

// ── Eliminar ───────────────────────────────────────────
const deleteDialog = ref(false);
const toDelete = ref<Tenant | null>(null);

function confirmDelete(tenant: Tenant) {
    toDelete.value = tenant;
    deleteDialog.value = true;
}

function handleDelete() {
    router.delete(route("tenants.destroy", toDelete.value!.id), {
        onFinish: () => {
            deleteDialog.value = false;
            toDelete.value = null;
        },
    });
}

function goToPage(url: string | null) {
    if (url) router.visit(url, { preserveScroll: true });
}

function getTenantUrl(tenant: Tenant) {
    const domain = tenant.domains?.[0]?.domain;
    return domain ? `http://${domain}` : null;
}
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-2xl font-bold text-foreground flex items-center gap-2"
                    >
                        <Building2 class="size-6" />
                        Tenants
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        Gestiona las empresas del sistema. Cada tenant tiene su
                        propia base de datos.
                    </p>
                </div>
                <Button @click="openCreate">
                    <Plus class="size-4" />
                    Nuevo tenant
                </Button>
            </div>

            <!-- Tabla -->
            <div class="rounded-lg border border-border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nombre</TableHead>
                            <TableHead>ID / Subdominio</TableHead>
                            <TableHead>Dominio</TableHead>
                            <TableHead>Admin asignado</TableHead>
                            <TableHead class="text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="tenant in tenants.data"
                            :key="tenant.id"
                        >
                            <TableCell class="font-medium">{{
                                tenant.name
                            }}</TableCell>
                            <TableCell>
                                <code
                                    class="text-xs bg-muted px-1.5 py-0.5 rounded"
                                >
                                    {{ tenant.id }}
                                </code>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <Globe
                                        class="size-3 text-muted-foreground"
                                    />
                                    <span class="text-sm">{{
                                        tenant.domains?.[0]?.domain ?? "—"
                                    }}</span>
                                    <a
                                        v-if="getTenantUrl(tenant)"
                                        :href="getTenantUrl(tenant)!"
                                        target="_blank"
                                        class="text-muted-foreground hover:text-foreground"
                                    >
                                        <ExternalLink class="size-3" />
                                    </a>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div v-if="tenant.user">
                                    <p class="text-sm font-medium">
                                        {{ tenant.user.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ tenant.user.email }}
                                    </p>
                                </div>
                                <Badge v-else variant="outline" class="text-xs"
                                    >Sin asignar</Badge
                                >
                            </TableCell>
                            <TableCell class="text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="openEdit(tenant)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:text-destructive"
                                        @click="confirmDelete(tenant)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="tenants.data.length === 0">
                            <TableCell
                                colspan="5"
                                class="text-center text-muted-foreground py-8"
                            >
                                No hay tenants registrados.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Paginación -->
                <div
                    v-if="tenants.last_page > 1"
                    class="flex items-center justify-between px-4 py-3 border-t border-border"
                >
                    <p class="text-sm text-muted-foreground">
                        Mostrando {{ tenants.from }} - {{ tenants.to }} de
                        {{ tenants.total }}
                    </p>
                    <div class="flex gap-1">
                        <Button
                            v-for="link in tenants.links"
                            :key="link.label"
                            variant="outline"
                            size="sm"
                            :disabled="!link.url"
                            :class="{
                                'bg-primary text-primary-foreground':
                                    link.active,
                            }"
                            @click="goToPage(link.url)"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Dialog crear -->
        <Dialog v-model:open="createDialog">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Nuevo tenant</DialogTitle>
                    <DialogDescription>
                        Se creará una base de datos dedicada para este tenant.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="store" class="space-y-4">
                    <div class="space-y-2">
                        <Label>Nombre de la empresa</Label>
                        <Input
                            v-model="createForm.name"
                            placeholder="Ej: Empresa ABC"
                            @input="autoSubdomain"
                            :class="{
                                'border-destructive': createForm.errors.name,
                            }"
                        />
                        <p
                            v-if="createForm.errors.name"
                            class="text-xs text-destructive"
                        >
                            {{ createForm.errors.name }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label>Subdominio</Label>
                        <div class="flex items-center gap-2">
                            <Input
                                v-model="createForm.subdomain"
                                placeholder="empresa-abc"
                                :class="{
                                    'border-destructive':
                                        createForm.errors.subdomain,
                                }"
                            />
                            <span
                                class="text-sm text-muted-foreground whitespace-nowrap"
                                >.localhost</span
                            >
                        </div>
                        <p
                            v-if="createForm.errors.subdomain"
                            class="text-xs text-destructive"
                        >
                            {{ createForm.errors.subdomain }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Solo letras minúsculas, números y guiones.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="createDialog = false"
                            >Cancelar</Button
                        >
                        <Button type="submit" :disabled="createForm.processing"
                            >Crear tenant</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog editar -->
        <Dialog v-model:open="editDialog">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Editar tenant</DialogTitle>
                    <DialogDescription>
                        Solo puedes cambiar el nombre. El subdominio no puede
                        modificarse.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="update" class="space-y-4">
                    <div class="space-y-2">
                        <Label>Nombre</Label>
                        <Input
                            v-model="editForm.name"
                            :class="{
                                'border-destructive': editForm.errors.name,
                            }"
                        />
                        <p
                            v-if="editForm.errors.name"
                            class="text-xs text-destructive"
                        >
                            {{ editForm.errors.name }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label>Subdominio</Label>
                        <Input :value="editing?.id" disabled />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="editDialog = false"
                            >Cancelar</Button
                        >
                        <Button type="submit" :disabled="editForm.processing"
                            >Guardar cambios</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog eliminar -->
        <Dialog v-model:open="deleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>¿Eliminar tenant?</DialogTitle>
                    <DialogDescription>
                        Se eliminará el tenant
                        <strong>{{ toDelete?.name }}</strong> y su base de
                        datos. Los admins asignados quedarán sin tenant. Esta
                        acción no se puede deshacer.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteDialog = false"
                        >Cancelar</Button
                    >
                    <Button variant="destructive" @click="handleDelete"
                        >Eliminar</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
