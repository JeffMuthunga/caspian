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

export default async function RecallDetailPage({ params }: { params: Promise<{ id: string }> }) {
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
          <h1 className="text-2xl font-semibold text-gray-900">{recall.recall_number}</h1>
          <div className="mt-1"><StatusBadge status={recall.status} /></div>
        </div>
        <Link href="/recalls" className={buttonVariants({ variant: 'outline' })}>
          ← Back to Recalls
        </Link>
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
