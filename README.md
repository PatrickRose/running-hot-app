# running-hot-app

The mechanics and timing for **Running Hot**, a pre-cyberpunk megagame by Patrick Rose.
See `CLAUDE.md` for how the application is put together, and
`docs/running-hot-rulebook.pdf` for the rules it implements.

## Faction artwork

The five Corporations and four gangs are drawn with their own logos. Drop each one
into `public/images/factions/`, named after a slug of the faction's name:

```
public/images/factions/augmented-nucleotech.png
public/images/factions/digital-tactical-control.png
public/images/factions/genetic-equity.png
public/images/factions/gordon.png
public/images/factions/mccullough-calibrated-mechanical.png
public/images/factions/facers.png
public/images/factions/g33ks.png
public/images/factions/dancers.png
public/images/factions/gruffsters.png
```

Nothing else needs doing: there is no column and no seeding step, so a logo added
here shows up in games that already exist. `webp`, `png`, `jpg` and `jpeg` all
resolve, and a `webp` supersedes a `png` of the same name.

- **Keep them small.** Discord scales a thumbnail to 80×80, so anything much over a
  few hundred pixels square is bytes spent on every message for nothing.
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
