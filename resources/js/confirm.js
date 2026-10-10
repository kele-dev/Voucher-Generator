// Accessible replacement for window.confirm(): one native <dialog> in the
// layout (x-confirm-dialog), driven by this store. showModal() gives focus
// containment, Esc and top-layer stacking; focus returns to the opener.
//
// Usage on a form:
//   @submit.prevent="$store.confirm.ask({ title, body, confirmLabel, tone: 'danger' }).then(ok => ok && $el.submit())"

export default {
    open: false,
    title: '',
    body: '',
    confirmLabel: 'Ya, lanjutkan',
    cancelLabel: 'Batal',
    tone: 'danger',
    _resolve: null,
    _opener: null,

    ask({ title, body = '', confirmLabel = 'Ya, lanjutkan', cancelLabel = 'Batal', tone = 'danger' }) {
        // A second ask while one is open settles the first as cancelled.
        this._settle(false);
        Object.assign(this, { title, body, confirmLabel, cancelLabel, tone, open: true });
        this._opener = document.activeElement;

        const dialog = document.getElementById('confirm-dialog');
        if (!dialog?.showModal) {
            // No dialog element (very old browser): fall back, never block.
            this.open = false;
            return Promise.resolve(window.confirm(body ? `${title}\n\n${body}` : title));
        }
        if (!dialog.open) dialog.showModal();

        return new Promise((resolve) => { this._resolve = resolve; });
    },

    // Settle before closing: the dialog's close event (Esc, backdrop) also
    // calls answer(false), which must not override an explicit answer.
    answer(ok) {
        this._settle(ok);
        const dialog = document.getElementById('confirm-dialog');
        if (dialog?.open) dialog.close();
    },

    _settle(ok) {
        this.open = false;
        const resolve = this._resolve;
        this._resolve = null;
        if (resolve) {
            resolve(ok);
            this._opener?.focus?.();
        }
    },
};
