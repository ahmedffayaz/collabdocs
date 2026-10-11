<script setup>
defineProps({
    open: { type: Boolean, default: false },
    dirty: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
});

defineEmits(['save', 'discard', 'cancel', 'leave']);
</script>

<template>
    <div v-if="open" class="dialog-backdrop" @click.self="$emit('cancel')">
        <section class="confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="unsaved-title">
            <button class="dialog-close" aria-label="Keep editing" @click="$emit('cancel')">×</button>
            <span class="dialog-icon">↶</span>
            <h2 id="unsaved-title">{{ dirty ? 'Unsaved changes' : 'Changes saved' }}</h2>
            <p v-if="saving">Saving your latest changes. You can leave when saving finishes.</p>
            <p v-else-if="dirty">You have changes that haven’t been saved. Save them before leaving, or discard them.</p>
            <p v-else>Your latest changes are saved. Return to your documents?</p>
            <div class="dialog-actions">
                <button class="dialog-secondary" @click="$emit('cancel')">Keep editing</button>
                <button v-if="dirty" class="dialog-discard" :disabled="saving" @click="$emit('discard')">Discard changes</button>
                <button v-else class="dialog-primary" @click="$emit('leave')">Back to documents</button>
                <button v-if="dirty" class="dialog-primary" :disabled="saving" @click="$emit('save')">{{ saving ? 'Saving…' : 'Save changes' }}</button>
            </div>
        </section>
    </div>
</template>
