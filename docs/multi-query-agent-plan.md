# Plan: Multi-step query engine (declarative-first, with dependent steps; agentic loop as escalation)

> Status: proposal / not yet implemented. As of 2026-05-27 the codebase is
> single-shot: `QueryPlanAgent` implements `HasStructuredOutput` (no tools) and
> emits exactly one `Plan`. This documents the agreed direction for answering
> questions that need more than one SoQL query.
>
> **Design call (2026-05-27).** The goal is to solve materially more complex
> questions *without* regressing cost (the prime constraint) or the search-box
> latency. The engine grows a **unified multi-step data model** with these
> capabilities, in priority order:
> 1. **Declarative program (default).** One completion emits a *program* of
>    sub-queries plus a `Presentation`; PHP runs them and computes the figure.
>    Same cost/latency as today. Covers ratios, percentages, comparisons,
>    multi-metric breakdowns.
> 2. **Dependent steps (core, not deferred).** A later query may filter on an
>    earlier query's *result value* (e.g. look up a plate, then count that model).
>    PHP resolves the reference between steps — **still one completion**, and the
>    model never sees the intermediate rows. This is the cheap, safe way to answer
>    the "how many of my car's model are on the road?" class.
> 3. **Agentic loop (escalation, deferred).** The same query + presentation
>    contracts run inside a tool loop, only for chains where the next query
>    requires the model to *reason over* prior results (not a direct value
>    substitution). Opt-in, flag-gated, built when such a case is in scope.
>
> Declarative (with dependent steps) is a strict subset of the loop's data
> contract, so the loop stays purely additive — nothing built earlier is wasted.
>
> The `laravel/ai` internals this plan depends on were verified against **v1.2.7**
> on 2026-05-27 (see §15); recheck on package upgrade.

## 0. The governing insight from two constraints

Two properties to preserve, which combine into one principle:

1. **Cost** — the LLM must never ingest full row dumps.
2. **Safety** — the LLM's output is never shown to the user, so there is nothing
   to inject *into* and nothing to exfiltrate.

**Principle: the LLM orchestrates, but never narrates.** It only ever emits
*structured* output (a query program, or — in loop mode — tool calls plus a
structured final answer). The displayed numbers and rows come from the
deterministic engine (`PlanRunner`) over trusted RDW data — never typed by the
model. The only free text it produces remains the one-sentence `explanation`
already shown today (no new surface).

This holds for **all** modes, and declarative mode — *including dependent steps* —
is strictly safer: the model emits the whole program before any query runs, so no
RDW data ever re-enters its context. Even when q2 filters on q1's result, **PHP**
substitutes that value; the model never sees it. Loop mode is the only place
bounded tool results re-enter the model, and §4 bounds that.

## 1. Capabilities on one data model

```
                    User prompt (wrapped, untrusted)
                                  │
              ┌───────────────────┴───────────────────┐
              ▼ DEFAULT                                ▼ ESCALATION (flag/opt-in)
   QueryProgramAgent                          QueryLoopAgent
   (HasStructuredOutput)                      (HasTools + HasStructuredOutput)
              │                                        │
   1 completion emits:                       loop (framework step-capped):
     QueryProgram {                            ├─ run_query(plan) ← validated
       queries: [q1, q2, …],   (q2 may          │    └ FULL rows → QueryLedger
                  reference q1's result)         │    └ BOUNDED summary → model
       presentation                            ├─ kind:"error" → model self-corrects
     }                                         └─ … → final Presentation
              │                                        │
              ▼                                        ▼
   PHP runs queries IN ORDER,             PHP already ran each query in-loop
   resolving {{qN.Field}} refs                        │
   from the ledger before each run                    │
              └───────────────────┬───────────────────┘
                                  ▼
                         QueryLedger { q1: RunnerResult, q2: … }
                                  │
                         Presentation { resultRef, display, derive?, explanation }
                                  │
                  Resolve refs + (optional) deterministic derive
                                  │
                  Persist (multi-step) + render with existing views
```

