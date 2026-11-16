import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Event that opens the GOLD upgrade dialog (Components/License/UpgradeModal.vue,
 * mounted once in AuthenticatedLayout). detail: { feature: 'ab_tests' }
 */
export const GOLD_REQUIRED_EVENT = 'netsendo:gold-required';

/**
 * Opens the upgrade dialog for a GOLD feature, e.g. when a SILVER user clicks a locked control.
 */
export function requireGold(feature = null) {
    window.dispatchEvent(new CustomEvent(GOLD_REQUIRED_EVENT, { detail: { feature } }));
}

/**
 * The licence in effect, from the shared `license` prop (HandleInertiaRequests).
 *
 *   const { isGold, can } = useLicense();
 *   can('ab_tests') // false on SILVER
 *
 * Feature names are the values of App\Licensing\Feature. The server enforces every
 * check on its own; this only decides what the interface shows.
 */
export function useLicense() {
    const page = usePage();

    const license = computed(() => page.props.license || {});
    const plan = computed(() => license.value.plan || 'SILVER');
    const isGold = computed(() => plan.value === 'GOLD');

    const can = (feature) => license.value.features?.[feature] === true;

    return { license, plan, isGold, can, requireGold };
}
