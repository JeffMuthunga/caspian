// app/recalls/new/page.tsx
'use client'
import { useState, useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { api, Batch, Manufacturer } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'

export default function NewRecallPage() {
  const router = useRouter()
  const [batches, setBatches] = useState<Batch[]>([])
  const [manufacturers, setManufacturers] = useState<Manufacturer[]>([])
  const [submitting, setSubmitting] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  const [form, setForm] = useState({
    batch_id: '', manufacturer_id: '', recall_number: '',
    classification: '', reason: '', qc_summary: '',
    date_issued: '', scope: '', status: 'active',
  })

  useEffect(() => {
    api.batches.list().then(setBatches)
    api.manufacturers.list().then(setManufacturers)
  }, [])

  function field(key: keyof typeof form, value: string | null) {
    setForm(f => ({ ...f, [key]: value ?? '' }))
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})
    try {
      const recall = await api.recalls.create({
        ...form,
        batch_id: Number(form.batch_id),
        manufacturer_id: Number(form.manufacturer_id),
      })
      router.push(`/recalls/${recall.id}`)
    } catch (err: unknown) {
      const apiErr = err as { data?: { errors?: Record<string, string[]> } }
      if (apiErr.data?.errors) setErrors(apiErr.data.errors)
    } finally {
      setSubmitting(false)
    }
  }

  function FieldError({ name }: { name: string }) {
    return errors[name] ? (
      <p className="text-red-500 text-xs mt-0.5">{errors[name][0]}</p>
    ) : null
  }

  return (
    <div>
      <h1 className="text-2xl font-semibold text-gray-900 mb-6">New Product Recall</h1>
      <Card className="max-w-2xl">
        <CardHeader><CardTitle>Recall Details</CardTitle></CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-4">

            <div className="space-y-1">
              <Label>Batch</Label>
              <Select onValueChange={(v: unknown) => field('batch_id', v as string | null)}>
                <SelectTrigger><SelectValue placeholder="Select batch" /></SelectTrigger>
                <SelectContent>
                  {batches.map(b => (
                    <SelectItem key={b.id} value={String(b.id)}>{b.batch_number}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldError name="batch_id" />
            </div>

            <div className="space-y-1">
              <Label>Manufacturer</Label>
              <Select onValueChange={(v: unknown) => field('manufacturer_id', v as string | null)}>
                <SelectTrigger><SelectValue placeholder="Select manufacturer" /></SelectTrigger>
                <SelectContent>
                  {manufacturers.map(m => (
                    <SelectItem key={m.id} value={String(m.id)}>{m.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldError name="manufacturer_id" />
            </div>

            <div className="space-y-1">
              <Label>Recall Number</Label>
              <Input value={form.recall_number} onChange={e => field('recall_number', e.target.value)} placeholder="RCL-001" />
              <FieldError name="recall_number" />
            </div>

            <div className="space-y-1">
              <Label>Classification</Label>
              <Select onValueChange={(v: unknown) => field('classification', v as string | null)}>
                <SelectTrigger><SelectValue placeholder="Select classification" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="Class I">Class I</SelectItem>
                  <SelectItem value="Class II">Class II</SelectItem>
                  <SelectItem value="Class III">Class III</SelectItem>
                </SelectContent>
              </Select>
              <FieldError name="classification" />
            </div>

            <div className="space-y-1">
              <Label>Reason</Label>
              <Textarea value={form.reason} onChange={e => field('reason', e.target.value)} placeholder="Reason for recall..." />
              <FieldError name="reason" />
            </div>

            <div className="space-y-1">
              <Label>QC Summary</Label>
              <Textarea value={form.qc_summary} onChange={e => field('qc_summary', e.target.value)} placeholder="Laboratory findings..." />
              <FieldError name="qc_summary" />
            </div>

            <div className="space-y-1">
              <Label>Date Issued</Label>
              <Input type="date" value={form.date_issued} onChange={e => field('date_issued', e.target.value)} />
              <FieldError name="date_issued" />
            </div>

            <div className="space-y-1">
              <Label>Scope (optional)</Label>
              <Input value={form.scope} onChange={e => field('scope', e.target.value)} placeholder="National / Regional..." />
            </div>

            <div className="space-y-1">
              <Label>Status</Label>
              <Select defaultValue="active" onValueChange={(v: unknown) => field('status', v as string | null)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="active">Active</SelectItem>
                  <SelectItem value="completed">Completed</SelectItem>
                  <SelectItem value="closed">Closed</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="flex gap-3 pt-2">
              <Button type="submit" disabled={submitting}>
                {submitting ? 'Submitting...' : 'Create Recall'}
              </Button>
              <Button type="button" variant="outline" onClick={() => router.push('/recalls')}>
                Cancel
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
