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
