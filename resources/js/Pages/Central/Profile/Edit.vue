<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import type { User } from "@/types";
import AppLayout from "@/layouts/AppLayout.vue";
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
import { UserCircle, Lock, ShieldCheck } from "lucide-vue-next";

const props = defineProps<{
    user: User;
}>();

// ── Info personal ──────────────────────────────────────
const infoForm = useForm({
    name: props.user.name,
    email: props.user.email,
});

function updateInfo() {
    infoForm.patch(route("profile.update-info"));
}

// ── Contraseña ─────────────────────────────────────────
const passwordForm = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
});

function updatePassword() {
    passwordForm.patch(route("profile.update-password"), {
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
    <Head title="Mi perfil" />
  <AppLayout>
    <div class="w-full max-w-4xl mx-auto space-y-6 px-4 sm:px-6 lg:px-0">

      <!-- Header -->
      <div class="space-y-1">
        <h1 class="flex items-center gap-2 text-2xl font-bold text-foreground">
          <UserCircle class="size-6" />
          Mi perfil
        </h1>
        <p class="text-sm text-muted-foreground">
          Gestiona tu información personal y contraseña.
        </p>
      </div>

      <!-- Avatar + rol -->
      <Card>
        <CardContent class="py-6">
          <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            
            <!-- Avatar -->
            <div
              class="flex size-16 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground text-xl font-bold"
            >
              {{ getInitials(user.name) }}
            </div>

            <!-- Info -->
            <div class="space-y-1">
              <p class="text-lg font-semibold text-foreground">
                {{ user.name }}
              </p>
              <p class="text-sm text-muted-foreground">
                {{ user.email }}
              </p>
              <div class="flex items-center gap-1 text-xs text-muted-foreground">
                <ShieldCheck class="size-3" />
                {{ user.role.name }}
              </div>
            </div>

          </div>
        </CardContent>
      </Card>

      <!-- Información personal -->
      <Card>
        <CardHeader>
          <CardTitle>Información personal</CardTitle>
          <CardDescription>
            Actualiza tu nombre y correo electrónico.
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form @submit.prevent="updateInfo" class="space-y-5">

            <div class="grid gap-4 sm:grid-cols-2">
              
              <!-- Nombre -->
              <div class="space-y-2">
                <Label for="name">Nombre completo</Label>
                <Input
                  id="name"
                  v-model="infoForm.name"
                  placeholder="Tu nombre"
                  :class="{ 'border-destructive': infoForm.errors.name }"
                />
                <p v-if="infoForm.errors.name" class="text-xs text-destructive">
                  {{ infoForm.errors.name }}
                </p>
              </div>

              <!-- Email -->
              <div class="space-y-2">
                <Label for="email">Correo electrónico</Label>
                <Input
                  id="email"
                  v-model="infoForm.email"
                  type="email"
                  placeholder="correo@ejemplo.com"
                  :class="{ 'border-destructive': infoForm.errors.email }"
                />
                <p v-if="infoForm.errors.email" class="text-xs text-destructive">
                  {{ infoForm.errors.email }}
                </p>
              </div>

            </div>

            <div class="flex justify-end">
              <Button type="submit" :disabled="infoForm.processing">
                Guardar cambios
              </Button>
            </div>

          </form>
        </CardContent>
      </Card>

      <!-- Cambiar contraseña -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Lock class="size-4" />
            Cambiar contraseña
          </CardTitle>
          <CardDescription>
            Usa una contraseña segura de al menos 8 caracteres.
          </CardDescription>
        </CardHeader>

        <CardContent>
          <form @submit.prevent="updatePassword" class="space-y-5">

            <div class="grid gap-4 sm:grid-cols-2">

              <!-- Actual -->
              <div class="space-y-2 sm:col-span-2">
                <Label for="current_password">Contraseña actual</Label>
                <Input
                  id="current_password"
                  v-model="passwordForm.current_password"
                  type="password"
                  placeholder="••••••••"
                  :class="{
                    'border-destructive':
                      passwordForm.errors.current_password,
                  }"
                />
                <p v-if="passwordForm.errors.current_password" class="text-xs text-destructive">
                  {{ passwordForm.errors.current_password }}
                </p>
              </div>

              <!-- Nueva -->
              <div class="space-y-2">
                <Label for="password">Nueva contraseña</Label>
                <Input
                  id="password"
                  v-model="passwordForm.password"
                  type="password"
                  placeholder="••••••••"
                  :class="{ 'border-destructive': passwordForm.errors.password }"
                />
                <p v-if="passwordForm.errors.password" class="text-xs text-destructive">
                  {{ passwordForm.errors.password }}
                </p>
              </div>

              <!-- Confirmación -->
              <div class="space-y-2">
                <Label for="password_confirmation">
                  Confirmar nueva contraseña
                </Label>
                <Input
                  id="password_confirmation"
                  v-model="passwordForm.password_confirmation"
                  type="password"
                  placeholder="••••••••"
                  :class="{
                    'border-destructive':
                      passwordForm.errors.password_confirmation,
                  }"
                />
                <p v-if="passwordForm.errors.password_confirmation" class="text-xs text-destructive">
                  {{ passwordForm.errors.password_confirmation }}
                </p>
              </div>

            </div>

            <div class="flex justify-end">
              <Button type="submit" :disabled="passwordForm.processing">
                Actualizar contraseña
              </Button>
            </div>

          </form>
        </CardContent>
      </Card>

    </div>
  </AppLayout>
</template>