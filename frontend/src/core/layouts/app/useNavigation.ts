import { inject } from 'vue';
import { useRouter } from 'vue-router';
import { useSession } from '@/core/stores/session';
import { NAVIGATION } from '../navigation';
import type { Navigation, NavItem } from '../navigation';

export function useNavigation() {
    const navigation = inject(NAVIGATION) as Navigation;
    const session = useSession();
    const router = useRouter();

    function allowed(item: NavItem): boolean {
        return item.requires === undefined || session.can(item.requires);
    }

    /** Active for the route itself and the pages below it. */
    function isActive(item: NavItem): boolean {
        if (!item.to) {
            return false;
        }

        const target = router.resolve(item.to).path;
        const current = router.currentRoute.value.path;

        return (
            current === target ||
            (target !== '/' && current.startsWith(`${target}/`))
        );
    }

    return { navigation, allowed, isActive };
}
