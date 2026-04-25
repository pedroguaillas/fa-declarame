<script setup lang="ts">
import { useForm, router } from "@inertiajs/vue3";
import { ref } from "vue";
import type { PaginatedData } from "@/types";
import TenantLayout from "@/layouts/TenantLayout.vue";
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
import { Users, Plus, Pencil, Trash2 } from "lucide-vue-next";

interface Employee {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
}

const props = defineProps<{
    employees: PaginatedData<Employee>;
}>();

// ── Crear ──────────────────────────────────────────────
const createDialog = ref(false);
const createForm = useForm({
    name: "",
    email: "",
    password: "",
    is_active: true,
});

function openCreate() {
    createForm.reset();
    createDialog.value = true;
}

function store() {
    createForm.post(route("employees.store"), {
        onSuccess: () => {
            createDialog.value = false;
            createForm.reset();
        },
    });
}

// ── Editar ─────────────────────────────────────────────
const editDialog = ref(false);
const editing = ref<Employee | null>(null);
const editForm = useForm({
    name: "",
    email: "",
    password: "",
    is_active: true,
});

function openEdit(employee: Employee) {
    editing.value = employee;
    editForm.name = employee.name;
    editForm.email = employee.email;
    editForm.password = "";
    editForm.is_active = employee.is_active;
    editDialog.value = true;
}

function update() {
    editForm.put(route("employees.update", editing.value!.id), {
        onSuccess: () => {
            editDialog.value = false;
            editing.value = null;
        },
    });
}

// ── Toggle activo ──────────────────────────────────────
function toggleActive(employee: Employee) {
    router.patch(route("employees.toggle-active", employee.id));
}

// ── Eliminar ───────────────────────────────────────────
const deleteDialog = ref(false);
const toDelete = ref<Employee | null>(null);

function confirmDelete(employee: Employee) {
    toDelete.value = employee;
    deleteDialog.value = true;
}

function handleDelete() {
    router.delete(route("employees.destroy", toDelete.value!.id), {
        onFinish: () => {
            deleteDialog.value = false;
            toDelete.value = null;
        },
    });
}

function goToPage(url: string | null) {
    if (url) router.visit(url, { preserveScroll: true });
}
</script>

<template>
    <TenantLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-2xl font-bold text-foreground flex items-center gap-2"
                    >
                        <Users class="size-6" />
                        Empleados
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        Gestiona los empleados de tu empresa.
                    </p>
                </div>
                <Button @click="openCreate">
                    <Plus class="size-4" />
                    Nuevo empleado
                </Button>
            </div>

            <!-- Tabla -->
            <div class="rounded-lg border border-border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Empleado</TableHead>
                            <TableHead class="text-center">Activo</TableHead>
                            <TableHead class="text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="employee in employees.data"
                            :key="employee.id"
                        >
                            <TableCell>
                                <div class="font-medium">
                                    {{ employee.name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ employee.email }}
                                </div>
                            </TableCell>
                            <TableCell class="text-center">
                                <Switch
                                    :model-value="employee.is_active"
                                    @update:model-value="toggleActive(employee)"
                                />
                            </TableCell>
                            <TableCell class="text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="openEdit(employee)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:text-destructive"
                                        @click="confirmDelete(employee)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="employees.data.length === 0">
                            <TableCell
                                colspan="3"
                                class="text-center text-muted-foreground py-8"
                            >
                                No hay empleados registrados.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Paginación -->
                <div
                    v-if="employees.last_page > 1"
                    class="flex items-center justify-between px-4 py-3 border-t border-border"
                >
                    <p class="text-sm text-muted-foreground">
                        Mostrando {{ employees.from }} - {{ employees.to }} de
                        {{ employees.total }}
                    </p>
                    <div class="flex gap-1">
                        <Button
                            v-for="link in employees.links"
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
                    <DialogTitle>Nuevo empleado</DialogTitle>
                    <DialogDescription
                        >Completa los datos para registrar un
                        empleado.</DialogDescription
                    >
                </DialogHeader>
                <form @submit.prevent="store" class="space-y-4">
                    <div class="space-y-2">
                        <Label>Nombre</Label>
                        <Input
                            v-model="createForm.name"
                            placeholder="Nombre completo"
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
                        <Label>Correo electrónico</Label>
                        <Input
                            v-model="createForm.email"
                            type="email"
                            placeholder="correo@ejemplo.com"
                            :class="{
                                'border-destructive': createForm.errors.email,
                            }"
                        />
                        <p
                            v-if="createForm.errors.email"
                            class="text-xs text-destructive"
                        >
                            {{ createForm.errors.email }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label>Contraseña</Label>
                        <Input
                            v-model="createForm.password"
                            type="password"
                            placeholder="Mínimo 8 caracteres"
                            :class="{
                                'border-destructive':
                                    createForm.errors.password,
                            }"
                        />
                        <p
                            v-if="createForm.errors.password"
                            class="text-xs text-destructive"
                        >
                            {{ createForm.errors.password }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <Switch
                            :model-value="createForm.is_active"
                            @update:model-value="
                                (val) => (createForm.is_active = val)
                            "
                        />
                        <Label class="cursor-pointer">Empleado activo</Label>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="createDialog = false"
                            >Cancelar</Button
                        >
                        <Button type="submit" :disabled="createForm.processing"
                            >Crear empleado</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog editar -->
        <Dialog v-model:open="editDialog">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Editar empleado</DialogTitle>
                    <DialogDescription>
                        Modifica los datos de
                        <strong>{{ editing?.name }}</strong
                        >.
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
                        <Label>Correo electrónico</Label>
                        <Input
                            v-model="editForm.email"
                            type="email"
                            :class="{
                                'border-destructive': editForm.errors.email,
                            }"
                        />
                        <p
                            v-if="editForm.errors.email"
                            class="text-xs text-destructive"
                        >
                            {{ editForm.errors.email }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label
                            >Nueva contraseña
                            <span class="text-muted-foreground text-xs"
                                >(dejar vacío para no cambiar)</span
                            ></Label
                        >
                        <Input
                            v-model="editForm.password"
                            type="password"
                            placeholder="Mínimo 8 caracteres"
                        />
                    </div>
                    <div class="flex items-center gap-3">
                        <Switch
                            :model-value="editForm.is_active"
                            @update:model-value="
                                (val) => (editForm.is_active = val)
                            "
                        />
                        <Label class="cursor-pointer">Empleado activo</Label>
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
                    <DialogTitle>¿Eliminar empleado?</DialogTitle>
                    <DialogDescription>
                        Estás a punto de eliminar a
                        <strong>{{ toDelete?.name }}</strong
                        >. Esta acción no se puede deshacer.
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
    </TenantLayout>
</template>
