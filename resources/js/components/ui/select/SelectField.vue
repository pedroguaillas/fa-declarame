<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '.'

export interface SelectOption {
    value: string | number;
    label: string;
}

const props = defineProps<{
    modelValue?: string | number | null;
    options: SelectOption[];
    placeholder?: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string | number];
}>();
</script>

<template>
    <Select
        :model-value="props.modelValue !== null && props.modelValue !== undefined ? String(props.modelValue) : undefined"
        :disabled="props.disabled"
        @update:model-value="(v) => v != null && emit('update:modelValue', v as string | number)"
    >
        <SelectTrigger>
            <SelectValue :placeholder="props.placeholder ?? 'Seleccionar...'" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem
                v-for="option in props.options"
                :key="option.value"
                :value="String(option.value)"
            >
                {{ option.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
