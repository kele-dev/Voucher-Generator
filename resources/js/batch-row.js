// Polls a generation batch until both code generation and ZIP export have
// finished. Stops on its own; a page reload restarts it for unfinished rows.

const INTERVAL_MS = 2000;

export default function batchRow(initial, statusUrl) {
    return {
        batch: initial,
        timer: null,

        init() {
            if (!this.batch.finished) this.poll();
        },

        destroy() {
            clearTimeout(this.timer);
        },

        get generatedPercent() {
            return Math.round((this.batch.generated / Math.max(1, this.batch.requested)) * 100);
        },

        get exportedPercent() {
            return Math.round((this.batch.exported / Math.max(1, this.batch.requested)) * 100);
        },

        async poll() {
            try {
                const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                if (response.ok) this.batch = await response.json();
            } catch {
                // Network blip: keep polling.
            }
            if (!this.batch.finished) {
                this.timer = setTimeout(() => this.poll(), INTERVAL_MS);
            }
        },
    };
}
