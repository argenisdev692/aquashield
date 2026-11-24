/**
 * CursorOrb — decorative pointer-following glow (reduced-motion safe).
 * React port of the Vue CursorOrb: teleported to <body>, styled through the
 * `.cursor-orb` / `.cursor-orb--active` tokens in app.css, writes coalesced to
 * one animation frame, and never attached on coarse pointers or under
 * prefers-reduced-motion.
 */
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

export default function CursorOrb() {
    const orbRef = useRef<HTMLDivElement | null>(null);
    const [isActive, setIsActive] = useState(false);
    const [isMounted, setIsMounted] = useState(false);

    const frame = useRef(0);
    const pointerX = useRef(0);
    const pointerY = useRef(0);

    useEffect(() => {
        const isCoarse = window.matchMedia('(pointer: coarse)').matches;
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (isCoarse || prefersReducedMotion) {
            return;
        }

        const paint = (): void => {
            frame.current = 0;

            const element = orbRef.current;

            if (element === null) {
                return;
            }

            element.style.setProperty('--cursor-x', `${pointerX.current}px`);
            element.style.setProperty('--cursor-y', `${pointerY.current}px`);

            setIsActive(true);
        };

        const handlePointerMove = (event: PointerEvent): void => {
            pointerX.current = event.clientX;
            pointerY.current = event.clientY;

            if (frame.current === 0) {
                frame.current = window.requestAnimationFrame(paint);
            }
        };

        setIsMounted(true);
        window.addEventListener('pointermove', handlePointerMove, { passive: true });

        return () => {
            window.removeEventListener('pointermove', handlePointerMove);

            if (frame.current !== 0) {
                window.cancelAnimationFrame(frame.current);
                frame.current = 0;
            }
        };
    }, []);

    if (!isMounted) {
        return null;
    }

    if (
        typeof window !== 'undefined' &&
        (window.matchMedia('(pointer: coarse)').matches ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches)
    ) {
        return null;
    }

    return createPortal(
        <div
            ref={orbRef}
            className={`cursor-orb${isActive ? ' cursor-orb--active' : ''}`}
            aria-hidden="true"
        />,
        document.body,
    );
}
