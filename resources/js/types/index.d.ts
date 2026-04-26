import { Config } from "ziggy-js";

export interface Tenant {
    id: string;
    name: string;
    user_id?: number;
    user?: User;
    domains?: Domain[];
    created_at?: string;
}

export interface Domain {
    id: number;
    tenant_id: string;
    domain: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    role: Role;
    admin_id: number | null;
    admin?: User;
    tenant_id: string | null;
    tenant?: Tenant;
    is_active: boolean;
    has_active_subscription: boolean | null;
}

export interface Role {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    users_count?: number;
    model_permissions?: ModelPermission[];
}

export interface Plan {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price: number;
    max_employees: number;
    is_active: boolean;
    subscriptions_count?: number;
}

export interface Subscription {
    id: number;
    user_id: number;
    plan_id: number;
    start_date: string;
    end_date: string;
    is_active: boolean;
    notes: string | null;
    plan?: Plan;
    user?: User;
    created_by?: User;
}

export interface Permission {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    model_permissions_count?: number;
}

export interface ModelEntity {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    model_permissions_count?: number;
}

export interface ModelPermission {
    id: number;
    role_id: number;
    permission_id: number;
    model_entity_id: number;
    permission?: Permission;
    model_entity?: ModelEntity;
}

export interface LaravelPaginatorMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    to: number | null;
    total: number;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    flash: {
        success: string | null;
        error: string | null;
    };
    currentCompany: CompanyScope | null;
    companiesScope: CompanyScope[];
    tenant: {
        id: string;
        name: string;
    };
    ziggy: Config & { location: string };
};
