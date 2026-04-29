<script setup>
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useTheme } from "@/composables/shared/useTheme";
import { Monitor, Moon, Sun } from "lucide-vue-next";

const { theme, setTheme } = useTheme();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="icon">
                <Sun v-if="theme === 'light'" class="h-4 w-4" />
                <Moon v-else-if="theme === 'dark'" class="h-4 w-4" />
                <Monitor v-else class="h-4 w-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem @click="setTheme('light')">
                <Sun class="mr-2 h-4 w-4" />
                Light
            </DropdownMenuItem>
            <DropdownMenuItem @click="setTheme('dark')">
                <Moon class="mr-2 h-4 w-4" />
                Dark
            </DropdownMenuItem>
            <DropdownMenuItem @click="setTheme('system')">
                <Monitor class="mr-2 h-4 w-4" />
                System
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
