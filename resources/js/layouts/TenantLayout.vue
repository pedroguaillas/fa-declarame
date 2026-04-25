<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { useTheme } from "@/composables/useTheme";
import FlashMessage from "@/components/FlashMessage.vue";
import { Button } from "@/components/ui/button";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Separator } from "@/components/ui/separator";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    SidebarProvider,
    SidebarInset,
    SidebarTrigger,
} from "@/components/ui/sidebar";
import TenantSidebar from "@/components/TenantSidebar.vue";
import {
    LogOut,
    Moon,
    Sun,
    Monitor,
    UserCircle,
    BadgeCheck,
} from "lucide-vue-next";
import type { PageProps } from "@/types";

const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user);
const { theme, setTheme } = useTheme();

function logout() {
    router.post(route("tenant.logout"));
}

function getInitials(name: string) {
    return name
        .split(" ")
        .map((n) => n[0])
        .slice(0, 2)
        .join("")
        .toUpperCase();
}
</script>

<template>
    <SidebarProvider>
        <TenantSidebar />
        <SidebarInset>
            <header
                class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-border px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
            >
                <div class="flex items-center gap-2">
                    <SidebarTrigger class="-ml-1" />
                    <Separator
                        orientation="vertical"
                        class="mr-2 data-[orientation=vertical]:h-4"
                    />
                </div>

                <!-- User menu -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            class="flex items-center gap-2 px-2"
                        >
                            <Avatar class="h-8 w-8 rounded-lg">
                                <AvatarFallback
                                    class="rounded-lg bg-primary text-primary-foreground text-xs"
                                >
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <div
                                class="hidden md:grid text-left text-sm leading-tight"
                            >
                                <span class="truncate font-medium">{{
                                    user.name
                                }}</span>
                                <span
                                    class="truncate text-xs text-muted-foreground"
                                    >{{ user.email }}</span
                                >
                            </div>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <DropdownMenuLabel class="p-0 font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5">
                                <Avatar class="h-8 w-8 rounded-lg">
                                    <AvatarFallback
                                        class="rounded-lg bg-primary text-primary-foreground text-xs"
                                    >
                                        {{ getInitials(user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div
                                    class="grid flex-1 text-left text-sm leading-tight"
                                >
                                    <span class="truncate font-semibold">{{
                                        user.name
                                    }}</span>
                                    <span
                                        class="truncate text-xs text-muted-foreground"
                                        >{{ user.email }}</span
                                    >
                                </div>
                            </div>
                        </DropdownMenuLabel>

                        <DropdownMenuSeparator />

                        <DropdownMenuLabel class="text-xs text-muted-foreground"
                            >Tema</DropdownMenuLabel
                        >
                        <DropdownMenuItem
                            :class="{ 'bg-accent': theme === 'light' }"
                            @click="setTheme('light')"
                        >
                            <Sun class="size-4" /> Claro
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            :class="{ 'bg-accent': theme === 'dark' }"
                            @click="setTheme('dark')"
                        >
                            <Moon class="size-4" /> Oscuro
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            :class="{ 'bg-accent': theme === 'system' }"
                            @click="setTheme('system')"
                        >
                            <Monitor class="size-4" /> Sistema
                        </DropdownMenuItem>

                        <DropdownMenuSeparator />

                        <DropdownMenuItem
                            @click="router.visit(route('tenant.profile.edit'))"
                        >
                            <BadgeCheck class="size-4" /> Mi perfil
                        </DropdownMenuItem>

                        <DropdownMenuSeparator />

                        <DropdownMenuItem
                            @click="logout"
                            class="text-destructive focus:text-destructive"
                        >
                            <LogOut class="size-4" /> Cerrar sesión
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </header>

            <main class="flex flex-1 flex-col gap-4 p-6">
                <slot />
            </main>
        </SidebarInset>
        <FlashMessage />
    </SidebarProvider>
</template>
