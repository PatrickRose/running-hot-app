export type PhaseStatus = 'pending' | 'running' | 'paused' | 'completed';

export type PhaseType = 'setup' | 'action' | 'team_time';

export type PhaseSummary = {
    id: number;
    turn: number;
    type: PhaseType;
    type_label: string;
    status: PhaseStatus;
    status_label: string;
    starts_at: string | null;
    ends_at: string | null;
    remaining_seconds: number;
    upkeep_applied: boolean;
};

export type DiscordProvisionStatus =
    'idle' | 'queued' | 'running' | 'completed' | 'failed' | 'resetting';

export type GameDiscord = {
    guild_id: string | null;
    invite_url: string | null;
    /** False when DISCORD_BOT_TOKEN is unset, which disables provisioning. */
    bot_configured: boolean;
    provision_status: DiscordProvisionStatus;
    provision_status_label: string;
    provision_in_progress: boolean;
    provision_message: string | null;
    provisioned_at: string | null;
    /** Counts of what the application has created, keyed by resource kind. */
    resource_counts: Record<string, number>;
};

/** Loaded on demand: the servers the bot has been added to. */
export type DiscordBotGuilds = {
    guilds: Array<{ id: string; name: string }>;
    error: string | null;
};

export type DiscordMemberSync = {
    id: number;
    user: string;
    discord_username: string | null;
    status: 'synced' | 'not_a_member' | 'failed';
    status_label: string;
    message: string | null;
    role_count: number;
    synced_at: string | null;
};

/** A seat on a game's Control team, waiting on a handle or bound to an account. */
export type ControlMember = {
    id: number;
    discord_username: string | null;
    claimed_by: string | null;
    is_you: boolean;
};

export type GameSummary = {
    id: number;
    name: string;
    status: 'draft' | 'running' | 'finished';
    status_label: string;
    stability: number;
    civil_unrest: number;
    auto_advance: boolean;
    /** Null until the game's Discord server is provisioned. */
    discord_webhook_url: string | null;
    durations: {
        setup_seconds: number;
        action_seconds: number;
        team_time_seconds: number;
    };
    phase: PhaseSummary | null;
    discord: GameDiscord;
    server_time: string;
};

export type TrackerValues = Record<string, number>;

export type TrackerSubject = {
    subject_type: string;
    subject_id: number;
    name?: string;
    values: TrackerValues;
};

/**
 * How a faction is drawn: its name, its logo where there is one, and the colour
 * that stands in for the logo where there is not.
 *
 * Both extra fields come from the server. `logo_path` is resolved from a slug of
 * the name — there is no column, so artwork added to the repository shows up in
 * games that already exist. `colour` is derived from an md5 of the name and is
 * the colour of the faction's Discord role, and it is sent rather than computed
 * here so that hash is not written twice.
 */
export type Faction = {
    name: string;
    /** Null for a faction with no artwork on record, which is a normal case. */
    logo_path: string | null;
    /** CSS hex, e.g. `#1ABC9C`. */
    colour: string;
};

export type CharacterSubject = TrackerSubject & {
    name: string;
    /**
     * Set only for the characters that are organisations rather than people —
     * the two Press outlets and HM Government. Null for everybody else, and the
     * page then draws nothing rather than falling back to initials the way a
     * faction does.
     */
    logo_path: string | null;
    role: string;
    role_label: string;
    team: string | null;
    discord_username: string | null;
    claimed_by: string | null;
    /**
     * The four printed stats. Not Trackers — they are what a character is
     * rather than a number the game moves — so Control edits them outright and
     * no ledger row is written.
     */
    brawn: number;
    hack: number;
    charisma: number;
    body: number;
    incapacitated: boolean;
};

export type NamedSubject = TrackerSubject & Faction;

export type GameTrackers = {
    global: TrackerSubject;
    corporations: NamedSubject[];
    gangs: NamedSubject[];
    characters: CharacterSubject[];
};

/**
 * What one of the player's characters is standing at, for the header strip.
 *
 * A Corporate player is shown their Corporation's Credits and nothing else, so
 * `subject` is the Corporation's name there and the character's own everywhere
 * else — it names whose numbers these are. Wounds, Tags and Body are null for
 * that case rather than zero: a CEO does not have none, they do not have any.
 */
export type StandingCharacter = {
    character_id: number;
    character: string;
    subject: string;
    credits: number;
    wounds: number | null;
    tags: number | null;
    body: number | null;
    incapacitated: boolean;
};

/**
 * Shared from HandleInertiaRequests on every page, beside the clock. Null when
 * no game is running.
 *
 * Procatorion's two numbers belong to the game, so everybody is shown them —
 * Control and the Press outlets included. `characters` is empty for anybody
 * holding no character the header has numbers for.
 */
export type PlayerStanding = {
    stability: number;
    civil_unrest: number;
    characters: StandingCharacter[];
};

export type TrackerAdjustment = {
    id: number;
    tracker: string;
    tracker_label: string;
    subject: string;
    value_before: number;
    value_after: number;
    delta: number;
    reason: string | null;
    automated: boolean;
    actor: string | null;
    at: string | null;
};

export type ProtectionKind = 'physical' | 'cyber';

export type ProtectionCardAvailability =
    'available' | 'rumoured' | 'research_only';

/**
 * How a type's effect grows with the number a Corporation owns: flat per
 * Facility, or stepping at 2, 3, 5, 8.
 */
