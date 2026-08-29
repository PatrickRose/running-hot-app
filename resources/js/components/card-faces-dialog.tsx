import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

/**
 * A card's artwork as a thumbnail, opening both of its faces full size.
 *
 * Research cards are printed proposal side up and flipped over once researched
 * (rulebook 3.2.2), so they have two faces and both are public - a technology is
 * not secret, only which Facility is storing it is. So this shows the two side
 * by side rather than making anyone flip between them.
 *
 * A card with one face shows that one and says nothing about a back, which is
 * every card that is not a technology.
 *
 * The thumbnail is a real button, unlike the tooltips elsewhere on this page: it
 * opens something, so it has to be reachable from the keyboard, and there is one
 * per row rather than one per figure in a stack.
 */
export function CardFacesDialog({
    name,
    code,
    frontPath,
    backPath,
}: {
    name: string;
    code?: string | null;
    frontPath: string;
    backPath?: string | null;
}) {
    const [frontFailed, setFrontFailed] = useState(false);
    const [backFailed, setBackFailed] = useState(false);

    if (frontFailed) {
        return null;
    }

    const showBack = Boolean(backPath) && !backFailed;

    return (
        <Dialog>
            <DialogTrigger className="rounded ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none">
                <img
                    src={frontPath}
                    alt=""
                    loading="lazy"
                    decoding="async"
                    onError={() => setFrontFailed(true)}
                    className="aspect-[5/7] w-12 rounded border bg-muted object-cover transition-opacity hover:opacity-80"
                />
                <span className="sr-only">
                    {showBack
                        ? `Show both faces of ${name}`
                        : `Show ${name} full size`}
                </span>
            </DialogTrigger>

            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {name}
                        {code ? (
                            <span className="ml-2 font-mono text-xs font-normal text-muted-foreground">
                                {code}
                            </span>
                        ) : null}
                    </DialogTitle>
                    <DialogDescription>
                        {showBack
                            ? 'Printed proposal side up and flipped over once it has been researched. Both faces are public.'
                            : 'The card as it is printed.'}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-wrap justify-center gap-4">
                    <Face path={frontPath} label="Front" />
                    {showBack ? (
                        <Face
                            path={backPath as string}
                            label="Back"
                            onError={() => setBackFailed(true)}
                        />
                    ) : null}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function Face({
    path,
    label,
    onError,
}: {
    path: string;
    label: string;
    onError?: () => void;
}) {
    return (
        <figure className="flex flex-col items-center gap-2">
            <img
                src={path}
                alt={label}
                onError={onError}
                className="max-h-[70vh] w-auto max-w-full rounded-lg border bg-muted"
            />
            <figcaption className="text-xs text-muted-foreground">
                {label}
            </figcaption>
        </figure>
    );
}
