// Alpine component for the voucher template editor.
//
// Fields are stored with coordinates normalized to 0-1 against the template's
// native size and font_size in native pixels, so the on-screen layout is just
// the stored layout multiplied by the current display scale. The CSS preview
// is approximate; the server preview (same renderer as real vouchers) is the
// source of truth.

import { snapBox } from './snap.js';

const WEIGHTS = { regular: 400, semibold: 600, bold: 700, extrabold: 800 };
const SNAP_PX = 6; // on-screen snap distance
// The preview route allows 30 renders/min; one per 2s of quiet stays under it.
const AUTO_PREVIEW_DELAY = 2000;
const RATE_LIMIT_BACKOFF = 60000; // fallback when 429 carries no Retry-After
const RATE_LIMIT_MESSAGE = 'Preview terlalu sering; tunggu sebentar.';
const SAVE_FAILED_MESSAGE = 'Gagal menyimpan: koneksi terputus atau server tidak merespons. Perubahan belum hilang, coba lagi.';
const UPLOAD_FAILED_MESSAGE = 'Gagal mengunggah gambar: koneksi terputus atau server tidak merespons. Coba lagi.';
const MIN_SIZE = 0.01;

const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

export default function templateEditor(config) {
    return {
        ...config,
        selectedIndex: null,
        displayWidth: 0,
        dirty: false,
        busy: false,
        uploadingSample: false, // upload only; busy also covers save
        errors: [], // save + upload; cleared only when those run again
        previewErrors: [], // preview only; never touches errors
        previewSrc: null,
        previewing: false,
        guides: [],
        dragging: false,
        autoPreview: (() => { try { return localStorage.getItem('editor-auto-preview') !== '0'; } catch (e) { return true; } })(),
        _previewTimer: null,
        _previewPausedUntil: 0,
        _previewAbort: null,
        pauseSeconds: 0,
        _pauseTicker: null,

        init() {
            // Any layout change (drag, resize, property edit, add/remove)
            // schedules a server preview once the editing settles.
            this.$watch('fields', () => this.schedulePreview(), { deep: true });
            this.$watch('autoPreview', (on) => {
                try { localStorage.setItem('editor-auto-preview', on ? '1' : '0'); } catch (e) {}
                if (on) this.schedulePreview(0);
                else clearTimeout(this._previewTimer); // a pending render must not fire after "off"
            });
            if (this.autoPreview && this.fields.length) this.schedulePreview(0);

            const observer = new ResizeObserver(([entry]) => {
                this.displayWidth = entry.contentRect.width;
            });
            observer.observe(this.$refs.stage);

            window.addEventListener('beforeunload', (event) => {
                if (this.dirty) event.preventDefault();
            });
        },

        get scale() {
            return this.displayWidth / this.width;
        },

        get selected() {
            return this.selectedIndex === null ? null : this.fields[this.selectedIndex];
        },

        get unusedKeys() {
            const used = new Set(this.fields.map((f) => f.key));
            return Object.keys(this.fieldConfig).filter((key) => !used.has(key));
        },

        // Unused slots grouped for the "add field" panel (config field_groups).
        get unusedGroups() {
            const unused = this.unusedKeys;
            return Object.entries(this.fieldGroups)
                .map(([key, label]) => ({ key, label, keys: unused.filter((k) => (this.fieldConfig[k].group ?? 'benefit') === key) }))
                .filter((group) => group.keys.length);
        },

        label(key) {
            return this.fieldConfig[key]?.label ?? key;
        },

        addField(key) {
            if (!key) return;
            const isImage = this.fieldConfig[key].type === 'image';
            const base = { key, type: isImage ? 'image' : 'text', x: 0.05, y: 0.05, width: isImage ? 0.3 : 0.25, height: isImage ? 0.4 : 0.07 };

            this.fields.push(isImage ? { ...base, fit: 'contain' } : {
                ...base,
                font_family: Object.keys(this.fonts)[0],
                font_weight: 'bold',
                font_size: 32,
                color: '#000000',
                align: 'center',
                vertical_align: 'middle',
                uppercase: false,
                wrap: false,
                line_height: 1.25,
                date_format: this.fieldConfig[key].type === 'date' ? 'j F Y' : null,
                cover_enabled: false,
                cover_color: '#FFFFFF',
                cover_inset: 0,
            });
            this.selectedIndex = this.fields.length - 1;
            this.dirty = true;
        },

        removeSelected() {
            if (this.selectedIndex === null) return;
            this.fields.splice(this.selectedIndex, 1);
            this.selectedIndex = null;
            this.dirty = true;
        },

        boxStyle(field) {
            return {
                left: `${field.x * 100}%`,
                top: `${field.y * 100}%`,
                width: `${field.width * 100}%`,
                height: `${field.height * 100}%`,
            };
        },

        textStyle(field) {
            const justify = { left: 'flex-start', center: 'center', right: 'flex-end' };
            const align = { top: 'flex-start', middle: 'center', bottom: 'flex-end' };

            return {
                fontFamily: 'Poppins, sans-serif',
                fontWeight: WEIGHTS[field.font_weight] ?? 700,
                fontSize: `${field.font_size * this.scale}px`,
                lineHeight: field.line_height,
                color: field.color,
                textTransform: field.uppercase ? 'uppercase' : 'none',
                textAlign: field.align,
                justifyContent: justify[field.align],
                alignItems: align[field.vertical_align],
                whiteSpace: field.wrap ? 'normal' : 'nowrap',
                background: field.cover_enabled ? field.cover_color : 'transparent',
            };
        },

        sampleText(field) {
            const value = this.samples[field.key];
            if (this.fieldConfig[field.key]?.type === 'date' && value) {
                return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
            }
            return value ?? '';
        },

        // Drag to move, or drag the corner handle to resize. Deltas are
        // converted to normalized units using the stage's rendered size.
        startDrag(event, index, mode) {
            if (!this.canEdit) return;
            event.preventDefault();
            this.selectedIndex = index;

            const field = this.fields[index];
            const rect = this.$refs.stage.getBoundingClientRect();
            const start = { px: event.clientX, py: event.clientY, x: field.x, y: field.y, w: field.width, h: field.height };

            const others = this.fields.filter((_, i) => i !== index);
            const threshold = { x: SNAP_PX / rect.width, y: SNAP_PX / rect.height };
            this.dragging = true;

            const move = (e) => {
                const dx = (e.clientX - start.px) / rect.width;
                const dy = (e.clientY - start.py) / rect.height;

                let box = mode === 'move'
                    ? { x: start.x + dx, y: start.y + dy, width: field.width, height: field.height }
                    : { x: field.x, y: field.y, width: start.w + dx, height: start.h + dy };

                // Alt held = free placement, no snapping.
                if (e.altKey) {
                    this.guides = [];
                } else {
                    const snapped = snapBox(box, others, mode, threshold);
                    box = snapped.box;
                    this.guides = snapped.guides;
                }

                if (mode === 'move') {
                    field.x = clamp(box.x, 0, 1 - field.width);
                    field.y = clamp(box.y, 0, 1 - field.height);
                } else {
                    field.width = clamp(box.width, MIN_SIZE, 1 - field.x);
                    field.height = clamp(box.height, MIN_SIZE, 1 - field.y);
                }
                this.dirty = true;
            };

            const stop = () => {
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', stop);
                this.guides = [];
                this.dragging = false;
                this.schedulePreview();
            };

            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', stop);
        },

        // Keyboard nudging for precise placement: arrows move 1px (native),
        // shift+arrows 10px.
        nudge(event) {
            if (!this.canEdit || !this.selected) return;
            if (event.target.closest('input, select, textarea')) return;
            const step = (event.shiftKey ? 10 : 1);
            const f = this.selected;
            const dx = { ArrowLeft: -step, ArrowRight: step }[event.key] ?? 0;
            const dy = { ArrowUp: -step, ArrowDown: step }[event.key] ?? 0;
            if (!dx && !dy) return;
            event.preventDefault();
            f.x = clamp(f.x + dx / this.width, 0, 1 - f.width);
            f.y = clamp(f.y + dy / this.height, 0, 1 - f.height);
            this.dirty = true;
        },

        // Numeric inputs edit in native pixels; storage stays normalized.
        px(field, prop) {
            const total = prop === 'x' || prop === 'width' ? this.width : this.height;
            return Math.round(field[prop] * total);
        },

        setPx(field, prop, value) {
            const total = prop === 'x' || prop === 'width' ? this.width : this.height;
            field[prop] = clamp(Number(value) / total, 0, 1);
            this.dirty = true;
        },

        payload() {
            return JSON.stringify({ fields: this.fields });
        },

        headers(extra = {}) {
            return {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                Accept: 'application/json',
                ...extra,
            };
        },

        async readErrors(response) {
            if (response.status === 422) {
                const body = await response.json();
                return Object.values(body.errors ?? {}).flat();
            }
            return [`Gagal (${response.status}).`];
        },

        guideStyle(guide) {
            return guide.axis === 'x'
                ? { left: `${guide.pos * 100}%`, top: 0, bottom: 0, width: '1px' }
                : { top: `${guide.pos * 100}%`, left: 0, right: 0, height: '1px' };
        },

        // Seconds left in a 429 pause, ticking for the button label.
        startPauseCountdown() {
            clearInterval(this._pauseTicker);
            const tick = () => {
                this.pauseSeconds = Math.max(0, Math.ceil((this._previewPausedUntil - Date.now()) / 1000));
                if (!this.pauseSeconds) {
                    clearInterval(this._pauseTicker);
                    // Drop only the rate-limit notice once the window reopens.
                    this.previewErrors = this.previewErrors.filter((e) => e !== RATE_LIMIT_MESSAGE);
                }
            };
            tick();
            this._pauseTicker = setInterval(tick, 1000);
        },

        // Debounced auto preview: never mid-drag, never while rate-limited.
        schedulePreview(delay = AUTO_PREVIEW_DELAY) {
            clearTimeout(this._previewTimer);
            if (!this.autoPreview || !this.fields.length) return;
            this._previewTimer = setTimeout(() => {
                if (!this.autoPreview || this.dragging) return; // stop() reschedules
                const wait = this._previewPausedUntil - Date.now();
                if (wait > 0) return this.schedulePreview(wait);
                this.preview();
            }, delay);
        },

        async save() {
            this.busy = true;
            this.errors = [];
            try {
                const response = await fetch(this.saveUrl, {
                    method: 'PUT',
                    headers: this.headers({ 'Content-Type': 'application/json' }),
                    body: this.payload(),
                });
                if (!response.ok) {
                    this.errors = await this.readErrors(response);
                    return;
                }
                const body = await response.json();
                this.fields = body.fields;
                this.dirty = false;
                this.$store.toasts.push(body.message);
            } catch (e) {
                // Network failure or an unreadable body: keep the edits, say so.
                this.errors = [SAVE_FAILED_MESSAGE];
            } finally {
                this.busy = false;
            }
        },

        // Renders the current, unsaved layout with the real renderer.
        // Only the newest request may update the image: a newer render
        // aborts the one in flight, so a slow old response cannot overwrite it.
        async preview() {
            // Rate-limited: every caller (button included) waits out the window.
            if (Date.now() < this._previewPausedUntil) return;
            clearTimeout(this._previewTimer);
            this._previewAbort?.abort();
            const controller = new AbortController();
            this._previewAbort = controller;
            this.previewing = true;
            this.previewErrors = [];
            try {
                const response = await fetch(this.previewUrl, {
                    method: 'POST',
                    headers: this.headers({ 'Content-Type': 'application/json' }),
                    body: this.payload(),
                    signal: controller.signal,
                });
                if (response.status === 429) {
                    // Laravel's throttle says when the window reopens.
                    const seconds = Number(response.headers.get('Retry-After'));
                    const wait = Number.isFinite(seconds) && seconds > 0 ? seconds * 1000 : RATE_LIMIT_BACKOFF;
                    this._previewPausedUntil = Date.now() + wait;
                    this.previewErrors = [RATE_LIMIT_MESSAGE];
                    this.startPauseCountdown();
                    this.schedulePreview(wait);
                    return;
                }
                if (!response.ok) {
                    this.previewErrors = await this.readErrors(response);
                    return;
                }
                const blob = await response.blob();
                if (controller.signal.aborted) return;
                if (this.previewSrc) URL.revokeObjectURL(this.previewSrc);
                this.previewSrc = URL.createObjectURL(blob);
            } catch (e) {
                if (e.name !== 'AbortError') this.previewErrors = ['Preview gagal dimuat.'];
            } finally {
                if (this._previewAbort === controller) this.previewing = false;
            }
        },

        async uploadSample(event) {
            const file = event.target.files[0];
            if (!file) return;

            const data = new FormData();
            data.append('sample_image', file);

            this.busy = true;
            this.uploadingSample = true;
            this.errors = [];
            try {
                const response = await fetch(this.sampleUploadUrl, { method: 'POST', headers: this.headers(), body: data });
                if (!response.ok) {
                    this.errors = await this.readErrors(response);
                    return;
                }
                const body = await response.json();
                this.samples.product_image = body.url;
                this.$store.toasts.push(body.message);
                this.schedulePreview(0);
            } catch (e) {
                this.errors = [UPLOAD_FAILED_MESSAGE];
            } finally {
                this.busy = false;
                this.uploadingSample = false;
                event.target.value = '';
            }
        },
    };
}
