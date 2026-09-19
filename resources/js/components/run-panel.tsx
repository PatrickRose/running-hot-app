import { router } from '@inertiajs/react';
import { ChevronDownIcon } from 'lucide-react';
import { useState } from 'react';
import { CardFace } from '@/components/card-face';
import { FactionBadge } from '@/components/faction-badge';
import { GameIcon } from '@/components/game-icon';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { budget as topUpBudget } from '@/routes/facilities';
import {
    activate,
    advance,
    begin,
    boost,
    challenge,
    charge,
    defend,
    leave,
} from '@/routes/runs';
import {
    resolve as resolveAccess,
    store as storeAccess,
} from '@/routes/runs/accesses';
import {
    mark as markConsequence,
    store as storeConsequence,
} from '@/routes/runs/consequences';
import {
    play as playEquipment,
    store as equipItems,
} from '@/routes/runs/equipment';
import { update as updateSkills } from '@/routes/runs/skills';
import type {
    RunCard,
    RunConsequenceEffect,
    RunEquipmentCard,
    RunHand,
    RunParticipantView,
    RunUndecidedAccess,
    RunView,
} from '@/types/game';

/**
 * The four steps, in the order rulebook 3.4.2 gives them, so the run reads as
 * a loop rather than as a list of buttons.
 */
const STEPS = ['activate', 'challenge', 'consequence', 'breather'] as const;

/**
 * What Security pays in Alerts to add a consequence itself (rulebook 3.4.2).
 *
 * Mirrored here only to label the buttons. The server prices every one of them
 * from `RunConsequence::alertCost()` and refuses what it cannot afford, so this
 * copy can never charge anybody anything.
 */
const ALERT_PRICES: Partial<Record<RunConsequenceEffect, number>> = {
    tag: 2,
    wound: 5,
    retry: 12,
    end_the_run: 15,
};

/**
 * What one of these acts sends: a flat body of scalars, which is all any of
 * them needs. Named so the two little post() helpers below can share it rather
 * than each widening to `object` and losing the check.
 */
type RunPayload = Record<
    string,
    | string
    | number
    | boolean
    | null
    // What Security marks: a count per effect, which is how a card's own
    // sentence goes over the wire.
    | Record<string, number>
    // A whole loadout at once, because equipping sets the set rather than
    // adding to it - the same choice handing cards to the Council Chair makes.
    | number[]
>;

const EFFECT_LABELS: Record<RunConsequenceEffect, string> = {
    alert: 'Alert',
    tag: 'Tag',
    wound: 'Wound',
    retry: 'Retry',
    end_the_run: 'End the Run',
};

/**
 * The consequences a card prints a number of, as opposed to the ones it either
 * does or does not do.
 *
 * A card says “2 alerts, 1 wound” but never “two Retries”, so these three get a
 * counter each and Retry gets a checkbox.
 */
const COUNTED_EFFECTS = ['alert', 'tag', 'wound'] as const;

/**
 * Doing one thing to a run, and hearing back when the server says no.
 *
 * A page posting with `router.post` gets no `errors` of its own the way an
 * Inertia `<Form>` does, and reading them off `usePage()` is no good here:
 * several runs are on screen at once and every one of them reports against the
 * same handful of keys, so a page-level `errors` would put one group's refusal
 * under every panel on the page. Same bug the research table's `ScoreForm`
 * had, and the same answer — each desk keeps its own.
 *
 * The first message is the one drawn, because every refusal in `RunEngine` is
 * a single sentence about the one thing that was wrong: a Boost the budget
 * cannot cover names the purse that came up short, and there is no second line
 * waiting behind it.
 */
function useRunAction() {
    const [busy, setBusy] = useState(false);
    const [refusal, setRefusal] = useState<string | null>(null);

    // A route definition or the plain url one of them resolves to: the access
    // routes take two parameters and are called for their `.url`.
    const post = (
        url: ReturnType<typeof activate> | string,
        data: RunPayload = {},
    ): void => {
        setBusy(true);
        setRefusal(null);
        router.post(url, data, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
            onError: (errors) =>
                setRefusal(
                    Object.values(errors)[0] ??
                        'The server would not take that.',
                ),
        });
    };

    return { busy, refusal, post };
}

/**
 * Why the last thing you tried did not happen.
 *
 * Drawn inside the desk that was refused rather than at the top of the run, so
 * a Runner spending an access and a Security player who cannot afford a Boost
 * each read the answer where they are looking. It is `role="alert"` because it
 * appears in response to something you just did and nothing else moves.
 */
function Refusal({ message }: { message: string | null }) {
    if (message === null) {
        return null;
    }

    return (
        <p
            role="alert"
            className="rounded-md border border-amber-500/40 bg-amber-50 px-2 py-1.5 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        >
            {message}
        </p>
    );
}

/**
 * One run, from whichever side of it the viewer is on.
 *
 * The same component for both, because the payload is what differs rather than
 * the layout: a Runner is handed no stack depth, no budget and a nameless card
 * until it is flipped, and the panel simply has nothing to draw in those
 * places. Building two components would have meant two places for the secrets
 * to leak from.
 */
export function RunPanel({ run }: { run: RunView }) {
    const finished = run.status === 'succeeded' || run.status === 'failed';
    const active = run.participants.filter((runner) => !runner.left);

    // A failed run opens closed: there is nothing left to do on it, and a turn
    // can leave four or five of them stacked above the one group still inside.
    // A successful one opens open, because its accesses are still to spend -
    // and stays collapsible by hand for once they have been.
    //
    // Read once, on mount, rather than followed: a run that fails while
    // somebody is watching it must not snap shut under them mid-sentence. The
    // page polls every five seconds, so that would otherwise happen to whoever
    // was reading the log at the moment it ended.
    const [open, setOpen] = useState(run.status !== 'failed');

    return (
        <Card>
            <Collapsible
                // Only a finished run can be shut, so a live one is held open
                // whatever the state says.
                open={finished ? open : true}
                onOpenChange={setOpen}
            >
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <FactionBadge faction={run.facility.corporation} />
                            <div>
                                <CardTitle>{run.facility.name}</CardTitle>
                                <CardDescription>
                                    {run.facility.corporation.name} ·{' '}
                                    {run.facility.facility_type}
                                </CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {run.order_index !== null && (
                                <Badge
                                    variant="outline"
                                    title={run.order_reason ?? undefined}
                                >
                                    Going {ordinal(run.order_index)}
                                </Badge>
                            )}
                            <Badge
                                variant={finished ? 'secondary' : 'default'}
                                className={
                                    run.status === 'succeeded'
                                        ? 'bg-emerald-600 hover:bg-emerald-600'
                                        : run.status === 'failed'
                                          ? 'bg-rose-600 hover:bg-rose-600'
                                          : undefined
                                }
                            >
                                {run.status_label}
                            </Badge>
                            {finished && (
                                <CollapsibleTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="gap-1"
                                    >
                                        {open ? 'Hide' : 'Show'}
                                        <ChevronDownIcon
                                            aria-hidden
                                            className={cn(
                                                'size-4 transition-transform',
                                                open && 'rotate-180',
                                            )}
                                        />
                                        <span className="sr-only">
                                            {open ? 'Hide' : 'Show'} the run on{' '}
                                            {run.facility.name}
                                        </span>
                                    </Button>
                                </CollapsibleTrigger>
                            )}
                        </div>
                    </div>

                    {/* What a shut run still has to say. Collapsing it must not
                    turn it into a name and a colour - how it ended and how far
                    they got is most of why anybody looks at a finished run. */}
                    {finished && !open && <FinishedSummary run={run} />}
                </CardHeader>

                <CollapsibleContent>
                    <CardContent className="flex flex-col gap-6">
                        <RunGauges run={run} />

                        {run.status === 'submitted' && (
                            <>
                                <EquipDesk run={run} />
                                <NotInYet run={run} />
                            </>
                        )}

                        {run.status === 'running' && (
                            <>
                                <StepTrack run={run} />
                                <div className="grid gap-6 lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]">
                                    <FacingCard run={run} />
                                    <div className="flex flex-col gap-4">
                                        {run.can_defend && (
                                            <SecurityDesk run={run} />
                                        )}
                                        {run.can_lead && (
                                            <LeaderDesk run={run} />
                                        )}
                                        {!run.can_lead && !run.can_defend && (
                                            <Watching run={run} />
                                        )}
                                        {/* Every Runner's own, Leader or not:
                                        3.4.2 gives each of them a card a step
                                        rather than giving the Leader four. */}
                                        <PlayEquipmentDesk run={run} />
                                    </div>
                                </div>
                            </>
                        )}

                        {run.status === 'succeeded' && <AccessDesk run={run} />}

                        <Party run={run} active={active} />
                        <RunLog run={run} />
                    </CardContent>
                </CollapsibleContent>
            </Collapsible>
        </Card>
    );
}

/**
 * The line a collapsed run still shows.
 *
 * Cards passed rather than Alerts or budget, because those are about a run in
 * progress and this one is over: what is worth knowing at a glance afterwards
 * is how far the group got, who was still standing, and - on a run that got in
 * - whether there is an access nobody has spent.
 */