export type FacilityGrantScaling = 'per_facility' | 'thresholds';

/** An entry in the game's Facility type catalogue (rulebook 3.3.1). */
export type FacilityTypeSummary = {
    id: number;
    key: string;
    name: string;
    description: string | null;
    access_effect: string | null;
    build_cost: number;
    /** Physical slots each Facility of this type adds. Security grants 1. */
    physical_slots_granted: number;
    /** Cyber slots each Facility of this type adds. Security grants 2. */
    cyber_slots_granted: number;
    /** Technology storage each Facility of this type adds. */
    technology_capacity_granted: number;
    /** Credits off reordering a stack. Factory grants 2. */
    card_move_discount: number;
    grant_scaling: FacilityGrantScaling;
    grant_scaling_label: string;
    facility_count: number;
    in_use: boolean;
};

/** One Corporation's stake in one Protection Card (rulebook 3.3.4). */
export type CardHolding = {
    card_type_id: number;
    code: string | null;
    name: string;
    kind: ProtectionKind;
    kind_label: string;
    kind_glyph: string;
    /** Uninstalled copies. Installing moves one of these into a Facility. */
    copies_in_hand: number;
    /** Copies sitting in this Corporation's Facilities. */
    installed: number;
};

export type CorporationCardHoldings = Faction & {
    corporation_id: number;
    cards: CardHolding[];
};

/** One Runner's stake in one Equipment card (rulebook 3.4.1). */
export type EquipmentHolding = {
    card_type_id: number;
    code: string | null;
    name: string;
    category: 'permanent' | 'this-run' | 'single-use';
    category_label: string;
    category_glyph: string;
    /** What the card does, as printed. */
    effect: string;
    /** Where the artwork lives, or null when there is none on record. */
    image_path: string | null;
    /**
     * Copies in hand. Playing a This-run or Single-use card spends one; a
     * Permanent item is only lost by being carried out of a Facility.
     */
    copies: number;
};

/** What one Runner is carrying. Per Character, because that is what 3.4.1 caps. */
export type RunnerEquipment = {
    character_id: number;
    name: string;
    role: 'runner' | 'freelancer';
    role_label: string;
    cards: EquipmentHolding[];
};

/**
 * One gang's Runners and their hands, or the Freelancers, who run with nobody.
 */
export type GangEquipmentHoldings = Faction & {
    /** Null for the Freelancers, who are grouped together rather than banded. */
    gang_id: number | null;
    /** False for that group, whose name is what they are rather than a faction. */
    has_badge: boolean;
    runners: RunnerEquipment[];
};

/** A card Runners carry into a Run (rulebook 3.4.1). */
export type EquipmentCardSummary = {
    id: number;
    code: string | null;
    image_path: string | null;
    name: string;
    category: 'permanent' | 'this-run' | 'single-use';
    category_label: string;
    /** The character that draws this category's icon in the game's own font. */
    category_glyph: string;
    effect: string;
    /** Null where the market does not sell it. */
    cost: number | null;
    notes: string | null;
};

/** A tech tree a technology can sit on: one Corporation's, or the common set. */
export type TechnologyTreeSummary = {
    tree: string;
    label: string;
    /** Null for the technologies common to every Corporation. */
    corporation_id: number | null;
};

/**
 * One of the four Research Point suits, with the character that draws its icon
 * in the game's own font.
 *
 * The glyph is a bare capital letter — the font maps icons onto ASCII — so it
 * only reads as an icon while the font is loaded, and the label always travels
 * with it.
 */
export type ResearchSuitSummary = {
    value: string;
    label: string;
    glyph: string;
};

/** A technology on a Corporation's tech tree (rulebook 3.2.2). */
export type TechnologySummary = {
    id: number;
    code: string | null;
    image_path: string | null;
    /**
     * The other face. A research card is printed proposal side up and flipped
     * over when it is researched, so it has two — and both are public.
     */
    back_image_path: string | null;
    name: string;
    /** The Corporation's own key, or "standard" for the common set. */
    tree: string;
    corporation: string | null;
    description: string | null;
    effect: string | null;
    /** The price in each of the four Research Point suits. */
    cost: Record<string, number>;
    is_free: boolean;
    prerequisites: string[];
    required_facility_type: string | null;
    copy_strength: number | null;
    destroy_strength: number | null;
};

/** An entry in the game's Protection Card catalogue (rulebook 3.3.2). */
export type ProtectionCardSummary = {
    id: number;
    /**
     * The code printed on the card, and how its artwork is found. Null for a
     * card Control invented mid-game, which has neither.
     */
    code: string | null;
    /** Where the artwork lives, or null when there is none on record. */
    image_path: string | null;
    name: string;
    kind: ProtectionKind;
    kind_label: string;
    /** The character that draws this kind's icon in the game's own font. */
    kind_glyph: string;
    /** Null for a card nobody can buy yet, which is not a card that is free. */
    cost: number | null;
    /**
     * The challenge as the card prints it - "Brute (6)", or "Hack (4+N) - where
     * N is the number of cards underneath this". Not a skill and a number,
     * because the real cards are not.
     */
    challenge: string;
    consequence: string;
    charge_cost: number | null;
    charge_consequence: string | null;
    availability: ProtectionCardAvailability;
    availability_label: string;
    notes: string | null;
    installed_count: number;
};

