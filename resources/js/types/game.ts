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

export type CharacterSubject = TrackerSubject & {
    name: string;
    role: string;
    role_label: string;
    team: string | null;
    discord_username: string | null;
    claimed_by: string | null;
    body: number;
    incapacitated: boolean;
};

export type NamedSubject = TrackerSubject & { name: string };

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
    /** Uninstalled copies. Installing moves one of these into a Facility. */
    copies_in_hand: number;
    /** Copies sitting in this Corporation's Facilities. */
    installed: number;
};

export type CorporationCardHoldings = {
    corporation_id: number;
    corporation: string;
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
    effect: string;
    /** Null where the market does not sell it. */
    cost: number | null;
    notes: string | null;
};

/** A technology on a Corporation's tech tree (rulebook 3.2.2). */
export type TechnologySummary = {
    id: number;
    code: string | null;
    image_path: string | null;
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

export type CorporationFacilities = {
    id: number;
    name: string;
    credits: number;
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
export type PublicCorporationFacilities = {
    name: string;
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
