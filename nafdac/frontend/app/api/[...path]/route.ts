import { NextRequest, NextResponse } from 'next/server'

async function proxy(req: NextRequest, { params }: { params: { path: string[] } }) {
  const backend = process.env['BACKEND_URL'] ?? 'http://localhost:8002'
  const path = params.path.join('/')
  const search = new URL(req.url).search
  const target = `${backend}/api/${path}${search}`

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  }

  const init: RequestInit = { method: req.method, headers }

  if (req.method !== 'GET' && req.method !== 'HEAD') {
    init.body = await req.text()
  }

  const res = await fetch(target, init)
  const body = await res.text()

  return new NextResponse(body, {
    status: res.status,
    headers: { 'Content-Type': res.headers.get('Content-Type') ?? 'application/json' },
  })
}

export const GET = proxy
export const POST = proxy
export const PUT = proxy
export const DELETE = proxy
export const PATCH = proxy
