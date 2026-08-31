import { ArrowDown } from 'lucide-react';

/**
 * One end of a Protection Card stack, named.
 *
 * A stack is drawn in the order Runners meet it (rulebook 3.3.4), so naming
 * both ends turns a column of cards into the corridor it represents: they
 * arrive at the top and work down towards the Facility. It is the one thing
 * "Met 1st" on a card cannot say by itself.
 *
 * The arrow does the work and the words carry the meaning, so the arrow is
 * hidden from a screen reader rather than read out as a shape.
 */
export function StackEnd({ label }: { label: string }) {
    return (
        <p className="flex items-center gap-1 px-2 text-xs text-muted-foreground">
            <ArrowDown className="size-3.5 shrink-0" aria-hidden="true" />
            {label}
        </p>
    );
}
