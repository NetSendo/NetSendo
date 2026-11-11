<script setup>
import { computed } from "vue";

/** A trend line without axes; it takes its colour from the text colour of its parent. */
const props = defineProps({
    values: { type: Array, default: () => [] },
});

const WIDTH = 100;
const HEIGHT = 28;
const PAD = 2;

const points = computed(() => {
    const values = props.values.map((v) => Number(v) || 0);
    if (values.length < 2) return [];

    const max = Math.max(...values);
    const min = Math.min(...values);
    const span = max - min;

    return values.map((value, index) => {
        const x = (index / (values.length - 1)) * WIDTH;
        // A flat series sits on the baseline instead of floating in the middle
        const y =
            span === 0
                ? HEIGHT - PAD
                : HEIGHT - PAD - ((value - min) / span) * (HEIGHT - 2 * PAD);
        return [x.toFixed(2), y.toFixed(2)];
    });
});

const line = computed(() => points.value.map((p) => p.join(",")).join(" "));
const area = computed(() =>
    points.value.length
        ? `0,${HEIGHT} ${line.value} ${WIDTH},${HEIGHT}`
        : "",
);
</script>

<template>
    <svg
        aria-hidden="true"
        :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
        preserveAspectRatio="none"
        fill="none"
    >
        <polygon v-if="area" :points="area" fill="currentColor" opacity="0.1" />
        <polyline
            v-if="line"
            :points="line"
            stroke="currentColor"
            stroke-width="1.75"
            stroke-linecap="round"
            stroke-linejoin="round"
            vector-effect="non-scaling-stroke"
        />
    </svg>
</template>
