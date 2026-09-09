import { router } from '@inertiajs/react';
import { useState } from 'react';
import { FactionBadge } from '@/components/faction-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/runs';
import type { RunPartyMember, RunTarget } from '@/types/game';

/**
 * The Alerts a group of this size raises just for being that size
 * (rulebook 3.4.1).
 *
 * Shown while the group is being assembled because it is the whole trade: more
 * Runners break the defences more easily, and every one past the first makes
 * the Facility harder before you have set foot in it. The server generates the
 * real number; this is here so the decision is made with its eyes open.
 *
 * Past six the rulebook stops printing numbers, so past six this says so
 * instead of guessing on screen.
 */
const GROUP_ALERTS = [0, 0, 1, 2, 4, 7, 11];

/**
 * Putting in for a run (rulebook 3.4.1).
 *
 * Done in Secret at the table by writing the Facility down and handing it to
 * Control, and only the Run Leader submits for a group — so this is one form
 * per group rather than each Runner opting in, and nobody else can see it has
 * been filled in.
 */
export function RunSubmitForm({
    targets,
    party,
}: {
    targets: RunTarget[];
    party: RunPartyMember[];
}) {
    const yours = party.filter((runner) => runner.is_yours);
    const [facility, setFacility] = useState('');
    const [leader, setLeader] = useState(String(yours[0]?.id ?? ''));
    const [members, setMembers] = useState<number[]>([]);
    const [submitting, setSubmitting] = useState(false);

    const leaderId = Number(leader);
    const group = [leaderId, ...members].filter(
        (id) => Number.isFinite(id) && id > 0,
    );
    const tags = party
        .filter((runner) => group.includes(runner.id))
        .reduce((sum, runner) => sum + runner.tags, 0);
    const sizeAlerts = GROUP_ALERTS[group.length] ?? null;

    if (targets.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Put in for a run</CardTitle>
                    <CardDescription>No Facility is open yet.</CardDescription>
                </CardHeader>
            </Card>
        );
    }

    // Everyone already out on a run this turn is left off the list entirely, so
    // a player whose only Runner is in a Facility has nobody to lead a second
    // group — which is a sentence rather than an empty select.
    if (yours.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Put in for a run</CardTitle>
                    <CardDescription>
                        Every Runner you hold is already out on a run this turn.
                        One run each — a Runner in two groups would be in two
                        dice pools at once.
                    </CardDescription>
                </CardHeader>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Put in for a run</CardTitle>
                <CardDescription>
                    Name the Facility and who is coming. Nobody else can see
                    this — the target is Secret until you go in.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="flex flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        setSubmitting(true);
                        router.post(
                            store(),
                            {
                                facility_id: Number(facility),
                                run_leader_character_id: leaderId,
                                member_character_ids: members,
                            },
                            {
                                onFinish: () => setSubmitting(false),
                                preserveScroll: true,
                                onSuccess: () => {
                                    setFacility('');
                                    setMembers([]);
                                },
                            },
                        );
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="run-facility">Facility</Label>
                            <select
                                id="run-facility"
                                required
                                className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                value={facility}
                                onChange={(event) =>
                                    setFacility(event.target.value)
                                }
                            >
                                <option value="">Choose one</option>
                                {targets.map((target) => (
                                    <option key={target.id} value={target.id}>
                                        {target.name} —{' '}
                                        {target.corporation.name} (
                                        {target.facility_type})
                                    </option>
                                ))}
                            </select>
                            <p className="text-xs text-muted-foreground">
                                How deep the stack is, and what is in it, is
                                Secret. That is what a run is for.
                            </p>
                        </div>

                        <div className="flex flex-col gap-1">
                            <Label htmlFor="run-leader">Run Leader</Label>
                            <select
                                id="run-leader"
                                required
                                className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                value={leader}
                                onChange={(event) =>
                                    setLeader(event.target.value)
                                }
                            >
                                {yours.map((runner) => (
                                    <option key={runner.id} value={runner.id}>
                                        {runner.name}
                                    </option>
                                ))}
                            </select>
                            <p className="text-xs text-muted-foreground">
                                The Leader rolls their full skill; everyone else
                                adds half of theirs, or a quarter if Wounded.
                            </p>
                        </div>
                    </div>

                    <fieldset className="flex flex-col gap-2">
                        <legend className="text-sm font-medium">
                            Who else is coming
                        </legend>
                        <ul className="grid max-h-72 gap-1 overflow-y-auto pr-1 sm:grid-cols-2">
                            {party
                                .filter((runner) => runner.id !== leaderId)
                                .map((runner) => (
                                    <li key={runner.id}>
                                        <label className="flex items-center gap-2 rounded-md border p-2 text-sm hover:bg-muted/50">
                                            <input
                                                type="checkbox"
                                                checked={members.includes(
                                                    runner.id,
                                                )}
                                                disabled={runner.incapacitated}
                                                onChange={(event) =>
                                                    setMembers((was) =>
                                                        event.target.checked
                                                            ? [
                                                                  ...was,
                                                                  runner.id,
                                                              ]
                                                            : was.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      runner.id,
                                                              ),
                                                    )
                                                }
                                            />
                                            {runner.gang !== null && (
                                                <FactionBadge
                                                    faction={runner.gang}
                                                    size="small"
                                                />
                                            )}
                                            <span className="flex-1">
                                                {runner.name}
                                            </span>
                                            <span className="text-muted-foreground tabular-nums">
                                                B {runner.brawn} · H{' '}
                                                {runner.hack}
                                                {runner.tags > 0 && (
                                                    <>
                                                        {' '}
                                                        ·{' '}
                                                        <span className="text-amber-600 dark:text-amber-400">
                                                            {runner.tags} T
                                                        </span>
                                                    </>
                                                )}
                                            </span>
                                        </label>
                                    </li>
                                ))}
                        </ul>
                        {party.length <= 1 && (
                            <p className="text-sm text-muted-foreground">
                                Nobody else is free. Running alone raises no
                                Alerts for the size of the group, which is the
                                whole reason to do it.
                            </p>
                        )}
                    </fieldset>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            type="submit"
                            disabled={
                                submitting || facility === '' || leader === ''
                            }
                        >
                            Submit the run
                        </Button>
                        <p className="text-sm text-muted-foreground">
                            {group.length} Runner
                            {group.length === 1 ? '' : 's'} ·{' '}
                            <Badge variant="outline">
                                {sizeAlerts === null
                                    ? 'Alerts for the group: Control decides'
                                    : `${sizeAlerts + tags} Alert${sizeAlerts + tags === 1 ? '' : 's'} on the way in`}
                            </Badge>
                            {tags > 0 && sizeAlerts !== null && (
                                <>
                                    {' '}
                                    ({tags} of them for Tags you are already
                                    carrying)
                                </>
                            )}
                        </p>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
