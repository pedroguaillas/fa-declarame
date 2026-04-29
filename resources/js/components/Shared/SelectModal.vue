<script setup lang="ts" generic="T extends object">
import type { Component } from "vue";
import { ref } from "vue";

import type { LaravelPaginator } from "@/components/Modals/ModalTable.vue";
import ModalTable from "@/components/Modals/ModalTable.vue";
import SelectButton from "@/components/Shared/SelectButton.vue";
import { ColumnDef } from "@/types/shared";

interface Props {
    // SelectButton
    label: string;
    placeholder?: string;
    error?: string;
    required?: boolean;
    class?: string;
    // ModalTable
    modalTitle: string;
    modalIcon?: Component;
    columns: ColumnDef<T>[];
    paginator: LaravelPaginator<T> | null;
    loading?: boolean;
    searchPlaceholder?: string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: "Seleccionar...",
    loading: false,
});

const emit = defineEmits<{
    fetch: [params: { search: string; page: number }];
    select: [item: T];
}>();

const selectedLabel = defineModel<string | null>("selectedLabel", {
    default: null,
});
const open = ref(false);

const handleSelect = (item: T) => {
    emit("select", item);
    open.value = false;
};
</script>

<template>
    <SelectButton
        :label="label"
        :placeholder="placeholder"
        :selected-label="selectedLabel"
        :error="error"
        :required="required"
        :class="props.class"
        @click="open = true"
    />

    <ModalTable
        :icon="modalIcon"
        :open="open"
        :title="modalTitle"
        :columns="columns"
        :paginator="paginator"
        :loading="loading"
        :search-placeholder="searchPlaceholder"
        @fetch="emit('fetch', $event)"
        @select="handleSelect"
        @close="open = false"
    />
</template>
