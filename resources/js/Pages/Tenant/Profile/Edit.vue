<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import TenantLayout from "@/layouts/TenantLayout.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { UserCircle, Lock } from "lucide-vue-next";

interface TenantUser {
    id: number;
    name: string;
    email: string;
}

const props = defineProps<{
    user: TenantUser;
}>();

const infoForm = useForm({
    name: props.user.name,
    email: props.user.email,
});

function updateInfo() {
    infoForm.patch(route("tenant.profile.update-info"));
}

const passwordForm = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});

function updatePassword() {
    passwordForm.patch(route("tenant.profile.update-password"), {
        onSuccess: () => passwordForm.reset(),
    });
}

function getInitials(name: string) {
    return name
        .split(" ")
        .map((n) => n[0])
        .slice(0, 2)
        .join("")
        .toUpperCase();
}
</script>

<template>
    <TenantLayout>
        <div class="space-y-6 max-w-2xl">
            <div>
                <h1
                    class="text-2xl font-bold text-foreground flex items-center gap-2"
                >
                    <UserCircle class="size-6" />
                    Mi perfil
                </h1>
                <p class="text-muted-foreground text-sm mt-1">
                    Gestiona tu información personal y contraseña.
                </p>
            </div>

            <!-- Avatar -->
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center gap-4">
                        <div
                            class="flex size-16 items-center justify-center rounded-full bg-primary text-primary-foreground text-xl font-bold"
                        >
                            {{ getInitials(user.name) }}
                        </div>
                        <div>
                            <p class="text-lg font-semibold text-foreground">
                                {{ user.name }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ user.email }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Info personal -->
            <Card>
                <CardHeader>
                    <CardTitle>Información personal</CardTitle>
                    <CardDescription
                        >Actualiza tu nombre y correo
                        electrónico.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="updateInfo" class="space-y-4">
                        <div class="space-y-2">
                            <Label>Nombre completo</Label>
                            <Input
                                v-model="infoForm.name"
                                :class="{
                                    'border-destructive': infoForm.errors.name,
                                }"
                            />
                            <p
                                v-if="infoForm.errors.name"
                                class="text-xs text-destructive"
                            >
                                {{ infoForm.errors.name }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Correo electrónico</Label>
                            <Input
                                v-model="infoForm.email"
                                type="email"
                                :class="{
                                    'border-destructive': infoForm.errors.email,
                                }"
                            />
                            <p
                                v-if="infoForm.errors.email"
                                class="text-xs text-destructive"
                            >
                                {{ infoForm.errors.email }}
                            </p>
                        </div>
                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                :disabled="infoForm.processing"
                                >Guardar cambios</Button
                            >
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Contraseña -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Lock class="size-4" />
                        Cambiar contraseña
                    </CardTitle>
                    <CardDescription>Mínimo 8 caracteres.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="updatePassword" class="space-y-4">
                        <div class="space-y-2">
                            <Label>Contraseña actual</Label>
                            <Input
                                v-model="passwordForm.current_password"
                                type="password"
                                placeholder="••••••••"
                                :class="{
                                    'border-destructive':
                                        passwordForm.errors.current_password,
                                }"
                            />
                            <p
                                v-if="passwordForm.errors.current_password"
                                class="text-xs text-destructive"
                            >
                                {{ passwordForm.errors.current_password }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Nueva contraseña</Label>
                            <Input
                                v-model="passwordForm.password"
                                type="password"
                                placeholder="••••••••"
                                :class="{
                                    'border-destructive':
                                        passwordForm.errors.password,
                                }"
                            />
                            <p
                                v-if="passwordForm.errors.password"
                                class="text-xs text-destructive"
                            >
                                {{ passwordForm.errors.password }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Confirmar contraseña</Label>
                            <Input
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                placeholder="••••••••"
                                :class="{
                                    'border-destructive':
                                        passwordForm.errors
                                            .password_confirmation,
                                }"
                            />
                            <p
                                v-if="passwordForm.errors.password_confirmation"
                                class="text-xs text-destructive"
                            >
                                {{ passwordForm.errors.password_confirmation }}
                            </p>
                        </div>
                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                :disabled="passwordForm.processing"
                                >Actualizar contraseña</Button
                            >
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
