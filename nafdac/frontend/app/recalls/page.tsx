// app/recalls/page.tsx
import { api } from '@/lib/api'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import { buttonVariants } from '@/components/ui/button'
import StatusBadge from '@/components/StatusBadge'
import Link from 'next/link'
import RecallsFilter from './RecallsFilter'
import { cn } from '@/lib/utils'

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
        <Link href="/recalls/new" className={buttonVariants({ variant: 'default' })}>
          New Recall
        </Link>
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
              <TableHead>Issue Date</TableHead>
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
      </div>
    </div>
  )
}
