/**
 * BrandMarkParticles — the shield as a cloud of water, sampled from the logo's
 * own alpha channel. React Three Fiber port of the Vue/Three version.
 *
 * The cycle is deliberately slow: assemble, hold, drain, rest. A logo that
 * never stops moving is a distraction in a CRM; this is one quiet brand beat.
 */
import { useRef, useMemo, useEffect, useState } from 'react';
import {
    Points,
    BufferGeometry,
    BufferAttribute,
    CanvasTexture,
    Color,
    PointsMaterial,
    SRGBColorSpace,
    AdditiveBlending,
} from 'three';
import { useFrame } from '@react-three/fiber';

interface BrandMarkParticlesProps {
    onReady?: () => void;
}

const MARK_SRC = '/img/logo-mark.webp';
const ART_HEIGHT = 4;
const SAMPLE_STEP = 2;
const CYCLE_SECONDS = 12;

const smoothstep = (edge0: number, edge1: number, value: number): number => {
    const x = Math.min(1, Math.max(0, (value - edge0) / (edge1 - edge0)));
    return x * x * (3 - 2 * x);
};

const sprite = (color: string): CanvasTexture => {
    const canvas = document.createElement('canvas');
    canvas.width = 64;
    canvas.height = 64;
    const context = canvas.getContext('2d');
    if (context) {
        const gradient = context.createRadialGradient(32, 32, 0, 32, 32, 32);
        gradient.addColorStop(0, color);
        gradient.addColorStop(0.55, color);
        gradient.addColorStop(1, 'transparent');
        context.fillStyle = gradient;
        context.fillRect(0, 0, 64, 64);
    }
    const texture = new CanvasTexture(canvas);
    texture.colorSpace = SRGBColorSpace;
    return texture;
};

export default function BrandMarkParticles({ onReady }: BrandMarkParticlesProps) {
    /*
     * Escape hatch, with a reason: `build` is a Three.js resource bag whose
     * setters deliberately mutate scene state from effects and `useFrame`.
     * That is not render-derived data, and memoizing it would freeze the
     * animation. React Compiler otherwise skips (and warns about) the file.
     */
    'use no memo';

    const ref = useRef<Points | null>(null);
    const [ready, setReady] = useState(false);

    const cssToken = (name: string): string =>
        getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    const build = useMemo(() => {
        let positionAttribute: BufferAttribute | null = null;
        let targets: Float32Array | null = null;
        let scatters: Float32Array | null = null;
        let seeds: Float32Array | null = null;
        let pointCount = 0;

        const image = new Image();
        image.decoding = 'async';
        image.src = MARK_SRC;

        image.decode().catch(() => {
            return;
        });

        return {
            image,
            positionAttribute: null as BufferAttribute | null,
            targets: null as Float32Array | null,
            scatters: null as Float32Array | null,
            seeds: null as Float32Array | null,
            pointCount: 0 as number,
            setPositionAttribute: (v: BufferAttribute | null) => {
                positionAttribute = v;
            },
            setTargets: (v: Float32Array | null) => {
                targets = v;
            },
            setScatters: (v: Float32Array | null) => {
                scatters = v;
            },
            setSeeds: (v: Float32Array | null) => {
                seeds = v;
            },
            setPointCount: (v: number) => {
                pointCount = v;
            },
            getPositionAttribute: () => positionAttribute,
            getTargets: () => targets,
            getScatters: () => scatters,
            getSeeds: () => seeds,
            getPointCount: () => pointCount,
        };
    }, []);

    useEffect(() => {
        const { image } = build;

        image.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = image.naturalWidth;
            canvas.height = image.naturalHeight;

            const context = canvas.getContext('2d', { willReadFrequently: true });
            if (!context) return;

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

            if (!base || sheen.length === 0) return;

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
                    if (alpha < 140) continue;

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

            if (seedValues.length === 0) return;

            build.setPointCount(seedValues.length);
            build.setTargets(new Float32Array(targetValues));
            build.setScatters(new Float32Array(scatterValues));
            build.setSeeds(new Float32Array(seedValues));

            const geometry = new BufferGeometry();
            const posAttr = new BufferAttribute(new Float32Array(currentValues), 3);
            build.setPositionAttribute(posAttr);
            geometry.setAttribute('position', posAttr);
            geometry.setAttribute('color', new BufferAttribute(new Float32Array(colorValues), 3));

            const material = new PointsMaterial({
                size: 0.062,
                map: sprite(sheen),
                transparent: true,
                depthWrite: false,
                blending: AdditiveBlending,
                vertexColors: true,
                sizeAttenuation: true,
                opacity: 0.98,
            });

            const points = new Points(geometry, material);
            points.frustumCulled = false;
            ref.current = points;
            setReady(true);
            onReady?.();
        };

        return () => {
            if (ref.current) {
                ref.current.geometry.dispose();
                if (ref.current.material instanceof PointsMaterial) {
                    ref.current.material.map?.dispose();
                    ref.current.material.dispose();
                }
                ref.current = null;
            }
        };
    }, [build, onReady]);

    useFrame((state) => {
        const posAttr = build.getPositionAttribute();
        if (!posAttr || !build.getTargets() || !build.getScatters() || !build.getSeeds()) return;

        const time = state.clock.getElapsedTime() % CYCLE_SECONDS;
        const assemble = smoothstep(0, 2.4, time) * (1 - smoothstep(8.4, 10.8, time));
        const positions = posAttr.array as Float32Array;

        for (let index = 0; index < build.getPointCount(); index += 1) {
            const offset = index * 3;
            const targetX = build.getTargets()![offset];
            const targetY = build.getTargets()![offset + 1];
            const targetZ = build.getTargets()![offset + 2];
            const scatterX = build.getScatters()![offset];
            const scatterY = build.getScatters()![offset + 1];
            const scatterZ = build.getScatters()![offset + 2];
            const seed = build.getSeeds()![index];

            const drift = (1 - assemble) * 0.22;

            positions[offset] =
                scatterX + (targetX - scatterX) * assemble + Math.sin(time * 0.7 + seed) * drift;
            positions[offset + 1] =
                scatterY + (targetY - scatterY) * assemble + Math.cos(time * 0.6 + seed) * drift;
            positions[offset + 2] = scatterZ + (targetZ - scatterZ) * assemble;
        }

        posAttr.needsUpdate = true;

        if (ref.current) {
            const targetRotationY = state.pointer.x * 0.22 + Math.sin(time * 0.4) * 0.05;
            const targetRotationX = -state.pointer.y * 0.16 + Math.cos(time * 0.35) * 0.03;

            ref.current.rotation.y += (targetRotationY - ref.current.rotation.y) * 0.06;
            ref.current.rotation.x += (targetRotationX - ref.current.rotation.x) * 0.06;
        }
    });

    const points = ready ? (ref.current ?? undefined) : undefined;

    if (!points) {
        return null;
    }

    return <primitive ref={ref} object={points} />;
}