The existing deterministic core (`PlanSchema`, `PlanFactory`, `PlanRunner`,
display hints, frontend views) is **reused wholesale**. `Plan` stops being the
*final output* and becomes **one entry in a query program**. The ledger,
multi-step `QueryResult`, `Presentation`, and `derive` are identical across all
modes. The only difference between declarative-dependent and the loop is *who
decides the next query's value*: **PHP substitutes a known column** (declarative)
vs **the model reasons over results** (loop).

## 2. What each capability covers

`PlanRunner` already executes `groupBy` + aggregates in a single query, and
dependent steps cover plate/value lookups, so almost every realistic question is
answerable at flat cost:

| Question | Queries | Mode |
|---|---|---|
| "% of cars that are yellow" | 1 grouped (`group by color`) + `groupShare` | declarative |
| "% of Toyotas that are electric" | 1 (`where brand=Toyota, group by fuel`) + `groupShare` | declarative |
| "avg weight of Toyotas vs all cars" | 2 scalars + `ratio` | declarative |
| **"how many of my car's model are on the road? (1-ZTZ-08)"** | **2: lookup plate → count that model** | **declarative + dependent step** |
| "newest car of the brand with the most yellow ones" | model must pick a brand from q1's *ranked* results, then query | agentic loop |

The first four are flat-cost, single-completion. Only the last — where the model
must *reason over* a multi-row result to choose the next query — needs the loop.

## 3. The contracts the model speaks

### 3a. Sub-query (`Plan`) — schema unchanged
Today's `PlanSchema::build()` verbatim. The only semantic addition: a `where`
clause **value** may be either a literal (as today) or a single **reference token**
(§3b) — the JSON shape stays a required string, so OpenAI strict mode is
unaffected.

### 3b. Dependent-step references (the core new mechanism)
A `where` value of the exact form `{{<queryId>.<FieldEnumCase>}}` is a reference to
another query's result, resolved by PHP:

- **Grammar:** the *entire* value is one token — no concatenation, no literals
  mixed in. `<queryId>` is an earlier query's id; `<FieldEnumCase>` is a
  PascalCase `RegisteredVehicleField` (the same vocabulary the model already uses).
- **Backward-only:** a query may reference only queries earlier in the list. The
  list order *is* the execution order; no cycles possible.
