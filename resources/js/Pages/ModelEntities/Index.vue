<script setup lang="ts">
import { useForm, router } from "@inertiajs/vue3";
import { ref } from "vue";
import type { ModelEntity } from "@/types";
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
import { Layers, Plus, Pencil, Trash2 } from "lucide-vue-next";

const props = defineProps<{
    modelEntities: ModelEntity[];
}>();

const systemSlugs = [
    "permissions",
    "models",
    "roles",
    "users",
    "plans",
    "subscriptions",
];

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
    createForm.post(route("model-entities.store"), {
        onSuccess: () => {
            createDialog.value = false;
            createForm.reset();
        },
    });
}

// ── Editar ─────────────────────────────────────────────
const editDialog = ref(false);
const editing = ref<ModelEntity | null>(null);
const editForm = useForm({
    name: "",
    slug: "",
    description: "",
});

function openEdit(entity: ModelEntity) {
    editing.value = entity;
    editForm.name = entity.name;
    editForm.slug = entity.slug;
    editForm.description = entity.description ?? "";
    editDialog.value = true;
}

function update() {
    editForm.put(route("model-entities.update", editing.value!.id), {
        onSuccess: () => {
            editDialog.value = false;
            editing.value = null;
        },
    });
}

// ── Eliminar ───────────────────────────────────────────
const deleteDialog = ref(false);
const toDelete = ref<ModelEntity | null>(null);

function confirmDelete(entity: ModelEntity) {
    toDelete.value = entity;
    deleteDialog.value = true;
}

function handleDelete() {
    router.delete(route("model-entities.destroy", toDelete.value!.id), {
        onFinish: () => {
            deleteDialog.value = false;
            toDelete.value = null;
        },
    });
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
                        <Layers class="size-6" />
                        Módulos
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        Gestiona los módulos del sistema sobre los que se
                        asignan permisos.
                    </p>
                </div>
                <Button @click="openCreate">
                    <Plus class="size-4" />
                    Nuevo módulo
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
                                >Asignado en roles</TableHead
                            >
                            <TableHead class="text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="entity in modelEntities"
                            :key="entity.id"
                        >
                            <TableCell class="font-medium">
                                {{ entity.name }}
                                <Badge
                                    v-if="systemSlugs.includes(entity.slug)"
                                    variant="secondary"
                                    class="ml-2 text-xs"
                                >
                                    Sistema
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <code
                                    class="text-xs bg-muted px-1.5 py-0.5 rounded"
                                    >{{ entity.slug }}</code
                                >
                            </TableCell>
                            <TableCell class="text-muted-foreground text-sm">
                                {{ entity.description ?? "—" }}
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge variant="outline">{{
                                    entity.model_permissions_count
                                }}</Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="openEdit(entity)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:text-destructive"
                                        :disabled="
                                            systemSlugs.includes(entity.slug)
                                        "
                                        @click="confirmDelete(entity)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="modelEntities.length === 0">
                            <TableCell
                                colspan="5"
                                class="text-center text-muted-foreground py-8"
                            >
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
                    <DialogDescription
                        >Completa los datos para crear un nuevo
                        módulo.</DialogDescription
                    >
                </DialogHeader>
                <form @submit.prevent="store" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label>Nombre</Label>
                            <Input
                                v-model="createForm.name"
                                placeholder="Ej: Reportes"
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
                                placeholder="Ej: reportes"
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
                                placeholder="Descripción del módulo"
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
                            >Crear módulo</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog editar -->
        <Dialog v-model:open="editDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Editar módulo</DialogTitle>
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
                                placeholder="Descripción del módulo"
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
                    <DialogTitle>¿Eliminar módulo?</DialogTitle>
                    <DialogDescription>
                        Estás a punto de eliminar el módulo
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