export type InstalledProtectionCard = {
    id: number;
    /** 1 is the card Runners meet first. */
    position: number;
    card_type_id: number;
    code: string | null;
    image_path: string | null;
    name: string;
    challenge: string;
    consequence: string;
    charge_cost: number | null;
    charge_consequence: string | null;
};

export type ProtectionStack = {
    kind: ProtectionKind;
    kind_label: string;
    kind_glyph: string;
    slots: number;
    cards: InstalledProtectionCard[];
};

export type FacilitySecurityState = {
    budget: number;
    budget_spent: number;
    budget_returned: boolean;
    cards_removed: number;
};

/**
 * Whether the application has this Facility's Discord channels on record.
 * Null for a viewer with no business knowing — only Control gets it.
 */
export type FacilityChannels = {
    text: boolean;
    voice: boolean;
};

export type FacilitySummary = {
    id: number;
    name: string;
    corporation_id: number;
    facility_type_id: number;
    facility_type: string;
    available_from_turn: number;
    /** False while the Facility is still being built. */
    available: boolean;
    notes: string | null;
    channels: FacilityChannels | null;
    stacks: ProtectionStack[];
    security: FacilitySecurityState;
    /** How many technologies this Facility can hold (rulebook 3.2.2). */
    technology_capacity: number;
    /**
     * What is stored in it. Present only for the Corporation that owns the
     * Facility and for Control — 3.4.2 keeps the contents Secret from outside
     * the Corporation so that reconnaissance costs something, not from the
     * people who put them there.
     */
    technologies: StoredTechnology[];
};

/**
 * A technology card sitting in a Facility (rulebook 3.2.2).
 *
 * Only ever the cards actually in the building: one a Run stole or destroyed
 * keeps its row but is not listed here, so the count on screen is the count the
 * server enforces capacity against.
 *
 * It carries enough to draw itself because Security drags these between
 * Facilities — which building holds what is a decision about what a Run would
 * come away with, so the card is shown while it is in the air rather than a
 * ghost of a chip.
 */
export type StoredTechnology = {
    id: number;
    name: string;
    code: string | null;
    image_path: string | null;
    description: string | null;
    effect: string | null;
    status: string;
    status_label: string;
    origin_label: string;
    /**
     * The Facility type this card has to be housed in, if it names one
     * (rulebook 3.2.2). Null for a technology that will go anywhere.
     */
    required_facility_type_id: number | null;
    required_facility_type: string | null;
    /**
     * 3.2.7 in one boolean: a claimed copy is paper until it is paid for, and a
     * stolen piece of a split technology does nothing until its thief holds
     * every piece.
     */
    usable: boolean;
};

/**
 * A card the Corporation is holding but has not installed — what Security drags
 * from. It carries everything a card needs to draw itself, because the board
 * shows the real card rather than a name in a list.
 */
export type HandCard = {
    card_type_id: number;
    code: string | null;
    image_path: string | null;
    name: string;
    kind: ProtectionKind;
    kind_label: string;
    kind_glyph: string;
    challenge: string;
    consequence: string;
    charge_cost: number | null;
    charge_consequence: string | null;
    /** Always at least 1: a card with no copies left is not in the hand. */
    copies_in_hand: number;
};

/** What a proposed stack order would cost, answered by the server. */
export type ReorderQuote = {
    /** Cards that have to move. The Factory discount comes off after this. */
    moved: number;
    discount: number;
    cost: number;
    affordable: boolean;
};

export type CorporationFacilities = Faction & {
    id: number;
    credits: number;
    /**
     * Whether this player may arrange the stacks or only read them. True for
     * the Corporation's Security seat; every other Corporate seat sees the
     * same cards and cannot move them.
     */
    can_defend: boolean;
    /** Empty for a viewer who may not defend. */
    hand: HandCard[];
    /** Physical and cyber move independently, so they are reported apart. */
    physical_slots: number;
    cyber_slots: number;
    technology_capacity_per_facility: number;
    card_move_discount: number;
    facilities: FacilitySummary[];
};

/** Whether the Facility list can be published to Discord, and whether it has. */
export type FacilityListState = {
    /** False until the game's Discord server has been provisioned. */
    channel_exists: boolean;
    /** False when DISCORD_BOT_TOKEN is unset; a webhook cannot do this. */
    bot_configured: boolean;
    published: boolean;
};

export type PublicFacility = {
    id: number;
    name: string;
    facility_type: string;
    available: boolean;
    available_from_turn: number;
};

/** What every player may see: who owns what, and nothing about its defences. */
export type PublicCorporationFacilities = Faction & {
    is_yours: boolean;
    facilities: PublicFacility[];
};

/**
 * The Facility list as one player sees it. `own` is set only for a player
 * holding a Corporate seat, and carries that Corporation's stacks in full —
 * rulebook 3.4.2 makes those Secret from everyone else, not from their owner.
 */
export type FacilityBoard = {
    turn: number | null;
    public: PublicCorporationFacilities[];
    own: CorporationFacilities | null;
};

/**
 * One of a Corporation's technologies as it grants a deck card (rulebook
 * 3.2.3).
 *
 * The amounts are Research Points the player assigns to suits of their own
 * choosing, all different — "6 research credits in any suit and 3 in another" is
 * `[6, 3]`, and "5 from each suit" is `[5, 5, 5, 5]`. The card takes the suit of
 * the first amount unless it is wild, and its value is the player's choice
 * inside the range.
 */
