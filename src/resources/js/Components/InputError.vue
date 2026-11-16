<script setup>
defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-rose-700 dark:text-rose-300">
            {{ message }}
        </p>
    </div>
</template>
