<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import {
    BadgeCheck,
    Bell,
    ChevronsUpDown,
    LogOut,
    Moon,
    Sun,
    Monitor,
} from "lucide-vue-next";
import { useTheme } from "@/composables/useTheme";
import type { User } from "@/types";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from "@/components/ui/sidebar";

defineProps<{
    user: User;
}>();

const { isMobile } = useSidebar();
const { theme, setTheme } = useTheme();

function logout() {
    router.post(route("logout"));
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
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                    >
                        <Avatar class="h-8 w-8 rounded-lg">
                            <AvatarFallback
                                class="rounded-lg bg-primary text-primary-foreground"
                            >
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div
                            class="grid flex-1 text-left text-sm leading-tight"
                        >
                            <span class="truncate font-medium">{{
                                user.name
                            }}</span>
                            <span
                                class="truncate text-xs text-sidebar-foreground/60"
                                >{{ user.email }}</span
                            >
                        </div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>

                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="isMobile ? 'bottom' : 'right'"
                    align="end"
                    :side-offset="4"
                >
                    <DropdownMenuLabel class="p-0 font-normal">
                        <div
                            class="flex items-center gap-2 px-1 py-1.5 text-left text-sm"
                        >
                            <Avatar class="h-8 w-8 rounded-lg">
                                <AvatarFallback
                                    class="rounded-lg bg-primary text-primary-foreground"
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

                    <!-- Tema -->
                    <DropdownMenuGroup>
                        <DropdownMenuLabel
                            class="text-xs text-muted-foreground px-2"
                        >
                            Tema
                        </DropdownMenuLabel>
                        <DropdownMenuItem
                            :class="{ 'bg-accent': theme === 'light' }"
                            @click="setTheme('light')"
                        >
                            <Sun class="size-4" />
                            <span>Claro</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            :class="{ 'bg-accent': theme === 'dark' }"
                            @click="setTheme('dark')"
                        >
                            <Moon class="size-4" />
                            <span>Oscuro</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            :class="{ 'bg-accent': theme === 'system' }"
                            @click="setTheme('system')"
                        >
                            <Monitor class="size-4" />
                            <span>Sistema</span>
                        </DropdownMenuItem>
                    </DropdownMenuGroup>

                    <DropdownMenuSeparator />

                    <DropdownMenuGroup>
                        <DropdownMenuItem
                            @click="router.visit(route('profile.edit'))"
                        >
                            <BadgeCheck class="size-4" />
                            <span>Mi perfil</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem>
                            <Bell class="size-4" />
                            <span>Notificaciones</span>
                        </DropdownMenuItem>
                    </DropdownMenuGroup>

                    <DropdownMenuSeparator />

                    <DropdownMenuItem
                        @click="logout"
                        class="text-destructive focus:text-destructive"
                    >
                        <LogOut class="size-4" />
                        <span>Cerrar sesión</span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