/**
 * One marking printed on a research card (rulebook 3.2.1, 3.2.3).
 *
 * `label` is the words on the card — "No single", "Other side must be Cog" —
 * and `note` is what they do. Both travel because neither explains itself at
 * the size a card tile is drawn, so the tooltip and the screen reader get the
 * pair of them.
 *
 * `short` and `glyph` are the same marking at tile size, where there is room
 * for one word: "Other" and the demanded suit's icon says what "Other side
 * must be Cog" truncated to nothing useful. `glyph` is null for a marking that
 * names no suit.
 */
export type CardMarkingSummary = {
    label: string;
    note: string;
    short: string;
    glyph: string | null;
};

export type DeckGrant = {
    amounts: number[];
    value_min: number;
    value_max: number;
    wild: boolean;
    /** What the card is printed with, if anything. */
    markings: CardMarkingSummary[];
    requires_research_facilities: number;
};

/** A card in the research game (rulebook 3.2.1). */
export type ResearchCardSummary = {
    id: number;
    /** Null for a wild card, which counts as whichever suit its set needs. */
    suit: string | null;
    suit_label: string | null;
    /** The character that draws this card's icon — the wildcard's own for a wild. */
    glyph: string;
    value: number;
    wild: boolean;
    /** "7 Leaf", or "Wild 4". */
    label: string;
    /** What the card is printed with — it may carry more than one. */
    markings: CardMarkingSummary[];
    zone: 'deck' | 'hand' | 'pool' | 'spent';
    zone_label: string;
};

/** A Corporation's place in the research turn order (rulebook 3.2.1). */
export type ResearchSeatSummary = Faction & {
    corporation_id: number;
    order: number;
    playing: boolean;
    /** Why they are out: they left, their deck ran dry, or Control took them out. */
    left_reason: string | null;
    is_turn: boolean;
    is_yours: boolean;
    deck_remaining: number;
};

/** One sitting of the research game. All of this is public. */
export type ResearchSessionSummary = {
    id: number;
    open: boolean;
    turn: number | null;
    pool: ResearchCardSummary[];
    public_deck_remaining: number;
    current_order: number | null;
    is_your_turn: boolean;
    seats: ResearchSeatSummary[];
};

/** One card of an equation, as it was when it was played. */
export type PlayedResearchCard = {
    suit: string | null;
    value: number;
    from_hand: boolean;
};

/** An equation somebody played, and what it paid (rulebook 3.2.1). */
export type ResearchEquationSummary = {
    id: number;
    status: 'pending' | 'scored' | 'voided';
    status_label: string;
    /** Set only on Control's page, where equations from every Corporation mix. */
    corporation: string | null;
    corporation_id: number;
    turn: number | null;
    left: PlayedResearchCard[];
    right: PlayedResearchCard[];
    left_label: string;
    right_label: string;
    left_sum: number;
    right_sum: number;
    cards_per_side: number;
    balanced: boolean;
    /** The triangular bonus a balanced equation pays: 1, 3, 6, 10, and so on. */
    bonus: number;
    /**
     * The suits each side may be scored as. An all-wild set may be any of the
     * four, which is why this comes from the server rather than off the cards.
     */
    left_suits: string[];
    right_suits: string[];
    /** The suits the bonus may be split across. */
    bonus_suits: string[];
    scored_side: 'left' | 'right' | null;
    scored_suit: string | null;
    /** Points by suit, once somebody has taken them. */
    awards: Record<string, number> | null;
    scored_at: string | null;
    notes: string | null;
};

/** A technology card a Corporation actually has (rulebook 3.2.2). */
export type TechnologyHoldingSummary = {
    id: number;
    technology_type_id: number;
    name: string;
    code: string | null;
    image_path: string | null;
    back_image_path: string | null;
    effect: string | null;
    /**
     * Destroyed and Stolen are the two ways a Run takes a card off a
     * Corporation, and they are kept apart because they are not the same loss
     * (rulebook 3.2.6). Neither row is deleted.
     */
    status: 'claimed' | 'researched' | 'destroyed' | 'stolen';
    status_label: string;
    origin: 'researched' | 'shared' | 'weak_copy' | 'good_copy' | 'stolen';
    origin_label: string;
    discount_percent: number;
    facility_id: number | null;
    facility: string | null;
    /**
     * The Facility type this card has to be housed in, if it names one
     * (rulebook 3.2.2). Null for a technology that will go anywhere.
     */
    required_facility_type_id: number | null;
    required_facility_type: string | null;
    paid: Record<string, number>;
    /**
     * Whether the card is actually working. False for a claimed copy, and false
     * for a split technology whose thief has not collected every piece
     * (rulebook 3.2.7).
     */
    usable: boolean;
    split_group: string | null;
    split_piece: number | null;
    split_pieces: number | null;
    notes: string | null;
};

/** A copy or a theft in hand, and what paying for it would cost. */
export type TechnologyClaim = {
    id: number;
    origin: string;
    origin_label: string;
    discount_percent: number;
    cost: Record<string, number>;
    facility: string | null;
};

/**
 * A technology as its card prints it, plus the two things the research game
 * needs off the row: whether it is one piece of a split technology (3.2.7), and
 * whether "researching" it actually customises a deck (3.2.3).
 *
 * This is what Control's panel lists. A Corporation's own tree adds its answer
 * to "can we?" on top — see `ResearchTreeEntry`.
 */
