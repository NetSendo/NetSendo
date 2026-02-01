<script setup>
import { useI18n } from "vue-i18n";

const { t } = useI18n();

defineProps({
    level: {
        type: String,
        default: "LOW",
    },
    score: {
        type: Number,
        default: 0,
    },
    size: {
        type: String,
        default: "md", // sm, md, lg
    },
    showScore: {
        type: Boolean,
        default: true,
    },
});

const getLevelConfig = (level) => {
    switch (level) {
        case "HIGH":
            return {
                labelKey: "crm.cardintel.context.high",
                bgClass: "bg-green-100 dark:bg-green-900/30",
                textClass: "text-green-800 dark:text-green-400",
                dotClass: "bg-green-500",
            };
        case "MEDIUM":
            return {
                labelKey: "crm.cardintel.context.medium",
                bgClass: "bg-yellow-100 dark:bg-yellow-900/30",
                textClass: "text-yellow-800 dark:text-yellow-400",
                dotClass: "bg-yellow-500",
            };
        case "LOW":
        default:
            return {
                labelKey: "crm.cardintel.context.low",
                bgClass: "bg-gray-100 dark:bg-gray-700",
                textClass: "text-gray-600 dark:text-gray-400",
                dotClass: "bg-gray-400",
            };
    }
};

const getSizeClasses = (size) => {
    switch (size) {
        case "sm":
            return {
                container: "px-2 py-0.5 text-xs",
                dot: "w-1.5 h-1.5",
            };
        case "lg":
            return {
                container: "px-4 py-2 text-base",
                dot: "w-3 h-3",
            };
        case "md":
        default:
            return {
                container: "px-3 py-1 text-sm",
                dot: "w-2 h-2",
            };
    }
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 font-medium rounded-full"
        :class="[
            getLevelConfig(level).bgClass,
            getLevelConfig(level).textClass,
            getSizeClasses(size).container,
        ]"
    >
        <span
            class="rounded-full"
            :class="[getLevelConfig(level).dotClass, getSizeClasses(size).dot]"
        ></span>
        <span>{{ t(getLevelConfig(level).labelKey) }}</span>
        <span v-if="showScore" class="opacity-75">({{ score }})</span>
    </span>
</template>
