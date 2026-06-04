# Rwanda FDA Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Rwanda FDA analyst dashboard — a Next.js 14 App Router application with shadcn/ui covering Post-Market Surveillance CRUD (recalls, batches) and an AI chat interface with clickable citations linking to source records.

**Architecture:** Server Components for all data-display pages; Client Components only for the create-recall form and AI chat (both require `useState`). All frontend API calls go to `/api/*` which Next.js rewrites to the Laravel backend on port 8001. The AI chat calls a new thin Laravel proxy endpoint (`POST /api/ai/query`) that forwards to the Python ontology service. No extra state management libraries.

**Tech Stack:** Next.js 14 (App Router) · shadcn/ui · Tailwind CSS · TypeScript · Laravel (minor backend changes) · PHPUnit (backend tests)

---

## File Map

**New — Frontend (`rwanda-fda/frontend/`)**
- `next.config.js` — API rewrite proxy to Laravel
- `app/layout.tsx` — root layout (sidebar + main)
- `app/page.tsx` — redirect to `/recalls`
- `app/recalls/page.tsx` — recall list (Server Component)
- `app/recalls/RecallsFilter.tsx` — status filter (Client Component)
- `app/recalls/new/page.tsx` — create recall form (Client Component)
- `app/recalls/[id]/page.tsx` — recall detail (Server Component)
- `app/batches/page.tsx` — batch list (Server Component)
- `app/batches/[id]/page.tsx` — batch detail + linked recalls (Server Component)
- `app/ai/page.tsx` — AI chat (Client Component)
- `components/Sidebar.tsx` — fixed sidebar nav (Client Component — needs `usePathname`)
- `components/StatusBadge.tsx` — status pill using shadcn Badge
- `components/CitationLink.tsx` — parses `[Type NMRA:id]` into Next.js Links
- `lib/api.ts` — typed fetch wrappers for all backend calls

**Modified — Backend (`rwanda-fda/backend/`)**
- `app/Http/Controllers/ProductRecallController.php` — eager-load relations on index + show
- `app/Http/Controllers/BatchController.php` — eager-load relations on index + show
- `app/Http/Controllers/AiProxyController.php` — new: proxy to ontology AI service
- `routes/api.php` — add `POST /api/ai/query` route
- `tests/Feature/ProductRecallTest.php` — add relation tests
- `tests/Feature/BatchTest.php` — add relation tests
- `tests/Feature/AiProxyTest.php` — new test file

**Modified — Infrastructure**
- `docker-compose.yml` — add `rwanda-fda-frontend` service
- `rwanda-fda/frontend/Dockerfile` — new

---

## Task 1: Scaffold Next.js project with shadcn/ui

**Files:**
- Create: `rwanda-fda/frontend/` (entire project)

- [ ] **Step 1: Scaffold Next.js 14 app**

```bash
cd /path/to/Caspian/rwanda-fda
npx create-next-app@14 frontend --typescript --tailwind --eslint --app --import-alias "@/*"
```

When prompted:
- Would you like to use `src/` directory? → **No**
- Would you like to use Turbopack? → **No**

- [ ] **Step 2: Initialise shadcn/ui**

```bash
cd frontend
npx shadcn@latest init --defaults
```

When prompted about base colour, choose **Neutral** (default is fine).

- [ ] **Step 3: Add all required shadcn components**

```bash
npx shadcn@latest add button table badge card input textarea select separator label
```

- [ ] **Step 4: Verify dev server starts**

```bash
npm run dev
```

Expected: server starts on `http://localhost:3000`, default Next.js page loads. Stop with Ctrl-C.

- [ ] **Step 5: Commit scaffold**

```bash
git add rwanda-fda/frontend
git commit -m "feat: scaffold Rwanda FDA Next.js frontend with shadcn/ui"
```

---

## Task 2: Configure API rewrite proxy

**Files:**
- Modify: `rwanda-fda/frontend/next.config.js` (or `.mjs` — whatever create-next-app generated)

- [ ] **Step 1: Replace the generated next.config file contents**

If the file is `next.config.mjs`, rename it first:
```bash
mv rwanda-fda/frontend/next.config.mjs rwanda-fda/frontend/next.config.js
```

Then write:

```js
/** @type {import('next').NextConfig} */
const nextConfig = {
  async rewrites() {
    return [
      {
        source: '/api/:path*',
        destination: `${process.env.BACKEND_URL || 'http://localhost:8001'}/api/:path*`,
      },
    ]
  },
}

module.exports = nextConfig
```

- [ ] **Step 2: Verify rewrite is active**

```bash
cd rwanda-fda/frontend && npm run dev
```

Open a second terminal and run:
```bash
curl http://localhost:3000/api/product-recalls
```

