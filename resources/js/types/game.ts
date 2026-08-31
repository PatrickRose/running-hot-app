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
    directed: boolean;
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
export type DeckGrant = {
    amounts: number[];
    value_min: number;
    value_max: number;
    wild: boolean;
    /** Words printed on the card that the rulebook never defines, e.g. "No single". */
    restriction: string | null;
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
    restriction: string | null;
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
    status: 'claimed' | 'researched' | 'destroyed';
    status_label: string;
    origin: 'researched' | 'shared' | 'weak_copy' | 'good_copy' | 'stolen';
    origin_label: string;
    discount_percent: number;
    facility_id: number | null;
    facility: string | null;
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
