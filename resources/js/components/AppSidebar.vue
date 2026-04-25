<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { useNavigation } from "@/composables/useNavigation";
import type { PageProps } from "@/types";
import NavMain from "@/components/NavMain.vue";
import NavUser from "@/components/NavUser.vue";
import AppLogo from "@/components/AppLogo.vue";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarRail,
} from "@/components/ui/sidebar";

const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user);
const { navItems } = useNavigation(user.value);
</script>

<template>
    <Sidebar collapsible="icon">
        <SidebarHeader>
            <AppLogo />
        </SidebarHeader>
        <SidebarContent>
            <NavMain :items="navItems" />
        </SidebarContent>
        <SidebarFooter>
            <NavUser :user="user" />
        </SidebarFooter>
        <SidebarRail />
    </Sidebar>
</template>
