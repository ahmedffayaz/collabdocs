<script setup>
defineProps({
    message: { type: String, default: '' },
    type: { type: String, default: 'success' },
});
</script>

<template>
    <div v-if="message" :class="['toast', { 'error-toast': type === 'error' }]" role="status" aria-live="polite">
        <span>{{ type === 'error' ? '!' : '✓' }}</span>{{ message }}
    </div>
</template>
