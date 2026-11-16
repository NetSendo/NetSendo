<script setup>
import { onBeforeUnmount, onMounted, ref } from "vue";

/**
 * The picture behind the sign-in screens: what NetSendo does, drawn. One bright point is the
 * installation ("your own server"); messages leave it in arcs and land on recipients, who light
 * up for a moment. Decorative only: hidden from assistive technology, still for people who ask
 * for reduced motion, and paused while the tab is in the background.
 */
const canvas = ref(null);

// The lime of the logo
const LIME = [164, 228, 28];
const PALE = [226, 232, 240];
const rgba = ([r, g, b], alpha) => `rgba(${r}, ${g}, ${b}, ${alpha})`;

// Same picture on every visit: the layout comes from a seeded generator, not Math.random()
const seeded = (seed) => () => {
    seed |= 0;
    seed = (seed + 0x6d2b79f5) | 0;
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
};

const easeInOut = (t) => (t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2);

let context = null;
let width = 0;
let height = 0;
let hub = { x: 0, y: 0 };
let nodes = [];
let flights = [];
let frame = null;
let last = 0;
let untilNextFlight = 0;
let observer = null;
let random = seeded(7);
let reducedMotion = false;

const layout = () => {
    const element = canvas.value;
    if (!element) return;

    const rect = element.getBoundingClientRect();
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    width = rect.width;
    height = rect.height;
    element.width = Math.round(width * ratio);
    element.height = Math.round(height * ratio);
    context = element.getContext("2d");
    context.setTransform(ratio, 0, 0, ratio, 0, 0);

    random = seeded(7);
    hub = { x: width * 0.8, y: height * 0.8 };

    // Fewer recipients behind the headline (left, middle) so the text stays easy to read
    const count = Math.max(28, Math.min(90, Math.round((width * height) / 9000)));
    nodes = [];
    let guard = 0;
    while (nodes.length < count && guard++ < count * 20) {
        const x = (0.04 + random() * 0.92) * width;
        const y = (0.06 + random() * 0.88) * height;
        const behindText = x < width * 0.62 && y > height * 0.34 && y < height * 0.7;
        const nearHub = Math.hypot(x - hub.x, y - hub.y) < 70;
        if (nearHub || (behindText && random() < 0.85)) continue;
        nodes.push({ x, y, size: 0.8 + random() * 1.1, lit: 0, ring: -1 });
    }

    flights = [];
    untilNextFlight = 0;
};

const launch = () => {
    const target = nodes[Math.floor(random() * nodes.length)];
    if (!target) return;

    const dx = target.x - hub.x;
    const dy = target.y - hub.y;
    const distance = Math.hypot(dx, dy);
    // The arc bows to one side of the straight line, more for longer trips
    const bow = distance * (0.18 + random() * 0.22) * (random() < 0.5 ? -1 : 1);

    flights.push({
        target,
        control: {
            x: hub.x + dx / 2 + (-dy / distance) * bow,
            y: hub.y + dy / 2 + (dx / distance) * bow,
        },
        duration: 1300 + distance * 1.6 + random() * 500,
        elapsed: 0,
        fade: 1,
    });
};

const pointOn = (flight, t) => {
    const u = 1 - t;
    return {
        x: u * u * hub.x + 2 * u * t * flight.control.x + t * t * flight.target.x,
        y: u * u * hub.y + 2 * u * t * flight.control.y + t * t * flight.target.y,
    };
};

const drawArc = (flight, from, to, alpha, lineWidth) => {
    const steps = 22;
    context.beginPath();
    for (let i = 0; i <= steps; i++) {
        const point = pointOn(flight, from + ((to - from) * i) / steps);
        if (i === 0) context.moveTo(point.x, point.y);
        else context.lineTo(point.x, point.y);
    }
    context.strokeStyle = rgba(LIME, alpha);
    context.lineWidth = lineWidth;
    context.stroke();
};

