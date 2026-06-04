# NAFDAC Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the NAFDAC analyst dashboard — a Next.js 14 App Router app at port 3001 mirroring the Rwanda FDA frontend but with NAFDAC branding, NAFDAC-specific column names, and a backend AI proxy sending `nmra_id: "NAFDAC"`.

**Architecture:** Copy `rwanda-fda/frontend/` to `nafdac/frontend/` as a starting point, then apply targeted changes: navy branding, NAFDAC TypeScript types, NAFDAC field names across all pages, and a new AI proxy on the NAFDAC Laravel backend. All base-ui/react compatibility patterns and Next.js 15 async params/searchParams patterns from Phase 4 carry over automatically.

**Tech Stack:** Next.js 14 (App Router) · shadcn/ui (base-nova / @base-ui/react) · Tailwind CSS · TypeScript · Laravel (NAFDAC backend, minor additions) · PHPUnit

---

## File Map

**New — Frontend (`nafdac/frontend/`)**
- Copied and adapted from `rwanda-fda/frontend/`
- `next.config.mjs` — port 8002
- `app/layout.tsx` — NAFDAC title
- `lib/api.ts` — NAFDAC types and field names
- `components/Sidebar.tsx` — navy branding
- `app/recalls/page.tsx` — NAFDAC columns
- `app/recalls/RecallsFilter.tsx` — unchanged logic
- `app/recalls/new/page.tsx` — NAFDAC form fields
- `app/recalls/[id]/page.tsx` — NAFDAC field mapping
- `app/batches/page.tsx` — NAFDAC columns
- `app/batches/[id]/page.tsx` — NAFDAC fields + `lot_id` filter
- `app/ai/page.tsx` — unchanged
- `app/page.tsx` — unchanged (redirect to /recalls)
- `Dockerfile` — new

**Modified — Backend (`nafdac/backend/`)**
- `app/Http/Controllers/ProductRecallController.php` — eager-load on index + show
- `app/Http/Controllers/BatchController.php` — eager-load on index + show
- `app/Http/Controllers/AiProxyController.php` — new
- `routes/api.php` — add AI proxy route
- `tests/Feature/ProductRecallTest.php` — add relation tests
- `tests/Feature/BatchTest.php` — add relation tests
- `tests/Feature/AiProxyTest.php` — new

**Modified — Infrastructure**
- `docker-compose.yml` — add `nafdac-frontend` service on port 3001

---

## Task 1: Copy Rwanda FDA frontend and initialise

**Files:**
- Create: `nafdac/frontend/` (copy of `rwanda-fda/frontend/`)

- [ ] **Step 1: Copy the frontend directory**

```bash
cp -r /Users/wainaina/Development/Caspian/rwanda-fda/frontend /Users/wainaina/Development/Caspian/nafdac/frontend
rm -rf /Users/wainaina/Development/Caspian/nafdac/frontend/node_modules
rm -rf /Users/wainaina/Development/Caspian/nafdac/frontend/.next
```

- [ ] **Step 2: Update package name in package.json**

In `nafdac/frontend/package.json`, change the `"name"` field:

```json
{
  "name": "nafdac-frontend",
  ...
}
```

- [ ] **Step 3: Install dependencies**

```bash
cd /Users/wainaina/Development/Caspian/nafdac/frontend && npm install
```

- [ ] **Step 4: Verify build**

```bash
npm run build
```

