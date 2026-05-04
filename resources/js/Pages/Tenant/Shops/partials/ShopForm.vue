<script setup lang="ts">
import { computed, ref, watch, watchEffect } from "vue";
import { Link } from "@inertiajs/vue3";

import FormField from "@/components/Shared/FormField.vue";
import FormSelect from "@/components/Shared/FormSelect.vue";
import FormDatePicker from "@/components/Shared/FormDatePicker.vue";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

import { Account, IdentificationType, VoucherType } from "@/types/tenant";

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
  iva12: number | string;

  base15: number | string;
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
  }
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
  }))
);

const voucherTypeOptions = computed(() => {
  const selectedIdentification = props.identificationTypes.find(
    (i) => i.id == props.form.type_identification
  );

  // SI NO HAY IDENTIFICACION
  if (!selectedIdentification) {
    return props.voucherTypes.map((v) => ({
      id: v.id,
      label: `${v.code} - ${v.description}`,
    }));
  }

  const label = selectedIdentification.description.trim().toLowerCase();

  // ─────────────────────────────
  // CEDULA O PASAPORTE
  // SOLO LIQUIDACION DE COMPRAS
  // ─────────────────────────────

  if (label.includes("cedula") || label.includes("pasaporte")) {
    return props.voucherTypes
      .filter((v) => v.code === "03")
      .map((v) => ({
        id: v.id,
        label: `${v.code} - ${v.description}`,
      }));
  }

  // ─────────────────────────────
  // RUC Y OTROS
  // OCULTAR LIQUIDACION
  // ─────────────────────────────

  return props.voucherTypes
    .filter((v) => v.code !== "03")
    .map((v) => ({
      id: v.id,
      label: `${v.code} - ${v.description}`,
    }));
});

const modifyVoucherOptions = computed(() =>
  props.voucherTypes
    .filter((v) => ["01", "02", "03"].includes(v.code))
    .map((v) => ({
      id: v.id,
      label: `${v.code} - ${v.description}`,
    }))
);

// ─────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────

const selectedVoucher = computed(() =>
  props.voucherTypes.find((v) => v.id == props.form.voucher_type_id)
);

const voucherCode = computed(() => selectedVoucher.value?.code || "");

const showModifyDocumentFields = computed(() => ["04", "05"].includes(voucherCode.value));

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

const maxIdentificationLength = computed(() => {
  if (!props.form.type_identification) return 13;

  const selected = props.identificationTypes.find(
    (i) => i.id == props.form.type_identification
  );

  if (!selected) return 13;

  const label = selected.description.toLowerCase();

  if (label.includes("cedula")) return 10;

  if (label.includes("ruc")) return 13;

  return 20;
});

