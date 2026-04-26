<script setup lang="ts">
import FormMessage from "@/components/Shared/FormMessage.vue";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useTemplateRef } from "vue";

interface Props {
    modelValue: string | number | null | undefined;
    label?: string;
    id?: string;
    error?: string;
    required?: boolean;
    readonly?: boolean;
    mask?: "document";
    pattern?: RegExp;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    "update:modelValue": [value: string | number];
}>();

const inputRef = useTemplateRef<{ $el: HTMLInputElement }>("inputRef");

const applyMask = (val: string): string => {
    if (props.mask === "document") {
        const digits = val.replace(/\D/g, "").slice(0, 15);
        const p1 = digits.slice(0, 3);
        const p2 = digits.slice(3, 6);
        const p3 = digits.slice(6, 15);
        if (digits.length <= 3) return p1;
        if (digits.length <= 6) return `${p1}-${p2}`;
        return `${p1}-${p2}-${p3}`;
    }
    return val;
};

const handleInput = (val: string | number) => {
    if (props.readonly) return;

    if (props.mask) {
        const masked = applyMask(String(val));
        emit("update:modelValue", masked);
        requestAnimationFrame(() => {
            const el = inputRef.value?.$el;
            if (el && el.value !== masked) el.value = masked;
        });
        return;
    }

    if (props.pattern) {
        const strVal = String(val);
        // Si el valor completo no cumple el patrón, revertir al valor anterior
        if (!props.pattern.test(strVal) && strVal !== "") {
            requestAnimationFrame(() => {
                const el = inputRef.value?.$el;
                if (el) el.value = String(props.modelValue ?? "");
            });
            return;
        }
    }

    emit("update:modelValue", val);
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

        <Input
            ref="inputRef"
            :id="id"
            :model-value="modelValue ?? ''"
            :readonly="readonly"
            @update:model-value="handleInput"
            v-bind="$attrs"
            :class="[
                'transition-colors',
                error
                    ? 'border-destructive ring-destructive/20 focus-visible:ring-destructive'
                    : '',
                readonly
                    ? 'bg-muted/50 text-muted-foreground focus-visible:border-input cursor-default focus-visible:ring-0 focus-visible:ring-offset-0'
                    : '',
            ]"
        />

        <FormMessage :message="error" variant="error" />
    </div>
</template>
