<script setup lang="ts">
import { Head, useForm, router } from "@inertiajs/vue3";
import { ref } from "vue";
import type { TenantModelEntity } from "@/types/tenant";
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
import { Layers, Plus, Pencil, Trash2, X } from "lucide-vue-next";
import { usePermissions } from "@/composables/usePermissions";
import TenantLayout from "@/layouts/TenantLayout.vue";

const props = defineProps<{
    modelEntities: TenantModelEntity[];
}>()
const { can } = usePermissions();

function defaultPermissions() {
    return [{ name: "", slug: "" }];
}

// ── Crear ──────────────────────────────────────────────
const createDialog = ref(false);
const createForm = useForm({
    name: "",
    slug: "",
    description: "",
    permissions: defaultPermissions(),
});

function autoSlug() {
    createForm.slug = createForm.name
        .toLowerCase()
        .replace(/\s+/g, "_")
        .replace(/[^a-z0-9_]/g, "");
}

function autoPermissionSlug(index: number) {
    createForm.permissions[index].slug = createForm.permissions[index].name
        .toLowerCase()
        .replace(/\s+/g, "_")
        .replace(/[^a-z0-9_]/g, "");
}

function addPermission() {
    createForm.permissions.push({ name: "", slug: "" });
}

function removePermission(index: number) {
    createForm.permissions.splice(index, 1);
}

function openCreate() {
    createForm.reset();
    createForm.permissions = defaultPermissions();
    createDialog.value = true;
}

function store() {
    createForm.post(route("tenant.model-entities.store"), {
        onSuccess: () => {
            createDialog.value = false;
            createForm.reset();
            createForm.permissions = defaultPermissions();
        },
    });
}

// ── Editar ─────────────────────────────────────────────
const editDialog = ref(false);
const editing = ref<TenantModelEntity | null>(null);
const editForm = useForm({
    name: "",
    slug: "",
    description: "",
    permissions: [] as { name: string; slug: string }[],
});

function openEdit(entity: TenantModelEntity) {
    editing.value = entity;
    editForm.name = entity.name;
    editForm.slug = entity.slug;
    editForm.description = entity.description ?? "";
    editForm.permissions = (entity.permissions ?? []).map((p) => ({
        name: p.name,
        slug: p.slug,
    }));
    editDialog.value = true;
}

function editAutoPermissionSlug(index: number) {
    editForm.permissions[index].slug = editForm.permissions[index].name
        .toLowerCase()
        .replace(/\s+/g, "_")
        .replace(/[^a-z0-9_]/g, "");
}

function editAddPermission() {
    editForm.permissions.push({ name: "", slug: "" });
}

function editRemovePermission(index: number) {
    editForm.permissions.splice(index, 1);
}

function update() {
    editForm.put(route("tenant.model-entities.update", editing.value!.id), {
        onSuccess: () => {
            editDialog.value = false;
            editing.value = null;
        },
    });
}

// ── Eliminar ───────────────────────────────────────────
const deleteDialog = ref(false);
const toDelete = ref<TenantModelEntity | null>(null);

function confirmDelete(entity: TenantModelEntity) {
    toDelete.value = entity;
    deleteDialog.value = true;
}

