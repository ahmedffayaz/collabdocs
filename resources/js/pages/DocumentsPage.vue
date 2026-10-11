<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import { documentService } from '../services/documentService.js';
import { downloadDocumentPdf } from '../services/documentPdf.js';
import { applyEditorFormat, clearNotification, formatDocumentDate, getInitials, notify, notifyError } from '../helpers.js';
const users = ref([]);
const currentUser = ref(null);
const documents = ref([]);
const activeDocument = ref(null);
const editor = ref(null);
const loading = ref(true);
const saving = ref(false);
const exporting = ref(false);
const search = ref('');
const filter = ref('all');
const showShare = ref(false);
const showUnsavedDialog = ref(false);
const showDeleteDialog = ref(false);
const showProfile = ref(false);
const pendingDeleteDocument = ref(null);
const shareEmail = ref('');
const sharePermission = ref('editor');
const toast = ref('');
const error = ref('');
const dirty = ref(false);
const titleInput = ref(null);
const savedTitle = ref('');
const savedContent = ref('');
let savePromise = null;

const visibleDocuments = computed(() => documents.value.filter((doc) => {
    const matchesFilter = filter.value === 'all' || (filter.value === 'owned' ? doc.access === 'owner' : doc.access !== 'owner');
    return matchesFilter && doc.title.toLowerCase().includes(search.value.toLowerCase());
}));
async function refresh() {
    const data = await documentService.getWorkspace();
    currentUser.value = data.user;
    users.value = data.users;
    documents.value = data.documents;
}
async function openDocument(doc) {
    if (activeDocument.value?.id === doc.id) return;
    const data = await documentService.getDocument(doc.id);
    savedTitle.value = data.title;
    savedContent.value = data.content || '<p></p>';
    activeDocument.value = data;
    dirty.value = false;
    showShare.value = false;
    nextTick(() => createEditor(savedContent.value, data.can_edit));
}
function createEditor(content, editable) {
    editor.value?.destroy();
    editor.value = new Editor({
        content,
        editable,
        extensions: [StarterKit, Underline],
        editorProps: { attributes: { class: 'tiptap', spellcheck: 'true', 'aria-label': 'Document content' } },
        onUpdate: () => {
            markDirty();
        },
    });
}
function markDirty() {
    if (!activeDocument.value?.can_edit) return;
    const content = editor.value?.getHTML() ?? savedContent.value;
    dirty.value = activeDocument.value.title !== savedTitle.value || content !== savedContent.value;
}
async function saveDocument() {
    if (!activeDocument.value?.can_edit || !dirty.value || !editor.value) return false;
    if (savePromise) {
        return savePromise;
    }

    const documentId = activeDocument.value.id;
    const content = editor.value.getHTML();
    const title = activeDocument.value.title.trim() || 'Untitled document';
    saving.value = true;
    savePromise = (async () => {
        try {
            const data = await documentService.updateDocument(documentId, {
                title,
                content,
            });
            if (activeDocument.value?.id !== documentId) return false;

            savedTitle.value = title;
            savedContent.value = content;
            activeDocument.value.title = activeDocument.value.title.trim() || 'Untitled document';
            activeDocument.value.updated_at = data.updated_at;
            const item = documents.value.find((doc) => doc.id === data.id);
            if (item) Object.assign(item, data);
            markDirty();
            return !dirty.value;
        } catch (err) {
            notifyError(error, err);
            return false;
        }
    })();

    try {
        return await savePromise;
    } finally {
        savePromise = null;
        saving.value = false;
    }
}
function leaveDocument() {
    editor.value?.destroy();
    editor.value = null;
    activeDocument.value = null;
    dirty.value = false;
    showShare.value = false;
    showUnsavedDialog.value = false;
    showDeleteDialog.value = false;
}
function requestBack() {
    if (dirty.value || saving.value) {
        showUnsavedDialog.value = true;
        return;
    }
    leaveDocument();
}
function discardChanges() {
    if (saving.value) return;
    if (activeDocument.value) {
        const item = documents.value.find((doc) => doc.id === activeDocument.value.id);
        if (item) item.title = savedTitle.value;
    }
    leaveDocument();
}
async function saveAndLeave() {
    if (await saveDocument()) leaveDocument();
}
async function saveDocumentNow() {
    if (await saveDocument()) notify(toast, 'Changes saved');
}
function focusTitle() {
    titleInput.value?.focus();
    titleInput.value?.select();
}
async function editDocumentTitle(doc) {
    await openDocument(doc);
    await nextTick();
    focusTitle();
}
async function exportDocument(doc, content = doc.content || '') {
    if (exporting.value) return;
    exporting.value = true;
    const titleText = doc.title || 'Untitled document';

    try {
        await downloadDocumentPdf(titleText, content);
        notify(toast, 'PDF downloaded');
    } catch (err) {
        notifyError(error, err);
    } finally {
        exporting.value = false;
    }
}
function exportActiveDocument() {
    if (!activeDocument.value) return;
    const doc = { ...activeDocument.value };
    const content = editor.value?.getHTML() || doc.content || '';
    exportDocument(doc, content);
}
function requestDeleteDocument(doc) {
    pendingDeleteDocument.value = doc;
    showDeleteDialog.value = true;
}
async function deleteDocument() {
    const target = pendingDeleteDocument.value || activeDocument.value;
    if (!target?.id) return;
    const id = target.id;
    try {
        await documentService.deleteDocument(id);
        documents.value = documents.value.filter((doc) => doc.id !== id);
        if (activeDocument.value?.id === id) leaveDocument();
        notify(toast, 'Document deleted');
    } catch (err) { notifyError(error, err); }
    finally {
        pendingDeleteDocument.value = null;
        showDeleteDialog.value = false;
    }
}
async function createDocument() {
    if (dirty.value || saving.value) {
        showUnsavedDialog.value = true;
        return;
    }
    try {
        const data = await documentService.createDocument({ title: 'Untitled document', content: '<p></p>' });
        documents.value.unshift(data);
        savedTitle.value = data.title;
        savedContent.value = '<p></p>';
        activeDocument.value = data;
        nextTick(() => createEditor('<p></p>', true));
        notify(toast, 'New document created');
    } catch (err) { notifyError(error, err); }
}
function renameDocument() {
    if (!activeDocument.value) return;
    activeDocument.value.title = activeDocument.value.title.trim() || 'Untitled document';
    markDirty();
}
async function switchUser(email) {
    if (dirty.value || saving.value) {
        showUnsavedDialog.value = true;
        return;
    }
    try {
        await documentService.switchUser({ email });
        activeDocument.value = null;
        editor.value?.destroy(); editor.value = null;
        showProfile.value = false;
        await refresh();
        notify(toast, `Viewing as ${currentUser.value.name}`);
    } catch (err) { notifyError(error, err); }
}
async function importFile(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    if (dirty.value || saving.value) {
        showUnsavedDialog.value = true;
        event.target.value = '';
        return;
    }

    try {
        if (file.size > 2 * 1024 * 1024) {
            notify(error, 'Files must be 2 MB or smaller.');
            return;
        }

        const extension = file.name.split('.').pop()?.toLowerCase();
        let data;
        if (extension === 'docx') {
            const { default: mammoth } = await import('mammoth/mammoth.browser.js');
            const converted = await mammoth.convertToHtml({ arrayBuffer: await file.arrayBuffer() });
            const content = converted.value.trim() || '<p></p>';
            if (content.length > 500000) {
                notify(error, 'This Word document is too large to import.');
                return;
            }
            data = await documentService.createDocument({
                title: file.name.replace(/\.docx$/i, '').trim() || 'Imported document',
                content,
            });
        } else {
            const body = new FormData();
            body.append('file', file);
            data = await documentService.importDocument(body);
        }
        documents.value.unshift(data);
        savedTitle.value = data.title;
        savedContent.value = data.content || '';
        activeDocument.value = data;
        nextTick(() => createEditor(data.content || '', true));
        notify(toast, `${file.name} imported`);
    } catch (err) { notifyError(error, err); }
    finally { event.target.value = ''; }
}
async function shareDocument() {
    try {
        const data = await documentService.shareDocument(activeDocument.value.id, {
            email: shareEmail.value,
            permission: sharePermission.value,
        });
        activeDocument.value.collaborators = data.collaborators;
        shareEmail.value = '';
        notify(toast, 'Access updated');
    } catch (err) { notifyError(error, err); }
}
onMounted(async () => {
    try { await refresh(); }
    catch (err) { notifyError(error, err); }
    finally { loading.value = false; }
});
watch(() => activeDocument.value?.title, (title) => {
    if (title) document.title = `${title} — Ajaia Docs`;
    markDirty();
});
onBeforeUnmount(() => { clearNotification(toast); clearNotification(error); editor.value?.destroy(); });
</script>

