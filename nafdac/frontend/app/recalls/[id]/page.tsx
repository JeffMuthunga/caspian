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
            <Row label="Status" value={recall.recall_status} />
            <Row label="Issue Date" value={recall.issue_date} />
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
            <Row label="INN Name" value={recall.batch?.product?.inn_name ?? '—'} />
            <Row label="Potency" value={recall.batch?.product?.potency ?? '—'} />
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