Expected: build succeeds (it's identical to Rwanda FDA at this point).

- [ ] **Step 5: Commit**

```bash
cd /Users/wainaina/Development/Caspian
git add nafdac/frontend
git commit -m "feat(nafdac-frontend): copy Rwanda FDA frontend as NAFDAC starting point"
```

---

## Task 2: Update next.config.mjs

**Files:**
- Modify: `nafdac/frontend/next.config.mjs`

- [ ] **Step 1: Replace contents**

```js
/** @type {import('next').NextConfig} */
const nextConfig = {
  async rewrites() {
    return [
      {
        source: '/api/:path*',
        destination: `${process.env.BACKEND_URL || 'http://localhost:8002'}/api/:path*`,
      },
    ]
  },
}

export default nextConfig
```

- [ ] **Step 2: Commit**

```bash
git add nafdac/frontend/next.config.mjs
git commit -m "feat(nafdac-frontend): set API rewrite to NAFDAC backend on port 8002"
```

---

## Task 3: Update lib/api.ts with NAFDAC types

**Files:**
- Modify: `nafdac/frontend/lib/api.ts`

- [ ] **Step 1: Replace file contents entirely**

```typescript
// lib/api.ts
export interface Manufacturer {
  id: number
  company_name: string
  origin_country: string
  reg_no: string
  authorization_status: string
}

export interface Product {
  id: number
  supplier_id: number
  product_name: string
  inn_name: string
  formulation: string
  potency: string
  market_auth_number: string
}

export interface Batch {
  id: number
  product_id: number
  supplier_id: number
  lot_number: string
  production_date: string
  expiry_date: string
  product?: Product
  manufacturer?: Manufacturer
}

export interface ProductRecall {
  id: number
  lot_id: number
  supplier_id: number
  alert_reference: string
  recall_reason: string
  severity_grade: 'Grade I' | 'Grade II' | 'Grade III'
  laboratory_findings: string
  recall_status: 'active' | 'completed' | 'closed'
  issue_date: string
  affected_regions?: string
  batch?: Batch
  manufacturer?: Manufacturer
  created_at: string
  updated_at: string
}

export interface AiQueryResponse {
  answer: string
  sources: { object_id: string; chunk_text: string }[]
}

const BASE =
  typeof window === 'undefined'
    ? `${process.env.BACKEND_URL || 'http://localhost:8002'}/api`
    : '/api'

async function get<T>(path: string): Promise<T> {
  const res = await fetch(`${BASE}${path}`, { cache: 'no-store' })
  if (!res.ok) throw new Error(`GET ${path} failed: ${res.status}`)
  return res.json()
}

async function post<T>(path: string, body: unknown): Promise<T> {
  const res = await fetch(`${BASE}${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(body),
  })
  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    throw Object.assign(new Error(`POST ${path} failed: ${res.status}`), {
      status: res.status,
      data: err,
    })
  }
  return res.json()
}

export const api = {
  recalls: {
    list: (status?: string) =>
      get<ProductRecall[]>(`/product-recalls${status && status !== 'all' ? `?status=${status}` : ''}`),
    get: (id: number | string) => get<ProductRecall>(`/product-recalls/${id}`),
    create: (body: Record<string, unknown>) => post<ProductRecall>('/product-recalls', body),
  },
  batches: {
    list: () => get<Batch[]>('/batches'),
    get: (id: number | string) => get<Batch>(`/batches/${id}`),
  },
  manufacturers: {
    list: () => get<Manufacturer[]>('/manufacturers'),
  },
  ai: {
    query: (question: string) => post<AiQueryResponse>('/ai/query', { question }),
  },
}
```

- [ ] **Step 2: Verify TypeScript**

```bash
cd nafdac/frontend && npx tsc --noEmit
```

Expected: zero errors.

- [ ] **Step 3: Commit**

```bash
git add nafdac/frontend/lib/api.ts
git commit -m "feat(nafdac-frontend): NAFDAC types and field names in api.ts"
```

---

## Task 4: Update Sidebar with NAFDAC navy branding

**Files:**
- Modify: `nafdac/frontend/components/Sidebar.tsx`

- [ ] **Step 1: Replace file contents**

```tsx
// components/Sidebar.tsx
'use client'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { Separator } from '@/components/ui/separator'

const links = [
  { href: '/recalls', label: 'Recalls' },
  { href: '/batches', label: 'Batches' },
  { href: '/ai', label: 'AI Query' },
]

export default function Sidebar() {
  const pathname = usePathname()

  return (
    <aside className="w-[220px] min-h-screen bg-[#1a1e2e] flex flex-col shrink-0">
      <div className="px-6 py-6">
        <p className="text-white font-semibold text-sm tracking-wide">NAFDAC</p>
        <p className="text-indigo-300 text-xs mt-0.5">Regulatory Authority</p>
      </div>
      <Separator className="bg-indigo-900" />
      <nav className="flex-1 px-3 py-4 space-y-1">
        {links.map(({ href, label }) => {
          const active = pathname.startsWith(href)
          return (
            <Link
              key={href}
              href={href}
              className={`flex items-center px-3 py-2 rounded text-sm transition-colors ${
                active
                  ? 'bg-[#2d3a6a] text-white border-l-2 border-indigo-300'
                  : 'text-indigo-200 hover:bg-[#2d3a6a] hover:text-white'
              }`}
            >
              {label}
            </Link>
          )
        })}
      </nav>
      <div className="px-6 py-4">
        <p className="text-indigo-700 text-xs">Post-Market Surveillance</p>
      </div>
    </aside>
  )
}
```

- [ ] **Step 2: Update layout.tsx title**

In `nafdac/frontend/app/layout.tsx`, change the metadata title:

```tsx
export const metadata: Metadata = {
  title: 'NAFDAC — Post-Market Surveillance',
}
```

- [ ] **Step 3: Verify build**

```bash
cd nafdac/frontend && npm run build
```

Expected: build succeeds.

- [ ] **Step 4: Commit**

```bash
git add nafdac/frontend/components/Sidebar.tsx nafdac/frontend/app/layout.tsx
git commit -m "feat(nafdac-frontend): NAFDAC navy branding"
```

---

## Task 5: Build recalls pages

**Files:**
- Modify: `nafdac/frontend/app/recalls/RecallsFilter.tsx` — unchanged logic, no edits needed
- Modify: `nafdac/frontend/app/recalls/page.tsx`
- Modify: `nafdac/frontend/app/recalls/new/page.tsx`
- Modify: `nafdac/frontend/app/recalls/[id]/page.tsx`

- [ ] **Step 1: Write `app/recalls/page.tsx`**

```tsx
// app/recalls/page.tsx
import { api } from '@/lib/api'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import { buttonVariants } from '@/components/ui/button'
import StatusBadge from '@/components/StatusBadge'
import Link from 'next/link'
import RecallsFilter from './RecallsFilter'

