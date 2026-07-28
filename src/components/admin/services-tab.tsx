'use client'

import { useState, useEffect, useCallback } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter,
} from '@/components/ui/dialog'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { Plus, Pencil, Trash2, Eye, EyeOff, Search } from 'lucide-react'

interface Service {
  id: string; name: string; slug: string; description: string; price: number
  icon: string; buttonLabel: string; buttonType: string; buttonUrl: string
  woocommerceProductId: string; categoryId: string | null; visible: boolean
  featured: boolean; displayOrder: number; category?: { id: string; name: string } | null
}

interface Category { id: string; name: string }

export function ServicesTab() {
  const [services, setServices] = useState<Service[]>([])
  const [categories, setCategories] = useState<Category[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [editItem, setEditItem] = useState<Service | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)
  const emptyForm = { name: '', description: '', price: '0', icon: 'FileText', buttonLabel: 'ADD TO CART', buttonType: 'cart', buttonUrl: '', woocommerceProductId: '', categoryId: '', visible: true, featured: false, displayOrder: '0' }
  const [form, setForm] = useState(emptyForm)

  const loadData = useCallback(async () => {
    try {
      const [sRes, cRes] = await Promise.all([
        fetch('/api/services?all=true').then(r => r.json()),
        fetch('/api/categories').then(r => r.json()),
      ])
      setServices(sRes.services || [])
      setCategories(cRes.categories || [])
    } catch (e) { console.error(e) } finally { setLoading(false) }
  }, [])

  useEffect(() => { loadData() }, [loadData])

  const openCreate = () => { setEditItem(null); setForm(emptyForm); setDialogOpen(true) }
  const openEdit = (s: Service) => {
    setEditItem(s)
    setForm({
      name: s.name, description: s.description, price: String(s.price), icon: s.icon,
      buttonLabel: s.buttonLabel, buttonType: s.buttonType, buttonUrl: s.buttonUrl,
      woocommerceProductId: s.woocommerceProductId, categoryId: s.categoryId || '',
      visible: s.visible, featured: s.featured, displayOrder: String(s.displayOrder),
    })
    setDialogOpen(true)
  }

  const handleSave = async () => {
    try {
      if (editItem) {
        await fetch('/api/services', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: editItem.id, ...form, price: parseFloat(form.price) || 0, displayOrder: parseInt(form.displayOrder) || 0 }) })
      } else {
        await fetch('/api/services', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(form) })
      }
      setDialogOpen(false); loadData()
    } catch (e) { console.error(e) }
  }

  const handleDelete = async (id: string) => {
    if (!confirm('Delete this service?')) return
    try { await fetch(`/api/services?id=${id}`, { method: 'DELETE' }); loadData() } catch (e) { console.error(e) }
  }

  const toggleVisible = async (s: Service) => {
    try { await fetch('/api/services', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: s.id, visible: !s.visible }) }); loadData() } catch (e) { console.error(e) }
  }

  const filtered = services.filter(s => s.name.toLowerCase().includes(search.toLowerCase()))

  if (loading) return <div className="space-y-4"><Skeleton className="h-8 w-48" /><Skeleton className="h-64 w-full" /></div>

  return (
    <div className="space-y-4">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <h2 className="text-2xl font-bold text-[#002B5C]">Services</h2>
        <div className="flex gap-2">
          <div className="relative"><Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" /><Input placeholder="Search..." value={search} onChange={e => setSearch(e.target.value)} className="pl-9 w-48" /></div>
          <Button onClick={openCreate} className="bg-[#002B5C] hover:bg-[#002B5C]/90"><Plus className="h-4 w-4 mr-1" />Add</Button>
        </div>
      </div>
      <Card>
        <CardContent className="p-0">
          <div className="max-h-[60vh] overflow-y-auto">
            <Table>
              <TableHeader><TableRow className="bg-gray-50">
                <TableHead className="w-12">#</TableHead><TableHead>Name</TableHead>
                <TableHead className="hidden md:table-cell">Category</TableHead>
                <TableHead>Price</TableHead><TableHead className="hidden sm:table-cell">Order</TableHead>
                <TableHead>Status</TableHead><TableHead className="w-28">Actions</TableHead>
              </TableRow></TableHeader>
              <TableBody>
                {filtered.length === 0 && <TableRow><TableCell colSpan={7} className="text-center py-8 text-gray-500">No services found</TableCell></TableRow>}
                {filtered.map((s, i) => (
                  <TableRow key={s.id} className={!s.visible ? 'opacity-50' : ''}>
                    <TableCell className="text-gray-400 text-xs">{i + 1}</TableCell>
                    <TableCell className="font-medium">{s.name}{s.featured && <Badge className="ml-2 bg-[#D4AF37] text-white text-[10px] px-1.5">Featured</Badge>}</TableCell>
                    <TableCell className="hidden md:table-cell text-gray-500 text-sm">{s.category?.name || '—'}</TableCell>
                    <TableCell className="font-mono text-sm">R{s.price.toFixed(2)}</TableCell>
                    <TableCell className="hidden sm:table-cell text-gray-500 text-sm">{s.displayOrder}</TableCell>
                    <TableCell><Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => toggleVisible(s)}>{s.visible ? <Eye className="h-3.5 w-3.5" /> : <EyeOff className="h-3.5 w-3.5" />}</Button></TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => openEdit(s)}><Pencil className="h-3.5 w-3.5" /></Button>
                        <Button variant="ghost" size="icon" className="h-7 w-7 text-red-500" onClick={() => handleDelete(s.id)}><Trash2 className="h-3.5 w-3.5" /></Button>
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
          <DialogHeader><DialogTitle>{editItem ? 'Edit' : 'Create'} Service</DialogTitle></DialogHeader>
          <div className="grid gap-4 py-2">
            <div className="grid gap-2"><Label>Name</Label><Input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} /></div>
            <div className="grid gap-2"><Label>Description</Label><Textarea value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} rows={3} /></div>
            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-2"><Label>Price</Label><Input type="number" step="0.01" value={form.price} onChange={e => setForm({ ...form, price: e.target.value })} /></div>
              <div className="grid gap-2"><Label>Display Order</Label><Input type="number" value={form.displayOrder} onChange={e => setForm({ ...form, displayOrder: e.target.value })} /></div>
            </div>
            <div className="grid gap-2"><Label>Category</Label>
              <Select value={form.categoryId} onValueChange={v => setForm({ ...form, categoryId: v })}>
                <SelectTrigger><SelectValue placeholder="None" /></SelectTrigger>
                <SelectContent>{categories.map(c => <SelectItem key={c.id} value={c.id}>{c.name}</SelectItem>)}</SelectContent>
              </Select>
            </div>
            <div className="grid gap-2"><Label>Icon Name</Label><Input value={form.icon} onChange={e => setForm({ ...form, icon: e.target.value })} placeholder="FileText" /></div>
            <div className="grid grid-cols-2 gap-3">
              <div className="grid gap-2"><Label>Button Label</Label><Input value={form.buttonLabel} onChange={e => setForm({ ...form, buttonLabel: e.target.value })} /></div>
              <div className="grid gap-2"><Label>Button Type</Label>
                <Select value={form.buttonType} onValueChange={v => setForm({ ...form, buttonType: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent><SelectItem value="cart">Cart</SelectItem><SelectItem value="quote">Quote</SelectItem><SelectItem value="booking">Booking</SelectItem><SelectItem value="link">Link</SelectItem></SelectContent>
                </Select>
              </div>
            </div>
            <div className="flex items-center gap-4">
              <div className="flex items-center gap-2"><Switch checked={form.visible} onCheckedChange={v => setForm({ ...form, visible: v })} /><Label>Visible</Label></div>
              <div className="flex items-center gap-2"><Switch checked={form.featured} onCheckedChange={v => setForm({ ...form, featured: v })} /><Label>Featured</Label></div>
            </div>
          </div>
          <DialogFooter><Button variant="outline" onClick={() => setDialogOpen(false)}>Cancel</Button><Button onClick={handleSave} className="bg-[#002B5C] hover:bg-[#002B5C]/90">Save</Button></DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