export type ResearchTechnologySummary = TechnologySummary & {
    split_group: string | null;
    split_piece: number | null;
    split_pieces: number | null;
    /** A technology the Corporation opened the game already holding (3.2.2). */
    starting: boolean;
    is_deck_customisation: boolean;
    deck_grant: DeckGrant | null;
};

/** A row on a Corporation's tech tree, with its own answer to "can we?". */
export type ResearchTreeEntry = ResearchTechnologySummary & {
    affordable: boolean;
    /** Prerequisite titles still missing (rulebook 3.2.2). */
    missing_prerequisites: string[];
    researched_count: number;
    claims: TechnologyClaim[];
};

/** Where a technology can be housed, and how full it is (rulebook 3.2.2). */
export type ResearchFacilitySummary = {
    id: number;
    name: string;
    facility_type: string;
    facility_type_id: number;
    available: boolean;
    stored: number;
    /** 2 for every Corporate Facility the Corporation owns. Zero is possible. */
    capacity: number;
};

/**
 * One Corporation's own half of the research game — its hand, its deck, its
 * points and its tree. Sent only to that Corporation: 3.2.5 makes the size of
 * its point pile semi-secret, and a hand everybody can read is not a card game.
 */
export type OwnResearch = Faction & {
    id: number;
    /** True for the Research seat. The CEO and Security read and cannot act. */
    can_play: boolean;
    points: Record<string, number>;
    hand: ResearchCardSummary[];
    deck: ResearchCardSummary[];
    deck_remaining: number;
    seated: boolean;
    playing: boolean;
    left_reason: string | null;
    is_your_turn: boolean;
    pending_equations: ResearchEquationSummary[];
    scored_equations: ResearchEquationSummary[];
    facilities: ResearchFacilitySummary[];
    holdings: TechnologyHoldingSummary[];
    tree: ResearchTreeEntry[];
};

/** The research sub-game as one player sees it (rulebook 3.2). */
export type ResearchBoard = {
    turn: number | null;
    suits: ResearchSuitSummary[];
    hand_size: number;
    pool_size: number;
    session: ResearchSessionSummary | null;
    corporations: Array<Faction & { id: number; is_yours: boolean }>;
    own: OwnResearch | null;
};

/** How a technology card came into a Corporation's hands (rulebook 3.2.6). */
export type TechnologyOriginSummary = {
    value: string;
    label: string;
    default_discount_percent: number;
};

/** Everything Research Control needs to run the table. No tiering at all. */
export type ResearchControlState = {
    turn: number | null;
    suits: ResearchSuitSummary[];
    origins: TechnologyOriginSummary[];
    session: ResearchSessionSummary | null;
    public_deck_remaining: number;
    corporations: Array<
        Faction & {
            id: number;
            points: Record<string, number>;
            deck_remaining: number;
            hand: ResearchCardSummary[];
            deck: ResearchCardSummary[];
            facilities: ResearchFacilitySummary[];
            holdings: TechnologyHoldingSummary[];
            researchers: string[];
        }
    >;
    equations: ResearchEquationSummary[];
    technologies: ResearchTechnologySummary[];
};

/**
 * The Council (rulebook 3.1).
 *
 * The shape of these mirrors who may see what rather than what is stored: a
 * field that is null here is usually a thing the viewer is not entitled to
 * rather than a thing that does not exist. `totals` and `breakdown` are the
 * ones that matter — both are withheld from the players on a vote the Chair
 * declared secret, and the Chair's own payload carries them.
 */
export type AgendaAmendment = 'addition' | 'removal' | 'rewording';

export type AgendaResolutionView = {
    id: number;
    position: number;
    text: string;
    /** False for an addition still waiting on Control, and for a removed one. */
    votable: boolean;
    removed: boolean;
    pending_amendment: AgendaAmendment | null;
    pending_amendment_label: string | null;
    /** The words a rewording would put in place of `text`, once signed off. */
    pending_text: string | null;
};

export type AgendaCardView = {
    id: number;
    title: string;
    body: string | null;
    control_note: string | null;
    status: string;
    status_label: string;
    is_custom: boolean;
    author: string | null;
    editable_by_author: boolean;
    resolutions: AgendaResolutionView[];
};

/** Political Will per resolution, keyed by resolution id. */
export type BallotAllocations = Record<number, number>;

/**
 * A seat at the Council. A Corporation votes with its Political Will; a
 * character Control has seated in their own right — HM Government — votes with
 * its bloc. `votes` is whichever, because from the Chair's side of the table
 * they weigh the same.
 */
export type CouncilVoter = Faction & {
    /** Unique across both kinds of seat, unlike the bare row id. */
    key: string;
    id: number;
    type: string;
    votes: number;
};

export type CouncilVoteRecord = Faction & {
    voter_key: string;
    allocations: BallotAllocations;
};

export type CouncilItem = {
    id: number;
    source: 'handed' | 'urgent' | 'promoted';
    source_label: string;
    secret: boolean;
    resolved: boolean;
    resolved_at: string | null;
    tie_broken: boolean;
    card: AgendaCardView;
    outcome: { resolution_id: number; text: string } | null;
    /** Who has handed a slip to the Chair — public even in a secret vote. */
    submitted: Array<
        Faction & {
            ballot_id: number;
            voter_key: string;
            submitted_at: string;
        }
    >;
    /** Withheld from the players while a vote is secret, or still open. */
    totals: Record<number, number> | null;
    tied: boolean | null;
    breakdown: CouncilVoteRecord[] | null;
    /** Your own vote, which is never hidden from you. */
    your_ballot: {
        id: number;
        submitted_at: string;
        allocations: BallotAllocations;
    } | null;
    can_vote: boolean;
};

