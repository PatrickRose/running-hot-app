import { Form, Head, router, usePoll } from '@inertiajs/react';
import { FacilityPanel } from '@/components/facility-panel';
import { FacilityTypeCatalogue } from '@/components/facility-type-catalogue';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { ProtectionCardCatalogue } from '@/components/protection-card-catalogue';
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
import { publishList, store } from '@/routes/control/facilities';
import { index, show } from '@/routes/control/games';
import type {
    CorporationFacilities,
    FacilityListState,
    FacilityTypeSummary,
    GameSummary,
    ProtectionCardSummary,
} from '@/types/game';

type Props = {
    game: GameSummary;
    facilities: CorporationFacilities[];
    facilityTypes: FacilityTypeSummary[];
    protectionCards: ProtectionCardSummary[];
    facilityList: FacilityListState;
};

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

export default function ControlFacilities({
    game,
    facilities,
    facilityTypes,
    protectionCards,
    facilityList,
}: Props) {
    // Security is placing cards while Control watches, and the clock moving is
    // what opens a Facility, so this page has to stay live like the panel does.
    usePoll(5000, {
        only: [
            'game',
            'facilities',
            'facilityTypes',
            'protectionCards',
            'facilityList',
        ],
    });

    const currentTurn = game.phase?.turn ?? null;

    // A Facility's channels need both a server to build them in and a bot to
    // build them with, so without either there is nothing to report or fix.
    const discordReady =
        game.discord.guild_id !== null && game.discord.bot_configured;

    return (
        <>
            <Head title={`Facilities — ${game.name}`} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Facility Defence"
                        description={
                            currentTurn === null
                                ? 'The game has not started.'
                                : `Turn ${currentTurn} · ${game.phase?.type_label}`
                        }
                    />
                    <Button
                        variant="ghost"
                        onClick={() => router.get(show.url({ game: game.id }))}
                    >
                        Back to the game
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>#facility-list</CardTitle>
                        <CardDescription>
                            Who owns what, posted to the game's Discord as an
                            embed that everyone can read. Facility names and
                            types only — what is installed in them is Secret,
                            and the channel is visible to Runners.
                            <br />
                            Published once and then rewritten in place, so
                            nobody reads a superseded list. Once it is up, the
                            turn clock keeps it current.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-3">
                        <Button
                            disabled={
                                !facilityList.channel_exists ||
                                !facilityList.bot_configured
                            }
                            onClick={() =>
                                router.post(
                                    publishList.url({ game: game.id }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {facilityList.published
                                ? 'Update the list'
                                : 'Publish the list'}
                        </Button>

                        {!facilityList.channel_exists && (
                            <p className="text-sm text-muted-foreground">
                                Provision the game's Discord server first.
                            </p>
                        )}
                        {facilityList.channel_exists &&
                            !facilityList.bot_configured && (
                                <p className="text-sm text-muted-foreground">
                                    Needs DISCORD_BOT_TOKEN — the announcement
                                    webhook can only post to its own channel.
                                </p>
                            )}
                        {facilityList.published && (
                            <p className="text-sm text-muted-foreground">
                                Already posted; publishing again rewrites it.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Facility types</CardTitle>
                        <CardDescription>
                            More Facility types may be researched during the
                            game, so this list is not fixed. A type that grants
                            card slots widens every Facility its Corporation
                            owns; one that grants tech storage raises what every
                            Facility can hold.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <FacilityTypeCatalogue
                            gameId={game.id}
                            types={facilityTypes}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Build a Facility</CardTitle>
                        <CardDescription>
                            A requisition is raised during Setup and opens next
                            turn. Build now is the override, and is how a game's
                            starting Facilities go in.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...store.form({ game: game.id })}
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="facility-corporation">
                                            Corporation
                                        </Label>
                                        <select
                                            id="facility-corporation"
                                            name="corporation_id"
                                            className={SELECT_CLASS}
                                            required
                                        >
                                            {facilities.map((corporation) => (
                                                <option
                                                    key={corporation.id}
                                                    value={corporation.id}
                                                >
                                                    {corporation.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.corporation_id}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="facility-type">
                                            Type
                                        </Label>
                                        <select
                                            id="facility-type"
                                            name="facility_type_id"
                                            className={SELECT_CLASS}
                                            required
                                        >
                                            {facilityTypes.map((type) => (
                                                <option
                                                    key={type.id}
                                                    value={type.id}
                                                >
                                                    {type.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.facility_type_id}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="facility-name">
                                            Name
                                        </Label>
                                        <Input
                                            id="facility-name"
                                            name="name"
                                            required
                                            placeholder="Attercliffe Yard"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="facility-cost">
                                            Build cost
                                        </Label>
                                        <Input
                                            id="facility-cost"
                                            name="cost"
                                            type="number"
                                            min={0}
                                            defaultValue={0}
                                        />
                                        <InputError message={errors.cost} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="facility-mode">
                                            When
                                        </Label>
                                        <select
                                            id="facility-mode"
                                            name="mode"
                                            className={SELECT_CLASS}
                                            defaultValue="requisition"
                                        >
                                            <option value="requisition">
                                                Requisition (opens next turn)
                                            </option>
                                            <option value="immediate">
                                                Build now
                                            </option>
                                        </select>
                                        <InputError message={errors.mode} />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Build
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {facilities.map((corporation) => (
                    <Card key={corporation.id}>
                        <CardHeader>
                            <CardTitle>{corporation.name}</CardTitle>
                            <CardDescription>
                                {corporation.credits} Credits &middot;{' '}
                                {corporation.physical_slots} physical and{' '}
                                {corporation.cyber_slots} cyber slots per
                                Facility &middot;{' '}
                                {corporation.technology_capacity_per_facility}{' '}
                                technologies storable per Facility
                                {corporation.card_move_discount > 0 && (
                                    <>
                                        {' '}
                                        &middot;{' '}
                                        {corporation.card_move_discount} Credit
                                        discount on moving cards
                                    </>
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {corporation.facilities.map((facility) => (
                                <FacilityPanel
                                    key={facility.id}
                                    gameId={game.id}
                                    facility={facility}
                                    catalogue={protectionCards}
                                    currentTurn={currentTurn}
                                    discordReady={discordReady}
                                />
                            ))}
                            {corporation.facilities.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No Facilities yet.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                ))}

                {facilities.length === 0 && (
                    <Card>
                        <CardContent className="py-6 text-muted-foreground">
                            This game has no Corporations, so there is nothing
                            to defend yet.
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Protection Card catalogue</CardTitle>
                        <CardDescription>
                            What Security can buy, what is rumoured, and what
                            only research will unlock. Installing is free; the
                            cost here is what the shop charges.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ProtectionCardCatalogue
                            gameId={game.id}
                            cards={protectionCards}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ControlFacilities.layout = {
    breadcrumbs: [
        { title: 'Control', href: index() },
        { title: 'Facilities', href: index() },
    ],
};
