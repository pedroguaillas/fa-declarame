<script setup lang="ts">
import { useForm, router } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import type { User, Role, Paginator, Tenant } from "@/types";
import AppLayout from "@/layouts/AppLayout.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
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

const props = defineProps<{
    users: Paginator<User>;
    roles: Role[];
    admins: User[];
    tenants: Tenant[];
}>();

// ── Crear ──────────────────────────────────────────────
const createDialog = ref(false);
const createForm = useForm({
    name: "",
    email: "",
    password: "",
    role_id: "",
    tenant_id: "",
    admin_id: "",
    is_active: true,
});

const createRoleSlug = computed(
    () => props.roles.find((r) => r.id === Number(createForm.role_id))?.slug,
);

function openCreate() {
    createForm.reset();
    createDialog.value = true;
}

function store() {
    createForm.post(route("users.store"), {
        onSuccess: () => {
            createDialog.value = false;
            createForm.reset();
        },
    });
}

// ── Editar ─────────────────────────────────────────────
const editDialog = ref(false);
const editing = ref<User | null>(null);
const editForm = useForm({
    name: "",
    email: "",
    password: "",
    role_id: "",
    tenant_id: "",
    admin_id: "",
    is_active: true,
});

const editRoleSlug = computed(
    () => props.roles.find((r) => r.id === Number(editForm.role_id))?.slug,
);

function openEdit(user: User) {
    editing.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = "";
    editForm.role_id = String(user.role.id);
    editForm.tenant_id = user.tenant_id ? String(user.tenant_id) : "";
    editForm.admin_id = user.admin_id ? String(user.admin_id) : "";
    editForm.is_active = user.is_active;
    editDialog.value = true;
}

function update() {
    editForm.put(route("users.update", editing.value!.id), {
        onSuccess: () => {
            editDialog.value = false;
            editing.value = null;
        },
    });
}

// ── Toggle activo ──────────────────────────────────────
function toggleActive(user: User) {
    router.patch(route("users.toggle-active", user.id));
}

// ── Eliminar ───────────────────────────────────────────
const deleteDialog = ref(false);
const toDelete = ref<User | null>(null);

function confirmDelete(user: User) {
    toDelete.value = user;
    deleteDialog.value = true;
}

function handleDelete() {
    router.delete(route("users.destroy", toDelete.value!.id), {
        onFinish: () => {
            deleteDialog.value = false;
            toDelete.value = null;
        },
    });
}

// ── Paginación ─────────────────────────────────────────
function goToPage(url: string | null) {
    if (url) router.visit(url, { preserveScroll: true });
}

