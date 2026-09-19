import { Head, usePoll } from '@inertiajs/react';
import Heading from '@/components/heading';
import { ShopCounter } from '@/components/shop-counter';
import { dashboard } from '@/routes';
import type { GameSummary, ShopBoard } from '@/types/game';

type Props = {
    game: GameSummary | null;
    shop: ShopBoard | null;
};

/**
 * The shop (rulebook 3.3.3, and 2.1 for the Runners' market).
 *
 * Two counters on one page, and which of them you are shown is the seat you
 * hold rather than a tab you pick. 3.3.3 hands the Protection Card list to the
 * Security players and 2.1 hands the market to the Runners; somebody holding
 * seats on both sides gets both, because a user claims characters rather than a
 * side.
 *
 * It polls, for the reason every other live screen does: Control announces a
 * card mid-phase, a rumoured line comes on sale, and somebody else takes the
 * last Angel. None of that is anything this browser did, and "first come first
 * served" is not a rule you can play to against a stale page.
 */
export default function Shop({ game, shop }: Props) {
    usePoll(5000, { only: ['game', 'shop'] });

    if (game === null || shop === null) {
        return (
            <>
                <Head title="Shop" />
                <div className="p-4">
                    <Heading title="Shop" description="No game is running." />
                </div>
            </>
        );
    }

    const nothingForYou = shop.protection === null && shop.equipment === null;

    return (
        <>
            <Head title="Shop" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Shop"
                    description={
                        shop.open
                            ? 'Open. Bought first come, first served.'
                            : `${shop.phase ?? 'The game'} — the shop opens during Setup.`
                    }
                />

                {shop.is_control && (
                    <p className="text-sm text-muted-foreground">
                        You are Control, so you see both counters whoever you
                        are sitting with. Putting cards out, repricing them and
                        selling to somebody who phoned it in all live on the
                        game's Control panel.
                    </p>
                )}

                {shop.protection !== null && (
                    <ShopCounter
                        title="Corporation shop"
                        description="Protection Cards, bought by your Security player out of the Corporation's Credits."
                        counter={shop.protection}
                        shape="landscape"
                        open={shop.open}
                        readOnlyReason={
                            shop.protection.buyers.length === 0
                                ? 'Your Security player does the buying. You are reading the list.'
                                : null
                        }
                    />
                )}

                {shop.equipment !== null && (
                    <ShopCounter
                        title="The market"
                        description="Equipment, bought out of your own Credits. You may also buy from other players, which happens at the table."
                        counter={shop.equipment}
                        shape="portrait"
                        open={shop.open}
                        readOnlyReason={null}
                    />
                )}

                {nothingForYou && (
                    <p className="text-sm text-muted-foreground">
                        The Corporation shop is handed to Security players and
                        the market to the Runners, so there is no counter here
                        for the seat you hold.
                    </p>
                )}
            </div>
        </>
    );
}

Shop.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Shop', href: dashboard() },
    ],
};
