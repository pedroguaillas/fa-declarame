<script setup lang="ts">
import { Loader2, ShieldAlert, ShieldCheck } from "lucide-vue-next";

export interface CertInfo {
    subject: string;
    valid_from: string;
    valid_to: string;
    is_expired: boolean;
    days_left: number;
}

defineProps<{
    checking?: boolean;
    error?: string | null;
    info?: CertInfo | null;
}>();
</script>

<template>
    <!-- Validando -->
    <div
        v-if="checking"
        class="border-border bg-muted/20 text-muted-foreground flex items-center gap-2.5 rounded-lg border px-3 py-2.5 text-sm"
    >
        <Loader2 class="h-4 w-4 shrink-0 animate-spin" />
        <span>Validando certificado...</span>
    </div>

    <!-- Error de clave/archivo -->
    <div
        v-else-if="error"
        class="flex items-start gap-2.5 rounded-lg border border-red-200/60 bg-red-50/50 px-3 py-2.5 text-sm dark:border-red-500/20 dark:bg-red-500/5"
    >
        <ShieldAlert class="mt-0.5 h-4 w-4 shrink-0 text-red-500" />
        <span class="text-red-700 dark:text-red-400">{{ error }}</span>
    </div>

    <!-- Info del certificado -->
    <div
        v-else-if="info"
        class="rounded-lg border px-3 py-2.5 text-sm transition-all duration-200"
        :class="
            info.is_expired
                ? 'border-red-200/60 bg-red-50/50 dark:border-red-500/20 dark:bg-red-500/5'
                : info.days_left <= 30
                  ? 'border-yellow-200/60 bg-yellow-50/50 dark:border-yellow-500/20 dark:bg-yellow-500/5'
                  : 'border-emerald-200/60 bg-emerald-50/50 dark:border-emerald-500/20 dark:bg-emerald-500/5'
        "
    >
        <!-- Fila principal: icono + nombre + badge -->
        <div class="flex flex-wrap items-center gap-2">
            <ShieldCheck
                class="h-4 w-4 shrink-0"
                :class="
                    info.is_expired
                        ? 'text-red-500'
                        : info.days_left <= 30
                          ? 'text-yellow-500'
                          : 'text-emerald-500'
                "
            />

            <span
                class="min-w-0 flex-1 truncate font-medium"
                :class="
                    info.is_expired
                        ? 'text-red-700 dark:text-red-400'
                        : info.days_left <= 30
                          ? 'text-yellow-700 dark:text-yellow-400'
                          : 'text-emerald-700 dark:text-emerald-400'
                "
            >
                {{ info.subject }}
            </span>

            <span
                class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                :class="
                    info.is_expired
                        ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400'
                        : info.days_left <= 30
                          ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-400'
                          : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'
                "
            >
                {{
                    info.is_expired
                        ? "Expirado"
                        : `${info.days_left}d restantes`
                }}
            </span>
        </div>

        <!-- Vigencia -->
        <div
            class="text-muted-foreground mt-1.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 pl-6 text-xs"
        >
            <span>Válido</span>
            <span class="font-medium">{{ info.valid_from }}</span>
            <span>→</span>
            <span class="font-medium">{{ info.valid_to }}</span>
        </div>
    </div>
</template>
