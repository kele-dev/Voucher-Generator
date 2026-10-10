// Run: node --test tests/js/snap.test.mjs
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { nearest, snapBox } from '../../resources/js/snap.js';

const t = { x: 0.01, y: 0.01 };
const close = (a, b) => assert.ok(Math.abs(a - b) < 1e-9, `${a} !== ${b}`);

test('nearest picks the closest line within the threshold', () => {
    assert.deepEqual(nearest([0.495], [0, 0.5, 1], 0.01), { delta: 0.5 - 0.495, line: 0.5 });
    assert.equal(nearest([0.3], [0, 0.5, 1], 0.01), null);
});

test('moving box snaps its left edge to another box', () => {
    const other = { x: 0.2, y: 0.6, width: 0.3, height: 0.1 };
    const { box, guides } = snapBox({ x: 0.205, y: 0.3, width: 0.1, height: 0.1 }, [other], 'move', t);
    close(box.x, 0.2);
    assert.deepEqual(guides, [{ axis: 'x', pos: 0.2 }]);
});

test('moving box snaps its centre to the image centre', () => {
    const { box, guides } = snapBox({ x: 0.296, y: 0.2, width: 0.4, height: 0.1 }, [], 'move', t);
    close(box.x + box.width / 2, 0.5);
    assert.deepEqual(guides.map((g) => g.axis), ['x']);
});

test('far from every line: unchanged, no guides', () => {
    const start = { x: 0.33, y: 0.27, width: 0.1, height: 0.1 };
    const { box, guides } = snapBox(start, [], 'move', t);
    assert.deepEqual(box, start);
    assert.deepEqual(guides, []);
});

test('resize snaps only the moving edges, never the origin', () => {
    const other = { x: 0.6, y: 0.0, width: 0.2, height: 0.5 };
    const { box, guides } = snapBox({ x: 0.1, y: 0.1, width: 0.495, height: 0.396 }, [other], 'resize', t);
    close(box.x, 0.1);
    close(box.y, 0.1);
    close(box.x + box.width, 0.6);
    close(box.y + box.height, 0.5);
    assert.equal(guides.length, 2);
});