function FinishedSummary({ run }: { run: RunView }) {
    const standing = run.participants.filter((runner) => !runner.left).length;
    const unspent = Object.values(run.accesses.left).reduce<number>(
        (total, left) => total + (left ?? 0),
        0,
    );

    return (
        <p className="flex flex-wrap gap-x-3 text-sm text-muted-foreground">
            <span>
                {run.cards_passed} card{run.cards_passed === 1 ? '' : 's'}{' '}
                passed
            </span>
            <span>
                {standing} of {run.participants.length} still standing
            </span>
            {unspent > 0 && (
                <span className="font-medium text-foreground">
                    {unspent} access{unspent === 1 ? '' : 'es'} still to spend
                </span>
            )}
        </p>
    );
}

/**
 * What the Runners take out of a Facility they got into (rulebook 3.4.3).
 *
 * One access each, and each Runner spends their own — so this draws a row per
 * Runner still standing rather than handing the lot to the Run Leader. The
 * rulebook has the Leader choosing the cards; at this table every Runner who
 * walked in gets to decide what their own access was for, which is the whole
 * reason a group of four is worth more than a group of one.
 *
 * Both sides read it. 3.4.3 keeps the *choices* Secret from Security "unless
 * they are Directing Security from this Facility", but by the time an access
 * has happened it is a thing that was done to the Corporation rather than a
 * plan — and the Corporation is entitled to know what left the building.
 */
