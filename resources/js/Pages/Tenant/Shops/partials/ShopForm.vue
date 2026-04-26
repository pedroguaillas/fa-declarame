<script setup lang="ts">
import { computed, ref, watch, watchEffect } from "vue";
import { Link } from "@inertiajs/vue3";

import FormField from "@/components/Shared/FormField.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import FormSelect from "@/components/Shared/FormSelect.vue";
import { Account, VoucherType } from "@/types/tenant";
import FormDatePicker from "@/components/Shared/FormDatePicker.vue";

interface FormErrors {
    [key: string]: string | undefined;
}

interface ShopFormData {
    acount_id: number | null;
    contact_id: number | null;
    voucher_type_id: number | string;
    emision: string;
    autorization: string;
    autorized_at: string;
    serie: string;
    sub_total: number | string;
    no_iva: number | string;
    base0: number | string;
    base12: number | string;
    base15: number | string;
    iva12: number | string;
    iva15: number | string;
    aditional_discount: number | string;
    discount: number | string;
    ice: number | string;
    total: number | string;
    state: string;
    serie_retention: string;
    date_retention: string;
    state_retention: string;
    autorization_retention: string;
    errors: FormErrors;
    processing: boolean;
}

const props = withDefaults(
    defineProps<{
        form: ShopFormData;
        voucherTypes: VoucherType[];
        accounts?: Account[];
        submitLabel: string;
        initialContactIdentification?: string;
        initialContactName?: string;
    }>(),
    {
        accounts: () => [],
        initialContactIdentification: "",
        initialContactName: "",
    },
);

const emit = defineEmits<{ submit: [] }>();

// ── Voucher type options ───────────────────────────────────────────────────

const voucherTypeOptions = computed(() =>
    props.voucherTypes.map((v) => ({
        id: v.id,
        label: `${v.code} - ${v.description}`,
    })),
);

// ── Contact resolve ────────────────────────────────────────────────────────

const contactIdentification = ref(props.initialContactIdentification ?? "");
const contactName = ref(props.initialContactName ?? "");
const contactResolving = ref(false);
const contactError = ref<string | null>(null);

watch(contactIdentification, async (identification) => {
    if (identification.length !== 10 && identification.length !== 13) {
        contactName.value = "";
        contactError.value = null;
        props.form.contact_id = null;
        return;
    }

    contactResolving.value = true;
    contactError.value = null;

    try {
        const res = await fetch(
            route("tenant.contacts.resolve", {
                identification,
            }),
            { headers: { Accept: "application/json" } },
        );

        if (!res.ok) {
            contactName.value = "";
            props.form.contact_id = null;
            contactError.value =
                "No se encontró el contacto con esa identificación.";
            return;
        }

        const data = await res.json();
        contactName.value = data.name;
        props.form.contact_id = data.id;
    } catch {
        contactError.value = "Error al consultar el contacto.";
    } finally {
        contactResolving.value = false;
    }
});

// ── Account search ─────────────────────────────────────────────────────────

const accountQuery = ref("");
const accountDropdownOpen = ref(false);

watch(
    () => props.form.acount_id,
    (id) => {
        if (id && !accountQuery.value) {
            const found = props.accounts.find((a) => a.id === id);
            if (found) accountQuery.value = `${found.code} – ${found.name}`;
        }
    },
    { immediate: true },
);

function filteredAccounts(): Account[] {
    const q = accountQuery.value.trim().toLowerCase();
    if (!q) return props.accounts.slice(0, 8);
    return props.accounts
        .filter(
            (a) =>
                a.code.toLowerCase().includes(q) ||
                a.name.toLowerCase().includes(q),
        )
        .slice(0, 8);
}

function selectAccount(account: Account) {
    props.form.acount_id = account.id;
    accountQuery.value = `${account.code} – ${account.name}`;
    accountDropdownOpen.value = false;
}

function clearAccount() {
    props.form.acount_id = null;
    accountQuery.value = "";
}

function closeAccountDropdownDelayed() {
    setTimeout(() => {
        accountDropdownOpen.value = false;
    }, 150);
}

// ── Numeric helpers ────────────────────────────────────────────────────────

const n = (v: number | string) => parseFloat(String(v)) || 0;

