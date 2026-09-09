import { Badge } from '@/components/ui/badge';
import type { AgendaCardView, AgendaResolutionView } from '@/types/game';

/**
 * An agenda card as it reads (rulebook 3.1).
 *
 * The resolutions are the point of the card, so they are numbered and listed
 * rather than run together: a CEO splits their Political Will between them, and
 * an amendment names one of them.
 *
 * An amendment the Chair has proposed is shown beside the words it would
 * change rather than in place of them. Until Council Control signs it off the
 * card still reads and votes as it stands (3.1.4), and showing the new wording
 * as though it were live would have people voting for words nobody has agreed.
 */
export function AgendaCardPanel({
    card,
    children,
}: {
    card: AgendaCardView;
    children?: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p className="font-medium">{card.title}</p>
                    {card.author && (
                        <p className="text-xs text-muted-foreground">
                            Written by {card.author}
                        </p>
                    )}
                </div>
                <Badge variant="secondary">{card.status_label}</Badge>
            </div>

            {card.body && (
                <p className="text-sm whitespace-pre-line text-muted-foreground">
                    {card.body}
                </p>
            )}

            {card.control_note && (
                <p className="rounded-md border border-dashed p-2 text-sm">
                    <span className="font-medium">
                        Control&rsquo;s remarks:{' '}
                    </span>
                    {card.control_note}
                </p>
            )}

            <ol className="flex flex-col gap-1 text-sm">
                {card.resolutions.map((resolution) => (
                    <ResolutionLine
                        key={resolution.id}
                        resolution={resolution}
                    />
                ))}
            </ol>

            {children}
        </div>
    );
}

function ResolutionLine({ resolution }: { resolution: AgendaResolutionView }) {
    return (
        <li className="flex flex-wrap items-baseline gap-2">
            <span className="font-mono text-xs text-muted-foreground tabular-nums">
                {resolution.position}.
            </span>
            <span className={resolution.removed ? 'line-through' : undefined}>
                {resolution.text}
            </span>

            {resolution.pending_amendment && (
                <span className="text-xs text-amber-600 dark:text-amber-500">
                    {resolution.pending_amendment_label} proposed
                    {resolution.pending_text
                        ? ` — “${resolution.pending_text}”`
                        : ''}
                    , waiting on Council Control
                </span>
            )}
        </li>
    );
}
