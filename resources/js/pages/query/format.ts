import type { Bucket, Plan, QueryRow } from './types';

const NON_INFORMATIVE_GROUP_LABELS = new Set([
    'diversen',
    'na',
    'nietbekend',
    'nietgeregistreerd',
    'nvt',
    'onbekend',
    'unknown',
]);

export function localeTag(locale: string): string {
    return locale === 'nl' ? 'nl-NL' : 'en-US';
}

export function formatNumber(v: unknown, locale: string): string {
    const n = typeof v === 'number' ? v : Number(v);

    return Number.isFinite(n)
        ? n.toLocaleString(localeTag(locale))
        : String(v ?? '');
}

export function formatCell(
    v: unknown,
    locale: string,
    t: (key: string) => string,
): string {
    if (v === null || v === undefined) {
        return '—';
    }

    if (typeof v === 'boolean') {
        return v ? t('pages.query.boolean.yes') : t('pages.query.boolean.no');
    }

    if (typeof v === 'number') {
        return formatNumber(v, locale);
    }

    return String(v);
}

// Acronyms that should stay uppercase in humanized output ("ApkExpiryDate"
// -> "APK expiry date" instead of "Apk expiry date").
const ACRONYMS = new Set(['APK', 'BPM', 'WAM', 'NL', 'EU', 'VAT', 'ID']);

// "FirstAdmissionDate" -> "First admission date"; "ApkExpiryDate" -> "APK expiry date".
export function humanizePascalCase(name: string): string {
    const tokens = name.match(/[A-Z][a-z]+|[A-Z]+(?![a-z])|[0-9]+/g);

    if (tokens === null || tokens.length === 0) {
        return name;
    }

    const normalized = tokens.map((token, index) => {
        const upper = token.toUpperCase();

        if (ACRONYMS.has(upper)) {
            return upper;
        }

        const lower = token.toLowerCase();

        return index === 0
            ? lower.charAt(0).toUpperCase() + lower.slice(1)
            : lower;
    });

    return normalized.join(' ');
}

// "avg_mass" / "total-count" -> "Avg mass" / "Total count"; acronyms in the
// shared set stay uppercase ("avg_apk_price" -> "Avg APK price").
export function humanizeSnakeCase(alias: string): string {
    const tokens = alias.split(/[_\s-]+/).filter((token) => token.length > 0);

    if (tokens.length === 0) {
        return alias;
    }

    return tokens
        .map((token, index) => {
            const upper = token.toUpperCase();

            if (ACRONYMS.has(upper)) {
                return upper;
            }

            const lower = token.toLowerCase();

            return index === 0
                ? lower.charAt(0).toUpperCase() + lower.slice(1)
                : lower;
        })
        .join(' ');
}

// Look up a localized label for an RDW PascalCase column. Falls back to the
// humanized form when no translation exists, so unknown / newly added fields
// still render readably.
export function translateColumn(
    column: string,
    t: (key: string) => string,
): string {
    const key = `pages.query.columns.${column}`;
    const translated = t(key);

    return translated === key ? humanizePascalCase(column) : translated;
}

// The value (Y) axis of a chart shows the first aggregate. `count` has no field,
// so it reads as "Vehicles" (every result row is a registered vehicle); the other
// functions name the field they summarise, e.g. "Average empty mass".
export function valueAxisLabel(plan: Plan, t: (key: string) => string): string {
    const aggregate = plan.aggregates[0];

    if (aggregate === undefined || aggregate.fn === 'count') {
        return t('pages.query.axis.vehicles');
    }

    const fn = t(`pages.query.axis.fn.${aggregate.fn}`);

    if (aggregate.field === null) {
        return fn;
    }

    return `${fn} ${translateColumn(aggregate.field, t)}`;
}

// Find the column that holds the chart's numeric value. `exclude` is the group
// column, so we never return it as the value key — otherwise a numeric-looking
// group label (a year "2015", a raw mass) would be plotted against itself.
// Date-like values are skipped so a date column is never mistaken for a value.
export function findNumericKey(
    row: QueryRow,
    exclude?: string,
): string | undefined {
    for (const [k, v] of Object.entries(row)) {
        if (k === exclude || isDateLike(v)) {
            continue;
        }

        if (
            typeof v === 'number' ||
            (typeof v === 'string' &&
                v.trim() !== '' &&
                Number.isFinite(Number(v)))
        ) {
            return k;
        }
    }

    // No numeric column found: fall back to the first column that isn't the
    // group key, so the caller still gets a distinct key (or undefined when the
    // group key is the only column, which the views treat as "render a table").
    return Object.keys(row).find((k) => k !== exclude);
}

