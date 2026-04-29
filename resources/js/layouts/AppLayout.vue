<script setup lang="ts">
import { useScroll } from "@vueuse/core";
import { ref } from "vue";
import AppSidebar from "@/components/AppSidebar.vue";
import FlashMessage from "@/components/FlashMessage.vue";
import { Separator } from "@/components/ui/separator";
import {
    SidebarInset,
    SidebarProvider,
    SidebarTrigger,
} from "@/components/ui/sidebar";

const mainRef = ref<HTMLElement | null>(null);
const { y } = useScroll(mainRef);
</script>

<template>
    <SidebarProvider>
        <AppSidebar />
        <SidebarInset class="min-w-0">
            <header
                :class="[
                    'sticky top-0 z-10 flex h-16 shrink-0 items-center gap-2 border-b border-border transition-all duration-200 ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12',
                    y > 0
                        ? 'bg-background/80 backdrop-blur-sm shadow-sm'
                        : 'bg-background',
                ]"
            >
                <div class="flex items-center gap-2 px-4">
                    <SidebarTrigger class="-ml-1" />
                    <Separator
                        orientation="vertical"
                        class="mr-2 data-[orientation=vertical]:h-4"
                    />
                </div>
            </header>
            <main
                ref="mainRef"
                class="flex flex-1 flex-col overflow-y-auto p-6"
            >
                <slot />
            </main>
        </SidebarInset>
        <FlashMessage />
    </SidebarProvider>
</template>
