<script setup>
defineProps({
    documentTitle: { type: String, default: 'Documents' },
    hasDocument: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    dirty: { type: Boolean, default: false },
    userInitials: { type: String, default: '' },
});
</script>

<template>
    <header class="topbar">
        <div class="breadcrumbs">
            <span>Workspace</span><span class="crumb-slash">/</span><strong>{{ documentTitle }}</strong>
        </div>
        <div class="top-actions">
            <span v-if="hasDocument" class="save-state">
                <i :class="saving ? 'saving-dot' : 'saved-dot'"></i>
                {{ saving ? 'Saving…' : dirty ? 'Unsaved changes' : 'All changes saved' }}
            </span>
            <button class="icon-button help-button" aria-label="Help">?</button>
            <span class="avatar top-avatar">{{ userInitials }}</span>
        </div>
    </header>
</template>