export function isDateLike(value: unknown): boolean {
    if (typeof value !== 'string') {
        return false;
    }

    return /^\d{4}-\d{2}-\d{2}/.test(value);
}

// The plan is authoritative for date granularity: a column appears in
// `plan.groupBy` exactly when the user asked for a date-trunc'd breakdown and
// the bucket on that entry says how coarse to render. Sniffing the rows
// instead would mis-label naturally Jan-1st dates as year-truncated.
export function bucketForColumn(plan: Plan, column: string): Bucket | null {
    const key = plan.groupBy.find((k) => k.field === column);

    return key === undefined ? null : key.bucket;
}

function normalizeGroupLabel(value: string): string {
    return value
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/gi, '')
        .toLowerCase();
}

function isNonInformativeGroupValue(value: unknown): boolean {
    if (value === null || value === undefined) {
        return true;
    }

    if (typeof value !== 'string') {
        return false;
    }

    const normalized = normalizeGroupLabel(value);

    return normalized === '' || NON_INFORMATIVE_GROUP_LABELS.has(normalized);
}

export function filterPresentationRows(
    rows: QueryRow[],
    plan: Plan,
): QueryRow[] {
    const categoricalGroupFields = plan.groupBy
        .filter((key) => key.bucket === 'none')
        .map((key) => key.field);

    if (categoricalGroupFields.length === 0) {
        return rows;
    }

    const filtered = rows.filter((row) =>
        categoricalGroupFields.every(
            (field) => !isNonInformativeGroupValue(row[field]),
        ),
    );

    return filtered.length > 0 ? filtered : rows;
}

