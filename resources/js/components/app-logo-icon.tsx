import { cn } from '@/lib/utils';

/**
 * The game's own mark.
 *
 * It is a photograph of a neon sign rather than a line drawing, so it carries
 * its own dark ground and cannot be recoloured by the page it sits on - which
 * is why it is drawn as a rounded tile like an app icon rather than as a glyph
 * taking `fill-current`. That reads correctly on both themes: on the dark one
 * it melts into the page, and on the light one it sits on it like a sticker.
 *
 * It lives under `public/` because it is fetched by an `img` at runtime, the
 * same reasoning that puts the card artwork there and the icon font under
 * `resources/`.
 */
export default function AppLogoIcon({ className }: { className?: string }) {
    return (
        <img
            src="/images/running-hot.webp"
            alt=""
            aria-hidden="true"
            className={cn('rounded-md object-cover', className)}
        />
    );
}