function getRoleBadgeVariant(slug: string) {
    if (slug === "admin") return "secondary" as const;
    return "outline" as const;
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
                        <Users class="size-6" />
                        Usuarios
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        Gestiona administradores y empleados del sistema.
                    </p>
                </div>
                <Button @click="openCreate">
                    <Plus class="size-4" />
                    Nuevo usuario
                </Button>
            </div>

            <!-- Tabla -->
            <div class="rounded-lg border border-border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Usuario</TableHead>
                            <TableHead>Rol</TableHead>
                            <TableHead>Admin padre</TableHead>
                            <TableHead class="text-center">Activo</TableHead>
                            <TableHead class="text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="user in users.data" :key="user.id">
                            <TableCell>
                                <div class="font-medium">{{ user.name }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ user.email }}
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    :variant="
                                        getRoleBadgeVariant(user.role.slug)
                                    "
                                >
                                    {{ user.role.name }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ user.admin?.name ?? "—" }}
                            </TableCell>
                            <TableCell class="text-center">
                                <Switch
                                    :model-value="user.is_active"
                                    @update:model-value="toggleActive(user)"
                                />
                            </TableCell>
                            <TableCell class="text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="openEdit(user)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:text-destructive"
                                        @click="confirmDelete(user)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="users.data.length === 0">
                            <TableCell
                                colspan="5"
                                class="text-center text-muted-foreground py-8"
                            >
                                No hay usuarios registrados.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Paginación -->
                <div
                    v-if="users.last_page > 1"
                    class="flex items-center justify-between px-4 py-3 border-t border-border"
                >
                    <p class="text-sm text-muted-foreground">
                        Mostrando {{ users.from }} - {{ users.to }} de
                        {{ users.total }}
                    </p>
                    <div class="flex gap-1">
                        <Button
                            v-for="link in users.links"
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
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Nuevo usuario</DialogTitle>
                    <DialogDescription
                        >Completa los datos para registrar un
                        usuario.</DialogDescription
                    >
                </DialogHeader>
                <form @submit.prevent="store" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label>Nombre</Label>
                            <Input
                                v-model="createForm.name"
                                placeholder="Nombre completo"
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
                            <Label>Correo electrónico</Label>
                            <Input
                                v-model="createForm.email"
                                type="email"
                                placeholder="correo@ejemplo.com"
                                :class="{
                                    'border-destructive':
                                        createForm.errors.email,
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
                        <div class="space-y-2">
                            <Label>Rol</Label>
                            <Select v-model="createForm.role_id">
                                <SelectTrigger
                                    class="w-full"
                                    :class="{
                                        'border-destructive':
                                            createForm.errors.role_id,
                                    }"
                                >
                                    <SelectValue
                                        placeholder="Seleccionar rol..."
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="role in roles"
                                        :key="role.id"
                                        :value="String(role.id)"
                                    >
                                        {{ role.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p
                                v-if="createForm.errors.role_id"
                                class="text-xs text-destructive"
                            >
                                {{ createForm.errors.role_id }}
                            </p>
                        </div>

                        <!-- Tenant — solo si es admin -->
                        <div
                            v-if="createRoleSlug === 'admin'"
                            class="col-span-2 space-y-2"
                        >
                            <Label>Tenant (empresa)</Label>
                            <Select v-model="createForm.tenant_id">
                                <SelectTrigger
                                    class="w-full"
                                    :class="{
                                        'border-destructive':
                                            createForm.errors.tenant_id,
                                    }"
                                >
                                    <SelectValue
                                        placeholder="Seleccionar tenant..."
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="tenant in tenants"
                                        :key="tenant.id"
                                        :value="tenant.id"
                                    >
                                        {{ tenant.name }} —
                                        {{ tenant.domains?.[0]?.domain }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p
                                v-if="createForm.errors.tenant_id"
                                class="text-xs text-destructive"
                            >
                                {{ createForm.errors.tenant_id }}
                            </p>
                        </div>

                        <div
                            v-if="createRoleSlug === 'employee'"
                            class="col-span-2 space-y-2"
                        >
                            <Label>Administrador al que pertenece</Label>
                            <Select v-model="createForm.admin_id">
                                <SelectTrigger
                                    class="w-full"
                                    :class="{
                                        'border-destructive':
                                            createForm.errors.admin_id,
                                    }"
                                >
                                    <SelectValue
                                        placeholder="Seleccionar administrador..."
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="admin in admins"
                                        :key="admin.id"
                                        :value="String(admin.id)"
                                    >
                                        {{ admin.name }} — {{ admin.email }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p
                                v-if="createForm.errors.admin_id"
                                class="text-xs text-destructive"
                            >
                                {{ createForm.errors.admin_id }}
                            </p>
                        </div>
                        <div class="col-span-2 flex items-center gap-3">
                            <Switch
                                :model-value="createForm.is_active"
                                @update:model-value="
                                    (val) => (createForm.is_active = val)
                                "
                            />
                            <Label class="cursor-pointer">Usuario activo</Label>
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
                            >Crear usuario</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog editar -->
        <Dialog v-model:open="editDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Editar usuario</DialogTitle>
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
                        <div class="col-span-2 space-y-2">
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
                        <div class="space-y-2">
                            <Label>Rol</Label>
                            <Select v-model="editForm.role_id">
                                <SelectTrigger class="w-full">
                                    <SelectValue
                                        placeholder="Seleccionar rol..."
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="role in roles"
                                        :key="role.id"
                                        :value="String(role.id)"
                                    >
                                        {{ role.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div v-if="editRoleSlug === 'admin'" class="space-y-2">
                            <Label>Tenant (empresa)</Label>
                            <Select v-model="editForm.tenant_id">
                                <SelectTrigger
                                    class="w-full"
                                    :class="{
                                        'border-destructive':
                                            editForm.errors.tenant_id,
                                    }"
                                >
                                    <SelectValue
                                        placeholder="Seleccionar tenant..."
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="tenant in tenants"
                                        :key="tenant.id"
                                        :value="tenant.id"
                                    >
                                        {{ tenant.name }} —
                                        {{ tenant.domains?.[0]?.domain }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p
                                v-if="editForm.errors.tenant_id"
                                class="text-xs text-destructive"
                            >
                                {{ editForm.errors.tenant_id }}
                            </p>
                        </div>

                        <div
                            v-if="editRoleSlug === 'employee'"
                            class="space-y-2"
                        >
                            <Label>Administrador</Label>
                            <Select v-model="editForm.admin_id">
                                <SelectTrigger class="w-full">
                                    <SelectValue placeholder="Seleccionar..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="admin in admins"
                                        :key="admin.id"
                                        :value="String(admin.id)"
                                    >
                                        {{ admin.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="col-span-2 flex items-center gap-3">
                            <Switch
                                :model-value="editForm.is_active"
                                @update:model-value="
                                    (val) => (editForm.is_active = val)
                                "
                            />
                            <Label class="cursor-pointer">Usuario activo</Label>
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
                    <DialogTitle>¿Eliminar usuario?</DialogTitle>
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
    </AppLayout>
</template>
