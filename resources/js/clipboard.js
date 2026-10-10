// MI-05: copy a voucher code stored on the element's data-copy attribute.
// Confirms with a toast and swaps the icon for a short beat; falls back to a
// hidden textarea when the Clipboard API is not available (insecure context).

const RESET_MS = 1600;

export default function copyButton() {
    return {
        copied: false,
        _reset: null,

        async copy() {
            const text = this.$el?.dataset?.copy ?? '';
            if (!text) return;

            try {
                await navigator.clipboard.writeText(text);
            } catch (e) {
                const area = document.createElement('textarea');
                area.value = text;
                area.setAttribute('readonly', '');
                area.style.position = 'absolute';
                area.style.left = '-9999px';
                document.body.appendChild(area);
                area.select();
                try { document.execCommand('copy'); } catch (_) {}
                document.body.removeChild(area);
            }

            this.copied = true;
            window.Alpine?.store('toasts')?.push('Kode voucher disalin.');
            clearTimeout(this._reset);
            this._reset = setTimeout(() => { this.copied = false; }, RESET_MS);
        },
    };
}
