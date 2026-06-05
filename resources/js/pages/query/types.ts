export type WhereClause = {
    field: string;
    op: string;
    value: string;
    /** Resolved match set for an `in` clause; absent for scalar ops. */
    values?: string[];
};
export type AggregateClause = {
    fn: string;
    field: string | null;
    alias: string;
};
export type OrderClause = { expr: string; direction: 'asc' | 'desc' };

export type Bucket = 'none' | 'year' | 'month' | 'day';
export type GroupKey = { field: string; bucket: Bucket };

export type DisplayHint =
    | 'count'
    | 'stats'
    | 'bars'
    | 'stacked_bars'
    | 'pie'
    | 'histogram'
    | 'timeseries'
    | 'table'
    | 'record'
    | 'unsupported';

export type Rating = 'up' | 'down';

export type TargetDataset = 'RegisteredVehicles' | 'RegisteredVehicleFuels';

export type Plan = {
    dataset: TargetDataset;
    where: WhereClause[];
    select: string[];
    groupBy: GroupKey[];
    aggregates: AggregateClause[];
    orderBy: OrderClause[];
    limit: number | null;
    display: DisplayHint;
    explanation: string;
};

export type QueryRow = Record<string, unknown>;

export type DeriveOp =
    | 'groupShare'
    | 'percentage'
    | 'ratio'
    | 'difference'
    | 'sum';

/** One executed query in a multi-step program (for the debug pane). */
export type Step = {
    id: string;
    plan: Plan;
    soql: Record<string, string>;
    url: string;
    rowCount: number;
};

/** The deterministic figure computed by the engine from query results. */
export type Derived = {
    op: DeriveOp;
    value: number;
    numerator: number;
    denominator: number;
};

export type RefusalReason =
    | 'out_of_scope'
    | 'no_such_data'
    | 'too_broad'
    | 'ambiguous';

/** Why a question could not be answered, plus answerable alternatives to offer the user. */
export type Refusal = {
    reason: RefusalReason;
    suggestions: string[];
};

/** The resolved presentation: which result is shown and any computed figure. */
export type Presentation = {
    resultRef: string;
    display: DisplayHint;
    derive: Record<string, unknown> | null;
    derived: Derived | null;
    refusal?: Refusal | null;
    explanation: string;
    /** Model-suggested next questions, each a complete prompt the user can re-run. */
    followUps?: string[];
};

export type TokenUsage = {
    prompt: number;
    completion: number;
    cacheRead: number;
    thought: number;
};

export type QueryResult = {
    slug?: string;
    correlationId?: string;
    prompt: string;
    plan: Plan;
    soql: Record<string, string>;
    url: string;
    rows: QueryRow[];
    displayHint: DisplayHint;
    rating: Rating | null;
    comment: string | null;
    model: string;
    tokens: TokenUsage;
    estimatedCost: number | null;
    steps?: Step[];
    presentation?: Presentation | null;
};

export type SharedRun = {
    slug: string;
    correlationId?: string;
    prompt: string;
    locale: string;
    plan: Plan;
    soql: Record<string, string>;
    url: string;
    rows: QueryRow[];
    displayHint: DisplayHint;
    rating: Rating | null;
    comment: string | null;
    model: string;
    tokens: TokenUsage;
    estimatedCost: number | null;
    steps?: Step[];
    presentation?: Presentation | null;
};

export type RunResponse = {
    slug: string;
    correlationId: string;
    plan: Plan;
    soql: Record<string, string>;
    url: string;
    rows: QueryRow[];
    displayHint: DisplayHint;
    model: string;
    tokens: TokenUsage;
    estimatedCost: number | null;
    steps?: Step[];
    presentation?: Presentation | null;
};

export type ErrorResponse = {
    error?: string;
    correlationId?: string;
    plan?: Plan;
    soql?: Record<string, string>;
    url?: string;
    responseBody?: string | null;
};

export type QueryError = {
    message: string;
    correlationId?: string;
    soql?: Record<string, string>;
    url?: string;
    responseBody?: string | null;
};

/** Platform-wide figures shown in the footer strip; arrives as a deferred Inertia prop. */
export type PlatformStats = {
    vehicles: number | null;
    datasets: number;
    queriesAnswered: number;
};
