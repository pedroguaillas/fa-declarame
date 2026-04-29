<script setup lang="ts">
import { computed, ref, watch, watchEffect } from "vue";
import { Link } from "@inertiajs/vue3";

import FormField from "@/components/Shared/FormField.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import FormSelect from "@/components/Shared/FormSelect.vue";
import FormDatePicker from "@/components/Shared/FormDatePicker.vue";

import {
    Account,
    IdentificationType,
    VoucherType,
} from "@/types/tenant";

// ─────────────────────────────────────────────
// TYPES
// ─────────────────────────────────────────────

interface FormErrors {
    [key: string]: string | undefined;
}

interface ShopFormData {
    acount_id: number | null;

    contact_id: number | null;

    voucher_type_id: number | string;

    type_identification: string | undefined;

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

    voucher_type_modify_id: number | string | null;

    est_modify: string;

    poi_modify: string;

    sec_modify: string;

    aut_modify: string;

    errors: FormErrors;

    processing: boolean;
}

// ─────────────────────────────────────────────
// PROPS
// ─────────────────────────────────────────────

const props = withDefaults(
    defineProps<{
        form: ShopFormData;

        voucherTypes: VoucherType[];

        accounts?: Account[];

        submitLabel: string;

        initialContactIdentification?: string;

        initialContactName?: string;

        identificationTypes: IdentificationType[];
    }>(),
    {
        accounts: () => [],

        initialContactIdentification: "",

        initialContactName: "",

        identificationTypes: () => [],
    },
);

const emit = defineEmits<{
    submit: [];
}>();

// ─────────────────────────────────────────────
// OPTIONS
// ─────────────────────────────────────────────

const identificationOptions = computed(() =>
    props.identificationTypes.map((i) => ({
        id: i.id ?? 0,
        label: i.description,
    })),
);

const voucherTypeOptions = computed(() =>
    props.voucherTypes.map((v) => ({
        id: v.id,
        label: `${v.code} - ${v.description}`,
    })),
);

const modifyVoucherOptions = computed(() =>
    props.voucherTypes
        .filter((v) =>
            ["01", "02", "03"].includes(v.code),
        )
        .map((v) => ({
            id: v.id,
            label: `${v.code} - ${v.description}`,
        })),
);

// ─────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────

const selectedVoucher = computed(() =>
    props.voucherTypes.find(
        (v) =>
            v.id == props.form.voucher_type_id,
    ),
);

const voucherCode = computed(
    () => selectedVoucher.value?.code || "",
);

// NOTA DE VENTA

const isNotaVenta = computed(
    () => voucherCode.value === "02",
);

// NOTA CREDITO / DEBITO

const showModifyDocumentFields =
    computed(() =>
        ["04", "05"].includes(
            voucherCode.value,
        ),
    );

// ─────────────────────────────────────────────
// CONTACT
// ─────────────────────────────────────────────

const contactIdentification = ref(
    props.initialContactIdentification ?? "",
);

const contactName = ref(
    props.initialContactName ?? "",
);

const contactResolving = ref(false);

const contactError = ref<string | null>(null);

watch(contactIdentification, async (identification) => {
    const expectedLength =
        maxIdentificationLength.value;

    if (
        props.form.type_identification &&
        identification.length !== expectedLength &&
        expectedLength !== 20
    ) {
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
            {
                headers: {
                    Accept: "application/json",
                },
            },
        );

        if (!res.ok) {
            contactName.value = "";

            props.form.contact_id = null;

            return;
        }

        const data = await res.json();

        contactName.value = data.name;

        props.form.contact_id = data.id;
    } catch {
        contactError.value =
            "Error al consultar contacto.";
    } finally {
        contactResolving.value = false;
    }
});

// ─────────────────────────────────────────────
// VALIDACIONES
// ─────────────────────────────────────────────

function validateVoucherWithIdentification() {
    const identification =
        contactIdentification.value;

    // FACTURA Y NOTA VENTA

    if (
        (voucherCode.value === "01" ||
            voucherCode.value === "02") &&
        identification.length !== 13
    ) {
        alert(
            "Factura y Nota de Venta solo permiten RUC.",
        );

        return false;
    }

    // LIQUIDACION

    if (voucherCode.value === "03") {
        const valid =
            identification.length === 10 ||
            identification.length === 13 ||
            identification.length > 13;

        if (!valid) {
            alert(
                "Liquidación requiere cédula, RUC o pasaporte.",
            );

            return false;
        }
    }

    return true;
}

