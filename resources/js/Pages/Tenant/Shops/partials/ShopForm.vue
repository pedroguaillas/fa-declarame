<script setup lang="ts">
import { computed, ref, watch, watchEffect } from "vue";
import { Link } from "@inertiajs/vue3";

import FormField from "@/components/Shared/FormField.vue";
import FormSelect from "@/components/Shared/FormSelect.vue";
import FormDatePicker from "@/components/Shared/FormDatePicker.vue";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Loader2 } from "lucide-vue-next";

import { today, getLocalTimeZone } from "@internationalized/date";

import { VoucherType } from "@/types/tenant";
import type { InertiaForm } from "@inertiajs/vue3";

interface ShopFormFields {
    contact_id: number | null;
    voucher_type_id: number | string;
    type_identification: string | undefined;
    emision: string;
    autorization: string;
    autorized_at: string;
    serie: string;
    sub_total: number | string;
    no_iva: number | string;
    exempt: number | string;
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
    serie_retention: string;
    date_retention: string;
    state_retention: string;
    autorization_retention: string;
    retention_at: string;
    voucher_type_modify_id: number | string | null;
    est_modify: string;
    poi_modify: string;
    sec_modify: string;
    aut_modify: string;
}

const props = withDefaults(
    defineProps<{
        form: InertiaForm<ShopFormFields>;
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

const emit = defineEmits<{
    submit: [];
}>();

// OPTIONS
const isCedulaOrPasaporte = computed(() => ["02", "03"].includes(props.form.type_identification ?? ""));

const isRuc = computed(() => props.form.type_identification === "01");

const voucherTypeOptions = computed(() =>
    props.voucherTypes
        .filter((v) => {
            if (isCedulaOrPasaporte.value) return v.code === "03";
            if (isRuc.value) return v.code !== "03";
            return true;
        })
        .map((v) => ({
            id: v.id,
            label: `${v.code} - ${v.description}`,
        })),
);

const modifyVoucherOptions = computed(() =>
    props.voucherTypes
        .filter((v) => ["01", "02", "03"].includes(v.code))
        .map((v) => ({
            id: v.id,
            label: `${v.code} - ${v.description}`,
        })),
);

// HELPERS
const selectedVoucher = computed(() => props.voucherTypes.find((v) => v.id == props.form.voucher_type_id));
const voucherCode = computed(() => selectedVoucher.value?.code || "");
const showModifyDocumentFields = computed(() => ["04", "05"].includes(voucherCode.value));
const isNotaVenta = computed(() => voucherCode.value === "02");

watch(isCedulaOrPasaporte, (restricted) => {
    if (restricted) {
        const lc = props.voucherTypes.find((v) => v.code === "03");
        if (lc) props.form.voucher_type_id = lc.id;
    }
});

// CONTACTO
const contactIdentification = ref(props.initialContactIdentification ?? "");
const contactName = ref(props.initialContactName ?? "");
const contactResolving = ref(false);
const contactNotFound = ref(false);

async function handleIdentificationBlur() {
    const identification = contactIdentification.value.trim();

    if (!identification) {
        contactName.value = "";
        props.form.contact_id = null;
        contactNotFound.value = false;
        return;
    }

    contactResolving.value = true;
    contactNotFound.value = false;

    try {
        const res = await fetch(route("tenant.contacts.search", { identification }), {
            headers: { Accept: "application/json" },
        });

        if (res.ok) {
            const data = await res.json();
            if (data.found) {
                contactName.value = data.name;
                props.form.contact_id = data.id;
                props.form.type_identification = data.type_identification;
                contactNotFound.value = false;
            } else {
                contactName.value = "";
                props.form.contact_id = null;
                contactNotFound.value = true;
            }
        }
    } catch {
        contactName.value = "";
        props.form.contact_id = null;
    } finally {
        contactResolving.value = false;
    }
}

function formatSerie() {
    const raw = props.form.serie.replace(/[^0-9-]/g, "");
    const parts = raw.split("-");
    if (parts.length >= 3) {
        const est = parts[0].padStart(3, "0");
        const pto = parts[1].padStart(3, "0");
        const seq = parts.slice(2).join("").padStart(9, "0");
        props.form.serie = `${est}-${pto}-${seq}`;
    } else if (parts.length === 2) {
        const est = parts[0].padStart(3, "0");
        const pto = parts[1].padStart(3, "0");
        props.form.serie = `${est}-${pto}-`;
    }
}

function handleSubmit() {
    emit("submit");
}

// CALCULOS
const n = (v: number | string) => parseFloat(String(v)) || 0;

const IVA15_START = new Date("2024-04-01");

const useIva15 = computed(() => {
    if (!props.form.emision) return true;
    return new Date(props.form.emision) >= IVA15_START;
});

watch(useIva15, (is15) => {
    if (is15) {
        props.form.base12 = 0;
        props.form.iva12 = 0;
    } else {
        props.form.base15 = 0;
        props.form.iva15 = 0;
    }
});

watch(isNotaVenta, (isNV) => {
    if (isNV) {
        props.form.no_iva = 0;
        props.form.base12 = 0;
        props.form.iva12 = 0;
        props.form.base15 = 0;
        props.form.iva15 = 0;
    }
});

watch(
    () => props.form.base15,
    (val) => {
        props.form.iva15 = parseFloat((n(val) * 0.15).toFixed(2));
    },
);

const computedSubTotal = computed(
    () => n(props.form.no_iva) + n(props.form.base0) + n(props.form.base12) + n(props.form.base15),
);

const computedTotal = computed(
    () =>
        computedSubTotal.value +
        n(props.form.iva15) +
        n(props.form.iva12) +
        n(props.form.ice) -
        n(props.form.discount) -
        n(props.form.aditional_discount),
);

watchEffect(() => {
    props.form.sub_total = parseFloat(computedSubTotal.value.toFixed(2));

    props.form.total = parseFloat(computedTotal.value.toFixed(2));
});

watch(
    () => props.form.emision,
    (val) => {
        if (val) {
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
</script>

<template>
    <form @submit.prevent="handleSubmit">
        <div class="p-6">
            <h2 class="mb-4 text-xs font-semibold uppercase tracking-widest text-muted-foreground">Documento</h2>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div class="flex flex-col gap-1.5">
                    <Label>
                        Identificación
                        <span class="text-destructive ml-0.5">*</span>
                    </Label>

                    <div class="relative">
                        <Input v-model="contactIdentification" @blur="handleIdentificationBlur" />
                        <Loader2
                            v-if="contactResolving"
                            class="text-muted-foreground absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin"
                        />
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label>
                        Nombre
                        <span class="text-destructive ml-0.5">*</span>
                    </Label>

                    <Input :model-value="contactName" readonly />

                    <Button
                        v-if="contactNotFound"
                        variant="link"
                        type="button"
                        class="h-auto justify-start p-0 text-xs"
                        as-child
                    >
                        <Link :href="route('tenant.contacts.index')">Registrar contacto</Link>
                    </Button>
                </div>

                <FormSelect
                    label="Tipo comprobante"
                    v-model="form.voucher_type_id"
                    :options="voucherTypeOptions"
                    required
                />

                <FormField
                    id="serie"
                    label="Serie"
                    v-model="form.serie"
                    maxlength="17"
                    placeholder="001-001-000000001"
                    required
                    @blur="formatSerie"
                />

                <FormDatePicker
                    id="emision"
                    label="Fecha emisión"
                    v-model="form.emision"
                    :max-value="today(getLocalTimeZone())"
                    required
                />

                <div class="lg:col-span-2">
                    <FormField
                        id="autorization"
                        label="Autorización"
                        v-model="form.autorization"
                        maxlength="49"
                        required
                    />
                </div>

                <FormDatePicker
                    id="autorized_at"
                    label="Fecha autorización"
                    v-model="form.autorized_at"
                    mode="datetime"
                    required
                />

                <template v-if="showModifyDocumentFields">
                    <div class="mt-4 lg:col-span-3">
                        <h3 class="text-sm font-semibold">Documento Modificado</h3>
                    </div>

                    <FormSelect
                        label="Comprobante original"
                        v-model="form.voucher_type_modify_id"
                        :options="modifyVoucherOptions"
                        required
                    />

                    <FormField
                        id="est_modify"
                        label="Establecimiento"
                        v-model="form.est_modify"
                        maxlength="3"
                        required
                    />

                    <FormField id="poi_modify" label="Punto emisión" v-model="form.poi_modify" maxlength="3" required />

                    <FormField id="sec_modify" label="Secuencial" v-model="form.sec_modify" maxlength="9" required />

                    <div class="lg:col-span-2">
                        <FormField
                            id="aut_modify"
                            label="Autorización documento original"
                            v-model="form.aut_modify"
                            maxlength="49"
                            required
                        />
                    </div>
                </template>
            </div>
        </div>

        <!-- VALORES -->

        <div class="border-t p-6">
            <h2 class="mb-4 text-xs font-semibold uppercase tracking-widest text-muted-foreground">Valores</h2>

            <div class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
                <FormField
                    v-if="!isNotaVenta"
                    id="no_iva"
                    label="No IVA"
                    v-model="form.no_iva"
                    type="number"
                    step="0.01"
                />

                <FormField id="exempt" label="Base Exenta" v-model="form.exempt" type="number" step="0.01" />

                <FormField id="base0" label="Base 0%" v-model="form.base0" type="number" step="0.01" />

                <FormField
                    v-if="!useIva15 && !isNotaVenta"
                    id="base12"
                    label="Base 12%"
                    v-model="form.base12"
                    type="number"
                    step="0.01"
                />

                <FormField v-if="!useIva15 && !isNotaVenta" id="iva12" label="IVA 12%" v-model="form.iva12" readonly />

                <FormField
                    v-if="useIva15 && !isNotaVenta"
                    id="base15"
                    label="Base 15%"
                    v-model="form.base15"
                    type="number"
                    step="0.01"
                />

                <FormField v-if="useIva15 && !isNotaVenta" id="iva15" label="IVA 15%" v-model="form.iva15" readonly />

                <FormField id="discount" label="Descuento" v-model="form.discount" type="number" step="0.01" />

                <FormField id="ice" label="ICE" v-model="form.ice" type="number" step="0.01" />

                <FormField id="sub_total" label="Subtotal" :model-value="form.sub_total" readonly />

                <div class="rounded-lg bg-muted p-4">
                    <Label>Total</Label>

                    <Input :model-value="form.total" readonly class="text-right text-lg font-semibold" />
                </div>
            </div>
        </div>

        <!-- BOTONES -->

        <div class="flex justify-end gap-3 border-t px-6 py-4">
            <Button variant="outline" type="button" as-child>
                <Link :href="route('tenant.shops.index')"> Cancelar </Link>
            </Button>

            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? "Guardando..." : submitLabel }}
            </Button>
        </div>
    </form>
</template>
