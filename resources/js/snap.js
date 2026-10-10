// Snap-to-guides for the template editor. Pure functions on normalized
// (0-1) boxes so they are unit-testable without a browser.
//
// Snap targets: the image edges and centre, plus the left/centre/right and
// top/middle/bottom lines of every other box. A moving box snaps its own
// left/centre/right (top/middle/bottom); a resizing box snaps only the edges
// that move (right, bottom).

/**
 * @param {number[]} edges   the moving box's candidate lines on this axis
 * @param {number[]} targets snap lines on this axis
 * @param {number} threshold max distance, normalized
 * @returns {{delta: number, line: number} | null}
 */
export function nearest(edges, targets, threshold) {
    let best = null;
    for (const edge of edges) {
        for (const target of targets) {
            const delta = target - edge;
            if (Math.abs(delta) <= threshold && (best === null || Math.abs(delta) < Math.abs(best.delta))) {
                best = { delta, line: target };
            }
        }
    }
    return best;
}

/**
 * @param {{x: number, y: number, width: number, height: number}} box
 * @param {Array<{x: number, y: number, width: number, height: number}>} others
 * @param {'move'|'resize'} mode
 * @param {{x: number, y: number}} threshold normalized per axis
 * @returns {{box: object, guides: Array<{axis: 'x'|'y', pos: number}>}}
 */
export function snapBox(box, others, mode, threshold) {
    const xs = [0, 0.5, 1];
    const ys = [0, 0.5, 1];
    for (const o of others) {
        xs.push(o.x, o.x + o.width / 2, o.x + o.width);
        ys.push(o.y, o.y + o.height / 2, o.y + o.height);
    }

    const next = { ...box };
    const guides = [];

    if (mode === 'move') {
        const sx = nearest([box.x, box.x + box.width / 2, box.x + box.width], xs, threshold.x);
        if (sx) { next.x = box.x + sx.delta; guides.push({ axis: 'x', pos: sx.line }); }
        const sy = nearest([box.y, box.y + box.height / 2, box.y + box.height], ys, threshold.y);
        if (sy) { next.y = box.y + sy.delta; guides.push({ axis: 'y', pos: sy.line }); }
    } else {
        const sx = nearest([box.x + box.width], xs, threshold.x);
        if (sx && box.width + sx.delta > 0) { next.width = box.width + sx.delta; guides.push({ axis: 'x', pos: sx.line }); }
        const sy = nearest([box.y + box.height], ys, threshold.y);
        if (sy && box.height + sy.delta > 0) { next.height = box.height + sy.delta; guides.push({ axis: 'y', pos: sy.line }); }
    }

    return { box: next, guides };
}
