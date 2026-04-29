<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { useTheme } from "@/composables/useTheme";
import { LogOut, Moon, Sun, Monitor, BadgeCheck } from "lucide-vue-next";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

const props = defineProps<{
    user: { name: string; email: string };
}>();

const { theme, setTheme } = useTheme();

const getInitials = (name: string) =>
    name
        .split(" ")
        .map((n) => n[0])
        .slice(0, 2)
        .join("")
        .toUpperCase();

const logout = () => router.post(route("tenant.logout"));
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" class="flex items-center gap-2 px-2">
                <Avatar class="h-8 w-8 rounded-lg">
                    <AvatarFallback
                        class="rounded-lg bg-primary text-primary-foreground text-xs"
                    >
                        {{ getInitials(user.name) }}
                    </AvatarFallback>
                </Avatar>
                <div class="hidden md:grid text-left text-sm leading-tight">
                    <span class="truncate font-medium">{{ user.name }}</span>
                    <span class="truncate text-xs text-muted-foreground">{{
                        user.email
                    }}</span>
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
                    <div class="grid flex-1 text-left text-sm leading-tight">
                        <span class="truncate font-semibold">{{
                            user.name
                        }}</span>
                        <span class="truncate text-xs text-muted-foreground">{{
                            user.email
                        }}</span>
                    </div>
                </div>
            </DropdownMenuLabel>

            <DropdownMenuSeparator />
            <DropdownMenuLabel
                class="text-xs text-muted-foreground uppercase tracking-widest px-2 py-1"
                >Tema</DropdownMenuLabel
            >
            <DropdownMenuItem
                @click="setTheme('light')"
                :class="{ 'bg-accent': theme === 'light' }"
            >
                <Sun class="mr-2 size-4" /> Claro
            </DropdownMenuItem>
            <DropdownMenuItem
                @click="setTheme('dark')"
                :class="{ 'bg-accent': theme === 'dark' }"
            >
                <Moon class="mr-2 size-4" /> Oscuro
            </DropdownMenuItem>
            <DropdownMenuItem
                @click="setTheme('system')"
                :class="{ 'bg-accent': theme === 'system' }"
            >
                <Monitor class="mr-2 size-4" /> Sistema
            </DropdownMenuItem>

            <DropdownMenuSeparator />
            <DropdownMenuItem
                @click="router.visit(route('tenant.profile.edit'))"
            >
                <BadgeCheck class="mr-2 size-4" /> Mi perfil
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                @click="logout"
                class="text-destructive focus:text-destructive"
            >
                <LogOut class="mr-2 size-4" /> Cerrar sesión
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
