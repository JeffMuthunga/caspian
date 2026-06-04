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
