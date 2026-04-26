<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";

import HeaderForm from "@/components/Shared/HeaderForm.vue";
import TenantLayout from "@/layouts/TenantLayout.vue";
import ShopForm from "./partials/ShopForm.vue";
import { Account, VoucherType } from "@/types/tenant";

const props = defineProps<{
    voucherTypes: VoucherType[];
    accounts: Account[];
}>();

const form = useForm({
    acount_id: null as number | null,
    contact_id: null as number | null,
    voucher_type_id: "" as number | string,
    emision: "",
    autorization: "",
    autorized_at: "",
    serie: "",
    sub_total: 0,
    no_iva: 0,
    base0: 0,
    base5: 0,
    base8: 0,
    base12: 0,
    base15: 0,
    iva5: 0,
    iva8: 0,
    iva12: 0,
    iva15: 0,
    aditional_discount: 0,
    discount: 0,
    ice: 0,
    total: 0,
    state: "CREADO",
    serie_retention: "",
    date_retention: "",
    state_retention: "",
    autorization_retention: "",
    retention_at: "",
});

function submit() {
    form.post(route("tenant.shops.store"));
}
</script>

<template>
    <TenantLayout>
        <div class="flex h-full flex-col gap-4">
            <HeaderForm
                title="Nueva Compra"
                :link-href="route('tenant.shops.index')"
            />

            <div
                class="border-border bg-card overflow-hidden rounded-lg border"
            >
                <ShopForm
                    :form="form"
                    :voucher-types="props.voucherTypes"
                    :accounts="props.accounts"
                    submit-label="Registrar compra"
                    @submit="submit"
                />
            </div>
        </div>
    </TenantLayout>
</template>
