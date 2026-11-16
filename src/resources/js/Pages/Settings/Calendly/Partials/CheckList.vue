<script setup>
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";
import { fieldClass, labelClass } from "@/Components/Ui/fieldClass";

/**
 * Any number of lists or tags, ticked in a box that scrolls. A filter appears once the list is
 * too long to scan, and the count stays in view when the ticked ones are scrolled away.
 */
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    // [{ id, name }]
    items: { type: Array, required: true },
    label: { type: String, required: true },
    searchPlaceholder: { type: String, default: "" },
    emptyText: { type: String, required: true },
});

const emit = defineEmits(["update:modelValue"]);

const { t } = useI18n();
const search = ref("");

const visible = computed(() => {
    const needle = search.value.trim().toLowerCase();
    return needle ? props.items.filter((item) => item.name.toLowerCase().includes(needle)) : props.items;
});

const toggle = (id, checked) => {
    const rest = props.modelValue.filter((value) => value !== id);
    emit("update:modelValue", checked ? [...rest, id] : rest);
};
</script>

<template>
    <fieldset class="min-w-0">
        <legend class="flex w-full items-baseline justify-between gap-3">
            <span :class="labelClass">{{ label }}</span>
            <span class="text-xs tabular-nums text-slate-600 dark:text-slate-400">
                {{ t("calendly_settings.selected", { count: modelValue.length }) }}
            </span>
        </legend>

        <input
            v-if="items.length > 8"
            v-model="search"
            type="search"
            :aria-label="searchPlaceholder"
            :placeholder="searchPlaceholder"
            class="mt-2"
            :class="fieldClass"
        />

        <div
            class="mt-2 max-h-44 overflow-y-auto rounded-xl border border-slate-300 dark:border-slate-600"
        >
            <p v-if="visible.length === 0" class="px-3 py-3 text-sm text-slate-600 dark:text-slate-400">
                {{ emptyText }}
            </p>
            <label
                v-for="item in visible"
                :key="item.id"
                class="flex min-h-10 cursor-pointer items-center gap-3 px-3 py-2 text-sm text-slate-900 hover:bg-slate-50 dark:text-slate-100 dark:hover:bg-slate-700/40"
            >
                <input
                    type="checkbox"
                    class="h-4 w-4 shrink-0 accent-indigo-600"
                    :checked="modelValue.includes(item.id)"
                    @change="toggle(item.id, $event.target.checked)"
                />
                <span class="min-w-0 break-words">{{ item.name }}</span>
            </label>
        </div>
    </fieldset>
</template>
