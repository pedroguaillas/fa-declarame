<script setup lang="ts">
import { AlertCircle, CheckCircle2 } from "lucide-vue-next";
import { computed } from "vue";

interface Props {
    message?: string;
    variant?: "error" | "success" | "info";
}

const props = withDefaults(defineProps<Props>(), {
    variant: "error",
});

const config = computed(() => {
    const variants = {
        error: {
            class: "text-destructive",
            icon: AlertCircle,
        },
        success: {
            class: "text-emerald-600",
            icon: CheckCircle2,
        },
        info: {
            class: "text-blue-600",
            icon: AlertCircle,
        },
    };
    return variants[props.variant];
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="transform -translate-y-1 opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="message"
            class="flex items-center gap-1.5 px-0.5"
            :class="config.class"
        >
            <component
                :is="config.icon"
                class="size-3.5 shrink-0"
                aria-hidden="true"
            />

            <span class="text-[11px] leading-none font-semibold tracking-tight">
                {{ message }}
            </span>
        </div>
    </Transition>
</template>
