import {
    LayoutDashboard,
    Users,
    ShieldCheck,
    CreditCard,
    Sheet,
    LucideSheet,
    Book,
    Settings,
    Building2,
    ShoppingCart,
    ReceiptIndianRupee,
} from "lucide-vue-next";
import type { User } from "@/types";
import type { LucideIcon } from "lucide-vue-next";

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon;
    isActive?: boolean;
    items?: { title: string; url: string }[];
}

export function useNavigation(user: User) {
    const superAdminNav: NavItem[] = [
        {
            title: "Dashboard",
            url: route("dashboard"),
            icon: LayoutDashboard,
        },
        {
            title: "Suscripciones",
            url: route("subscriptions.index"),
            icon: CreditCard,
        },
        {
            title: "Usuarios",
            url: route("users.index"),
            icon: Users,
        },
        {
            title: "Tenants",
            url: route("tenants.index"),
            icon: Building2,
        },
        {
            title: "Planes",
            url: route("plans.index"),
            icon: Building2,
        },
        {
            title: "Roles y Permisos",
            url: "#",
            icon: ShieldCheck,
            items: [
                { title: "Roles", url: route("roles.index") },
                { title: "Permisos", url: route("permissions.index") },
                { title: "Módulos", url: route("model-entities.index") },
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
                },
            ],
        },
    ];

    const adminNav: NavItem[] = [
        {
            title: "Dashboard",
            url: route("tenant.dashboard"),
            icon: LayoutDashboard,
        },
        {
            title: "Contribuyentes",
            url: route("tenant.companies.index"),
            icon: Building2,
        },
        {
            title: "Compras",
            url: route("tenant.shops.index"),
            icon: ShoppingCart,
        },
        {
            title: "Ventas",
            url: route("tenant.orders.index"),
            icon: ReceiptIndianRupee,
        },
        {
            title: "Plan de cuentas",
            url: route("tenant.accounts.index"),
            icon: Book,
        },
        {
            title: "Reportes Compras",
            url: "#",
            icon: Sheet,
            items: [
                {
                    title: "Mayor analítico",
                    url: route("tenant.reports.shops-by-account"),
                },
                {
                    title: "Por tipo de comprobante",
                    url: route("tenant.reports.shops-by-voucher-type"),
                },
                {
                    title: "Por proveedor",
                    url: route("tenant.reports.shops-by-provider"),
                },
                {
                    title: "Retenciones",
                    url: route("tenant.reports.shops-by-retention"),
                },
            ],
        },
        {
            title: "Reportes Ventas",
            url: "#",
            icon: LucideSheet,
            items: [
                {
                    title: "Por tipo de comprobante",
                    url: route("tenant.reports.orders-by-voucher-type"),
                },
                {
                    title: "Por cliente",
                    url: route("tenant.reports.orders-by-client"),
                },
            ],
        },
    ];

    const employeeNav: NavItem[] = [
        {
            title: "Dashboard",
            url: route("dashboard"),
            icon: LayoutDashboard,
        },
        {
            title: "Configuración",
            url: "#",
            icon: Settings,
            items: [
                {
                    title: "Mi perfil",
                    url: route("profile.edit"),
                },
            ],
        },
    ];

    const navMap: Record<string, NavItem[]> = {
        super_admin: superAdminNav,
        admin: adminNav,
        employee: employeeNav,
    };

    return {
        navItems: navMap[user.role.slug] ?? [],
    };
}
