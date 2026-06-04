# Rwanda FDA Frontend — Phase 4 Design Spec

**Date:** 2026-06-04  
**Phase:** 4 — Rwanda FDA Frontend  
**Stack:** Next.js 14 (App Router) · shadcn/ui · Tailwind CSS · TypeScript  
**Port:** 3000

---

## Overview

A clean, minimal, professional government dashboard for Rwanda FDA analysts. Covers the Post-Market Surveillance (PMS) module — specifically product recalls and batch management. Includes an AI chat interface that surfaces cross-border regulatory intelligence via the shared ontology service.

---

## Architecture

### Project Structure

```
rwanda-fda/frontend/
├── app/
│   ├── layout.tsx           # Root layout — sidebar + main content area
│   ├── page.tsx             # Redirects to /recalls
│   ├── recalls/
│   │   ├── page.tsx         # Recall list with status filter
│   │   ├── new/page.tsx     # Create recall form
│   │   └── [id]/page.tsx    # Recall detail
│   ├── batches/
│   │   ├── page.tsx         # Batch list
│   │   └── [id]/page.tsx    # Batch detail with linked recalls
│   └── ai/
│       └── page.tsx         # AI chat interface
├── components/
│   ├── Sidebar.tsx          # Fixed sidebar with nav links
│   ├── StatusBadge.tsx      # Active/Completed/Closed pill using shadcn Badge
│   └── CitationLink.tsx     # Parses [ObjectType NMRA:id] into clickable links
├── lib/
│   └── api.ts               # Typed fetch wrappers for all backend calls
└── next.config.js           # Rewrites /api/* → http://localhost:8001/api/*
```

### Dependencies

- Next.js 14 (App Router)
- shadcn/ui (Button, Table, Badge, Card, Input, Textarea, Select, Separator)
- Tailwind CSS (via shadcn init)
- TypeScript

No additional state management libraries. No React Query. Server Components for data pages; Client Component only for the AI chat page.

---

## Layout & Navigation

**Two-column layout:**
- Fixed 220px sidebar (left)
- Main content area (right, fills remaining width)

**Sidebar:**
- Rwanda FDA wordmark at top (text only)
- Nav links: Recalls · Batches · AI Query
- Active link: left border accent (dark green)
- Footer: "Post-Market Surveillance Module"

**Top bar** (inside main content, not global):
- Page title
- Primary action button where applicable ("New Recall" on recall list)

**Colour palette:**
- Sidebar background: `#1a2e1a`
- Sidebar text: white / muted white for inactive
- Accent: `#2d6a2d`
- Page background: `#f8f9fa`
- Cards: white with thin border

**Status badge colours:**
- Active → red
- Completed → grey
- Closed → slate

---

## Pages

### `/recalls` — Recall List

- Table columns: Recall No. · Product · Batch · Classification · Status · Date Issued · Actions
- "View" link per row → `/recalls/[id]`
- Status filter via `Select` (All / Active / Completed / Closed) — passes `?status=` query param
- "New Recall" button in top bar → `/recalls/new`
- Data: `GET /api/product-recalls?status=`

### `/recalls/new` — Create Recall

- Card-wrapped form
- Fields:
  - Batch (Select — populated from `GET /api/batches`)
  - Manufacturer (Select — populated from `GET /api/manufacturers`)
  - Recall Number (Input)
  - Classification (Select — Class I / Class II / Class III)
  - Reason (Textarea)
  - QC Summary (Textarea)
  - Date Issued (Input type=date)
  - Scope (Input, optional)
  - Status (Select — active / completed / closed, defaults to active)
- Submit: `POST /api/product-recalls`
- On success: redirect to `/recalls/[id]`
- On error: inline field-level validation messages

### `/recalls/[id]` — Recall Detail

- Two Cards side by side:
  - Left: recall metadata (number, classification, status badge, date issued, scope, reason, QC summary)
  - Right: linked batch info + manufacturer info (loaded via recall relations)
- Status badge prominent at top of page
- **Requires:** `ProductRecallController::show` must eager-load relations — add `$productRecall->load(['batch.product', 'manufacturer'])` before returning (one-line change)

### `/batches` — Batch List

- Table columns: Batch No. · Product · Manufacturer · Manufacture Date · Expiry Date · Actions
- "View" link per row → `/batches/[id]`
- Data: `GET /api/batches`

### `/batches/[id]` — Batch Detail

- Batch metadata Card (batch number, product, manufacturer, dates)
- Nested Table of linked recalls for this batch
  - Fetched via `GET /api/product-recalls` and filtered client-side by `batch_id`
- **Requires:** `BatchController::show` must eager-load relations — add `$batch->load(['product', 'manufacturer'])` before returning (one-line change)

### `/ai` — AI Chat

- Client Component (requires useState for chat history)
- Layout: scrollable chat history above, Textarea + Send button fixed at bottom
- User messages: right-aligned
- AI responses: left-aligned in styled container
- Citations in AI answer (`[Recall RWANDA_FDA:uuid]`) parsed by `CitationLink.tsx` → clickable links to `/recalls/uuid` or `/batches/uuid`
- Sources panel below each AI response: collapsible list of raw source chunks
- API call: `POST /api/ai/query` with `{ question: string }`

---

## Data Flow & API

### Next.js Rewrite (next.config.js)

All `/api/*` requests rewritten to `http://localhost:8001/api/*` (Rwanda FDA Laravel backend).

### lib/api.ts — Typed Fetch Wrappers

```
GET  /api/product-recalls          → recall list
GET  /api/product-recalls/:id      → recall detail
POST /api/product-recalls          → create recall
GET  /api/batches                  → batch list
GET  /api/batches/:id              → batch detail
GET  /api/manufacturers            → manufacturer list (for form selects)
GET  /api/products                 → product list
POST /api/ai/query                 → AI proxy
```

### Laravel AI Proxy (new endpoint on rwanda-fda/backend)

- Route: `POST /api/ai/query`
- Receives: `{ question: string }`
- Appends: `nmra_id: "RWANDA_FDA"`
- Forwards to Python AI service via HTTP
- Returns response as-is: `{ answer: string, sources: [{object_id, chunk_text}] }`

### CitationLink Component

Regex: `/\[(\w+)\s+([\w_]+):([\w-]+)\]/g`

Splits answer text into segments. Each citation token (`[Recall RWANDA_FDA:uuid]`) becomes a `<Link>` navigating to:
- `Recall` / `ProductRecall` → `/recalls/uuid`
- `Batch` → `/batches/uuid`
- All other types (Manufacturer, Product, etc.) → rendered as styled plain text (no detail page in scope)

---

## What Is Not In Scope

- Authentication / login
- Creating batches or products via the frontend (data seeded via API / tests)
- NAFDAC frontend (Phase 6, future)
- Pagination (prototype data volume is small)
- Real-time updates / websockets
