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
      onValueChange={(value) => {
        if (!value) return
        router.push(value === 'all' ? '/recalls' : `/recalls?status=${value}`)
      }}
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
