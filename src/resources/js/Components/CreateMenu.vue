<script setup>
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";

/**
 * One "Create" button with the things people start most often. It replaces the six
 * quick-action tiles the dashboard used to carry. It lives in the dashboard's header, not in
 * the layout: other pages put their own primary action there.
 */
const { t } = useI18n();

const groups = computed(() => [
    [
        { label: t("dashboard.create.email"), href: route("messages.create") },
        { label: t("dashboard.create.sms"), href: route("sms.create") },
    ],
    [
        { label: t("dashboard.quick_actions.add_subscriber"), href: route("subscribers.create") },
        { label: t("dashboard.quick_actions.import_contacts"), href: route("subscribers.import") },
    ],
    [
        { label: t("dashboard.quick_actions.new_email_list"), href: route("mailing-lists.create") },
        { label: t("dashboard.quick_actions.new_sms_list"), href: route("sms-lists.create") },
        { label: t("dashboard.quick_actions.new_form"), href: route("forms.create") },
    ],
]);
</script>

<template>
    <Dropdown
        align="right"
        width="56"
        content-classes="py-1 bg-white dark:bg-slate-800"
    >
        <template #trigger>
            <button
                type="button"
                aria-haspopup="true"
                class="flex h-10 items-center gap-2 rounded-xl bg-indigo-600 px-3 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
            >
                <svg
                    aria-hidden="true"
                    class="h-4 w-4"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.4"
                    stroke-linecap="round"
                >
                    <path d="M12 5v14M5 12h14" />
                </svg>
                <span class="sr-only 2xl:not-sr-only">{{ $t("dashboard.create.button") }}</span>
            </button>
        </template>

        <template #content>
            <template v-for="(group, index) in groups" :key="index">
                <div
                    v-if="index > 0"
                    class="my-1 border-t border-slate-100 dark:border-slate-700"
                ></div>
                <DropdownLink v-for="item in group" :key="item.href" :href="item.href">
                    {{ item.label }}
                </DropdownLink>
            </template>
        </template>
    </Dropdown>
</template>
