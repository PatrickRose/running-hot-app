import { CheckIcon, ChevronsUpDownIcon, SearchIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

/**
 * One thing that can be picked.
 *
 * `search` is what typing matches against, and it is supplied rather than
 * derived because the useful terms are not always on screen: a Protection Card
 * is looked up by its printed code as often as by its name, and Control
 * hunting for a Runner may only remember their gang.
 */
export type PickerOption = {
    value: number;
    label: string;
    /** Drawn small beside the label — a code, a category, a team. */
    hint?: string | null;
    /** Everything typing matches, the label included. */
    search: string;
};

/**
 * A searchable picker for a list too long to scroll.
 *
 * The card catalogues are 83 Protection Cards and 74 Equipment cards, and a
 * native `select` holding either is a list nobody can find anything in — worst
 * of all on a phone, which is where half this game is played. So this is the
 * shadcn combobox: a button that opens a popover, a filter that narrows as you
 * type, and arrow keys and Enter that work from the one focused input.
 *
 * It is deliberately generic rather than a card picker. Control also picks a
 * character to sell to out of a roster of forty-odd, and that is the same
 * control with different words in it.
 */
export function SearchPicker({
    options,
    value,
    onChange,
    id,
    placeholder,
    searchPlaceholder = 'Type to search…',
    emptyMessage = 'Nothing matches that.',
    className,
}: {
    options: PickerOption[];
    /** Null is nothing picked yet, which is how both callers start. */
    value: number | null;
    onChange: (value: number) => void;
    id?: string;
    placeholder: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    className?: string;
}) {
    const [open, setOpen] = useState(false);
    const selected = options.find((option) => option.value === value) ?? null;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    // The trigger *is* the combobox as far as assistive
                    // technology is concerned; cmdk owns the listbox inside.
                    role="combobox"
                    aria-expanded={open}
                    className={cn(
                        'w-full justify-between font-normal',
                        selected === null && 'text-muted-foreground',
                        className,
                    )}
                >
                    {/* A magnifier while nothing is picked, because the
                        trigger otherwise reads as an ordinary select and
                        nobody clicks a select expecting to type. That is not
                        hypothetical: the first person to use this went looking
                        for the search and did not find it. Once something is
                        picked the icon goes, since by then the control has
                        said what it is. */}
                    {selected === null ? (
                        <SearchIcon
                            className="mr-2 size-4 shrink-0 opacity-50"
                            aria-hidden="true"
                        />
                    ) : null}
                    <span className="mr-auto truncate">
                        {selected === null ? placeholder : selected.label}
                        {selected?.hint ? (
                            <span className="ml-2 text-muted-foreground">
                                {selected.hint}
                            </span>
                        ) : null}
                    </span>
                    <ChevronsUpDownIcon className="ml-2 size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>

            {/* Matched to the trigger's width so a long card name has room.
                `[var(--…)]` rather than Tailwind 3's bare `[--…]` shorthand,
                which this project's Tailwind 4 emits as invalid CSS - the
                popover then sizes to its content and the match is silent. */}
            <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0">
                <Command
                    // cmdk scores its own fuzzy match by default, which puts
                    // "Angel" below things that merely contain those letters.
                    // The option says what it wants matched, so a plain
                    // substring test over that is both predictable and enough.
                    filter={(itemValue, search) => {
                        const option = options.find(
                            (candidate) =>
                                String(candidate.value) === itemValue,
                        );

                        if (option === undefined) {
                            return 0;
                        }

                        return option.search
                            .toLowerCase()
                            .includes(search.trim().toLowerCase())
                            ? 1
                            : 0;
                    }}
                >
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyMessage}</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    // The id, so the filter above can find the
                                    // option again; cmdk hands this back rather
                                    // than the object.
                                    value={String(option.value)}
                                    onSelect={() => {
                                        onChange(option.value);
                                        setOpen(false);
                                    }}
                                >
                                    <CheckIcon
                                        className={cn(
                                            'mr-2 size-4 shrink-0',
                                            option.value === value
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    <span className="truncate">
                                        {option.label}
                                    </span>
                                    {option.hint ? (
                                        <span className="ml-auto pl-2 text-xs text-muted-foreground">
                                            {option.hint}
                                        </span>
                                    ) : null}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
