<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    canEdit: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    canOpen: { type: Boolean, default: false },
    updateLabel: { type: String, default: 'Update document' },
});

const emit = defineEmits(['save', 'rename', 'delete', 'export']);
const open = ref(false);
const root = ref(null);

function closeOnOutsideClick(event) {
    if (!root.value?.contains(event.target)) open.value = false;
}

onMounted(() => window.addEventListener('click', closeOnOutsideClick));
onBeforeUnmount(() => window.removeEventListener('click', closeOnOutsideClick));

function run(action) {
    open.value = false;
    emit(action);
}
</script>

<template>
    <div ref="root" class="document-menu-wrap" @click.stop @keydown.esc="open = false">
        <button
            class="editor-more"
            type="button"
            aria-label="Document actions"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click.stop="open = !open"
        >···</button>
        <div v-if="open" class="document-menu" role="menu">
            <button v-if="canEdit || canOpen" type="button" role="menuitem" @click="run('save')">{{ updateLabel }}</button>
            <button v-if="canEdit" type="button" role="menuitem" @click="run('rename')">Rename document</button>
            <button type="button" role="menuitem" @click="run('export')">Export as PDF</button>
            <span v-if="canDelete" class="document-menu-divider"></span>
            <button v-if="canDelete" class="menu-danger" type="button" role="menuitem" @click="run('delete')">Delete document</button>
        </div>
    </div>
</template>
