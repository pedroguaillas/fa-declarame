import { usePage } from "@inertiajs/vue3";
import { can as canCheck } from "@/utils/permissions";
import type { PageProps } from "@/types";

export function usePermissions() {
    const page = usePage<PageProps>();

    function can(permission: string, model: string): boolean {
        return canCheck(page.props.auth.user, permission, model);
    }

    return { can };
}
