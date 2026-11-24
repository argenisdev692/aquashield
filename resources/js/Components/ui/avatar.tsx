/**
 * Local edit to the upstream shadcn primitive.
 *
 * Upstream paints a "blend border" on the root: an `::after` at `inset-0`
 * with a 1px border and a `mix-blend` mode. The pseudo-element is square, so
 * the root's `rounded-full` + `overflow-hidden` clips its four corners away
 * and what survives is **four short straight strokes** — one per side — not a
 * ring. `mix-blend-lighten` against the app's navy surfaces then paints them
 * at full strength, so the artefact is loud rather than subtle.
 *
 * The ring also fights the callers: the sidebar avatar is meant to be
 * borderless, and the profile avatar carries its own frame. The blend border
 * is removed rather than rounded, because a blend-mode ring is decoration
 * this app does not use — the surfaces do that work with elevation.
 */
import * as React from 'react';
import { Avatar as AvatarPrimitive } from '@base-ui/react/avatar';
import { cn } from 'cn';

function Avatar({
    className,
    size = 'default',
    ...props
}: AvatarPrimitive.Root.Props & {
    size?: 'default' | 'sm' | 'lg';
}) {
    return (
        <AvatarPrimitive.Root
            data-slot="avatar"
            data-size={size}
            className={cn(
                'group/avatar relative flex shrink-0 select-none overflow-hidden rounded-full bg-surface-200 dark:bg-surface-700 has-[img]:bg-transparent',
                size === 'sm' ? 'size-6' : size === 'lg' ? 'size-11' : 'size-8',
                className,
            )}
            {...props}
        />
    );
}

function AvatarImage({ className, ...props }: AvatarPrimitive.Image.Props) {
    return (
        <AvatarPrimitive.Image
            data-slot="avatar-image"
            className={cn('aspect-square size-full', className)}
            {...props}
        />
    );
}

function AvatarFallback({ className, ...props }: AvatarPrimitive.Fallback.Props) {
    return (
        <AvatarPrimitive.Fallback
            data-slot="avatar-fallback"
            className={cn(
                'flex size-full items-center justify-center rounded-full bg-surface-100 text-sm text-surface-700 dark:bg-surface-800 dark:text-surface-0',
                className,
            )}
            {...props}
        />
    );
}

export { Avatar, AvatarFallback, AvatarImage };