function handleSubmit() {
    if (!validateVoucherWithIdentification())
        return;

    emit("submit");
}

// ─────────────────────────────────────────────
// CUENTAS
// ─────────────────────────────────────────────

const accountQuery = ref("");

const accountDropdownOpen = ref(false);

watch(
    () => props.form.acount_id,
    (id) => {
        if (id && !accountQuery.value) {
            const found = props.accounts.find(
                (a) => a.id === id,
            );

            if (found) {
                accountQuery.value = `${found.code} - ${found.name}`;
            }
        }
    },
    {
        immediate: true,
    },
);

function filteredAccounts(): Account[] {
    const q =
        accountQuery.value
            .trim()
            .toLowerCase();

    if (!q) return props.accounts.slice(0, 8);

    return props.accounts
        .filter(
            (a) =>
                a.code
                    .toLowerCase()
                    .includes(q) ||
                a.name
                    .toLowerCase()
                    .includes(q),
        )
        .slice(0, 8);
}

function selectAccount(account: Account) {
    props.form.acount_id = account.id;

    accountQuery.value = `${account.code} - ${account.name}`;

    accountDropdownOpen.value = false;
}

// ─────────────────────────────────────────────
// CALCULOS
// ─────────────────────────────────────────────

const n = (v: number | string) =>
    parseFloat(String(v)) || 0;

// NOTA VENTA SOLO IVA 0

watch(isNotaVenta, (val) => {
    if (val) {
        props.form.base12 = 0;

        props.form.base15 = 0;

        props.form.iva12 = 0;

        props.form.iva15 = 0;
    }
});

// IVA 12

watch(
    () => props.form.base12,
    (val) => {
        if (isNotaVenta.value) {
            props.form.base12 = 0;

            props.form.iva12 = 0;

            return;
        }

        props.form.iva12 = parseFloat(
            (n(val) * 0.12).toFixed(2),
        );
    },
);

// IVA 15

watch(
    () => props.form.base15,
    (val) => {
        if (isNotaVenta.value) {
            props.form.base15 = 0;

            props.form.iva15 = 0;

            return;
        }

        props.form.iva15 = parseFloat(
            (n(val) * 0.15).toFixed(2),
        );
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
    props.form.sub_total = parseFloat(
        computedSubTotal.value.toFixed(2),
    );

    props.form.total = parseFloat(
        computedTotal.value.toFixed(2),
    );
});

// ─────────────────────────────────────────────
// IDENTIFICACION
// ─────────────────────────────────────────────

const maxIdentificationLength = computed(() => {
    if (!props.form.type_identification)
        return 13;

    const selected =
        props.identificationTypes.find(
            (i) =>
                i.id ==
                props.form.type_identification,
        );

    if (!selected) return 13;

    const label =
        selected.description.toLowerCase();

    if (label.includes("cedula"))
        return 10;

    if (label.includes("ruc")) return 13;

    return 20;
});
</script>

