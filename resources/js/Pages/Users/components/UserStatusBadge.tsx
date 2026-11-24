/**
 * UserStatusBadge — the staff lifecycle state.
 *
 * Colour is never the only signal: the label is always rendered. The three
 * values mirror Modules\Users\Enums\UserStatus; the backend remains the source
 * for transitions, this file only names their presentation.
 */
import { Tag } from '@/Components/ui/tag';
import { cn } from '@/lib/utils';

export type UserAccountStatus = Modules.Users.Enums.UserStatus;

const VARIANTS = {
    active: 'success',
    invited: 'warning',
    suspended: 'destructive',
} as const;

const LABELS: Record<UserAccountStatus, string> = {
    active: 'Active',
    invited: 'Invited',
    suspended: 'Suspended',
};

interface UserStatusBadgeProps {
    status: UserAccountStatus | string;
    label?: string;
    className?: string;
}

const isAccountStatus = (value: string): value is UserAccountStatus =>
    value === 'active' || value === 'invited' || value === 'suspended';

export default function UserStatusBadge({ status, label, className }: UserStatusBadgeProps) {
    const resolved = isAccountStatus(status) ? status : null;
    const text = label ?? (resolved === null ? status : LABELS[resolved]);

    return (
        <Tag
            variant={resolved === null ? 'outline' : VARIANTS[resolved]}
            className={cn('normal-case tracking-normal', className)}
        >
            {text}
        </Tag>
    );
}
