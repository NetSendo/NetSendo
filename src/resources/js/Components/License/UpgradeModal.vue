<script setup>
/**
 * The GOLD upgrade dialog. Mounted once in AuthenticatedLayout; opens when
 *  - the server refused a GOLD feature on an Inertia visit (flash `gold_required`),
 *  - an axios call got 402 `gold_required` (interceptor in bootstrap.js),
 *  - a page calls requireGold('<feature>') from Composables/useLicense.
 */
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Modal from '@/Components/Modal.vue';
import GoldBadge from '@/Components/License/GoldBadge.vue';
import { GOLD_REQUIRED_EVENT } from '@/Composables/useLicense';

const page = usePage();
const { t, te } = useI18n();

const show = ref(false);
const feature = ref(null);

const featureName = computed(() => {
    const key = `license.gating.features.${feature.value}`;
    return feature.value && te(key) ? t(key) : t('license.gating.feature_fallback');
});

const open = (name) => {
    feature.value = name || null;
    show.value = true;
};

const onEvent = (event) => open(event.detail?.feature);

// A refused Inertia visit comes back with the feature in the flash (watch the flash
// object, which every visit replaces, so the same feature refused twice opens it twice)
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.gold_required) {
            open(flash.gold_required);
        }
    },
    { immediate: true },
);

onMounted(() => window.addEventListener(GOLD_REQUIRED_EVENT, onEvent));
onUnmounted(() => window.removeEventListener(GOLD_REQUIRED_EVENT, onEvent));
</script>

<template>
    <Modal :show="show" max-width="md" @close="show = false">
        <div class="p-6">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-amber-400 to-yellow-500 text-amber-950"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 11h14v10H5z" />
                    </svg>
                </div>
                <div>
                    <GoldBadge />
                    <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $t('license.gating.dialog_title', { feature: featureName }) }}
                    </h2>
                </div>
            </div>

            <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                {{ $t('license.gating.dialog_desc') }}
            </p>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="show = false"
                >
                    {{ $t('license.gating.dialog_close') }}
                </button>
                <Link
                    :href="route('license.index')"
                    class="rounded-lg bg-gradient-to-r from-amber-400 to-yellow-500 px-4 py-2 text-center text-sm font-semibold text-amber-950 shadow hover:from-amber-500 hover:to-yellow-600"
                    @click="show = false"
                >
                    {{ $t('license.gating.dialog_cta') }}
                </Link>
            </div>
        </div>
    </Modal>
</template>
