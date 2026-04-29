<script setup lang="ts">
import { computed, ref, watch, watchEffect } from "vue";
import { Link } from "@inertiajs/vue3";

import FormField from "@/components/Shared/FormField.vue";
import FormSelect from "@/components/Shared/FormSelect.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

import type { VoucherType } from "@/types/tenant";
import FormDatePicker from "@/components/Shared/FormDatePicker.vue";

interface FormErrors {
    [key: string]: string | undefined;
}

interface OrderFormData {
    contact_id: number | null;
    voucher_type_id: number | string;
    emision: string;
    autorization: string;
    autorized_at: string;
    serie: string;
    sub_total: number | string;
    no_iva: number | string;
    base0: number | string;
    base5: number | string;
    base8: number | string;
    base12: number | string;
    base15: number | string;
    iva5: number | string;
    iva8: number | string;
    iva12: number | string;
    iva15: number | string;
    aditional_discount: number | string;
    discount: number | string;
    ice: number | string;
    total: number | string;
    state: string;
    errors: FormErrors;
    processing: boolean;
}

const props = withDefaults(
    defineProps<{
        form: OrderFormData;
        voucherTypes: VoucherType[];
        submitLabel: string;
        initialContactIdentification?: string;
        initialContactName?: string;
    }>(),
    {
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
            route("tenant.contacts.resolve", { identification }),
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
                <div class="lg:col-span-2">
                    <FormField
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
                    label="No IVA"
                    v-model="form.no_iva"
                    type="number"
                    step="0.01"
                    min="0"
                    class="text-right font-mono"
                />
                <FormField
                    label="Base 0%"
                    v-model="form.base0"
                    type="number"
                    step="0.01"
                    min="0"
                    class="text-right font-mono"
                />
                <FormField
                    label="Base 15%"
                    v-model="form.base15"
                    type="number"
                    step="0.01"
                    min="0"
                    class="text-right font-mono"
                />
                <FormField
                    label="IVA 15%"
                    v-model="form.iva15"
                    type="number"
                    step="0.01"
                    min="0"
                    class="text-right font-mono"
                />
                <FormField
                    label="Descuento"
                    v-model="form.discount"
                    type="number"
                    step="0.01"
                    min="0"
                    class="text-right font-mono"
                />
                <FormField
                    label="Subtotal"
                    :model-value="form.sub_total"
                    :error="form.errors.sub_total"
                    type="number"
                    step="0.01"
                    readonly
                    class="text-right font-mono"
                />
                <FormField
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
                        >Total</Label
                    >
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
                <Link :href="route('tenant.orders.index')">Cancelar</Link>
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? "Guardando..." : submitLabel }}
            </Button>
        </div>
    </form>
</template>
