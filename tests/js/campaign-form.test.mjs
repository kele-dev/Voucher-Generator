// Run: node --test tests/js/campaign-form.test.mjs
import { test } from 'node:test';
import assert from 'node:assert/strict';
import campaignForm from '../../resources/js/campaign-form.js';

function form() {
    return campaignForm({ previewUrl: '/preview', menuItems: [], templates: [], outlets: [], brands: [], presets: {} });
}

const pick = (file) => ({ target: { files: file ? [file] : [] } });

test('picking a photo shows its name and a local thumbnail', () => {
    const f = form();
    const file = new File(['x'], 'bebek.png', { type: 'image/png' });

    f.pickImage(pick(file));

    assert.equal(f.pickedImage.name, 'bebek.png');
    assert.match(f.pickedImage.url, /^blob:/);
});

test('picking another photo revokes the previous object URL', () => {
    const f = form();
    const revoked = [];
    const original = URL.revokeObjectURL;
    URL.revokeObjectURL = (url) => revoked.push(url);
    try {
        f.pickImage(pick(new File(['a'], 'a.png')));
        const first = f.pickedImage.url;
        f.pickImage(pick(new File(['b'], 'b.png')));

        assert.deepEqual(revoked, [first]);
        assert.equal(f.pickedImage.name, 'b.png');
    } finally {
        URL.revokeObjectURL = original;
    }
});

test('clearing the file input clears the thumbnail', () => {
    const f = form();
    f.pickImage(pick(new File(['a'], 'a.png')));

    f.pickImage(pick(null));

    assert.equal(f.pickedImage, null);
});
