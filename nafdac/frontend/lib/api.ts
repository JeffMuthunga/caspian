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
