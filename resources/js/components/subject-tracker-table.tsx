import { FactionBadge } from '@/components/faction-badge';
import { TrackerValue } from '@/components/tracker-value';
import type { NamedSubject } from '@/types/game';

/**
 * A table of factions and the Trackers they carry, each number editable.
 *
 * Every change goes through TrackerService behind the dialog, so the ledger
 * records what moved, by how much, who moved it and why.
 */
export function SubjectTrackerTable({
    gameId,
    subjects,
    columns,
    emptyMessage,
}: {
    gameId: number;
    subjects: NamedSubject[];
    /** Tracker key and the heading it is drawn under. */
    columns: Array<[string, string]>;
    emptyMessage: string;
}) {
    if (subjects.length === 0) {
        return <p className="text-sm text-muted-foreground">{emptyMessage}</p>;
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b text-left text-muted-foreground">
                        <th className="py-2 pr-4 font-medium">Name</th>
                        {columns.map(([key, label]) => (
                            <th
                                key={key}
                                className="py-2 pr-4 text-right font-medium"
                            >
                                {label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {subjects.map((subject) => (
                        <tr
                            key={subject.subject_id}
                            className="border-b last:border-0"
                        >
                            <td className="py-2 pr-4">
                                <span className="flex items-center gap-2">
                                    <FactionBadge
                                        faction={subject}
                                        size="small"
                                    />
                                    {subject.name}
                                </span>
                            </td>
                            {columns.map(([key, label]) => (
                                <td key={key} className="py-2 pr-4">
                                    <TrackerValue
                                        gameId={gameId}
                                        subjectType={subject.subject_type}
                                        subjectId={subject.subject_id}
                                        subjectName={subject.name}
                                        tracker={key}
                                        trackerLabel={label}
                                        value={subject.values[key] ?? 0}
                                    />
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
