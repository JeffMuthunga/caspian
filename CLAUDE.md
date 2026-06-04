# NMRA Prototype — Project Brief for Claude

## What We Are Building

A prototype demonstrating cross-border medicines regulatory data sharing between two National Medicines Regulatory Authorities (NMRAs) in Africa. The core idea is borrowed from Palantir's Ontology architecture: each NMRA keeps its own sovereign data, but a shared ontology layer allows governed, cited, real-time intelligence to flow between them — powered by a local RAG AI system.

**NMRAs in this prototype:**
- **NMRA A = Rwanda FDA** (Rwanda Food and Drugs Authority) — port 3000 / 8001
- **NMRA B = NAFDAC** (Nigeria — National Agency for Food and Drug Administration and Control) — port 3001 / 8002

**Module in scope: Post-Market Surveillance (PMS)**
The prototype focuses on the PMS module, specifically product recall and quality control. The demo scenario: Rwanda FDA's lab finds a batch of Amoxicillin 500mg from PharmaCo Ltd is substandard (API content at 72%, spec: 95–105%) and issues a Class II recall. A NAFDAC analyst, before approving a batch import, asks the system a natural language question and receives a cited answer surfacing the active recall — without NAFDAC ever having direct access to Rwanda FDA's database.

A key design detail: the two NMRAs use **different database column names** for the same data (e.g. `batch_number` vs `lot_number`, `date_issued` vs `issue_date`). Each NMRA's Laravel backend maps its own schema to a shared canonical ontology object schema at publish time. This simulates real-world heterogeneity and proves the ontology's normalization value.

---

## Architecture Overview

### Three Services, Three Databases

```
nmra-prototype/                  (GitHub monorepo)
├── rwanda-fda/
│   ├── frontend/                Next.js — Rwanda FDA UI (port 3000)
│   └── backend/                 Laravel — Rwanda FDA API (port 8001)
├── nafdac/
│   ├── frontend/                Next.js — NAFDAC UI (port 3001)
│   └── backend/                 Laravel — NAFDAC API (port 8002)
├── ai-ontology-service/         Python FastAPI (port 8000)
│   ├── main.py
│   ├── ontology.py              object publish + link + markings logic
│   ├── retrieve.py              pgvector semantic search
│   └── generate.py             Ollama LLM call + prompt assembly
├── db/
│   ├── rwanda_fda_schema.sql    manufacturers, products, batches, product_recalls
│   ├── nafdac_schema.sql        same entities, different column names
│   └── ontology_schema.sql      published_objects, object_links, object_markings, embeddings
└── docker-compose.yml           spins up all 9 services with one command
```

### Data Flow

1. Each NMRA stores its own data privately via Laravel → its own Postgres instance
2. Laravel publishes a governed projection (subset of fields) to the Python ontology service via `POST /ontology/publish`
3. Python embeds the published object using Ollama `nomic-embed-text` and stores it in pgvector
4. Cross-org links are created via `POST /ontology/link` when objects from A and B refer to the same real-world entity
5. An analyst queries via `POST /ai/query` — Python retrieves relevant chunks enforcing markings, sends to Ollama `llama3.1:8b`, streams a cited answer
6. Laravel renders citations as clickable links back to source records

### The Ontology Service is Both a Service and a Database

The Python FastAPI service owns the third Postgres database. Other services never query the ontology DB directly — they always call the Python API. This centralises access control in one place.

```
Rwanda FDA Laravel  →  POST /ontology/publish    (write projection)
NAFDAC Laravel      →  POST /ontology/publish    (write projection)
Rwanda FDA Laravel  →  POST /ai/query            (read — enforces markings)
NAFDAC Laravel      →  POST /ai/query            (read — enforces markings)
```

---

## Tech Stack

| Layer | Technology | Notes |
|---|---|---|
| Frontend | Next.js (TypeScript) | One instance per NMRA |
| Backend | Laravel 13 (PHP 8.4) | One instance per NMRA |
| AI + Ontology | Python FastAPI | Single shared service |
| Database | PostgreSQL × 3 | nmra_a · nmra_b · ontology |
| Vector search | pgvector | Extension on ontology DB |
| Embeddings | Ollama nomic-embed-text | Runs locally, 274MB |
| LLM | Ollama llama3.1:8b | Runs locally, 4.7GB |
| Local AI host | Ollama | M4 MacBook Air 16GB |
| Orchestration | Docker Compose | One command dev setup |
| Source control | GitHub (monorepo) | |

---

## Key Design Principles

**Data sovereignty.** NMRA A never touches NMRA B's raw database. Data flows from each org's private DB → their Laravel backend → the Python ontology service. Raw tables are never shared directly.

**Publish projections, not raw data.** When NMRA A publishes a Manufacturer object, it specifies which fields to include and which to exclude. `internal_cost`, `route_id`, and other sensitive fields never leave the org. The ontology enforces this at the storage layer, not the UI layer.

**Access control is centralised.** The `object_markings` table records which NMRA can read or write each published object, and which properties are excluded. The Python service enforces this on every query before returning data.

