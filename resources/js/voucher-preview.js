// Server-rendered voucher preview (POST campaigns.preview -> JPEG). Used on
// the campaign page and, mixed into campaignForm, live while editing.

function appendValue(data, key, value) {
    if (value === null || value === undefined) return;
    if (Array.isArray(value)) {
        value.forEach((item, i) => appendValue(data, `${key}[${i}]`, item));
    } else if (typeof value === 'object') {
        Object.entries(value).forEach(([k, v]) => appendValue(data, `${key}[${k}]`, v));
    } else {
        data.append(key, value);
    }
}

export function toFormData(payload) {
    const data = new FormData();
    Object.entries(payload).forEach(([key, value]) => appendValue(data, key, value));
    return data;
}

export default function voucherPreview({ url }) {
    return {
        previewUrl: url,
        src: null,
        loading: false,
        error: null,
        _previewAbort: null,

        async load(payload) {
            const data = payload instanceof FormData ? payload : toFormData(payload);

            this._previewAbort?.abort();
            const controller = new AbortController();
            this._previewAbort = controller;
            this.loading = true;
            this.error = null;

            try {
                const response = await fetch(this.previewUrl, {
                    method: 'POST',
                    body: data,
                    signal: controller.signal,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        Accept: 'image/jpeg',
                    },
                });
                if (!response.ok) {
                    this.error = response.status === 422
                        ? 'Pilih template untuk melihat pratinjau.'
                        : response.status === 429 ? 'Terlalu sering memperbarui, tunggu sebentar.' : 'Pratinjau gagal dimuat.';
                    return;
                }
                const blob = await response.blob();
                if (this.src) URL.revokeObjectURL(this.src);
                this.src = URL.createObjectURL(blob);
            } catch (e) {
                if (e.name !== 'AbortError') this.error = 'Pratinjau gagal dimuat.';
            } finally {
                if (this._previewAbort === controller) this.loading = false;
            }
        },
    };
}