function AccessDesk({ run }: { run: RunView }) {
    const { busy, refusal, post } = useRunAction();
    const holders = run.participants.filter(
        (runner) => (run.accesses.left[String(runner.character_id)] ?? 0) > 0,
    );

    const spend = (
        characterId: number,
        kind: string,
        holdingId: number | null = null,
    ) => {
        post(
            storeAccess(run.id),
            holdingId === null
                ? { character_id: characterId, kind }
                : {
                      character_id: characterId,
                      kind,
                      technology_holding_id: holdingId,
                  },
        );
    };

    // Cards nobody has turned over yet. The blind draw only reaches these, so
    // when it hits zero the only way to a card is by name.
    const unseen = run.technologies_left - run.known_technologies.length;

    return (
        <section className="flex flex-col gap-3 rounded-md border p-3">
            <header className="flex flex-wrap items-baseline justify-between gap-2">
                <h3 className="font-medium">Inside</h3>
                <p className="text-sm text-muted-foreground">
                    {run.technologies_left === 0
                        ? 'Nothing left in the racks.'
                        : `${run.technologies_left} in the racks · ${unseen} not turned over yet`}
                </p>
            </header>

            <Refusal message={refusal} />

            {/* What this Facility's own effect actually is, said once and in
                full. It is one of the four things an access can be spent on, so
                deciding between them means being able to read it - it used to
                be a clause at the end of a paragraph of small print. */}
            {run.access_effect !== null && (
                <p className="rounded-md bg-muted/50 px-3 py-2 text-sm">
                    <span className="text-muted-foreground">
                        This Facility&rsquo;s effect:{' '}
                    </span>
                    {run.access_effect}
                </p>
            )}

            {run.accesses.taken.length > 0 && (
                <ul className="flex flex-col gap-1 text-sm">
                    {run.accesses.taken.map((taken) => (
                        <li key={taken.id} className="flex flex-wrap gap-x-2">
                            <span className="font-medium">
                                {taken.character}
                            </span>
                            <span className="text-muted-foreground">
                                {taken.kind_label}
                                {taken.action_label !== null
                                    ? ` · ${taken.action_label}`
                                    : ''}
                                {taken.technology !== null
                                    ? ` · ${taken.technology}`
                                    : ''}
                                {taken.credits !== null
                                    ? ` · ${taken.credits} Credits`
                                    : ''}
                                {taken.outcome !== null
                                    ? ` · ${taken.outcome.replace(/_/g, ' ')}`
                                    : ' · face up, undecided'}
                                {taken.discount_percent !== null
                                    ? ` (${taken.discount_percent}% off)`
                                    : ''}
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            {/* A card that has been turned over and not yet decided on. The
                choice comes after the reveal, which is the only order it makes
                sense in: you cannot pick how to open a safe before you know
                what is in it. */}
            {run.accesses.undecided.map((undecided) => (
                <UndecidedCard
                    key={undecided.id}
                    run={run}
                    access={undecided}
                />
            ))}

            {holders.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Every access has been spent.
                </p>
            ) : (
                holders.map((runner) => (
                    <div
                        key={runner.character_id}
                        className="flex flex-col gap-2 rounded-md bg-muted/40 p-2"
                    >
                        <p className="text-sm font-medium">
                            {runner.name}
                            <span className="font-normal text-muted-foreground">
                                {' '}
                                — one access
                            </span>
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={busy || unseen === 0}
                                onClick={() =>
                                    spend(runner.character_id, 'technology')
                                }
                            >
                                {unseen === 0
                                    ? 'Nothing new to draw'
                                    : 'Draw an unseen card'}
                            </Button>
                            {/* A card that has been face up can be asked for by
                                name: the Runners have seen it, and going back
                                for one is how 3.4.3 says you make a second
                                copy. An unseen card can only ever be drawn. */}
                            {run.known_technologies.map((known) => (
                                <Button
                                    key={known.id}
                                    size="sm"
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() =>
                                        spend(
                                            runner.character_id,
                                            'technology',
                                            known.id,
                                        )
                                    }
                                >
                                    Go back for {known.name}
                                </Button>
                            ))}
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={busy || run.accesses.credits_taken}
                                onClick={() =>
                                    spend(runner.character_id, 'credits')
                                }
                            >
                                {run.accesses.credits_taken
                                    ? 'Credits card gone'
                                    : 'Take the Credits card'}
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={
                                    busy ||
                                    run.accesses.facility_effect_taken ||
                                    run.access_effect === null
                                }
                                onClick={() =>
                                    spend(
                                        runner.character_id,
                                        'facility_effect',
                                    )
                                }
                            >
                                {run.accesses.facility_effect_taken
                                    ? 'Facility effect gone'
                                    : 'Use the Facility effect'}
                            </Button>
                            <Button
                                size="sm"
                                variant="secondary"
                                disabled={busy}
                                onClick={() =>
                                    spend(runner.character_id, 'plot')
                                }
                            >
                                Plot access
                            </Button>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            A card nobody has turned over is drawn, not chosen,
                            and you decide what to do with it once it is face
                            up. One that has been face up can be asked for by
                            name — going back for it is how you make a second
                            copy. A plot access needs no reason here: tell
                            Control what you are after.
                        </p>
                    </div>
                ))
            )}
        </section>
    );
}

/**
 * The card is face up. Copy it, steal it, break it, or leave it.
 *
 * Leaving it is a real answer rather than a way out, which is why it sits with
 * the other three rather than being a cancel. The access is spent either way:
 * what it bought was finding out what the Facility is holding, and a Runner who
 * does not fancy their dice against this particular card has still learned
 * that.
 */
function UndecidedCard({
    run,
    access,
}: {
    run: RunView;
    access: RunUndecidedAccess;
}) {
    const { busy, refusal, post } = useRunAction();

    const decide = (action: string | null) => {
        post(
            resolveAccess([run.id, access.id]).url,
            action === null ? {} : { action },
        );
    };

    return (
        <div className="flex flex-col gap-2 rounded-md border border-dashed p-2">
            <p className="text-sm">
                <span className="font-medium">{access.character}</span> turned
                up{' '}
                <span className="font-medium">
                    {access.technology ?? 'a card'}
                </span>
                . What now?
            </p>
            <Refusal message={refusal} />
            <div className="flex flex-wrap gap-2">
                <Button
                    size="sm"
                    variant="outline"
                    disabled={busy}
                    onClick={() => decide('copy')}
                >
                    Copy it
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    disabled={busy}
                    onClick={() => decide('steal')}
                >
                    Steal it (8+)
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    disabled={busy}
                    onClick={() => decide('destroy')}
                >
                    Destroy it
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    disabled={busy}
                    onClick={() => decide(null)}
                >
                    Leave it
                </Button>
            </div>
            <p className="text-xs text-muted-foreground">
                Copy leaves it where it is and is worth 25% off, or 50% on four
                successes. Steal takes it on 8. Destroy only removes it outright
                on twelve, and anything less leaves traces.
            </p>
        </div>
    );
}

/**
 * The numbers both sides can see, and the one they cannot.
 *
 * Alerts are the Runners' own doing — their Tags, their group size — so the
 * pool is shown to everybody, along with what it is currently adding to every
 * card still ahead of them. How close the next point of strength is matters to
 * both: to Security as a reason to hold on to Alerts, and to the Runners as a
 * reason to stop taking them.
 */
function RunGauges({ run }: { run: RunView }) {
    return (
        <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Gauge
                label="Alerts"
                value={run.alerts_available}
                note={
                    run.alerts_spent > 0
                        ? `${run.alerts} raised, ${run.alerts_spent} spent`
                        : `+${run.alert_strength_bonus} on every card left`
                }
            />
            <Gauge
                label="Cards passed"
                value={run.cards_passed}
                note={
                    run.active_cards_passed === run.cards_passed
                        ? `+${Math.floor(run.active_cards_passed / 2)} strength from those`
                        : `${run.active_cards_passed} of them Active`
                }
            />
            <Gauge
                label="Cards left"
                value={run.cards_remaining ?? '—'}
                note={
                    run.cards_remaining === null
                        ? 'Secret (3.4.1)'
                        : 'You can see the stack'
                }
            />
            <Gauge
                label="Pass"
                value={run.pass}
                note={run.retry_pending ? 'Retry pending' : run.step_label}
            />
        </dl>
    );
}

function Gauge({
    label,
    value,
    note,
}: {
    label: string;
    value: number | string;
    note: string;
}) {
    return (
        <div className="rounded-md border p-3">
            <dt className="text-xs text-muted-foreground uppercase">{label}</dt>
            <dd className="text-2xl font-semibold tabular-nums">{value}</dd>
            <dd className="text-xs text-muted-foreground">{note}</dd>
        </div>
    );
}

/**
 * A run that has been put in for and not gone in yet.
 *
 * Alerts are generated on the way in, from the Tags the group is carrying at
 * that moment (3.4.1), so this says so — a Runner who can get a Tag removed
 * before going in is a Runner who should.
 */
function NotInYet({ run }: { run: RunView }) {
    const [override, setOverride] = useState('');
    const { busy, refusal, post } = useRunAction();
    const big = run.participants.length > 6;

    if (!run.can_lead) {
        return (
            <p className="text-sm text-muted-foreground">
                Submitted. It goes in when the Run Leader calls it.
            </p>
        );
    }

    return (
        <form
            className="flex flex-col gap-3 rounded-md border p-3"
            onSubmit={(event) => {
                event.preventDefault();
                post(
                    begin(run.id),
                    override === ''
                        ? {}
                        : { group_alert_override: Number(override) },
                );
            }}
        >
            <Refusal message={refusal} />
            <p className="text-sm">
                Alerts are raised on the way in, from the Tags you are carrying
                now and the size of the group. A Tag removed before you go in is
                an Alert you never take.
            </p>
            {big && (
                <div className="flex flex-col gap-1">
                    <Label htmlFor={`override-${run.id}`}>
                        Group Alerts (Control&rsquo;s number)
                    </Label>
                    <Input
                        id={`override-${run.id}`}
                        type="number"
                        min={0}
                        value={override}
                        onChange={(event) => setOverride(event.target.value)}
                        placeholder="Leave blank for our arithmetic"
                    />
                    <p className="text-xs text-muted-foreground">
                        Past six Runners the rulebook sends Control to a help
                        sheet, so the number we would use is a proposal.
                    </p>
                </div>
            )}
            <Button type="submit" disabled={busy} className="self-start">
                Go in
            </Button>
        </form>
    );
}

/**
 * A card in a hand or on the table, small enough to sit in a list.
 *
 * Its name, its category and what it does, because the effect is the whole
 * reason you are choosing between them — and because it is what you then
 * declare on the challenge form, since none of the seventy-four is parsed.
 */
function EquipmentLine({ card }: { card: RunEquipmentCard }) {
    return (
        <span className="flex min-w-0 flex-col">
            <span className="flex items-center gap-1.5 text-sm font-medium">
                <GameIcon
                    glyph={card.category_glyph}
                    label={card.category_label}
                />
                {card.name}
                {card.copies !== null && card.copies > 1 ? (
                    <span className="font-mono text-xs font-normal text-muted-foreground">
                        &times;{card.copies}
                    </span>
                ) : null}
            </span>
            <span className="text-xs text-muted-foreground">{card.effect}</span>
        </span>
    );
}

/**
 * What the group is taking in, and what each of them has in front of them.
 *
 * Drawn before the run goes in, because that is when 3.4.1 has you equip a
 * permanent item — "by placing them in front of you", which is also why the
 * finished loadouts are the whole group's to read rather than each Runner's
 * own secret. Your *hand* is yours; what you are wearing is on the table.
 *
 * One form per Runner you hold, so somebody playing two seats kits both out.
 */
function EquipDesk({ run }: { run: RunView }) {
    const equipment = run.equipment;

    if (equipment === null || equipment.hands.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-4 rounded-md border p-3">
            <div>
                <h3 className="font-medium">Kit</h3>
                <p className="text-sm text-muted-foreground">
                    Up to {equipment.cap} permanent items each, placed in front
                    of you before you go in. Choosing again replaces what you
                    picked.
                </p>
            </div>

            <GroupLoadouts run={run} />

            {equipment.hands.map((hand) => (
                <div key={hand.character_id} className="flex flex-col gap-3">
                    <EquipForm run={run} hand={hand} />
                    <SkillAdjustment run={run} hand={hand} />
                </div>
            ))}
        </section>
    );
}

function EquipForm({ run, hand }: { run: RunView; hand: RunHand }) {
    const equipment = run.equipment;
    const alreadyOn =
        equipment?.equipped.find(
            (loadout) => loadout.character_id === hand.character_id,
        )?.cards ?? [];

    const [chosen, setChosen] = useState<number[]>(() =>
        alreadyOn.map((card) => card.card_type_id),
    );
    const { busy, refusal, post } = useRunAction();

    const cap = equipment?.cap ?? 3;
    const full = chosen.length >= cap;

    if (hand.permanent.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {hand.name} is carrying no permanent items.
            </p>
        );
    }

    return (
        <form
            className="flex flex-col gap-2 border-l-2 pl-3"
            onSubmit={(event) => {
                event.preventDefault();
                post(equipItems(run.id), {
                    character_id: hand.character_id,
                    equipment_card_type_ids: chosen,
                });
            }}
        >
            <p className="text-sm font-medium">
                {hand.name}
                <span className="ml-2 text-xs font-normal text-muted-foreground">
                    {chosen.length} of {cap} chosen
                </span>
            </p>

            <Refusal message={refusal} />

            <ul className="flex flex-col gap-1.5">
                {hand.permanent.map((card) => {
                    const picked = chosen.includes(card.card_type_id);

                    return (
                        <li key={card.card_type_id}>
                            <label className="flex items-start gap-2">
                                <input
                                    type="checkbox"
                                    className="mt-1"
                                    checked={picked}
                                    /* The cap is the server's rule; this only
                                    stops you queueing up a refusal you can
                                    already see coming. */
                                    disabled={!picked && full}
                                    onChange={() =>
                                        setChosen((current) =>
                                            picked
                                                ? current.filter(
                                                      (id) =>
                                                          id !==
                                                          card.card_type_id,
                                                  )
                                                : [
                                                      ...current,
                                                      card.card_type_id,
                                                  ],
                                        )
                                    }
                                />
                                <EquipmentLine card={card} />
                            </label>
                        </li>
                    );
                })}
            </ul>

            <Button
                type="submit"
                size="sm"
                disabled={busy}
                className="self-start"
            >
                {chosen.length === 0 ? 'Take nothing in' : 'Equip these'}
            </Button>
        </form>
    );
}

/**
 * Who is wearing what, for everybody on the run.
 *
 * Face up on the table at 3.4.1, so face up here: a group deciding who takes a
 * consequence needs to know which of them has the Armour on.
 */
function GroupLoadouts({ run }: { run: RunView }) {
    const loadouts = (run.equipment?.equipped ?? []).filter(
        (loadout) => loadout.cards.length > 0,
    );

    if (loadouts.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Nobody has equipped anything yet.
            </p>
        );
    }

    return (
        <dl className="flex flex-col gap-1 text-sm">
            {loadouts.map((loadout) => (
                <div
                    key={loadout.character_id}
                    className="flex flex-wrap items-baseline gap-x-2"
                >
                    <dt className="font-medium">{loadout.name}</dt>
                    <dd className="text-muted-foreground">
                        {loadout.cards.map((card) => card.name).join(', ')}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

/**
 * Playing a This-run or Single-use card (rulebook 3.4.2).
 *
 * One card per Runner per *step*, which is what the worked examples make it:
 * Ryan uses a Boost during the Activate step and "can not use another card
 * until the next Activate step". The server counts the rows and refuses the
 * second; this says so up front, because a button that looks live and then
 * refuses is worse than one that explains itself.
 *
 * Every Runner gets their own, Leader or not — the card is theirs to spend.
 */
function PlayEquipmentDesk({ run }: { run: RunView }) {
    const hands = (run.equipment?.hands ?? []).filter((hand) => !hand.left);

    if (hands.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-3 rounded-md border p-3">
            <div>
                <h3 className="font-medium">Your cards</h3>
                <p className="text-sm text-muted-foreground">
                    One each during this {run.step_label} step. What it does to
                    a roll is yours to declare on the Leader&rsquo;s form.
                </p>
            </div>

            <GroupLoadouts run={run} />

            {hands.map((hand) => (
                <div key={hand.character_id} className="flex flex-col gap-3">
                    <PlayEquipmentForm run={run} hand={hand} />
                    <SkillAdjustment run={run} hand={hand} />
                </div>
            ))}
        </section>
    );
}

/**
 * What a Runner's Equipment is worth to their skills, for this run.
 *
 * A card reading "+2 Brute" changes the *skill*, and that is not the same as
 * adding dice: 3.4.2 halves a skill on the way into the pool for everybody who
 * is not leading, so +2 Brawn is two dice to the Run Leader and one to anybody
 * else. The Leader's per-roll boxes on the challenge form are the other thing,
 * for a card that really does say "+1 die for this roll".
 *
 * Declared rather than read off the cards, like everything else here — and set
 * rather than added to, so a number typed wrong is corrected by sending the
 * right one. The pool above re-reads itself the moment this lands.
 */
function SkillAdjustment({ run, hand }: { run: RunView; hand: RunHand }) {
    const [brawn, setBrawn] = useState(String(hand.brawn_adjustment));
    const [hack, setHack] = useState(String(hand.hack_adjustment));
    const { busy, refusal, post } = useRunAction();

    const changed =
        brawn !== String(hand.brawn_adjustment) ||
        hack !== String(hand.hack_adjustment);

    return (
        <form
            className="flex flex-col gap-2 border-l-2 pl-3"
            onSubmit={(event) => {
                event.preventDefault();
                post(updateSkills(run.id), {
                    character_id: hand.character_id,
                    brawn_adjustment: brawn === '' ? 0 : Number(brawn),
                    hack_adjustment: hack === '' ? 0 : Number(hack),
                });
            }}
        >
            <p className="text-sm font-medium">
                {hand.name}&rsquo;s skills for this run
            </p>

            <Refusal message={refusal} />

            <div className="flex flex-wrap items-end gap-2">
                <div className="flex flex-col gap-1">
                    <Label htmlFor={`brawn-${run.id}-${hand.character_id}`}>
                        Brawn
                    </Label>
                    <Input
                        id={`brawn-${run.id}-${hand.character_id}`}
                        type="number"
                        className="w-20"
                        value={brawn}
                        placeholder="0"
                        onChange={(event) => setBrawn(event.target.value)}
                    />
                </div>
                <div className="flex flex-col gap-1">
                    <Label htmlFor={`hack-${run.id}-${hand.character_id}`}>
                        Hack
                    </Label>
                    <Input
                        id={`hack-${run.id}-${hand.character_id}`}
                        type="number"
                        className="w-20"
                        value={hack}
                        placeholder="0"
                        onChange={(event) => setHack(event.target.value)}
                    />
                </div>
                <Button type="submit" size="sm" disabled={busy || !changed}>
                    Set
                </Button>
            </div>

            <p className="text-xs text-muted-foreground">
                What your cards add to your skills, not to your dice — a skill
                is halved for everyone who is not leading, so the pool counts it
                properly. A card that grants dice for one roll goes on the
                Leader&rsquo;s form instead.
            </p>
        </form>
    );
}

function PlayEquipmentForm({ run, hand }: { run: RunView; hand: RunHand }) {
    const [selected, setSelected] = useState('');
    const { busy, refusal, post } = useRunAction();

    if (hand.playable.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {hand.name} has nothing left to play.
            </p>
        );
    }

    if (hand.played_this_step) {
        return (
            <p className="text-sm text-muted-foreground">
                {hand.name} has already played a card this {run.step_label}{' '}
                step.
            </p>
        );
    }

    const card = hand.playable.find(
        (option) => String(option.card_type_id) === selected,
    );

    return (
        <form
            className="flex flex-col gap-2 border-l-2 pl-3"
            onSubmit={(event) => {
                event.preventDefault();
                post(playEquipment(run.id), {
                    character_id: hand.character_id,
                    equipment_card_type_id: Number(selected),
                });
            }}
        >
            <p className="text-sm font-medium">{hand.name}</p>

            <Refusal message={refusal} />

            <select
                aria-label={`A card for ${hand.name} to play`}
                className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                value={selected}
                onChange={(event) => setSelected(event.target.value)}
            >
                <option value="">Choose a card&hellip;</option>
                {hand.playable.map((option) => (
                    <option
                        key={option.card_type_id}
                        value={option.card_type_id}
                    >
                        {option.name}
                        {option.copies !== null && option.copies > 1
                            ? ` (\u00d7${option.copies})`
                            : ''}
                    </option>
                ))}
            </select>

            {card !== undefined && (
                <p className="text-xs text-muted-foreground">{card.effect}</p>
            )}

            <Button
                type="submit"
                size="sm"
                disabled={busy || selected === ''}
                className="self-start"
            >
                Play it
            </Button>
        </form>
    );
}

/**
 * Where in the loop the run is.
 *
 * Drawn as the four steps rather than as one label, because the loop is the
 * thing a player has to hold in their head under time pressure — and because
 * the step tells each side whose turn it is to do something.
 */
function StepTrack({ run }: { run: RunView }) {
    return (
        <ol className="flex flex-wrap items-center gap-1 text-sm">
            {STEPS.map((step, index) => {
                const current = run.step === step;

                return (
                    <li key={step} className="flex items-center gap-1">
                        <span
                            className={
                                current
                                    ? 'rounded-md bg-primary px-2 py-1 font-medium text-primary-foreground'
                                    : 'px-2 py-1 text-muted-foreground'
                            }
                        >
                            {step === 'activate'
                                ? 'Activate'
                                : step === 'challenge'
                                  ? 'Challenge'
                                  : step === 'consequence'
                                    ? 'Consequence'
                                    : 'Breather'}
                        </span>
                        {index < STEPS.length - 1 && (
                            <span className="text-muted-foreground">→</span>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}

/**
 * The card in front of the Runners.
 *
 * A card that is not Active and not yours to read is drawn as a back rather
 * than as an empty space, because "there is something there and you do not know
 * what" is the actual state of play.
 */
function FacingCard({ run }: { run: RunView }) {
    const card = run.card;

    if (card === null) {
        return (
            <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                Nothing in front of you. Move on at the Breather to finish the
                run.
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-2">
            {card.name === undefined ? (
                <FaceDownCard card={card} />
            ) : (
                <CardFace
                    name={card.name}
                    code={card.code ?? null}
                    imagePath={card.image_path ?? null}
                    shape="landscape"
                    className="w-full sm:w-full"
                    lines={[
                        { label: 'Challenge', value: card.challenge ?? null },
                        {
                            label: 'Consequence',
                            value: card.consequence ?? null,
                        },
                        {
                            label: 'Charge',
                            value:
                                card.charge_cost === null ||
                                card.charge_cost === undefined
                                    ? null
                                    : `${card.charge_cost} — ${card.charge_consequence ?? 'see the card'}`,
                        },
                    ]}
                />
            )}

            <div className="flex flex-wrap items-center gap-2 text-xs">
                <Badge variant="outline">{card.kind_label}</Badge>
                <Badge variant={card.active ? 'default' : 'secondary'}>
                    {card.active ? 'Active' : 'Inactive'}
                </Badge>
                {card.boosts > 0 && (
                    <Badge variant="outline">+{card.boosts} Boosted</Badge>
                )}
            </div>

            {card.challenge !== undefined && (
                <p className="text-sm">
                    <span className="text-muted-foreground">Challenge: </span>
                    {card.challenge}
                </p>
            )}
            {card.consequence !== undefined && (
                <p className="text-sm">
                    <span className="text-muted-foreground">Consequence: </span>
                    {card.consequence}
                </p>
            )}
        </div>
    );
}

/**
 * A card the viewer is not entitled to read.
 *
 * The kind is shown because the Runners can see where they are standing — they
 * know when they are through the physical stack and into the cyber one — but
 * nothing else about it is theirs until Security flips it over.
 */
function FaceDownCard({ card }: { card: RunCard }) {
    return (
        <div className="flex aspect-[600/440] w-full flex-col items-center justify-center gap-2 rounded-md border border-dashed bg-muted/40 p-4 text-center">
            <p className="font-medium">A {card.kind_label} card, face down</p>
            <p className="text-sm text-muted-foreground">
                {card.settled
                    ? 'Security has had its go at this one.'
                    : 'Waiting on Security.'}
            </p>
        </div>
    );
}

/**
 * Security's controls: switch the card on, make it worse, pay a Charge, or
 * spend Alerts on a consequence of their own.
 */
function SecurityDesk({ run }: { run: RunView }) {
    const card = run.card;
    const budget = run.budget;
    // One slider per thing that costs something, because each of them names
    // its own cost and the cost is half of what the control says. They cannot
    // share: a Boost of three and a Charge of one are on screen together, and
    // one slider reading "1 required" would be lying about the other.
    const [activationAlerts, setActivationAlerts] = useState(0);
    const [boostAlerts, setBoostAlerts] = useState(0);
    const [chargeAlerts, setChargeAlerts] = useState(0);
    const [boosts, setBoosts] = useState('1');
    const [printed, setPrinted] = useState('');
    const { busy, refusal, post } = useRunAction();

    if (card === null) {
        return null;
    }

    // What N Boosts cost from here: the next one is next_boost_cost and each
    // after it costs one more, which is the rule the engine applies.
    const boostCost = (times: number) => {
        const next = card?.next_boost_cost ?? 1;

        return (times * (2 * next + times - 1)) / 2;
    };

    /**
     * Split one cost between the Alerts the slider names and the budget.
     *
     * Two purses and no third: company money reaches a run by raising the
     * budget, never by a payment quietly reaching past it. The server checks
     * both halves against what is really there — this only decides the
     * allocation, which is the player's to make.
     */
    const spend = (cost: number, alerts: number): RunPayload => {
        const fromAlerts = Math.min(alerts, cost, run.alerts_available);

        return {
            alerts_to_spend: fromAlerts,
            budget_to_spend: cost - fromAlerts,
        };
    };

    return (
        <section className="flex flex-col gap-3 rounded-md border p-3">
            <header className="flex flex-wrap items-baseline justify-between gap-2">
                <h3 className="font-medium">Security</h3>
                {budget !== null && (
                    <p className="text-sm text-muted-foreground">
                        {budget.left} of {budget.placed} Credits of budget left
                    </p>
                )}
            </header>

            <Refusal message={refusal} />

            <BudgetTopUp run={run} />

            {!card.settled && (
                <div className="flex flex-col gap-2">
                    <p className="text-sm">
                        {card.activation_cost === null
                            ? 'Switch it on.'
                            : `Switching it on costs ${card.activation_cost}.`}
                    </p>
                    <PaymentSlider
                        run={run}
                        id={`activation-${run.id}`}
                        cost={card.activation_cost ?? 0}
                        alerts={activationAlerts}
                        onAlerts={setActivationAlerts}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            disabled={busy}
                            onClick={() =>
                                post(activate(run.id), {
                                    activating: true,
                                    ...spend(
                                        card.activation_cost ?? 0,
                                        activationAlerts,
                                    ),
                                })
                            }
                        >
                            Activate
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={busy}
                            onClick={() =>
                                post(activate(run.id), {
                                    activating: false,
                                })
                            }
                        >
                            Leave it off
                        </Button>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Leaving it off costs nothing and the Runners walk
                        straight past — which also means it makes nothing after
                        it harder.
                    </p>
                </div>
            )}

            {run.step === 'activate' && card.active && (
                <div className="flex flex-col gap-3 border-t pt-3">
                    {/*
                     * Boosting is the last line of the Activate step in 3.4.2
                     * and belongs to it: Security names the printed strength
                     * and rolls the defence at the Challenge, so a Boost bought
                     * after that could not reach the roll it was meant to win.
                     * The engine refuses it later rather than trusting this.
                     *
                     * Each of these reads the same way down the column: what
                     * sets the cost, then how it is being paid, then the button
                     * that commits it. A Boost's cost moves with the count, so
                     * the count comes first and the slider re-reads itself.
                     */}
                    <div className="flex flex-col gap-2">
                        <div className="flex flex-col gap-1">
                            <Label htmlFor={`boosts-${run.id}`}>Boosts</Label>
                            <Input
                                id={`boosts-${run.id}`}
                                type="number"
                                min={1}
                                max={9}
                                className="w-20"
                                value={boosts}
                                onChange={(event) =>
                                    setBoosts(event.target.value)
                                }
                            />
                        </div>
                        <PaymentSlider
                            run={run}
                            id={`boost-${run.id}`}
                            cost={boostCost(Number(boosts) || 1)}
                            alerts={boostAlerts}
                            onAlerts={setBoostAlerts}
                        />
                        <Button
                            size="sm"
                            variant="outline"
                            className="self-start"
                            disabled={busy}
                            onClick={() =>
                                post(boost(run.id), {
                                    times: Number(boosts) || 1,
                                    ...spend(
                                        boostCost(Number(boosts) || 1),
                                        boostAlerts,
                                    ),
                                })
                            }
                        >
                            Boost (next costs {card.next_boost_cost})
                        </Button>
                    </div>
                </div>
            )}

            {/*
             * A Charge buys an *extra* consequence on top of one the Runners
             * are already taking, so there is nothing to add it to until they
             * have lost the roll (3.4.2, and the glossary's "if the runner(s)
             * fail to break a Protection Card"). The Consequence step is
             * exactly that, so the control only exists there — and the engine
             * refuses it anywhere else rather than trusting this.
             */}
            {run.step === 'consequence' &&
                card.charge_cost !== null &&
                card.charge_cost !== undefined && (
                    <div className="flex flex-col gap-2 border-t pt-3">
                        <p className="text-sm">
                            The Runners did not break it. Paying the Charge adds
                            what the card prints:{' '}
                            <span className="font-medium">
                                {card.charge_consequence ?? 'see the card.'}
                            </span>
                        </p>
                        <PaymentSlider
                            run={run}
                            id={`charge-${run.id}`}
                            cost={card.charge_cost}
                            alerts={chargeAlerts}
                            onAlerts={setChargeAlerts}
                        />
                        <Button
                            size="sm"
                            variant="outline"
                            className="self-start"
                            disabled={busy}
                            onClick={() =>
                                post(
                                    charge(run.id),
                                    spend(card.charge_cost ?? 0, chargeAlerts),
                                )
                            }
                        >
                            Charge for {card.charge_cost}
                        </Button>
                    </div>
                )}

            {run.step === 'activate' && card.active && (
                <form
                    className="flex flex-wrap items-end gap-2 border-t pt-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        post(defend(run.id), {
                            printed_strength: Number(printed) || 0,
                        });
                        setPrinted('');
                    }}
                >
                    <div className="flex flex-col gap-1">
                        <Label htmlFor={`printed-${run.id}`}>
                            Printed strength
                        </Label>
                        <Input
                            id={`printed-${run.id}`}
                            type="number"
                            min={0}
                            required
                            className="w-24"
                            value={printed}
                            onChange={(event) => setPrinted(event.target.value)}
                        />
                    </div>
                    <Button type="submit" size="sm" disabled={busy}>
                        Roll the defence
                    </Button>
                    <StrengthReadout run={run} printed={printed} />
                </form>
            )}

            {run.step === 'challenge' && (
                <p className="border-t pt-3 text-sm text-muted-foreground">
                    Rolled. Waiting on the Runners to throw theirs.
                </p>
            )}

            {run.step === 'consequence' && (
                <ConsequenceMarkings run={run} busy={busy} post={post} />
            )}
        </section>
    );
}

/**
 * Security writing down what the Runners are about to take.
 *
 * The card is Security's to read — they are holding it, and at this step they
 * are the only person who has certainly seen it — so this is where a
 * consequence enters the run. The Run Leader's part is deciding who takes it,
 * which is the only part of it rulebook 3.4.2 gives them.
 *
 * **One button.** What the card prints and what Alerts are buying go over
 * together, because they are one decision made once: read the card, decide
 * whether to make it worse, hand the lot over. Each Alert effect used to be its
 * own button that spent the moment it was pressed, which meant committing to it
 * before seeing what the finished consequence looked like — and the Run Leader
 * watched the slip grow under them.
 *
 * The two halves are still different underneath and the wording says so: the
 * card can be marked again and again, and Alerts are gone once Mark is pressed.
 */
function ConsequenceMarkings({
    run,
    busy,
    post,
}: {
    run: RunView;
    busy: boolean;
    post: (
        url: ReturnType<typeof activate> | string,
        data?: RunPayload,
    ) => void;
}) {
    const slip = run.consequence;
    // Seeded from whatever is already marked, so re-marking is a correction
    // rather than starting again — and so a poll landing mid-edit does not
    // wipe what somebody is typing, because this only reads it once.
    const [counts, setCounts] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            COUNTED_EFFECTS.map((effect) => [
                effect,
                String(slip.effects[effect] ?? 0),
            ]),
        ),
    );
    const [retrying, setRetrying] = useState(slip.effects.retry !== undefined);
    const [ending, setEnding] = useState(slip.ends_the_run);

    // What this Mark is buying. Empty every time, because Alerts are spent on
    // the way through: what they bought is on the slip already and is not
    // being bought again.
    const [buying, setBuying] = useState<Record<string, number>>({});

    const effects: Record<string, number> = {
        ...Object.fromEntries(
            COUNTED_EFFECTS.map((effect) => [
                effect,
                Number(counts[effect]) || 0,
            ]),
        ),
        retry: retrying ? 1 : 0,
        end_the_run: ending ? 1 : 0,
    };

    const bill = Object.entries(buying).reduce(
        (total, [effect, times]) =>
            total + (ALERT_PRICES[effect as RunConsequenceEffect] ?? 0) * times,
        0,
    );

    return (
        <div className="flex flex-col gap-3 border-t pt-3">
            <p className="text-sm">
                They did not break it. Write down what the card does to them —
                all of it: “1 alert, 1 wound” is one consequence with two parts.
            </p>

            <div className="flex flex-wrap items-end gap-2">
                {COUNTED_EFFECTS.map((effect) => (
                    <div className="flex flex-col gap-1" key={effect}>
                        <Label htmlFor={`mark-${effect}-${run.id}`}>
                            {EFFECT_LABELS[effect]}s
                        </Label>
                        <Input
                            id={`mark-${effect}-${run.id}`}
                            type="number"
                            min={0}
                            max={9}
                            className="w-20"
                            value={counts[effect]}
                            onChange={(event) =>
                                setCounts((was) => ({
                                    ...was,
                                    [effect]: event.target.value,
                                }))
                            }
                        />
                    </div>
                ))}
                <label className="flex h-9 items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={retrying}
                        onChange={(event) => setRetrying(event.target.checked)}
                    />
                    Retry
                </label>
                <label className="flex h-9 items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={ending}
                        onChange={(event) => setEnding(event.target.checked)}
                    />
                    End the Run
                </label>
            </div>

            {/*
             * Above the button rather than below it: both are ways of filling
             * in the same slip, so everything that can go on it belongs on
             * screen before the thing that says you are done with it.
             */}
            <div className="flex flex-col gap-2 rounded-md bg-muted/40 p-2">
                <p className="text-sm font-medium">
                    Make it worse with Alerts
                    <span className="ml-2 font-normal text-muted-foreground tabular-nums">
                        {run.alerts_available} in hand
                    </span>
                </p>
                <div className="flex flex-wrap items-end gap-2">
                    {(['tag', 'wound', 'retry', 'end_the_run'] as const).map(
                        (effect) => (
                            <div className="flex flex-col gap-1" key={effect}>
                                <Label htmlFor={`buy-${effect}-${run.id}`}>
                                    {EFFECT_LABELS[effect]}
                                    <span className="ml-1 font-normal text-muted-foreground">
                                        {ALERT_PRICES[effect]} each
                                    </span>
                                </Label>
                                <Input
                                    id={`buy-${effect}-${run.id}`}
                                    type="number"
                                    min={0}
                                    max={9}
                                    className="w-24"
                                    value={buying[effect] ?? 0}
                                    onChange={(event) =>
                                        setBuying((was) => ({
                                            ...was,
                                            [effect]:
                                                Number(event.target.value) || 0,
                                        }))
                                    }
                                />
                            </div>
                        ),
                    )}
                </div>
                <p className="text-xs text-muted-foreground">
                    {bill === 0
                        ? 'Spent when you press Mark, and gone for good — but they lower the Alerts standing, which makes every card the Runners have left easier.'
                        : `${bill} Alert${bill === 1 ? '' : 's'} when you press Mark, leaving ${run.alerts_available - bill}.`}
                </p>
            </div>

            <Button
                size="sm"
                className="self-start"
                disabled={busy}
                onClick={() => {
                    post(markConsequence(run.id), { effects, alerts: buying });
                    setBuying({});
                }}
            >
                {slip.marked ? 'Mark again' : 'Mark it'}
                {bill > 0 ? ` — ${bill} Alerts` : ''}
            </Button>

            {slip.marked && (
                <p className="text-xs text-muted-foreground">
                    Marked: {slip.description}. Waiting on the Run Leader to say
                    who takes it.
                </p>
            )}
        </div>
    );
}

/**
 * How one cost is being paid, as the slider that names it.
 *
 * Two purses, and which a Credit comes out of is a real decision rather than
 * bookkeeping. Alerts are free in Credits but spending them lowers the Alerts
 * standing, which makes every card the Runners have left *easier* — so this is
 * the trade the desk exists to make, and it is a slider because the question is
 * "how much of this cost", not "type a number".
 *
 * Which is why the slider runs to the *cost* rather than to the Alerts in hand:
 * a payment of 1 has two answers and a bar the width of a full Alert pool would
 * spend the first nine tenths of its travel saying the same one. Whatever the
 * Alerts do not cover comes off the budget, and there is no third purse — the
 * company reaches a run by raising the budget, which is the button above.
 *
 * Every number here is checked again by the server against what is really
 * there. What this decides is only the allocation, which is the player's.
 */
function PaymentSlider({
    run,
    id,
    cost,
    alerts,
    onAlerts,
}: {
    run: RunView;
    id: string;
    cost: number;
    alerts: number;
    onAlerts: (value: number) => void;
}) {
    // Nothing to apportion: a free card has no payment to make a decision
    // about, and a slider from 0 to 0 is a line with a knob on it.
    if (cost <= 0) {
        return null;
    }

    const most = Math.min(cost, run.alerts_available);
    const fromAlerts = Math.min(alerts, most);
    const fromBudget = cost - fromAlerts;
    const short = fromBudget - (run.budget?.left ?? 0);

    return (
        <div className="flex flex-col gap-1">
            <Label htmlFor={id}>
                Alerts to spend as Credits
                <span className="ml-2 font-normal text-muted-foreground tabular-nums">
                    {cost} required
                </span>
            </Label>
            <input
                id={id}
                type="range"
                min={0}
                max={most}
                step={1}
                value={fromAlerts}
                disabled={most === 0}
                onChange={(event) => onAlerts(Number(event.target.value))}
                className="w-full accent-foreground disabled:opacity-50"
            />
            <p className="text-xs text-muted-foreground">
                {most === 0
                    ? `No Alerts in hand, so all ${cost} comes off the budget.`
                    : `Spending ${fromAlerts} Alert${fromAlerts === 1 ? '' : 's'}, ${fromBudget} Credit${fromBudget === 1 ? '' : 's'} of budget.`}
                {short > 0 && (
                    <span className="text-amber-600 dark:text-amber-400">
                        {' '}
                        The budget is {short} short — top it up, or put more of
                        it on the Alerts.
                    </span>
                )}
            </p>
        </div>
    );
}

/**
 * Moving company money onto the Facility, mid-run.
 *
 * The one way the Corporation's own Credits reach a run, and deliberately a
 * separate act from paying for anything: a budget is escrow, so raising it
 * takes the Credits off the Corporation there and then and writes the ledger
 * row — where a payment reaching quietly past the budget would have spent the
 * same Credits twice over, once here and once on whatever else the Facility was
 * funded for.
 *
 * It posts the *new total* rather than the top-up, because that is what the
 * budget route takes and what `setSecurityBudget` reconciles against what has
 * already been spent. Nothing here is a second implementation of that rule:
 * a Corporation that cannot afford it is refused by the service.
 */
function BudgetTopUp({ run }: { run: RunView }) {
    const budget = run.budget;
    const [amount, setAmount] = useState('');
    const { busy, refusal, post } = useRunAction();

    if (budget === null) {
        return null;
    }

    const adding = Number(amount) || 0;

    return (
        <form
            className="flex flex-wrap items-end gap-2 rounded-md bg-muted/40 p-2"
            onSubmit={(event) => {
                event.preventDefault();
                post(topUpBudget(run.facility.id), {
                    security_budget: budget.placed + adding,
                });
                setAmount('');
            }}
        >
            <div className="flex flex-col gap-1">
                <Label htmlFor={`top-up-${run.id}`}>Add to the budget</Label>
                {/*
                 * No `max`: the browser's own constraint blocked the submit
                 * outright, so a top-up past what the company holds produced a
                 * native tooltip and no post at all — and the figure it would
                 * have been checking against is up to a poll out of date
                 * anyway. The service is the one authority on affordability,
                 * and its refusal names the Corporation.
                 */}
                <Input
                    id={`top-up-${run.id}`}
                    type="number"
                    min={1}
                    className="w-24"
                    value={amount}
                    onChange={(event) => setAmount(event.target.value)}
                />
            </div>
            <Button
                type="submit"
                size="sm"
                variant="outline"
                disabled={busy || adding < 1}
            >
                Take {adding > 0 ? adding : ''} from the company
            </Button>
            <p className="text-xs text-muted-foreground">
                {budget.company} Credit{budget.company === 1 ? '' : 's'} in the
                company. It leaves the Corporation the moment it lands here, and
                whatever is unspent goes home when the phase ends.
            </p>
            <div className="w-full">
                <Refusal message={refusal} />
            </div>
        </form>
    );
}

/**
 * What the card is actually worth, as Security types the number off it.
 *
 * Rulebook 3.4.2 escalates a card from three directions at once and they stack:
 * how far in the Runners have got, how many Alerts are standing, and whatever
 * has been spent Boosting this one. The printed strength is only the floor, and
 * the point of showing the sum before the roll is that a Runner asking where
 * "strength 6" came from gets an answer rather than a number.
 *
 * Every bonus here is the server's own, so what is shown and what is rolled
 * cannot drift; only the printed number is the browser's, and that is because
 * it is still being typed.
 */
function StrengthReadout({ run, printed }: { run: RunView; printed: string }) {
    const bonuses = run.card?.strength_bonuses;

    if (bonuses === undefined) {
        return null;
    }

    const base = Number(printed) || 0;
    const total = base + bonuses.cards_passed + bonuses.alerts + bonuses.boosts;
    const parts = [
        `${base} printed`,
        ...(bonuses.cards_passed > 0
            ? [`+${bonuses.cards_passed} for cards passed`]
            : []),
        ...(bonuses.alerts > 0 ? [`+${bonuses.alerts} from Alerts`] : []),
        ...(bonuses.boosts > 0 ? [`+${bonuses.boosts} Boosted`] : []),
    ];

    return (
        <div className="w-full rounded-md bg-muted/50 px-3 py-2 text-sm">
            <p>
                <span className="font-medium tabular-nums">{total}d8</span>{' '}
                <span className="text-muted-foreground">
                    against the Runners, 5+ to succeed. A tie goes to you.
                </span>
            </p>
            <p className="text-xs text-muted-foreground">{parts.join(', ')}</p>
        </div>
    );
}

/**
 * How many dice the Runners have in hand, and where each of them came from.
 *
 * 3.4.5 asks players to work their contribution out in advance because the
 * Action phase is fifteen minutes long, and this is that arithmetic done for
 * them: the Leader's full skill, half of everyone else's rounded down, or a
 * quarter rounded up while Wounded. Every number here is the server's, so what
 * is shown and what is rolled cannot drift apart.
 *
 * The die size is the Leader's alone — d6s if they are Wounded, d8s otherwise —
 * which is why it is said out loud beside the count: a Wounded Leader handing
 * over before a hard card is a real tactic, and it should be visible that
 * handing over is what changes it.
 */
function DicePoolReadout({
    run,
    skill,
}: {
    run: RunView;
    skill: 'brawn' | 'hack';
}) {
    const pool = run.dice_pool[skill];

    if (pool === undefined) {
        return null;
    }

    const leader = run.participants.find((runner) => runner.is_leader);
    const contributors = run.participants.filter(
        (runner) =>
            !runner.left &&
            !runner.is_leader &&
            (pool.others[String(runner.character_id)] ?? 0) > 0,
    );

    return (
        <div className="w-full rounded-md bg-muted/50 px-3 py-2 text-sm">
            <p>
                <span className="font-medium tabular-nums">
                    {pool.total}d{pool.die_faces}
                </span>{' '}
                <span className="text-muted-foreground">
                    in the pool, 5+ to succeed.
                </span>
            </p>
            <p className="text-xs text-muted-foreground">
                {leader?.name ?? 'The Leader'} rolls {pool.leader} (their full{' '}
                {skill === 'brawn' ? 'Brawn' : 'Hack'})
                {contributors.length === 0
                    ? '. Nobody else adds a die.'
                    : `, and ${contributors
                          .map(
                              (runner) =>
                                  `${runner.name} +${pool.others[String(runner.character_id)]}`,
                          )
                          .join(', ')}.`}
            </p>
        </div>
    );
}

/**
 * The Run Leader's controls: roll, decide who takes what, and move on.
 *
 * The challenge asks for the skill and the printed strength rather than working
 * them out, because a challenge is the sentence the card prints and the table
 * converts it. "Brute/Hack (2)" is the Runners' choice; "Hack (4+N) — where N
 * is the number of cards underneath this" is not knowable from a column. The
 * sentence is on screen beside this, which is the whole reason it is shown.
 */
function LeaderDesk({ run }: { run: RunView }) {
    const [skill, setSkill] = useState<'brawn' | 'hack'>('brawn');
    // What Equipment is doing to this roll, as the Runners reading their cards
    // say. Nothing is parsed from the seventy-four printed effects — encoding
    // them would be a second rulebook to keep in step, and a card Control
    // invented mid-game would get nothing. See App\Support\Runs\RollModifiers.
    const [extraDice, setExtraDice] = useState('');
    const [dieFaces, setDieFaces] = useState('');
    const [reroll, setReroll] = useState(false);
    const [bumps, setBumps] = useState('');
    const { busy, refusal, post } = useRunAction();

    return (
        <section className="flex flex-col gap-4 rounded-md border p-3">
            <h3 className="font-medium">Run Leader</h3>

            <Refusal message={refusal} />

            {run.step === 'activate' && !run.card?.active && (
                <p className="text-sm text-muted-foreground">
                    Waiting on Security to switch the card on — or to leave it
                    off, in which case you walk straight past it.
                </p>
            )}

            {run.step === 'activate' && run.card?.active && (
                <p className="text-sm text-muted-foreground">
                    The card is on. Waiting on Security to read its strength off
                    it and roll — you throw yours against what they got.
                </p>
            )}

            {run.step === 'challenge' && (
                <form
                    className="flex flex-wrap items-end gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        post(challenge(run.id), {
                            skill,
                            extra_dice:
                                extraDice === '' ? 0 : Number(extraDice),
                            die_faces:
                                dieFaces === '' ? null : Number(dieFaces),
                            reroll_failures: reroll,
                            bumps: bumps === '' ? 0 : Number(bumps),
                        });
                    }}
                >
                    <div className="flex flex-col gap-1">
                        <Label htmlFor={`skill-${run.id}`}>Skill</Label>
                        <select
                            id={`skill-${run.id}`}
                            className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            value={skill}
                            onChange={(event) =>
                                setSkill(event.target.value as 'brawn' | 'hack')
                            }
                        >
                            <option value="brawn">Brawn</option>
                            <option value="hack">Hack</option>
                        </select>
                    </div>
                    <Button type="submit" size="sm" disabled={busy}>
                        Roll
                    </Button>
                    <DicePoolReadout run={run} skill={skill} />

                    {/* The dice are thrown on the server, so a card that says
                    "+2 Brute" or "roll d8s" has nowhere else to act. Declared
                    rather than parsed: the player is holding the card. */}
                    <fieldset className="flex w-full flex-wrap items-end gap-2 border-t pt-2">
                        <legend className="sr-only">
                            What your Equipment is doing to this roll
                        </legend>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor={`extra-dice-${run.id}`}>
                                Extra dice
                            </Label>
                            <Input
                                id={`extra-dice-${run.id}`}
                                type="number"
                                min={-9}
                                max={9}
                                className="w-24"
                                value={extraDice}
                                placeholder="0"
                                onChange={(event) =>
                                    setExtraDice(event.target.value)
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor={`die-faces-${run.id}`}>
                                Die size
                            </Label>
                            <select
                                id={`die-faces-${run.id}`}
                                className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                value={dieFaces}
                                onChange={(event) =>
                                    setDieFaces(event.target.value)
                                }
                            >
                                <option value="">As normal</option>
                                {[4, 6, 8, 10, 12].map((faces) => (
                                    <option key={faces} value={faces}>
                                        d{faces}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <label className="flex items-center gap-2 pb-2 text-sm">
                            <input
                                type="checkbox"
                                checked={reroll}
                                onChange={(event) =>
                                    setReroll(event.target.checked)
                                }
                            />
                            Reroll failures once
                        </label>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor={`bumps-${run.id}`}>
                                +1 on a die
                            </Label>
                            <Input
                                id={`bumps-${run.id}`}
                                type="number"
                                min={0}
                                className="w-24"
                                value={bumps}
                                placeholder="0"
                                onChange={(event) =>
                                    setBumps(event.target.value)
                                }
                            />
                        </div>
                    </fieldset>

                    <p className="w-full text-xs text-muted-foreground">
                        Security has already thrown theirs. The dice are rolled
                        on the server and every face is kept. Anything your
                        Equipment grants goes in the three boxes above — the
                        cards are not read for you. A reroll throws the misses
                        again and a &ldquo;+1 on a die&rdquo; lands after that,
                        on whichever die it can turn into a success; a card that
                        raises a <em>skill</em> goes on your own desk below
                        instead.
                    </p>
                </form>
            )}

            {run.step === 'consequence' && (
                <ConsequenceToTake run={run} busy={busy} post={post} />
            )}

            {run.step === 'breather' && (
                <div className="flex flex-col gap-2">
                    <p className="text-sm">
                        {run.retry_pending
                            ? 'A Retry is waiting: move on and you face the same card again.'
                            : 'Move on, and you find out whether there was another card.'}
                    </p>
                    <Button
                        size="sm"
                        disabled={busy}
                        className="self-start"
                        onClick={() => post(advance(run.id))}
                    >
                        {run.retry_pending ? 'Face it again' : 'Move on'}
                    </Button>
                </div>
            )}
        </section>
    );
}

/**
 * The run as the rest of the group sees it.
 *
 * A Runner who is not the Leader still has skin in this: rulebook 3.4.2 has
 * the consequence taken by "a single player, decided by the Run Leader", and
 * that player is as likely to be them as anybody. So what Security has marked
 * is on their screen too — at the table the card is face up and they can read
 * it, and being told only "watching" while somebody decides who takes two
 * Wounds is worse than useless.
 *
 * Read-only: the Leader decides and this says what is being decided.
 */
function Watching({ run }: { run: RunView }) {
    const slip = run.consequence;
    const leader = run.participants.find((runner) => runner.is_leader);

    return (
        <section className="flex flex-col gap-2 rounded-md border p-3">
            {run.step === 'consequence' && slip.marked ? (
                <>
                    <p className="text-sm">
                        Security marked the card:{' '}
                        <span className="font-medium">{slip.description}</span>.
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {leader === undefined ? 'The Run Leader' : leader.name}{' '}
                        is deciding who takes it
                        {slip.ends_the_run
                            ? `, and whether to take the End the Run or swap it for ${slip.ignore_cost} Wound${slip.ignore_cost === 1 ? '' : 's'}, ${slip.ignore_cost} Tag${slip.ignore_cost === 1 ? '' : 's'} and ${slip.ignore_cost} Alert${slip.ignore_cost === 1 ? '' : 's'} and another go at the card`
                            : ''}
                        .
                    </p>
                </>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {run.step === 'consequence'
                        ? 'You did not break it. Waiting on Security to write down what the card does to you.'
                        : 'Watching. The Run Leader rolls and moves the group on; you may still walk away at a Breather.'}
                </p>
            )}
        </section>
    );
}

/**
 * What the Runners are taking, and the one decision left on it.
 *
 * The Run Leader does not retype the card here: Security has read it off the
 * card and written it down, and rulebook 3.4.2 gives the Leader exactly one
 * job — "the consequence must be taken by a single player, decided by the Run
 * Leader". So this reads the slip and asks who.
 *
 * An End the Run on the slip is the other question, and it is a real choice
 * rather than a way out: stop the run, or take Wounds and Tags instead and go
 * round again. The price climbs — "for each 'End the Run' that you have
 * ignored (including this one), you take 1 Wound, 1 Tag and 1 Alert" — so the
 * second one costs 2 of each, and both buttons say what they cost before they
 * are pressed. The Alert is worst of the three: it makes every card still
 * ahead of them harder.
 */
function ConsequenceToTake({
    run,
    busy,
    post,
}: {
    run: RunView;
    busy: boolean;
    post: (
        url: ReturnType<typeof activate> | string,
        data?: RunPayload,
    ) => void;
}) {
    const slip = run.consequence;
    const active = run.participants.filter((runner) => !runner.left);
    const [taker, setTaker] = useState('');

    if (!slip.marked) {
        return (
            <p className="text-sm text-muted-foreground">
                You did not break it. Waiting on Security to write down what the
                card does to you.
            </p>
        );
    }

    const apply = (ignore: boolean) =>
        post(storeConsequence(run.id), {
            character_id: taker === '' ? null : Number(taker),
            ignore_end_the_run: ignore,
        });

    const cost = slip.ignore_cost;
    const each = `${cost} Wound${cost === 1 ? '' : 's'}, ${cost} Tag${cost === 1 ? '' : 's'} and ${cost} Alert${cost === 1 ? '' : 's'}`;

    return (
        <div className="flex flex-col gap-3">
            <p className="text-sm">
                Security marked the card:{' '}
                <span className="font-medium">{slip.description}</span>. One
                Runner takes the lot, and that is your call.
            </p>

            <div className="flex flex-wrap items-end gap-2">
                <div className="flex flex-col gap-1">
                    <Label htmlFor={`taker-${run.id}`}>Taken by</Label>
                    <select
                        id={`taker-${run.id}`}
                        className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                        value={taker}
                        onChange={(event) => setTaker(event.target.value)}
                    >
                        <option value="">Nobody (Alerts only)</option>
                        {active.map((runner) => (
                            <option
                                key={runner.character_id}
                                value={runner.character_id}
                            >
                                {runner.name} ({runner.wounds}/{runner.body}{' '}
                                Wounds)
                            </option>
                        ))}
                    </select>
                </div>

                {!slip.ends_the_run && (
                    <Button
                        size="sm"
                        disabled={busy || slip.is_empty}
                        onClick={() => apply(false)}
                    >
                        {slip.is_empty
                            ? 'Nothing to take'
                            : `Take ${slip.description}`}
                    </Button>
                )}
            </div>

            {slip.ends_the_run && (
                <div className="flex flex-col gap-2 border-t pt-3">
                    <p className="text-sm">
                        It ends the run — unless you would rather take{' '}
                        <span className="font-medium">{each}</span> and face the
                        card again. That price goes up by one every time you
                        shrug one off.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant="destructive"
                            disabled={busy}
                            onClick={() => apply(false)}
                        >
                            Take it — the run is over
                        </Button>
                        <Button
                            size="sm"
                            variant="secondary"
                            disabled={busy || taker === ''}
                            onClick={() => apply(true)}
                        >
                            Ignore it — {each}, then retry
                        </Button>
                    </div>
                    {taker === '' && (
                        <p className="text-xs text-muted-foreground">
                            Name the Runner taking the Wounds and Tags before
                            you can ignore it.
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

/**
 * Who is on the run, in the order the Breather goes round.
 *
 * Every Runner gets their own Leave button rather than the Leader deciding for
 * them: "Each Runner, starting with the Run Leader, may take this opportunity
 * to leave", and it is the one decision on this page that is nobody else's.
 */
function Party({
    run,
    active,
}: {
    run: RunView;
    active: RunParticipantView[];
}) {
    const { busy, refusal, post } = useRunAction();
    const [successor, setSuccessor] = useState('');

    return (
        <section className="flex flex-col gap-2">
            <h3 className="font-medium">
                The group{' '}
                <span className="text-sm font-normal text-muted-foreground">
                    ({active.length} still in
                    {active.length === run.participants.length
                        ? ''
                        : ` of ${run.participants.length}`}
                    )
                </span>
            </h3>

            <Refusal message={refusal} />
            <ul className="flex flex-col gap-2">
                {run.participants.map((runner) => (
                    <li
                        key={runner.id}
                        className={`flex flex-wrap items-center justify-between gap-2 rounded-md border p-2 ${
                            runner.left ? 'opacity-60' : ''
                        }`}
                    >
                        <div className="flex items-center gap-2">
                            {runner.gang !== null && (
                                <FactionBadge
                                    faction={runner.gang}
                                    size="small"
                                />
                            )}
                            <span className="font-medium">{runner.name}</span>
                            {runner.is_leader && (
                                <Badge variant="outline">Run Leader</Badge>
                            )}
                            {runner.left && (
                                <Badge variant="secondary">
                                    {runner.left_reason}
                                </Badge>
                            )}
                        </div>
                        <div className="flex items-center gap-3 text-sm text-muted-foreground tabular-nums">
                            <span title="Brawn">B {runner.brawn}</span>
                            <span title="Hack">H {runner.hack}</span>
                            <span
                                title="Wounds against Body"
                                className={
                                    runner.wounds > 0
                                        ? 'text-amber-600 dark:text-amber-400'
                                        : undefined
                                }
                            >
                                {runner.wounds}/{runner.body} W
                            </span>
                            <span title="Tags">{runner.tags} T</span>
                            {!runner.left &&
                                run.status === 'running' &&
                                (runner.is_yours || run.can_lead) && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        disabled={busy}
                                        onClick={() =>
                                            post(leave(run.id), {
                                                character_id:
                                                    runner.character_id,
                                                new_leader_character_id:
                                                    successor === ''
                                                        ? null
                                                        : Number(successor),
                                            })
                                        }
                                    >
                                        Leave
                                    </Button>
                                )}
                        </div>
                    </li>
                ))}
            </ul>

            {run.can_lead && active.length > 1 && (
                <div className="flex flex-wrap items-end gap-2">
                    <div className="flex flex-col gap-1">
                        <Label htmlFor={`successor-${run.id}`}>
                            If the Leader leaves, hand over to
                        </Label>
                        <select
                            id={`successor-${run.id}`}
                            className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            value={successor}
                            onChange={(event) =>
                                setSuccessor(event.target.value)
                            }
                        >
                            <option value="">
                                Nobody — pick one at random
                            </option>
                            {active
                                .filter(
                                    (runner) =>
                                        runner.character_id !==
                                        run.leader_character_id,
                                )
                                .map((runner) => (
                                    <option
                                        key={runner.character_id}
                                        value={runner.character_id}
                                    >
                                        {runner.name}
                                    </option>
                                ))}
                        </select>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        The rulebook wants this decided democratically, and
                        rolls for it when it cannot be.
                    </p>
                </div>
            )}
        </section>
    );
}

/**
 * The log, newest last, with the dice that decided each line.
 *
 * Shown to both sides in full, because this is the record that settles an
 * argument and a log one side could not read would settle nothing. The faces
 * are here rather than a verdict for the same reason: a player who can see six
 * d8s that came up 1,2,2,3,4,4 will accept losing the check.
 */
function RunLog({ run }: { run: RunView }) {
    const [open, setOpen] = useState(false);
    const lines = open ? run.log : run.log.slice(-4);

    return (
        <section className="flex flex-col gap-2">
            <div className="flex items-baseline justify-between gap-2">
                <h3 className="font-medium">What happened</h3>
                {run.log.length > 4 && (
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => setOpen((was) => !was)}
                    >
                        {open ? 'Show the last few' : `All ${run.log.length}`}
                    </Button>
                )}
            </div>
            <ol className="flex flex-col gap-2">
                {lines.map((event) => (
                    <li
                        key={event.id}
                        className="rounded-md border p-2 text-sm"
                    >
                        <div className="flex flex-wrap items-baseline gap-2">
                            {event.pass > 0 && (
                                <span className="text-xs text-muted-foreground">
                                    Pass {event.pass}
                                </span>
                            )}
                            <span>{event.description}</span>
                        </div>
                        {event.rolls.length > 0 && (
                            <ul className="mt-1 flex flex-col gap-0.5 font-mono text-xs text-muted-foreground">
                                {event.rolls.map((roll, index) => (
                                    <li key={index}>
                                        {roll.roller_label}: {roll.readout}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </li>
                ))}
                {run.log.length === 0 && (
                    <li className="text-sm text-muted-foreground">
                        Nothing yet.
                    </li>
                )}
            </ol>
        </section>
    );
}

function ordinal(position: number): string {
    const suffixes = ['th', 'st', 'nd', 'rd'];
    const remainder = position % 100;

    return (
        position +
        (suffixes[(remainder - 20) % 10] ?? suffixes[remainder] ?? suffixes[0])
    );
}