// Format a group-by value using the bucket from the query plan, so date-truncated
// columns render as "2011" / "Mar 2011" / "15 Mar 2011" instead of raw ISO strings.
export function formatBucketLabel(
    value: unknown,
    bucket: Bucket,
    locale: string,
): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (bucket === 'none' || typeof value !== 'string' || !isDateLike(value)) {
        return String(value);
    }

    if (bucket === 'year') {
        return value.slice(0, 4);
    }

    // Parse the calendar date from its components and build a *local* date, so
    // a bare "2020-03-15" renders as the 15th everywhere. `new Date("2020-03-15")`
    // would parse as UTC midnight and shift a day earlier west of UTC.
    const [year, month, day] = value.slice(0, 10).split('-').map(Number);

    if (
        year === undefined ||
        month === undefined ||
        day === undefined ||
        !Number.isFinite(year) ||
        !Number.isFinite(month) ||
        !Number.isFinite(day)
    ) {
        return value;
    }

    const date = new Date(year, month - 1, day);

    // Reject impossible components (e.g. month 13, day 45) instead of letting
    // the Date constructor silently roll them over into a different date.
    if (
        date.getFullYear() !== year ||
        date.getMonth() !== month - 1 ||
        date.getDate() !== day
    ) {
        return value;
    }

    if (bucket === 'month') {
        return date.toLocaleDateString(localeTag(locale), {
            month: 'short',
            year: 'numeric',
        });
    }

    return date.toLocaleDateString(localeTag(locale), {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export type TimeGranularity = 'year' | 'month' | 'day';
export type TimePoint = { x: string; value: number };

// 80%+ on Jan 1 / day 1 is enough: a single off-day point shouldn't demote
// the whole axis to daily ticks.
const GRANULARITY_THRESHOLD = 0.8;

// A date_trunc bucket arrives as an ISO timestamp ("2001-03-01T00:00:00.000").
// Infer how coarse the series is so ticks render — and gaps fill — at the right
// step. The plan's bucket would be authoritative, but the timeseries view keys
// off the returned rows, so sniffing the values keeps the two paths in sync.
export function detectTimeGranularity(xs: string[]): TimeGranularity {
    if (xs.length === 0) {
        return 'day';
    }

    const onJan1 = xs.filter((x) => /^\d{4}-01-01/.test(x)).length;
    const onDay1 = xs.filter((x) => /^\d{4}-\d{2}-01/.test(x)).length;

    if (onJan1 / xs.length >= GRANULARITY_THRESHOLD) {
        return 'year';
    }

    if (onDay1 / xs.length >= GRANULARITY_THRESHOLD) {
        return 'month';
    }

    return 'day';
}

// Daily series over decades would balloon into thousands of buckets; past this
// we leave the points untouched and let the area chart smooth over the gaps.
const MAX_FILLED_BUCKETS = 731;

// SoQL's `GROUP BY date_trunc_*` only emits buckets that have rows, so a month
// (or year/day) with zero registrations simply vanishes and a line chart bridges
// straight over it — implying continuity that isn't there. Re-insert the missing
// buckets as explicit zeros across the observed span so gaps read as gaps.
// `points` must be sorted ascending by `x`.
export function fillTimeBuckets(
    points: TimePoint[],
    granularity: TimeGranularity,
): TimePoint[] {
    if (points.length < 2) {
        return points;
    }

    const valueByDay = new Map<string, number>();

    for (const point of points) {
        valueByDay.set(point.x.slice(0, 10), point.value);
    }

    // length ≥ 2 guarantees first and last exist; the assertions keep tsc happy
    // under noUncheckedIndexedAccess without runtime ceremony.
    const first = points[0]!;
    const last = points[points.length - 1]!;
    const keys = enumerateBuckets(
        first.x.slice(0, 10),
        last.x.slice(0, 10),
        granularity,
    );

    if (keys.length === 0 || keys.length > MAX_FILLED_BUCKETS) {
        return points;
    }

    // Re-attach the canonical time suffix so every x — observed or filled —
    // parses identically (local midnight) in the tick/label formatters.
    return keys.map((key) => ({
        x: `${key}T00:00:00.000`,
        value: valueByDay.get(key) ?? 0,
    }));
}

// Enumerate every YYYY-MM-DD bucket key from `first` to `last` inclusive at the
// given granularity. Returns [] when either bound is unparseable.
function enumerateBuckets(
    first: string,
    last: string,
    granularity: TimeGranularity,
): string[] {
    const [fy, fm, fd] = first.split('-').map(Number);
    const [ly, lm, ld] = last.split('-').map(Number);

    if (
        fy === undefined ||
        fm === undefined ||
        fd === undefined ||
        ly === undefined ||
        lm === undefined ||
        ld === undefined ||
        [fy, fm, fd, ly, lm, ld].some((n) => !Number.isFinite(n))
    ) {
        return [];
    }

    const keys: string[] = [];

    if (granularity === 'year') {
        for (let y = fy; y <= ly; y++) {
            keys.push(`${pad(y, 4)}-01-01`);
        }

        return keys;
    }

    if (granularity === 'month') {
        let y = fy;
        let m = fm;

        while (y < ly || (y === ly && m <= lm)) {
            keys.push(`${pad(y, 4)}-${pad(m, 2)}-01`);
            m += 1;

            if (m > 12) {
                m = 1;
                y += 1;
            }
        }

        return keys;
    }

    // Step in UTC days so DST and month lengths can't drift the cursor.
    const end = Date.UTC(ly, lm - 1, ld);

    for (
        let cursor = Date.UTC(fy, fm - 1, fd);
        cursor <= end && keys.length <= MAX_FILLED_BUCKETS;
        cursor += 86_400_000
    ) {
        const date = new Date(cursor);
        keys.push(
            `${pad(date.getUTCFullYear(), 4)}-${pad(date.getUTCMonth() + 1, 2)}-${pad(date.getUTCDate(), 2)}`,
        );
    }

    return keys;
}

function pad(value: number, length: number): string {
    return String(value).padStart(length, '0');
}

// 5 chart slots in the design system. We cycle for groups with more categories.
const CHART_PALETTE_SIZE = 5;

export function chartColor(index: number): string {
    return `var(--chart-${(index % CHART_PALETTE_SIZE) + 1})`;
}

export function formatPercent(ratio: number, locale: string): string {
    if (!Number.isFinite(ratio)) {
        return '—';
    }

    return new Intl.NumberFormat(localeTag(locale), {
        style: 'percent',
        maximumFractionDigits: ratio < 0.1 ? 1 : 0,
    }).format(ratio);
}