Expected: a JSON response from Laravel (or a connection refused error if Laravel isn't running — either way confirms Next.js is routing to port 8001, not returning a 404 from Next.js itself).

- [ ] **Step 3: Commit**

```bash
git add rwanda-fda/frontend/next.config.js
git commit -m "feat(frontend): add API rewrite proxy to Laravel backend"
```

---

## Task 3: Build `lib/api.ts` — typed fetch wrappers

**Files:**
- Create: `rwanda-fda/frontend/lib/api.ts`

- [ ] **Step 1: Write the file**

```typescript
// lib/api.ts
export interface Manufacturer {
  id: number
  name: string
  country: string
  registration_number: string
  license_status: 'active' | 'suspended' | 'revoked'
}

export interface Product {
  id: number
  manufacturer_id: number
  name: string
  generic_name: string
  dosage_form: string
  strength: string
  registration_number: string
}

export interface Batch {
  id: number
  product_id: number
  manufacturer_id: number
  batch_number: string
  manufacture_date: string
  expiry_date: string
  product?: Product
  manufacturer?: Manufacturer
}

export interface ProductRecall {
  id: number
  batch_id: number
  manufacturer_id: number
  recall_number: string
  reason: string
  classification: 'Class I' | 'Class II' | 'Class III'
  qc_summary: string
  status: 'active' | 'completed' | 'closed'
  date_issued: string
  scope?: string
  batch?: Batch
  manufacturer?: Manufacturer
  created_at: string
  updated_at: string
}

export interface AiQueryResponse {
  answer: string
  sources: { object_id: string; chunk_text: string }[]
}

const BASE = '/api'

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

- [ ] **Step 2: Verify TypeScript compiles**

```bash
cd rwanda-fda/frontend && npx tsc --noEmit
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add rwanda-fda/frontend/lib/api.ts
git commit -m "feat(frontend): add typed API client wrappers"
```

---

## Task 4: Build shared components

**Files:**
- Create: `rwanda-fda/frontend/components/Sidebar.tsx`
- Create: `rwanda-fda/frontend/components/StatusBadge.tsx`
- Create: `rwanda-fda/frontend/components/CitationLink.tsx`

- [ ] **Step 1: Write `Sidebar.tsx`**

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
    <aside className="w-[220px] min-h-screen bg-[#1a2e1a] flex flex-col shrink-0">
      <div className="px-6 py-6">
        <p className="text-white font-semibold text-sm tracking-wide">Rwanda FDA</p>
        <p className="text-green-300 text-xs mt-0.5">Regulatory Authority</p>
      </div>
      <Separator className="bg-green-900" />
      <nav className="flex-1 px-3 py-4 space-y-1">
        {links.map(({ href, label }) => {
          const active = pathname.startsWith(href)
          return (
            <Link
              key={href}
              href={href}
              className={`flex items-center px-3 py-2 rounded text-sm transition-colors ${
                active
                  ? 'bg-[#2d6a2d] text-white border-l-2 border-green-300'
                  : 'text-green-200 hover:bg-[#2d6a2d] hover:text-white'
              }`}
            >
              {label}
            </Link>
          )
        })}
      </nav>
      <div className="px-6 py-4">
        <p className="text-green-700 text-xs">Post-Market Surveillance</p>
      </div>
    </aside>
  )
}
```

- [ ] **Step 2: Write `StatusBadge.tsx`**

```tsx
// components/StatusBadge.tsx
import { Badge } from '@/components/ui/badge'

type Status = 'active' | 'completed' | 'closed'

const styles: Record<Status, string> = {
  active: 'bg-red-100 text-red-700 border-red-200',
  completed: 'bg-gray-100 text-gray-600 border-gray-200',
  closed: 'bg-slate-100 text-slate-500 border-slate-200',
}

export default function StatusBadge({ status }: { status: Status }) {
  return (
    <Badge variant="outline" className={styles[status] ?? styles.closed}>
      {status.charAt(0).toUpperCase() + status.slice(1)}
    </Badge>
  )
}
```

- [ ] **Step 3: Write `CitationLink.tsx`**

```tsx
// components/CitationLink.tsx
import Link from 'next/link'

const CITATION_RE = /\[(\w+)\s+([\w_]+):([\w-]+)\]/g

function linkPath(objectType: string, id: string): string | null {
  const t = objectType.toLowerCase()
  if (t === 'recall' || t === 'productrecall') return `/recalls/${id}`
  if (t === 'batch') return `/batches/${id}`
  return null
}

export default function CitationLink({ text }: { text: string }) {
  const parts: React.ReactNode[] = []
  let lastIndex = 0
  const regex = new RegExp(CITATION_RE.source, 'g')
  let match: RegExpExecArray | null

  while ((match = regex.exec(text)) !== null) {
    if (match.index > lastIndex) parts.push(text.slice(lastIndex, match.index))
    const [full, objectType, , id] = match
    const path = linkPath(objectType, id)
    parts.push(
      path ? (
        <Link
          key={match.index}
          href={path}
          className="text-blue-600 underline font-medium hover:text-blue-800"
        >
          {full}
        </Link>
      ) : (
        <span key={match.index} className="font-medium text-gray-700">
          {full}
        </span>
      )
    )
    lastIndex = match.index + full.length
  }
  if (lastIndex < text.length) parts.push(text.slice(lastIndex))

  return <>{parts}</>
}
```

- [ ] **Step 4: Verify TypeScript compiles**

```bash
cd rwanda-fda/frontend && npx tsc --noEmit
```

Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add rwanda-fda/frontend/components/
git commit -m "feat(frontend): add Sidebar, StatusBadge, CitationLink components"
```

---

## Task 5: Build root layout and redirect

**Files:**
- Modify: `rwanda-fda/frontend/app/layout.tsx`
- Modify: `rwanda-fda/frontend/app/page.tsx`

- [ ] **Step 1: Replace `app/layout.tsx`**

```tsx
// app/layout.tsx
import type { Metadata } from 'next'
import { Inter } from 'next/font/google'
import './globals.css'
import Sidebar from '@/components/Sidebar'

const inter = Inter({ subsets: ['latin'] })

export const metadata: Metadata = {
  title: 'Rwanda FDA — Post-Market Surveillance',
}

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body className={`${inter.className} flex h-screen bg-[#f8f9fa] overflow-hidden`}>
        <Sidebar />
        <main className="flex-1 overflow-auto p-8">
          {children}
        </main>
      </body>
    </html>
  )
}
```

- [ ] **Step 2: Replace `app/page.tsx`**

```tsx
// app/page.tsx
import { redirect } from 'next/navigation'

