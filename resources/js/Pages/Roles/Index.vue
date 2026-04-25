<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { ref } from "vue";
import type { Role } from "@/types";
import AppLayout from "@/layouts/AppLayout.vue";
import { Button } from "@/components/ui/button";
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
import { Plus, Pencil, Trash2, ShieldCheck } from "lucide-vue-next";

const props = defineProps<{
    roles: Role[];
}>();

const deleteDialog = ref(false);
const roleToDelete = ref<Role | null>(null);

function confirmDelete(role: Role) {
    roleToDelete.value = role;
    deleteDialog.value = true;
}

function handleDelete() {
    if (!roleToDelete.value) return;
    router.delete(route("roles.destroy", roleToDelete.value.id), {
        onFinish: () => {
            deleteDialog.value = false;
            roleToDelete.value = null;
        },
    });
}

const systemSlugs = ["super_admin", "admin", "employee"];
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
                        <ShieldCheck class="size-6" />
                        Roles
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        Gestiona los roles y sus permisos en el sistema.
                    </p>
                </div>
                <Button @click="router.visit(route('roles.create'))">
                    <Plus class="size-4" />
                    Nuevo rol
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
                            <TableHead class="text-center">Usuarios</TableHead>
                            <TableHead class="text-center">Permisos</TableHead>
                            <TableHead class="text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="role in roles" :key="role.id">
                            <TableCell class="font-medium">
                                {{ role.name }}
                                <Badge
                                    v-if="systemSlugs.includes(role.slug)"
                                    variant="secondary"
                                    class="ml-2 text-xs"
                                >
                                    Sistema
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <code
                                    class="text-xs bg-muted px-1.5 py-0.5 rounded"
                                >
                                    {{ role.slug }}
                                </code>
                            </TableCell>
                            <TableCell class="text-muted-foreground text-sm">
                                {{ role.description ?? "—" }}
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge variant="outline">{{
                                    role.users_count
                                }}</Badge>
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge variant="outline">{{
                                    role.model_permissions?.length ?? 0
                                }}</Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="
                                            router.visit(
                                                route('roles.edit', role.id),
                                            )
                                        "
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:text-destructive"
                                        :disabled="
                                            systemSlugs.includes(role.slug)
                                        "
                                        @click="confirmDelete(role)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="roles.length === 0">
                            <TableCell
                                colspan="6"
                                class="text-center text-muted-foreground py-8"
                            >
                                No hay roles registrados.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <!-- Dialog confirmación eliminar -->
        <Dialog v-model:open="deleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>¿Eliminar rol?</DialogTitle>
                    <DialogDescription>
                        Estás a punto de eliminar el rol
                        <strong>{{ roleToDelete?.name }}</strong
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
