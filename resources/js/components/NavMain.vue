<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import { ChevronRight } from "lucide-vue-next";
import type { NavItem } from "@/composables/useNavigation";
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from "@/components/ui/collapsible";
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from "@/components/ui/sidebar";

const props = defineProps<{
    items: NavItem[];
}>();

const page = usePage();

function isActive(url: string): boolean {
    if (url === "#") return false;
    try {
        return page.url.startsWith(new URL(url).pathname);
    } catch {
        return false;
    }
}

function isGroupActive(item: NavItem): boolean {
    return item.items?.some((sub) => isActive(sub.url)) ?? false;
}

const openMap = ref<Record<string, boolean>>(
    Object.fromEntries(
        props.items.map((item) => [item.title, isGroupActive(item)]),
    ),
);

watch(
    () => page.url,
    () => {
        props.items.forEach((item) => {
            if (isGroupActive(item)) {
                openMap.value[item.title] = true;
            }
        });
    },
);
</script>

<template>
    <SidebarGroup>
        <SidebarGroupLabel>Navegación</SidebarGroupLabel>
        <SidebarMenu>
            <template v-for="item in items" :key="item.title">
                <!-- Con subitems → collapsible -->
                <Collapsible
                    v-if="item.items?.length"
                    as-child
                    v-model:open="openMap[item.title]"
                    class="group/collapsible"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                :tooltip="item.title"
                                :is-active="false"
                            >
                                <component :is="item.icon" v-if="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronRight
                                    class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem
                                    v-for="subItem in item.items"
                                    :key="subItem.title"
                                >
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="isActive(subItem.url)"
                                        :class="
                                            isActive(subItem.url)
                                                ? 'bg-sidebar-accent text-sidebar-accent-foreground font-medium'
                                                : ''
                                        "
                                    >
                                        <Link :href="subItem.url">
                                            <span>{{ subItem.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>

                <!-- Sin subitems → link directo -->
                <SidebarMenuItem v-else>
                    <SidebarMenuButton
                        :tooltip="item.title"
                        :is-active="isActive(item.url)"
                        as-child
                    >
                        <Link :href="item.url">
                            <component :is="item.icon" v-if="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
