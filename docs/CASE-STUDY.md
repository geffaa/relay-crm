# Case study — Relay, a WhatsApp-first sales CRM with explainable AI

**Role:** full-stack design & build (Laravel, PostgreSQL, Vue 3) · **Timeline:** MVP in ~3 weeks
**Live artifacts:** [README](app/README.md) · [screenshots](screenshots/) · [2-minute demo video](demo.webm)

## The client problem (persona: 3–30 person sales team at an agency/SMB)

Teams that sell on WhatsApp keep context on personal phones. When a rep is
leave or quits, conversations and follow-ups disappear with them. Big CRMs
(HubSpot, Pipedrive) bolt WhatsApp on as an expensive add-on; WhatsApp inbox
tools (Wati, Respond.io) have weak pipelines; and lead scoring everywhere is a
number without reasons — so reps don't trust it.

Relay's answer is one workspace where **every WhatsApp conversation is part of
its deal**, and where AI is only allowed to speak with citations.

## What the MVP delivers

1. **Pipeline that sees stuck deals.** Custom stages carry a "stale after N
   days" limit; deals past the limit are flagged hot on the board and
   auto-generate tasks in the rep's Today queue. Marking Lost demands a reason,
   which feeds the manager's lost-reasons report.
2. **A real WhatsApp inbox.** Meta Cloud API integration with signed webhooks,
   idempotent ingest (duplicate `wamid` never duplicates a message), E.164
   contact dedup, round-robin assignment within working hours, message
   statuses, and the 24-hour service window — free-form replies lock when it
   closes and only approved templates may go out. The public demo runs the
   same pipeline against a `simulated` channel adapter, driven by an
   admin-only "simulate inbound" button, and says so plainly.
3. **AI that shows its work.**
   - *Summaries* must cite the message ids they are grounded in; points whose
     citations don't resolve to real messages are **rejected before storage**
     (tested). Every summary shows its model and prompt version.
   - *Reply drafts* land in the composer as editable text — sending is always a
     human click.
   - *Lead scoring* is explainable by construction: a deterministic structured
     score (0–70) from visible signals, plus a bounded AI adjustment (−15..+30)
     with 1–3 concrete reasons; managers can override with a reason, and the
     override survives recomputation.
4. **A work queue instead of a dashboard.** Reps start in *Today*: stale deals,
   unreplied leads (computed against the company's actual working hours),
   manual tasks — with inline actions and keyboard shortcuts (j/k, E, R, ⌘K).

## Engineering decisions worth noting

- **Tenancy as a trait, not discipline.** One `BelongsToCompany` trait gives
  every model a global scope bound by middleware; `company_id` is never mass
  assignable, so tenant isolation is enforced by construction and verified by
  tests on every route (foreign id → 404).
- **One ingest path, two transports.** Live Meta webhooks and the demo
  simulator feed the same `ProcessWhatsAppEvent` job — the demo is not a
  shortcut around the code, it's the code.
- **AI degradation is graceful.** API failure returns an error note; the inbox
  itself never blocks. Every run is metered (`ai_runs`: model, tokens, status)
  and visible in Settings.
- **Numbers with receipts.** The manager dashboard's win rate, pipeline value
  and lost-reason aggregates are asserted in tests to equal direct queries.

## Verification

- **64 automated tests** (Pest): unit (E.164 normalization, score math) +
  feature (webhook signature → 401, `wamid` dedup, 24h window enforcement,
  tenant isolation, merge/conversion dedup safety, AI output validation).
- **PHPStan level 5** with zero errors; Pint for style.
- **Playwright E2E** (headless): simulated lead enters → rep opens thread →
  AI-drafted reply is edited and sent → message lands on the contact timeline.

## What I'd build next (already mapped)

Email channel in the same thread, sequences with reply-stop, forecast per rep,
quote PDFs, custom fields — see [FEATURE-MAP](FEATURE-MAP.md) for the phase 2
breakdown compared against Haliqq OS.
