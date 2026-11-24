/**
 * TableButton — small action button used inside DataTable rows.
 */
import { Button } from '@/Components/ui/button';

interface TableButtonProps {
    onClick: () => void;
    children: React.ReactNode;
    variant?: 'default' | 'outline' | 'ghost' | 'destructive';
    size?: 'sm' | 'default';
    disabled?: boolean;
}

export default function TableButton({
    onClick,
    children,
    variant = 'ghost',
    size = 'sm',
    disabled = false,
}: TableButtonProps) {
    return (
        <Button
            type="button"
            variant={variant}
            size={size}
            onClick={onClick}
            disabled={disabled}
            className="h-8 w-8 p-0"
        >
            {children}
        </Button>
    );
}
