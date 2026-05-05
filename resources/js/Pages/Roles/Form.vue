<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import type { Role, Permission, ModelEntity, ModelPermission } from "@/types";
import AppLayout from "@/layouts/AppLayout.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { ShieldCheck, ArrowLeft } from "lucide-vue-next";
import { router } from "@inertiajs/vue3";

const props = defineProps<{
    role?: Role;
    permissions: Permission[];
    modelEntities: ModelEntity[];
}>();

const isEditing = computed(() => !!props.role);

// Construir matriz de permisos activos
function buildMatrix(): Record<string, Record<number, boolean>> {
    const matrix: Record<string, Record<number, boolean>> = {};
    props.modelEntities.forEach((model) => {
        matrix[model.slug] = {};
        props.permissions.forEach((perm) => {
            matrix[model.slug][perm.id] = false;
        });
    });

    if (props.role?.model_permissions) {
        props.role.model_permissions.forEach((mp: ModelPermission) => {
            const model = props.modelEntities.find(
                (m) => m.id === mp.model_entity_id,
            );
            if (model && matrix[model.slug]) {
                matrix[model.slug][mp.permission_id] = true;
            }
        });
    }

    return matrix;
}

const matrix = ref(buildMatrix());

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

function buildPermissions() {
    const perms: { permission_id: number; model_entity_id: number }[] = [];
    props.modelEntities.forEach((model) => {
        props.permissions.forEach((perm) => {
            if (matrix.value[model.slug]?.[perm.id]) {
                perms.push({
                    permission_id: perm.id,
                    model_entity_id: model.id,
                });
            }
        });
    });
    return perms;
}

function toggleAll(modelSlug: string, value: boolean) {
    props.permissions.forEach((perm) => {
        matrix.value[modelSlug][perm.id] = value;
    });
}

function isAllChecked(modelSlug: string): boolean {
    return props.permissions.every((p) => matrix.value[modelSlug]?.[p.id]);
}

function submit() {
    form.permissions = buildPermissions();

    if (isEditing.value) {
        form.put(route("roles.update", props.role!.id));
    } else {
        form.post(route("roles.store"));
    }
}
</script>

