/**
 * BrandMark — the theme-aware shield on its own.
 *
 * `BrandLockup` uses it next to the name; the collapsed rail uses it inside the
 * expand button, where the mark fades out on hover/focus and the open-panel
 * arrow takes its place. Keeping the two image sources here means the rail and
 * the header cannot disagree about which mark belongs on which ground.
 */
import { cn } from '@/lib/utils';

interface BrandMarkProps {
    /** 28px cut for the 72px rail; 32px everywhere else. */
    compact?: boolean;
    className?: string;
}

export default function BrandMark({ compact = false, className }: BrandMarkProps) {
    const size = compact ? 'size-7' : 'size-8';

    return (
        <span className={cn('relative grid shrink-0 place-items-center', size, className)}>
            <img
                src="/img/logo-mark-color.png"
                alt=""
                width={315}
                height={372}
                className={cn('object-contain dark:hidden', size)}
            />
            <img
                src="/img/logo-mark.webp"
                alt=""
                width={358}
                height={402}
                className={cn('hidden object-contain dark:block', size)}
            />
        </span>
    );
}
