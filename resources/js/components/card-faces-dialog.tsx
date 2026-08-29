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
 * A card with one face shows that one and says nothing about the other, which
 * is every card that is not a technology - and also a fair few that are, since
 * the artwork does not run to both faces of everything. Either face may be the
 * one that is missing, so the thumbnail is whichever exists rather than always
 * the front.
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
    frontPath?: string | null;
    backPath?: string | null;
}) {
    const [failed, setFailed] = useState<string[]>([]);

    const faces = [
        { label: 'Front', path: frontPath },
        { label: 'Back', path: backPath },
    ].filter(
        (face): face is { label: string; path: string } =>
            Boolean(face.path) && !failed.includes(face.path as string),
    );

    if (faces.length === 0) {
        return null;
    }

    // Whichever face there is. Sixteen technologies have a back on record and
    // no front, so always reaching for the front would hide them entirely.
    const [thumbnail] = faces;
    const bothFaces = faces.length > 1;

    return (
        <Dialog>
            <DialogTrigger className="rounded ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none">
                <img
                    src={thumbnail.path}
                    alt=""
                    loading="lazy"
                    decoding="async"
                    onError={() => setFailed((was) => [...was, thumbnail.path])}
                    // Held to a height rather than a width, so a landscape
                    // research card and a portrait Equipment card still line
                    // the rows up.
                    className="h-12 w-auto rounded border bg-muted transition-opacity hover:opacity-80"
                />
                <span className="sr-only">
                    {bothFaces
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
                        {bothFaces
                            ? 'Printed proposal side up and flipped over once it has been researched. Both faces are public.'
                            : `Only the ${thumbnail.label.toLowerCase()} of this card has been drawn.`}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-wrap justify-center gap-4">
                    {faces.map((face) => (
                        <Face
                            key={face.label}
                            path={face.path}
                            label={face.label}
                            onError={() =>
                                setFailed((was) => [...was, face.path])
                            }
                        />
                    ))}
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