<template>
    <main class="app-shell">
        <aside class="sidebar">
            <div class="brand"><span class="brand-mark">a<span>.</span></span><span>ajaia<span class="brand-light">docs</span></span></div>
            <button class="new-doc" @click="createDocument"><span class="plus">＋</span> New document <span class="shortcut">⌘ N</span></button>
            <label class="import-button"><span class="import-icon">↥</span> Import a file <span class="file-label">.md · .txt · .docx</span><input type="file" accept=".md,.txt,.docx,text/plain,text/markdown,application/vnd.openxmlformats-officedocument.wordprocessingml.document" @change="importFile"></label>
            <div class="nav-label">YOUR WORKSPACE</div>
            <div class="filters">
                <button :class="{ selected: filter === 'all' }" @click="filter = 'all'"><span class="nav-icon">▦</span> All documents <span class="count">{{ documents.length }}</span></button>
                <button :class="{ selected: filter === 'owned' }" @click="filter = 'owned'"><span class="nav-icon">◷</span> Owned by me</button>
                <button :class="{ selected: filter === 'shared' }" @click="filter = 'shared'"><span class="nav-icon">⇄</span> Shared with me</button>
            </div>
            <div class="sidebar-bottom">
                <div class="workspace-card"><span class="workspace-glyph">✳</span><div><strong>Ajaia Studio</strong><small>Personal workspace</small></div><span class="chevron">⌄</span></div>
                <button class="profile-button" @click="showProfile = !showProfile">
                    <span class="avatar user-avatar">{{ getInitials(currentUser?.name) }}</span><span class="profile-name"><strong>{{ currentUser?.name || 'Loading…' }}</strong><small>{{ currentUser?.email }}</small></span><span class="more">···</span>
                </button>
                <div v-if="showProfile" class="profile-menu"><div class="menu-caption">Switch demo account</div><button v-for="user in users" :key="user.id" @click="switchUser(user.email)"><span class="avatar mini-avatar">{{ getInitials(user.name) }}</span><span>{{ user.name }}</span><span v-if="user.id === currentUser?.id" class="check">✓</span></button></div>
            </div>
        </aside>

        <section class="main-area">
            <AppHeader
                :document-title="activeDocument?.title || 'Documents'"
                :has-document="Boolean(activeDocument)"
                :saving="saving"
                :dirty="dirty"
                :user-initials="getInitials(currentUser?.name)"
            />

            <section v-if="!activeDocument" class="home-view">
                <div class="welcome-row"><div><div class="eyebrow"><span class="sparkle">✳</span> YOUR IDEAS, IN GOOD COMPANY</div><h1>A little room to <em>think.</em></h1><p class="welcome-subtitle">A calm place for the work you do together.</p></div><button class="primary-button" @click="createDocument"><span>＋</span> Create a document</button></div>
                <div class="overview-stats"><div class="stat-card"><span class="stat-icon lilac">▤</span><div><strong>{{ documents.length }}</strong><small>Documents</small></div></div><div class="stat-card"><span class="stat-icon peach">↗</span><div><strong>{{ documents.filter(d => d.access !== 'owner').length }}</strong><small>Shared with you</small></div></div><div class="stat-note"><span>✦</span> Good ideas get better when they’re shared.</div></div>
                <div class="list-heading"><div><h2>Your documents</h2><p>Everything you’ve been working on, all in one place.</p></div><div class="list-controls"><div class="search-field"><span>⌕</span><input v-model="search" placeholder="Search documents" aria-label="Search documents"><kbd>⌘ K</kbd></div><button class="sort-button">Recently edited <span>⌄</span></button></div></div>
                <div v-if="loading" class="empty-state"><div class="loader"></div><span>Gathering your documents…</span></div>
                <div v-else-if="visibleDocuments.length" class="document-table">
                    <div class="table-head"><span>NAME</span><span>ACCESS</span><span>LAST EDITED</span><span></span></div>
                    <div v-for="doc in visibleDocuments" :key="doc.id" class="document-row">
                        <button class="document-name document-open" type="button" @click="openDocument(doc)">
                            <span class="doc-icon">▤</span>
                            <span class="doc-title-wrap"><strong>{{ doc.title }}</strong><small>{{ doc.owner?.name === currentUser?.name ? 'You' : `Owned by ${doc.owner?.name}` }}</small></span>
                        </button>
                        <span><span :class="['access-pill', doc.access === 'owner' ? 'owner-pill' : 'shared-pill']">{{ doc.access === 'owner' ? '●  Owner' : doc.access === 'viewer' ? '◉  Viewer' : '⇄  Shared' }}</span></span>
                        <span class="edited-date">{{ formatDocumentDate(doc.updated_at) }}</span>
                        <DocumentActionsMenu
                            :can-edit="doc.can_edit"
                            :can-open="true"
                            :can-delete="doc.access === 'owner'"
                            :update-label="doc.can_edit ? 'Update document' : 'Open document'"
                            @save="openDocument(doc)"
                            @rename="editDocumentTitle(doc)"
                            @delete="requestDeleteDocument(doc)"
                            @export="exportDocument(doc)"
                        />
                    </div>
                </div>
                <div v-else class="empty-state"><div class="empty-illustration"><span>✧</span><span>▤</span><span>✳</span></div><h3>{{ search ? 'No documents found' : 'Your first page is waiting' }}</h3><p>{{ search ? 'Try another search term.' : 'Start with a blank page, or bring in a Markdown or text file.' }}</p><button class="text-action" @click="createDocument">Create your first document <span>→</span></button></div>
                <AppFooter />
            </section>

            <section v-else class="editor-view">
                <div class="editor-header"><div class="editor-leading"><button class="back-button" aria-label="Back to documents" title="Back to documents" @click="requestBack"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M11.8 4.5 6.3 10l5.5 5.5M6.7 10h7" /></svg></button><div class="editor-title-area"><div class="doc-breadcrumb"><span>My documents</span><span>/</span><span>{{ activeDocument.access === 'owner' ? 'Owned by me' : 'Shared with me' }}</span></div><input ref="titleInput" v-model="activeDocument.title" class="title-input" :readonly="!activeDocument.can_edit" @blur="renameDocument" @keydown.enter="$event.target.blur()"><div class="doc-meta"><span>Last edited {{ formatDocumentDate(activeDocument.updated_at) }}</span><span class="meta-dot">·</span><span>{{ activeDocument.access === 'owner' ? 'Private to you' : `Shared by ${activeDocument.owner?.name}` }}</span></div></div></div><div class="editor-actions"><div v-if="activeDocument.collaborators?.length" class="collaborator-stack"><span v-for="person in activeDocument.collaborators.slice(0,3)" :key="person.id" class="avatar collaborator-avatar" :title="person.name">{{ getInitials(person.name) }}</span><span v-if="activeDocument.collaborators.length > 3" class="avatar extra-avatar">+{{ activeDocument.collaborators.length - 3 }}</span></div><button v-if="dirty && activeDocument.can_edit" class="save-changes-button" :disabled="saving" @click="saveDocumentNow">{{ saving ? 'Saving…' : 'Save changes' }}</button><button v-if="activeDocument.access === 'owner'" class="share-button" @click="showShare = !showShare"><span>↗</span> Share</button><DocumentActionsMenu :can-edit="activeDocument.can_edit" :can-delete="activeDocument.access === 'owner'" @save="saveDocumentNow" @rename="focusTitle" @delete="requestDeleteDocument(activeDocument)" @export="exportActiveDocument" /></div></div>
                <div class="format-toolbar" v-if="activeDocument.can_edit"><span class="toolbar-style">Normal text <span>⌄</span></span><span class="toolbar-divider"></span><button :class="{ active: editor?.isActive('bold') }" title="Bold" @click="applyEditorFormat(editor, 'bold')"><b>B</b></button><button :class="{ active: editor?.isActive('italic') }" title="Italic" @click="applyEditorFormat(editor, 'italic')"><i>I</i></button><button :class="{ active: editor?.isActive('underline') }" title="Underline" @click="applyEditorFormat(editor, 'underline')"><u>U</u></button><span class="toolbar-divider"></span><button :class="{ active: editor?.isActive('heading', { level: 2 }) }" title="Heading" @click="applyEditorFormat(editor, 'heading')">H<sub>2</sub></button><span class="toolbar-divider"></span><button :class="{ active: editor?.isActive('bulletList') }" title="Bulleted list" @click="applyEditorFormat(editor, 'bullet')">☷</button><button :class="{ active: editor?.isActive('orderedList') }" title="Numbered list" @click="applyEditorFormat(editor, 'ordered')">☷<sup>1</sup></button><span class="toolbar-divider"></span><button title="Undo" @click="editor?.chain().focus().undo().run()">↶</button><button title="Redo" @click="editor?.chain().focus().redo().run()">↷</button></div>
                <div v-else class="read-only-banner"><span>◉</span> You have view-only access to this document.</div>
                <div class="page-viewport"><div class="paper"><editor-content v-if="editor" :editor="editor"/><div v-else class="editor-loading">Opening your document…</div></div></div>
                <div v-if="showShare && activeDocument.access === 'owner'" class="share-popover"><div class="share-popover-head"><div><h3>Share document</h3><p>Invite a teammate to work with you.</p></div><button class="close-button" @click="showShare = false">×</button></div><form class="invite-form" @submit.prevent="shareDocument"><div class="invite-fields"><select v-model="shareEmail" required aria-label="Choose a teammate"><option value="" disabled>Choose a teammate</option><option v-for="person in users.filter(u => u.id !== currentUser?.id)" :key="person.id" :value="person.email">{{ person.name }} · {{ person.email }}</option></select><select v-model="sharePermission" aria-label="Permission"><option value="editor">Editor</option><option value="viewer">Viewer</option></select></div><button class="invite-submit" type="submit">Send invite <span>→</span></button></form><div class="access-heading">People with access <span>{{ (activeDocument.collaborators || []).length + 1 }}</span></div><div class="access-person"><span class="avatar owner-avatar">{{ getInitials(activeDocument.owner?.name) }}</span><span class="person-info"><strong>{{ activeDocument.owner?.name }} <small>(you)</small></strong><small>{{ activeDocument.owner?.email }}</small></span><span class="permission-label">Owner</span></div><div v-for="person in activeDocument.collaborators" :key="person.id" class="access-person"><span class="avatar collaborator-avatar">{{ getInitials(person.name) }}</span><span class="person-info"><strong>{{ person.name }}</strong><small>{{ person.email }}</small></span><span class="permission-label">{{ person.permission }}</span></div><p class="share-note">Anyone you add here can find this document in their shared workspace.</p></div>
            </section>
        </section>
        <AppToast :message="error || toast" :type="error ? 'error' : 'success'" />
        <ConfirmActionDialog :open="showDeleteDialog" title="Delete this document?" message="This permanently removes the document and its content. This action cannot be undone." confirm-label="Delete document" @confirm="deleteDocument" @cancel="showDeleteDialog = false" />
        <UnsavedChangesDialog
            :open="showUnsavedDialog"
            :dirty="dirty"
            :saving="saving"
            @save="saveAndLeave"
            @discard="discardChanges"
            @cancel="showUnsavedDialog = false"
            @leave="leaveDocument"
        />
    </main>
</template>
