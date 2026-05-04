```vue
<script setup lang="ts">
import { computed, ref, watch, watchEffect } from "vue";
import { Link } from "@inertiajs/vue3";

import FormField from "@/components/Shared/FormField.vue";
import FormSelect from "@/components/Shared/FormSelect.vue";
import FormDatePicker from "@/components/Shared/FormDatePicker.vue";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

import type { VoucherType, IdentificationType } from "@/types/tenant";

interface FormErrors {
  [key: string]: string | undefined;
}

interface OrderFormData {
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

    identificationTypes: IdentificationType[];
  }>(),
  {
    initialContactIdentification: "",

    initialContactName: "",

    identificationTypes: () => [],
  }
);

const emit = defineEmits<{ submit: [] }>();

// ─────────────────────────────────────────────
// OPTIONS
// ─────────────────────────────────────────────

const identificationOptions = computed(() =>
  props.identificationTypes.map((i) => ({
    id: i.id ?? 0,
    label: i.description,
  }))
);

const voucherTypeOptions = computed(() =>
  props.voucherTypes.map((v) => ({
    id: v.id,
    label: `${v.code} - ${v.description}`,
  }))
);

// ─────────────────────────────────────────────
// CONTACTO
// ─────────────────────────────────────────────

const contactIdentification = ref(props.initialContactIdentification ?? "");

const contactName = ref(props.initialContactName ?? "");

const contactResolving = ref(false);

const contactError = ref<string | null>(null);

const showCreateContactModal = ref(false);

const createContactForm = ref({
  identification_type_id: "",

  identification: "",

  name: "",

  phone: "",

  email: "",

  address: "",
});

// ─────────────────────────────────────────────
// LONGITUD IDENTIFICACION
// ─────────────────────────────────────────────

const maxIdentificationLength = computed(() => {
  if (!props.form.type_identification) return 13;

  const selected = props.identificationTypes.find(
    (i) => i.id == props.form.type_identification
  );

  if (!selected) return 13;

  const label = selected.description.trim().toLowerCase();

  if (label.includes("cedula")) return 10;

  if (label.includes("ruc")) return 13;

  if (label === "consumidor final" || label.includes("consumidor")) {
    return 13;
  }

  if (label.includes("pasaporte")) return 20;

  return 20;
});

// ─────────────────────────────────────────────
// CONSUMIDOR FINAL AUTOMATICO
// ─────────────────────────────────────────────

watch(
  () => props.form.type_identification,
  async (val) => {
    const selected = props.identificationTypes.find((i) => i.id == val);

    if (!selected) return;

    const label = selected.description.trim().toLowerCase();

    // CONSUMIDOR FINAL
    if (label === "consumidor final" || label.includes("consumidor")) {
      // PONE AUTOMATICAMENTE LOS 13 NUEVES
      contactIdentification.value = "9999999999999";

      contactName.value = "";

      props.form.contact_id = null;

      // EJECUTA AUTOMATICAMENTE
      // EL BUSCADOR DE CONTACTOS

      contactResolving.value = true;

      try {
        const res = await fetch(
          route("tenant.contacts.resolve", {
            identification: "9999999999999",
          }),
          {
            headers: {
              Accept: "application/json",
            },
          }
        );

        if (!res.ok) {
          contactName.value = "CONSUMIDOR FINAL";

          props.form.contact_id = null;

          return;
        }

        const data = await res.json();

        contactName.value = data.name;

        props.form.contact_id = data.id;
      } catch {
        contactName.value = "CONSUMIDOR FINAL";
      } finally {
        contactResolving.value = false;
      }

      return;
    }

    // PASAPORTE
    if (label.includes("pasaporte")) {
      contactIdentification.value = "";

      contactName.value = "";

      props.form.contact_id = null;

      return;
    }

    // OTROS
    contactIdentification.value = "";

    contactName.value = "";

    props.form.contact_id = null;
  }
);

// ─────────────────────────────────────────────
// BUSQUEDA CONTACTO
// ─────────────────────────────────────────────

watch(contactIdentification, async (identification) => {
  const selected = props.identificationTypes.find(
    (i) => i.id == props.form.type_identification
  );

  const label = selected?.description?.trim().toLowerCase() || "";

  // CONSUMIDOR FINAL
  if (label === "consumidor final" || label.includes("consumidor")) {
    return;
  }

  const expectedLength = maxIdentificationLength.value;

  if (
    props.form.type_identification &&
    identification.length !== expectedLength &&
    expectedLength !== 20
  ) {
    contactName.value = "";

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
      }
    );

    if (!res.ok) {
      contactName.value = "";

      props.form.contact_id = null;

      showCreateContactModal.value = true;

      createContactForm.value.identification = identification;

      return;
    }

    const data = await res.json();

    contactName.value = data.name;

    props.form.contact_id = data.id;
  } catch {
    contactError.value = "Error al consultar contacto.";
  } finally {
    contactResolving.value = false;
  }
});

// ─────────────────────────────────────────────
// GUARDAR CONTACTO
// ─────────────────────────────────────────────

async function saveContact() {
  try {
    const res = await fetch(route("tenant.contacts.store"), {
      method: "POST",

      headers: {
        "Content-Type": "application/json",

        Accept: "application/json",

        "X-CSRF-TOKEN":
          (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
            ?.content || "",
      },

      body: JSON.stringify(createContactForm.value),
    });

    if (!res.ok) {
      alert("Error al guardar contacto");

      return;
    }

    const data = await res.json();

    props.form.contact_id = data.id;

    contactName.value = data.name;

    contactIdentification.value = data.identification;

    showCreateContactModal.value = false;
  } catch {
    alert("Error al guardar contacto");
  }
}

// ─────────────────────────────────────────────
// VALIDACION
// ─────────────────────────────────────────────

function validateVoucherWithIdentification() {
  const selectedIdentification = props.identificationTypes.find(
    (i) => i.id == props.form.type_identification
  );

  const identificationLabel = selectedIdentification?.description?.toLowerCase() || "";

  if (["01", "02"].includes(String(props.form.voucher_type_id))) {
    if (
      !identificationLabel.includes("ruc") &&
      !identificationLabel.includes("consumidor")
    ) {
      alert("Factura y Nota de Venta solo permiten RUC o Consumidor Final.");

      return false;
    }
  }

  return true;
}

function handleSubmit() {
  if (!validateVoucherWithIdentification()) return;

  emit("submit");
}

// ─────────────────────────────────────────────
// CALCULOS
// ─────────────────────────────────────────────

const n = (v: number | string) => parseFloat(String(v)) || 0;

watch(
  () => props.form.base12,
  (val) => {
    props.form.iva12 = parseFloat((n(val) * 0.12).toFixed(2));
  }
);

watch(
  () => props.form.base15,
  (val) => {
    props.form.iva15 = parseFloat((n(val) * 0.15).toFixed(2));
  }
);

const computedSubTotal = computed(
  () =>
    n(props.form.no_iva) +
    n(props.form.base0) +
    n(props.form.base12) +
    n(props.form.base15)
);

const computedTotal = computed(
  () =>
    computedSubTotal.value +
    n(props.form.iva12) +
    n(props.form.iva15) +
    n(props.form.ice) -
    n(props.form.discount) -
    n(props.form.aditional_discount)
);

watchEffect(() => {
  props.form.sub_total = parseFloat(computedSubTotal.value.toFixed(2));

  props.form.total = parseFloat(computedTotal.value.toFixed(2));
});
</script>

<template>
  <form @submit.prevent="handleSubmit">
    <!-- DOCUMENTO -->

    <div class="p-6">
      <h2
        class="mb-4 text-xs font-semibold uppercase tracking-widest text-muted-foreground"
      >
        Documento
      </h2>

      <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <!-- TIPO IDENTIFICACION -->

        <FormSelect
          label="Tipo identificación"
          v-model="form.type_identification"
          :options="identificationOptions"
          required
        />

        <!-- IDENTIFICACION -->

        <div class="flex flex-col gap-1.5">
          <Label> Identificación </Label>

          <Input
            v-model="contactIdentification"
            type="text"
            :maxlength="maxIdentificationLength"
          />
        </div>

        <!-- NOMBRE -->

        <div class="flex flex-col gap-1.5">
          <Label>Nombre</Label>

          <Input :model-value="contactName" readonly />
        </div>

        <!-- COMPROBANTE -->

        <FormSelect
          label="Tipo comprobante"
          v-model="form.voucher_type_id"
          :options="voucherTypeOptions"
          required
        />

        <!-- SERIE -->

        <FormField
          id="serie"
          label="Serie"
          v-model="form.serie"
          maxlength="17"
          placeholder="001-001-000000001"
        />

        <!-- FECHA -->

        <FormDatePicker id="emision" label="Fecha emisión" v-model="form.emision" />

        <!-- AUTORIZACION -->

        <div class="lg:col-span-2">
          <FormField
            id="autorization"
            label="Autorización"
            v-model="form.autorization"
            maxlength="49"
          />
        </div>

        <!-- FECHA AUTORIZACION -->

        <FormDatePicker
          id="autorized_at"
          label="Fecha autorización"
          v-model="form.autorized_at"
          mode="datetime"
        />
      </div>
    </div>

    <!-- VALORES -->

    <div class="border-t p-6">
      <h2
        class="mb-4 text-xs font-semibold uppercase tracking-widest text-muted-foreground"
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
        />

        <FormField
          id="base0"
          label="Base 0%"
          v-model="form.base0"
          type="number"
          step="0.01"
        />

        <FormField
          id="base12"
          label="Base 12%"
          v-model="form.base12"
          type="number"
          step="0.01"
        />

        <FormField id="iva12" label="IVA 12%" v-model="form.iva12" readonly />

        <FormField
          id="base15"
          label="Base 15%"
          v-model="form.base15"
          type="number"
          step="0.01"
        />

        <FormField id="iva15" label="IVA 15%" v-model="form.iva15" readonly />

        <FormField
          id="discount"
          label="Descuento"
          v-model="form.discount"
          type="number"
          step="0.01"
        />

        <FormField id="ice" label="ICE" v-model="form.ice" type="number" step="0.01" />

        <FormField
          id="sub_total"
          label="Subtotal"
          :model-value="form.sub_total"
          readonly
        />

        <div class="rounded-lg bg-muted p-4">
          <Label>Total</Label>

          <Input
            :model-value="form.total"
            readonly
            class="text-right text-lg font-semibold"
          />
        </div>
      </div>
    </div>

    <!-- MODAL CONTACTO -->

    <div
      v-if="showCreateContactModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    >
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
        <h2 class="mb-4 text-lg font-semibold">Crear contacto</h2>

        <div class="grid gap-4">
          <FormSelect
            label="Tipo identificación"
            v-model="createContactForm.identification_type_id"
            :options="identificationOptions"
          />

          <FormField
            id="identification"
            label="Identificación"
            v-model="createContactForm.identification"
          />

          <FormField id="name" label="Nombre" v-model="createContactForm.name" />

          <FormField id="phone" label="Teléfono" v-model="createContactForm.phone" />

          <FormField id="email" label="Email" v-model="createContactForm.email" />

          <FormField id="address" label="Dirección" v-model="createContactForm.address" />
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <Button type="button" variant="outline" @click="showCreateContactModal = false">
            Cancelar
          </Button>

          <Button type="button" @click="saveContact"> Guardar contacto </Button>
        </div>
      </div>
    </div>

    <!-- BOTONES -->

    <div class="flex justify-end gap-3 border-t px-6 py-4">
      <Button variant="outline" type="button" as-child>
        <Link :href="route('tenant.orders.index')"> Cancelar </Link>
      </Button>

      <Button type="submit" :disabled="form.processing">
        {{ form.processing ? "Guardando..." : submitLabel }}
      </Button>
    </div>
  </form>
</template>
```
