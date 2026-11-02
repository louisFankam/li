import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type PermissionPageProps = {
    auth?: {
        permissions?: string[];
        user?: { is_super_admin?: boolean } | null;
    };
};

export function usePermissions() {
    const page = usePage<PermissionPageProps>();
    const permissions = computed<string[]>(() => page.props.auth?.permissions ?? []);
    const isSuperAdmin = computed(() => Boolean(page.props.auth?.user?.is_super_admin));

    function can(permission: string): boolean {
        return isSuperAdmin.value || permissions.value.includes(permission);
    }

    return { can, permissions, isSuperAdmin };
}
