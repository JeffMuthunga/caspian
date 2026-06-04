# Post-Market Surveillance (PMS) Module — Design Spec
_Date: 2026-06-04_

## Purpose

Demonstrate cross-border post-market surveillance intelligence between two NMRAs (Rwanda FDA and NAFDAC) using the shared ontology layer. The module proves that a NAFDAC analyst can surface active product recalls issued by Rwanda FDA — before approving a batch import — without ever having direct access to Rwanda FDA's database.

---

## The Demo Question

> *"Are there any active recalls I should know about before approving this batch import?"*

Expected cited answer:
> "Rwanda FDA has issued an active Class II recall [ProductRecall RCL-001] for Batch LOT-4421 [Batch BAT-4421] of Amoxicillin 500mg [Product PRD-112] from PharmaCo Ltd [Manufacturer MFG-441]. QC findings: API content at 72% (specification: 95–105%). Status: Active."

---

## Approach

Four entity types, published independently to the ontology, connected via cross-org links. The recall is the primary record; QC findings are embedded in it as supporting evidence.

---

## Data Model

### Rwanda FDA (NMRA A) — private database schema

```sql
manufacturers   id, name, country, registration_number, license_status,
                internal_vendor_rating, contract_terms

products        id, manufacturer_id, name, generic_name,
                dosage_form, strength, registration_number, internal_cost

batches         id, product_id, manufacturer_id, batch_number,
                manufacture_date, expiry_date, quantity_produced, internal_lot_code

product_recalls id, batch_id, manufacturer_id, recall_number,
                reason, classification, qc_summary,
                status, date_issued, scope,
                internal_investigation_notes, inspector_id
```

### NAFDAC (NMRA B) — same data, different column names

```sql
manufacturers   id, company_name, origin_country, reg_no, authorization_status

products        id, supplier_id, product_name, inn_name,
                formulation, potency, market_auth_number

batches         id, product_id, supplier_id, lot_number,
                production_date, expiry_date, units_manufactured

product_recalls id, lot_id, supplier_id, alert_reference,
                recall_reason, severity_grade, laboratory_findings,
                recall_status, issue_date, affected_regions
```

The schema difference simulates real-world heterogeneity. Each NMRA's Laravel backend is responsible for mapping its own column names to the shared ontology object schema.

---

## Ontology Objects (published fields only)

| Object Type | Published Fields | Stripped Fields |
|---|---|---|
| Manufacturer | name, country, registration_number, license_status | internal_vendor_rating, contract_terms |
| Product | name, generic_name, dosage_form, strength, registration_number | internal_cost, procurement_price |
| Batch | batch_number, manufacture_date, expiry_date | quantity_produced / units_manufactured, internal_lot_code |
| ProductRecall | recall_number, reason, classification, qc_summary, status, date_issued, scope | internal_investigation_notes, inspector_id |

Both NMRAs map their own column names to the canonical ontology field names at publish time.

---

## Cross-Org Links

Created via `POST /ontology/link` after objects are published:

```
Manufacturer  → manufactures      → Product
Batch         → batch_of          → Product
ProductRecall → affects           → Batch
ProductRecall → issued_against    → Manufacturer
```

The RAG query finds `ProductRecall` and traverses these links to assemble the full evidence chain (Batch → Product → Manufacturer) before sending to the LLM.

---

## Demo Flow (Step by Step)

1. Rwanda FDA's lab tests Batch LOT-4421 of Amoxicillin 500mg from PharmaCo Ltd → API content at 72% (spec: 95–105%)
2. Rwanda FDA analyst records the recall in their Laravel backend
3. Rwanda FDA Laravel publishes four objects to the ontology: `Manufacturer`, `Product`, `Batch`, `ProductRecall`
4. Rwanda FDA Laravel creates four cross-org links between those objects
5. Python AI service embeds each published object and stores vectors in pgvector
6. NAFDAC analyst types: *"Are there any active recalls I should know about before approving this batch import?"*
7. Python retrieves relevant chunks via semantic search, enforces `object_markings`, traverses links
8. LLM generates a cited answer surfacing the recall with object IDs as citations

---

## Services to Scaffold

| Service | Tech | Port |
|---|---|---|
| Rwanda FDA backend | Laravel 11 | 8001 |
| NAFDAC backend | Laravel 11 | 8002 |
| AI Ontology Service | Python FastAPI | 8000 |
