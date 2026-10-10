// MI-02: a metric counts up once when it first renders, then stops. It is a
// one-shot attention cue, not a loop, and it is skipped under reduced motion.

const DURATION_MS = 600;

export default function countUp(to) {
    return {
        display: '0',

        init() {
            const target = Number(to) || 0;
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduce || target === 0) {
                this.display = target.toLocaleString('en-US');
                return;
            }

            const start = performance.now();
            const tick = (now) => {
                const progress = Math.min(1, (now - start) / DURATION_MS);
                const eased = 1 - Math.pow(1 - progress, 3);
                this.display = Math.round(target * eased).toLocaleString('en-US');
                if (progress < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        },
    };
}
