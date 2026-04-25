<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { useTheme } from "@/composables/useTheme";
import type { PageProps } from "@/types";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from "@/components/ui/sidebar";
import { LayoutDashboard, Users, Settings, Building2 } from "lucide-vue-next";

const page = usePage<PageProps & { tenant: { id: string; name: string } }>();
const tenant = computed(() => page.props.tenant);

const navItems = [
    {
        title: "Dashboard",
        url: route("tenant.dashboard"),
        icon: LayoutDashboard,
    },
    {
        title: "Empleados",
        url: route("employees.index"),
        icon: Users,
    },
    {
        title: "Mi perfil",
        url: route("tenant.profile.edit"),
        icon: Settings,
    },
];
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
            <SidebarGroup>
                <SidebarGroupLabel>Navegación</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in navItems" :key="item.title">
                        <SidebarMenuButton :tooltip="item.title" as-child>
                            <Link :href="item.url">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarRail />
    </Sidebar>
</template>
