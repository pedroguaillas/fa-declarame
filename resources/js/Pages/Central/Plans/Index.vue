<script setup lang="ts">
import { Head, useForm, router } from "@inertiajs/vue3";
import { ref } from "vue";
import type { Plan } from "@/types";
import AppLayout from "@/layouts/AppLayout.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
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
import { Building2, Plus, Pencil, Trash2, Users } from "lucide-vue-next";

const props = defineProps<{
    plans: Plan[];
}>();

// ── Crear ──────────────────────────────────────────────
const createDialog = ref(false);
const createForm = useForm({
    name: "",
    slug: "",
    description: "",
    price: "",
    max_employees: "",
    is_active: true,
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
    createForm.post(route("plans.store"), {
        onSuccess: () => {
            createDialog.value = false;
            createForm.reset();
        },
    });
}

// ── Editar ─────────────────────────────────────────────
const editDialog = ref(false);
const editing = ref<Plan | null>(null);
const editForm = useForm({
    name: "",
    slug: "",
    description: "",
    price: "",
    max_employees: "",
    is_active: true,
});

function openEdit(plan: Plan) {
    editing.value = plan;
    editForm.name = plan.name;
    editForm.slug = plan.slug;
    editForm.description = plan.description ?? "";
    editForm.price = String(plan.price);
    editForm.max_employees = String(plan.max_employees);
    editForm.is_active = plan.is_active;
    editDialog.value = true;
}

function update() {
    editForm.put(route("plans.update", editing.value!.id), {
        onSuccess: () => {
            editDialog.value = false;
            editing.value = null;
        },
    });
}

// ── Toggle activo ──────────────────────────────────────
function toggleActive(plan: Plan) {
    router.patch(route("plans.toggle-active", plan.id));
}

// ── Eliminar ───────────────────────────────────────────
const deleteDialog = ref(false);
const toDelete = ref<Plan | null>(null);

function confirmDelete(plan: Plan) {
    toDelete.value = plan;
    deleteDialog.value = true;
}

function handleDelete() {
    router.delete(route("plans.destroy", toDelete.value!.id), {
        onFinish: () => {
            deleteDialog.value = false;
            toDelete.value = null;
        },
    });
}

function formatPrice(price: number) {
    return new Intl.NumberFormat("es-EC", {
        style: "currency",
        currency: "USD",
    }).format(price);
}
</script>

<template>
    <Head title="Planes de suscripción" />
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-2xl font-bold text-foreground flex items-center gap-2"
                    >
                        <Building2 class="size-6" />
                        Planes
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        Gestiona los planes de suscripción disponibles en el
                        sistema.
                    </p>
                </div>
                <Button @click="openCreate">
                    <Plus class="size-4" />
                    Nuevo plan
                </Button>
            </div>

            <!-- Tabla -->
            <div class="rounded-lg border border-border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nombre</TableHead>
                            <TableHead>Slug</TableHead>
                            <TableHead class="text-right">Precio</TableHead>
                            <TableHead class="text-center"
                                >Max. Empleados</TableHead
                            >
                            <TableHead class="text-center"
                                >Suscripciones</TableHead
                            >
                            <TableHead class="text-center">Activo</TableHead>
                            <TableHead class="text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="plan in plans" :key="plan.id">
                            <TableCell class="font-medium">
                                <div>{{ plan.name }}</div>
                                <div
                                    v-if="plan.description"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ plan.description }}
                                </div>
                            </TableCell>
                            <TableCell>
                                <code
                                    class="text-xs bg-muted px-1.5 py-0.5 rounded"
                                    >{{ plan.slug }}</code
                                >
                            </TableCell>
                            <TableCell class="text-right font-medium">
                                {{ formatPrice(plan.price) }}
                            </TableCell>
                            <TableCell class="text-center">
                                <div
                                    class="flex items-center justify-center gap-1"
                                >
                                    <Users
                                        class="size-3 text-muted-foreground"
                                    />
                                    <span>{{ plan.max_employees }}</span>
                                </div>
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge variant="outline">{{
                                    plan.subscriptions_count
                                }}</Badge>
                            </TableCell>
                            <TableCell class="text-center">
                                <Switch
                                    :model-value="plan.is_active"
                                    @update:model-value="toggleActive(plan)"
                                />
                            </TableCell>
                            <TableCell class="text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="openEdit(plan)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:text-destructive"
                                        @click="confirmDelete(plan)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="plans.length === 0">
                            <TableCell
                                colspan="7"
                                class="text-center text-muted-foreground py-8"
                            >
                                No hay planes registrados.
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
                    <DialogTitle>Nuevo plan</DialogTitle>
                    <DialogDescription
                        >Completa los datos para crear un nuevo
                        plan.</DialogDescription
                    >
                </DialogHeader>
                <form @submit.prevent="store" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label>Nombre</Label>
                            <Input
                                v-model="createForm.name"
                                placeholder="Ej: Empresarial"
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
                                placeholder="Ej: empresarial"
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
                        <div class="space-y-2">
                            <Label>Precio (USD)</Label>
                            <Input
                                v-model="createForm.price"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                                :class="{
                                    'border-destructive':
                                        createForm.errors.price,
                                }"
                            />
                            <p
                                v-if="createForm.errors.price"
                                class="text-xs text-destructive"
                            >
                                {{ createForm.errors.price }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Máximo de empleados</Label>
                            <Input
                                v-model="createForm.max_employees"
                                type="number"
                                min="1"
                                placeholder="5"
                                :class="{
                                    'border-destructive':
                                        createForm.errors.max_employees,
                                }"
                            />
                            <p
                                v-if="createForm.errors.max_employees"
                                class="text-xs text-destructive"
                            >
                                {{ createForm.errors.max_employees }}
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
                                placeholder="Descripción del plan"
                            />
                        </div>
                        <div class="col-span-2 flex items-center gap-3">
                            <Switch
                                :model-value="createForm.is_active"
                                @update:model-value="
                                    (val) => (createForm.is_active = val)
                                "
                            />
                            <Label class="cursor-pointer">Plan activo</Label>
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
                            >Crear plan</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog editar -->
        <Dialog v-model:open="editDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Editar plan</DialogTitle>
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
                        <div class="space-y-2">
                            <Label>Precio (USD)</Label>
                            <Input
                                v-model="editForm.price"
                                type="number"
                                min="0"
                                step="0.01"
                                :class="{
                                    'border-destructive': editForm.errors.price,
                                }"
                            />
                            <p
                                v-if="editForm.errors.price"
                                class="text-xs text-destructive"
                            >
                                {{ editForm.errors.price }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Máximo de empleados</Label>
                            <Input
                                v-model="editForm.max_employees"
                                type="number"
                                min="1"
                                :class="{
                                    'border-destructive':
                                        editForm.errors.max_employees,
                                }"
                            />
                            <p
                                v-if="editForm.errors.max_employees"
                                class="text-xs text-destructive"
                            >
                                {{ editForm.errors.max_employees }}
                            </p>
                        </div>
                        <div class="col-span-2 space-y-2">
                            <Label>Descripción</Label>
                            <Input v-model="editForm.description" />
                        </div>
                        <div class="col-span-2 flex items-center gap-3">
                            <Switch
                                :model-value="editForm.is_active"
                                @update:model-value="
                                    (val) => (editForm.is_active = val)
                                "
                            />
                            <Label class="cursor-pointer">Plan activo</Label>
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
                    <DialogTitle>¿Eliminar plan?</DialogTitle>
                    <DialogDescription>
                        Estás a punto de eliminar el plan
                        <strong>{{ toDelete?.name }}</strong
                        >. Si tiene suscripciones asociadas no podrá eliminarse.
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
