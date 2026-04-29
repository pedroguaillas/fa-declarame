<script setup lang="ts">
import FormMessage from "@/components/Shared/FormMessage.vue";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";

interface Props {
    modelValue: string | null | undefined;
    label?: string;
    id?: string;
    error?: string;
    required?: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    "update:modelValue": [value: string];
}>();

const handleInput = (val: string | number) => {
    emit("update:modelValue", String(val));
};
</script>

<template>
    <div class="space-y-2">
        <Label
            v-if="label"
            :for="id"
            :class="{ 'text-destructive': error }"
            class="text-xs font-bold tracking-tight uppercase"
        >
            {{ label }}
            <span v-if="required" class="text-destructive ml-0.5">*</span>
        </Label>

        <Textarea
            :id="id"
            :model-value="modelValue ?? ''"
            @update:model-value="handleInput"
            v-bind="$attrs"
            :class="[
                'transition-colors',
                error
                    ? 'border-destructive ring-destructive/20 focus-visible:ring-destructive'
                    : '',
            ]"
        />

        <FormMessage :message="error" variant="error" />
    </div>
</template>
