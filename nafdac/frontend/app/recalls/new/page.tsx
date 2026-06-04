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
    lot_id: '', supplier_id: '', alert_reference: '',
    severity_grade: '', recall_reason: '', laboratory_findings: '',
    issue_date: '', affected_regions: '', recall_status: 'active',
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
        lot_id: Number(form.lot_id),
        supplier_id: Number(form.supplier_id),
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
              <Label>Batch (Lot)</Label>
              <Select onValueChange={(v: unknown) => field('lot_id', v as string | null)}>
                <SelectTrigger><SelectValue placeholder="Select lot" /></SelectTrigger>
                <SelectContent>
                  {batches.map(b => (
                    <SelectItem key={b.id} value={String(b.id)}>{b.lot_number}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldError name="lot_id" />
            </div>

            <div className="space-y-1">
              <Label>Supplier / Manufacturer</Label>
              <Select onValueChange={(v: unknown) => field('supplier_id', v as string | null)}>
                <SelectTrigger><SelectValue placeholder="Select supplier" /></SelectTrigger>
                <SelectContent>
                  {manufacturers.map(m => (
                    <SelectItem key={m.id} value={String(m.id)}>{m.company_name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldError name="supplier_id" />
            </div>

            <div className="space-y-1">
              <Label>Alert Reference</Label>
              <Input value={form.alert_reference} onChange={e => field('alert_reference', e.target.value)} placeholder="ALT-001" />
              <FieldError name="alert_reference" />
            </div>

            <div className="space-y-1">
              <Label>Severity Grade</Label>
              <Select onValueChange={(v: unknown) => field('severity_grade', v as string | null)}>
                <SelectTrigger><SelectValue placeholder="Select severity grade" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="Grade I">Grade I</SelectItem>
                  <SelectItem value="Grade II">Grade II</SelectItem>
                  <SelectItem value="Grade III">Grade III</SelectItem>
                </SelectContent>
              </Select>
              <FieldError name="severity_grade" />
            </div>

            <div className="space-y-1">
              <Label>Recall Reason</Label>
              <Textarea value={form.recall_reason} onChange={e => field('recall_reason', e.target.value)} placeholder="Reason for recall..." />
              <FieldError name="recall_reason" />
            </div>

            <div className="space-y-1">
              <Label>Laboratory Findings</Label>
              <Textarea value={form.laboratory_findings} onChange={e => field('laboratory_findings', e.target.value)} placeholder="Laboratory findings..." />
              <FieldError name="laboratory_findings" />
            </div>

            <div className="space-y-1">
              <Label>Issue Date</Label>
              <Input type="date" value={form.issue_date} onChange={e => field('issue_date', e.target.value)} />
              <FieldError name="issue_date" />
            </div>

            <div className="space-y-1">
              <Label>Affected Regions (optional)</Label>
              <Input value={form.affected_regions} onChange={e => field('affected_regions', e.target.value)} placeholder="National / Regional..." />
            </div>

            <div className="space-y-1">
              <Label>Status</Label>
              <Select defaultValue="active" onValueChange={(v: unknown) => field('recall_status', v as string | null)}>
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
