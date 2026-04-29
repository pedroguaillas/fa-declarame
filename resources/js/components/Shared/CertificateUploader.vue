<script setup lang="ts">
import { FileKey2, X } from "lucide-vue-next";
import { ref } from "vue";

const props = defineProps<{
    modelValue: File | null;
    initialPath?: string | null;
    error?: string;
}>();

const emit = defineEmits<{
    "update:modelValue": [file: File | null];
    remove: [];
}>();

const input = ref<HTMLInputElement | null>(null);

const fileName = ref<string | null>(
    props.modelValue?.name ?? props.initialPath?.split("/").pop() ?? null,
);

function onChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    fileName.value = file.name;
    emit("update:modelValue", file);
}

function remove(event: MouseEvent) {
    event.stopPropagation();
    fileName.value = null;
    emit("update:modelValue", null);
    emit("remove");
    if (input.value) input.value.value = "";
}
</script>

<template>
    <div class="space-y-1.5">
        <label class="text-sm font-medium">
            Certificado
            <span class="text-muted-foreground font-normal">(.p12)</span>
        </label>

        <div
            class="group relative cursor-pointer rounded-lg border px-3 py-2.5 transition-all duration-200"
            :class="
                fileName
                    ? 'border-emerald-200/60 bg-emerald-50/50 hover:border-emerald-300 hover:bg-emerald-100/40 dark:border-emerald-500/20 dark:bg-emerald-500/5 dark:hover:border-emerald-500/40 dark:hover:bg-emerald-500/10'
                    : 'border-border bg-muted/10 hover:border-primary/40 hover:bg-muted/30 border-dashed'
            "
            @click="input?.click()"
        >
            <div class="flex items-center gap-3">
                <!-- Icono: estado vacío vs con archivo -->
                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md transition-all duration-200"
                    :class="
                        fileName
                            ? 'bg-emerald-100 dark:bg-emerald-500/15'
                            : 'bg-muted group-hover:bg-primary/10'
                    "
                >
                    <!-- Sin archivo: muestra flecha de subir, en hover se anima -->
                    <template v-if="!fileName">
                        <svg
                            class="text-muted-foreground group-hover:text-primary h-4 w-4 transition-all duration-200 group-hover:-translate-y-0.5"
                            viewBox="0 0 16 16"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                d="M8 11V3M5 6l3-3 3 3"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                            <path d="M3 13h10" stroke-linecap="round" />
                        </svg>
                    </template>

                    <!-- Con archivo: siempre muestra el ícono de certificado -->
                    <template v-else>
                        <FileKey2
                            class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                        />
                    </template>
                </div>

                <!-- Texto -->
                <div class="min-w-0 flex-1">
                    <p
                        class="truncate text-sm transition-colors duration-200"
                        :class="
                            fileName
                                ? 'font-medium text-emerald-700 dark:text-emerald-400'
                                : 'text-muted-foreground group-hover:text-foreground'
                        "
                    >
                        {{ fileName ?? "Haz clic para seleccionar archivo" }}
                    </p>
                    <p class="text-muted-foreground/60 text-xs">
                        {{
                            fileName
                                ? modelValue
                                    ? "Nuevo · clic para cambiar"
                                    : "Guardado · clic para cambiar"
                                : "formato permitido:  [ .p12 ]"
                        }}
                    </p>
                </div>

                <!-- X solo cuando hay archivo -->
                <button
                    v-if="fileName"
                    type="button"
                    @click="remove"
                    class="text-muted-foreground/40 flex h-7 w-7 shrink-0 cursor-pointer items-center justify-center rounded-full transition-all duration-150 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-500/10"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>

        <input
            ref="input"
            type="file"
            accept=".p12,.pfx"
            class="hidden"
            @change="onChange"
        />
        <p v-if="error" class="text-destructive text-xs">{{ error }}</p>
    </div>
</template>