<template>
    <Head title="Configurar rol" />
    <AppLayout>
        <div class="space-y-6 max-w-full md:max-w-2xl xl:max-w-4xl mx-auto">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="icon"
                    @click="router.visit(route('roles.index'))"
                >
                    <ArrowLeft class="size-4" />
                </Button>
                <div>
                    <h1
                        class="text-2xl font-bold text-foreground flex items-center gap-2"
                    >
                        <ShieldCheck class="size-6" />
                        {{ isEditing ? "Editar rol" : "Nuevo rol" }}
                    </h1>
                    <p class="text-muted-foreground text-sm mt-1">
                        {{
                            isEditing
                                ? "Modifica los datos y permisos del rol."
                                : "Completa los datos para crear un nuevo rol."
                        }}
                    </p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Datos básicos -->
                <Card>
                    <CardHeader>
                        <CardTitle>Información del rol</CardTitle>
                        <CardDescription
                            >Datos básicos del rol en el
                            sistema.</CardDescription
                        >
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
                                    :class="{
                                        'border-destructive': form.errors.name,
                                    }"
                                />
                                <p
                                    v-if="form.errors.name"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.name }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="slug">Slug</Label>
                                <Input
                                    id="slug"
                                    v-model="form.slug"
                                    placeholder="Ej: supervisor"
                                    :disabled="isEditing"
                                    :class="{
                                        'border-destructive': form.errors.slug,
                                    }"
                                />
                                <p
                                    v-if="form.errors.slug"
                                    class="text-xs text-destructive"
                                >
                                    {{ form.errors.slug }}
                                </p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label for="description">Descripción</Label>
                            <Input
                                id="description"
                                v-model="form.description"
                                placeholder="Descripción opcional del rol"
                            />
                        </div>
                    </CardContent>
                </Card>

                <!-- Matriz de permisos -->
                <Card>
                    <CardHeader>
                        <CardTitle>Permisos por módulo</CardTitle>
                        <CardDescription>
                            Asigna los permisos que tendrá este rol en cada
                            módulo del sistema.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <!-- Móvil: cards por módulo -->
                        <div class="space-y-3 md:hidden">
                            <div
                                v-for="model in modelEntities"
                                :key="model.id"
                                class="rounded-lg border border-border p-4 space-y-3"
                            >
                                <div>
                                    <p class="font-medium text-foreground">
                                        {{ model.name }}
                                    </p>
                                    <p
                                        v-if="model.description"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ model.description }}
                                    </p>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div
                                        v-for="perm in permissions"
                                        :key="perm.id"
                                        class="flex items-center justify-between gap-2 rounded-md bg-muted/40 px-3 py-2"
                                    >
                                        <span class="text-sm">{{
                                            perm.name
                                        }}</span>
                                        <Switch
                                            :model-value="
                                                matrix[model.slug]?.[perm.id] ??
                                                false
                                            "
                                            @update:model-value="
                                                (val) =>
                                                    (matrix[model.slug][
                                                        perm.id
                                                    ] = val)
                                            "
                                        />
                                    </div>
                                    <div
                                        class="col-span-2 flex items-center justify-between gap-2 rounded-md border border-border px-3 py-2"
                                    >
                                        <span class="text-sm font-medium"
                                            >Todos los permisos</span
                                        >
                                        <Switch
                                            :model-value="
                                                isAllChecked(model.slug)
                                            "
                                            @update:model-value="
                                                (val) =>
                                                    toggleAll(model.slug, val)
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Escritorio: tabla -->
                        <div class="hidden md:block overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-border">
                                        <th
                                            class="text-left py-3 pr-4 font-medium text-muted-foreground w-40"
                                        >
                                            Módulo
                                        </th>
                                        <th
                                            v-for="perm in permissions"
                                            :key="perm.id"
                                            class="text-center py-3 px-4 font-medium text-muted-foreground"
                                        >
                                            <Badge variant="outline">{{
                                                perm.name
                                            }}</Badge>
                                        </th>
                                        <th
                                            class="text-center py-3 px-4 font-medium text-muted-foreground"
                                        >
                                            Todos
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="model in modelEntities"
                                        :key="model.id"
                                        class="border-b border-border last:border-0 hover:bg-muted/30 transition-colors"
                                    >
                                        <td class="py-3 pr-4">
                                            <span
                                                class="font-medium text-foreground"
                                                >{{ model.name }}</span
                                            >
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ model.description }}
                                            </p>
                                        </td>
                                        <td
                                            v-for="perm in permissions"
                                            :key="perm.id"
                                            class="text-center py-3 px-4"
                                        >
                                            <Switch
                                                :model-value="
                                                    matrix[model.slug]?.[
                                                        perm.id
                                                    ] ?? false
                                                "
                                                @update:model-value="
                                                    (val) =>
                                                        (matrix[model.slug][
                                                            perm.id
                                                        ] = val)
                                                "
                                            />
                                        </td>
                                        <td class="text-center py-3 px-4">
                                            <Switch
                                                :model-value="
                                                    isAllChecked(model.slug)
                                                "
                                                @update:model-value="
                                                    (val) =>
                                                        toggleAll(
                                                            model.slug,
                                                            val,
                                                        )
                                                "
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <!-- Acciones -->
                <div
                    class="flex flex-col-reverse sm:flex-row items-center justify-end gap-3"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="w-full sm:w-auto"
                        @click="router.visit(route('roles.index'))"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        class="w-full sm:w-auto"
                        :disabled="form.processing"
                    >
                        {{
                            form.processing
                                ? "Guardando..."
                                : isEditing
                                  ? "Actualizar rol"
                                  : "Crear rol"
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
