# running-hot-app

The mechanics and timing for **Running Hot**, a pre-cyberpunk megagame by Patrick Rose.
See `CLAUDE.md` for how the application is put together, and
`docs/running-hot-rulebook.pdf` for the rules it implements.

## Logos

The five Corporations and four gangs are drawn with their own logos, and so are the
three characters that are organisations rather than people — the two Press outlets
and HM Government. Drop them into `public/images/logos/`, named after a slug of the
name they belong to.

**Two variants per faction, because one picture cannot do both jobs.**

- **The square badge** is the logo alone, filed under the bare slug. It goes
  wherever the name is already written beside it — a table row, a card heading, a
  character's role line — which is every one of the application's own screens. At
  24–48 pixels square, anything with words in it would be illegible.
- **The wide lockup** is the logo with the faction's name set as type, filed with a
  `-wide` suffix. It says the name itself, so it only belongs where it can stand in
  place of written text and has room to be read. The `#facility-list` Discord embed
  is its one consumer, where a Corporation gets the full width of a message.

| Badge                                  | Lockup                                      |
| -------------------------------------- | ------------------------------------------- |
| `augmented-nucleotech.png`             | `augmented-nucleotech-wide.png`             |
| `digital-tactical-control.png`         | `digital-tactical-control-wide.png`         |
| `genetic-equity.png`                   | `genetic-equity-wide.png`                   |
| `gordon.png`                           | `gordon-wide.png`                           |
| `mccullough-calibrated-mechanical.png` | `mccullough-calibrated-mechanical-wide.png` |
| `facers.png`                           | `facers-wide.png`                           |
| `g33ks.png`                            | `g33ks-wide.png`                            |
| `dancers.png`                          | `dancers-wide.png`                          |
| `gruffsters.png`                       | `gruffsters-wide.png`                       |
| `business-times.png`                   | `business-times-wide.png`                   |
| `th3-undergr0und.png`                  | `th3-undergr0und-wide.png`                  |
| `hm-government.png`                    | `hm-government-wide.png`                    |

Nothing else needs doing: there is no column and no seeding step, so a logo added
here shows up in games that already exist. `webp`, `png`, `jpg` and `jpeg` all
resolve, and a `webp` supersedes a `png` of the same name. `Str::slug` folds an
underscore to a hyphen, so `gordon_wide.png` lands in the same place as
`gordon-wide.png`.

**Either variant may be the one that is missing**, and neither stands in for the
other: a faction with only a lockup draws its initials in the small slots rather
than an unreadable smudge, and the embed falls back to the square badge as an
80×80 thumbnail when there is no lockup.

- **Keep them small.** Discord scales a thumbnail to 80×80 and an embed image to a
  few hundred pixels wide, so anything much beyond that is bytes spent on every
  message for nothing.
- **No SVG.** Discord will not render one in an embed, so a vector-only faction
  would look right in the browser and have no thumbnail in the channel. The
  resolver ignores them for that reason.
- **Discord fetches the image itself, over the public internet.** A logo has to be
  reachable at an absolute URL from wherever the app is deployed — so on a dev
  server Discord cannot see, the `#facility-list` embed simply has no thumbnail.
  Nothing breaks; there is just no picture.
- **A faction with no logo is a normal case**, not an error: it is drawn as its
  initials on the colour its Discord role already wears. That is also what a
  Corporation Control invents mid-game gets.
- **A character with no logo is drawn as nothing at all**, which is the opposite
  rule and deliberate. Only the three above are organisations; the rest of the
  forty-odd characters are people, and a coloured square against every name would
  imply an organisation where there is only somebody's name. So a person needs no
  file, and adding one for Jack Scanton would simply start showing it.
