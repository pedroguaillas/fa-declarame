<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";

import HeaderForm from "@/components/Shared/HeaderForm.vue";
import TenantLayout from "@/layouts/TenantLayout.vue";
import OrderForm from "./partials/OrderForm.vue";
import type { Order, VoucherType } from "@/types/tenant";

const props = defineProps<{
    order: Order;
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
    contact_id: props.order.contact_id,
    voucher_type_id: props.order.voucher_type_id,
    type_identification: "" as string | undefined,
    emision: props.order.emision ? toInputDate(props.order.emision) : "",
    autorization: props.order.autorization ?? "",
    autorized_at: props.order.autorized_at ? toInputDatetime(props.order.autorized_at) : "",
    serie: props.order.serie,
    sub_total: props.order.sub_total,
    no_iva: props.order.no_iva,
    exempt: props.order.exempt,
    base0: props.order.base0,
    base5: props.order.base5,
    base8: props.order.base8,
    base12: props.order.base12,
    base15: props.order.base15,
    iva5: props.order.iva5,
    iva8: props.order.iva8,
    iva12: props.order.iva12,
    iva15: props.order.iva15,
    aditional_discount: props.order.aditional_discount,
    discount: props.order.discount,
    ice: props.order.ice,
    total: props.order.total,
    state: props.order.state,
    serie_retention: props.order.serie_retention ?? "",
    date_retention: props.order.date_retention ?? "",
    state_retention: props.order.state_retention ?? "",
    autorization_retention: props.order.autorization_retention ?? "",
    retention_at: props.order.retention_at ?? "",
});

function submit() {
    form.put(route("tenant.orders.update", { order: props.order.id }));
}
</script>

<template>
    <Head title="Editar venta" />
    <TenantLayout>
        <div class="flex h-full flex-col gap-4">
            <HeaderForm title="Editar Venta" :link-href="route('tenant.orders.index')" />

            <div class="border-border bg-card overflow-hidden rounded-lg border">
                <OrderForm
                    :form="form"
                    :voucher-types="props.voucherTypes"
                    :initial-contact-identification="props.order.contact?.identification ?? ''"
                    :initial-contact-name="props.order.contact?.name ?? ''"
                    submit-label="Actualizar venta"
                    @submit="submit"
                />
            </div>
        </div>
    </TenantLayout>
</template>
