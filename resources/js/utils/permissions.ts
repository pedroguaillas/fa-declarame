import type { User } from "@/types";

export function can(user: User, permissionSlug: string, modelSlug: string): boolean {
    if (user.role?.slug === 'super_admin') return true;
    if (!user.permissions) return false;
    return user.permissions.some(
        (p) => p.permission === permissionSlug && p.model === modelSlug,
    );
}
