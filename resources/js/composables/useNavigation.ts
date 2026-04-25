import {
    LayoutDashboard,
    Users,
    ShieldCheck,
    CreditCard,
    Settings,
    Building2,
    UserCog,
    KeyRound,
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
            title: "Usuarios",
            url: route("users.index"),
            icon: Users,
        },
        {
            title: "Suscripciones",
            url: route("subscriptions.index"),
            icon: CreditCard,
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
            url: route("dashboard"),
            icon: LayoutDashboard,
        },
        {
            title: "Empleados",
            url: "#",
            icon: UserCog,
            items: [
                { title: "Listar", url: "#" },
                { title: "Registrar", url: "#" },
            ],
        },
        {
            title: "Mi Suscripción",
            url: "#",
            icon: CreditCard,
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
