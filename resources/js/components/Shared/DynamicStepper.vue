<script setup lang="ts">
import { Check } from "lucide-vue-next";
import {
    Stepper,
    StepperDescription,
    StepperIndicator,
    StepperItem,
    StepperSeparator,
    StepperTitle,
    StepperTrigger,
} from "@/components/ui/stepper";

defineProps<{
    steps: any[];
    modelValue: number;
}>();

const emit = defineEmits(["update:modelValue"]);
</script>

<template>
    <Stepper
        :model-value="modelValue"
        @update:model-value="emit('update:modelValue', $event)"
        class="flex w-full items-start gap-2"
    >
        <StepperItem
            v-for="step in steps"
            :key="step.value"
            :step="step.value"
            class="relative flex w-full flex-col items-center justify-center"
        >
            <StepperTrigger as-child>
                <div class="flex flex-col items-center gap-2">
                    <StepperIndicator>
                        <Check v-if="modelValue > step.value" class="size-4" />
                        <component v-else :is="step.icon" class="size-4" />
                    </StepperIndicator>
                    <div class="flex flex-col items-center text-center">
                        <StepperTitle
                            class="text-xs font-bold uppercase tracking-wider"
                        >
                            {{ step.title }}
                        </StepperTitle>
                        <StepperDescription class="hidden text-[10px] sm:block">
                            {{ step.desc }}
                        </StepperDescription>
                    </div>
                </div>
            </StepperTrigger>

            <StepperSeparator
                v-if="step.value !== steps.length"
                class="absolute left-[calc(50%+20px)] right-[calc(-50%+10px)] top-5 block h-0.5 shrink-0 rounded-full bg-muted group-data-[state=completed]:bg-primary"
            />
        </StepperItem>
    </Stepper>
</template>
