<script setup lang="ts">
import TenantLayout from "@/layouts/TenantLayout.vue";
import { Head, useForm } from "@inertiajs/vue3";
import { ref } from "vue";

interface Account {
    id: number;
    parent_id: number | null;
    code: string;
    name: string;
    type: string;
    is_detail: boolean;
}

defineProps<{
    accounts: Account[];
}>();

const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm<{ file: File | null }>({
    file: null,
});

function openFilePicker() {
    fileInput.value?.click();
}

function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    if (!file) {
        return;
    }

    form.file = file;
    form.post(route("tenant.accounts.import"), {
        forceFormData: true,
    });
}

const typeLabel: Record<string, string> = {
    activo: "Activo",
    pasivo: "Pasivo",
    patrimonio: "Patrimonio",
    ingreso: "Ingreso",
    costo: "Costo",
};

const typeColor: Record<string, string> = {
    activo: "text-blue-600 dark:text-blue-400",
    pasivo: "text-red-600 dark:text-red-400",
    patrimonio: "text-purple-600 dark:text-purple-400",
    ingreso: "text-green-600 dark:text-green-400",
    costo: "text-orange-600 dark:text-orange-400",
};

function indentLevel(code: string): number {
    return (code.match(/\./g) ?? []).length;
}
</script>

<template>
    <Head title="Plan de cuentas contables" />

    <TenantLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-foreground text-2xl font-semibold">Plan de Cuentas</h1>

            <div class="flex items-center gap-3">
                <span v-if="form.processing" class="text-muted-foreground text-sm"> Importando… </span>

                <button
                    v-if="accounts.length > 0"
                    type="button"
                    class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors disabled:opacity-60"
                    :disabled="form.processing"
                    @click="openFilePicker"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="size-[16px]"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"
                        />
                    </svg>
                    Reimportar
                </button>

                <input ref="fileInput" type="file" accept=".xlsx,.xls" class="hidden" @change="handleFileChange" />
            </div>
        </div>

        <!-- Empty state with import button -->
        <div
            v-if="accounts.length === 0"
            class="border-border bg-card flex flex-col items-center justify-center rounded-lg border py-16"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="text-muted-foreground mb-4 size-12"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"
                />
            </svg>
            <p class="text-foreground mb-1 text-sm font-medium">No hay cuentas registradas</p>
            <p class="text-muted-foreground mb-6 text-sm">Importa el plan de cuentas desde un archivo Excel (.xlsx)</p>
            <button
                type="button"
                class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-2 rounded-md px-5 py-2.5 text-sm font-medium transition-colors disabled:opacity-60"
                :disabled="form.processing"
                @click="openFilePicker"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-[16px]"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"
                    />
                </svg>
                Importar Plan de Cuentas
            </button>
        </div>

        <!-- Accounts table -->
        <div v-else class="border-border bg-card overflow-hidden rounded-lg border">
            <table class="divide-border min-w-full divide-y">
                <thead class="bg-muted">
                    <tr>
                        <th
                            class="text-muted-foreground px-5 py-3 text-left text-xs font-medium tracking-wider uppercase"
                        >
                            Código
                        </th>
                        <th
                            class="text-muted-foreground px-5 py-3 text-left text-xs font-medium tracking-wider uppercase"
                        >
                            Cuenta
                        </th>
                        <th
                            class="text-muted-foreground px-5 py-3 text-left text-xs font-medium tracking-wider uppercase"
                        >
                            Tipo
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-border bg-card divide-y">
                    <tr v-for="account in accounts" :key="account.id" class="hover:bg-muted/40 transition-colors">
                        <td class="text-foreground px-5 py-2.5 font-mono text-sm whitespace-nowrap">
                            {{ account.code }}
                        </td>
                        <td class="px-5 py-2.5 text-sm">
                            <span
                                :style="{ paddingLeft: `${indentLevel(account.code) * 16}px` }"
                                :class="account.is_detail ? 'text-foreground font-medium' : 'text-muted-foreground'"
                            >
                                {{ account.name }}
                            </span>
                        </td>
                        <td class="px-5 py-2.5 text-sm whitespace-nowrap">
                            <span
                                v-if="account.parent_id === null"
                                class="text-xs font-medium"
                                :class="typeColor[account.type]"
                            >
                                {{ typeLabel[account.type] ?? account.type }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </TenantLayout>
</template>
