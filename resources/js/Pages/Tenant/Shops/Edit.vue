<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";

import HeaderForm from "@/components/Shared/HeaderForm.vue";
import TenantLayout from "@/layouts/TenantLayout.vue";
import ShopForm from "./partials/ShopForm.vue";
import { Account, Shop, VoucherType } from "@/types/tenant";

const props = defineProps<{
    shop: Shop;
    voucherTypes: VoucherType[];
    accounts: Account[];
}>();

// Model cast 'date:d-m-Y' serializes as "17-04-2026"; input[type=date] needs "2026-04-17"
function toInputDate(d: string): string {
    const parts = d.split("-");
    return parts.length === 3 && parts[2].length === 4
        ? `${parts[2]}-${parts[1]}-${parts[0]}`
        : d;
}

// Model cast 'datetime' serializes as "2026-04-16T12:28:31.000000Z"; datetime-local needs "2026-04-16T12:28"
function toInputDatetime(d: string): string {
    return d.slice(0, 16);
}

const form = useForm({
    acount_id: props.shop.acount_id,
    contact_id: props.shop.contact_id,
    voucher_type_id: props.shop.voucher_type_id,
    emision: props.shop.emision ? toInputDate(props.shop.emision) : "",
    autorization: props.shop.autorization,
    autorized_at: props.shop.autorized_at
        ? toInputDatetime(props.shop.autorized_at)
        : "",
    serie: props.shop.serie,
    sub_total: props.shop.sub_total,
    no_iva: props.shop.no_iva,
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
});

function submit() {
    form.put(route("tenant.shops.update", { shop: props.shop.id }));
}
</script>

<template>
    <TenantLayout>
        <div class="flex h-full flex-col gap-4">
            <HeaderForm
                title="Editar Compra"
                :link-href="route('tenant.shops.index')"
            />

            <div
                class="border-border bg-card overflow-hidden rounded-lg border"
            >
                <ShopForm
                    :form="form"
                    :voucher-types="props.voucherTypes"
                    :accounts="props.accounts"
                    :initial-contact-identification="
                        props.shop.contact?.identification ?? ''
                    "
                    :initial-contact-name="props.shop.contact?.name ?? ''"
                    submit-label="Actualizar compra"
                    @submit="submit"
                />
            </div>
        </div>
    </TenantLayout>
</template>
