<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";
import { Loader2 } from "lucide-vue-next";
import { computed } from "vue";

interface Props {
    open: boolean;
    title?: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: "destructive" | "default" | "warning";
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    title: "¿Estás seguro?",
    description: "Esta acción no se puede deshacer.",
    confirmLabel: "Confirmar",
    cancelLabel: "Cancelar",
    variant: "destructive",
    loading: false,
});

const emit = defineEmits(["update:open", "confirm", "cancel"]);

const variantClasses = computed(() => {
    return {
        destructive:
            "bg-destructive text-destructive-foreground hover:bg-destructive/90",
        default: "bg-primary text-primary-foreground hover:bg-primary/90",
        warning: "bg-orange-500 text-white hover:bg-orange-600",
    }[props.variant];
});
</script>

<template>
    <AlertDialog :open="open" @update:open="emit('update:open', $event)">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle class="font-black tracking-tight uppercase">
                    {{ title }}
                </AlertDialogTitle>
                <AlertDialogDescription class="text-sm">
                    <slot name="description">
                        {{ description }}
                    </slot>
                </AlertDialogDescription>
            </AlertDialogHeader>

            <AlertDialogFooter>
                <AlertDialogCancel
                    class="cursor-pointer"
                    @click="emit('cancel')"
                    :disabled="loading"
                >
                    {{ cancelLabel }}
                </AlertDialogCancel>

                <Button
                    type="button"
                    class="cursor-pointer"
                    :class="variantClasses"
                    :disabled="loading"
                    @click="emit('confirm')"
                >
                    <Loader2 v-if="loading" class="size-4 animate-spin" />
                    {{ confirmLabel }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