<template>
    <form @submit.prevent="handleSubmit">
        <!-- DOCUMENTO -->

        <div class="p-6">
            <h2 class="mb-4 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                Documento
            </h2>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <!-- IDENTIFICACION -->

                <FormSelect label="Tipo identificación" v-model="form.type_identification
                    " :options="identificationOptions
                        " required />

                <!-- NUMERO -->

                <div class="flex flex-col gap-1.5">
                    <Label>
                        Identificación
                    </Label>

                    <Input v-model="contactIdentification
                        " :maxlength="maxIdentificationLength
                            " />
                </div>

                <!-- NOMBRE -->

                <div class="flex flex-col gap-1.5">
                    <Label>Nombre</Label>

                    <Input :model-value="contactName
                        " readonly />
                </div>

                <!-- CUENTA -->

                <div class="sm:col-span-2 lg:col-span-3">
                    <Label>
                        Cuenta contable
                    </Label>

                    <div class="relative">
                        <Input v-model="accountQuery" placeholder="Buscar cuenta..." @focus="
                            accountDropdownOpen = true
                            " />

                        <div v-if="
                            accountDropdownOpen &&
                            filteredAccounts()
                                .length
                        " class="absolute z-10 mt-1 w-full rounded-md border bg-white shadow">
                            <button v-for="account in filteredAccounts()" :key="account.id" type="button"
                                class="w-full px-3 py-2 text-left hover:bg-gray-100" @mousedown.prevent="
                                    selectAccount(
                                        account,
                                    )
                                    ">
                                {{
                                    account.code
                                }}
                                -
                                {{
                                    account.name
                                }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- COMPROBANTE -->

                <FormSelect label="Tipo comprobante" v-model="form.voucher_type_id
                    " :options="voucherTypeOptions
                        " required />

                <!-- SERIE -->

                <FormField id="serie" label="Serie" v-model="form.serie" maxlength="17"
                    placeholder="001-001-000000001" />

                <!-- FECHA -->

                <FormDatePicker id="emision" label="Fecha emisión" v-model="form.emision" />

                <!-- AUTORIZACION -->

                <div class="lg:col-span-2">
                    <FormField id="autorization" label="Autorización" v-model="form.autorization
                        " maxlength="49" />
                </div>

                <!-- FECHA AUTORIZACION -->

                <FormDatePicker id="autorized_at" label="Fecha autorización" v-model="form.autorized_at
                    " mode="datetime" />

                <!-- DOCUMENTO MODIFICADO -->

                <template v-if="
                    showModifyDocumentFields
                ">
                    <div class="lg:col-span-3 mt-4">
                        <h3 class="text-sm font-semibold">
                            Documento Modificado
                        </h3>
                    </div>

                    <FormSelect label="Comprobante original" v-model="form.voucher_type_modify_id
                        " :options="modifyVoucherOptions
                            " />

                    <FormField id="est_modify" label="Establecimiento" v-model="form.est_modify
                        " maxlength="3" />

                    <FormField id="poi_modify" label="Punto emisión" v-model="form.poi_modify
                        " maxlength="3" />

                    <FormField id="sec_modify" label="Secuencial" v-model="form.sec_modify
                        " maxlength="9" />

                    <div class="lg:col-span-2">
                        <FormField id="aut_modify" label="Autorización documento original" v-model="form.aut_modify
                            " maxlength="49" />
                    </div>
                </template>
            </div>
        </div>

        <!-- VALORES -->

        <div class="border-t p-6">
            <h2 class="mb-4 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                Valores
            </h2>

            <div class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
                <!-- NO IVA -->

                <FormField id="no_iva" label="No IVA" v-model="form.no_iva" type="number" step="0.01" />

                <!-- BASE 0 -->

                <FormField id="base0" label="Base 0%" v-model="form.base0" type="number" step="0.01" />

                <!-- SOLO SI NO ES NOTA VENTA -->

                <template v-if="!isNotaVenta">
                    <!-- BASE 12 -->

                    <FormField id="base12" label="Base 12%" v-model="form.base12
                        " type="number" step="0.01" />

                    <!-- IVA 12 -->

                    <FormField id="iva12" label="IVA 12%" v-model="form.iva12
                        " readonly />

                    <!-- BASE 15 -->

                    <FormField id="base15" label="Base 15%" v-model="form.base15
                        " type="number" step="0.01" />

                    <!-- IVA 15 -->

                    <FormField id="iva15" label="IVA 15%" v-model="form.iva15
                        " readonly />
                </template>

                <!-- DESCUENTO -->

                <FormField id="discount" label="Descuento" v-model="form.discount" type="number" step="0.01" />

                <!-- ICE -->

                <FormField id="ice" label="ICE" v-model="form.ice" type="number" step="0.01" />

                <!-- SUBTOTAL -->

                <FormField id="sub_total" label="Subtotal" :model-value="form.sub_total
                    " readonly />

                <!-- TOTAL -->

                <div class="rounded-lg bg-muted p-4">
                    <Label>Total</Label>

                    <Input :model-value="form.total
                        " readonly class="text-right text-lg font-semibold" />
                </div>
            </div>
        </div>

        <!-- BOTONES -->

        <div class="flex justify-end gap-3 border-t px-6 py-4">
            <Button variant="outline" type="button" as-child>
                <Link :href="route(
                    'tenant.shops.index',
                )
                    ">
                    Cancelar
                </Link>
            </Button>

            <Button type="submit" :disabled="form.processing
                ">
                {{
                    form.processing
                        ? "Guardando..."
                        : submitLabel
                }}
            </Button>
        </div>
    </form>
</template>