watch(
    () => props.form.emision,
    (val) => {
        if (val && !props.form.autorized_at) {
            props.form.autorized_at = `${val}T00:00`;
        }
    },
);

watch(
    () => props.form.base12,
    (val) => {
        props.form.iva12 = parseFloat((n(val) * 0.12).toFixed(2));
    },
);

watch(
    () => props.form.base15,
    (val) => {
        props.form.iva15 = parseFloat((n(val) * 0.15).toFixed(2));
    },
);

const computedSubTotal = computed(
    () =>
        n(props.form.no_iva) +
        n(props.form.base0) +
        n(props.form.base12) +
        n(props.form.base15),
);

const computedTotal = computed(
    () =>
        computedSubTotal.value +
        n(props.form.iva12) +
        n(props.form.iva15) +
        n(props.form.ice) -
        n(props.form.discount) -
        n(props.form.aditional_discount),
);

watchEffect(() => {
    props.form.sub_total = parseFloat(computedSubTotal.value.toFixed(2));
    props.form.total = parseFloat(computedTotal.value.toFixed(2));
});

const today = new Date().toISOString().slice(0, 10);
const nowLocal = new Date(
    new Date().getTime() - new Date().getTimezoneOffset() * 60000,
)
    .toISOString()
    .slice(0, 16);
</script>

