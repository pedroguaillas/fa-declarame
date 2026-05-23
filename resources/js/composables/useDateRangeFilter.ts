import { computed, ref } from "vue";

const MIN_DATE = "2015-01-01";
const today = new Date().toISOString().split("T")[0];

const yearOf = (d: string) => d.substring(0, 4);

export function useDateRangeFilter(initialStart: string | null, initialEnd: string | null) {
    const startDate = ref(initialStart ?? "");
    const endDate = ref(initialEnd ?? "");

    const dateRangeError = computed(() => {
        if (startDate.value && endDate.value && yearOf(startDate.value) !== yearOf(endDate.value)) {
            return "Las fechas deben ser del mismo año.";
        }
        return null;
    });

    return {
        startDate,
        endDate,
        minDate: MIN_DATE,
        maxDate: today,
        dateRangeError,
    };
}
