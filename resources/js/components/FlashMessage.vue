<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { computed, watch, ref } from "vue";
import type { PageProps } from "@/types";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { CheckCircle2, XCircle } from "lucide-vue-next";

const page = usePage<PageProps>();
const visible = ref(false);
const flash = computed(() => page.props.flash);

watch(
    flash,
    (val) => {
        if (val.success || val.error) {
            visible.value = true;
            setTimeout(() => (visible.value = false), 4000);
        }
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
    >
        <div v-if="visible" class="fixed top-4 right-4 z-50 w-96">
            <Alert
                v-if="flash.success"
                class="border-green-500 bg-green-50 dark:bg-green-950"
            >
                <CheckCircle2 class="size-4 text-green-600" />
                <AlertDescription class="text-green-700 dark:text-green-300">
                    {{ flash.success }}
                </AlertDescription>
            </Alert>
            <Alert v-if="flash.error" variant="destructive">
                <XCircle class="size-4" />
                <AlertDescription>{{ flash.error }}</AlertDescription>
            </Alert>
        </div>
    </Transition>
</template>