export type CouncilSessionView = {
    id: number;
    chair: (Faction & { id: number }) | null;
    recess_at: string | null;
    recess_seconds_remaining: number | null;
    in_recess: boolean;
    paused: boolean;
    /** Whether Control has handed the Chair anything this sitting. */
    has_handed: boolean;
    /** Once true, the hand is settled and Control cannot change it. */
    chair_has_chosen: boolean;
    tabled_count: number;
    maximum_items: number;
    /** What the Control panel offers as a hand, not a limit. */
    cards_handed: number;
    cards_kept: number;
    can_promote: boolean;
};

export type CouncilViewer = {
    is_control: boolean;
    /** You hold the CEO seat of the Corporation chairing this turn. */
    is_chair: boolean;
    /**
     * You may use the Chair's controls — the Chair, or Control standing behind
     * them. Not the same as being the Chair, and never say so on a page.
     */
    can_chair: boolean;
    /** You hold a CEO seat, so there is a Corporation's vote for you to cast. */
    can_vote: boolean;
    can_submit_agenda: boolean;
    /** The seat you vote from, of either kind. Control holds none. */
    voter: CouncilVoter | null;
    character_id: number | null;
};

export type CouncilBoard = {
    turn: number | null;
    session: CouncilSessionView | null;
    viewer: CouncilViewer;
    /** What Control has handed the Chair: the Chair's and Control's alone. */
    hand: AgendaCardView[];
    items: CouncilItem[];
    with_chair: AgendaCardView[];
    important: AgendaCardView[];
    /**
     * Cards waiting on Control's remarks — Control's alone, and pointedly not
     * the Chair's: one of these has not been handed to the Chair yet.
     */
    with_control: AgendaCardView[];
    my_cards: AgendaCardView[];
};

export type CouncilSeatView = Faction & {
    corporation_id: number;
    political_will: number;
    setup_attendance: 'unknown' | 'present' | 'absent';
    action_attendance: 'unknown' | 'present' | 'absent';
    setup_penalty_applied: boolean;
    action_penalty_applied: boolean;
};

/** A character Control may seat at the Council, or has seated. */
export type CouncilSeatCandidate = {
    id: number;
    name: string;
    role_label: string;
    team: string | null;
    /** Null for somebody who holds no seat yet. */
    votes: number | null;
};

export type CouncilControlBoard = {
    deck: AgendaCardView[];
    with_control: AgendaCardView[];
    amendments: Array<
        AgendaResolutionView & {
            card_id: number;
            card_title: string;
            proposed_by: string | null;
        }
    >;
    rotation: Array<
        Faction & { id: number; chair_order: number | null; is_chair: boolean }
    >;
    seats: CouncilSeatView[];
    /** Seats that are not Corporations — HM Government's, and any Control adds. */
    own_seats: CouncilSeatCandidate[];
    seatable: CouncilSeatCandidate[];
    /** What Control's penalty field is pre-filled with, not a rule. */
    absence_penalty: number;
    recess_seconds: number;
};

/**
 * A Protection Card as the Runners standing in front of it may see it
 * (rulebook 3.4.2).
 *
 * The optional half is the point rather than an afterthought: a card is face
 * down until it is Active, so a Runner gets the shell and nothing else. Once it
 * is flipped they get all of it, because they have to read the challenge to
 * roll against it. Security, reading their own stack, always gets the whole
 * thing.
 */
export type RunCard = {
    id: number;
    kind: 'physical' | 'cyber';
    kind_label: string;
    position: number;
    active: boolean;
    /** Whether Security has had its go at this card yet, either way. */
    settled: boolean;
    boosts: number;
    next_boost_cost: number;
    activation_cost: number | null;

    /**
     * What the card gains before its printed strength is even named — quoted by
     * the server so the sum shown while Security types the number is the same
     * one that gets rolled.
     */
    strength_bonuses: {
        cards_passed: number;
        alerts: number;
        boosts: number;
    };
    name?: string;
    code?: string | null;
    /** The sentence the card prints, e.g. `Brute (6)`. Never parsed. */
    challenge?: string;
    consequence?: string;
    charge_cost?: number | null;
    charge_consequence?: string | null;
    image_path?: string | null;
};

export type RunDiceRollView = {
    roller: 'runners' | 'security';
    roller_label: string;
    pool: number;
    die_faces: number;
    faces: number[];
    successes: number;
    /** `6d8, 5+ — 1,2,2,3,4,4 (0 successes)`. */
    readout: string;
    reason: string | null;
};

export type RunEventView = {
    id: number;
    pass: number;
    step: RunStep;
    type: string;
    description: string;
    character: string | null;
    at: string | null;
    rolls: RunDiceRollView[];
};

export type RunStep = 'activate' | 'challenge' | 'consequence' | 'breather';

export type RunConsequenceEffect =
    'alert' | 'tag' | 'wound' | 'retry' | 'end_the_run';

export type RunParticipantView = {
    id: number;
    character_id: number;
    name: string;
    position: number;
    is_leader: boolean;
    is_yours: boolean;
    gang: Faction | null;
    brawn: number;
    hack: number;
    body: number;
    wounds: number;
    tags: number;
    left: boolean;
    left_reason: string | null;
};

