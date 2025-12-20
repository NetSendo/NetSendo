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
        src="https://gregciupek.com/wp-content/uploads/2025/12/Logo-NetSendo-1700-x-500-px-1.png" 
        alt="NetSendo Logo"
        :class="class"
    />
</template>