function handleDelete() {
    router.delete(route("tenant.model-entities.destroy", toDelete.value!.id), {
        onFinish: () => {
            deleteDialog.value = false;
            toDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Módulos del tenant" />
    <TenantLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-foreground flex items-center gap-2">
                        <Layers class="size-6" />
                        Módulos
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        Gestiona los módulos del tenant y sus permisos.
                    </p>
                </div>
                <Button v-if="can('create', 'models')" @click="openCreate">
                    <Plus class="size-4" />
                    Nuevo módulo
                </Button>
            </div>

            <div class="rounded-lg border border-border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Slug</TableHead>
                            <TableHead>Permisos</TableHead>
                            <TableHead class="text-center">Roles</TableHead>
                            <TableHead class="text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="entity in modelEntities" :key="entity.id">
                            <TableCell class="font-medium">{{ entity.name }}</TableCell>
                            <TableCell>
                                <code class="text-xs bg-muted px-1.5 py-0.5 rounded">{{ entity.slug }}</code>
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-wrap gap-1">
                                    <Badge
                                        v-for="perm in entity.permissions"
                                        :key="perm.slug"
                                        variant="outline"
                                        class="text-xs"
                                    >
                                        {{ perm.name }}
                                    </Badge>
                                    <span v-if="!entity.permissions?.length" class="text-xs text-muted-foreground">
                                        Sin permisos
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge variant="outline">{{ entity.model_permissions_count }}</Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Button v-if="can('edit', 'models')" variant="ghost" size="icon" @click="openEdit(entity)">
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button v-if="can('delete', 'models')"
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:text-destructive"
                                        @click="confirmDelete(entity)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="modelEntities.length === 0">
                            <TableCell colspan="5" class="text-center text-muted-foreground py-8">
                                No hay módulos registrados.
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
                    <DialogTitle>Nuevo módulo</DialogTitle>
                    <DialogDescription>Completa los datos y define los permisos del módulo.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="store" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label>Nombre</Label>
                            <Input
                                v-model="createForm.name"
                                placeholder="Ej: Reportes"
                                @input="autoSlug"
                                :class="{ 'border-destructive': createForm.errors.name }"
                            />
                            <p v-if="createForm.errors.name" class="text-xs text-destructive">{{ createForm.errors.name }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label>Slug</Label>
                            <Input
                                v-model="createForm.slug"
                                placeholder="Ej: reportes"
                                :class="{ 'border-destructive': createForm.errors.slug }"
                            />
                            <p v-if="createForm.errors.slug" class="text-xs text-destructive">{{ createForm.errors.slug }}</p>
                        </div>
                        <div class="col-span-2 space-y-2">
                            <Label>Descripción <span class="text-muted-foreground text-xs">(opcional)</span></Label>
                            <Input v-model="createForm.description" placeholder="Descripción del módulo" />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <Label>Permisos</Label>
                            <Button type="button" variant="outline" size="sm" @click="addPermission">
                                <Plus class="size-3 mr-1" /> Agregar permiso
                            </Button>
                        </div>
                        <div v-for="(perm, i) in createForm.permissions" :key="i" class="flex items-center gap-2">
                            <div class="flex-1">
                                <Input
                                    v-model="perm.name"
                                    placeholder="Nombre"
                                    @input="autoPermissionSlug(i)"
                                    :class="{ 'border-destructive': createForm.errors['permissions.' + i + '.name'] }"
                                />
                            </div>
                            <div class="flex-1">
                                <Input
                                    v-model="perm.slug"
                                    placeholder="Slug"
                                    :class="{ 'border-destructive': createForm.errors['permissions.' + i + '.slug'] }"
                                />
                            </div>
                            <Button type="button" variant="ghost" size="icon" class="shrink-0" @click="removePermission(i)">
                                <X class="size-4" />
                            </Button>
                        </div>
                        <p v-if="createForm.errors.permissions" class="text-xs text-destructive">{{ createForm.errors.permissions }}</p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="createDialog = false">Cancelar</Button>
                        <Button type="submit" :disabled="createForm.processing">Crear módulo</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog editar -->
        <Dialog v-model:open="editDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Editar módulo</DialogTitle>
                    <DialogDescription>Modifica los datos y permisos de <strong>{{ editing?.name }}</strong>.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="update" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label>Nombre</Label>
                            <Input
                                v-model="editForm.name"
                                :class="{ 'border-destructive': editForm.errors.name }"
                            />
                            <p v-if="editForm.errors.name" class="text-xs text-destructive">{{ editForm.errors.name }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label>Slug</Label>
                            <Input v-model="editForm.slug" disabled />
                        </div>
                        <div class="col-span-2 space-y-2">
                            <Label>Descripción</Label>
                            <Input v-model="editForm.description" placeholder="Descripción del módulo" />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <Label>Permisos</Label>
                            <Button type="button" variant="outline" size="sm" @click="editAddPermission">
                                <Plus class="size-3 mr-1" /> Agregar permiso
                            </Button>
                        </div>
                        <div v-for="(perm, i) in editForm.permissions" :key="i" class="flex items-center gap-2">
                            <div class="flex-1">
                                <Input
                                    v-model="perm.name"
                                    placeholder="Nombre"
                                    @input="editAutoPermissionSlug(i)"
                                />
                            </div>
                            <div class="flex-1">
                                <Input v-model="perm.slug" placeholder="Slug" />
                            </div>
                            <Button type="button" variant="ghost" size="icon" class="shrink-0" @click="editRemovePermission(i)">
                                <X class="size-4" />
                            </Button>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="editDialog = false">Cancelar</Button>
                        <Button type="submit" :disabled="editForm.processing">Guardar cambios</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog eliminar -->
        <Dialog v-model:open="deleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>¿Eliminar módulo?</DialogTitle>
                    <DialogDescription>
                        Estás a punto de eliminar el módulo <strong>{{ toDelete?.name }}</strong>.
                        Si está asignado a algún rol, no podrá eliminarse.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteDialog = false">Cancelar</Button>
                    <Button variant="destructive" @click="handleDelete">Eliminar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