export default function Home() {
  redirect('/recalls')
}
```

- [ ] **Step 3: Start dev server and verify layout renders**

```bash
cd rwanda-fda/frontend && npm run dev
```

Open `http://localhost:3000` — should redirect to `/recalls`, show the dark-green sidebar, and display a loading/error state (recall list will 404 until backend changes in Task 6). Stop with Ctrl-C.

- [ ] **Step 4: Commit**

```bash
git add rwanda-fda/frontend/app/layout.tsx rwanda-fda/frontend/app/page.tsx
git commit -m "feat(frontend): add root layout with sidebar and home redirect"
```

---

## Task 6: Laravel backend — eager loading + AI proxy

**Files:**
- Modify: `rwanda-fda/backend/app/Http/Controllers/ProductRecallController.php`
- Modify: `rwanda-fda/backend/app/Http/Controllers/BatchController.php`
- Create: `rwanda-fda/backend/app/Http/Controllers/AiProxyController.php`
- Modify: `rwanda-fda/backend/routes/api.php`
- Modify: `rwanda-fda/backend/tests/Feature/ProductRecallTest.php`
- Modify: `rwanda-fda/backend/tests/Feature/BatchTest.php`
- Create: `rwanda-fda/backend/tests/Feature/AiProxyTest.php`

- [ ] **Step 1: Write the failing test for recall index relations**

Add this method to `tests/Feature/ProductRecallTest.php` inside the class (after the last existing test):

```php
public function test_recall_index_includes_batch_and_manufacturer_relations(): void
{
    Http::fake([
        '*/ontology/publish' => Http::response(['id' => 'some-uuid', 'object_type' => 'Manufacturer'], 200),
        '*/ontology/link'    => Http::response(['id' => 'link-uuid'], 200),
        '*/ai/ingest'        => Http::response(['status' => 'ok'], 200),
    ]);

    ['mfg' => $mfg, 'batch' => $batch] = $this->seedBatchAndManufacturer();

    $this->postJson('/api/product-recalls', [
        'batch_id' => $batch->id, 'manufacturer_id' => $mfg->id,
        'recall_number' => 'RCL-REL-001', 'reason' => 'Substandard API content',
        'classification' => 'Class II', 'qc_summary' => 'API at 72%',
        'status' => 'active', 'date_issued' => '2026-01-15',
    ]);

    $this->getJson('/api/product-recalls')
         ->assertStatus(200)
         ->assertJsonPath('0.batch.batch_number', 'LOT-4421')
         ->assertJsonPath('0.manufacturer.name', 'PharmaCo Ltd');
}

public function test_recall_show_includes_batch_and_manufacturer_relations(): void
{
    Http::fake([
        '*/ontology/publish' => Http::response(['id' => 'some-uuid', 'object_type' => 'Manufacturer'], 200),
        '*/ontology/link'    => Http::response(['id' => 'link-uuid'], 200),
        '*/ai/ingest'        => Http::response(['status' => 'ok'], 200),
    ]);

    ['mfg' => $mfg, 'batch' => $batch] = $this->seedBatchAndManufacturer();

    $recall = $this->postJson('/api/product-recalls', [
        'batch_id' => $batch->id, 'manufacturer_id' => $mfg->id,
        'recall_number' => 'RCL-SHOW-001', 'reason' => 'Substandard API content',
        'classification' => 'Class II', 'qc_summary' => 'API at 72%',
        'status' => 'active', 'date_issued' => '2026-01-15',
    ])->json();

    $this->getJson("/api/product-recalls/{$recall['id']}")
         ->assertStatus(200)
         ->assertJsonPath('batch.id', $batch->id)
         ->assertJsonPath('batch.product.registration_number', 'PRD-112')
         ->assertJsonPath('manufacturer.id', $mfg->id);
}
```

