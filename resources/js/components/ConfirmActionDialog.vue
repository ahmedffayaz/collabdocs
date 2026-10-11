<script setup>
defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
});

defineEmits(['confirm', 'cancel']);
</script>

<template>
    <div v-if="open" class="dialog-backdrop" @click.self="$emit('cancel')">
        <section class="confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title">
            <button class="dialog-close" aria-label="Cancel" @click="$emit('cancel')">×</button>
            <span class="dialog-icon dialog-icon-danger">!</span>
            <h2 id="confirm-title">{{ title }}</h2>
            <p>{{ message }}</p>
            <div class="dialog-actions">
                <button class="dialog-secondary" @click="$emit('cancel')">Cancel</button>
                <button class="dialog-danger" @click="$emit('confirm')">{{ confirmLabel }}</button>
            </div>
        </section>
    </div>
</template>
