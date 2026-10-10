const panel = document.querySelector('[data-admin-notifications]');
let busy = false;
const refresh = async () => {
    if (!panel || busy || document.hidden) return;
    busy = true;
    try {
        const response = await fetch(panel.dataset.adminNotifications, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, cache: 'no-store', credentials: 'same-origin', redirect: 'error'});
        if (!response.ok) throw new Error('unavailable');
        const counts = await response.json();
        for (const key of ['messages', 'notifications']) {
            const count = counts[key];
            if (!Number.isInteger(count) || count < 0) throw new Error('invalid');
            const badge = document.querySelector(`[data-admin-count="${key}"]`);
            if (badge) badge.textContent = count > 0 ? String(count) : '';
        }
        panel.textContent = 'Notifications actualisées';
    } catch { panel.textContent = 'Actualisation indisponible. Rechargez la page.'; }
    finally { busy = false; }
};
if (panel) { refresh(); setInterval(refresh, 30000); document.addEventListener('visibilitychange', refresh); }
document.querySelectorAll('.admin-form').forEach(form => form.addEventListener('submit', () => {
    if (!form.checkValidity()) return;
    const button = form.querySelector('button[type="submit"], button:not([type])');
    if (button) { button.disabled = true; button.textContent = 'Enregistrement…'; }
}));

// Explicit row actions only. Fiscal amounts are always recalculated by the server.
const documentEditor = document.querySelector('[data-document-editor]');
if (documentEditor) {
    const lines = documentEditor.querySelector('[data-document-lines]');
    const template = documentEditor.querySelector('[data-document-template]');
    documentEditor.querySelector('[data-document-add]')?.addEventListener('click', () => {
        if (!template || lines.children.length >= 50) return;
        const index = Number(lines.dataset.nextLine);
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach(input => { input.name = input.name.replace('__INDEX__', String(index)); });
        lines.append(fragment);
        lines.dataset.nextLine = String(index + 1);
        lines.lastElementChild.querySelector('input')?.focus();
    });
    lines.addEventListener('click', event => {
        const button = event.target.closest('[data-document-remove]');
        if (button && lines.children.length > 1) button.closest('[data-document-line]').remove();
    });
}
