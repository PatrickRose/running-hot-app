import { Form, Head, router, usePoll } from '@inertiajs/react';
import { FacilityPanel } from '@/components/facility-panel';
import { FacilityTypeCatalogue } from '@/components/facility-type-catalogue';
import { FactionBadge } from '@/components/faction-badge';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { ProtectionCardCatalogue } from '@/components/protection-card-catalogue';
import { ProtectionCardHoldings } from '@/components/protection-card-holdings';
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
    CorporationCardHoldings,
    CorporationFacilities,
    FacilityListState,
    FacilitySummary,
    FacilityTypeSummary,
    GameSummary,
    ProtectionCardSummary,
} from '@/types/game';

type Props = {
    game: GameSummary;
    facilities: CorporationFacilities[];
    /** The Facilities belonging to nobody: Control's own, for the Runners to hit. */
    plotFacilities: FacilitySummary[];
    facilityTypes: FacilityTypeSummary[];
    protectionCards: ProtectionCardSummary[];
    cardHoldings: CorporationCardHoldings[];
    facilityList: FacilityListState;
};

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

export default function ControlFacilities({
    game,
    facilities,
    plotFacilities,
    facilityTypes,
    protectionCards,
    cardHoldings,
    facilityList,
}: Props) {
    // Security is placing cards while Control watches, and the clock moving is
    // what opens a Facility, so this page has to stay live like the panel does.
    usePoll(5000, {
        only: [
            'game',
            'facilities',
            'plotFacilities',
            'facilityTypes',
            'protectionCards',
            'cardHoldings',
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
                            starting Facilities go in. Leave the cost blank to
                            charge the type sheet's price, or put 0 to build it
                            free. CEOs can requisition their own at the sheet's
                            price from the Facilities page.
                            <br />
                            Choosing nobody builds a Plot Facility: yours rather
                            than a Corporation's, for the Runners to run
                            against. It opens at once, costs nothing, takes as
                            many Protection Cards as you care to install and
                            stores no technologies.
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
                                            defaultValue=""
                                        >
                                            {facilities.map((corporation) => (
                                                <option
                                                    key={corporation.id}
                                                    value={corporation.id}
                                                >
                                                    {corporation.name}
                                                </option>
                                            ))}
                                            {/*
                                             * Nobody: a Plot Facility. It is
                                             * built at once and costs nothing,
                                             * so the cost and When boxes are
                                             * ignored for one.
                                             */}
                                            <option value="">
                                                Nobody — Plot Facility
                                            </option>
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
                                                    {type.name} —{' '}
                                                    {type.build_cost} Credits
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
                                        {/* Blank charges the type
                                            sheet's price; 0 builds it free.
                                            Players always pay the sheet. */}
                                        <Input
                                            id="facility-cost"
                                            name="cost"
                                            type="number"
                                            min={0}
                                            placeholder="Type sheet price"
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
                            <CardTitle className="flex items-center gap-2">
                                <FactionBadge faction={corporation} />
                                {corporation.name}
                            </CardTitle>
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

                {plotFacilities.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Plot Facilities</CardTitle>
                            <CardDescription>
                                Yours. No Corporation owns them, so nobody in
                                the roster defends one and nothing about them
                                costs anybody Credits — install as many
                                Protection Cards as the story needs, arrange
                                them freely, and set whatever budget the
                                Facility should have to spend on a Run.
                                <br />
                                Players see them on the Facility list as{' '}
                                <strong>Independent</strong>, alongside the
                                Corporations', so a group can name one as a
                                target without being told which buildings are
                                yours. They store no technologies.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            {plotFacilities.map((facility) => (
                                <FacilityPanel
                                    key={facility.id}
                                    gameId={game.id}
                                    facility={facility}
                                    catalogue={protectionCards}
                                    currentTurn={currentTurn}
                                    discordReady={discordReady}
                                />
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Cards each Corporation owns</CardTitle>
                        <CardDescription>
                            One copy of a card may defend one Facility, so the
                            count here is what caps how far a card can stretch.
                            Installing takes a copy out of the hand and removing
                            one puts it back.
                            <br />
                            Set outright: the shop, the auctions, the research
                            grants and any trade between Security players all
                            happen at the table, so this is where Control writes
                            down where they ended up.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ProtectionCardHoldings
                            gameId={game.id}
                            holdings={cardHoldings}
                            cards={protectionCards}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Protection Card catalogue</CardTitle>
                        <CardDescription>
                            What Security can buy, what is rumoured, and what
                            only research will unlock. Installing costs no
                            Credits — it costs a copy of the card. The list
                            carries no prices: the shop is its own piece of
                            work, so a cost here is only what Control chooses to
                            charge.
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
