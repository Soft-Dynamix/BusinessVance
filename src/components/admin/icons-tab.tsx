'use client'

import { useState, useEffect, useCallback } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Plus, Pencil, Trash2, Eye, EyeOff } from 'lucide-react'

interface Icon {
  id: string
  name: string
  label: string
  svgPath: string
  category: string
  displayOrder: number
}

const ICON_CATEGORIES = ['general', 'business', 'finance', 'marketing', 'people', 'security', 'status']

export function IconsTab() {
  const [icons, setIcons] = useState<Icon[]>([])
  const [loading, setLoading] = useState(true)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [editing, setEditing] = useState<Icon | null>(null)
  const [form, setForm] = useState({ name: '', label: '', svgPath: '', category: 'general', displayOrder: 0 })

  const fetchIcons = useCallback(async () => {
    try {
      const res = await fetch('/api/icons')
      if (res.ok) {
        const data = await res.json()
        setIcons(data.icons || [])
      }
    } catch (e) { console.error(e) }
    finally { setLoading(false) }
  }, [])

  useEffect(() => { fetchIcons() }, [fetchIcons])

  const openCreate = () => {
    setEditing(null)
    setForm({ name: '', label: '', svgPath: '', category: 'general', displayOrder: icons.length })
    setDialogOpen(true)
  }

  const openEdit = (icon: Icon) => {
    setEditing(icon)
    setForm({ name: icon.name, label: icon.label, svgPath: icon.svgPath, category: icon.category, displayOrder: icon.displayOrder })
    setDialogOpen(true)
  }

  const handleSave = async () => {
    const method = editing ? 'PUT' : 'POST'
    const body = editing ? { ...form, id: editing.id } : form
    try {
      const res = await fetch('/api/icons', { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
      if (res.ok) { setDialogOpen(false); fetchIcons() }
    } catch (e) { console.error(e) }
  }

  const handleDelete = async (id: string) => {
    if (!confirm('Delete this icon?')) return
    try {
      const res = await fetch('/api/icons', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) })
      if (res.ok) fetchIcons()
    } catch (e) { console.error(e) }
  }

  if (loading) return <div className="flex items-center justify-center py-12"><div className="animate-spin h-8 w-8 border-2 border-[#D4AF37] border-t-transparent rounded-full" /></div>

  const grouped = ICON_CATEGORIES.reduce((acc, cat) => {
    acc[cat] = icons.filter(i => i.category === cat).sort((a, b) => a.displayOrder - b.displayOrder)
    return acc
  }, {} as Record<string, Icon[]>)

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold text-[#002B5C]">Icon Manager ({icons.length} icons)</h2>
        <Button size="sm" onClick={openCreate} className="bg-[#D4AF37] hover:bg-[#c4a030] text-white">
          <Plus className="w-4 h-4 mr-1" /> Add Icon
        </Button>
      </div>

      {ICON_CATEGORIES.map(cat => (grouped[cat] || []).length > 0 && (
        <Card key={cat} className="border-gray-200">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-[#002B5C] capitalize">{cat}</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-10">Preview</TableHead>
                  <TableHead>Name</TableHead>
                  <TableHead>Label</TableHead>
                  <TableHead>Order</TableHead>
                  <TableHead className="w-24 text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {grouped[cat].map(icon => (
                  <TableRow key={icon.id}>
                    <TableCell>
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d={icon.svgPath} />
                      </svg>
                    </TableCell>
                    <TableCell className="font-medium">{icon.name}</TableCell>
                    <TableCell>{icon.label}</TableCell>
                    <TableCell>{icon.displayOrder}</TableCell>
                    <TableCell className="text-right">
                      <Button variant="ghost" size="sm" onClick={() => openEdit(icon)}><Pencil className="w-3 h-3" /></Button>
                      <Button variant="ghost" size="sm" onClick={() => handleDelete(icon.id)}><Trash2 className="w-3 h-3 text-red-500" /></Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      ))}

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Icon' : 'Add Icon'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div>
              <Label>Name</Label>
              <Input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} placeholder="e.g. briefcase" />
            </div>
            <div>
              <Label>Label</Label>
              <Input value={form.label} onChange={e => setForm({ ...form, label: e.target.value })} placeholder="e.g. Briefcase" />
            </div>
            <div>
              <Label>SVG Path</Label>
              <Input value={form.svgPath} onChange={e => setForm({ ...form, svgPath: e.target.value })} placeholder="M20 7h-9..." />
              {form.svgPath && (
                <div className="mt-1 p-2 bg-gray-50 rounded flex items-center gap-2">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d={form.svgPath} />
                  </svg>
                  <span className="text-xs text-gray-500">Preview</span>
                </div>
              )}
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label>Category</Label>
                <Select value={form.category} onValueChange={v => setForm({ ...form, category: v })}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {ICON_CATEGORIES.map(c => <SelectItem key={c} value={c}>{c}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label>Display Order</Label>
                <Input type="number" value={form.displayOrder} onChange={e => setForm({ ...form, displayOrder: parseInt(e.target.value) || 0 })} />
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>Cancel</Button>
            <Button onClick={handleSave} className="bg-[#D4AF37] hover:bg-[#c4a030] text-white">Save</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
