<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";

import HeaderForm from "@/components/Shared/HeaderForm.vue";
import TenantLayout from "@/layouts/TenantLayout.vue";
import ShopForm from "./partials/ShopForm.vue";
import { Shop, VoucherType } from "@/types/tenant";

const props = defineProps<{
    shop: Shop;
    voucherTypes: VoucherType[];
}>();

function toInputDate(d: string): string {
    const parts = d.split("-");
    return parts.length === 3 && parts[2].length === 4 ? `${parts[2]}-${parts[1]}-${parts[0]}` : d;
}

function toInputDatetime(d: string): string {
    return d.slice(0, 16);
}

const form = useForm({
    contact_id: props.shop.contact_id,
    voucher_type_id: props.shop.voucher_type_id,
    type_identification: "" as string | undefined,
    emision: props.shop.emision ? toInputDate(props.shop.emision) : "",
    autorization: props.shop.autorization,
    autorized_at: props.shop.autorized_at ? toInputDatetime(props.shop.autorized_at) : "",
    serie: props.shop.serie,
    sub_total: props.shop.sub_total,
    no_iva: props.shop.no_iva,
    exempt: props.shop.exempt,
    base0: props.shop.base0,
    base5: props.shop.base5,
    base8: props.shop.base8,
    base12: props.shop.base12,
    base15: props.shop.base15,
    iva5: props.shop.iva5,
    iva8: props.shop.iva8,
    iva12: props.shop.iva12,
    iva15: props.shop.iva15,
    aditional_discount: props.shop.aditional_discount,
    discount: props.shop.discount,
    ice: props.shop.ice,
    total: props.shop.total,
    state: props.shop.state,
    serie_retention: props.shop.serie_retention ?? "",
    date_retention: props.shop.date_retention ?? "",
    state_retention: props.shop.state_retention ?? "",
    autorization_retention: props.shop.autorization_retention ?? "",
    retention_at: props.shop.retention_at ?? "",

    // DOCUMENTO MODIFICADO
    voucher_type_modify_id: props.shop.voucher_type_modify_id ?? (null as number | string | null),
    est_modify: props.shop.est_modify ?? "",
    poi_modify: props.shop.poi_modify ?? "",
    sec_modify: props.shop.sec_modify ?? "",
    aut_modify: props.shop.aut_modify ?? "",
});

function submit() {
    form.put(route("tenant.shops.update", { shop: props.shop.id }));
}
</script>

<template>
    <Head title="Editar compra" />
    <TenantLayout>
        <div class="flex h-full flex-col gap-4">
            <HeaderForm title="Editar Compra" :link-href="route('tenant.shops.index')" />

            <div class="border-border bg-card overflow-hidden rounded-lg border">
                <ShopForm
                    :form="form"
                    :voucher-types="props.voucherTypes"
                    :initial-contact-identification="props.shop.contact?.identification ?? ''"
                    :initial-contact-name="props.shop.contact?.name ?? ''"
                    :with-cedula="props.shop.data_additional?.with_cedula ?? false"
                    submit-label="Actualizar compra"
                    @submit="submit"
                />
            </div>
        </div>
    </TenantLayout>
</template>
