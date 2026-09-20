import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/claim';

/**
 * The way back in for somebody Control could not reach by Discord handle.
 *
 * It asks for one thing, because one thing is all Control reliably has: the
 * address the player signed up with. Matching it hands them straight to the
 * Discord sign in, which is where every seat ends up bound anyway.
 */
export default function Claim() {
    return (
        <>
            <Head title="Find my seat" />

            <Form {...store.form()} className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">
                                The email you signed up with
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                autoComplete="email"
                                placeholder="email@example.com"
                            />
                            <InputError message={errors.email} />
                            <p className="text-sm text-muted-foreground">
                                If Control has it on the roster, we will send
                                you to Discord and link your account to your
                                character.
                            </p>
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                            data-test="claim-button"
                        >
                            {processing && <Spinner />}
                            Find my seat
                        </Button>
                    </>
                )}
            </Form>

            <div className="text-center text-sm text-muted-foreground">
                Already set up? <TextLink href={login()}>Sign in</TextLink>
            </div>
        </>
    );
}

Claim.layout = {
    title: 'Find my seat',
    description:
        'Control reserves each character against a Discord handle. If yours never reached them, your email address will do instead.',
};