- [ ] **Step 2: Run recall tests — verify new tests fail**

```bash
cd rwanda-fda/backend && /opt/homebrew/opt/php/bin/php vendor/bin/phpunit tests/Feature/ProductRecallTest.php --no-coverage
```

Expected: `test_recall_index_includes_batch_and_manufacturer_relations` and `test_recall_show_includes_batch_and_manufacturer_relations` FAIL (no `batch` key in response).

- [ ] **Step 3: Update `ProductRecallController` index and show**

Replace the `index` and `show` methods in `app/Http/Controllers/ProductRecallController.php`:

```php
public function index(Request $request): JsonResponse
{
    $query = ProductRecall::with(['batch.product', 'manufacturer']);
    if ($request->has('status')) {
        $query->where('status', $request->input('status'));
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

Expected: all tests pass.

- [ ] **Step 5: Write the failing tests for batch relations**

Add to `tests/Feature/BatchTest.php` inside the class:

```php
public function test_batch_index_includes_product_and_manufacturer_relations(): void
{
    ['manufacturer' => $mfg, 'product' => $product] = $this->product();

    $this->postJson('/api/batches', [
        'product_id' => $product->id, 'manufacturer_id' => $mfg->id,
        'batch_number' => 'LOT-IDX-001', 'manufacture_date' => '2025-01-01',
        'expiry_date' => '2027-01-01',
    ]);

    $this->getJson('/api/batches')
         ->assertStatus(200)
         ->assertJsonPath('0.product.name', 'Amoxicillin 500mg')
         ->assertJsonPath('0.manufacturer.name', 'PharmaCo Ltd');
}

