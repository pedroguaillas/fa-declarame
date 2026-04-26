<script setup lang="ts">
import FormMessage from "@/components/Shared/FormMessage.vue";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import { ChevronDown, Search } from "lucide-vue-next";

interface Props {
    label: string;
    placeholder?: string;
    selectedLabel?: string | null;
    error?: string;
    required?: boolean;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: "Seleccionar...",
});

const emit = defineEmits<{
    click: [];
}>();
</script>

<template>
    <div :class="cn('space-y-2', props.class)">
        <Label
            class="text-xs font-bold tracking-tight uppercase"
            :class="error ? 'text-destructive' : ''"
        >
            {{ label }}
            <span v-if="required" class="text-destructive ml-0.5">*</span>
        </Label>

        <Button
            type="button"
            variant="outline"
            class="w-full justify-start bg-transparent text-xs font-bold tracking-tight uppercase"
            :class="error ? 'border-destructive!' : ''"
            @click="emit('click')"
        >
            <Search class="size-4 shrink-0 opacity-50" />
            <span
                class="min-w-0 truncate"
                :class="
                    !selectedLabel
                        ? 'text-muted-foreground font-normal normal-case'
                        : ''
                "
            >
                {{ selectedLabel || placeholder }}
            </span>
            <ChevronDown class="ml-auto size-4 shrink-0 opacity-50" />
        </Button>

        <FormMessage :message="error" variant="error" />
    </div>
</template>
