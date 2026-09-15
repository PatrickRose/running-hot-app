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
import type { RunGroupAlerts, RunPartyMember, RunTarget } from '@/types/game';

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
    groupAlerts,
    isControl,
}: {
    targets: RunTarget[];
    party: RunPartyMember[];
    groupAlerts: Record<number, RunGroupAlerts | undefined>;
    isControl: boolean;
}) {
    const yours = party.filter((runner) => runner.is_yours);

    // Control holds no characters of its own, so `yours` is empty for Control
    // and leading from it would offer an empty select - which read as "every
    // Runner you hold is already out" and left Control unable to put a group
    // in at all. Control may lead with anybody, which is the same override
    // RunPolicy::before() gives it over every other act of a run.
    const leaders = isControl ? party : yours;

    // Two dozen Facilities in one flat list is a wall of names, and the thing
    // the Runners actually decide first is which Corporation they are hitting.
    // An optgroup per Corporation says that without costing a second control:
    // the server already orders targets by Corporation and then by name, so
    // walking the list in order is all the grouping it takes.
    const byCorporation = targets.reduce<
        { corporation: string; facilities: RunTarget[] }[]
    >((groups, target) => {
        const last = groups[groups.length - 1];

        if (
            last !== undefined &&
            last.corporation === target.corporation.name
        ) {
            last.facilities.push(target);
        } else {
            groups.push({
                corporation: target.corporation.name,
                facilities: [target],
            });
        }

        return groups;
    }, []);

    const [facility, setFacility] = useState('');

    // A closed select shows the option's own text and not its group's label, so
    // the Corporation would vanish the moment one was picked. Naming it under
    // the select says it once, with the badge the rest of the application uses.
    const chosenTarget = targets.find(
        (target) => String(target.id) === facility,
    );
    const [leader, setLeader] = useState(String(leaders[0]?.id ?? ''));
    const [members, setMembers] = useState<number[]>([]);
    const [submitting, setSubmitting] = useState(false);

    // A poll can take the chosen Leader off the list - they have just gone in
    // with somebody else's group - so fall back rather than posting an id the
    // server is about to refuse.
    const chosen = leaders.some((runner) => String(runner.id) === leader)
        ? leader
        : String(leaders[0]?.id ?? '');
    const leaderId = Number(chosen);
    const group = [leaderId, ...members].filter(
        (id) => Number.isFinite(id) && id > 0,
    );
    const tags = party
        .filter((runner) => group.includes(runner.id))
        .reduce((sum, runner) => sum + runner.tags, 0);

    // The Alerts this group raises just for being this size (rulebook 3.4.1),
    // quoted by the server. Shown while the group is assembled because it is
    // the whole trade: more Runners break the defences more easily, and every
    // one past the first makes the Facility harder before you have set foot in
    // it. Past six the rulebook stops printing numbers and the application
    // carries the curve on, which is Control's to overrule - so the number is
    // still given and it is marked as a proposal rather than withheld.
    const sizeAlerts = groupAlerts[group.length]?.alerts ?? null;
    const extrapolated = groupAlerts[group.length]?.extrapolated ?? false;

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
    if (leaders.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Put in for a run</CardTitle>
                    <CardDescription>
                        {isControl
                            ? 'Every Runner and Freelancer in the game is already out on a run this turn.'
                            : 'Every Runner you hold is already out on a run this turn. One run each — a Runner in two groups would be in two dice pools at once.'}
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
                                {byCorporation.map((group) => (
                                    <optgroup
                                        key={group.corporation}
                                        label={group.corporation}
                                    >
                                        {group.facilities.map((target) => (
                                            <option
                                                key={target.id}
                                                value={target.id}
                                            >
                                                {target.name} —{' '}
                                                {target.facility_type}
                                            </option>
                                        ))}
                                    </optgroup>
                                ))}
                            </select>
                            {chosenTarget !== undefined && (
                                <p className="flex items-center gap-1.5 text-xs">
                                    <FactionBadge
                                        faction={chosenTarget.corporation}
                                        size="small"
                                    />
                                    <span className="text-muted-foreground">
                                        {chosenTarget.corporation.name}
                                    </span>
                                </p>
                            )}
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
                                value={chosen}
                                onChange={(event) =>
                                    setLeader(event.target.value)
                                }
                            >
                                {leaders.map((runner) => (
                                    <option key={runner.id} value={runner.id}>
                                        {runner.name}
                                        {isControl && runner.gang !== null
                                            ? ` — ${runner.gang.name}`
                                            : ''}
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
                                submitting || facility === '' || chosen === ''
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
                            {extrapolated && (
                                <>
                                    {' '}
                                    — past six the rulebook stops printing
                                    numbers, so {sizeAlerts} for the group is
                                    ours and Control may say otherwise.
                                </>
                            )}
                        </p>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
