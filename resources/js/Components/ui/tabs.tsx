import { Tabs as TabsPrimitive } from '@base-ui/react/tabs';
import { cn } from '@/lib/utils';

function Tabs({ className, ...props }: TabsPrimitive.Root.Props) {
    return (
        <TabsPrimitive.Root
            data-slot="tabs"
            className={cn('flex flex-col', className)}
            {...props}
        />
    );
}

function TabsList({ className, ...props }: TabsPrimitive.List.Props) {
    return (
        <TabsPrimitive.List
            data-slot="tabs-list"
            className={cn(
                // Underline tab strip on a hairline bottom border. The strip
                // stays transparent: it sits inside a card or a bordered panel
                // that already owns the surface, so an opaque fill here paints
                // a visible rectangle the moment a tab is switched.
                'relative flex w-full items-center overflow-x-auto overscroll-x-contain border-b border-surface-200 bg-transparent [scrollbar-width:none] dark:border-surface-700 [&::-webkit-scrollbar]:hidden',
                className,
            )}
            {...props}
        />
    );
}

function TabsTab({ className, ...props }: TabsPrimitive.Tab.Props) {
    return (
        <TabsPrimitive.Tab
            data-slot="tabs-tab"
            className={cn(
                // Underlined tabs, semibold labels, active tab in the brand
                // aqua. Base UI marks the selected tab with `aria-selected`
                // (and mirrors the state on `data-active`); styling through
                // both attributes keeps the active state working even if the
                // primitive changes which one it emits.
                //
                // The active colour is repeated under `dark:` on purpose: the
                // dark text utility and a bare `aria-selected:` utility have
                // the same specificity, so later-in-the-stylesheet `dark:` won
                // and the selected label rendered grey (only the underline
                // showed). The stacked `dark:aria-selected:` variant is more
                // specific than `dark:text-*`, so order no longer matters.
                // The underline is 2px and overlaps the strip's 1px hairline:
                // a 1px aqua line on the night navy is invisible at 1x.
                '-mb-px inline-flex shrink-0 cursor-pointer select-none items-center gap-2 whitespace-nowrap border-b-2 border-transparent px-[1.125rem] py-4 text-sm font-semibold text-surface-500 transition-colors duration-200 hover:text-surface-700 focus-visible:z-10 focus-visible:outline focus-visible:outline-1 focus-visible:outline-offset-[-1px] focus-visible:outline-aq-focus disabled:pointer-events-none disabled:opacity-60 aria-selected:border-aq-accent-text aria-selected:text-aq-accent-text data-active:border-aq-accent-text data-active:text-aq-accent-text dark:text-surface-300 dark:hover:text-surface-200 dark:aria-selected:text-aq-accent-text dark:data-active:text-aq-accent-text motion-reduce:transition-none',
                className,
            )}
            {...props}
        />
    );
}

interface TabsPanelProps extends TabsPrimitive.Panel.Props {
    /**
     * Container padding. On by default for panels that own their content
     * (dashboard boards). Turn it off when the panel sits inside a shell that
     * already pads — a second inset made the fields narrower than the heading
     * and the action above them.
     */
    padded?: boolean;
}

function TabsPanel({ className, keepMounted, padded = true, ...props }: TabsPanelProps) {
    return (
        <TabsPrimitive.Panel
            data-slot="tabs-panel"
            keepMounted={keepMounted}
            className={cn(
                // The panel owns the container padding, the form inside owns
                // the field gap. No background: the bordered pipeline shell
                // behind it owns the surface.
                'flex flex-col bg-transparent outline-none',
                padded ? 'px-[1.125rem] pt-[0.875rem] pb-[1.125rem]' : 'p-0',
                className,
            )}
            {...props}
        />
    );
}

export { Tabs, TabsList, TabsTab, TabsPanel };