public function test_batch_show_includes_product_and_manufacturer_relations(): void
{
    ['manufacturer' => $mfg, 'product' => $product] = $this->product();

    $batch = $this->postJson('/api/batches', [
        'product_id' => $product->id, 'manufacturer_id' => $mfg->id,
        'batch_number' => 'LOT-SHOW-001', 'manufacture_date' => '2025-01-01',
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

- [ ] **Step 8: Run batch tests — verify all pass**

```bash
/opt/homebrew/opt/php/bin/php vendor/bin/phpunit tests/Feature/BatchTest.php --no-coverage
```

Expected: all tests pass.

- [ ] **Step 9: Write failing AI proxy tests**

Create `tests/Feature/AiProxyTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_query_proxies_to_ontology_with_nmra_id(): void
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
                && $request['nmra_id'] === 'RWANDA_FDA'
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

Expected: FAIL — route does not exist.

- [ ] **Step 11: Create `AiProxyController`**

Create `app/Http/Controllers/AiProxyController.php`:

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

- [ ] **Step 12: Register the route**

Add to `routes/api.php` (after the existing resource routes):

```php
use App\Http\Controllers\AiProxyController;

Route::post('ai/query', [AiProxyController::class, 'query']);
```

- [ ] **Step 13: Run AI proxy tests — verify all pass**

```bash
/opt/homebrew/opt/php/bin/php vendor/bin/phpunit tests/Feature/AiProxyTest.php --no-coverage
```

Expected: both tests pass.

- [ ] **Step 14: Run full test suite — verify nothing broken**

```bash
/opt/homebrew/opt/php/bin/php vendor/bin/phpunit --no-coverage
```

Expected: all tests pass.

- [ ] **Step 15: Commit all backend changes**

```bash
git add rwanda-fda/backend/app/Http/Controllers/ProductRecallController.php \
        rwanda-fda/backend/app/Http/Controllers/BatchController.php \
        rwanda-fda/backend/app/Http/Controllers/AiProxyController.php \
        rwanda-fda/backend/routes/api.php \
        rwanda-fda/backend/tests/Feature/ProductRecallTest.php \
        rwanda-fda/backend/tests/Feature/BatchTest.php \
        rwanda-fda/backend/tests/Feature/AiProxyTest.php
git commit -m "feat(backend): eager-load relations on index/show + add AI query proxy endpoint"
```

---

## Task 7: Build recalls pages

**Files:**
- Create: `rwanda-fda/frontend/app/recalls/page.tsx`
- Create: `rwanda-fda/frontend/app/recalls/RecallsFilter.tsx`
- Create: `rwanda-fda/frontend/app/recalls/new/page.tsx`
- Create: `rwanda-fda/frontend/app/recalls/[id]/page.tsx`

- [ ] **Step 1: Write `app/recalls/RecallsFilter.tsx`**

```tsx
// app/recalls/RecallsFilter.tsx
'use client'
import { useRouter } from 'next/navigation'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

export default function RecallsFilter({ current }: { current?: string }) {
  const router = useRouter()
  return (
    <Select
      value={current ?? 'all'}
      onValueChange={(value) =>
        router.push(value === 'all' ? '/recalls' : `/recalls?status=${value}`)
      }
    >
      <SelectTrigger className="w-[180px]">
        <SelectValue placeholder="Filter by status" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value="all">All Statuses</SelectItem>
        <SelectItem value="active">Active</SelectItem>
        <SelectItem value="completed">Completed</SelectItem>
        <SelectItem value="closed">Closed</SelectItem>
      </SelectContent>
    </Select>
  )
}
```

- [ ] **Step 2: Write `app/recalls/page.tsx`**

```tsx
// app/recalls/page.tsx
import { api } from '@/lib/api'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import StatusBadge from '@/components/StatusBadge'
import Link from 'next/link'
import RecallsFilter from './RecallsFilter'

export default async function RecallsPage({
  searchParams,
}: {
  searchParams: { status?: string }
}) {
  const recalls = await api.recalls.list(searchParams.status)

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-semibold text-gray-900">Product Recalls</h1>
        <Button asChild>
          <Link href="/recalls/new">New Recall</Link>
        </Button>
      </div>
      <div className="bg-white rounded-lg border">
        <div className="p-4 border-b">
          <RecallsFilter current={searchParams.status} />
        </div>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Recall No.</TableHead>
              <TableHead>Product</TableHead>
              <TableHead>Batch</TableHead>
              <TableHead>Classification</TableHead>
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
                  <TableCell className="font-mono text-sm">{recall.recall_number}</TableCell>
                  <TableCell>{recall.batch?.product?.name ?? '—'}</TableCell>
                  <TableCell className="font-mono text-sm">{recall.batch?.batch_number ?? '—'}</TableCell>
                  <TableCell>{recall.classification}</TableCell>
                  <TableCell><StatusBadge status={recall.status} /></TableCell>
                  <TableCell>{recall.date_issued}</TableCell>
                  <TableCell>
                    <Button variant="ghost" size="sm" asChild>
                      <Link href={`/recalls/${recall.id}`}>View</Link>
                    </Button>
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

- [ ] **Step 3: Write `app/recalls/new/page.tsx`**

```tsx
// app/recalls/new/page.tsx
'use client'
import { useState, useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { api, Batch, Manufacturer } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'

export default function NewRecallPage() {
  const router = useRouter()
  const [batches, setBatches] = useState<Batch[]>([])
  const [manufacturers, setManufacturers] = useState<Manufacturer[]>([])
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  const [form, setForm] = useState({
    batch_id: '', manufacturer_id: '', recall_number: '',
    classification: '', reason: '', qc_summary: '',
    date_issued: '', scope: '', status: 'active',
  })

  useEffect(() => {
    api.batches.list().then(setBatches)
    api.manufacturers.list().then(setManufacturers)
  }, [])

  function field(key: keyof typeof form, value: string) {
    setForm(f => ({ ...f, [key]: value }))
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})
    try {
      const recall = await api.recalls.create({
        ...form,
        batch_id: Number(form.batch_id),
        manufacturer_id: Number(form.manufacturer_id),
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
              <Label>Batch</Label>
              <Select onValueChange={v => field('batch_id', v)}>
                <SelectTrigger><SelectValue placeholder="Select batch" /></SelectTrigger>
                <SelectContent>
                  {batches.map(b => (
                    <SelectItem key={b.id} value={String(b.id)}>{b.batch_number}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldError name="batch_id" />
            </div>

            <div className="space-y-1">
              <Label>Manufacturer</Label>
              <Select onValueChange={v => field('manufacturer_id', v)}>
                <SelectTrigger><SelectValue placeholder="Select manufacturer" /></SelectTrigger>
                <SelectContent>
                  {manufacturers.map(m => (
                    <SelectItem key={m.id} value={String(m.id)}>{m.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldError name="manufacturer_id" />
            </div>

            <div className="space-y-1">
              <Label>Recall Number</Label>
              <Input value={form.recall_number} onChange={e => field('recall_number', e.target.value)} placeholder="RCL-001" />
              <FieldError name="recall_number" />
            </div>

            <div className="space-y-1">
              <Label>Classification</Label>
              <Select onValueChange={v => field('classification', v)}>
                <SelectTrigger><SelectValue placeholder="Select classification" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="Class I">Class I</SelectItem>
                  <SelectItem value="Class II">Class II</SelectItem>
                  <SelectItem value="Class III">Class III</SelectItem>
                </SelectContent>
              </Select>
              <FieldError name="classification" />
            </div>

            <div className="space-y-1">
              <Label>Reason</Label>
              <Textarea value={form.reason} onChange={e => field('reason', e.target.value)} placeholder="Reason for recall..." />
              <FieldError name="reason" />
            </div>

            <div className="space-y-1">
              <Label>QC Summary</Label>
              <Textarea value={form.qc_summary} onChange={e => field('qc_summary', e.target.value)} placeholder="Laboratory findings..." />
              <FieldError name="qc_summary" />
            </div>

            <div className="space-y-1">
              <Label>Date Issued</Label>
              <Input type="date" value={form.date_issued} onChange={e => field('date_issued', e.target.value)} />
              <FieldError name="date_issued" />
            </div>

            <div className="space-y-1">
              <Label>Scope (optional)</Label>
              <Input value={form.scope} onChange={e => field('scope', e.target.value)} placeholder="National / Regional..." />
            </div>

            <div className="space-y-1">
              <Label>Status</Label>
              <Select defaultValue="active" onValueChange={v => field('status', v)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="active">Active</SelectItem>
                  <SelectItem value="completed">Completed</SelectItem>
                  <SelectItem value="closed">Closed</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="flex gap-3 pt-2">
              <Button type="submit" disabled={submitting}>
                {submitting ? 'Submitting...' : 'Create Recall'}
              </Button>
              <Button type="button" variant="outline" onClick={() => router.push('/recalls')}>
                Cancel
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
```

- [ ] **Step 4: Write `app/recalls/[id]/page.tsx`**

```tsx
// app/recalls/[id]/page.tsx
import { api } from '@/lib/api'
import { notFound } from 'next/navigation'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
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

export default async function RecallDetailPage({ params }: { params: { id: string } }) {
  let recall
  try {
    recall = await api.recalls.get(params.id)
  } catch {
    notFound()
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">{recall.recall_number}</h1>
          <div className="mt-1"><StatusBadge status={recall.status} /></div>
        </div>
        <Button variant="outline" asChild>
          <Link href="/recalls">← Back to Recalls</Link>
        </Button>
      </div>
      <div className="grid grid-cols-2 gap-6">
        <Card>
          <CardHeader><CardTitle>Recall Information</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <Row label="Recall Number" value={recall.recall_number} />
            <Row label="Classification" value={recall.classification} />
            <Row label="Date Issued" value={recall.date_issued} />
            <Row label="Scope" value={recall.scope ?? '—'} />
            <Row label="Reason" value={recall.reason} />
            <Row label="QC Summary" value={recall.qc_summary} />
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle>Batch & Manufacturer</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <Row label="Batch Number" value={recall.batch?.batch_number ?? '—'} />
            <Row label="Product" value={recall.batch?.product?.name ?? '—'} />
            <Row label="Generic Name" value={recall.batch?.product?.generic_name ?? '—'} />
            <Row label="Strength" value={recall.batch?.product?.strength ?? '—'} />
            <Row label="Manufacture Date" value={recall.batch?.manufacture_date ?? '—'} />
            <Row label="Expiry Date" value={recall.batch?.expiry_date ?? '—'} />
            <Row label="Manufacturer" value={recall.manufacturer?.name ?? '—'} />
            <Row label="Country" value={recall.manufacturer?.country ?? '—'} />
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
```

- [ ] **Step 5: Verify recalls pages in the browser**

With Laravel running on port 8001 and `npm run dev` running:

1. Navigate to `http://localhost:3000/recalls` — table renders (empty or with seeded data)
2. Navigate to `http://localhost:3000/recalls/new` — form renders with batch/manufacturer selects populated
3. Submit the form with valid data — redirects to detail page
4. Detail page shows recall info + batch/manufacturer cards

- [ ] **Step 6: Commit**

```bash
git add rwanda-fda/frontend/app/recalls/
git commit -m "feat(frontend): add recalls list, create form, and detail pages"
```

---

## Task 8: Build batches pages

**Files:**
- Create: `rwanda-fda/frontend/app/batches/page.tsx`
- Create: `rwanda-fda/frontend/app/batches/[id]/page.tsx`

- [ ] **Step 1: Write `app/batches/page.tsx`**

```tsx
// app/batches/page.tsx
import { api } from '@/lib/api'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
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
              <TableHead>Batch No.</TableHead>
              <TableHead>Product</TableHead>
              <TableHead>Manufacturer</TableHead>
              <TableHead>Manufacture Date</TableHead>
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
                  <TableCell className="font-mono text-sm">{batch.batch_number}</TableCell>
                  <TableCell>{batch.product?.name ?? '—'}</TableCell>
                  <TableCell>{batch.manufacturer?.name ?? '—'}</TableCell>
                  <TableCell>{batch.manufacture_date}</TableCell>
                  <TableCell>{batch.expiry_date}</TableCell>
                  <TableCell>
                    <Button variant="ghost" size="sm" asChild>
                      <Link href={`/batches/${batch.id}`}>View</Link>
                    </Button>
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
import { Button } from '@/components/ui/button'
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

export default async function BatchDetailPage({ params }: { params: { id: string } }) {
  let batch
  try {
    batch = await api.batches.get(params.id)
  } catch {
    notFound()
  }

  const allRecalls = await api.recalls.list()
  const recalls = allRecalls.filter(r => r.batch_id === batch.id)

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-semibold text-gray-900">{batch.batch_number}</h1>
        <Button variant="outline" asChild>
          <Link href="/batches">← Back to Batches</Link>
        </Button>
      </div>
      <div className="space-y-6">
        <Card>
          <CardHeader><CardTitle>Batch Information</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <Row label="Batch Number" value={batch.batch_number} />
            <Row label="Product" value={batch.product?.name ?? '—'} />
            <Row label="Generic Name" value={batch.product?.generic_name ?? '—'} />
            <Row label="Strength" value={batch.product?.strength ?? '—'} />
            <Row label="Manufacturer" value={batch.manufacturer?.name ?? '—'} />
            <Row label="Manufacture Date" value={batch.manufacture_date} />
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
                    <TableHead>Recall No.</TableHead>
                    <TableHead>Classification</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Date Issued</TableHead>
                    <TableHead></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {recalls.map(r => (
                    <TableRow key={r.id}>
                      <TableCell className="font-mono text-sm">{r.recall_number}</TableCell>
                      <TableCell>{r.classification}</TableCell>
                      <TableCell><StatusBadge status={r.status} /></TableCell>
                      <TableCell>{r.date_issued}</TableCell>
                      <TableCell>
                        <Button variant="ghost" size="sm" asChild>
                          <Link href={`/recalls/${r.id}`}>View</Link>
                        </Button>
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

- [ ] **Step 3: Verify batches pages in the browser**

1. Navigate to `http://localhost:3000/batches` — table renders with product and manufacturer names
2. Click "View" on a batch — detail page shows batch info and linked recalls table

- [ ] **Step 4: Commit**

```bash
git add rwanda-fda/frontend/app/batches/
git commit -m "feat(frontend): add batches list and detail pages"
```

---

## Task 9: Build AI chat page

**Files:**
- Create: `rwanda-fda/frontend/app/ai/page.tsx`

- [ ] **Step 1: Write `app/ai/page.tsx`**

```tsx
// app/ai/page.tsx
'use client'
import { useState, useRef, useEffect } from 'react'
import { api, AiQueryResponse } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent } from '@/components/ui/card'
import CitationLink from '@/components/CitationLink'

interface Message {
  role: 'user' | 'ai'
  content: string
  sources?: AiQueryResponse['sources']
}

export default function AiPage() {
  const [messages, setMessages] = useState<Message[]>([])
  const [question, setQuestion] = useState('')
  const [loading, setLoading] = useState(false)
  const bottomRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!question.trim() || loading) return
    const q = question.trim()
    setMessages(prev => [...prev, { role: 'user', content: q }])
    setQuestion('')
    setLoading(true)
    try {
      const data = await api.ai.query(q)
      setMessages(prev => [...prev, { role: 'ai', content: data.answer, sources: data.sources }])
    } catch {
      setMessages(prev => [
        ...prev,
        { role: 'ai', content: 'An error occurred. Please try again.' },
      ])
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex flex-col h-full max-h-[calc(100vh-4rem)]">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold text-gray-900">AI Regulatory Query</h1>
        <p className="text-sm text-gray-500 mt-0.5">
          Ask questions about regulatory data across the shared ontology
        </p>
      </div>

      <div className="flex-1 overflow-auto space-y-4 mb-4 min-h-0">
        {messages.length === 0 && (
          <div className="text-center text-gray-400 py-16">
            <p className="text-lg font-medium">Ask a regulatory question</p>
            <p className="text-sm mt-1 max-w-md mx-auto">
              e.g. "Are there any active recalls I should know about before approving a batch import?"
            </p>
          </div>
        )}

        {messages.map((msg, i) => (
          <div
            key={i}
            className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}
          >
            {msg.role === 'user' ? (
              <div className="max-w-xl bg-[#2d6a2d] text-white rounded-lg px-4 py-2">
                <p className="text-sm">{msg.content}</p>
              </div>
            ) : (
              <Card className="max-w-2xl w-full">
                <CardContent className="pt-4 pb-4">
                  <p className="text-sm leading-relaxed whitespace-pre-wrap">
                    <CitationLink text={msg.content} />
                  </p>
                  {msg.sources && msg.sources.length > 0 && (
                    <details className="mt-4">
                      <summary className="text-xs text-gray-500 cursor-pointer hover:text-gray-700">
                        {msg.sources.length} source{msg.sources.length !== 1 ? 's' : ''} cited
                      </summary>
                      <div className="mt-2 space-y-2">
                        {msg.sources.map((s, j) => (
                          <div
                            key={j}
                            className="text-xs text-gray-600 bg-gray-50 rounded p-2 font-mono break-all"
                          >
                            <span className="text-gray-400 block">{s.object_id}</span>
                            {s.chunk_text}
                          </div>
                        ))}
                      </div>
                    </details>
                  )}
                </CardContent>
              </Card>
            )}
          </div>
        ))}

        {loading && (
          <div className="flex justify-start">
            <Card className="max-w-2xl">
              <CardContent className="pt-4 pb-4">
                <p className="text-sm text-gray-400 animate-pulse">
                  Querying regulatory intelligence...
                </p>
              </CardContent>
            </Card>
          </div>
        )}
        <div ref={bottomRef} />
      </div>

      <form onSubmit={handleSubmit} className="flex gap-3 items-end border-t pt-4 shrink-0">
        <Textarea
          value={question}
          onChange={e => setQuestion(e.target.value)}
          onKeyDown={e => {
            if (e.key === 'Enter' && !e.shiftKey) {
              e.preventDefault()
              handleSubmit(e as any)
            }
          }}
          placeholder="Ask a regulatory question... (Enter to send, Shift+Enter for newline)"
          className="flex-1 resize-none"
          rows={2}
        />
        <Button type="submit" disabled={loading || !question.trim()}>
          {loading ? 'Querying...' : 'Send'}
        </Button>
      </form>
    </div>
  )
}
```

- [ ] **Step 2: Verify AI chat in the browser**

With all services running (Laravel on 8001, Python AI service on 8000):

1. Navigate to `http://localhost:3000/ai`
2. Type: "Are there any active recalls I should know about before approving a batch import?"
3. Click Send
4. Expected: AI response appears with citation like `[Recall RWANDA_FDA:some-uuid]` rendered as a clickable link
5. Click the citation link — navigates to `/recalls/[id]`
6. Sources panel shows collapsible raw chunks

- [ ] **Step 3: Commit**

```bash
git add rwanda-fda/frontend/app/ai/
git commit -m "feat(frontend): add AI chat page with citation links"
```

---

## Task 10: Add frontend to docker-compose

**Files:**
- Create: `rwanda-fda/frontend/Dockerfile`
- Modify: `docker-compose.yml`

- [ ] **Step 1: Write `rwanda-fda/frontend/Dockerfile`**

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

- [ ] **Step 2: Add frontend service to `docker-compose.yml`**

Add the following service block after the `nafdac-backend` service:

```yaml
  rwanda-fda-frontend:
    build: ./rwanda-fda/frontend
    ports:
      - "3000:3000"
    environment:
      BACKEND_URL: http://rwanda-fda-backend:8000
    depends_on:
      - rwanda-fda-backend
```

- [ ] **Step 3: Verify the full stack builds**

```bash
docker compose build rwanda-fda-frontend
```

Expected: image builds without errors.

- [ ] **Step 4: Commit**

```bash
git add rwanda-fda/frontend/Dockerfile docker-compose.yml
git commit -m "feat(infra): add Rwanda FDA frontend to docker-compose"
```

---

## Self-Review Checklist (already applied)

- [x] All spec requirements have a corresponding task
- [x] No TBD, TODO, or placeholder steps
- [x] Type names consistent across tasks (`ProductRecall`, `Batch`, `Manufacturer`, `AiQueryResponse`)
- [x] API paths consistent with Laravel routes (`/api/product-recalls`, `/api/batches`, `/api/manufacturers`, `/api/ai/query`)
- [x] Eager loading covers both `index` and `show` for both controllers
- [x] TDD applied to all backend changes (tests written before implementation in Tasks 6)
- [x] Citation link routing covers `Recall`/`ProductRecall` → `/recalls/[id]` and `Batch` → `/batches/[id]`