- **Single-row lookups only:** the referenced query must return exactly one row
  (it's a lookup like "the vehicle with this plate"). `{{q1.Brand}}` resolves to
  that row's `Brand`. Multi-row "pick from a ranking" is explicitly the loop's job,
  not this mechanism's.
- **Resolution is deterministic and invisible to the model:** before running a
  query, PHP replaces each reference with the ledgered value, then casts it like
  any literal. The resolved value is never shown to the model.

Validation splits cleanly:
- **Structural (factory, → 422):** token well-formed; `<queryId>` exists and is
  earlier; `<FieldEnumCase>` is a known field.
- **Runtime (resolver, → unsupported / bounded retry):** the referenced query
  actually returned exactly one row and that row has a non-null value for the
  column.

### 3c. `QueryProgram` — the declarative output (structured)
```
QueryProgram {
  queries:      [ { id: "q1", plan: Plan }, { id: "q2", plan: Plan }, … ]  // 1..N, capped
  presentation: Presentation
}
```
- One completion. PHP runs each plan **in list order**, resolving §3b references
  from the ledger before each run, ledgering every result.
- **Cap** `queries` (e.g. ≤ 4) in schema description + factory.
- Prefer the **fewest** queries (see §10 examples).

### 3d. `Presentation` — the final answer (structured)
```
Presentation {
  resultRef:   string         // which ledgered query to display, e.g. "q2", or "derived"
  display:     DisplayHint     // reuse the existing enum
  derive:      null | Derive   // deterministic combine (see 3e)
  explanation: string          // one sentence, user's language (untrusted, as today)
}
```
- `derive` null → render `ledger[resultRef].rows` with `display` via existing views.
- `derive` set → **PHP computes the figure** and renders a single-figure view.

`QueryPlanAgent` has no `#[Strict]` today, so structured output is currently sent
with `strict: false` (`buildSchemaFormat(..., Strict::isAppliedTo($agent))`,
`vendor/laravel/ai/src/Gateway/OpenAi/Concerns/BuildsTextRequests.php:35`). Add
`#[Strict]` if we want OpenAI to hard-validate the program/presentation.

### 3e. `Derive` — deterministic combine
```
Derive =
  | { op: "groupShare",  source: resultRef, selector: { column, value } }   // 1 grouped query
  | { op: "percentage" | "ratio" | "difference" | "sum",
      numerator: resultRef, denominator: resultRef }                        // 2 scalar queries
```
All ops are pure PHP over ledgered numbers — fully unit-testable, never typed by
the model.

## 3f. Worked example — the plate→model question

> "How many of my car's model are on the road? 1-ZTZ-08"

```jsonc
QueryProgram {
  queries: [
    { id: "q1", plan: {
        where: [{ field: "LicensePlate", op: "=", value: "1-ZTZ-08" }],
        select: ["Brand", "CommercialName"], aggregates: [], groupBy: [],
        orderBy: [], limit: 1, display: "record",
        explanation: "Het voertuig met kenteken 1-ZTZ-08."
    }},
    { id: "q2", plan: {
        where: [
          { field: "Brand",          op: "=", value: "{{q1.Brand}}" },
          { field: "CommercialName", op: "=", value: "{{q1.CommercialName}}" }
        ],
        select: [], groupBy: [],
        aggregates: [{ fn: "count", field: "*", alias: "n" }],
        orderBy: [], limit: 1, display: "count",
        explanation: "Aantal voertuigen van hetzelfde merk en model."
    }}
  ],
  presentation: {
    resultRef: "q2", display: "count", derive: null,
    explanation: "Er rijden er net zoveel van hetzelfde merk en model als 1-ZTZ-08."
  }
}
```

Execution: PHP runs q1 → `{ Brand: "VOLKSWAGEN", CommercialName: "UP" }`, substitutes
into q2's `where`, runs q2 → `58408`. **One LLM completion**, two sequential RDW
calls, zero RDW rows shown to the model. (Verified live on 2026-05-27.)

**Plate normalisation:** RDW stores plates uppercase, no separators (`1ZTZ08`). The
frontend already has `detectPlate()` (`resources/js/pages/query/plate.ts`). Mirror
it server-side: normalise any `LicensePlate` filter value (strip non-alphanumerics,
uppercase) in `PlanRunner::castValue()` / a small `PlateNormaliser`, so the model
can emit `1-ZTZ-08`, `1ztz08`, etc. and still match.

## 4. Bounded data handling (cost + safety lever)
- **Declarative mode (incl. dependent steps):** rows never reach the model — it
  emits the whole program before any query runs, and PHP substitutes references.
  The ledger holds full `RunnerResult`s purely for rendering + persistence.
  Cheapest and safest.
- **Loop mode only:** `run_query` returns a small JSON string shaped by result
  type; full rows go to the ledger out-of-band:

| Result type | Returned to model | Withheld |
|---|---|---|
| Scalar aggregate (count/avg, no groupBy) | `{ id, kind:"scalar", value, soqlOk:true }` | — |
| Grouped/aggregate (small) | `{ id, kind:"grouped", rowCount, columns, rows: <capped, e.g. 20> }` | rows beyond N |
| Row listing (`select`, many rows) | `{ id, kind:"rows", rowCount, columns }` **only** | **all row values** |
| Empty | `{ id, kind:"empty", rowCount:0 }` | — |
| Error (RDW 400 / validation) | `{ id, kind:"error", message:<sanitised, capped> }` | stack traces, internals |

Rules (loop mode): row dumps never returned; grouped results capped; error messages
sanitised + length-capped before re-entering context.

## 5. Self-correction (orthogonal to the loop)
Failing SoQL does **not** require the agentic loop:
- Most invalid plans are prevented up front — `PlanSchema` enum-constrains field
  names, operators, aggregate functions, and known field values.
- For the rest, a **bounded single retry**: catch the
  `QueryExecutionException`/`InvalidArgumentException` (or a failed reference
  resolution), feed the sanitised error back, ask the agent to fix the program,
  retry **once**. One extra completion only on failure.
- A dependent-step lookup that returns 0 or >1 rows (e.g. an unknown plate) is a
  resolution failure → bounded retry, else a graceful `unsupported` ("kenteken niet
  gevonden").

## 6. Token & cost accounting
- **Declarative mode, incl. dependent steps = one completion.** Same cost/latency
  profile as today; the expensive field-catalog system prompt is paid once. The
  plate→model question costs the same as "how many Toyotas".
- **Bounded retry** adds one completion only on failure.
- **Loop mode:** the final `usage` is **already cumulative** — the gateway sums
  every step via `combineUsage($steps)` (`ParsesTextResponses.php:170,424-430`). No
  manual accumulation: read `$response->usage` as today; feed `CostEstimator`/
  `TokenUsage` unchanged. Cost is cumulative because OpenAI bills each turn's full
  retained input (`previous_response_id`); the `cached_tokens` discount is folded in.
- **Caching is automatic on OpenAI — nothing to build.** Continuation turns don't
  resend the system prompt (`continueWithToolResults` sends only
  `previous_response_id` + new tool results, `ParsesTextResponses.php:235-247`); no
  `cache_control` knob exists; `extractUsage` reads `cached_tokens` and
  `CostEstimator` discounts them.

## 7. Backend changes (file by file)

Declarative-mode (incl. dependent steps) first; loop-mode items marked **[loop]**.

- **`app/Services/QueryPlan/QueryProgramSchema.php` + `QueryProgram.php` +
  `QueryProgramFactory.php`** *(new)* — `QueryProgramSchema` wraps
  `PlanSchema::build()` in a capped `queries[]` array plus a `presentation` object.
  `QueryProgramFactory::fromArray()` builds each `Plan` via the existing
  `PlanFactory`, validates ids are unique, and performs the **structural reference
  validation** (§3b: backward-only, known field, well-formed token) and the
  `presentation` ref/derive validation; throws `InvalidArgumentException`
  (existing 422 path) otherwise. **Verify** OpenAI strict-schema nesting/property
  limits tolerate the extra array level (today's single plan is ~3 levels deep;
  `queries[]` adds one). Note `value` stays a string, so no per-property
  discriminator is needed (the existing schema comment already warns the builder
  lacks one).
- **`app/Services/QueryPlan/StepReferenceResolver.php`** *(new)* — pure. Given a
  `Plan` and the `QueryLedger`, replace each `{{qN.Field}}` `where` value with the
  ledgered value. Enforces the **runtime** rules (referenced query ran, returned
  exactly one row, column present + non-null). Returns a resolved `Plan` or throws a
  typed `StepReferenceException` (→ retry / unsupported). Fully unit-testable.
- **`app/Services/QueryPlan/Presentation.php` + `PresentationFactory.php`** *(new)*
  — typed terminal answer; ref + derive validation (shared by both modes).
- **`app/Services/QueryPlan/Derivation.php`** *(new)* — pure: `groupShare` +
  `percentage/ratio/difference/sum`. Unit-testable.
- **`app/Services/QueryPlan/QueryLedger.php`** *(new)* — holds
  `id => RunnerResult (+ sub-Plan + summary)`; resolves refs for `Presentation` and
  feeds the `StepReferenceResolver`; provides the transcript for persistence.
- **`app/Services/QueryPlan/PlanRunner.php`** — add server-side **plate
  normalisation** for `LicensePlate` filter values in `castValue()` (mirror
  `plate.ts::detectPlate`). Otherwise unchanged.
- **`app/Services/QueryPlan/PlanFactory.php`, `PlanSchema.php`** — **unchanged.**
- **`app/Ai/Agents/QueryPlanAgent.php`** — point `schema()` at
  `QueryProgramSchema`; keep `provider() = OpenAI`; optionally `#[Strict]`. (Rename
  to `QueryProgramAgent` if clearer.)
- **`app/Actions/Rdw/RunNaturalLanguageQuery.php`** — drive declarative mode: get
  the `QueryProgram`, then **for each query in order**: resolve references via
  `StepReferenceResolver`, run via `PlanFactory`+`PlanRunner`, ledger the result.
  Resolve `Presentation` (+ optional derive), assemble a multi-step `QueryResult`.
  Wrap execution with the §5 bounded retry. Token usage read as today.
- **`app/Services/QueryPlan/QueryResult.php`** — carry `steps`
  (`{id, plan, soql, url, rowCount}`) + resolved `presentation` + final displayed
  `rows` + `display`. Keep `plan`/`rows`/`display` accessors for the *presented*
  result so the controller JSON changes minimally.
- **`app/Http/Controllers/Rdw/QueryController.php`** — response JSON gains `steps`
  and `presentation`/`derived`; keeps `rows`/`displayHint`/`soql`/`url` pointing at
  the presented result for backward compatibility. Error mapping unchanged.
- **[loop] `app/Ai/Tools/RunQueryTool.php`** *(new)* — `implements Tool`;
  `schema()` → `PlanSchema::build()`; `handle(Request)` runs the plan, writes the
  ledger, returns the §4 bounded summary. **Catching is load-bearing:** the gateway
  does not wrap `handle()` (`InvokesTools::executeTool()` calls it bare,
  `InvokesTools.php:42`), so an uncaught throw aborts the run with no
  self-correction. MUST catch `RateLimitException`/`QueryExecutionException`/
  `InvalidArgumentException` → `kind:"error"`.
- **[loop] `app/Ai/Agents/QueryLoopAgent.php`** *(new)* — `tools()` returns the
  **container-resolved** `RunQueryTool` (scoped `QueryLedger` injected); `schema()`
  → `PresentationSchema`; add a `maxSteps()` method (**mandatory**, ~6 — default
  with one tool is `round(count($tools)*1.5)=2`, too few; `ParsesTextResponses.php:125`).
  **Verified:** OpenAI supports tools + structured output together.
- **[loop] `QueryLedger` must be `scoped()`** so tool + action share one instance;
  keep the loop synchronous (`->queue()` would change the scope boundary). Handle
  the **step-cap edge**: hitting `maxSteps` mid-tool-call yields empty `structured`
  (`json_decode('') → []`, `ParsesTextResponses.php:164-165`) → graceful
  `unsupported`, never a 500.

## 8. Persistence (`QueryRun`) — Mongo is schemaless, so additive
- Add `steps` (array of per-query `{plan, soql, url, rowCount}`, with the *resolved*
  plan so the debug pane shows the real `where` values) and `presentation`
  (`{resultRef, display, derive, explanation}`); keep top-level
  `plan`/`soql`/`url`/`rows`/`display_hint` populated **from the presented result**
  so sharing by slug renders with no migration, "popular queries" keeps working,
  and **old runs keep working** (they lack `steps`/`presentation`; the read path
  falls back to the single-plan shape).
- **`PlanPresenter`** — add `stepsToArray()` / `presentationToArray()`; keep
  `normalisePersisted()` and extend it to leave legacy single-plan docs untouched.
- **`PersistQueryRun`** — accept the step list + presentation; store `rows` =
  presented rows. Slug logic unchanged.

## 9. Frontend (`resources/js/pages/query/`)
- **`types.ts`** — add `Step`, `Presentation`, `Derived`; extend
  `QueryResult`/`SharedRun`/`RunResponse` with optional
  `steps`/`presentation`/`derived` (optional → old shared runs still typecheck).
- **`views/result-body.tsx`** — when `derived` is present, render a new
  **`DerivedView`**; otherwise unchanged: switch on `displayHint`, pass the
  *presented* query's sub-`plan` so `pie`/`bars`/etc. work as-is.
- **`views/derived-view.tsx`** *(new)* — deterministic figure + group/total (or
  numerator/denominator) context. No LLM text beyond `explanation`.
- **Debug pane** — list **each step's** SoQL/URL (currently shows one), using the
  *resolved* plans so a dependent step shows `merk=VOLKSWAGEN`, not `{{q1.Brand}}`.

## 10. Prompt changes (`PromptBuilder`)
- Reframe from "emit one plan" to "emit a **query program**: the fewest sub-queries
  needed, plus a `Presentation`."
- Rules: prefer **one** query (a grouped query already yields shares — use
  `groupShare`); add a second query only for different filters/denominators **or**
  when a value must be looked up first (dependent step); to reference an earlier
  result use **exactly** `{{qN.FieldName}}` as a whole `where` value (only for a
  query that returns a single row); use `derive` for figures rather than computing
  yourself; **never** put computed numbers in `explanation`; cap at N queries.
- Keep the `<user_question>` wrapping and existing refusal/injection rules verbatim.
- Worked examples: (a) "% yellow" → one grouped query + `groupShare`; (b) "avg
  weight Toyota vs all" → two scalars + `ratio`; (c) **"how many of my car's model
  — 1-ZTZ-08" → §3f: plate lookup then `{{q1.Brand}}`/`{{q1.CommercialName}}`
  count**; (d) "how many Toyotas" → single passthrough (don't over-decompose).
- **[loop]** variant adds: on a `kind:"error"` result, fix the plan and retry once
  or twice, else present an `unsupported` explanation.

## 11. Security analysis (the prompt-injection concern)
**Preserved (and strengthened in declarative mode):**
- User input stays wrapped and untrusted.
- The model emits **only structured output** — no free-text answer channel.
- **Declarative mode never feeds RDW data back into the model**, *including
  dependent steps*: q1's `Brand` is substituted into q2 **by PHP**; the model
  emitted `{{q1.Brand}}` and never sees the resolved value or any row. Strictly
  less surface than the loop.
- Reference resolution is itself bounded: only `where` *values*, only single-row
  lookups, only known fields — a hostile RDW value can flow into a *filter*, but
  the worst case is an empty/odd result set, never code or model-context injection.
- The lone free-text field shown is the existing one-sentence `explanation`.

**Loop-mode addition (and mitigation):** the model reads bounded tool results;
RDW is a curated government dataset → low injection risk; error strings sanitised +
length-capped; step/query/token caps bound abuse; uncaught tool errors fail closed.

## 12. Testing
- **Unchanged:** `PlanFactory`/`PlanRunner` suites.
- **New unit:**
  - `Derivation` math (`groupShare` + scalar ops; divide-by-zero / empty group).
  - `QueryProgramFactory` — query cap, duplicate ids, **forward/self reference
    rejected**, unknown field in a ref, unknown `resultRef`, derive operand
    mismatch → 422.
  - `StepReferenceResolver` — happy path (`{{q1.Brand}}` → value); referenced query
    returned 0 rows / >1 rows / null column → `StepReferenceException`; non-ref
    literals pass through untouched.
  - Plate normalisation (`1-ZTZ-08`, `1ztz08`, `1 ZTZ 08` → `1ZTZ08`).
- **New feature (declarative):**
  - "% yellow" → one query + `groupShare` figure.
  - Two-scalar ratio.
  - **plate→model (§3f)** — fake the agent to return the two-step program; fake RDW
    so q1 returns `{Brand:VOLKSWAGEN, CommercialName:UP}` and q2 returns count;
    assert q2's SoQL contains the *resolved* `merk=VOLKSWAGEN` and the presented
    figure is the count. Assert the model was prompted **once**.
  - Unknown plate → graceful `unsupported`.
  - Single-query passthrough not over-decomposed.
  - Bounded retry — first attempt 400s, retry succeeds.
- **[loop] harness:** `FakeTextGateway` executes the *real* tool when you queue a
  `ToolCall` (`handleFakeToolCalls`), so `[ToolCall, ToolCall, StructuredTextResponse]`
  runs `RunQueryTool::handle()` and returns the final `Presentation`. Assert (a)
  step cap stops the loop, (b) injected `kind:"error"` triggers a retry that
  succeeds, (c) full rows never appear in any string returned to the model.
- **Injection:** "ignore instructions…" still yields a structured refusal /
  `unsupported`; assert no free text leaks.

## 13. Rollout (incremental, low-risk)
- **Phase 1 — data model:** `QueryLedger`, multi-step `QueryResult`,
  `Presentation`, persistence + frontend types. Route today's single plan through
  it as a one-entry program. No user-visible change.
- **Phase 2 — declarative program + dependent steps + derive (the product):**
  `QueryProgram*`, `StepReferenceResolver`, `Derivation` (incl. `groupShare`),
  plate normalisation, `DerivedView`, prompt reframe with the four worked examples.
  This unlocks ratios, percentages, comparisons **and the plate→model class** at
  flat cost. Gate behind `config('rdwai.agent_mode')` = `single|program` for A/B +
  instant rollback.
- **Phase 3 — bounded self-correction retry:** one extra completion on query or
  reference failure; independent of the loop.
- **Phase 4 — agentic loop escalation (optional):** add `RunQueryTool` +
  `QueryLoopAgent` + guards on the *same* data model, for chains where the model
  must reason over multi-row results. Gate `agent_mode = loop`. Build when a real
  reasoning-chain question is in scope.
- **Phase 5 — polish:** richer debug pane, references in `orderBy`/aggregates if
  needed, tune caps/budget from real usage.

## 14. Open decisions
1. **Reference scope.** Start with references in `where` *values* only (covers the
   plate→model class). Extend to `orderBy`/aggregate operands later only if a real
   case needs it.
2. **Reference syntax.** `{{qN.Field}}` templated string (recommended — keeps the
   schema's "every value is a required string" invariant) vs a structured
   `{kind:"ref",…}` union (cleaner but fights the builder's lack of discriminators).
3. **Query cap** in a program (recommend ≤ 4), enforced at schema **and** factory.
4. **One agent + flag** vs two classes (`QueryProgramAgent` / `QueryLoopAgent`).
   Lean two classes: schemas differ; keeps each agent small.
5. **`explanation`** — keep LLM-generated or move to a templated, deterministic
   caption later. (Not blocking.)

## 15. Load-bearing assumptions — verified against `laravel/ai` v1.2.7
All resolved by reading the gateway source; recheck on package upgrade.
- ✅ **Structured output of nested arrays of objects** — `PlanSchema` already nests
  `array()->items(object)`, so `QueryProgram.queries[]` is expressible. Verify the
  added nesting level stays within OpenAI strict-schema limits (§7). References add
  **no** schema complexity (value stays a string).
- ✅ **[loop] Tools + structured output together on OpenAI** —
  `buildTextRequestBody` and `continueWithToolResults` send both `tools` and
  `text`(schema) every turn; `processResponse` loops then returns a
  `StructuredTextResponse` (`ParsesTextResponses.php:123-176`).
- ✅ **[loop] Cumulative token usage** — `combineUsage($steps)` sums all turns
  (`ParsesTextResponses.php:170,424-430`); no manual accumulation (§6).
- ⚠️ **[loop] Default step cap is 2** with one tool — set `maxSteps` explicitly (§7).
- ⚠️ **[loop] Uncaught tool errors abort the run** — the tool must catch and return
  `kind:"error"` (§7).
- ⚠️ **No `cache_control` knob; instructions not resent on continuation turns** —
  caching is automatic; nothing to build (§6).
