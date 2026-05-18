<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import type { TenantRole, TenantModelEntity } from "@/types/tenant";
import AppLayout from "@/layouts/AppLayout.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ShieldCheck, ArrowLeft } from "lucide-vue-next";
import { router } from "@inertiajs/vue3";
import GroupCheckMatrix from "@/components/Shared/GroupCheckMatrix.vue";
import type { SelectionGroup } from "@/components/Shared/GroupCheckMatrix.vue";

const props = defineProps<{
    role?: TenantRole;
    modelEntities: TenantModelEntity[];
}>();

const isEditing = computed(() => !!props.role);

const permissionGroups = computed<SelectionGroup[]>(() =>
    props.modelEntities.map((m) => ({
        id: m.id,
        display_name: m.name,
        items: (m.permissions ?? []).map((p) => ({
            id: p.id,
            display_name: p.name,
        })),
    })),
);

const initialIds: number[] = [];
if (props.role?.model_permissions) {
    for (const mp of props.role.model_permissions) {
        initialIds.push(mp.permission_id);
    }
}
const selectedPermissionIds = ref<number[]>(initialIds);

const form = useForm({
    name: props.role?.name ?? "",
    slug: props.role?.slug ?? "",
    description: props.role?.description ?? "",
    permissions: [] as { permission_id: number; model_entity_id: number }[],
});

function autoSlug() {
    if (!isEditing.value) {
        form.slug = form.name
            .toLowerCase()
            .replace(/\s+/g, "_")
            .replace(/[^a-z0-9_]/g, "");
    }
}

function buildPermissions(): { permission_id: number; model_entity_id: number }[] {
    return selectedPermissionIds.value.map((permId) => {
        for (const model of props.modelEntities) {
            const found = (model.permissions ?? []).find((p) => p.id === permId);
            if (found) {
                return { permission_id: permId, model_entity_id: model.id };
            }
        }
        throw new Error(`Permission ID ${permId} not found in any model entity`);
    });
}

function submit() {
    form.permissions = buildPermissions();

    if (isEditing.value) {
        form.put(route("tenant.roles.update", props.role!.id));
    } else {
        form.post(route("tenant.roles.store"));
    }
}
</script>

<template>
    <Head title="Configurar rol de tenant" />
    <AppLayout>
        <div class="space-y-6 max-w-full md:max-w-2xl xl:max-w-4xl mx-auto">
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="icon" @click="router.visit(route('tenant.roles.index'))">
                    <ArrowLeft class="size-4" />
                </Button>
                <div>
                    <h1 class="text-2xl font-bold text-foreground flex items-center gap-2">
                        <ShieldCheck class="size-6" />
                        {{ isEditing ? "Editar rol" : "Nuevo rol" }}
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        {{ isEditing ? "Modifica los datos y permisos del rol." : "Completa los datos para crear un nuevo rol." }}
                    </p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Información del rol</CardTitle>
                        <CardDescription>Datos básicos del rol en el tenant.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="name">Nombre</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    placeholder="Ej: Supervisor"
                                    @input="autoSlug"
                                    :class="{ 'border-destructive': form.errors.name }"
                                />
                                <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="slug">Slug</Label>
                                <Input
                                    id="slug"
                                    v-model="form.slug"
                                    placeholder="Ej: supervisor"
                                    :disabled="isEditing"
                                    :class="{ 'border-destructive': form.errors.slug }"
                                />
                                <p v-if="form.errors.slug" class="text-xs text-destructive">{{ form.errors.slug }}</p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label for="description">Descripción</Label>
                            <Input id="description" v-model="form.description" placeholder="Descripción opcional del rol" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Permisos por módulo</CardTitle>
                        <CardDescription>Asigna los permisos que tendrá este rol en cada módulo del tenant.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <GroupCheckMatrix :groups="permissionGroups" v-model="selectedPermissionIds" />
                    </CardContent>
                </Card>

                <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3">
                    <Button type="button" variant="outline" class="w-full sm:w-auto" @click="router.visit(route('tenant.roles.index'))">
                        Cancelar
                    </Button>
                    <Button type="submit" class="w-full sm:w-auto" :disabled="form.processing">
                        {{ form.processing ? "Guardando..." : isEditing ? "Actualizar rol" : "Crear rol" }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