<template>
    <form @submit.prevent="emit('submit')">
        <!-- Sección: Documento -->
        <div class="p-6">
            <h2
                class="text-muted-foreground mb-4 text-xs font-semibold tracking-widest uppercase"
            >
                Documento
            </h2>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Identificación -->
                <div class="flex flex-col gap-1.5">
                    <Label
                        >Identificación
                        <span class="text-destructive">*</span></Label
                    >
                    <div class="relative">
                        <Input
                            v-model="contactIdentification"
                            type="text"
                            maxlength="13"
                            placeholder="RUC o cédula"
                            class="pr-8 font-mono"
                            :disabled="contactResolving"
                        />
                        <span
                            v-if="contactResolving"
                            class="text-muted-foreground absolute top-1/2 right-2.5 -translate-y-1/2"
                        >
                            <svg
                                class="size-4 animate-spin"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                />
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                />
                            </svg>
                        </span>
                    </div>
                    <p
                        v-if="contactError"
                        class="text-muted-foreground text-xs"
                    >
                        {{ contactError }}
                    </p>
                    <p
                        v-if="form.errors.contact_id"
                        class="text-destructive text-xs"
                    >
                        {{ form.errors.contact_id }}
                    </p>
                </div>

                <!-- Nombre -->
                <div class="flex flex-col gap-1.5">
                    <Label>Nombre</Label>
                    <Input
                        :model-value="contactName"
                        type="text"
                        readonly
                        placeholder="Se completa automáticamente"
                        class="bg-muted text-muted-foreground cursor-default"
                    />
                </div>

                <!-- Cuenta contable -->
                <div class="flex flex-col gap-1.5 sm:col-span-2 lg:col-span-3">
                    <Label>Cuenta contable (costo / gasto)</Label>
                    <div class="relative">
                        <Input
                            v-model="accountQuery"
                            type="text"
                            placeholder="Buscar por código o nombre…"
                            class="pr-8"
                            @focus="accountDropdownOpen = true"
                            @blur="closeAccountDropdownDelayed"
                        />
                        <button
                            v-if="form.acount_id"
                            type="button"
                            class="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2"
                            @mousedown.prevent="clearAccount"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                class="size-4"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6 18 18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                        <div
                            v-if="
                                accountDropdownOpen &&
                                filteredAccounts().length > 0
                            "
                            class="border-border bg-popover absolute left-0 right-0 top-full z-10 mt-1 max-h-52 overflow-y-auto rounded-md border shadow-lg"
                        >
                            <button
                                v-for="account in filteredAccounts()"
                                :key="account.id"
                                type="button"
                                class="hover:bg-accent flex w-full items-center gap-3 px-3 py-2 text-left text-sm transition-colors"
                                @mousedown.prevent="selectAccount(account)"
                            >
                                <span
                                    class="text-foreground w-28 shrink-0 font-mono text-xs font-medium"
                                    >{{ account.code }}</span
                                >
                                <span
                                    class="text-muted-foreground flex-1 truncate text-xs"
                                    >{{ account.name }}</span
                                >
                            </button>
                        </div>
                    </div>
                    <p
                        v-if="form.errors.acount_id"
                        class="text-destructive text-xs"
                    >
                        {{ form.errors.acount_id }}
                    </p>
                </div>

                <!-- Tipo de comprobante -->
                <FormSelect
                    label="Tipo de comprobante"
                    v-model="form.voucher_type_id"
                    :options="voucherTypeOptions"
                    :error="form.errors.voucher_type_id"
                    placeholder="Seleccionar tipo"
                    required
                />

                <!-- Serie -->
                <FormField
                    id="serie"
                    label="Serie"
                    v-model="form.serie"
                    :error="form.errors.serie"
                    maxlength="17"
                    placeholder="001-001-000000001"
                    required
                    class="font-mono"
                />

                <!-- Fecha de emisión -->
                <FormDatePicker
                    id="emision"
                    label="Fecha de emisión"
                    v-model="form.emision"
                    :error="form.errors.emision"
                    default-today
                    required
                />

                <!-- Autorización -->
                <div class="flex flex-col gap-1.5 lg:col-span-2">
                    <FormField
                        id="autorization"
                        label="Autorización"
                        v-model="form.autorization"
                        :error="form.errors.autorization"
                        maxlength="49"
                        required
                        class="font-mono"
                    />
                </div>

                <!-- Fecha de autorización -->
                <FormDatePicker
                    id="autorized_at"
                    label="Fecha de autorización"
                    v-model="form.autorized_at"
                    :error="form.errors.autorized_at"
                    default-today
                     mode="datetime"
                />
            </div>
        </div>

        <!-- Sección: Valores -->
        <div class="border-border border-t p-6">
            <h2
                class="text-muted-foreground mb-4 text-xs font-semibold tracking-widest uppercase"
            >
                Valores
            </h2>
            <div class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
                <FormField
                    id="no_iva"
                    label="No IVA"
                    v-model="form.no_iva"
                    type="number"
                    step="0.01"
                    min="0"
                    class="text-right font-mono"
                />
                <FormField
                    id="base0"
                    label="Base 0%"
                    v-model="form.base0"
                    type="number"
                    step="0.01"
                    min="0"
                    class="text-right font-mono"
                />
                <FormField
                    id="base15"
                    label="Base 15%"
                    v-model="form.base15"
                    type="number"
                    step="0.01"
                    min="0"
                    class="text-right font-mono"
                />
                <FormField
                    id="iva15"
                    label="IVA 15%"
                    v-model="form.iva15"
                    type="number"
                    step="0.01"
                    min="0"
                    class="text-right font-mono"
                />
                <FormField
                    id="discount"
                    label="Descuento"
                    v-model="form.discount"
                    type="number"
                    step="0.01"
                    min="0"
                    class="text-right font-mono"
                />
                <FormField
                    id="sub_total"
                    label="Subtotal"
                    :model-value="form.sub_total"
                    :error="form.errors.sub_total"
                    type="number"
                    step="0.01"
                    readonly
                    class="text-right font-mono"
                />
                <FormField
                    id="ice"
                    label="ICE"
                    v-model="form.ice"
                    type="number"
                    step="0.01"
                    min="0"
                    class="text-right font-mono"
                />

                <!-- Total destacado -->
                <div class="bg-muted flex flex-col gap-1 rounded-lg px-4 py-3">
                    <Label
                        class="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                    >
                        Total
                    </Label>
                    <Input
                        :model-value="form.total"
                        type="number"
                        step="0.01"
                        readonly
                        class="cursor-default border-0 bg-transparent p-0 text-right font-mono text-base font-semibold shadow-none focus-visible:ring-0"
                    />
                    <p
                        v-if="form.errors.total"
                        class="text-destructive text-xs"
                    >
                        {{ form.errors.total }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div
            class="border-border flex items-center justify-end gap-3 border-t px-6 py-4"
        >
            <Button variant="outline" type="button" as-child>
                <Link :href="route('tenant.shops.index')"> Cancelar </Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? "Guardando..." : submitLabel }}
            </Button>
        </div>
    </form>
</template>
