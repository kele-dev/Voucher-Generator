// Toast stack (x-toast-stack in the layout). Announced through an
// aria-live region; auto-dismiss pauses while hovered or focused.

const DURATION = 5000;
let nextId = 1;

export default {
    items: [],

    push(message, type = 'success') {
        if (!message) return;
        const toast = { id: nextId++, message, type, paused: false, remaining: DURATION, startedAt: Date.now(), timer: null, rev: 0 };
        this.items.push(toast);
        this._schedule(toast);
        // Keep the stack short.
        if (this.items.length > 4) this.dismiss(this.items[0].id);
    },

    dismiss(id) {
        const toast = this.items.find((t) => t.id === id);
        if (toast) clearTimeout(toast.timer);
        this.items = this.items.filter((t) => t.id !== id);
    },

    pause(id) {
        const toast = this.items.find((t) => t.id === id);
        if (!toast || toast.paused) return;
        clearTimeout(toast.timer);
        toast.remaining -= Date.now() - toast.startedAt;
        toast.paused = true;
    },

    resume(id) {
        const toast = this.items.find((t) => t.id === id);
        if (!toast || !toast.paused) return;
        toast.paused = false;
        // Bumping rev remounts the bar so its countdown restarts from `remaining`.
        toast.rev += 1;
        this._schedule(toast);
    },

    _schedule(toast) {
        toast.startedAt = Date.now();
        toast.timer = setTimeout(() => this.dismiss(toast.id), Math.max(toast.remaining, 1000));
    },
};
