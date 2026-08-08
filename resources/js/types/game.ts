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

export type GameSummary = {
    id: number;
    name: string;
    status: 'draft' | 'running' | 'finished';
    status_label: string;
    stability: number;
    civil_unrest: number;
    auto_advance: boolean;
    has_discord_webhook: boolean;
    durations: {
        setup_seconds: number;
        action_seconds: number;
        team_time_seconds: number;
    };
    phase: PhaseSummary | null;
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
