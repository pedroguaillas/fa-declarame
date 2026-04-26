<script setup lang="ts">
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import FormMessage from "@/components/Shared/FormMessage.vue";

interface Option {
    id: string | number;
    label: string;
}

interface Props {
    modelValue: string | number | null | undefined;
    options: Option[];
    label?: string;
    placeholder?: string;
    error?: string;
    required?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: "Seleccionar",
    required: false,
    disabled: false,
});

defineEmits<{
    "update:modelValue": [value: string | number];
}>();
</script>

<template>
    <div class="space-y-2">
        <Label
            v-if="label"
            :class="{ 'text-destructive': error }"
            class="px-0.5 text-xs font-bold tracking-tight uppercase"
        >
            {{ label }}
            <span v-if="required" class="text-destructive ml-0.5">*</span>
        </Label>

        <Select
            :model-value="modelValue?.toString()"
            @update:model-value="
                (value) => {
                    if (value == null || typeof value !== 'string') return;
                    $emit(
                        'update:modelValue',
                        isNaN(Number(value)) ? value : Number(value),
                    );
                }
            "
            :disabled="disabled"
        >
            <SelectTrigger
                :class="[
                    'w-full transition-colors',
                    error
                        ? 'border-destructive ring-destructive/20 focus:ring-destructive'
                        : '',
                    !modelValue ? '[&>span]:text-muted-foreground' : '',
                ]"
            >
                <SelectValue :placeholder="placeholder" />
            </SelectTrigger>

            <SelectContent>
                <SelectItem
                    v-for="option in options"
                    :key="option.id"
                    :value="String(option.id)"
                >
                    {{ option.label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <FormMessage :message="error" />
    </div>
</template>
