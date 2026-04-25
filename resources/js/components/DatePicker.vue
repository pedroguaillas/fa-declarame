<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { Ref } from 'vue'
import type { DateValue } from '@internationalized/date'
import { getLocalTimeZone, parseDate } from '@internationalized/date'
import { CalendarIcon } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
import { Label } from '@/components/ui/label'
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover'

const props = defineProps<{
    modelValue: string
    placeholder?: string
    disabled?: boolean
    label?: string
}>()

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

// Normaliza cualquier formato de fecha a yyyy-MM-dd
function toDateString(value: string): string {
    if (!value) return ''
    // Si trae timestamp (ISO 8601 completo), tomar solo la parte de fecha
    return value.split('T')[0]
}

const date = ref(
    props.modelValue ? parseDate(toDateString(props.modelValue)) : null
) as Ref<DateValue | null>

watch(() => props.modelValue, (val) => {
    date.value = val ? parseDate(toDateString(val)) : null
})

const displayValue = computed(() => {
    if (!date.value) return props.placeholder ?? 'Seleccionar fecha'
    return date.value.toDate(getLocalTimeZone()).toLocaleDateString('es-EC', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    })
})

function onSelect(value: DateValue | undefined, close: () => void) {
    if (value) {
        date.value = value
        const d = value.toDate(getLocalTimeZone())
        const yyyy = d.getFullYear()
        const mm = String(d.getMonth() + 1).padStart(2, '0')
        const dd = String(d.getDate()).padStart(2, '0')
        emit('update:modelValue', `${yyyy}-${mm}-${dd}`)
        close()
    }
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <Label v-if="label">{{ label }}</Label>
        <Popover v-slot="{ close }">
            <PopoverTrigger as-child>
                <Button
                    variant="outline"
                    class="w-full justify-start text-left font-normal"
                    :class="{ 'text-muted-foreground': !date }"
                    :disabled="disabled"
                >
                    <CalendarIcon class="mr-2 size-4 shrink-0" />
                    {{ displayValue }}
                </Button>
            </PopoverTrigger>
            <PopoverContent class="w-auto overflow-hidden p-0" align="start">
                <Calendar
                    :model-value="date ?? undefined"
                    layout="month-and-year"
                    @update:model-value="(val) => onSelect(val, close)"
                />
            </PopoverContent>
        </Popover>
    </div>
</template>