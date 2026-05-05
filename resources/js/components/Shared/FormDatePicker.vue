<script setup lang="ts">
import { APP_LOCALE } from "@/lib/config";
import {
    type DateValue,
    getLocalTimeZone,
    parseDate,
} from "@internationalized/date";
import { CalendarIcon } from "lucide-vue-next";
import { onMounted, ref, watch, computed } from "vue";

import FormMessage from "@/components/Shared/FormMessage.vue";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Label } from "@/components/ui/label";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";

interface Props {
    modelValue: string | null | undefined;
    mode?: "date" | "datetime";
    label?: string;
    id?: string;
    error?: string;
    required?: boolean;
    placeholder?: string;
    defaultNow?: boolean;
    maxValue?: DateValue;
}

const props = withDefaults(defineProps<Props>(), {
    mode: "date",
});

const emit = defineEmits<{
    "update:modelValue": [value: string | null];
}>();

const isPopoverOpen = ref(false);
const internalDate = ref<DateValue | undefined>();
const time = ref("00:00");

const isDateTime = computed(() => props.mode === "datetime");

const initializeDate = () => {
    if (props.modelValue) {
        try {
            if (isDateTime.value) {
                const [datePart, timePart] = props.modelValue.split("T");
                internalDate.value = parseDate(datePart);
                time.value = timePart?.slice(0, 5) ?? "00:00";
            } else {
                internalDate.value = parseDate(props.modelValue);
            }
        } catch (e) {
            console.error("Error parseando fecha:", e);
            internalDate.value = undefined;
        }
    } else if (props.defaultNow) {
        const now = new Date();

        const yyyy = now.getFullYear();
        const mm = String(now.getMonth() + 1).padStart(2, "0");
        const dd = String(now.getDate()).padStart(2, "0");
        const hh = String(now.getHours()).padStart(2, "0");
        const min = String(now.getMinutes()).padStart(2, "0");

        if (isDateTime.value) {
            emit("update:modelValue", `${yyyy}-${mm}-${dd}T${hh}:${min}`);
            internalDate.value = parseDate(`${yyyy}-${mm}-${dd}`);
            time.value = `${hh}:${min}`;
        } else {
            emit("update:modelValue", `${yyyy}-${mm}-${dd}`);
            internalDate.value = parseDate(`${yyyy}-${mm}-${dd}`);
        }
    }
};

onMounted(initializeDate);

watch(
    () => props.modelValue,
    (newVal) => {
        if (!newVal) {
            internalDate.value = undefined;
            time.value = "00:00";
            return;
        }

        try {
            if (isDateTime.value) {
                const [datePart, timePart] = newVal.split("T");
                internalDate.value = parseDate(datePart);
                time.value = timePart?.slice(0, 5) ?? "00:00";
            } else {
                internalDate.value = parseDate(newVal);
            }
        } catch (e) {
            console.error(e);
        }
    },
);

const emitValue = () => {
    if (!internalDate.value) {
        emit("update:modelValue", null);
        return;
    }

    const dateStr = internalDate.value.toString();

    if (isDateTime.value) {
        emit("update:modelValue", `${dateStr}T${time.value}`);
    } else {
        emit("update:modelValue", dateStr);
    }
};

const handleSelect = (date: DateValue | undefined) => {
    internalDate.value = date;
    emitValue();
};

const handleTimeChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    time.value = target.value;
    emitValue();
};
</script>

<template>
    <div class="flex flex-col space-y-2">
        <Label v-if="label" :for="id" class="text-xs font-bold uppercase">
            {{ label }}
            <span v-if="required" class="text-destructive">*</span>
        </Label>

        <Popover v-model:open="isPopoverOpen">
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="outline"
                    :id="id"
                    class="w-full justify-between text-left"
                >
                    <span>
                        {{
                            internalDate
                                ? internalDate
                                      .toDate(getLocalTimeZone())
                                      .toLocaleDateString("es-EC") +
                                  (isDateTime ? " " + time : "")
                                : placeholder ||
                                  (isDateTime
                                      ? "Seleccionar fecha y hora"
                                      : "Seleccionar fecha")
                        }}
                    </span>

                    <CalendarIcon class="size-4 opacity-50" />
                </Button>
            </PopoverTrigger>

            <PopoverContent class="w-auto p-3 space-y-3">
                <Calendar
                    :locale="APP_LOCALE"
                    :model-value="internalDate"
                    :max-value="maxValue"
                    @update:model-value="handleSelect"
                    layout="month-and-year"
                />

                <div v-if="isDateTime" class="flex flex-col gap-1">
                    <Label class="text-xs">Hora</Label>
                    <input
                        type="time"
                        :value="time"
                        @input="handleTimeChange"
                        class="border rounded px-2 py-1"
                    />
                </div>
            </PopoverContent>
        </Popover>

        <FormMessage :message="error" variant="error" />
    </div>
</template>
