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

export type CouncilVoteRecord = Faction & {
    corporation_id: number;
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
            corporation_id: number;
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
    corporation: (Faction & { id: number; political_will: number }) | null;
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
    directed: boolean;
    placed: number;
    spent: number;
    left: number;
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
    /**
     * Null for the Runners. A Facility's stack depth is Secret (rulebook 3.4.1,
     * footnote 11), so they find out by running out of cards.
     */
    cards_remaining: number | null;
    card: RunCard | null;
    leader_character_id: number | null;
    participants: RunParticipantView[];
    /** Null for the Runners: how much defence is left is the Corporation's. */
    budget: RunBudget | null;
    can_lead: boolean;
    can_act: boolean;
    can_defend: boolean;
    log: RunEventView[];
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
    /** Runs this player is on, seen from inside the Facility. */
    yours: RunView[];
    /** Runs coming at this player's Facilities, seen from the Security desk. */
    defending: RunView[];
};
