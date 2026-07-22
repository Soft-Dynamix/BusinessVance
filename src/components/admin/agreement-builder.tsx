'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription,
} from '@/components/ui/dialog';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import {
  FileSignature, Plus, Pencil, Trash2, Eye, Loader2,
} from 'lucide-react';

/* ═══════════════════════════════════════════════════════════════
   Types
   ═══════════════════════════════════════════════════════════════ */
interface AgreementTemplate {
  id: string;
  name: string;
  slug: string;
  content: string;
  version: string;
  status: string;
  createdAt?: string;
  updatedAt?: string;
  _count?: { services: number; projects: number };
}

const STATUS_STYLES: Record<string, { bg: string; text: string; label: string }> = {
  draft: { bg: '#f3f4f6', text: '#6b7280', label: 'Draft' },
  published: { bg: '#ecfdf5', text: '#059669', label: 'Published' },
  archived: { bg: '#fef2f2', text: '#dc2626', label: 'Archived' },
};

/* ═══════════════════════════════════════════════════════════════
   Toast helper
   ═══════════════════════════════════════════════════════════════ */
function InlineToast({ message }: { message: string | null }) {
  if (!message) return null;
  return (
    <div className="fixed bottom-4 right-4 z-[9999] px-4 py-3 rounded-lg text-white text-sm font-medium shadow-lg" style={{ backgroundColor: '#002B5C', animation: 'bvSlideIn 0.3s ease-out' }}>
      {message}
    </div>
  );
}

/* ═══════════════════════════════════════════════════════════════
   AgreementBuilderTab Component
   ═══════════════════════════════════════════════════════════════ */
