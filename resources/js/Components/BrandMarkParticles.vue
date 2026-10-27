<script setup lang="ts">
/**
 * The shield as a cloud of water, built from the artwork's own alpha.
 *
 * Sampling the mark keeps the 3D honest: the silhouette is the real logo,
 * not an approximation traced by hand. The raster is small (102x146), so a
 * 2px stride leaves roughly 4k points — enough to read as a mark, small
 * enough that a CPU-side lerp costs less than uploading a second buffer.
 *
 * The cycle is deliberately slow and mostly at rest: assemble, hold, drain,
 * rest. A logo that never stops moving is a distraction in a CRM; the
 * band's job is one quiet brand beat between product proof and paths.
 */
import { onMounted, onUnmounted, shallowRef } from 'vue';
import { useLoop } from '@tresjs/core';
import {
    AdditiveBlending,
    BufferAttribute,
    BufferGeometry,
    CanvasTexture,
    Color,
    Points,
    PointsMaterial,
    SRGBColorSpace,
} from 'three';

const emit = defineEmits<{ ready: [] }>();

const { onBeforeRender } = useLoop();

const cloud = shallowRef<Points | null>(null);

const MARK_SRC = '/img/logo-mark.webp';
const ART_HEIGHT = 4;
const SAMPLE_STEP = 2;
const CYCLE_SECONDS = 12;

let targets: Float32Array | null = null;
let scatters: Float32Array | null = null;
let seeds: Float32Array | null = null;
let positionAttribute: BufferAttribute | null = null;
let pointCount = 0;

/* Tokens, not literals: the architecture suite bans a raw hex in a .vue,
 * and reading the theme means the cloud follows a palette change with the
 * rest of the page. */
const cssToken = (name: string): string =>
    getComputedStyle(document.documentElement).getPropertyValue(name).trim();

const smoothstep = (edge0: number, edge1: number, value: number): number => {
    const x = Math.min(1, Math.max(0, (value - edge0) / (edge1 - edge0)));

    return x * x * (3 - 2 * x);
};

/* A soft dot rather than a square: PointsMaterial's default would show the
 * quad's corners. The texture is generated in memory, so it never becomes a
 * network request and cannot fall foul of the image CSP. */
const sprite = (color: string): CanvasTexture => {
    const canvas = document.createElement('canvas');
    canvas.width = 64;
    canvas.height = 64;

    const context = canvas.getContext('2d');

    if (context) {
        const gradient = context.createRadialGradient(32, 32, 0, 32, 32, 32);
        gradient.addColorStop(0, color);
        gradient.addColorStop(0.45, color);
        gradient.addColorStop(1, 'transparent');
        context.fillStyle = gradient;
        context.fillRect(0, 0, 64, 64);
    }

    const texture = new CanvasTexture(canvas);
    texture.colorSpace = SRGBColorSpace;

    return texture;
};

