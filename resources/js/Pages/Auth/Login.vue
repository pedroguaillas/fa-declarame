<script setup lang="ts">
import { Head, Link, useForm } from "@inertiajs/vue3";
import { ref } from "vue";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox"; // Usando el componente de shadcn

import { Check, Eye, EyeOff } from "lucide-vue-next";
import FormField from "@/components/Shared/FormField.vue";
import PasswordField from "@/components/Shared/PasswordField.vue";

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    username: "",
    password: "",
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route("login"), {
        onFinish: () => form.reset("password"),
    });
};
</script>

<template>
    <Head title="Iniciar sesión" />

    <div class="flex min-h-screen bg-background text-foreground">
        <div class="relative hidden lg:flex lg:w-1/2 flex-col justify-between p-12 overflow-hidden border-r border-emerald-900/20 bg-emerald-950/10">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -top-40 -left-20 h-125 w-125 rounded-full bg-emerald-500/10 blur-[120px]"></div>
                <div class="absolute -bottom-20 right-0 h-100 w-100 rounded-full bg-emerald-700/8 blur-[120px]"></div>
            </div>

            <Link href="/" class="relative flex items-center gap-2 group">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-lg shadow-emerald-500/40 ring-2 ring-emerald-400/20 transition-transform group-hover:scale-105"
                >
                    <span class="text-xl font-bold">D</span>
                </div>
                <span class="text-xl font-bold tracking-tight">Declárame</span>
            </Link>

            <div class="relative max-w-lg">
                <div class="mb-8 h-1 w-12 rounded-full bg-emerald-500"></div>
                <blockquote class="space-y-6">
                    <p class="text-4xl leading-tight font-bold tracking-tight text-foreground">
                        Gestiona tus obligaciones con el SRI desde un solo lugar.
                    </p>
                    <p class="text-muted-foreground text-lg">
                        Compras, ventas y retenciones organizadas automáticamente en un solo lugar. Sin descargas, sin
                        importaciones manuales y sin horas perdidas preparando información tributaria.
                    </p>
                </blockquote>

                <div class="mt-12 space-y-3">
                    <div
                        v-for="item in [
                            'Sincronización directa con el SRI',
                            'Distribución automática de IVA por tarifa',
                            'Retenciones en fuente e IVA actualizadas',
                            'Multi-empresa y multi-cliente',
                        ]"
                        :key="item"
                        class="flex items-center gap-3 rounded-lg border border-emerald-800/40 bg-emerald-900/20 px-4 py-2.5"
                    >
                        <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 ring-1 ring-emerald-500/40">
                            <Check class="h-3.5 w-3.5 text-emerald-400" />
                        </div>
                        <span class="text-sm font-medium text-muted-foreground/90">
                            {{ item }}
                        </span>
                    </div>
                </div>
            </div>

            <p class="relative text-xs text-muted-foreground font-medium">
                © {{ new Date().getFullYear() }} Declárame — Plataforma Tributaria Inteligente
            </p>
        </div>

        <div class="relative flex flex-1 items-center justify-center overflow-hidden px-8 py-12">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -top-20 -right-20 h-80 w-80 rounded-full bg-emerald-500/8 blur-[120px]"></div>
                <div class="absolute -bottom-20 left-0 h-60 w-60 rounded-full bg-emerald-700/6 blur-[100px]"></div>
            </div>
            <div class="w-full max-w-sm space-y-8">
                <div>
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Plataforma Tributaria
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight uppercase">Bienvenido</h1>
                    <p class="text-sm text-muted-foreground mt-2">Ingresa tus credenciales para acceder a tu cuenta</p>
                </div>

                <div
                    v-if="status"
                    class="rounded-md bg-primary/10 p-3 text-sm font-medium text-primary border border-primary/20"
                >
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <FormField
                        id="username"
                        label="Usuario"
                        v-model="form.username"
                        type="text"
                        placeholder="nombre.usuario"
                        :error="form.errors.username"
                        required
                    />

                    <PasswordField
                        label="Contraseña"
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        placeholder="••••••••"
                        :error="form.errors.password"
                        required
                    />

                    <div class="flex items-center space-x-2">
                        <Checkbox id="remember" :model-value="form.remember" />
                        <label
                            for="remember"
                            class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                        >
                            Mantener sesión iniciada
                        </label>
                    </div>

                    <Button type="submit" class="w-full font-semibold" :disabled="form.processing">
                        {{ form.processing ? "Ingresando..." : "Iniciar sesión" }}
                    </Button>
                </form>
            </div>
        </div>
    </div>
</template>
