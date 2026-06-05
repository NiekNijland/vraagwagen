export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type AdminQueryListItem = {
    id: string;
    slug: string;
    prompt: string;
    locale: string;
    rating: 'up' | 'down' | null;
    hasComment: boolean;
    model: string | null;
    totalTokens: number;
    estimatedCost: number | null;
    userId: string | null;
    createdAt: string;
};

export type AdminQueryDetail = {
    id: string;
    slug: string;
    correlationId: string | null;
    prompt: string;
    locale: string;
    plan: Record<string, unknown> | null;
    soql: Record<string, string> | null;
    url: string;
    rows: Record<string, unknown>[];
    rowCount: number;
    displayHint: string;
    steps: Record<string, unknown>[] | null;
    presentation: Record<string, unknown> | null;
    rating: 'up' | 'down' | null;
    comment: string | null;
    ratedAt: string | null;
    model: string | null;
    promptTokens: number | null;
    completionTokens: number | null;
    cacheReadTokens: number | null;
    thoughtTokens: number | null;
    estimatedCost: number | null;
    userId: string | null;
    createdAt: string;
};

export type AdminFeedbackItem = {
    id: string;
    slug: string;
    prompt: string;
    locale: string;
    rating: 'up' | 'down' | null;
    comment: string | null;
    ratedAt: string | null;
    createdAt: string;
};

export type AdminUserItem = {
    id: string;
    name: string;
    email: string;
    isAdmin: boolean;
    verified: boolean;
    queryCount: number;
    lastQueryAt: string | null;
    createdAt: string | null;
};

export type AdminQueryFilters = {
    search: string | null;
    rating: string | null;
    locale: string | null;
    from: string | null;
    to: string | null;
};

export type AdminStats = {
    perDay: {
        date: string;
        queries: number;
        cost: number;
        promptTokens: number;
        completionTokens: number;
        up: number;
        down: number;
    }[];
    totals: {
        queries: number;
        cost: number;
        promptTokens: number;
        completionTokens: number;
        up: number;
        down: number;
        allTimeQueries: number;
    };
};

export type RateLimitUsage = {
    used: number;
    limit: number;
    remaining: number;
    resetsInSeconds: number;
};

export type RateLimitValue = {
    value: number;
    overridden: boolean;
    default: number;
};

export type RateLimits = {
    per_minute: RateLimitValue;
    per_day_ip: RateLimitValue;
    per_day_global: RateLimitValue;
    feedback_per_minute: RateLimitValue;
};

export type IpUsage = {
    perMinute: RateLimitUsage;
    perDay: RateLimitUsage;
    feedbackPerMinute: RateLimitUsage;
};
