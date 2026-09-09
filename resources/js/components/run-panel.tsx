import { router } from '@inertiajs/react';
import { useState } from 'react';
import { CardFace } from '@/components/card-face';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    activate,
    advance,
    begin,
    boost,
    challenge,
    charge,
    ignoreEnd,
    leave,
} from '@/routes/runs';
import { trigger as triggerAlerts } from '@/routes/runs/alerts';
import { store as storeConsequence } from '@/routes/runs/consequences';
import type {
    RunCard,
    RunConsequenceEffect,
    RunParticipantView,
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
type RunPayload = Record<string, string | number | boolean | null>;

const EFFECT_LABELS: Record<RunConsequenceEffect, string> = {
    alert: 'Alert',
    tag: 'Tag',
    wound: 'Wound',
    retry: 'Retry',
    end_the_run: 'End the Run',
};

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

    return (
        <Card>
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
                    </div>
                </div>
            </CardHeader>

            <CardContent className="flex flex-col gap-6">
                <RunGauges run={run} />

                {run.status === 'submitted' && <NotInYet run={run} />}

                {run.status === 'running' && (
                    <>
                        <StepTrack run={run} />
                        <div className="grid gap-6 lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]">
                            <FacingCard run={run} />
                            <div className="flex flex-col gap-4">
                                {run.can_defend && <SecurityDesk run={run} />}
                                {run.can_lead && <LeaderDesk run={run} />}
                                {!run.can_lead && !run.can_defend && (
                                    <p className="text-sm text-muted-foreground">
                                        Watching. The Run Leader rolls and moves
                                        the group on; you may still walk away at
                                        a Breather.
                                    </p>
                                )}
                            </div>
                        </div>
                    </>
                )}

                <Party run={run} active={active} />
                <RunLog run={run} />
            </CardContent>
        </Card>
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
    const [submitting, setSubmitting] = useState(false);
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
                setSubmitting(true);
                router.post(
                    begin(run.id),
                    override === ''
                        ? {}
                        : { group_alert_override: Number(override) },
                    {
                        onFinish: () => setSubmitting(false),
                        preserveScroll: true,
                    },
                );
            }}
        >
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
            <Button type="submit" disabled={submitting} className="self-start">
                Go in
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
    const [alerts, setAlerts] = useState('');
    const [boosts, setBoosts] = useState('1');
    const [busy, setBusy] = useState(false);

    if (card === null) {
        return null;
    }

    const spend = (): RunPayload =>
        alerts === '' ? {} : { alerts_to_spend: Number(alerts) };
    const post = (url: ReturnType<typeof activate>, data: RunPayload = {}) => {
        setBusy(true);
        router.post(url, data, {
            onFinish: () => setBusy(false),
            preserveScroll: true,
        });
    };

    return (
        <section className="flex flex-col gap-3 rounded-md border p-3">
            <header className="flex flex-wrap items-baseline justify-between gap-2">
                <h3 className="font-medium">Security</h3>
                {budget !== null && (
                    <p className="text-sm text-muted-foreground">
                        {budget.left} of {budget.placed} Credits left
                        {budget.directed ? ' · Directing here' : ''}
                    </p>
                )}
            </header>

            {!card.settled && (
                <div className="flex flex-col gap-2">
                    <p className="text-sm">
                        {card.activation_cost === null
                            ? 'Switch it on.'
                            : `Switching it on costs ${card.activation_cost}.`}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            disabled={busy}
                            onClick={() =>
                                post(activate(run.id), {
                                    activating: true,
                                    ...spend(),
                                })
                            }
                        >
                            Activate
                        </Button>
                        {budget?.directed && (
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
                        )}
                    </div>
                    {!budget?.directed && (
                        <p className="text-xs text-muted-foreground">
                            Only a Security player Directing Security here may
                            leave a card off. Otherwise it comes on if the
                            budget can cover it, and stays off if it cannot.
                        </p>
                    )}
                </div>
            )}

            {card.active && budget?.directed && (
                <div className="flex flex-wrap items-end gap-2">
                    <div className="flex flex-col gap-1">
                        <Label htmlFor={`boosts-${run.id}`}>Boosts</Label>
                        <Input
                            id={`boosts-${run.id}`}
                            type="number"
                            min={1}
                            max={9}
                            className="w-20"
                            value={boosts}
                            onChange={(event) => setBoosts(event.target.value)}
                        />
                    </div>
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={busy}
                        onClick={() =>
                            post(boost(run.id), {
                                times: Number(boosts) || 1,
                                ...spend(),
                            })
                        }
                    >
                        Boost (next costs {card.next_boost_cost})
                    </Button>
                    {card.charge_cost !== null &&
                        card.charge_cost !== undefined && (
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={busy}
                                onClick={() => post(charge(run.id), spend())}
                            >
                                Charge for {card.charge_cost}
                            </Button>
                        )}
                </div>
            )}

            <div className="flex flex-col gap-2 border-t pt-3">
                <div className="flex flex-col gap-1">
                    <Label htmlFor={`alerts-${run.id}`}>
                        Alerts to spend as Credits
                    </Label>
                    <Input
                        id={`alerts-${run.id}`}
                        type="number"
                        min={0}
                        max={run.alerts_available}
                        className="w-24"
                        value={alerts}
                        onChange={(event) => setAlerts(event.target.value)}
                        placeholder="0"
                    />
                    <p className="text-xs text-muted-foreground">
                        {run.alerts_available} in hand. Spending them makes
                        everything the Runners have left easier — the next point
                        of strength arrives at {run.next_alert_threshold}.
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    {(['tag', 'wound', 'retry', 'end_the_run'] as const).map(
                        (effect) => (
                            <Button
                                key={effect}
                                size="sm"
                                variant="secondary"
                                disabled={
                                    busy ||
                                    run.alerts_available <
                                        (ALERT_PRICES[effect] ?? 0)
                                }
                                onClick={() =>
                                    post(triggerAlerts(run.id), { effect })
                                }
                            >
                                {EFFECT_LABELS[effect]} · {ALERT_PRICES[effect]}{' '}
                                Alerts
                            </Button>
                        ),
                    )}
                </div>
            </div>
        </section>
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
    const [strength, setStrength] = useState('');
    const [taker, setTaker] = useState('');
    const [times, setTimes] = useState('1');
    const [busy, setBusy] = useState(false);

    const active = run.participants.filter((runner) => !runner.left);
    const post = (url: ReturnType<typeof advance>, data: RunPayload = {}) => {
        setBusy(true);
        router.post(url, data, {
            onFinish: () => setBusy(false),
            preserveScroll: true,
        });
    };

    return (
        <section className="flex flex-col gap-4 rounded-md border p-3">
            <h3 className="font-medium">Run Leader</h3>

            {run.step === 'activate' && !run.card?.active && (
                <p className="text-sm text-muted-foreground">
                    Waiting on Security to switch the card on — or to leave it
                    off, in which case you walk straight past it.
                </p>
            )}

            {run.step === 'activate' && run.card?.active && (
                <form
                    className="flex flex-wrap items-end gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        post(challenge(run.id), {
                            skill,
                            printed_strength: Number(strength) || 0,
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
                    <div className="flex flex-col gap-1">
                        <Label htmlFor={`strength-${run.id}`}>
                            Printed strength
                        </Label>
                        <Input
                            id={`strength-${run.id}`}
                            type="number"
                            min={0}
                            required
                            className="w-24"
                            value={strength}
                            onChange={(event) =>
                                setStrength(event.target.value)
                            }
                        />
                    </div>
                    <Button type="submit" size="sm" disabled={busy}>
                        Roll
                    </Button>
                    <p className="w-full text-xs text-muted-foreground">
                        Read the number off the card. The dice are thrown on the
                        server and every face is kept.
                    </p>
                </form>
            )}

            {run.step === 'consequence' && (
                <div className="flex flex-col gap-2">
                    <p className="text-sm">
                        You did not break it. Take the consequence the card
                        prints — one Runner takes it, and that is your call.
                    </p>
                    <div className="flex flex-wrap items-end gap-2">
                        <div className="flex flex-col gap-1">
                            <Label htmlFor={`taker-${run.id}`}>Taken by</Label>
                            <select
                                id={`taker-${run.id}`}
                                className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                value={taker}
                                onChange={(event) =>
                                    setTaker(event.target.value)
                                }
                            >
                                <option value="">Nobody (Alerts only)</option>
                                {active.map((runner) => (
                                    <option
                                        key={runner.character_id}
                                        value={runner.character_id}
                                    >
                                        {runner.name} ({runner.wounds}/
                                        {runner.body} Wounds)
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor={`times-${run.id}`}>How many</Label>
                            <Input
                                id={`times-${run.id}`}
                                type="number"
                                min={1}
                                max={9}
                                className="w-20"
                                value={times}
                                onChange={(event) =>
                                    setTimes(event.target.value)
                                }
                            />
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {(['alert', 'tag', 'wound', 'retry'] as const).map(
                            (effect) => (
                                <Button
                                    key={effect}
                                    size="sm"
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() =>
                                        post(storeConsequence(run.id), {
                                            effect,
                                            character_id:
                                                taker === ''
                                                    ? null
                                                    : Number(taker),
                                            times: Number(times) || 1,
                                        })
                                    }
                                >
                                    {EFFECT_LABELS[effect]}
                                </Button>
                            ),
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2 border-t pt-2">
                        <Button
                            size="sm"
                            variant="destructive"
                            disabled={busy}
                            onClick={() =>
                                post(storeConsequence(run.id), {
                                    effect: 'end_the_run',
                                })
                            }
                        >
                            End the Run
                        </Button>
                        <Button
                            size="sm"
                            variant="secondary"
                            disabled={busy || taker === ''}
                            onClick={() =>
                                post(ignoreEnd(run.id), {
                                    character_id: Number(taker),
                                })
                            }
                        >
                            Ignore it: {run.ignored_end_the_run + 1} Wound
                            {run.ignored_end_the_run + 1 === 1 ? '' : 's'}, Tags
                            and Alerts, then retry
                        </Button>
                    </div>
                </div>
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
    const [busy, setBusy] = useState(false);
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
                                        onClick={() => {
                                            setBusy(true);
                                            router.post(
                                                leave(run.id),
                                                {
                                                    character_id:
                                                        runner.character_id,
                                                    new_leader_character_id:
                                                        successor === ''
                                                            ? null
                                                            : Number(successor),
                                                },
                                                {
                                                    onFinish: () =>
                                                        setBusy(false),
                                                    preserveScroll: true,
                                                },
                                            );
                                        }}
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
