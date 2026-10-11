const notificationTimers = new WeakMap();

export function getInitials(name = '') {
    return name.split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase();
}

export function formatDocumentDate(date) {
    if (!date) return 'Just now';
    const then = new Date(date);
    const today = new Date();
    if (then.toDateString() === today.toDateString()) {
        return `Today, ${then.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`;
    }
    return then.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

export function notify(target, message, duration = 2600) {
    clearNotification(target);
    target.value = message;
    notificationTimers.set(target, setTimeout(() => { target.value = ''; }, duration));
}

export function notifyError(target, error, duration = 4500) {
    const response = error.response?.data;
    const validationMessages = Object.values(response?.data || {}).flat();
    notify(target, validationMessages[0] || response?.message || 'Something went wrong. Please try again.', duration);
}

export function clearNotification(target) {
    const timer = notificationTimers.get(target);
    if (timer) clearTimeout(timer);
    notificationTimers.delete(target);
    target.value = '';
}

export function applyEditorFormat(editor, command) {
    if (!editor) return;
    const actions = {
        bold: () => editor.chain().focus().toggleBold().run(),
        italic: () => editor.chain().focus().toggleItalic().run(),
        underline: () => editor.chain().focus().toggleUnderline().run(),
        heading: () => editor.chain().focus().toggleHeading({ level: 2 }).run(),
        bullet: () => editor.chain().focus().toggleBulletList().run(),
        ordered: () => editor.chain().focus().toggleOrderedList().run(),
    };
    actions[command]?.();
}
