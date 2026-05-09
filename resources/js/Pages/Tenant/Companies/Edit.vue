<script setup lang="ts">
import HeaderForm from "@/components/Shared/HeaderForm.vue";
import FormField from "@/components/Shared/FormField.vue";
import FormSelect from "@/components/Shared/FormSelect.vue";
import FormSwitch from "@/components/Shared/FormSwitch.vue";
import PasswordField from "@/components/Shared/PasswordField.vue";
import RequiredFields from "@/components/Shared/RequiredFields.vue";
import { Button } from "@/components/ui/button";
import TenantLayout from "@/layouts/TenantLayout.vue";
import { ContributorType, Company } from "@/types/tenant";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { Loader2 } from "lucide-vue-next";
import { computed } from "vue";

const props = defineProps<{
    company: Company;
    contributorTypes: ContributorType[];
}>();

const contributorTypeOptions = computed(() => props.contributorTypes.map((c) => ({ id: c.id, label: c.description })));

const typeDeclarationOptions = [
    { id: "mensual", label: "Mensual" },
    { id: "semestral", label: "Semestral" },
];

const form = useForm({
    ruc: props.company.ruc,
    name: props.company.name,
    matrix_address: props.company.matrix_address,
    contributor_type_id: props.company.contributor_type_id,
    special_contribution: props.company.special_contribution,
    accounting: props.company.accounting,
    retention_agent: props.company.retention_agent,
    phantom_taxpayer: props.company.phantom_taxpayer,
    no_transactions: props.company.no_transactions,
    phone: props.company.phone ?? "",
    email: props.company.email ?? "",
    type_declaration: props.company.type_declaration ?? "",
    pass_sri: props.company.pass_sri ?? "",
});

function submit() {
    form.put(
        route("tenant.companies.update", {
            company: props.company.id,
        }),
    );
}
</script>

<template>
    <Head title="Editar contribuyente" />
    <TenantLayout>
        <HeaderForm title="Editar Contribuyente" :link-href="route('tenant.companies.index')" />

        <!-- Nota campos requeridos -->
        <RequiredFields />

        <div class="border-border bg-card overflow-hidden rounded-lg border">
            <form class="p-4" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <!-- RUC -->
                    <FormField
                        id="ruc"
                        label="RUC"
                        v-model="form.ruc"
                        :error="form.errors.ruc"
                        required
                        maxlength="13"
                        class="font-mono"
                    />

                    <!-- Nombre -->
                    <FormField
                        id="name"
                        label="Nombre"
                        v-model="form.name"
                        :error="form.errors.name"
                        required
                        maxlength="300"
                    />

                    <!-- Dirección -->
                    <div class="sm:col-span-2">
                        <FormField
                            id="matrix_address"
                            label="Dirección Matriz"
                            v-model="form.matrix_address"
                            :error="form.errors.matrix_address"
                            required
                            maxlength="300"
                        />
                    </div>

                    <!-- Teléfono -->
                    <FormField
                        id="phone"
                        label="Teléfono"
                        v-model="form.phone"
                        :error="form.errors.phone"
                        maxlength="20"
                    />

                    <!-- Email -->
                    <FormField
                        id="email"
                        label="Email"
                        v-model="form.email"
                        :error="form.errors.email"
                        type="email"
                        maxlength="50"
                    />

                    <!-- Tipo contribuyente -->
                    <FormSelect
                        label="Tipo de Contribuyente"
                        v-model="form.contributor_type_id"
                        :options="contributorTypeOptions"
                        :error="form.errors.contributor_type_id"
                        placeholder="Seleccionar tipo"
                        required
                    />

                    <!-- Tipo declaración -->
                    <FormSelect
                        label="Tipo de Declaración"
                        v-model="form.type_declaration"
                        :options="typeDeclarationOptions"
                        :error="form.errors.type_declaration"
                        placeholder="Seleccionar período"
                    />

                    <!-- Contribuyente especial -->
                    <FormField
                        id="special_contribution"
                        label="Contribuyente Especial N°"
                        v-model="form.special_contribution"
                        :error="form.errors.special_contribution"
                        type="number"
                        min="0"
                        placeholder="Número de resolución (opcional)"
                    />

                    <!-- Agente de retención -->
                    <FormField
                        id="retention_agent"
                        label="Agente de Retención N°"
                        v-model="form.retention_agent"
                        :error="form.errors.retention_agent"
                        type="number"
                        min="0"
                        placeholder="Número de resolución (opcional)"
                    />

                    <!-- Clave SRI -->
                    <PasswordField
                        id="pass_sri"
                        label="Clave SRI"
                        v-model="form.pass_sri"
                        :error="form.errors.pass_sri"
                        maxlength="50"
                        required
                    />
                </div>

                <!-- Características -->
                <div class="border-border mt-6 border-t pt-6">
                    <h2 class="text-foreground text-sm font-medium">Características</h2>
                    <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <FormSwitch label="Contabilidad" description="Lleva contabilidad" v-model="form.accounting" />
                        <FormSwitch
                            label="Contribuyente fantasma"
                            description="Contribuyente fantasma"
                            v-model="form.phantom_taxpayer"
                        />
                        <FormSwitch
                            label="Sin transacciones"
                            description="Sin transacciones"
                            v-model="form.no_transactions"
                        />
                    </div>
                </div>

                <!-- Acciones -->
                <div class="mt-6 flex items-center justify-end gap-3">
                    <Button variant="outline" as-child>
                        <Link :href="route('tenant.companies.index')">Cancelar</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="mr-1.5 size-4 animate-spin" />
                        {{ form.processing ? "Guardando..." : "Actualizar" }}
                    </Button>
                </div>
            </form>
        </div>
    </TenantLayout>
</template>
