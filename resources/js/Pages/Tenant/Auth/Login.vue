<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Building2 } from "lucide-vue-next";

defineProps<{
    tenant: { id: string; name: string };
}>();

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

function submit() {
    form.post(route("tenant.login.submit"), {
        onFinish: () => form.reset("password"),
    });
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-background">
        <Card class="w-full max-w-md">
            <CardHeader class="space-y-1">
                <div class="flex items-center justify-center gap-2 mb-2">
                    <div
                        class="flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                    >
                        <Building2 class="size-5" />
                    </div>
                </div>
                <CardTitle class="text-2xl font-bold text-center">
                    {{ tenant.name }}
                </CardTitle>
                <CardDescription class="text-center">
                    Ingresa tus credenciales para acceder al sistema
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="email">Correo electrónico</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            placeholder="correo@ejemplo.com"
                            :class="{ 'border-destructive': form.errors.email }"
                            autofocus
                            autocomplete="username"
                        />
                        <p
                            v-if="form.errors.email"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="password">Contraseña</Label>
                        <Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            placeholder="••••••••"
                            :class="{
                                'border-destructive': form.errors.password,
                            }"
                            autocomplete="current-password"
                        />
                        <p
                            v-if="form.errors.password"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.password }}
                        </p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <input
                            id="remember"
                            v-model="form.remember"
                            type="checkbox"
                            class="rounded border-border"
                        />
                        <Label
                            for="remember"
                            class="text-sm font-normal cursor-pointer"
                        >
                            Recordarme
                        </Label>
                    </div>
                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? "Ingresando..." : "Ingresar" }}
                    </Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
