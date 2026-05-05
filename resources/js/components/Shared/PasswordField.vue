<script setup lang="ts">
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import FormMessage from "@/components/Shared/FormMessage.vue";
import { Eye, EyeOff } from "lucide-vue-next";
import { ref } from "vue";

interface Props {
    modelValue: string | null;
    label?: string;
    id?: string;
    error?: string;
    required?: boolean;
    placeholder?: string;
}

defineOptions({ inheritAttrs: false });
defineProps<Props>();

const emit = defineEmits<{
    "update:modelValue": [value: string | number];
}>();
const handleInput = (val: string | number) => {
    emit("update:modelValue", val);
};

const showPassword = ref(false);
</script>

<template>
    <div class="space-y-2">
        <Label
            v-if="label"
            :for="id"
            :class="{ 'text-destructive': error }"
            class="flex items-center gap-1 text-xs font-bold tracking-tight uppercase"
        >
            {{ label }}
            <span v-if="required" class="text-destructive">*</span>
        </Label>

        <div class="group relative">
            <Input
                v-bind="$attrs"
                :id="id"
                :placeholder="placeholder"
                :type="showPassword ? 'text' : 'password'"
                :value="modelValue"
                @update:model-value="handleInput"
                :class="[
                    'pr-10 transition-colors',
                    error
                        ? 'border-destructive ring-destructive/20 focus-visible:ring-destructive'
                        : '',
                ]"
            />

            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="text-muted-foreground hover:text-foreground absolute top-0 right-0 h-full w-10 px-3 py-2 transition-colors hover:bg-transparent cursor-pointer"
                @click="showPassword = !showPassword"
                tabindex="-1"
            >
                <component :is="showPassword ? EyeOff : Eye" class="size-4" />
            </Button>
        </div>

        <FormMessage :message="error" />
    </div>
</template>