export default function AgreementBuilderTab() {
  const [templates, setTemplates] = useState<AgreementTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState<string | null>(null);
  const [deleteConfirm, setDeleteConfirm] = useState<{ open: boolean; id: string; name: string }>({ open: false, id: '', name: '' });

  // Modal state
  const [modal, setModal] = useState(false);
  const [editing, setEditing] = useState<AgreementTemplate | null>(null);
  const [form, setForm] = useState<Record<string, string>>({});

  // View modal state
  const [viewModal, setViewModal] = useState(false);
  const [viewTemplate, setViewTemplate] = useState<AgreementTemplate | null>(null);

  const showToast = useCallback((msg: string) => { setToast(msg); setTimeout(() => setToast(null), 3000); }, []);

  // Fetch templates
  const fetchTemplates = useCallback(async () => {
    try {
      const res = await fetch('/api/agreement-templates');
      const data = await res.json();
      setTemplates(data.templates || []);
    } catch (err) { console.error('Failed to fetch:', err); }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { fetchTemplates(); }, [fetchTemplates]);

  // ── CRUD ──
  const openNew = () => {
    setEditing(null);
    setForm({ name: '', content: '', version: '1.0', status: 'draft' });
    setModal(true);
  };

  const openEdit = (t: AgreementTemplate) => {
    setEditing(t);
    setForm({ name: t.name, content: t.content, version: t.version, status: t.status });
    setModal(true);
  };

  const openView = (t: AgreementTemplate) => {
    setViewTemplate(t);
    setViewModal(true);
  };

  const save = async () => {
    setSaving(true);
    try {
      const isEdit = !!editing;
      const url = isEdit ? `/api/agreement-templates/${editing.id}` : '/api/agreement-templates';
      const method = isEdit ? 'PUT' : 'POST';
      const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(form) });
      if (!res.ok) throw new Error();
      showToast(isEdit ? 'Agreement updated!' : 'Agreement created!');
      setModal(false);
      fetchTemplates();
    } catch { showToast('Error saving agreement'); }
    finally { setSaving(false); }
  };

  const deleteTemplate = async () => {
    setSaving(true);
    try {
      await fetch(`/api/agreement-templates/${deleteConfirm.id}`, { method: 'DELETE' });
      showToast('Agreement deleted!');
      setDeleteConfirm({ open: false, id: '', name: '' });
      fetchTemplates();
    } catch { showToast('Error deleting agreement'); }
    finally { setSaving(false); }
  };

  // ── Render ──
  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Loader2 className="animate-spin" size={32} style={{ color: '#D4AF37' }} />
      </div>
    );
  }

  return (
    <section aria-label="Agreement Builder">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
        <div className="flex items-center gap-3">
          <FileSignature size={24} style={{ color: '#D4AF37' }} />
          <div>
            <h1 className="text-2xl font-bold" style={{ color: '#002B5C' }}>Agreement Builder</h1>
            <p className="text-sm text-[#646970]">Create and manage digital agreement templates</p>
          </div>
        </div>
        <Button onClick={openNew} className="font-semibold text-white shadow-sm hover:opacity-90 transition-opacity" style={{ backgroundColor: '#D4AF37' }}>
          <Plus size={16} className="mr-1.5" /> Add New Agreement
        </Button>
      </div>

      {/* Table */}
      <div className="bg-white rounded-lg border border-[#c3c4c7]/30 overflow-hidden">
        <div className="overflow-x-auto max-h-[600px] overflow-y-auto wp-scrollbar">
          <Table>
            <TableHeader>
              <TableRow style={{ backgroundColor: '#f8f9fa' }}>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Name</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Version</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Status</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e] text-center">Linked Services</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e] text-center">Projects Signed</TableHead>
                <TableHead className="w-28 text-right text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {templates.map((t) => {
                const st = STATUS_STYLES[t.status] || STATUS_STYLES.draft;
                return (
                  <TableRow key={t.id}>
                    <TableCell className="py-3 px-3">
                      <div className="font-medium text-sm text-[#1d2327]">{t.name}</div>
                      <div className="text-xs text-[#a7aaad] font-mono">{t.slug}</div>
                    </TableCell>
                    <TableCell className="py-3 px-3 text-sm font-mono text-[#50575e]">{t.version}</TableCell>
                    <TableCell className="py-3 px-3">
                      <Badge className="text-xs font-medium border-0" style={{ backgroundColor: st.bg, color: st.text }}>{st.label}</Badge>
                    </TableCell>
                    <TableCell className="py-3 px-3 text-center text-sm text-[#50575e]">{t._count?.services || 0}</TableCell>
                    <TableCell className="py-3 px-3 text-center text-sm text-[#50575e]">{t._count?.projects || 0}</TableCell>
                    <TableCell className="py-3 px-3 text-right">
                      <div className="flex items-center justify-end gap-1">
                        <button onClick={() => openView(t)} className="p-1.5 rounded text-[#2A9D8F] hover:bg-[#f0f0f1] transition-colors" title="Preview"><Eye size={15} /></button>
                        <button onClick={() => openEdit(t)} className="p-1.5 rounded text-[#2271b1] hover:bg-[#f0f0f1] transition-colors" title="Edit"><Pencil size={15} /></button>
                        <button onClick={() => setDeleteConfirm({ open: true, id: t.id, name: t.name })} className="p-1.5 rounded text-[#d63638] hover:bg-red-50 transition-colors" title="Delete"><Trash2 size={15} /></button>
                      </div>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
          {templates.length === 0 && (
            <div className="text-center py-16 text-[#a7aaad]">
              <FileSignature size={40} className="mx-auto mb-3 opacity-40" />
              <p className="font-medium">No agreement templates yet</p>
              <p className="text-sm mt-1">Click &quot;Add New Agreement&quot; to get started</p>
            </div>
          )}
        </div>
      </div>

      {/* ═══ EDIT / CREATE MODAL ═══ */}
      <Dialog open={modal} onOpenChange={(open) => { if (!open) setModal(false); }}>
        <DialogContent className="sm:max-w-[800px] max-h-[90vh] overflow-y-auto wp-scrollbar">
          <DialogHeader>
            <DialogTitle className="text-lg" style={{ color: '#002B5C' }}>{editing ? 'Edit Agreement Template' : 'New Agreement Template'}</DialogTitle>
            <DialogDescription>{editing ? 'Update agreement details and content' : 'Create a new digital agreement template'}</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="md:col-span-2">
                <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Template Name *</Label>
                <Input value={form.name || ''} onChange={(e) => setForm({ ...form, name: e.target.value })} className="text-sm" placeholder="Confidentiality Undertaking" />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Version</Label>
                  <Input value={form.version || '1.0'} onChange={(e) => setForm({ ...form, version: e.target.value })} className="text-sm" />
                </div>
                <div>
                  <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Status</Label>
                  <Select value={form.status || 'draft'} onValueChange={(v) => setForm({ ...form, status: v })}>
                    <SelectTrigger className="w-full text-sm"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="draft">Draft</SelectItem>
                      <SelectItem value="published">Published</SelectItem>
                      <SelectItem value="archived">Archived</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </div>
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Agreement Content (HTML) *</Label>
              <Textarea
                value={form.content || ''}
                onChange={(e) => setForm({ ...form, content: e.target.value })}
                className="text-sm font-mono"
                rows={16}
                placeholder={'<h2>Confidentiality Undertaking</h2>\n<p>By signing this agreement, the client agrees to...</p>\n<ol>\n  <li>Keep all business information confidential</li>\n  <li>Not share documents with third parties</li>\n</ol>'}
              />
              <p className="text-xs text-[#a7aaad] mt-1">Enter the HTML content of the agreement. This will be displayed to clients for signing.</p>
            </div>
          </div>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setModal(false)}>Cancel</Button>
            <Button onClick={save} disabled={saving || !form.name?.trim()} className="text-white" style={{ backgroundColor: '#D4AF37' }}>
              {saving && <Loader2 size={14} className="mr-1.5 animate-spin" />}
              {editing ? 'Update Agreement' : 'Create Agreement'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* ═══ VIEW / PREVIEW MODAL ═══ */}
      <Dialog open={viewModal} onOpenChange={(open) => { if (!open) setViewModal(false); }}>
        <DialogContent className="sm:max-w-[800px] max-h-[90vh] overflow-y-auto wp-scrollbar">
          <DialogHeader>
            <DialogTitle className="text-lg" style={{ color: '#002B5C' }}>{viewTemplate?.name}</DialogTitle>
            <DialogDescription>Version {viewTemplate?.version} · {viewTemplate ? STATUS_STYLES[viewTemplate.status]?.label : ''}</DialogDescription>
          </DialogHeader>
          {viewTemplate && (
            <div
              className="prose prose-sm max-w-none border border-[#e5e7eb] rounded-lg p-6 bg-white min-h-[300px]"
              dangerouslySetInnerHTML={{ __html: viewTemplate.content || '<p class="text-gray-400 italic">No content</p>' }}
            />
          )}
        </DialogContent>
      </Dialog>

      {/* ═══ DELETE CONFIRMATION ═══ */}
      <Dialog open={deleteConfirm.open} onOpenChange={(open) => setDeleteConfirm({ ...deleteConfirm, open })}>
        <DialogContent className="sm:max-w-[420px]">
          <DialogHeader>
            <DialogTitle className="text-lg text-[#d63638]">Delete Agreement?</DialogTitle>
            <DialogDescription>
              Are you sure you want to permanently delete <strong>&quot;{deleteConfirm.name}&quot;</strong>? This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setDeleteConfirm({ ...deleteConfirm, open: false })}>Cancel</Button>
            <Button onClick={deleteTemplate} disabled={saving} className="bg-[#d63638] hover:bg-[#b32d2e] text-white">
              {saving && <Loader2 size={14} className="mr-1.5 animate-spin" />}
              Delete
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <InlineToast message={toast} />
    </section>
  );
}