**Everything is cited.** The LLM system prompt requires every claim to be cited with a source object ID (e.g. `[Manufacturer MFG-441]`, `[Inspection INS-220]`). Regulatory decisions cannot be based on hallucinated data.

**Fully local for the prototype.** No data leaves the machine. Ollama runs both the embedding model and the LLM locally. This is not just a cost decision — it is a data sovereignty argument for regulators and data protection officers.

---

## Ontology Database Schema (ontology_schema.sql)

```sql
CREATE EXTENSION IF NOT EXISTS vector;

-- Objects published from either NMRA into the shared space
CREATE TABLE published_objects (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    source_nmra     VARCHAR(50) NOT NULL,       -- 'NMRA_A' | 'NMRA_B'
    object_type     VARCHAR(100) NOT NULL,       -- 'Manufacturer' | 'Product' | 'ADRReport' etc
    source_id       VARCHAR(255) NOT NULL,       -- PK from source NMRA's DB
    properties      JSONB NOT NULL,             -- published fields only
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW()
);

-- Cross-org links between published objects
CREATE TABLE object_links (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    from_object_id  UUID REFERENCES published_objects(id),
    to_object_id    UUID REFERENCES published_objects(id),
    link_type       VARCHAR(100) NOT NULL,       -- 'manufactures' | 'reported_adr_for' etc
    created_by_nmra VARCHAR(50) NOT NULL,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- Access control — what each NMRA can see
CREATE TABLE object_markings (
    id                    UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    object_id             UUID REFERENCES published_objects(id),
    nmra_id               VARCHAR(50) NOT NULL,
    can_read              BOOLEAN DEFAULT TRUE,
    can_write             BOOLEAN DEFAULT FALSE,
    property_exclusions   TEXT[] DEFAULT '{}'   -- fields hidden from this NMRA
);

-- Vector embeddings for semantic search
CREATE TABLE embeddings (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    object_id   UUID REFERENCES published_objects(id),
    chunk_text  TEXT NOT NULL,
    embedding   vector(768),                    -- nomic-embed-text dimension
    metadata    JSONB DEFAULT '{}'
);

CREATE INDEX ON embeddings USING ivfflat (embedding vector_cosine_ops);
```

---

## FastAPI Endpoints (ai-ontology-service)

```
POST  /ontology/publish       NMRA pushes objects into shared space
GET   /ontology/objects/{id}  Fetch one object — enforces markings
GET   /ontology/linked/{id}   Get cross-org linked objects
POST  /ontology/link          Create a cross-org link type
POST  /ai/query               RAG query — returns streamed cited answer
POST  /ai/ingest              Embed and index published objects
GET   /health                 Service health check
```

---

## Build Order

- [x] **Phase 1 — Foundation**: GitHub monorepo · docker-compose · 3 DB schemas · Ollama running
- [ ] **Phase 2 — AI Service**: FastAPI skeleton · embed function · pgvector store · ingest script
- [x] **Phase 3 — Rwanda FDA Backend**: Laravel manufacturers + products + batches + product_recalls · OntologyPublisher wired · all tests passing (Laravel 13 / PHP 8.4)
- [ ] **Phase 4 — Rwanda FDA Frontend**: Next.js recall list · batch view · AI chat interface · citations
- [~] **Phase 5 — NAFDAC Backend**: Scaffold complete · domain models in progress (different column names: company_name, lot_number, severity_grade, etc.)

### Test commands
- Rwanda FDA: `cd rwanda-fda/backend && /opt/homebrew/opt/php/bin/php vendor/bin/phpunit --no-coverage`
- NAFDAC: `/opt/homebrew/opt/php/bin/php /Users/wainaina/Development/Caspian/nafdac/backend/vendor/bin/phpunit --configuration /Users/wainaina/Development/Caspian/nafdac/backend/phpunit.xml --no-coverage`
- Python AI service: `cd ai-ontology-service && pytest tests/ -v`

---

## The One Question the Prototype Must Answer

> *A NAFDAC analyst types: "Are there any active recalls I should know about before approving this batch import?" — and receives a cited answer that surfaces Rwanda FDA's Class II recall for Amoxicillin 500mg (Batch LOT-4421, PharmaCo Ltd), without NAFDAC ever having direct access to Rwanda FDA's database.*

If the prototype answers that question convincingly, the architecture is proven.

---

## Notes for Claude

- We are building step by step. Do not skip phases. Guide me into what you are building and how you are building because I need to understand whats going on
- Always ask before introducing new dependencies not in the stack above.
- Laravel handles auth, business logic, and DB access for each NMRA. It never queries the ontology DB directly.
- Python handles all AI and ontology logic. It does not handle NMRA-specific business logic.
- Next.js is a thin UI layer. API calls go to Laravel, not directly to Python.
- The M4 MacBook Air 16GB is the development machine. Ollama is already installed with `llama3.1:8b` and `nomic-embed-text` pulled.
- Docker Compose is the local dev orchestration. We are not deploying to cloud yet.
- When writing SQL, use Postgres syntax. No MySQL.
- When writing Python, use async FastAPI patterns throughout.
- When writing Laravel, use Laravel 11 conventions.