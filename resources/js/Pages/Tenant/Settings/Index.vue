<script setup lang="ts">
import TenantLayout from "@/layouts/TenantLayout.vue";
import { Button } from "@/components/ui/button";
import { Head, useForm } from "@inertiajs/vue3";
import { ref, computed } from "vue";

const props = defineProps<{
    logoUrl: string | null;
}>();

const form = useForm({
    logo: null as File | null,
    remove_logo: false,
});

const localPreview = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const showLogo = computed(() => !form.remove_logo && (localPreview.value || props.logoUrl));
const previewSrc = computed(() => localPreview.value ?? props.logoUrl);

function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.logo = file;
    form.remove_logo = false;
    if (file) {
        const reader = new FileReader();
        reader.onload = (ev) => (localPreview.value = ev.target?.result as string);
        reader.readAsDataURL(file);
    } else {
        localPreview.value = null;
    }
}

function removeLogo() {
    form.logo = null;
    form.remove_logo = true;
    localPreview.value = null;
    if (fileInput.value) fileInput.value.value = "";
}

function submit() {
    form.post(route("tenant.settings.update"), {
        forceFormData: true,
    });
}
</script>

<template>
    <Head title="Ajustes" />

    <TenantLayout>
        <div class="mb-6">
            <h1 class="text-foreground text-2xl font-semibold">Ajustes</h1>
            <p class="text-muted-foreground mt-1 text-sm">Configuración general de la firma contadora</p>
        </div>

        <div class="border-border bg-card max-w-lg rounded-lg border p-6">
            <h2 class="text-foreground mb-4 text-sm font-semibold">Logo de la firma</h2>
            <p class="text-muted-foreground mb-4 text-xs">Se mostrará en la cabecera de los reportes Excel.</p>

            <!-- Preview -->
            <div v-if="showLogo" class="mb-4">
                <img
                    :src="previewSrc!"
                    alt="Logo"
                    class="border-border h-20 rounded-md border object-contain p-2"
                />
            </div>
            <div v-else class="border-border mb-4 flex h-20 w-40 items-center justify-center rounded-md border border-dashed">
                <span class="text-muted-foreground text-xs">Sin logo</span>
            </div>

            <!-- Input -->
            <div class="flex flex-wrap items-center gap-3">
                <label class="cursor-pointer">
                    <span class="border-border bg-background text-foreground hover:bg-accent rounded-md border px-3 py-1.5 text-sm font-medium transition-colors">
                        {{ showLogo ? "Cambiar logo" : "Subir logo" }}
                    </span>
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        @change="onFileChange"
                    />
                </label>

                <button
                    v-if="showLogo"
                    type="button"
                    class="text-destructive hover:text-destructive/80 text-sm transition-colors"
                    @click="removeLogo"
                >
                    Eliminar logo
                </button>
            </div>

            <p v-if="form.errors.logo" class="text-destructive mt-2 text-xs">{{ form.errors.logo }}</p>

            <div class="mt-6 flex justify-end">
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? "Guardando…" : "Guardar" }}
                </Button>
            </div>
        </div>
    </TenantLayout>
</template>