export type RunBudget = {
    placed: number;
    spent: number;
    left: number;
    /** What the Corporation still holds behind the budget. */
    company: number;
};

/**
 * One access a Runner spent inside a Facility they broke into (rulebook 3.4.3).
 */
export type RunAccessTaken = {
    id: number;
    character_id: number;
    character: string;
    kind: string;
    kind_label: string;
    action: string | null;
    action_label: string | null;
    technology: string | null;
    successes: number | null;
    outcome: string | null;
    discount_percent: number | null;
    credits: number | null;
};

/**
 * What has been taken out of the Facility, and who still has an access left.
 */
/** A card an access turned up that nobody has decided about yet. */
export type RunUndecidedAccess = {
    id: number;
    character_id: number;
    character: string;
    technology: string | null;
};

export type RunAccesses = {
    taken: RunAccessTaken[];
    undecided: RunUndecidedAccess[];
    /** Accesses remaining, keyed by character id. */
    left: Record<string, number | undefined>;
    credits_taken: boolean;
    facility_effect_taken: boolean;
};

/**
 * The dice the Runners have in hand for one skill (rulebook 3.4.2).
 *
 * The Leader rolls their full skill and everyone else adds to the same pool,
 * so there is one die size and it comes from the Leader alone: d6s if they are
 * Wounded, d8s otherwise.
 */
export type RunDicePool = {
    /** The Leader's own dice: their full skill. */
    leader: number;
    /** What each other Runner adds, keyed by character id. */
    others: Record<string, number>;
    die_faces: number;
    total: number;
};

/**
 * What Security has written down that the Runners are about to take.
 *
 * Both sides get it: Security marks the card and the Run Leader decides who
 * takes it, so it has to be the same slip in front of both of them.
 */
export type RunConsequenceSlip = {
    /** Whether Security has written anything at all, which "nothing" is not. */
    marked: boolean;
    effects: Partial<Record<RunConsequenceEffect, number>>;
    description: string;
    is_empty: boolean;
    ends_the_run: boolean;
    /**
     * Wounds, Tags and Alerts it would cost to shrug off an End the Run right
     * now — one more than the number already ignored (rulebook 3.4.2).
     */
    ignore_cost: number;
};

export type RunView = {
    id: number;
    status: 'submitted' | 'running' | 'succeeded' | 'failed';
    status_label: string;
    facility: {
        id: number;
        name: string;
        facility_type: string;
        corporation: Faction;
    };
    order_index: number | null;
    order_reason: string | null;
    alerts: number;
    alerts_spent: number;
    alerts_available: number;
    alert_strength_bonus: number;
    next_alert_threshold: number;
    cards_passed: number;
    active_cards_passed: number;
    ignored_end_the_run: number;
    retry_pending: boolean;
    pass: number;
    step: RunStep;
    step_label: string;
    consequence: RunConsequenceSlip;
    /**
     * Null for the Runners. A Facility's stack depth is Secret (rulebook 3.4.1,
     * footnote 11), so they find out by running out of cards.
     */
    cards_remaining: number | null;
    card: RunCard | null;

    /**
     * What the Runners would throw, keyed by skill — quoted by the server so
     * the half-rounded-down / quarter-rounded-up contribution rule of 3.4.2 is
     * never written a second time here. Empty while there is no Run Leader on
     * the run, which is not the same as a pool of no dice.
     */
    dice_pool: Record<string, RunDicePool | undefined>;

    accesses: RunAccesses;
    /** The Facility type's own effect, as printed. Null where it has none. */
    access_effect: string | null;
    /**
     * Cards this run has already turned over and could go back for. Named,
     * because the Runners have seen them; the unseen ones stay a count.
     */
    known_technologies: { id: number; name: string }[];
    /**
     * How many technologies are still there to be drawn from — a count and not
     * a list, because the card is drawn rather than chosen.
     */
    technologies_left: number;
    leader_character_id: number | null;
    participants: RunParticipantView[];
    /** Null for the Runners: how much defence is left is the Corporation's. */
    budget: RunBudget | null;
    /**
     * What the group is carrying. Null for the Security side, who have no
     * business reading either tier of it.
     */
    equipment: RunEquipmentView | null;
    can_lead: boolean;
    can_act: boolean;
    can_defend: boolean;
    log: RunEventView[];
};

/** One Equipment card, as a run describes it (rulebook 3.4.1). */
export type RunEquipmentCard = {
    card_type_id: number;
    code: string | null;
    name: string;
    category: 'permanent' | 'this-run' | 'single-use';
    category_label: string;
    category_glyph: string;
    /**
     * The printed effect. What it does to a roll is declared on the challenge
     * form rather than parsed from this — see App\Support\Runs\RollModifiers.
     */
    effect: string;
    image_path: string | null;
    /** Copies in hand, or null on a card already equipped for this run. */
    copies: number | null;
};

/**
 * What one Runner has in front of them. Visible to the whole group, because
 * 3.4.1 equips permanent items "by placing them in front of you".
 */
export type RunLoadout = {
    character_id: number;
    name: string;
    cards: RunEquipmentCard[];
};

/** One Runner's own hand. Only ever their own, or every one of them to Control. */
export type RunHand = {
    character_id: number;
    name: string;
    /** Chosen before the run goes in, capped at `cap`. */
    permanent: RunEquipmentCard[];
    /** Played during the run, one per Runner per step. */
    playable: RunEquipmentCard[];
    /** Whether this Runner has already played a card during this pass and step. */
    played_this_step: boolean;
    left: boolean;
    /**
     * What this Runner has declared their Equipment is worth to each skill for
     * this run. A skill rather than dice: 3.4.2 halves it on the way into the
     * pool for everybody who is not leading.
     */
    brawn_adjustment: number;
    hack_adjustment: number;
};

