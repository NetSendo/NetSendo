<script setup>
import { useI18n } from "vue-i18n";
import { fieldClass } from "@/Components/Ui/fieldClass";

/**
 * Which contact lists a team member may open, and whether only to look or also to change:
 * a tick per list and, for a ticked one, the level.
 */
const props = defineProps({
    // [{ list_id, permission: "view" | "edit" }]
    modelValue: { type: Array, required: true },
    // [{ id, name, group, type }]
    lists: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:modelValue"]);

const { t } = useI18n();

const levelOf = (listId) => props.modelValue.find((entry) => entry.list_id === listId)?.permission ?? null;

const toggle = (listId) => {
    emit(
        "update:modelValue",
        levelOf(listId)
            ? props.modelValue.filter((entry) => entry.list_id !== listId)
            : [...props.modelValue, { list_id: listId, permission: "view" }],
    );
};

const setLevel = (listId, permission) => {
    emit(
        "update:modelValue",
        props.modelValue.map((entry) => (entry.list_id === listId ? { ...entry, permission } : entry)),
    );
};
</script>

<template>
    <p v-if="lists.length === 0" class="text-sm text-slate-600 dark:text-slate-400">
        {{ t("users.no_lists") }}
    </p>
    <ul
        v-else
        class="max-h-72 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200 dark:divide-slate-700/40 dark:border-slate-700/60"
    >
        <li v-for="list in lists" :key="list.id" class="flex min-h-12 items-center gap-3 px-3 py-2">
            <label class="flex min-w-0 flex-1 items-center gap-2.5">
                <input
                    type="checkbox"
                    class="h-4 w-4 shrink-0 accent-indigo-600"
                    :checked="!!levelOf(list.id)"
                    @change="toggle(list.id)"
                />
                <span class="min-w-0">
                    <span class="block truncate text-sm text-slate-900 dark:text-white">{{ list.name }}</span>
                    <span v-if="list.group" class="block truncate text-xs text-slate-600 dark:text-slate-400">
                        {{ list.group }}
                    </span>
                </span>
            </label>
            <select
                v-if="levelOf(list.id)"
                :value="levelOf(list.id)"
                :aria-label="t('users.permission_level_for', { name: list.name })"
                class="!w-auto shrink-0 !py-1"
                :class="fieldClass"
                @change="setLevel(list.id, $event.target.value)"
            >
                <option value="view">{{ t("users.permission_view") }}</option>
                <option value="edit">{{ t("users.permission_edit") }}</option>
            </select>
        </li>
    </ul>
</template>
