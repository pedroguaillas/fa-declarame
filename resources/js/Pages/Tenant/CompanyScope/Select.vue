<script setup lang="ts">
import { useForm } from "@inertiajs/vue3";
import TenantLayout from "@/layouts/TenantLayout.vue";
import { Button } from "@/components/ui/button";
import { Building2 } from "lucide-vue-next";
import { CompanyScope } from "@/types/tenant";

const props = defineProps<{
    companies: CompanyScope[];
}>();

const form = useForm({ company_id: null as number | null });

function select(company: CompanyScope) {
    form.company_id = company.id;
    form.post(route("tenant.company-scope.store"));
}
</script>

<template>
    <TenantLayout>
        <div class="flex h-full flex-col items-center justify-center gap-6">
            <div class="text-center">
                <Building2 class="text-muted-foreground mx-auto mb-3 size-10" />
                <h2 class="text-xl font-black tracking-tight uppercase">
                    Selecciona una empresa
                </h2>
                <p class="text-muted-foreground mt-1 text-sm">
                    Elige la empresa con la que deseas trabajar.
                </p>
            </div>

            <div class="w-full max-w-sm space-y-2">
                <Button
                    v-for="company in companies"
                    :key="company.id"
                    variant="outline"
                    class="w-full justify-start gap-3"
                    :disabled="form.processing"
                    @click="select(company)"
                >
                    <Building2 class="size-4 shrink-0 opacity-60" />
                    <div class="min-w-0 text-left">
                        <p class="truncate text-sm font-medium">
                            {{ company.name }}
                        </p>
                        <p
                            class="truncate font-mono text-xs text-muted-foreground"
                        >
                            {{ company.ruc }}
                        </p>
                    </div>
                </Button>
            </div>
        </div>
    </TenantLayout>
</template>
