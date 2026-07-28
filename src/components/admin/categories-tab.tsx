'use client'

import { useState, useEffect, useCallback } from 'react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Plus, Pencil, Trash2 } from 'lucide-react'

interface Category {
  id: string
  name: string
  slug: string
  color: string
}

export function CategoriesTab() {
  const [items, setItems] = useState<Category[]>([])
  const [loading, setLoading] = useState(true)
  const [editItem, setEditItem] = useState<Category | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)
  const [form, setForm] = useState({ name: '', color: '#002B5C' })

  const load = useCallback(async () => {
    try {
      const res = await fetch('/api/categories').then(r => r.json())
      setItems(res.categories || [])
    } catch (e) {
      console.error(e)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { load() }, [load])

  const openCreate = () => {
    setEditItem(null)
    setForm({ name: '', color: '#002B5C' })
    setDialogOpen(true)
  }

  const openEdit = (c: Category) => {
    setEditItem(c)
    setForm({ name: c.name, color: c.color })
    setDialogOpen(true)
  }

  const handleSave = async () => {
    try {
      if (editItem) {
        await fetch('/api/categories', {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: editItem.id, ...form })
        })
      } else {
        await fetch('/api/categories', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(form)
        })
      }
      setDialogOpen(false)
      load()
    } catch (e) {
      console.error(e)
    }
  }

  const handleDelete = async (id: string) => {
    if (!confirm('Delete this category?')) return
    try {
      await fetch(`/api/categories/${id}`, { method: 'DELETE' })
      load()
    } catch (e) {
      console.error(e)
    }
  }

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
        <h2 className="text-2xl font-bold text-[#002B5C]">Categories</h2>
        <Button onClick={openCreate} className="bg-[#002B5C] hover:bg-[#002B5C]/90">
          <Plus className="h-4 w-4 mr-1" />Add
        </Button>
      </div>

      <Card>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow className="bg-gray-50">
                <TableHead className="w-12">#</TableHead>
                <TableHead>Name</TableHead>
                <TableHead>Slug</TableHead>
                <TableHead>Color</TableHead>
                <TableHead className="w-28">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.length === 0 && (
                <TableRow>
                  <TableCell colSpan={5} className="text-center py-8 text-gray-500">No categories</TableCell>
                </TableRow>
              )}
              {items.map((c, i) => (
                <TableRow key={c.id}>
                  <TableCell className="text-gray-400 text-xs">{i + 1}</TableCell>
                  <TableCell className="font-medium">{c.name}</TableCell>
                  <TableCell className="text-gray-500 text-sm font-mono">{c.slug}</TableCell>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <div className="w-5 h-5 rounded" style={{ backgroundColor: c.color }} />
                      <span className="text-xs text-gray-500 font-mono">{c.color}</span>
                    </div>
                  </TableCell>
                  <TableCell>
                    <div className="flex gap-1">
                      <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => openEdit(c)}>
                        <Pencil className="h-3.5 w-3.5" />
                      </Button>
                      <Button variant="ghost" size="icon" className="h-7 w-7 text-red-500" onClick={() => handleDelete(c.id)}>
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>{editItem ? 'Edit' : 'Create'} Category</DialogTitle>
          </DialogHeader>
          <div className="grid gap-4 py-2">
            <div className="grid gap-2">
              <Label>Name</Label>
              <Input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} />
            </div>
            <div className="grid gap-2">
              <Label>Color</Label>
              <div className="flex gap-2">
                <Input type="color" value={form.color} onChange={e => setForm({ ...form, color: e.target.value })} className="w-12 h-9 p-1" />
                <Input value={form.color} onChange={e => setForm({ ...form, color: e.target.value })} />
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
