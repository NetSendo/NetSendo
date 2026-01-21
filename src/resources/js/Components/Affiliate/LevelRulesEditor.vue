<script setup>
import { ref, watch, computed } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    maxLevels: {
        type: Number,
        default: 10,
    },
    currency: {
        type: String,
        default: "PLN",
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["update:modelValue"]);

// Initialize with at least one level if empty
const levels = ref(
    props.modelValue.length > 0
        ? [...props.modelValue]
        : [
              {
                  level: 1,
                  commission_type: "percent",
                  commission_value: 10,
                  min_sales_required: 0,
              },
          ],
);

// Watch for changes and emit
watch(
    levels,
    (newVal) => {
        emit("update:modelValue", newVal);
    },
    { deep: true },
);

// Watch for external changes
watch(
    () => props.modelValue,
    (newVal) => {
        if (JSON.stringify(newVal) !== JSON.stringify(levels.value)) {
            levels.value =
                newVal.length > 0
                    ? [...newVal]
                    : [
                          {
                              level: 1,
                              commission_type: "percent",
                              commission_value: 10,
                              min_sales_required: 0,
                          },
                      ];
        }
    },
    { deep: true },
);

const canAddLevel = computed(() => {
    return levels.value.length < props.maxLevels && !props.disabled;
});

const canRemoveLevel = computed(() => {
    return levels.value.length > 1 && !props.disabled;
});

const addLevel = () => {
    if (!canAddLevel.value) return;

    const lastLevel = levels.value[levels.value.length - 1];
    const newValue =
        lastLevel.commission_type === "percent"
            ? Math.max(1, Math.floor(lastLevel.commission_value / 2))
            : Math.max(1, Math.floor(lastLevel.commission_value / 2));

    levels.value.push({
        level: levels.value.length + 1,
        commission_type: lastLevel.commission_type,
        commission_value: newValue,
        min_sales_required: 0,
    });
};

const removeLevel = (index) => {
    if (!canRemoveLevel.value) return;

    levels.value.splice(index, 1);
    // Renumber levels
    levels.value.forEach((l, i) => {
        l.level = i + 1;
    });
};

const getLevelLabel = (levelNumber) => {
    if (levelNumber === 1) {
        return t("affiliate.direct_partner");
    }
    return t("affiliate.tier_n_partner", { n: levelNumber });
};

const getLevelIcon = (levelNumber) => {
    if (levelNumber === 1) return "👤";
    if (levelNumber === 2) return "👥";
    return "🌐";
};
</script>

<template>
    <div class="space-y-4">
        <!-- Level Rules -->
        <TransitionGroup name="level-list" tag="div" class="space-y-3">
            <div
                v-for="(level, index) in levels"
                :key="level.level"
                class="relative bg-slate-50 dark:bg-slate-700/50 rounded-xl p-4 border border-slate-200 dark:border-slate-600"
            >
                <!-- Level Header -->
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">{{
                            getLevelIcon(level.level)
                        }}</span>
                        <span
                            class="font-medium text-slate-700 dark:text-slate-200"
                        >
                            {{ $t("affiliate.level") }} {{ level.level }}
                        </span>
                        <span
                            class="text-sm text-slate-500 dark:text-slate-400"
                        >
                            ({{ getLevelLabel(level.level) }})
                        </span>
                    </div>
                    <button
                        v-if="canRemoveLevel"
                        type="button"
                        @click="removeLevel(index)"
                        class="text-slate-400 hover:text-red-500 transition-colors"
                        :title="$t('affiliate.remove_level')"
                    >
                        <svg
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                            />
                        </svg>
                    </button>
                </div>

                <!-- Level Settings Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Commission Type -->
                    <div>
                        <label
                            class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1"
                        >
                            {{ $t("affiliate.commission_type") }}
                        </label>
                        <select
                            v-model="level.commission_type"
                            :disabled="disabled"
                            class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="percent">
                                {{ $t("affiliate.commission_percent") }}
                            </option>
                            <option value="fixed">
                                {{ $t("affiliate.commission_fixed") }}
                            </option>
                        </select>
                    </div>

                    <!-- Commission Value -->
                    <div>
                        <label
                            class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1"
                        >
                            {{ $t("affiliate.commission_value") }}
                        </label>
                        <div class="relative">
                            <input
                                v-model.number="level.commission_value"
                                type="number"
                                min="0"
                                :max="
                                    level.commission_type === 'percent'
                                        ? 100
                                        : undefined
                                "
                                step="0.01"
                                :disabled="disabled"
                                class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500 pr-10"
                            />
                            <span
                                class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-500 dark:text-slate-400 text-sm"
                            >
                                {{
                                    level.commission_type === "percent"
                                        ? "%"
                                        : currency
                                }}
                            </span>
                        </div>
                    </div>

                    <!-- Min Sales Required -->
                    <div>
                        <label
                            class="block text-sm font-medium text-slate-600 dark:text-slate-400 mb-1"
                        >
                            {{ $t("affiliate.min_sales_required") }}
                        </label>
                        <input
                            v-model.number="level.min_sales_required"
                            type="number"
                            min="0"
                            :disabled="disabled"
                            class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                </div>

                <!-- Visual connector to next level -->
                <div
                    v-if="index < levels.length - 1"
                    class="absolute -bottom-3 left-1/2 transform -translate-x-1/2 z-10"
                >
                    <div
                        class="w-0.5 h-3 bg-slate-300 dark:bg-slate-600 mx-auto"
                    ></div>
                    <svg
                        class="w-4 h-4 text-slate-400 dark:text-slate-500 -mt-1"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </div>
            </div>
        </TransitionGroup>

        <!-- Add Level Button -->
        <button
            v-if="canAddLevel"
            type="button"
            @click="addLevel"
            class="w-full py-3 border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl text-slate-500 dark:text-slate-400 hover:border-indigo-400 hover:text-indigo-600 dark:hover:border-indigo-500 dark:hover:text-indigo-400 transition-colors flex items-center justify-center gap-2"
        >
            <svg
                class="w-5 h-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                />
            </svg>
            {{ $t("affiliate.add_level") }}
        </button>

        <!-- Max Levels Info -->
        <p
            v-if="levels.length >= maxLevels"
            class="text-sm text-slate-500 dark:text-slate-400 text-center"
        >
            {{ $t("affiliate.max_levels_reached", { max: maxLevels }) }}
        </p>
    </div>
</template>

<style scoped>
.level-list-enter-active,
.level-list-leave-active {
    transition: all 0.3s ease;
}

.level-list-enter-from,
.level-list-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

.level-list-move {
    transition: transform 0.3s ease;
}
</style>
