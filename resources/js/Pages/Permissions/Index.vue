<script setup lang="ts">
import { Head, useForm, router } from "@inertiajs/vue3";
import { ref } from "vue";
import type { Permission } from "@/types";
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
import { KeyRound, Plus, Pencil, Trash2 } from "lucide-vue-next";

const props = defineProps<{
    permissions: Permission[];
}>();

// ── Crear ──────────────────────────────────────────────
const createDialog = ref(false);
const createForm = useForm({
    name: "",
    slug: "",
    description: "",
});

function autoSlug() {
    createForm.slug = createForm.name
        .toLowerCase()
        .replace(/\s+/g, "_")
        .replace(/[^a-z0-9_]/g, "");
}

function openCreate() {
    createForm.reset();
    createDialog.value = true;
}

function store() {
    createForm.post(route("permissions.store"), {
        onSuccess: () => {
            createDialog.value = false;
            createForm.reset();
        },
    });
}

// ── Editar ─────────────────────────────────────────────
const editDialog = ref(false);
const editing = ref<Permission | null>(null);
const editForm = useForm({
    name: "",
    slug: "",
    description: "",
});

function openEdit(permission: Permission) {
    editing.value = permission;
    editForm.name = permission.name;
    editForm.slug = permission.slug;
    editForm.description = permission.description ?? "";
    editDialog.value = true;
}

function update() {
    editForm.put(route("permissions.update", editing.value!.id), {
        onSuccess: () => {
            editDialog.value = false;
            editing.value = null;
        },
    });
}

// ── Eliminar ───────────────────────────────────────────
const deleteDialog = ref(false);
const toDelete = ref<Permission | null>(null);

function confirmDelete(permission: Permission) {
    toDelete.value = permission;
    deleteDialog.value = true;
}

function handleDelete() {
    router.delete(route("permissions.destroy", toDelete.value!.id), {
        onFinish: () => {
            deleteDialog.value = false;
            toDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Permisos del sistema" />
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-2xl font-bold text-foreground flex items-center gap-2"
                    >
                        <KeyRound class="size-6" />
                        Permisos
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        Gestiona los permisos disponibles en el sistema.
                    </p>
                </div>
                <Button @click="openCreate">
                    <Plus class="size-4" />
                    Nuevo permiso
                </Button>
            </div>

            <!-- Tabla -->
            <div class="rounded-lg border border-border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Slug</TableHead>
                            <TableHead>Descripción</TableHead>
                            <TableHead class="text-center"
                                >Usado en roles</TableHead
                            >
                            <TableHead class="text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="permission in permissions"
                            :key="permission.id"
                        >
                            <TableCell class="font-medium">{{
                                permission.name
                            }}</TableCell>
                            <TableCell>
                                <code
                                    class="text-xs bg-muted px-1.5 py-0.5 rounded"
                                    >{{ permission.slug }}</code
                                >
                            </TableCell>
                            <TableCell class="text-muted-foreground text-sm">
                                {{ permission.description ?? "—" }}
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge variant="outline">{{
                                    permission.model_permissions_count
                                }}</Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="openEdit(permission)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:text-destructive"
                                        @click="confirmDelete(permission)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="permissions.length === 0">
                            <TableCell
                                colspan="5"
                                class="text-center text-muted-foreground py-8"
                            >
                                No hay permisos registrados.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <!-- Dialog crear -->
        <Dialog v-model:open="createDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Nuevo permiso</DialogTitle>
                    <DialogDescription
                        >Completa los datos para crear un nuevo
                        permiso.</DialogDescription
                    >
                </DialogHeader>
                <form @submit.prevent="store" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label>Nombre</Label>
                            <Input
                                v-model="createForm.name"
                                placeholder="Ej: Exportar"
                                @input="autoSlug"
                                :class="{
                                    'border-destructive':
                                        createForm.errors.name,
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
                            <Label>Slug</Label>
                            <Input
                                v-model="createForm.slug"
                                placeholder="Ej: exportar"
                                :class="{
                                    'border-destructive':
                                        createForm.errors.slug,
                                }"
                            />
                            <p
                                v-if="createForm.errors.slug"
                                class="text-xs text-destructive"
                            >
                                {{ createForm.errors.slug }}
                            </p>
                        </div>
                        <div class="col-span-2 space-y-2">
                            <Label
                                >Descripción
                                <span class="text-muted-foreground text-xs"
                                    >(opcional)</span
                                ></Label
                            >
                            <Input
                                v-model="createForm.description"
                                placeholder="Descripción del permiso"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="createDialog = false"
                            >Cancelar</Button
                        >
                        <Button type="submit" :disabled="createForm.processing"
                            >Crear permiso</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog editar -->
        <Dialog v-model:open="editDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Editar permiso</DialogTitle>
                    <DialogDescription>
                        Modifica los datos de
                        <strong>{{ editing?.name }}</strong
                        >.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="update" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
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
                            <Label>Slug</Label>
                            <Input v-model="editForm.slug" disabled />
                        </div>
                        <div class="col-span-2 space-y-2">
                            <Label>Descripción</Label>
                            <Input
                                v-model="editForm.description"
                                placeholder="Descripción del permiso"
                            />
                        </div>
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
                    <DialogTitle>¿Eliminar permiso?</DialogTitle>
                    <DialogDescription>
                        Estás a punto de eliminar el permiso
                        <strong>{{ toDelete?.name }}</strong
                        >. Si está asignado a algún rol, no podrá eliminarse.
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
