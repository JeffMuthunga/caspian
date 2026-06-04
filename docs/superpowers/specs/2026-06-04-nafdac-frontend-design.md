# NAFDAC Frontend — Phase 6 Design Spec

**Date:** 2026-06-04
**Phase:** 6 — NAFDAC Frontend
**Stack:** Next.js 14 (App Router) · shadcn/ui · Tailwind CSS · TypeScript
**Port:** 3001 (host) / 3000 (container)

---

## Overview

A mirror of the Rwanda FDA frontend (`rwanda-fda/frontend/`) adapted for NAFDAC analysts. Same six-page structure, same shadcn/ui components, same patterns — with NAFDAC branding, NAFDAC-specific column names, and an AI proxy on the NAFDAC Laravel backend that sends `nmra_id: "NAFDAC"` to the Python ontology service.

## Approach

Copy `rwanda-fda/frontend/` to `nafdac/frontend/`, then apply targeted changes:
- Port, branding, colour palette
- TypeScript interfaces and API field names
- Form fields and table columns
- docker-compose service

---

## Architecture

### Project Structure

```
nafdac/frontend/
├── app/
│   ├── layout.tsx
│   ├── page.tsx             # redirect → /recalls
│   ├── recalls/
│   │   ├── page.tsx         # recall list
│   │   ├── RecallsFilter.tsx
│   │   ├── new/page.tsx     # create recall form
│   │   └── [id]/page.tsx    # recall detail
│   ├── batches/
│   │   ├── page.tsx         # batch list
│   │   └── [id]/page.tsx    # batch detail + linked recalls
│   └── ai/
│       └── page.tsx         # AI chat — centrepiece of the demo
├── components/
│   ├── Sidebar.tsx          # navy branding
│   ├── StatusBadge.tsx      # reads recall_status field
│   └── CitationLink.tsx     # identical — citations are ontology IDs
├── lib/
│   └── api.ts               # NAFDAC field names, port 8002
└── next.config.mjs          # /api/* → http://localhost:8002
```

---

## Layout & Branding

- **Sidebar background:** `#1a1e2e` (dark navy — visually distinct from Rwanda FDA's `#1a2e1a` dark green)
- **Active link:** `bg-[#2d3a6a]` with `border-l-2 border-indigo-300`
- **Inactive links:** `text-indigo-200`
- **Sidebar wordmark:** "NAFDAC" / "Regulatory Authority"
- **Page background:** `#f8f9fa` (same)
- **Status badges:** same colour logic (active=red, completed=grey, closed=slate) reading `recall_status` field

---

## NAFDAC Field Mappings

### Manufacturer
| NAFDAC column | Display label |
|---|---|
| `company_name` | Manufacturer |
| `origin_country` | Country |
| `reg_no` | Registration No. |
| `authorization_status` | Status |

### Product
| NAFDAC column | Display label |
|---|---|
| `product_name` | Product |
| `inn_name` | Generic Name (INN) |
| `formulation` | Dosage Form |
| `potency` | Strength |
| `market_auth_number` | Market Auth. No. |

### Batch
| NAFDAC column | Display label |
|---|---|
| `lot_number` | Lot No. |
| `production_date` | Production Date |
| `expiry_date` | Expiry Date |
| `supplier_id` | Manufacturer (FK) |

### ProductRecall
| NAFDAC column | Display label |
|---|---|
| `alert_reference` | Alert Reference |
| `recall_reason` | Reason |
| `severity_grade` | Severity Grade (Grade I / II / III) |
| `laboratory_findings` | Laboratory Findings |
| `recall_status` | Status |
| `issue_date` | Date Issued |
| `affected_regions` | Affected Regions |
| `lot_id` | Batch (FK) |
| `supplier_id` | Manufacturer (FK) |

---

## Pages

### `/recalls` — Recall List
Table columns: Alert Ref. · Product · Lot No. · Severity Grade · Status · Date Issued · Actions
Filter by `recall_status`. "New Recall" → `/recalls/new`.

### `/recalls/new` — Create Recall
Form fields: Batch (Select → `lot_id`), Manufacturer (Select → `supplier_id`), Alert Reference, Severity Grade (Grade I/II/III), Recall Reason (Textarea), Laboratory Findings (Textarea), Issue Date, Affected Regions (optional), Status.
POST to `/api/product-recalls`. Redirect to `/recalls/[id]` on success.

### `/recalls/[id]` — Recall Detail
Left card: alert_reference, severity_grade, recall_status, issue_date, affected_regions, recall_reason, laboratory_findings.
Right card: lot_number, product_name, inn_name, potency, production_date, expiry_date, company_name, origin_country.
`notFound()` on fetch error.

### `/batches` — Batch List
Table columns: Lot No. · Product · Manufacturer · Production Date · Expiry Date · Actions.

### `/batches/[id]` — Batch Detail
Batch info card + linked recalls table (filter by `lot_id === batch.id` client-side).

### `/ai` — AI Chat (Demo Centrepiece)
Identical to Rwanda FDA AI chat. Calls `POST /api/ai/query` → NAFDAC Laravel proxy → Python AI service with `nmra_id: "NAFDAC"`. Citations rendered as clickable links via `CitationLink`.

---

## Data Flow & API

### next.config.mjs
`/api/*` → `${BACKEND_URL || 'http://localhost:8002'}/api/*`

### lib/api.ts
Same `get`/`post` helpers. Types updated for NAFDAC field names:
```typescript
interface Manufacturer { id, company_name, origin_country, reg_no, authorization_status }
interface Product { id, supplier_id, product_name, inn_name, formulation, potency, market_auth_number }
interface Batch { id, product_id, supplier_id, lot_number, production_date, expiry_date, product?, manufacturer? }
interface ProductRecall { id, lot_id, supplier_id, alert_reference, recall_reason, severity_grade,
  laboratory_findings, recall_status, issue_date, affected_regions?, batch?, manufacturer? }
```

### NAFDAC Laravel backend changes (same pattern as Rwanda FDA Phase 4)
- `ProductRecallController::index` → add `with(['batch.product', 'manufacturer'])`
- `ProductRecallController::show` → add `load(['batch.product', 'manufacturer'])`
- `BatchController::index` → add `with(['product', 'manufacturer'])`
- `BatchController::show` → add `load(['product', 'manufacturer'])`
- New `AiProxyController` + `POST /api/ai/query` route
- NAFDAC `config/services.php` must have `ontology.nmra_id = 'NAFDAC'`

### docker-compose
New service `nafdac-frontend` on port 3001, `BACKEND_URL: http://nafdac-backend:8000`.

---

## What Is Not In Scope

- Authentication / login
- Creating batches or products via UI (seeded via DemoSeeder)
- Pagination
- Real-time updates
