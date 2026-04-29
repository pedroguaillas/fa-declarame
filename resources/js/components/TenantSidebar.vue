<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import type { PageProps } from "@/types";
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from "@/components/ui/sidebar";
import { Building2 } from "lucide-vue-next";
import { useNavigation } from "@/composables/useNavigation";
import NavMain from "./NavMain.vue";

const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user);
const { navItems } = useNavigation(user.value);
const tenant = computed(() => page.props.tenant);
</script>

<template>
    <Sidebar collapsible="icon">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" class="pointer-events-none">
                        <div
                            class="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                        >
                            <Building2 class="size-4" />
                        </div>
                        <div
                            class="grid flex-1 text-left text-sm leading-tight"
                        >
                            <span class="truncate font-semibold">{{
                                tenant?.name
                            }}</span>
                            <span
                                class="truncate text-xs text-sidebar-foreground/60"
                                >Panel de empresa</span
                            >
                        </div>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="navItems" />
        </SidebarContent>

        <SidebarRail />
    </Sidebar>
</template>
