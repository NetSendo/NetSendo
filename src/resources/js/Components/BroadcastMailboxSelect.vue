<script setup>
/**
 * A list's broadcast mailbox: the mailbox its campaigns go out from when the
 * list's default mailbox cannot send campaigns. Gmail mailboxes never can, yet
 * they are a fine default for autoresponders and system emails, so a list with
 * a Gmail default names a second mailbox (usually SMTP on the owner's domain)
 * for its campaigns. Shown when the default mailbox needs it, or when one is
 * already set.
 */
import { computed } from "vue";

const props = defineProps({
    modelValue: { type: [Number, String, null], default: null },
    mailboxes: { type: Array, default: () => [] },
    primaryMailboxId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(["update:modelValue"]);

const canBroadcast = (mailbox) =>
    (mailbox?.allowed_types || []).includes("broadcast");

const primary = computed(() =>
    props.mailboxes.find((m) => m.id === Number(props.primaryMailboxId)),
);

const needed = computed(() => !!primary.value && !canBroadcast(primary.value));

const options = computed(() => props.mailboxes.filter(canBroadcast));

const visible = computed(() => needed.value || !!props.modelValue);

const value = computed({
    get: () => (props.modelValue ? Number(props.modelValue) : null),
    set: (v) => emit("update:modelValue", v || null),
});
</script>

<template>
    <div
        v-if="visible"
        class="mb-6 rounded-xl border p-4"
        :class="
            needed && !value
                ? 'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-900/20'
                : 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800'
        "
    >
        <label
            class="mb-1 block text-sm font-medium text-slate-900 dark:text-white"
        >
            {{ $t("mailing_lists.settings.broadcast_mailbox_label") }}
        </label>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
            <template v-if="needed">
                {{
                    $t("mailing_lists.settings.broadcast_mailbox_desc_gmail", {
                        name: primary.name,
                        email: primary.from_email,
                    })
                }}
            </template>
            <template v-else>
                {{ $t("mailing_lists.settings.broadcast_mailbox_desc") }}
            </template>
        </p>

        <select
            v-model="value"
            class="block w-full rounded-xl border-slate-200 bg-white px-4 py-3 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:focus:border-indigo-400"
        >
            <option :value="null">
                {{ $t("mailing_lists.settings.broadcast_mailbox_none") }}
            </option>
            <option
                v-for="mailbox in options"
                :key="mailbox.id"
                :value="mailbox.id"
            >
                {{ mailbox.name }} ({{ mailbox.from_email }})
            </option>
        </select>

        <p
            v-if="needed && !options.length"
            class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300"
        >
            {{ $t("mailing_lists.settings.broadcast_mailbox_no_options") }}
        </p>
        <p
            v-else-if="needed && !value"
            class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300"
        >
            {{ $t("mailing_lists.settings.broadcast_mailbox_missing") }}
        </p>
    </div>
</template>