export default async function RecallsPage({
  searchParams,
}: {
  searchParams: Promise<{ status?: string }>
}) {
  const { status } = await searchParams
  const recalls = await api.recalls.list(status)

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-semibold text-gray-900">Product Recalls</h1>
        <Link href="/recalls/new" className={buttonVariants()}>New Recall</Link>
      </div>
      <div className="bg-white rounded-lg border">
        <div className="p-4 border-b">
          <RecallsFilter current={status} />
        </div>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Alert Ref.</TableHead>
              <TableHead>Product</TableHead>
              <TableHead>Lot No.</TableHead>
              <TableHead>Severity Grade</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Date Issued</TableHead>
              <TableHead></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {recalls.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="text-center text-gray-500 py-8">
                  No recalls found.
                </TableCell>
              </TableRow>
            ) : (
              recalls.map((recall) => (
                <TableRow key={recall.id}>
                  <TableCell className="font-mono text-sm">{recall.alert_reference}</TableCell>
                  <TableCell>{recall.batch?.product?.product_name ?? '—'}</TableCell>
                  <TableCell className="font-mono text-sm">{recall.batch?.lot_number ?? '—'}</TableCell>
                  <TableCell>{recall.severity_grade}</TableCell>
                  <TableCell><StatusBadge status={recall.recall_status} /></TableCell>
                  <TableCell>{recall.issue_date}</TableCell>
                  <TableCell>
                    <Link href={`/recalls/${recall.id}`} className={buttonVariants({ variant: 'ghost', size: 'sm' })}>
                      View
                    </Link>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  )
}
```

- [ ] **Step 2: Write `app/recalls/new/page.tsx`**

```tsx
// app/recalls/new/page.tsx
'use client'
import { useState, useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { api, Batch, Manufacturer } from '@/lib/api'
import { buttonVariants } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import Link from 'next/link'

export default function NewRecallPage() {
  const router = useRouter()
  const [batches, setBatches] = useState<Batch[]>([])
  const [manufacturers, setManufacturers] = useState<Manufacturer[]>([])
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  const [form, setForm] = useState({
    lot_id: '', supplier_id: '', alert_reference: '',
    severity_grade: '', recall_reason: '', laboratory_findings: '',
    issue_date: '', affected_regions: '', recall_status: 'active',
  })

  useEffect(() => {
    api.batches.list().then(setBatches)
    api.manufacturers.list().then(setManufacturers)
  }, [])

  function field(key: keyof typeof form, value: string | null) {
    setForm(f => ({ ...f, [key]: value ?? '' }))
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})
    try {
      const recall = await api.recalls.create({
        ...form,
        lot_id: Number(form.lot_id),
        supplier_id: Number(form.supplier_id),
      })
      router.push(`/recalls/${recall.id}`)
    } catch (err: any) {
      if (err.data?.errors) setErrors(err.data.errors)
    } finally {
      setSubmitting(false)
    }
  }

  function FieldError({ name }: { name: string }) {
    return errors[name] ? (
      <p className="text-red-500 text-xs mt-0.5">{errors[name][0]}</p>
    ) : null
  }

  return (
    <div>
      <h1 className="text-2xl font-semibold text-gray-900 mb-6">New Product Recall</h1>
      <Card className="max-w-2xl">
        <CardHeader><CardTitle>Recall Details</CardTitle></CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-4">

            <div className="space-y-1">
              <Label>Batch (Lot)</Label>
              <Select onValueChange={(v: unknown) => field('lot_id', v as string)}>
                <SelectTrigger><SelectValue placeholder="Select batch" /></SelectTrigger>
                <SelectContent>
                  {batches.map(b => (
                    <SelectItem key={b.id} value={String(b.id)}>{b.lot_number}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldError name="lot_id" />
            </div>

            <div className="space-y-1">
              <Label>Manufacturer</Label>
              <Select onValueChange={(v: unknown) => field('supplier_id', v as string)}>
                <SelectTrigger><SelectValue placeholder="Select manufacturer" /></SelectTrigger>
                <SelectContent>
                  {manufacturers.map(m => (
                    <SelectItem key={m.id} value={String(m.id)}>{m.company_name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldError name="supplier_id" />
            </div>

            <div className="space-y-1">
              <Label>Alert Reference</Label>
              <Input value={form.alert_reference} onChange={e => field('alert_reference', e.target.value)} placeholder="NAFDAC-ALERT-001" />
              <FieldError name="alert_reference" />
            </div>

            <div className="space-y-1">
              <Label>Severity Grade</Label>
              <Select onValueChange={(v: unknown) => field('severity_grade', v as string)}>
                <SelectTrigger><SelectValue placeholder="Select severity grade" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="Grade I">Grade I</SelectItem>
                  <SelectItem value="Grade II">Grade II</SelectItem>
                  <SelectItem value="Grade III">Grade III</SelectItem>
                </SelectContent>
              </Select>
              <FieldError name="severity_grade" />
            </div>

            <div className="space-y-1">
              <Label>Recall Reason</Label>
              <Textarea value={form.recall_reason} onChange={e => field('recall_reason', e.target.value)} placeholder="Reason for recall..." />
              <FieldError name="recall_reason" />
            </div>

            <div className="space-y-1">
              <Label>Laboratory Findings</Label>
              <Textarea value={form.laboratory_findings} onChange={e => field('laboratory_findings', e.target.value)} placeholder="Laboratory analysis results..." />
              <FieldError name="laboratory_findings" />
            </div>

            <div className="space-y-1">
              <Label>Date Issued</Label>
              <Input type="date" value={form.issue_date} onChange={e => field('issue_date', e.target.value)} />
              <FieldError name="issue_date" />
            </div>

            <div className="space-y-1">
              <Label>Affected Regions (optional)</Label>
              <Input value={form.affected_regions} onChange={e => field('affected_regions', e.target.value)} placeholder="National / Regional..." />
            </div>

            <div className="space-y-1">
              <Label>Status</Label>
              <Select defaultValue="active" onValueChange={(v: unknown) => field('recall_status', v as string)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="active">Active</SelectItem>
                  <SelectItem value="completed">Completed</SelectItem>
                  <SelectItem value="closed">Closed</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="flex gap-3 pt-2">
              <button
                type="submit"
                disabled={submitting}
                className="inline-flex items-center justify-center rounded-md text-sm font-medium bg-primary text-primary-foreground hover:bg-primary/90 h-9 px-4 py-2 disabled:opacity-50"
              >
                {submitting ? 'Submitting...' : 'Create Recall'}
              </button>
              <Link href="/recalls" className="inline-flex items-center justify-center rounded-md text-sm font-medium border border-input bg-background hover:bg-accent h-9 px-4 py-2">
                Cancel
              </Link>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
```

- [ ] **Step 3: Write `app/recalls/[id]/page.tsx`**

```tsx
// app/recalls/[id]/page.tsx
import { api } from '@/lib/api'
import { notFound } from 'next/navigation'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { buttonVariants } from '@/components/ui/button'
import StatusBadge from '@/components/StatusBadge'
import Link from 'next/link'

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs text-gray-500 uppercase tracking-wide">{label}</p>
      <p className="text-sm text-gray-900 mt-0.5">{value}</p>
    </div>
  )
}

export default async function RecallDetailPage({
  params,
}: {
  params: Promise<{ id: string }>
}) {
  const { id } = await params
  let recall
  try {
    recall = await api.recalls.get(id)
  } catch {
    notFound()
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">{recall.alert_reference}</h1>
          <div className="mt-1"><StatusBadge status={recall.recall_status} /></div>
        </div>
        <Link href="/recalls" className={buttonVariants({ variant: 'outline' })}>
          ← Back to Recalls
        </Link>
      </div>
      <div className="grid grid-cols-2 gap-6">
        <Card>
          <CardHeader><CardTitle>Recall Information</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <Row label="Alert Reference" value={recall.alert_reference} />
            <Row label="Severity Grade" value={recall.severity_grade} />
            <Row label="Date Issued" value={recall.issue_date} />
            <Row label="Affected Regions" value={recall.affected_regions ?? '—'} />
            <Row label="Recall Reason" value={recall.recall_reason} />
            <Row label="Laboratory Findings" value={recall.laboratory_findings} />
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle>Batch & Manufacturer</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <Row label="Lot Number" value={recall.batch?.lot_number ?? '—'} />
            <Row label="Product" value={recall.batch?.product?.product_name ?? '—'} />
            <Row label="Generic Name (INN)" value={recall.batch?.product?.inn_name ?? '—'} />
            <Row label="Strength" value={recall.batch?.product?.potency ?? '—'} />
            <Row label="Production Date" value={recall.batch?.production_date ?? '—'} />
            <Row label="Expiry Date" value={recall.batch?.expiry_date ?? '—'} />
            <Row label="Manufacturer" value={recall.manufacturer?.company_name ?? '—'} />
            <Row label="Country" value={recall.manufacturer?.origin_country ?? '—'} />
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
```

- [ ] **Step 4: Verify TypeScript and build**

```bash
cd nafdac/frontend && npx tsc --noEmit && npm run build
```

Expected: zero TypeScript errors, build succeeds.

- [ ] **Step 5: Commit**

```bash
cd /Users/wainaina/Development/Caspian
git add nafdac/frontend/app/recalls/
git commit -m "feat(nafdac-frontend): recalls list, create form, and detail pages"
```

---

## Task 6: Build batches pages

**Files:**
- Modify: `nafdac/frontend/app/batches/page.tsx`
- Modify: `nafdac/frontend/app/batches/[id]/page.tsx`

- [ ] **Step 1: Write `app/batches/page.tsx`**

```tsx
// app/batches/page.tsx
import { api } from '@/lib/api'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import { buttonVariants } from '@/components/ui/button'
import Link from 'next/link'

export default async function BatchesPage() {
  const batches = await api.batches.list()

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-semibold text-gray-900">Batches</h1>
      </div>
      <div className="bg-white rounded-lg border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Lot No.</TableHead>
              <TableHead>Product</TableHead>
              <TableHead>Manufacturer</TableHead>
              <TableHead>Production Date</TableHead>
              <TableHead>Expiry Date</TableHead>
              <TableHead></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {batches.length === 0 ? (
              <TableRow>
                <TableCell colSpan={6} className="text-center text-gray-500 py-8">
                  No batches found.
                </TableCell>
              </TableRow>
            ) : (
              batches.map((batch) => (
                <TableRow key={batch.id}>
                  <TableCell className="font-mono text-sm">{batch.lot_number}</TableCell>
                  <TableCell>{batch.product?.product_name ?? '—'}</TableCell>
                  <TableCell>{batch.manufacturer?.company_name ?? '—'}</TableCell>
                  <TableCell>{batch.production_date}</TableCell>
                  <TableCell>{batch.expiry_date}</TableCell>
                  <TableCell>
                    <Link href={`/batches/${batch.id}`} className={buttonVariants({ variant: 'ghost', size: 'sm' })}>
                      View
                    </Link>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  )
}
```

- [ ] **Step 2: Write `app/batches/[id]/page.tsx`**

```tsx
// app/batches/[id]/page.tsx
import { api } from '@/lib/api'
import { notFound } from 'next/navigation'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import { buttonVariants } from '@/components/ui/button'
import StatusBadge from '@/components/StatusBadge'
import Link from 'next/link'

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs text-gray-500 uppercase tracking-wide">{label}</p>
      <p className="text-sm text-gray-900 mt-0.5">{value}</p>
    </div>
  )
}

export default async function BatchDetailPage({
  params,
}: {
  params: Promise<{ id: string }>
}) {
  const { id } = await params
  let batch
  try {
    batch = await api.batches.get(id)
  } catch {
    notFound()
  }

  const allRecalls = await api.recalls.list()
  const recalls = allRecalls.filter(r => r.lot_id === batch.id)

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-semibold text-gray-900">{batch.lot_number}</h1>
        <Link href="/batches" className={buttonVariants({ variant: 'outline' })}>
          ← Back to Batches
        </Link>
      </div>
      <div className="space-y-6">
        <Card>
          <CardHeader><CardTitle>Batch Information</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <Row label="Lot Number" value={batch.lot_number} />
            <Row label="Product" value={batch.product?.product_name ?? '—'} />
            <Row label="Generic Name (INN)" value={batch.product?.inn_name ?? '—'} />
            <Row label="Strength" value={batch.product?.potency ?? '—'} />
            <Row label="Manufacturer" value={batch.manufacturer?.company_name ?? '—'} />
            <Row label="Production Date" value={batch.production_date} />
            <Row label="Expiry Date" value={batch.expiry_date} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Linked Recalls ({recalls.length})</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            {recalls.length === 0 ? (
              <p className="text-sm text-gray-500 px-6 py-4">No recalls for this batch.</p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Alert Ref.</TableHead>
                    <TableHead>Severity Grade</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Date Issued</TableHead>
                    <TableHead></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {recalls.map(r => (
                    <TableRow key={r.id}>
                      <TableCell className="font-mono text-sm">{r.alert_reference}</TableCell>
                      <TableCell>{r.severity_grade}</TableCell>
                      <TableCell><StatusBadge status={r.recall_status} /></TableCell>
                      <TableCell>{r.issue_date}</TableCell>
                      <TableCell>
                        <Link href={`/recalls/${r.id}`} className={buttonVariants({ variant: 'ghost', size: 'sm' })}>
                          View
                        </Link>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
```

- [ ] **Step 3: Verify TypeScript and build**

```bash
cd nafdac/frontend && npx tsc --noEmit && npm run build
```

Expected: zero errors, build succeeds.

- [ ] **Step 4: Commit**

```bash
cd /Users/wainaina/Development/Caspian
git add nafdac/frontend/app/batches/
git commit -m "feat(nafdac-frontend): batches list and detail pages"
```

---

## Task 7: NAFDAC Laravel backend — eager loading + AI proxy

**Files:**
- Modify: `nafdac/backend/app/Http/Controllers/ProductRecallController.php`
- Modify: `nafdac/backend/app/Http/Controllers/BatchController.php`
- Create: `nafdac/backend/app/Http/Controllers/AiProxyController.php`
- Modify: `nafdac/backend/routes/api.php`
- Modify: `nafdac/backend/tests/Feature/ProductRecallTest.php`
- Modify: `nafdac/backend/tests/Feature/BatchTest.php`
- Create: `nafdac/backend/tests/Feature/AiProxyTest.php`

**Test command:** `/opt/homebrew/opt/php/bin/php vendor/bin/phpunit --no-coverage`
**Working directory for tests:** `nafdac/backend`

- [ ] **Step 1: Add failing recall relation tests to `tests/Feature/ProductRecallTest.php`**

Add inside the class after the last existing test method:

```php
public function test_recall_index_includes_batch_and_manufacturer_relations(): void
{
    Http::fake([
        '*/ontology/publish' => Http::response(['id' => 'uuid-1', 'object_type' => 'Manufacturer'], 200),
        '*/ontology/link'    => Http::response(['id' => 'link-uuid'], 200),
        '*/ai/ingest'        => Http::response(['status' => 'ok'], 200),
    ]);

    ['mfg' => $mfg, 'batch' => $batch] = $this->seedBatchAndManufacturer();

    $this->postJson('/api/product-recalls', [
        'lot_id' => $batch->id, 'supplier_id' => $mfg->id,
        'alert_reference' => 'ALERT-REL-001', 'recall_reason' => 'Substandard',
        'severity_grade' => 'Grade II', 'laboratory_findings' => 'API at 72%',
        'recall_status' => 'active', 'issue_date' => '2026-01-15',
    ]);

    $this->getJson('/api/product-recalls')
         ->assertStatus(200)
         ->assertJsonPath('0.batch.lot_number', 'LOT-4421')
         ->assertJsonPath('0.manufacturer.company_name', 'PharmaCo Ltd');
}

public function test_recall_show_includes_batch_and_manufacturer_relations(): void
{
    Http::fake([
        '*/ontology/publish' => Http::response(['id' => 'uuid-1', 'object_type' => 'Manufacturer'], 200),
        '*/ontology/link'    => Http::response(['id' => 'link-uuid'], 200),
        '*/ai/ingest'        => Http::response(['status' => 'ok'], 200),
    ]);

    ['mfg' => $mfg, 'batch' => $batch] = $this->seedBatchAndManufacturer();

    $recall = $this->postJson('/api/product-recalls', [
        'lot_id' => $batch->id, 'supplier_id' => $mfg->id,
        'alert_reference' => 'ALERT-SHOW-001', 'recall_reason' => 'Substandard',
        'severity_grade' => 'Grade II', 'laboratory_findings' => 'API at 72%',
        'recall_status' => 'active', 'issue_date' => '2026-01-15',
    ])->json();

    $this->getJson("/api/product-recalls/{$recall['id']}")
         ->assertStatus(200)
         ->assertJsonPath('batch.id', $batch->id)
         ->assertJsonPath('manufacturer.id', $mfg->id)
         ->assertJsonPath('batch.lot_number', 'LOT-4421');
}
```

- [ ] **Step 2: Run recall tests — verify new tests fail**

```bash
cd /Users/wainaina/Development/Caspian/nafdac/backend
/opt/homebrew/opt/php/bin/php vendor/bin/phpunit tests/Feature/ProductRecallTest.php --no-coverage
```

Expected: new tests FAIL with no `batch` key in response.

- [ ] **Step 3: Update `ProductRecallController` index and show**

Replace the `index` and `show` methods in `app/Http/Controllers/ProductRecallController.php`:

```php
public function index(Request $request): JsonResponse
{
    $query = ProductRecall::with(['batch.product', 'manufacturer']);
    if ($request->has('status')) {
        $query->where('recall_status', $request->input('status'));
    }
    return response()->json($query->get());
}

public function show(ProductRecall $productRecall): JsonResponse
{
    $productRecall->load(['batch.product', 'manufacturer']);
    return response()->json($productRecall);
}
```

- [ ] **Step 4: Run recall tests — verify all pass**

```bash
/opt/homebrew/opt/php/bin/php vendor/bin/phpunit tests/Feature/ProductRecallTest.php --no-coverage
```

Expected: ALL tests pass.

- [ ] **Step 5: Add failing batch relation tests to `tests/Feature/BatchTest.php`**

Add inside the class after the last existing test method:

```php
public function test_batch_index_includes_product_and_manufacturer_relations(): void
{
    ['manufacturer' => $mfg, 'product' => $product] = $this->product();

    $this->postJson('/api/batches', [
        'product_id' => $product->id, 'supplier_id' => $mfg->id,
        'lot_number' => 'LOT-IDX-001', 'production_date' => '2025-01-01',
        'expiry_date' => '2027-01-01',
    ]);

    $this->getJson('/api/batches')
         ->assertStatus(200)
         ->assertJsonPath('0.product.product_name', 'Amoxicillin 500mg')
         ->assertJsonPath('0.manufacturer.company_name', 'PharmaCo Ltd');
}

public function test_batch_show_includes_product_and_manufacturer_relations(): void
{
    ['manufacturer' => $mfg, 'product' => $product] = $this->product();

    $batch = $this->postJson('/api/batches', [
        'product_id' => $product->id, 'supplier_id' => $mfg->id,
        'lot_number' => 'LOT-SHOW-001', 'production_date' => '2025-01-01',
        'expiry_date' => '2027-01-01',
    ])->json();

    $this->getJson("/api/batches/{$batch['id']}")
         ->assertStatus(200)
         ->assertJsonPath('product.id', $product->id)
         ->assertJsonPath('manufacturer.id', $mfg->id);
}
```

- [ ] **Step 6: Run batch tests — verify new tests fail**

```bash
/opt/homebrew/opt/php/bin/php vendor/bin/phpunit tests/Feature/BatchTest.php --no-coverage
```

Expected: new tests FAIL.

- [ ] **Step 7: Update `BatchController` index and show**

Replace the `index` and `show` methods in `app/Http/Controllers/BatchController.php`:

```php
public function index(): JsonResponse
{
    return response()->json(Batch::with(['product', 'manufacturer'])->get());
}

public function show(Batch $batch): JsonResponse
{
    $batch->load(['product', 'manufacturer']);
    return response()->json($batch);
}
```

Note: `Batch::with(['product', 'manufacturer'])` uses the NAFDAC model relationships — `manufacturer()` is defined via `supplier_id` FK in the Batch model, so this eager load resolves correctly.

- [ ] **Step 8: Run batch tests — verify all pass**

```bash
/opt/homebrew/opt/php/bin/php vendor/bin/phpunit tests/Feature/BatchTest.php --no-coverage
```

Expected: ALL tests pass.

- [ ] **Step 9: Create `tests/Feature/AiProxyTest.php`**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_query_proxies_to_ontology_with_nafdac_nmra_id(): void
    {
        Http::fake([
            '*/ai/query' => Http::response([
                'answer'  => 'Active recall found [Recall RWANDA_FDA:some-uuid].',
                'sources' => [['object_id' => 'some-uuid', 'chunk_text' => 'Recall data.']],
            ], 200),
        ]);

        $this->postJson('/api/ai/query', ['question' => 'Are there any active recalls?'])
             ->assertStatus(200)
             ->assertJsonStructure(['answer', 'sources'])
             ->assertJsonPath('answer', 'Active recall found [Recall RWANDA_FDA:some-uuid].');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/ai/query')
                && $request['nmra_id'] === 'NAFDAC'
                && $request['question'] === 'Are there any active recalls?';
        });
    }

    public function test_ai_query_requires_question_field(): void
    {
        $this->postJson('/api/ai/query', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['question']);
    }
}
```

- [ ] **Step 10: Run AI proxy test — verify it fails**

```bash
/opt/homebrew/opt/php/bin/php vendor/bin/phpunit tests/Feature/AiProxyTest.php --no-coverage
```

Expected: FAIL — route does not exist yet.

- [ ] **Step 11: Create `app/Http/Controllers/AiProxyController.php`**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiProxyController extends Controller
{
    public function query(Request $request): JsonResponse
    {
        $data = $request->validate(['question' => 'required|string|max:1000']);

        $response = Http::post(
            config('services.ontology.url') . '/ai/query',
            [
                'question' => $data['question'],
                'nmra_id'  => config('services.ontology.nmra_id'),
            ]
        );

        return response()->json($response->json(), $response->status());
    }
}
```

- [ ] **Step 12: Register AI proxy route in `routes/api.php`**

Add at the bottom of `routes/api.php`:

```php
use App\Http\Controllers\AiProxyController;

Route::post('ai/query', [AiProxyController::class, 'query']);
```

- [ ] **Step 13: Run AI proxy tests — verify all pass**

```bash
/opt/homebrew/opt/php/bin/php vendor/bin/phpunit tests/Feature/AiProxyTest.php --no-coverage
```

Expected: both tests pass.

- [ ] **Step 14: Run full NAFDAC test suite**

```bash
/opt/homebrew/opt/php/bin/php vendor/bin/phpunit --no-coverage
```

Expected: ALL tests pass.

- [ ] **Step 15: Commit**

```bash
cd /Users/wainaina/Development/Caspian
git add nafdac/backend/app/Http/Controllers/ProductRecallController.php \
        nafdac/backend/app/Http/Controllers/BatchController.php \
        nafdac/backend/app/Http/Controllers/AiProxyController.php \
        nafdac/backend/routes/api.php \
        nafdac/backend/tests/Feature/ProductRecallTest.php \
        nafdac/backend/tests/Feature/BatchTest.php \
        nafdac/backend/tests/Feature/AiProxyTest.php
git commit -m "feat(nafdac-backend): eager-load relations on index/show + AI proxy endpoint"
```

---

## Task 8: Add nafdac-frontend to docker-compose

**Files:**
- Create: `nafdac/frontend/Dockerfile`
- Modify: `docker-compose.yml`

- [ ] **Step 1: Create `nafdac/frontend/Dockerfile`**

```dockerfile
FROM node:20-alpine
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build
EXPOSE 3000
ENV NODE_ENV=production
CMD ["npm", "start"]
```

- [ ] **Step 2: Add `nafdac-frontend` service to `docker-compose.yml`**

Add after the `rwanda-fda-frontend` service:

```yaml
  nafdac-frontend:
    build: ./nafdac/frontend
    ports:
      - "3001:3000"
    environment:
      BACKEND_URL: http://nafdac-backend:8000
    depends_on:
      - nafdac-backend
```

- [ ] **Step 3: Commit**

```bash
git add nafdac/frontend/Dockerfile docker-compose.yml
git commit -m "feat(infra): add NAFDAC frontend to docker-compose on port 3001"
```

---

## Self-Review Checklist (already applied)

- [x] All six pages implemented (recalls list, filter, new, detail; batches list, detail)
- [x] AI chat page copied unchanged — no code changes needed, it calls `/api/ai/query` which routes to the NAFDAC proxy
- [x] All NAFDAC column names used consistently: `lot_id`, `supplier_id`, `alert_reference`, `recall_reason`, `severity_grade`, `laboratory_findings`, `recall_status`, `issue_date`, `affected_regions`, `lot_number`, `production_date`, `company_name`, `origin_country`, `product_name`, `inn_name`, `potency`
- [x] Batch detail uses `r.lot_id === batch.id` (not `r.batch_id`) for filtering linked recalls
- [x] TDD applied to all backend changes
- [x] `config('services.ontology.nmra_id')` defaults to `'NAFDAC'` — confirmed in NAFDAC services.php
- [x] base-ui/react patterns used throughout (no `asChild`, `(v: unknown) => v as string` casting)
- [x] `searchParams` and `params` awaited as Promises (Next.js 15 pattern)
- [x] Server-side fetch uses absolute URL via `BACKEND_URL || 'http://localhost:8002'`
