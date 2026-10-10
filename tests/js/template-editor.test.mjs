// Run: node --test tests/js/template-editor.test.mjs
import { test, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import templateEditor from '../../resources/js/template-editor.js';

const realFetch = globalThis.fetch;
afterEach(() => { globalThis.fetch = realFetch; });

function editor() {
    const toasts = [];
    const ed = templateEditor({
        fields: [], samples: {}, saveUrl: '/save', sampleUploadUrl: '/upload', previewUrl: '/preview',
    });
    ed.$store = { toasts: { push: (m) => toasts.push(m) } };
    ed.headers = () => ({});
    ed.payload = () => '{}';
    ed.schedulePreview = () => {};
    ed.toasts = toasts;
    return ed;
}

const offline = async () => { throw new TypeError('Failed to fetch'); };

test('save shows an error when the network fails and frees the button', async () => {
    const ed = editor();
    globalThis.fetch = offline;
    ed.dirty = true;

    await ed.save();

    assert.equal(ed.busy, false);
    assert.equal(ed.dirty, true, 'unsaved edits stay marked as unsaved');
    assert.equal(ed.errors.length, 1);
    assert.match(ed.errors[0], /Gagal menyimpan/);
});

test('save still reports validation errors from the server', async () => {
    const ed = editor();
    ed.readErrors = async () => ['Posisi tidak valid'];
    globalThis.fetch = async () => ({ ok: false });

    await ed.save();

    assert.deepEqual(ed.errors, ['Posisi tidak valid']);
    assert.equal(ed.busy, false);
});

test('upload shows an error when the network fails and resets its state', async () => {
    const ed = editor();
    globalThis.fetch = offline;
    const input = { files: [new Blob(['x'])], value: 'sample.png' };

    await ed.uploadSample({ target: input });

    assert.equal(ed.uploadingSample, false);
    assert.equal(ed.busy, false);
    assert.equal(input.value, '', 'file input is cleared so the same file can be picked again');
    assert.match(ed.errors[0], /Gagal mengunggah/);
});

test('upload is flagged while in flight', async () => {
    const ed = editor();
    let seen;
    globalThis.fetch = async () => {
        seen = { uploading: ed.uploadingSample, busy: ed.busy };
        return { ok: true, json: async () => ({ url: '/img.png', message: 'Tersimpan' }) };
    };

    await ed.uploadSample({ target: { files: [new Blob(['x'])], value: 'a.png' } });

    assert.deepEqual(seen, { uploading: true, busy: true });
    assert.equal(ed.uploadingSample, false);
    assert.equal(ed.samples.product_image, '/img.png');
    assert.deepEqual(ed.toasts, ['Tersimpan']);
});
