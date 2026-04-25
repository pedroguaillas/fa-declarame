<script setup lang="ts">
import { ShieldCheck } from "lucide-vue-next";
import {
    SidebarMenu,
    SidebarMenuItem,
    SidebarMenuButton,
} from "@/components/ui/sidebar";
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <SidebarMenuButton size="lg" class="pointer-events-none">
                <div
                    class="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                >
                    <ShieldCheck class="size-4" />
                </div>
                <div class="grid flex-1 text-left text-sm leading-tight">
                    <span class="truncate font-semibold">AliceSoft</span>
                    <span class="truncate text-xs text-sidebar-foreground/60"
                        >Panel de control</span
                    >
                </div>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
