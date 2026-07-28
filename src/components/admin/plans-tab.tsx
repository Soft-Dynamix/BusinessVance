'use client'

import { useState, useEffect, useCallback } from 'react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Plus, Pencil, Trash2, Eye, EyeOff, Search } from 'lucide-react'

interface Plan {
  id: string; name: string; subtitle: string; price: number; color: string
  buttonLabel: string; buttonType: string; visible: boolean; featured: boolean
  displayOrder: number; category?: { id: string; name: string } | null
  features: { id: string; text: string }[]
}
interface Category { id: string; name: string }

const emptyForm = {
  name: '', subtitle: '', price: '0', color: '#002B5C', buttonLabel: 'GET STARTED',
  buttonType: 'cart', categoryId: '', visible: true, featured: false, displayOrder: '0', featuresText: ''
}

export function PlansTab() {
  const [plans, setPlans] = useState<Plan[]>([])
  const [categories, setCategories] = useState<Category[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [editItem, setEditItem] = useState<Plan | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [form, setForm] = useState(emptyForm)

  const loadData = useCallback(async () => {
    try {
      const [pRes, cRes] = await Promise.all([
        fetch('/api/plans?all=true').then(r => r.json()),
        fetch('/api/categories').then(r => r.json())
      ])
      setPlans(pRes.plans || [])
      setCategories(cRes.categories || [])
    } catch (e) { console.error(e) } finally { setLoading(false) }
  }, [])

  useEffect(() => { loadData() }, [loadData])

  const openCreate = () => {
    setEditItem(null)
    setForm(emptyForm)
    setDialogOpen(true)
  }

  const openEdit = (p: Plan) => {
    setEditItem(p)
    setForm({
      name: p.name, subtitle: p.subtitle, price: String(p.price), color: p.color,
      buttonLabel: p.buttonLabel, buttonType: p.buttonType,
      categoryId: '', visible: p.visible, featured: p.featured,
      displayOrder: String(p.displayOrder),
      featuresText: p.features.map(f => f.text).join('\n')
    })
    setDialogOpen(true)
  }

  const handleSave = async () => {
    const features = form.featuresText.split('\n').map(f => f.trim()).filter(Boolean)
    const data = {
      ...form, price: parseFloat(form.price) || 0,
      displayOrder: parseInt(form.displayOrder) || 0, features
    }
    try {
      if (editItem) {
        await fetch('/api/plans', {
          method: 'PUT', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: editItem.id, ...data })
        })
      } else {
        await fetch('/api/plans', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        })
      }
      setDialogOpen(false)
      loadData()
    } catch (e) { console.error(e) }
  }

  const handleDelete = async (id: string) => {
    if (!confirm('Delete this plan?')) return
    try {
      await fetch(`/api/plans?id=${id}`, { method: 'DELETE' })
      loadData()
    } catch (e) { console.error(e) }
  }

  const toggleVisible = async (p: Plan) => {
    try {
      await fetch('/api/plans', {
        method: 'PUT', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: p.id, visible: !p.visible })
      })
      loadData()
    } catch (e) { console.error(e) }
  }

  const filtered = plans.filter(p =>
    p.name.toLowerCase().includes(search.toLowerCase())
  )

  if (loading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-64 w-full" />
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <h2 className="text-2xl font-bold text-[#002B5C]">Plans</h2>
        <div className="flex gap-2">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <Input
              placeholder="Search..."
              value={search}
              onChange={e => setSearch(e.target.value)}
              className="pl-9 w-48"
            />
          </div>
          <Button onClick={openCreate} className="bg-[#002B5C] hover:bg-[#002B5C]/90">
            <Plus className="h-4 w-4 mr-1" />Add
          </Button>
        </div>
      </div>

      <Card>
        <CardContent className="p-0">
          <div className="max-h-[60vh] overflow-y-auto">
            <Table>
              <TableHeader>
                <TableRow className="bg-gray-50">
                  <TableHead className="w-12">#</TableHead>
                  <TableHead>Name</TableHead>
                  <TableHead className="hidden md:table-cell">Category</TableHead>
                  <TableHead>Price</TableHead>
                  <TableHead className="hidden lg:table-cell">Features</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="w-28">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filtered.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={7} className="text-center py-8 text-gray-500">No plans found</TableCell>
                  </TableRow>
                )}
                {filtered.map(p => (
                  <TableRow key={p.id} className={!p.visible ? 'opacity-50' : ''}>
                    <TableCell>
                      <div className="w-4 h-4 rounded" style={{ backgroundColor: p.color }} />
                    </TableCell>
                    <TableCell>
                      <div className="font-medium">
                        {p.name}
                        {p.featured && <Badge className="ml-2 bg-[#D4AF37] text-white text-[10px] px-1.5">Featured</Badge>}
                      </div>
                      {p.subtitle && <div className="text-xs text-gray-400">{p.subtitle}</div>}
                    </TableCell>
                    <TableCell className="hidden md:table-cell text-gray-500 text-sm">
                      {p.category?.name || '—'}
                    </TableCell>
                    <TableCell className="font-mono text-sm">R{p.price.toFixed(2)}</TableCell>
                    <TableCell className="hidden lg:table-cell text-xs text-gray-500 max-w-48 truncate">
                      {p.features.map(f => f.text).join(', ')}
                    </TableCell>
                    <TableCell>
                      <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => toggleVisible(p)}>
                        {p.visible ? <Eye className="h-3.5 w-3.5" /> : <EyeOff className="h-3.5 w-3.5" />}
                      </Button>
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => openEdit(p)}>
                          <Pencil className="h-3.5 w-3.5" />
                        </Button>
                        <Button variant="ghost" size="icon" className="h-7 w-7 text-red-500" onClick={() => handleDelete(p.id)}>
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{editItem ? 'Edit' : 'Create'} Plan</DialogTitle>
          </DialogHeader>
          <div className="grid gap-4 py-2">
            <div className="grid gap-2">
              <Label>Name</Label>
              <Input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} />
            </div>
            <div className="grid gap-2">
              <Label>Subtitle</Label>
              <Input value={form.subtitle} onChange={e => setForm({ ...form, subtitle: e.target.value })} />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-2">
                <Label>Price</Label>
                <Input type="number" step="0.01" value={form.price} onChange={e => setForm({ ...form, price: e.target.value })} />
              </div>
              <div className="grid gap-2">
                <Label>Color</Label>
                <div className="flex gap-2">
                  <Input type="color" value={form.color} onChange={e => setForm({ ...form, color: e.target.value })} className="w-12 h-9 p-1" />
                  <Input value={form.color} onChange={e => setForm({ ...form, color: e.target.value })} />
                </div>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-2">
                <Label>Display Order</Label>
                <Input type="number" value={form.displayOrder} onChange={e => setForm({ ...form, displayOrder: e.target.value })} />
              </div>
              <div className="grid gap-2">
                <Label>Category</Label>
                <Select value={form.categoryId} onValueChange={v => setForm({ ...form, categoryId: v })}>
                  <SelectTrigger><SelectValue placeholder="None" /></SelectTrigger>
                  <SelectContent>
                    {categories.map(c => <SelectItem key={c.id} value={c.id}>{c.name}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="grid gap-2">
              <Label>Features (one per line)</Label>
              <Textarea
                value={form.featuresText}
                onChange={e => setForm({ ...form, featuresText: e.target.value })}
                rows={5}
                placeholder="Feature 1&#10;Feature 2&#10;Feature 3"
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-2">
                <Label>Button Label</Label>
                <Input value={form.buttonLabel} onChange={e => setForm({ ...form, buttonLabel: e.target.value })} />
              </div>
              <div className="grid gap-2">
                <Label>Button Type</Label>
                <Select value={form.buttonType} onValueChange={v => setForm({ ...form, buttonType: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="cart">Cart</SelectItem>
                    <SelectItem value="quote">Quote</SelectItem>
                    <SelectItem value="booking">Booking</SelectItem>
                    <SelectItem value="link">Link</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="flex items-center gap-4">
              <div className="flex items-center gap-2">
                <Switch checked={form.visible} onCheckedChange={v => setForm({ ...form, visible: v })} />
                <Label>Visible</Label>
              </div>
              <div className="flex items-center gap-2">
                <Switch checked={form.featured} onCheckedChange={v => setForm({ ...form, featured: v })} />
                <Label>Featured</Label>
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>Cancel</Button>
            <Button onClick={handleSave} className="bg-[#002B5C] hover:bg-[#002B5C]/90">Save</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
