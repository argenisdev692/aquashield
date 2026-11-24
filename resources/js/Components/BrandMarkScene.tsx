/**
 * BrandMarkScene — the canvas shell for the shield's water moment.
 *
 * Kept separate from the particles so the camera and renderer settings are
 * readable in one place. Mirrors the Vue `TresCanvas` defaults the production
 * build shipped: transparent clear (`alpha` + `clearAlpha=0`) so the navy
 * section shows through behind the cloud, `antialias: false` (ambience, not a
 * game), DPR capped at 1.5 and `powerPreference: low-power`.
 */
import { Suspense, useState } from 'react';
import { Canvas } from '@react-three/fiber';
import { PerspectiveCamera } from '@react-three/drei';
import BrandMarkParticles from './BrandMarkParticles';

interface BrandMarkSceneProps {
    onReady?: () => void;
}

export default function BrandMarkScene({ onReady }: BrandMarkSceneProps) {
    const [ready, setReady] = useState(false);

    return (
        <Canvas gl={{ antialias: false, alpha: true, powerPreference: 'low-power' }} dpr={[1, 1.5]}>
            <PerspectiveCamera makeDefault position={[0, 0, 7.24]} fov={45} lookAt={[0, 0, 0]} />
            <Suspense fallback={null}>
                <BrandMarkParticles
                    onReady={() => {
                        setReady(true);
                        onReady?.();
                    }}
                />
            </Suspense>
        </Canvas>
    );
}
