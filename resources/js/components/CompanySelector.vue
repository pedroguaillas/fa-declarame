<script setup lang="ts">
import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { Building2, ChevronsUpDown, Check } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import type { CompanyScope } from "@/types/tenant";

const props = defineProps<{
    currentCompany?: CompanyScope | null;
    companies: CompanyScope[];
}>();

const open = ref(false);

function selectCompany(company: CompanyScope) {
    open.value = false;
    router.post(
        route("tenant.company-scope.store"),
        { company_id: company.id },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                variant="ghost"
                size="sm"
                class="flex h-8 items-center gap-2 px-2 text-sm font-medium"
                :class="!currentCompany ? 'text-destructive' : ''"
            >
                <Building2 class="size-4 shrink-0" />
                <span class="hidden max-w-40 truncate sm:inline-block">
                    {{ currentCompany?.name ?? "Seleccionar empresa" }}
                </span>
                <ChevronsUpDown class="size-3.5 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-72 p-0" align="start">
            <Command>
                <CommandInput placeholder="Buscar empresa..." />
                <CommandList>
                    <CommandEmpty>No se encontró ninguna empresa.</CommandEmpty>
                    <CommandGroup heading="Empresas">
                        <CommandItem
                            v-for="company in companies"
                            :key="company.id"
                            :value="company.name"
                            class="cursor-pointer"
                            @select="selectCompany(company)"
                        >
                            <Building2
                                class="mr-2 size-4 shrink-0 opacity-60"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ company.name }}
                                </p>
                                <p
                                    class="truncate font-mono text-xs text-muted-foreground"
                                >
                                    {{ company.ruc }}
                                </p>
                            </div>
                            <Check
                                v-if="currentCompany?.id === company.id"
                                class="ml-2 size-4 shrink-0 text-primary"
                            />
                        </CommandItem>
                    </CommandGroup>
                </CommandList>
            </Command>
        </PopoverContent>
    </Popover>
</template>
