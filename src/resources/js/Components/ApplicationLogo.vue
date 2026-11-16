<script setup>
defineProps({
    class: {
        type: String,
        default: ''
    }
});
</script>

<template>
    <img 
        src="/images/brand/netsendo-logo.png" 
        alt="NetSendo Logo"
        :class="class"
    />
</template>
