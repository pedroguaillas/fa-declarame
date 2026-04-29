<script setup lang="ts">
import { computed } from "vue";

import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";

// ── Tipos ──────────────────────────────────────────────────────────────────

export interface SelectionItem {
    id: number;
    display_name: string;
}

export interface SelectionGroup {
    id: number;
    display_name: string;
    items: SelectionItem[];
}

// ── Props & Emits ──────────────────────────────────────────────────────────

const props = defineProps<{
    /** Lista de grupos con sus ítems seleccionables */
    groups: SelectionGroup[];
    /** IDs de ítems actualmente seleccionados */
    modelValue: number[];
    /** Etiqueta para el contador. Default: 'seleccionados' */
    label?: string;
    /** Texto cuando no hay grupos. Default: 'No hay elementos disponibles.' */
    emptyText?: string;
    /** Texto cuando un grupo no tiene ítems. Default: 'Sin elementos configurados.' */
    emptyGroupText?: string;
}>();

const emit = defineEmits<{
    "update:modelValue": [value: number[]];
}>();

// ── Computed ───────────────────────────────────────────────────────────────

const totalItems = computed(() =>
    props.groups.reduce((acc, group) => acc + group.items.length, 0),
);

const selectedCount = computed(() => props.modelValue.length);

const isAllSelected = computed(
    () => totalItems.value > 0 && selectedCount.value === totalItems.value,
);

const isNoneSelected = computed(() => selectedCount.value === 0);

// ── Helpers por grupo ──────────────────────────────────────────────────────

const groupSelectedCount = (group: SelectionGroup) =>
    group.items.filter((item) => props.modelValue.includes(item.id)).length;

const isGroupFullySelected = (group: SelectionGroup) =>
    group.items.length > 0 &&
    group.items.every((item) => props.modelValue.includes(item.id));

const isGroupPartiallySelected = (group: SelectionGroup) => {
    const count = groupSelectedCount(group);
    return count > 0 && count < group.items.length;
};

const isSelected = (id: number) => props.modelValue.includes(id);

// ── Handlers ───────────────────────────────────────────────────────────────

const toggleItem = (id: number) => {
    const updated = isSelected(id)
        ? props.modelValue.filter((v) => v !== id)
        : [...props.modelValue, id];
    emit("update:modelValue", updated);
};

const toggleGroup = (group: SelectionGroup) => {
    const groupIds = group.items.map((item) => item.id);
    const allSelected = groupIds.every((id) => props.modelValue.includes(id));
    const updated = allSelected
        ? props.modelValue.filter((id) => !groupIds.includes(id))
        : [
              ...props.modelValue,
              ...groupIds.filter((id) => !props.modelValue.includes(id)),
          ];
    emit("update:modelValue", updated);
};

const toggleAll = () => {
    if (isAllSelected.value) {
        emit("update:modelValue", []);
    } else {
        emit(
            "update:modelValue",
            props.groups.flatMap((g) => g.items.map((item) => item.id)),
        );
    }
};
</script>

<template>
    <div class="space-y-4">
        <!-- ── Barra de control global ─────────────────────────────────────────── -->
        <div
            class="bg-muted/5 border-border/50 flex items-center justify-between rounded-lg border px-4 py-2.5"
        >
            <div class="flex items-center gap-3">
                <Checkbox
                    id="toggle-all"
                    :model-value="isAllSelected"
                    :indeterminate="!isNoneSelected && !isAllSelected"
                    @update:model-value="toggleAll"
                />
                <Label
                    for="toggle-all"
                    class="text-foreground cursor-pointer text-[11px] font-bold uppercase tracking-wide"
                >
                    Seleccionar todo
                </Label>
            </div>
            <div class="flex items-center gap-2">
                <Badge variant="secondary" class="font-mono font-bold">
                    {{ selectedCount }} / {{ totalItems }}
                </Badge>
                <span class="text-muted-foreground text-[11px] font-medium">
                    {{ label ?? "seleccionados" }}
                </span>
            </div>
        </div>

        <!-- ── Grid de grupos ──────────────────────────────────────────────────── -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="group in groups"
                :key="group.id"
                class="bg-muted/5 border-border/50 hover:bg-muted/10 flex flex-col gap-3 rounded-xl border p-4 transition-all"
            >
                <!-- Encabezado del grupo -->
                <div
                    @click="toggleGroup(group)"
                    class="border-border/50 flex cursor-pointer items-center justify-between border-b pb-2"
                >
                    <div class="flex items-center gap-2.5">
                        <Checkbox
                            :model-value="isGroupFullySelected(group)"
                            :indeterminate="isGroupPartiallySelected(group)"
                            @click.stop
                            @update:model-value="toggleGroup(group)"
                        />
                        <span
                            class="text-foreground text-xs font-black uppercase tracking-widest"
                        >
                            {{ group.display_name }}
                        </span>
                    </div>
                    <Badge
                        variant="outline"
                        class="text-[10px] font-bold transition-colors"
                        :class="{
                            'border-primary/50 bg-primary/10 text-primary':
                                isGroupFullySelected(group),
                            'border-amber-500/50 bg-amber-500/10 text-amber-600':
                                isGroupPartiallySelected(group),
                        }"
                    >
                        {{ groupSelectedCount(group) }} /
                        {{ group.items.length }}
                    </Badge>
                </div>

                <!-- Ítems del grupo -->
                <div class="space-y-2.5">
                    <div
                        v-for="item in group.items"
                        :key="item.id"
                        class="flex items-start gap-3"
                    >
                        <Checkbox
                            :id="`item-${item.id}`"
                            :model-value="isSelected(item.id)"
                            @update:model-value="() => toggleItem(item.id)"
                            class="mt-0.5"
                        />
                        <Label
                            :for="`item-${item.id}`"
                            class="text-foreground/80 hover:text-foreground cursor-pointer text-xs font-bold leading-tight transition-colors"
                        >
                            {{ item.display_name }}
                        </Label>
                    </div>

                    <p
                        v-if="group.items.length === 0"
                        class="text-muted-foreground text-[11px] italic"
                    >
                        {{ emptyGroupText ?? "Sin elementos configurados." }}
                    </p>
                </div>
            </div>
        </div>

        <!-- ── Estado vacío ────────────────────────────────────────────────────── -->
        <div
            v-if="groups.length === 0"
            class="text-muted-foreground py-10 text-center text-sm"
        >
            {{ emptyText ?? "No hay elementos disponibles." }}
        </div>
    </div>
</template>