watch(contactIdentification, async (identification) => {
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
  const identification = contactIdentification.value;

  const selectedIdentification = props.identificationTypes.find(
    (i) => i.id == props.form.type_identification
  );

  const identificationLabel = selectedIdentification?.description?.toLowerCase() || "";

  // FACTURA Y NOTA VENTA
  // SOLO RUC

  if (["01", "02"].includes(voucherCode.value)) {
    if (!identificationLabel.includes("ruc") || identification.length !== 13) {
      alert("Factura y Nota de Venta solo permiten RUC.");

      return false;
    }
  }

  // LIQUIDACION DE COMPRA
  // SOLO CEDULA O PASAPORTE

  if (voucherCode.value === "03") {
    const isCedula = identificationLabel.includes("cedula");

    const isPassport = identificationLabel.includes("pasaporte");

    if (!isCedula && !isPassport) {
      alert("La liquidación de compra solo permite cédula o pasaporte.");

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
// CUENTAS
// ─────────────────────────────────────────────

const accountQuery = ref("");

const accountDropdownOpen = ref(false);

watch(
  () => props.form.acount_id,
  (id) => {
    if (id && !accountQuery.value) {
      const found = props.accounts.find((a) => a.id === id);

      if (found) {
        accountQuery.value = `${found.code} - ${found.name}`;
      }
    }
  },
  {
    immediate: true,
  }
);

function filteredAccounts(): Account[] {
  const q = accountQuery.value.trim().toLowerCase();

  if (!q) return props.accounts.slice(0, 8);

  return props.accounts
    .filter((a) => a.code.toLowerCase().includes(q) || a.name.toLowerCase().includes(q))
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

const n = (v: number | string) => parseFloat(String(v)) || 0;

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
    n(props.form.iva15) +
    n(props.form.iva12) +
    n(props.form.ice) -
    n(props.form.discount) -
    n(props.form.aditional_discount)
);

watchEffect(() => {
  props.form.sub_total = parseFloat(computedSubTotal.value.toFixed(2));

  props.form.total = parseFloat(computedTotal.value.toFixed(2));
});

watch(
  () => props.form.base12,
  (val) => {
    props.form.iva12 = parseFloat((n(val) * 0.12).toFixed(2));
  }
);
</script>

<template>
  <form @submit.prevent="handleSubmit">
    <div class="p-6">
      <h2
        class="mb-4 text-xs font-semibold uppercase tracking-widest text-muted-foreground"
      >
        Documento
      </h2>

      <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <FormSelect
          label="Tipo identificación"
          v-model="form.type_identification"
          :options="identificationOptions"
          required
        />

        <div class="flex flex-col gap-1.5">
          <Label> Identificación </Label>

          <Input v-model="contactIdentification" :maxlength="maxIdentificationLength" />
        </div>

        <div class="flex flex-col gap-1.5">
          <Label>Nombre</Label>

          <Input :model-value="contactName" readonly />
        </div>

        <div class="sm:col-span-2 lg:col-span-3">
          <Label> Cuenta contable </Label>

          <div class="relative">
            <Input
              v-model="accountQuery"
              placeholder="Buscar cuenta..."
              @focus="accountDropdownOpen = true"
            />

            <div
              v-if="accountDropdownOpen && filteredAccounts().length"
              class="absolute z-10 mt-1 w-full rounded-md border bg-white shadow"
            >
              <button
                v-for="account in filteredAccounts()"
                :key="account.id"
                type="button"
                class="w-full px-3 py-2 text-left hover:bg-gray-100"
                @mousedown.prevent="selectAccount(account)"
              >
                {{ account.code }}
                -
                {{ account.name }}
              </button>
            </div>
          </div>
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
        />

        <FormDatePicker id="emision" label="Fecha emisión" v-model="form.emision" />

        <div class="lg:col-span-2">
          <FormField
            id="autorization"
            label="Autorización"
            v-model="form.autorization"
            maxlength="49"
          />
        </div>

        <FormDatePicker
          id="autorized_at"
          label="Fecha autorización"
          v-model="form.autorized_at"
          mode="datetime"
        />

        <template v-if="showModifyDocumentFields">
          <div class="mt-4 lg:col-span-3">
            <h3 class="text-sm font-semibold">Documento Modificado</h3>
          </div>

          <FormSelect
            label="Comprobante original"
            v-model="form.voucher_type_modify_id"
            :options="modifyVoucherOptions"
          />

          <FormField
            id="est_modify"
            label="Establecimiento"
            v-model="form.est_modify"
            maxlength="3"
          />

          <FormField
            id="poi_modify"
            label="Punto emisión"
            v-model="form.poi_modify"
            maxlength="3"
          />

          <FormField
            id="sec_modify"
            label="Secuencial"
            v-model="form.sec_modify"
            maxlength="9"
          />

          <div class="lg:col-span-2">
            <FormField
              id="aut_modify"
              label="Autorización documento original"
              v-model="form.aut_modify"
              maxlength="49"
            />
          </div>
        </template>
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

    <!-- MODAL -->

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
        <Link :href="route('tenant.shops.index')"> Cancelar </Link>
      </Button>

      <Button type="submit" :disabled="form.processing">
        {{ form.processing ? "Guardando..." : submitLabel }}
      </Button>
    </div>
  </form>
</template>