export type RunEquipmentView = {
    equipped: RunLoadout[];
    hands: RunHand[];
    /** The permanent-item cap of 3.4.1, from the engine rather than hardcoded. */
    cap: number;
};

export type RunTarget = {
    id: number;
    name: string;
    facility_type: string;
    corporation: Faction;
};

export type RunPartyMember = {
    id: number;
    name: string;
    is_yours: boolean;
    gang: Faction | null;
    brawn: number;
    hack: number;
    body: number;
    wounds: number;
    tags: number;
    incapacitated: boolean;
};

export type RunBoard = {
    turn: number | null;
    is_action_phase: boolean;
    is_control: boolean;
    can_submit: boolean;
    targets: RunTarget[];
    party: RunPartyMember[];
    /** Alerts raised on the way in, keyed by group size. Quoted by the server. */
    group_alerts: Record<number, number | undefined>;
    /** Runs this player is on, seen from inside the Facility. */
    yours: RunView[];
    /** Runs coming at this player's Facilities, seen from the Security desk. */
    defending: RunView[];
};

/**
 * Where a line of the shop's list stands (rulebook 3.3.3).
 *
 * Rumoured is a real line rather than an absent one: a card nobody can buy yet
 * is still something Security is told about and plans around.
 */
export type ShopListingStatus = 'on_sale' | 'rumoured' | 'withdrawn';

/** The Protection Card half of a shop line. */
export type ShopProtectionCard = {
    id: number;
    code: string | null;
    name: string;
    image_path: string | null;
    kind: string;
    kind_label: string;
    kind_glyph: string;
    challenge: string;
    consequence: string;
    charge_cost: number | null;
    charge_consequence: string | null;
    /** The catalogue's own word on the card, which is a different question. */
    availability: string;
    availability_label: string;
};

/** The Equipment half. */
export type ShopEquipmentCard = {
    id: number;
    code: string | null;
    name: string;
    image_path: string | null;
    category: 'permanent' | 'this-run' | 'single-use';
    category_label: string;
    category_glyph: string;
    effect: string;
};

/** One line of the shop's list: a card, a price, and what is left of it. */
export type ShopListing = {
    id: number;
    family: 'protection' | 'equipment';
    card: ShopProtectionCard | ShopEquipmentCard;
    price: number;
    /** Null is a line that never runs out, which is not the same as nought. */
    stock: number | null;
    status: ShopListingStatus;
    status_label: string;
    /** On sale and in stock. What the buy button is enabled on. */
    available: boolean;
    sold_out: boolean;
    sold_count: number;
    notes: string | null;
};

/**
 * A seat this player can shop with, and the purse it spends from.
 *
 * The purse is the Corporation's at the Protection counter and the character's
 * own at the market, so it is named rather than assumed.
 */
export type ShopBuyer = {
    character_id: number;
    name: string;
    /** The Corporation at the Protection counter, the character at the market. */
    purse_name: string;
    credits: number;
    /** Copies already in hand, keyed by listing id. */
    held: Record<number, number | undefined>;
};

/** One counter: what is on it, and who this player can buy with. */
export type ShopCounter = {
    listings: ShopListing[];
    buyers: ShopBuyer[];
};

/**
 * The shop as a player sees it. Either counter may be null: a Runner is not
 * handed the Protection Card list, and a Security player has no business at the
 * Runners' market.
 */
export type ShopBoard = {
    /** Whether a purchase would be in time. The shop runs during Setup. */
    open: boolean;
    phase: string | null;
    is_control: boolean;
    protection: ShopCounter | null;
    equipment: ShopCounter | null;
};

/**
 * A card not yet on the list, for Control's add form.
 *
 * The same shape a listed card carries, so the form can draw the card being
 * priced — pricing one you cannot see is guesswork, and the artwork is what
 * somebody at the table will be holding.
 */
export type ShopUnlistedCard = ShopProtectionCard | ShopEquipmentCard;

/** Whether an unlisted card is the Protection sort, for the picker and preview. */
export function isProtectionCard(
    card: ShopUnlistedCard,
): card is ShopProtectionCard {
    return 'kind' in card;
}

/** One copy leaving the shop, as Control's till roll shows it. */
export type ShopPurchase = {
    id: number;
    card_name: string;
    buyer_name: string;
    corporation_name: string | null;
    price_paid: number;
    turn: number | null;
    phase: string | null;
    bought_at: string | null;
};

/** Somebody Control can buy on behalf of. */
export type ShopBuyerOption = {
    character_id: number;
    name: string;
    role_label: string;
    team: string | null;
    credits: number;
    /** The purse that would actually pay: the Corporation's, or their own. */
    purse_credits: number;
};

/** The shop as Control runs it. */
export type ShopControlBoard = {
    open: boolean;
    phase: string | null;
    listings: ShopListing[];
    unlisted: {
        protection: ShopUnlistedCard[];
        equipment: ShopUnlistedCard[];
    };
    purchases: ShopPurchase[];
    buyers: {
        protection: ShopBuyerOption[];
        equipment: ShopBuyerOption[];
    };
    statuses: { value: ShopListingStatus; label: string }[];
};