const build = async (): Promise<void> => {
    const image = new Image();
    image.decoding = 'async';
    image.src = MARK_SRC;

    try {
        await image.decode();
    } catch {
        /* The poster stays. A failed decode is a reason to show less, not
         * to throw inside a decorative band. */
        return;
    }

    const canvas = document.createElement('canvas');
    canvas.width = image.naturalWidth;
    canvas.height = image.naturalHeight;

    const context = canvas.getContext('2d', { willReadFrequently: true });

    if (!context) {
        return;
    }

    context.drawImage(image, 0, 0);

    const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;

    const palette = [
        '--color-aq-accent',
        '--color-aq-accent-soft',
        '--color-aq-success',
        '--color-aq-ink',
    ]
        .map(cssToken)
        .filter((value) => value.length > 0)
        .map((value) => new Color(value));

    const base = palette[0];
    const sheen = cssToken('--color-aq-sheen');

    if (!base || sheen.length === 0) {
        return;
    }

    const scale = ART_HEIGHT / canvas.height;
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;

    const targetValues: number[] = [];
    const currentValues: number[] = [];
    const scatterValues: number[] = [];
    const colorValues: number[] = [];
    const seedValues: number[] = [];

    for (let y = 0; y < canvas.height; y += SAMPLE_STEP) {
        for (let x = 0; x < canvas.width; x += SAMPLE_STEP) {
            const alpha = pixels[(y * canvas.width + x) * 4 + 3];

            if (alpha < 140) {
                continue;
            }

            const targetX = (x - centerX) * scale;
            const targetY = (centerY - y) * scale;

            const angle = Math.random() * Math.PI * 2;
            const radius = 1.2 + Math.random() * 1.6;
            const scatterX = Math.cos(angle) * radius;
            const scatterY = Math.sin(angle) * radius * 0.95;
            const scatterZ = (Math.random() - 0.5) * 1.4;

            targetValues.push(targetX, targetY, (Math.random() - 0.5) * 0.06);
            currentValues.push(scatterX, scatterY, scatterZ);
            scatterValues.push(scatterX, scatterY, scatterZ);

            const color = palette[Math.floor(Math.random() * palette.length)] ?? base;
            colorValues.push(color.r, color.g, color.b);
            seedValues.push(Math.random() * Math.PI * 2);
        }
    }

    if (seedValues.length === 0) {
        return;
    }

    pointCount = seedValues.length;
    targets = new Float32Array(targetValues);
    scatters = new Float32Array(scatterValues);
    seeds = new Float32Array(seedValues);

    const geometry = new BufferGeometry();
    const position = new BufferAttribute(new Float32Array(currentValues), 3);

    geometry.setAttribute('position', position);
    geometry.setAttribute('color', new BufferAttribute(new Float32Array(colorValues), 3));

    const material = new PointsMaterial({
        size: 0.055,
        map: sprite(sheen),
        transparent: true,
        depthWrite: false,
        blending: AdditiveBlending,
        vertexColors: true,
        sizeAttenuation: true,
        opacity: 0.95,
    });

    const points = new Points(geometry, material);
    points.frustumCulled = false;

    positionAttribute = position;
    cloud.value = points;

    emit('ready');
};

onBeforeRender(({ elapsed }) => {
    if (!positionAttribute || !targets || !scatters || !seeds) {
        return;
    }

    const time = elapsed % CYCLE_SECONDS;
    const assemble = smoothstep(0, 2.4, time) * (1 - smoothstep(8.4, 10.8, time));
    const positions = positionAttribute.array as Float32Array;

    for (let index = 0; index < pointCount; index += 1) {
        const offset = index * 3;
        const targetX = targets[offset];
        const targetY = targets[offset + 1];
        const targetZ = targets[offset + 2];
        const scatterX = scatters[offset];
        const scatterY = scatters[offset + 1];
        const scatterZ = scatters[offset + 2];
        const seed = seeds[index];

        const drift = (1 - assemble) * 0.22;

        positions[offset] =
            scatterX + (targetX - scatterX) * assemble + Math.sin(elapsed * 0.7 + seed) * drift;
        positions[offset + 1] =
            scatterY + (targetY - scatterY) * assemble + Math.cos(elapsed * 0.6 + seed) * drift;
        positions[offset + 2] = scatterZ + (targetZ - scatterZ) * assemble;
    }

    positionAttribute.needsUpdate = true;
});

onMounted(() => {
    void build();
});

onUnmounted(() => {
    positionAttribute = null;
    targets = null;
    scatters = null;
    seeds = null;

    const points = cloud.value;

    if (!points) {
        return;
    }

    /* `<primitive>` does not dispose its object by default, so the geometry,
     * material and generated sprite are released here — a canvas that comes
     * and goes with the viewport must not leak a GPU buffer each time. */
    points.geometry.dispose();

    if (points.material instanceof PointsMaterial) {
        points.material.map?.dispose();
        points.material.dispose();
    }

    cloud.value = null;
});
</script>

<template>
    <primitive v-if="cloud" :object="cloud" />
</template>
