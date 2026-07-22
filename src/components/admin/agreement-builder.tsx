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
  FileSignature, Plus, Pencil, Trash2, Eye, Loader2, AlertTriangle,
  FileText, Code, Maximize2, Minimize2,
} from 'lucide-react';

/* ═══════════════════════════════════════════════════════════════
   Types
   ═══════════════════════════════════════════════════════════════ */
interface AgreementBuilderProps {
  toast: (msg: string, type?: 'success' | 'error') => void;
}

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

/* ═══════════════════════════════════════════════════════════════
   Constants
   ═══════════════════════════════════════════════════════════════ */
const STATUS_STYLES: Record<string, { bg: string; text: string; dot: string; label: string }> = {
  draft: { bg: '#f3f4f6', text: '#6b7280', dot: '#9ca3af', label: 'Draft' },
  published: { bg: '#ecfdf5', text: '#059669', dot: '#10b981', label: 'Published' },
  archived: { bg: '#fef2f2', text: '#dc2626', dot: '#ef4444', label: 'Archived' },
};

const SAMPLE_CONTENT = `<h2 style="color: #002B5C; margin-bottom: 16px;">Confidentiality Undertaking</h2>

<p style="margin-bottom: 12px;">By signing this agreement, the client agrees to the following terms and conditions regarding the confidentiality of all business information shared during the course of engagement.</p>

<h3 style="color: #002B5C; margin-bottom: 8px;">1. Confidential Information</h3>
<p style="margin-bottom: 12px;">All information disclosed during the engagement, including but not limited to business plans, financial data, client lists, and proprietary processes, shall be treated as strictly confidential.</p>

<h3 style="color: #002B5C; margin-bottom: 8px;">2. Obligations</h3>
<ol style="margin-bottom: 12px; padding-left: 24px;">
  <li style="margin-bottom: 8px;">Keep all business information confidential and secure</li>
  <li style="margin-bottom: 8px;">Not share documents with any third parties without prior written consent</li>
  <li style="margin-bottom: 8px;">Return or destroy all confidential materials upon termination of engagement</li>
  <li style="margin-bottom: 8px;">Immediately notify of any unauthorized disclosure</li>
</ol>

<h3 style="color: #002B5C; margin-bottom: 8px;">3. Duration</h3>
<p style="margin-bottom: 12px;">This agreement shall remain in effect for the duration of the engagement and for a period of 2 (two) years thereafter.</p>

<h3 style="color: #002B5C; margin-bottom: 8px;">4. Breach</h3>
<p>Any breach of this agreement may result in immediate termination of services and may subject the client to legal action.</p>`;

/* ═══════════════════════════════════════════════════════════════
   AgreementBuilderTab Component
   ═══════════════════════════════════════════════════════════════ */
