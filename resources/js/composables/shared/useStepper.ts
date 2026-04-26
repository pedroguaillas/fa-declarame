import { computed, ref } from 'vue';

export interface Step {
  value: number;
  title: string;
  desc?: string;
  icon?: any;
}

export function useStepper(steps: Step[], initialStep = 1) {
  const currentStep = ref(initialStep);
  const totalSteps = steps.length;

  const isFirstStep = computed(() => currentStep.value === 1);
  const isLastStep = computed(() => currentStep.value === totalSteps);

  const nextStep = () => {
    if (currentStep.value < totalSteps) currentStep.value++;
  };

  const prevStep = () => {
    if (currentStep.value > 1) currentStep.value--;
  };

  return {
    currentStep,
    isFirstStep,
    isLastStep,
    nextStep,
    prevStep,
    steps,
  };
}
