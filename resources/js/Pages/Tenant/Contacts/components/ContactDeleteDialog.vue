<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import ConfirmDialog from "@/components/Shared/ConfirmDialog.vue";

interface Contact {
    id: number;
    name: string;
}

const props = defineProps<{
    open: boolean;
    contact: Contact | null;
}>();

const emit = defineEmits<{
    "update:open": [value: boolean];
}>();

function handleDelete() {
    if (!props.contact) return;
    router.delete(route("tenant.contacts.destroy", props.contact.id), {
        onFinish: () => emit("update:open", false),
    });
}

function handleCancel() {
    emit("update:open", false);
}
</script>

<template>
    <ConfirmDialog
        :open="open"
        title="Eliminar contacto"
        :description="`¿Estás seguro de eliminar a ${contact?.name ?? ''}? Esta acción no se puede deshacer.`"
        @confirm="handleDelete"
        @cancel="handleCancel"
    />
</template>