export default function AgreementBuilderTab({ toast }: AgreementBuilderProps) {
  const [templates, setTemplates] = useState<AgreementTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [deleteConfirm, setDeleteConfirm] = useState<{ open: boolean; id: string; name: string }>({ open: false, id: '', name: '' });

  // Modal state
  const [modal, setModal] = useState(false);
  const [editing, setEditing] = useState<AgreementTemplate | null>(null);
  const [form, setForm] = useState<Record<string, string>>({});
  const [editorExpanded, setEditorExpanded] = useState(false);

  // View modal state
  const [viewModal, setViewModal] = useState(false);
  const [viewTemplate, setViewTemplate] = useState<AgreementTemplate | null>(null);
  const [viewMode, setViewMode] = useState<'preview' | 'source'>('preview');

  // ── Data Fetching ──
  const fetchTemplates = useCallback(async () => {
    try {
      const res = await fetch('/api/agreement-templates');
      const data = await res.json();
      let list = data.templates || [];
      if (statusFilter && statusFilter !== 'all') {
        list = list.filter((t: AgreementTemplate) => t.status === statusFilter);
      }
      setTemplates(list);
    } catch (err) {
      console.error('Failed to fetch:', err);
      toast('Error loading agreement templates', 'error');
    } finally { setLoading(false); }
  }, [statusFilter, toast]);

  useEffect(() => { fetchTemplates(); }, [fetchTemplates]);

  // ── CRUD ──
  const openNew = () => {
    setEditing(null);
    setForm({ name: '', content: '', version: '1.0', status: 'draft' });
    setEditorExpanded(false);
    setModal(true);
  };

  const openEdit = (t: AgreementTemplate) => {
    setEditing(t);
    setForm({ name: t.name, content: t.content, version: t.version, status: t.status });
    setEditorExpanded(false);
    setModal(true);
  };

  const openView = (t: AgreementTemplate) => {
    setViewTemplate(t);
    setViewMode('preview');
    setViewModal(true);
  };

  const save = async () => {
    if (!form.name?.trim()) return;
    setSaving(true);
    try {
      const isEdit = !!editing;
      const url = isEdit ? `/api/agreement-templates/${editing.id}` : '/api/agreement-templates';
      const method = isEdit ? 'PUT' : 'POST';
      const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(form) });
      if (!res.ok) throw new Error('Save failed');
      toast(isEdit ? 'Agreement template updated!' : 'Agreement template created!', 'success');
      setModal(false);
      fetchTemplates();
    } catch { toast('Error saving agreement template', 'error'); }
    finally { setSaving(false); }
  };

  const duplicateTemplate = async (t: AgreementTemplate) => {
    try {
      const res = await fetch('/api/agreement-templates', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: `${t.name} (Copy)`, content: t.content, version: t.version, status: 'draft' }),
      });
      if (!res.ok) throw new Error('Duplicate failed');
      toast('Agreement duplicated successfully!', 'success');
      fetchTemplates();
    } catch { toast('Error duplicating agreement', 'error'); }
  };

  const deleteTemplate = async () => {
    setSaving(true);
    try {
      const res = await fetch(`/api/agreement-templates/${deleteConfirm.id}`, { method: 'DELETE' });
      if (!res.ok) throw new Error('Delete failed');
      toast('Agreement deleted!', 'success');
      setDeleteConfirm({ open: false, id: '', name: '' });
      fetchTemplates();
    } catch { toast('Error deleting agreement', 'error'); }
    finally { setSaving(false); }
  };

  // ── Helpers ──
  const formatDate = (dateStr?: string) => {
    if (!dateStr) return '—';
    try { return new Date(dateStr).toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric' }); } catch { return '—'; }
  };

  const getWordCount = (html: string) => {
    const text = html.replace(/<[^>]*>/g, '').trim();
    return text ? text.split(/\s+/).length : 0;
  };

  // ── Loading ──
  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Loader2 className="animate-spin" size={32} style={{ color: '#D4AF37' }} />
      </div>
    );
  }

  return (
    <section aria-label="Agreement Builder">
      {/* ═══ Header ═══ */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
        <div className="flex items-center gap-3">
          <div className="flex items-center justify-center w-10 h-10 rounded-lg" style={{ backgroundColor: '#002B5C20' }}>
            <FileSignature size={22} style={{ color: '#002B5C' }} />
          </div>
          <div>
            <h1 className="text-2xl font-bold" style={{ color: '#002B5C' }}>Agreement Builder</h1>
            <p className="text-sm text-[#646970]">Create and manage digital agreement templates for client signatures</p>
          </div>
        </div>
        <Button onClick={openNew} className="font-semibold text-white shadow-sm hover:opacity-90 transition-opacity" style={{ backgroundColor: '#D4AF37' }}>
          <Plus size={16} className="mr-1.5" /> Add New Agreement
        </Button>
      </div>

      {/* ═══ Summary Stats Bar ═══ */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        {[
          { label: 'Total Agreements', value: templates.length, color: '#002B5C' },
          { label: 'Published', value: templates.filter(t => t.status === 'published').length, color: '#059669' },
          { label: 'Drafts', value: templates.filter(t => t.status === 'draft').length, color: '#6b7280' },
          { label: 'Times Signed', value: templates.reduce((sum, t) => sum + (t._count?.projects || 0), 0), color: '#D4AF37' },
        ].map(stat => (
          <div key={stat.label} className="bg-white rounded-lg border border-[#c3c4c7]/30 px-4 py-3">
            <p className="text-[11px] uppercase tracking-wide font-semibold text-[#a7aaad] mb-0.5">{stat.label}</p>
            <p className="text-xl font-bold" style={{ color: stat.color }}>{stat.value}</p>
          </div>
        ))}
      </div>

      {/* ═══ Filter Bar ═══ */}
      <div className="flex items-center gap-2 mb-4">
        {[
          { value: 'all', label: 'All' },
          { value: 'draft', label: 'Draft' },
          { value: 'published', label: 'Published' },
          { value: 'archived', label: 'Archived' },
        ].map(filter => (
          <button
            key={filter.value}
            onClick={() => setStatusFilter(filter.value)}
            className={`px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors ${
              statusFilter === filter.value
                ? 'text-white border-transparent'
                : 'text-[#50575e] border-[#d1d5db] bg-white hover:bg-[#f8f9fa]'
            }`}
            style={statusFilter === filter.value ? { backgroundColor: '#002B5C' } : undefined}
          >
            {filter.label}
          </button>
        ))}
      </div>

      {/* ═══ Table ═══ */}
      <div className="bg-white rounded-lg border border-[#c3c4c7]/30 overflow-hidden">
        <div className="overflow-x-auto max-h-[600px] overflow-y-auto wp-scrollbar">
          <Table>
            <TableHeader>
              <TableRow style={{ backgroundColor: '#f8f9fa' }}>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Name</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Version</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Status</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e] text-center hidden md:table-cell">Words</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e] text-center">Linked Services</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e] text-center hidden sm:table-cell">Projects Signed</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e] hidden lg:table-cell">Updated</TableHead>
                <TableHead className="w-40 text-right text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {templates.map((t) => {
                const st = STATUS_STYLES[t.status] || STATUS_STYLES.draft;
                return (
                  <TableRow key={t.id} className="hover:bg-[#f8f9fa]/60 transition-colors">
                    <TableCell className="py-3 px-3">
                      <div className="flex items-center gap-2">
                        <FileSignature size={16} className="text-[#a7aaad] flex-shrink-0" />
                        <div>
                          <div className="font-medium text-sm text-[#1d2327]">{t.name}</div>
                          <div className="text-[11px] text-[#a7aaad] font-mono">{t.slug}</div>
                        </div>
                      </div>
                    </TableCell>
                    <TableCell className="py-3 px-3 text-sm font-mono text-[#50575e]">
                      <span className="px-2 py-0.5 rounded text-[11px] font-medium" style={{ backgroundColor: '#f0f0f1', color: '#50575e' }}>v{t.version}</span>
                    </TableCell>
                    <TableCell className="py-3 px-3">
                      <Badge className="text-[11px] font-medium border-0 gap-1.5" style={{ backgroundColor: st.bg, color: st.text }}>
                        <span className="w-1.5 h-1.5 rounded-full inline-block" style={{ backgroundColor: st.dot }} />
                        {st.label}
                      </Badge>
                    </TableCell>
                    <TableCell className="py-3 px-3 text-center text-sm text-[#50575e] hidden md:table-cell">
                      {t.content ? getWordCount(t.content) : 0}
                    </TableCell>
                    <TableCell className="py-3 px-3 text-center">
                      {(t._count?.services || 0) > 0 ? (
                        <Badge variant="outline" className="text-[11px] text-[#D4AF37] border-[#D4AF37]">{t._count?.services} service{(t._count?.services || 0) !== 1 ? 's' : ''}</Badge>
                      ) : <span className="text-xs text-[#a7aaad]">—</span>}
                    </TableCell>
                    <TableCell className="py-3 px-3 text-center text-sm text-[#50575e] hidden sm:table-cell">{t._count?.projects || 0}</TableCell>
                    <TableCell className="py-3 px-3 text-xs text-[#a7aaad] hidden lg:table-cell">{formatDate(t.updatedAt)}</TableCell>
                    <TableCell className="py-3 px-3 text-right">
                      <div className="flex items-center justify-end gap-0.5">
                        <button onClick={() => openView(t)} className="p-1.5 rounded text-[#2A9D8F] hover:bg-[#f0f0f1] transition-colors" title="Preview agreement">
                          <Eye size={15} />
                        </button>
                        <button onClick={() => openEdit(t)} className="p-1.5 rounded text-[#2271b1] hover:bg-[#f0f0f1] transition-colors" title="Edit agreement">
                          <Pencil size={15} />
                        </button>
                        <button onClick={() => duplicateTemplate(t)} className="p-1.5 rounded hover:bg-[#f0f0f1] transition-colors" title="Duplicate agreement" style={{ color: '#D4AF37' }}>
                          <Plus size={15} className="opacity-70" />
                        </button>
                        <button onClick={() => setDeleteConfirm({ open: true, id: t.id, name: t.name })} className="p-1.5 rounded text-[#d63638] hover:bg-red-50 transition-colors" title="Delete agreement">
                          <Trash2 size={15} />
                        </button>
                      </div>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
          {templates.length === 0 && (
            <div className="text-center py-16 text-[#a7aaad]">
              <FileSignature size={40} className="mx-auto mb-3 opacity-30" />
              <p className="font-medium text-[#50575e]">No agreement templates found</p>
              <p className="text-sm mt-1">
                {statusFilter !== 'all'
                  ? `No ${statusFilter} agreements. Try a different filter or `
                  : 'Click '}
                <button onClick={openNew} className="text-[#2271b1] underline hover:text-[#135e96]">create your first agreement</button>
              </p>
            </div>
          )}
        </div>
      </div>

      {/* ═══ EDIT / CREATE MODAL ═══ */}
      <Dialog open={modal} onOpenChange={(open) => { if (!open) setModal(false); }}>
        <DialogContent className={`max-h-[90vh] overflow-y-auto wp-scrollbar transition-all ${editorExpanded ? 'sm:max-w-[95vw]' : 'sm:max-w-[800px]'}`}>
          <DialogHeader>
            <DialogTitle className="text-lg" style={{ color: '#002B5C' }}>
              {editing ? 'Edit Agreement Template' : 'New Agreement Template'}
            </DialogTitle>
            <DialogDescription>
              {editing ? 'Update agreement details and content' : 'Create a new digital agreement template for client signatures'}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-2">
            {/* Template Info */}
            <div className="p-4 rounded-lg border border-[#e5e7eb]" style={{ backgroundColor: '#fafafa' }}>
              <h3 className="text-[13px] font-bold text-[#1d2327] uppercase tracking-wide mb-3">Agreement Details</h3>
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
            </div>

            {/* HTML Content Editor */}
            <div className="p-4 rounded-lg border border-[#e5e7eb]">
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                  <h3 className="text-[13px] font-bold text-[#1d2327] uppercase tracking-wide">Agreement Content (HTML)</h3>
                  {form.content && (
                    <Badge variant="outline" className="text-[10px] px-2 py-0 text-[#50575e]">
                      {getWordCount(form.content)} words
                    </Badge>
                  )}
                </div>
                <div className="flex items-center gap-1">
                  <button
                    onClick={() => setForm({ ...form, content: SAMPLE_CONTENT })}
                    className="px-2.5 py-1 text-[11px] font-medium rounded border border-[#d1d5db] text-[#50575e] hover:bg-[#f8f9fa] transition-colors"
                    title="Insert sample content"
                  >
                    <FileText size={12} className="inline mr-1" /> Sample
                  </button>
                  <button
                    onClick={() => setEditorExpanded(!editorExpanded)}
                    className="p-1.5 rounded border border-[#d1d5db] text-[#50575e] hover:bg-[#f8f9fa] transition-colors"
                    title={editorExpanded ? 'Minimize editor' : 'Maximize editor'}
                  >
                    {editorExpanded ? <Minimize2 size={14} /> : <Maximize2 size={14} />}
                  </button>
                </div>
              </div>
              <Textarea
                value={form.content || ''}
                onChange={(e) => setForm({ ...form, content: e.target.value })}
                className="text-sm font-mono leading-relaxed"
                rows={editorExpanded ? 30 : 16}
                placeholder={SAMPLE_CONTENT}
              />
              <div className="flex items-center justify-between mt-2">
                <p className="text-xs text-[#a7aaad]">
                  Enter the HTML content of the agreement. This will be displayed to clients for electronic signature.
                </p>
                <div className="flex items-center gap-1.5 text-xs text-[#a7aaad]">
                  <Code size={12} />
                  <span>HTML Mode</span>
                </div>
              </div>
            </div>
          </div>
          <DialogFooter className="gap-2 border-t border-[#e5e7eb] pt-4">
            <Button variant="outline" onClick={() => setModal(false)}>Cancel</Button>
            <Button onClick={save} disabled={saving || !form.name?.trim()} className="text-white font-semibold" style={{ backgroundColor: '#D4AF37' }}>
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
            <div className="flex items-center justify-between">
              <div>
                <DialogTitle className="text-lg" style={{ color: '#002B5C' }}>{viewTemplate?.name}</DialogTitle>
                <DialogDescription>
                  Version {viewTemplate?.version} · {viewTemplate ? STATUS_STYLES[viewTemplate.status]?.label : ''}
                  {viewTemplate?._count?.services ? ` · ${viewTemplate._count.services} linked service${viewTemplate._count.services !== 1 ? 's' : ''}` : ''}
                  {viewTemplate?._count?.projects ? ` · Signed ${viewTemplate._count.projects} time${viewTemplate._count.projects !== 1 ? 's' : ''}` : ''}
                </DialogDescription>
              </div>
              <div className="flex items-center gap-1 border border-[#d1d5db] rounded-md overflow-hidden">
                <button
                  onClick={() => setViewMode('preview')}
                  className={`px-3 py-1.5 text-xs font-medium transition-colors ${viewMode === 'preview' ? 'text-white' : 'text-[#50575e] hover:bg-[#f8f9fa]'}`}
                  style={viewMode === 'preview' ? { backgroundColor: '#002B5C' } : undefined}
                >
                  <Eye size={12} className="inline mr-1" /> Preview
                </button>
                <button
                  onClick={() => setViewMode('source')}
                  className={`px-3 py-1.5 text-xs font-medium transition-colors ${viewMode === 'source' ? 'text-white' : 'text-[#50575e] hover:bg-[#f8f9fa]'}`}
                  style={viewMode === 'source' ? { backgroundColor: '#002B5C' } : undefined}
                >
                  <Code size={12} className="inline mr-1" /> Source
                </button>
              </div>
            </div>
          </DialogHeader>
          {viewTemplate && (
            viewMode === 'preview' ? (
              <div
                className="prose prose-sm max-w-none border border-[#e5e7eb] rounded-lg p-6 bg-white min-h-[300px]"
                style={{ color: '#1d2327' }}
                dangerouslySetInnerHTML={{ __html: viewTemplate.content || '<p style="color: #a7aaad; font-style: italic;">No content yet. Edit this agreement to add HTML content.</p>' }}
              />
            ) : (
              <div className="border border-[#e5e7eb] rounded-lg overflow-hidden">
                <div className="px-3 py-1.5 border-b border-[#e5e7eb] flex items-center gap-2" style={{ backgroundColor: '#f8f9fa' }}>
                  <Code size={12} className="text-[#a7aaad]" />
                  <span className="text-xs text-[#a7aaad] font-mono">HTML Source</span>
                </div>
                <pre className="p-4 text-xs font-mono text-[#50575e] overflow-x-auto max-h-[500px] overflow-y-auto wp-scrollbar whitespace-pre-wrap break-words">
                  {viewTemplate.content || '<!-- No content -->'}
                </pre>
              </div>
            )
          )}
        </DialogContent>
      </Dialog>

      {/* ═══ DELETE CONFIRMATION ═══ */}
      <Dialog open={deleteConfirm.open} onOpenChange={(open) => setDeleteConfirm({ ...deleteConfirm, open })}>
        <DialogContent className="sm:max-w-[420px]">
          <DialogHeader>
            <DialogTitle className="text-lg text-[#d63638] flex items-center gap-2">
              <AlertTriangle size={18} /> Delete Agreement?
            </DialogTitle>
            <DialogDescription>
              Are you sure you want to permanently delete <strong>&quot;{deleteConfirm.name}&quot;</strong>? This action cannot be undone. Any services linked to this agreement will lose their agreement template.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setDeleteConfirm({ ...deleteConfirm, open: false })}>Cancel</Button>
            <Button onClick={deleteTemplate} disabled={saving} className="bg-[#d63638] hover:bg-[#b32d2e] text-white font-semibold">
              {saving && <Loader2 size={14} className="mr-1.5 animate-spin" />}
              Delete Permanently
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </section>
  );
}
