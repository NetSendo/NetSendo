<script setup>
// Repeatable entries inside a block (benefit bullets, FAQ pairs, testimonials…).
import draggable from 'vuedraggable';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    fields: { type: Array, required: true },
    blockType: { type: String, required: true },
    propKey: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

const items = () => (Array.isArray(props.modelValue) ? props.modelValue : []);

// Single-field entries are stored as plain strings by the API, so normalise
// both shapes into an object the inputs can bind to.
const readValue = (item, key) => {
    if (typeof item === 'string') {
        return props.fields.length === 1 ? item : '';
    }
    return item?.[key] ?? '';
};

const writeValue = (index, key, value) => {
    const next = items().slice();
    const current = next[index];

    if (props.fields.length === 1) {
        next[index] = value;
    } else {
        next[index] = { ...(typeof current === 'object' && current !== null ? current : {}), [key]: value };
    }

    emit('update:modelValue', next);
};

const add = () => {
    const next = items().slice();
    next.push(props.fields.length === 1 ? '' : Object.fromEntries(props.fields.map((f) => [f.key, ''])));
    emit('update:modelValue', next);
};

const remove = (index) => {
    const next = items().slice();
    next.splice(index, 1);
    emit('update:modelValue', next);
};

const onReorder = (value) => emit('update:modelValue', value);
</script>

<template>
    <div class="space-y-2">
        <draggable
            :model-value="items()"
            @update:model-value="onReorder"
            item-key="__index"
            handle=".item-handle"
            :animation="150"
            class="space-y-2"
        >
            <template #item="{ index }">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-900/40">
                    <div class="mb-1.5 flex items-center gap-1">
                        <span class="item-handle cursor-grab text-gray-400 hover:text-gray-600" title="↕">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>
                        </span>
                        <span class="text-[11px] font-semibold text-gray-400">#{{ index + 1 }}</span>
                        <button type="button" class="ml-auto text-gray-400 hover:text-red-500" @click="remove(index)">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </div>

                    <div class="space-y-1.5">
                        <template v-for="field in fields" :key="field.key">
                            <textarea
                                v-if="field.type === 'textarea'"
                                rows="2"
                                :value="readValue(items()[index], field.key)"
                                @input="writeValue(index, field.key, $event.target.value)"
                                :placeholder="$t(`webinars.builder.blocks.${blockType}.items.${field.key}`)"
                                class="w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            ></textarea>
                            <input
                                v-else
                                type="text"
                                :value="readValue(items()[index], field.key)"
                                @input="writeValue(index, field.key, $event.target.value)"
                                :placeholder="$t(`webinars.builder.blocks.${blockType}.items.${field.key}`)"
                                class="w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            >
                        </template>
                    </div>
                </div>
            </template>
        </draggable>

        <button
            type="button"
            @click="add"
            class="w-full rounded-lg border border-dashed border-gray-300 py-1.5 text-xs font-medium text-gray-500 transition hover:border-indigo-400 hover:text-indigo-600 dark:border-gray-600 dark:text-gray-400"
        >
            + {{ $t('webinars.builder.add_item') }}
        </button>
    </div>
</template>
