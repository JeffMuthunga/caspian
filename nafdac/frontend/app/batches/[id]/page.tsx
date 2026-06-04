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
  const linkedRecalls = allRecalls.filter((r) => r.batch_id === batch.id)

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">{batch.batch_number}</h1>
          <p className="text-sm text-gray-500 mt-1">{batch.product?.name ?? '—'}</p>
        </div>
        <Link href="/batches" className={buttonVariants({ variant: 'outline' })}>
          ← Back to Batches
        </Link>
      </div>

      <div className="grid grid-cols-2 gap-6 mb-6">
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
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Linked Recalls ({linkedRecalls.length})</CardTitle>
        </CardHeader>
        <CardContent className="p-0">
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
              {linkedRecalls.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="text-center text-gray-500 py-8">
                    No recalls for this batch.
                  </TableCell>
                </TableRow>
              ) : (
                linkedRecalls.map((recall) => (
                  <TableRow key={recall.id}>
                    <TableCell className="font-mono text-sm">{recall.recall_number}</TableCell>
                    <TableCell>{recall.classification}</TableCell>
                    <TableCell><StatusBadge status={recall.status} /></TableCell>
                    <TableCell>{recall.date_issued}</TableCell>
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
