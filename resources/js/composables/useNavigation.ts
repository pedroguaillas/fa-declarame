import { computed, type Ref } from "vue";
import type { LucideIcon } from "lucide-vue-next";
import {
    LayoutDashboard,
    Users,
    ShieldCheck,
    CreditCard,
    Sheet,
    Book,
    Settings,
    Building2,
    ShoppingCart,
    ReceiptIndianRupee,
    Contact,
    CloudDownload,
    ClipboardList,
} from "lucide-vue-next";

import type { User } from "@/types";
import { can } from "@/utils/permissions";

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon;
    permission?: [string, string];
    items?: NavItem[];
}

const nav: NavItem[] = [
    {
        title: "Dashboard",
        url: route("dashboard"),
        icon: LayoutDashboard,
    },
    {
        title: "Suscripciones",
        url: route("subscriptions.index"),
        icon: CreditCard,
        permission: ["view", "subscriptions"],
    },
    {
        title: "Usuarios",
        url: route("users.index"),
        icon: Users,
        permission: ["view", "users"],
    },
    {
        title: "Tenants",
        url: route("tenants.index"),
        icon: Building2,
        permission: ["view", "tenants"],
    },
    {
        title: "Planes",
        url: route("plans.index"),
        icon: Building2,
        permission: ["view", "plans"],
    },
    {
        title: "Sistema",
        url: "#",
        icon: ShieldCheck,
        items: [
            {
                title: "Roles",
                url: route("roles.index"),
                icon: ShieldCheck,
                permission: ["view", "roles"],
            },
            {
                title: "Módulos",
                url: route("model-entities.index"),
                icon: ShieldCheck,
                permission: ["view", "models"],
            },
        ],
    },
    {
        title: "Configuración",
        url: "#",
        icon: Settings,
        items: [
            {
                title: "Mi perfil",
                url: route("profile.edit"),
                icon: Settings,
            },
        ],
    },
];

const tenantNav: NavItem[] = [
    {
        title: "Panel de control",
        url: route("tenant.dashboard"),
        icon: LayoutDashboard,
    },
    {
        title: "Contribuyentes",
        url: route("tenant.companies.index"),
        icon: Building2,
        permission: ["view", "companies"],
    },
    {
        title: "Descarga automática",
        url: route("tenant.sri-scrape.index"),
        icon: CloudDownload,
        permission: ["view", "sri_scrape"],
    },
    {
        title: "Compras",
        url: route("tenant.shops.index"),
        icon: ShoppingCart,
        permission: ["view", "shops"],
    },
    {
        title: "Ventas",
        url: route("tenant.orders.index"),
        icon: ReceiptIndianRupee,
        permission: ["view", "orders"],
    },
    {
        title: "Contactos",
        url: route("tenant.contacts.index"),
        icon: Contact,
        permission: ["view", "contacts"],
    },
    {
        title: "Plan de cuentas",
        url: route("tenant.accounts.index"),
        icon: Book,
        permission: ["view", "accounts"],
    },
    {
        title: "Reportes",
        url: route("tenant.reports.index"),
        icon: Sheet,
        permission: ["view", "reports"],
    },
    {
        title: "Declaración",
        url: route("tenant.declaration.index"),
        icon: ClipboardList,
        permission: ["view", "declaration"],
    },
    {
        title: "Configuración",
        url: "#",
        icon: Settings,
        items: [
            {
                title: "Mi perfil",
                url: route("tenant.profile.edit"),
                icon: Settings,
            },
            {
                title: "Usuarios",
                url: route("tenant.users.index"),
                icon: Users,
                permission: ["view", "users"],
            },
            {
                title: "Roles",
                url: route("tenant.roles.index"),
                icon: ShieldCheck,
                permission: ["view", "roles"],
            },
            {
                title: "Módulos",
                url: route("tenant.model-entities.index"),
                icon: ShieldCheck,
                permission: ["view", "models"],
            },
        ],
    },
];

function filterNav(items: NavItem[], user: User): NavItem[] {
    return items
        .map((item) => {
            if (item.permission) {
                const [perm, model] = item.permission;
                if (!can(user, perm, model)) return null;
            }

            if (item.items) {
                const children = filterNav(item.items, user);
                if (children.length === 0) return null;
                return { ...item, items: children };
            }

            return item;
        })
        .filter(Boolean) as NavItem[];
}

export function useNavigation(user: User | null, hasTenant: Ref<boolean>) {

    const navItems = computed(() => {
        if (!user) return [];

        if (hasTenant.value) return filterNav(tenantNav, user);

        return filterNav(nav, user);
    });

    return { navItems };
}