const draw = (time) => {
    context.clearRect(0, 0, width, height);

    // A soft light around the hub
    const glow = context.createRadialGradient(hub.x, hub.y, 0, hub.x, hub.y, Math.max(width, height) * 0.55);
    glow.addColorStop(0, rgba(LIME, 0.09));
    glow.addColorStop(1, rgba(LIME, 0));
    context.fillStyle = glow;
    context.fillRect(0, 0, width, height);

    for (const flight of flights) {
        const progress = easeInOut(Math.min(1, flight.elapsed / flight.duration));

        // The path already travelled stays as a faint trace, then fades
        drawArc(flight, 0, progress, 0.1 * flight.fade, 1);

        if (flight.elapsed < flight.duration) {
            // The message itself: a short bright tail and a glowing head
            const tail = Math.max(0, progress - 0.16);
            drawArc(flight, tail, progress, 0.75, 1.6);
            const head = pointOn(flight, progress);
            context.shadowColor = rgba(LIME, 0.9);
            context.shadowBlur = 12;
            context.fillStyle = "#f4ffd6";
            context.beginPath();
            context.arc(head.x, head.y, 2, 0, Math.PI * 2);
            context.fill();
            context.shadowBlur = 0;
        }
    }

    for (const node of nodes) {
        if (node.ring >= 0) {
            context.strokeStyle = rgba(LIME, 0.5 * (1 - node.ring));
            context.lineWidth = 1;
            context.beginPath();
            context.arc(node.x, node.y, 3 + node.ring * 16, 0, Math.PI * 2);
            context.stroke();
        }

        context.fillStyle = node.lit > 0.02 ? rgba(LIME, 0.3 + node.lit * 0.7) : rgba(PALE, 0.28);
        context.beginPath();
        context.arc(node.x, node.y, node.size + node.lit * 1.4, 0, Math.PI * 2);
        context.fill();
    }

    // The hub breathes: two rings leave it in turn
    for (const offset of [0, 0.5]) {
        const phase = (time / 3200 + offset) % 1;
        context.strokeStyle = rgba(LIME, 0.35 * (1 - phase));
        context.lineWidth = 1;
        context.beginPath();
        context.arc(hub.x, hub.y, 6 + phase * 34, 0, Math.PI * 2);
        context.stroke();
    }
    context.shadowColor = rgba(LIME, 0.9);
    context.shadowBlur = 18;
    context.fillStyle = rgba(LIME, 1);
    context.beginPath();
    context.arc(hub.x, hub.y, 4.5, 0, Math.PI * 2);
    context.fill();
    context.shadowBlur = 0;
};

const step = (time) => {
    // A tab that was in the background comes back with one huge gap: treat it as a normal frame
    const delta = Math.min(64, time - (last || time));
    last = time;

    untilNextFlight -= delta;
    if (untilNextFlight <= 0 && flights.length < 9) {
        launch();
        untilNextFlight = 320 + random() * 520;
    }

    for (const flight of flights) {
        const before = flight.elapsed;
        flight.elapsed += delta;
        if (before < flight.duration && flight.elapsed >= flight.duration) {
            flight.target.lit = 1;
            flight.target.ring = 0;
        }
        if (flight.elapsed > flight.duration) flight.fade = Math.max(0, flight.fade - delta / 2200);
    }
    flights = flights.filter((flight) => flight.fade > 0);

    for (const node of nodes) {
        if (node.lit > 0) node.lit = Math.max(0, node.lit - delta / 4200);
        if (node.ring >= 0) {
            node.ring += delta / 900;
            if (node.ring >= 1) node.ring = -1;
        }
    }

    draw(time);
    frame = requestAnimationFrame(step);
};

// For reduced motion: the same scene, caught at one moment
const drawStill = () => {
    for (let i = 0; i < 9; i++) launch();
    flights.forEach((flight, index) => {
        flight.elapsed = flight.duration * (index < 6 ? 1.2 : 0.35 + index * 0.06);
        if (index < 6) flight.target.lit = 0.4 + index * 0.1;
    });
    draw(800);
};

const start = () => {
    cancelAnimationFrame(frame);
    last = 0;
    if (reducedMotion) drawStill();
    else frame = requestAnimationFrame(step);
};

const onVisibility = () => {
    if (document.hidden) cancelAnimationFrame(frame);
    else if (!reducedMotion) start();
};

onMounted(() => {
    reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    layout();
    start();

    observer = new ResizeObserver(() => {
        layout();
        start();
    });
    observer.observe(canvas.value);
    document.addEventListener("visibilitychange", onVisibility);
});

onBeforeUnmount(() => {
    cancelAnimationFrame(frame);
    observer?.disconnect();
    document.removeEventListener("visibilitychange", onVisibility);
});
</script>

<template>
    <canvas ref="canvas" aria-hidden="true" class="pointer-events-none absolute inset-0 h-full w-full"></canvas>
</template>
