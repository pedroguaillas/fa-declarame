<script setup lang="ts">
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import {
    SidebarProvider,
    SidebarInset,
    SidebarTrigger,
} from "@/components/ui/sidebar";
import { Separator } from "@/components/ui/separator";
import TenantSidebar from "@/components/TenantSidebar.vue";
import FlashMessage from "@/components/FlashMessage.vue";
import CompanySelector from "@/components/CompanySelector.vue";
import UserNav from "@/components/UserNav.vue";
import type { PageProps } from "@/types";
import { TooltipProvider } from "@/components/ui/tooltip";

const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user);
const currentCompany = computed(() => page.props.currentCompany);
const companies = computed(() => page.props.companiesScope);
</script>

<template>
    <TooltipProvider>
        <SidebarProvider>
            <TenantSidebar />
            <SidebarInset>
                <header
                    class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-border px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
                >
                    <div class="flex items-center gap-2">
                        <SidebarTrigger class="-ml-1" />
                        <Separator orientation="vertical" class="mr-2 h-4" />

                        <CompanySelector
                            :current-company="currentCompany"
                            :companies="companies"
                        />
                    </div>

                    <UserNav :user="user" />
                </header>

                <main class="flex flex-1 flex-col gap-4 p-6">
                    <slot />
                </main>
            </SidebarInset>
            <FlashMessage />
        </SidebarProvider>
    </TooltipProvider>
</template>
