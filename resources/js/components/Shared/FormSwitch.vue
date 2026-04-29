<script setup lang="ts">
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import FormMessage from "@/components/Shared/FormMessage.vue";

interface Props {
    modelValue: boolean;
    label: string;
    description?: string;
    id?: string;
    error?: string;
    required?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    description: "Activa o desactiva para continuar.",
    id: () => `switch-${Math.random().toString(36).slice(2, 9)}`,
    required: false,
});

defineEmits<{
    "update:modelValue": [value: boolean];
}>();
</script>

<template>
    <div class="flex h-full min-h-0 flex-col space-y-2">
        <Label
            v-if="label"
            :for="id"
            :class="{ 'text-destructive': error }"
            class="block shrink-0 px-0.5 text-xs font-bold tracking-tight uppercase"
        >
            {{ label }}
            <span v-if="required" class="text-destructive ml-0.5">*</span>
        </Label>

        <Label
            :for="id"
            class="hover:bg-muted/30 bg-muted/20 flex cursor-pointer items-center justify-between rounded-md border px-3 py-2 shadow-sm transition-all"
            :class="[
                error
                    ? 'border-destructive ring-destructive ring-1'
                    : 'hover:border-accent-foreground/20',
            ]"
        >
            <div
                class="mr-2 flex flex-col justify-center overflow-hidden font-normal select-none"
            >
                <p
                    class="text-muted-foreground text-[12px] leading-tight font-medium"
                >
                    {{ description }}
                </p>
            </div>

            <div class="flex shrink-0 items-center">
                <Switch
                    :id="id"
                    :model-value="modelValue"
                    @update:model-value="$emit('update:modelValue', $event)"
                    class="data-[state=checked]:bg-primary scale-90"
                    :disabled="disabled"
                />
            </div>
        </Label>

        <FormMessage :message="error" class="shrink-0" />
    </div>
</template>
