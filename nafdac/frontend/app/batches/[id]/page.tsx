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
import { cn } from '@/lib/utils'

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs text-gray-500 uppercase tracking-wide">{label}</p>
      <p className="text-sm text-gray-900 mt-0.5">{value}</p>
    </div>
  )
}

export default async function BatchDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params

  let batch
  try {
    batch = await api.batches.get(id)
  } catch {
    notFound()
  }

  const allRecalls = await api.recalls.list()
  const linkedRecalls = allRecalls.filter((r) => r.lot_id === batch.id)

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">{batch.lot_number}</h1>
          <p className="text-sm text-gray-500 mt-1">{batch.product?.product_name ?? '—'}</p>
        </div>
        <Link href="/batches" className={buttonVariants({ variant: 'outline' })}>
          ← Back to Batches
        </Link>
      </div>

      <div className="grid grid-cols-2 gap-6 mb-6">
        <Card>
          <CardHeader><CardTitle>Batch Information</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <Row label="Lot Number" value={batch.lot_number} />
            <Row label="Product" value={batch.product?.product_name ?? '—'} />
            <Row label="INN Name" value={batch.product?.inn_name ?? '—'} />
            <Row label="Potency" value={batch.product?.potency ?? '—'} />
            <Row label="Manufacturer" value={batch.manufacturer?.company_name ?? '—'} />
            <Row label="Production Date" value={batch.production_date} />
            <Row label="Expiry Date" value={batch.expiry_date} />
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Linked Recalls ({linkedRecalls.length})</CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Alert Reference</TableHead>
                <TableHead>Severity Grade</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Issue Date</TableHead>
                <TableHead></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {linkedRecalls.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="text-center text-gray-500 py-8">
                    No recalls for this batch.
                  </TableCell>
                </TableRow>
              ) : (
                linkedRecalls.map((recall) => (
                  <TableRow key={recall.id}>
                    <TableCell className="font-mono text-sm">{recall.alert_reference}</TableCell>
                    <TableCell>{recall.severity_grade}</TableCell>
                    <TableCell><StatusBadge status={recall.recall_status} /></TableCell>
                    <TableCell>{recall.issue_date}</TableCell>
                    <TableCell>
                      <Link
                        href={`/recalls/${recall.id}`}
                        className={cn(buttonVariants({ variant: 'ghost', size: 'sm' }))}
                      >
                        View
                      </Link>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}
