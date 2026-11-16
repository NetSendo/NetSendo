<script setup>
/**
 * Amber notice for a GOLD feature on a SILVER installation: the message, with a button
 * that opens the upgrade dialog for the feature.
 */
import GoldBadge from '@/Components/License/GoldBadge.vue';
import { requireGold } from '@/Composables/useLicense';

defineProps({
    feature: {
        type: String,
        required: true,
    },
    message: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div
        class="flex flex-col gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200"
    >
        <span class="flex items-start gap-2">
            <GoldBadge locked class="mt-0.5 shrink-0" />
            <span>{{ message }}</span>
        </span>
        <button
            type="button"
            class="shrink-0 rounded-md bg-amber-400 px-3 py-1.5 text-xs font-semibold text-amber-950 hover:bg-amber-500"
            @click="requireGold(feature)"
        >
            {{ $t('license.gating.locked_title') }}
        </button>
    </div>
</template>
