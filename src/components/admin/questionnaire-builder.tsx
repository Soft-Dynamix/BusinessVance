'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
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
  ClipboardList, Plus, Pencil, Trash2, Copy, Eye, ChevronDown, ChevronRight,
  Loader2, GripVertical, X, AlertTriangle, FileQuestion, ListOrdered,
  ArrowUp, ArrowDown, ToggleLeft,
} from 'lucide-react';

/* ═══════════════════════════════════════════════════════════════
   Types
   ═══════════════════════════════════════════════════════════════ */
interface QuestionnaireBuilderProps {
  toast: (msg: string, type?: 'success' | 'error') => void;
}

interface Question {
  id?: string;
  type: string;
  label: string;
  placeholder: string;
  required: boolean;
  options: string;
  conditionalOn: string;
  conditionalValue: string;
  helpText: string;
  displayOrder: number;
}

interface Section {
  id?: string;
  title: string;
  description: string;
  displayOrder: number;
  isShared: boolean;
  questions: Question[];
}

interface Template {
  id: string;
  name: string;
  slug: string;
  description: string;
  version: string;
  status: string;
  sections: Section[];
  _count?: { services: number; projects: number };
  createdAt?: string;
  updatedAt?: string;
}

/* ═══════════════════════════════════════════════════════════════
   Constants
   ═══════════════════════════════════════════════════════════════ */
const QUESTION_TYPES = [
  { value: 'text', label: 'Text Input', icon: 'T' },
  { value: 'textarea', label: 'Text Area', icon: '¶' },
  { value: 'number', label: 'Number', icon: '#' },
  { value: 'email', label: 'Email', icon: '@' },
  { value: 'phone', label: 'Phone', icon: '☎' },
  { value: 'date', label: 'Date', icon: '📅' },
  { value: 'select', label: 'Dropdown Select', icon: '▾' },
  { value: 'multiselect', label: 'Multi-Select', icon: '☑' },
  { value: 'radio', label: 'Radio Buttons', icon: '◉' },
  { value: 'checkbox', label: 'Checkbox', icon: '☐' },
  { value: 'file', label: 'File Upload', icon: '📎' },
  { value: 'heading', label: 'Heading (Label Only)', icon: 'H' },
  { value: 'paragraph', label: 'Paragraph (Info Only)', icon: 'P' },
];

const STATUS_STYLES: Record<string, { bg: string; text: string; dot: string; label: string }> = {
  draft: { bg: '#f3f4f6', text: '#6b7280', dot: '#9ca3af', label: 'Draft' },
  published: { bg: '#ecfdf5', text: '#059669', dot: '#10b981', label: 'Published' },
  archived: { bg: '#fef2f2', text: '#dc2626', dot: '#ef4444', label: 'Archived' },
};

const defaultQuestion = (): Question => ({
  type: 'text', label: '', placeholder: '', required: false, options: '[]',
  conditionalOn: '', conditionalValue: '', helpText: '', displayOrder: 0,
});

const defaultSection = (): Section => ({
  title: '', description: '', displayOrder: 0, isShared: false, questions: [],
});

/* ═══════════════════════════════════════════════════════════════
   QuestionnaireBuilderTab Component
   ═══════════════════════════════════════════════════════════════ */
export default function QuestionnaireBuilderTab({ toast }: QuestionnaireBuilderProps) {
  const [templates, setTemplates] = useState<Template[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [deleteConfirm, setDeleteConfirm] = useState<{ open: boolean; id: string; name: string }>({ open: false, id: '', name: '' });
  const [statusFilter, setStatusFilter] = useState<string>('all');

  // Template modal state
  const [templateModal, setTemplateModal] = useState(false);
  const [editingTemplate, setEditingTemplate] = useState<Template | null>(null);
  const [form, setForm] = useState<Record<string, string>>({});
  const [sections, setSections] = useState<Section[]>([]);

  // Section modal state
  const [sectionModal, setSectionModal] = useState(false);
  const [editingSectionIdx, setEditingSectionIdx] = useState<number | null>(null);
  const [sectionForm, setSectionForm] = useState<{ title: string; description: string; isShared: boolean }>({ title: '', description: '', isShared: false });

  // Question modal state
  const [questionModal, setQuestionModal] = useState(false);
  const [editingQuestionIdx, setEditingQuestionIdx] = useState<number | null>(null);
  const [questionForm, setQuestionForm] = useState<Question>(defaultQuestion());
  const [activeSectionIdx, setActiveSectionIdx] = useState<number>(0);

  // Preview modal
  const [previewModal, setPreviewModal] = useState(false);
  const [previewTemplate, setPreviewTemplate] = useState<Template | null>(null);

  // ── Data Fetching ──
  const fetchTemplates = useCallback(async () => {
    try {
      const params = new URLSearchParams();
      params.set('include', 'services');
      if (statusFilter && statusFilter !== 'all') params.set('status', statusFilter);
      const res = await fetch(`/api/questionnaire-templates?${params.toString()}`);
      const data = await res.json();
      setTemplates(data.templates || []);
    } catch (err) {
      console.error('Failed to fetch:', err);
      toast('Error loading questionnaire templates', 'error');
    } finally { setLoading(false); }
  }, [statusFilter, toast]);

  useEffect(() => { fetchTemplates(); }, [fetchTemplates]);

  // ── Template CRUD ──
  const openNewTemplate = () => {
    setEditingTemplate(null);
    setForm({ name: '', description: '', version: '1.0', status: 'draft' });
    setSections([]);
    setTemplateModal(true);
  };

  const openEditTemplate = async (t: Template) => {
    try {
      const res = await fetch(`/api/questionnaire-templates/${t.id}`);
      const data = await res.json();
      const full = data.template;
      setEditingTemplate(full);
      setForm({ name: full.name, description: full.description || '', version: full.version, status: full.status });
      setSections(full.sections || []);
      setTemplateModal(true);
    } catch { toast('Error loading template details', 'error'); }
  };

  const saveTemplate = async () => {
    if (!form.name?.trim()) return;
    setSaving(true);
    try {
      const isEdit = !!editingTemplate;
      const url = isEdit ? `/api/questionnaire-templates/${editingTemplate.id}` : '/api/questionnaire-templates';
      const method = isEdit ? 'PUT' : 'POST';
      const body: Record<string, unknown> = { ...form, sections };
      const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      if (!res.ok) throw new Error('Save failed');
      toast(isEdit ? 'Questionnaire template updated!' : 'Questionnaire template created!', 'success');
      setTemplateModal(false);
      fetchTemplates();
    } catch { toast('Error saving template', 'error'); }
    finally { setSaving(false); }
  };

  const duplicateTemplate = async (t: Template) => {
    try {
      const res = await fetch('/api/questionnaire-templates', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: `${t.name} (Copy)`, description: t.description, version: t.version, status: 'draft', sections: t.sections }),
      });
      if (!res.ok) throw new Error('Duplicate failed');
      toast('Template duplicated successfully!', 'success');
      fetchTemplates();
    } catch { toast('Error duplicating template', 'error'); }
  };

  const deleteTemplate = async () => {
    setSaving(true);
    try {
      const res = await fetch(`/api/questionnaire-templates/${deleteConfirm.id}`, { method: 'DELETE' });
      if (!res.ok) throw new Error('Delete failed');
      toast('Template deleted!', 'success');
      setDeleteConfirm({ open: false, id: '', name: '' });
      fetchTemplates();
    } catch { toast('Error deleting template', 'error'); }
    finally { setSaving(false); }
  };

  // ── Section CRUD ──
  const openNewSection = () => {
    setEditingSectionIdx(null);
    setSectionForm({ title: '', description: '', isShared: false });
    setSectionModal(true);
  };

  const openEditSection = (idx: number) => {
    const s = sections[idx];
    setEditingSectionIdx(idx);
    setSectionForm({ title: s.title, description: s.description, isShared: s.isShared });
    setSectionModal(true);
  };

  const saveSection = () => {
    if (!sectionForm.title.trim()) return;
    const newSection: Section = {
      title: sectionForm.title,
      description: sectionForm.description,
      displayOrder: 0,
      isShared: sectionForm.isShared,
      questions: [],
    };
    if (editingSectionIdx !== null) {
      const updated = [...sections];
      newSection.questions = sections[editingSectionIdx].questions;
      updated[editingSectionIdx] = newSection;
      setSections(updated);
    } else {
      newSection.displayOrder = sections.length;
      setSections([...sections, newSection]);
    }
    setSectionModal(false);
  };

  const removeSection = (idx: number) => {
    setSections(sections.filter((_, i) => i !== idx));
  };

  const moveSection = (idx: number, dir: -1 | 1) => {
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= sections.length) return;
    const updated = [...sections];
    [updated[idx], updated[newIdx]] = [updated[newIdx], updated[idx]];
    updated.forEach((s, i) => { s.displayOrder = i; });
    setSections(updated);
  };

  // ── Question CRUD ──
  const openNewQuestion = (sectionIdx: number) => {
    setActiveSectionIdx(sectionIdx);
    setEditingQuestionIdx(null);
    setQuestionForm({ ...defaultQuestion() });
    setQuestionModal(true);
  };

  const openEditQuestion = (sectionIdx: number, qIdx: number) => {
    setActiveSectionIdx(sectionIdx);
    const q = sections[sectionIdx].questions[qIdx];
    setEditingQuestionIdx(qIdx);
    setQuestionForm({ ...q });
    setQuestionModal(true);
  };

  const saveQuestion = () => {
    if (!questionForm.label.trim()) return;
    const q: Question = {
      id: questionForm.id,
      type: questionForm.type,
      label: questionForm.label,
      placeholder: questionForm.placeholder,
      required: questionForm.required,
      options: questionForm.options,
      conditionalOn: questionForm.conditionalOn,
      conditionalValue: questionForm.conditionalValue,
      helpText: questionForm.helpText,
      displayOrder: 0,
    };
    const updatedSections = [...sections];
    const sec = { ...updatedSections[activeSectionIdx] };
    if (editingQuestionIdx !== null) {
      q.displayOrder = editingQuestionIdx;
      const qs = [...sec.questions];
      qs[editingQuestionIdx] = q;
      sec.questions = qs;
    } else {
      q.displayOrder = sec.questions.length;
      sec.questions = [...sec.questions, q];
    }
    updatedSections[activeSectionIdx] = sec;
    setSections(updatedSections);
    setQuestionModal(false);
  };

  const removeQuestion = (sectionIdx: number, qIdx: number) => {
    const updatedSections = [...sections];
    const sec = { ...updatedSections[sectionIdx] };
    sec.questions = sec.questions.filter((_, i) => i !== qIdx);
    updatedSections[sectionIdx] = sec;
    setSections(updatedSections);
  };

  const moveQuestion = (sectionIdx: number, qIdx: number, dir: -1 | 1) => {
    const newIdx = qIdx + dir;
    const updatedSections = [...sections];
    const sec = { ...updatedSections[sectionIdx] };
    if (newIdx < 0 || newIdx >= sec.questions.length) return;
    const qs = [...sec.questions];
    [qs[qIdx], qs[newIdx]] = [qs[newIdx], qs[qIdx]];
    qs.forEach((q, i) => { q.displayOrder = i; });
    sec.questions = qs;
    updatedSections[sectionIdx] = sec;
    setSections(updatedSections);
  };

  // ── Preview ──
  const openPreview = async (t: Template) => {
    try {
      const res = await fetch(`/api/questionnaire-templates/${t.id}`);
      const data = await res.json();
      setPreviewTemplate(data.template);
      setPreviewModal(true);
    } catch { toast('Error loading preview', 'error'); }
  };

  // ── Stats ──
  const totalQuestions = sections.reduce((sum, s) => sum + s.questions.length, 0);

  // ── Loading ──
  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Loader2 className="animate-spin" size={32} style={{ color: '#D4AF37' }} />
      </div>
    );
  }

  return (
    <section aria-label="Questionnaire Builder">
      {/* ═══ Header ═══ */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
        <div className="flex items-center gap-3">
          <div className="flex items-center justify-center w-10 h-10 rounded-lg" style={{ backgroundColor: '#D4AF3720' }}>
            <ClipboardList size={22} style={{ color: '#D4AF37' }} />
          </div>
          <div>
            <h1 className="text-2xl font-bold" style={{ color: '#002B5C' }}>Questionnaire Builder</h1>
            <p className="text-sm text-[#646970]">Create and manage intake questionnaires for client onboarding</p>
          </div>
        </div>
        <Button onClick={openNewTemplate} className="font-semibold text-white shadow-sm hover:opacity-90 transition-opacity" style={{ backgroundColor: '#D4AF37' }}>
          <Plus size={16} className="mr-1.5" /> Add New Template
        </Button>
      </div>

      {/* ═══ Summary Stats Bar ═══ */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        {[
          { label: 'Total Templates', value: templates.length, color: '#002B5C' },
          { label: 'Published', value: templates.filter(t => t.status === 'published').length, color: '#059669' },
          { label: 'Drafts', value: templates.filter(t => t.status === 'draft').length, color: '#6b7280' },
          { label: 'Total Questions', value: templates.reduce((sum, t) => sum + (t.sections?.reduce((s, sec) => s + (sec.questions?.length || 0), 0) || 0), 0), color: '#D4AF37' },
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

      {/* ═══ Templates Table ═══ */}
      <div className="bg-white rounded-lg border border-[#c3c4c7]/30 overflow-hidden">
        <div className="overflow-x-auto max-h-[600px] overflow-y-auto wp-scrollbar">
          <Table>
            <TableHeader>
              <TableRow style={{ backgroundColor: '#f8f9fa' }}>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Name</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e] hidden lg:table-cell">Description</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Version</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Status</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e] text-center">Sections</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e] text-center">Questions</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e] text-center">Linked</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e] text-center hidden sm:table-cell">Projects</TableHead>
                <TableHead className="w-40 text-right text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {templates.map((t) => {
                const st = STATUS_STYLES[t.status] || STATUS_STYLES.draft;
                const qCount = t.sections?.reduce((s, sec) => s + (sec.questions?.length || 0), 0) || 0;
                return (
                  <TableRow key={t.id} className="hover:bg-[#f8f9fa]/60 transition-colors">
                    <TableCell className="py-3 px-3">
                      <div className="flex items-center gap-2">
                        <ClipboardList size={16} className="text-[#a7aaad] flex-shrink-0" />
                        <div>
                          <div className="font-medium text-sm text-[#1d2327]">{t.name}</div>
                          <div className="text-[11px] text-[#a7aaad] font-mono">{t.slug}</div>
                        </div>
                      </div>
                    </TableCell>
                    <TableCell className="py-3 px-3 text-sm text-[#50575e] max-w-[200px] truncate hidden lg:table-cell">
                      {t.description || <span className="text-[#a7aaad] italic">No description</span>}
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
                    <TableCell className="py-3 px-3 text-center text-sm font-medium text-[#1d2327]">{t.sections?.length || 0}</TableCell>
                    <TableCell className="py-3 px-3 text-center text-sm text-[#50575e]">{qCount}</TableCell>
                    <TableCell className="py-3 px-3 text-center">
                      {(t._count?.services || 0) > 0 ? (
                        <Badge variant="outline" className="text-[11px] text-[#D4AF37] border-[#D4AF37]">{t._count?.services} service{(t._count?.services || 0) !== 1 ? 's' : ''}</Badge>
                      ) : <span className="text-xs text-[#a7aaad]">—</span>}
                    </TableCell>
                    <TableCell className="py-3 px-3 text-center text-sm text-[#50575e] hidden sm:table-cell">{t._count?.projects || 0}</TableCell>
                    <TableCell className="py-3 px-3 text-right">
                      <div className="flex items-center justify-end gap-0.5">
                        <button onClick={() => openPreview(t)} className="p-1.5 rounded text-[#2A9D8F] hover:bg-[#f0f0f1] transition-colors" title="Preview questionnaire">
                          <Eye size={15} />
                        </button>
                        <button onClick={() => openEditTemplate(t)} className="p-1.5 rounded text-[#2271b1] hover:bg-[#f0f0f1] transition-colors" title="Edit template">
                          <Pencil size={15} />
                        </button>
                        <button onClick={() => duplicateTemplate(t)} className="p-1.5 rounded hover:bg-[#f0f0f1] transition-colors" title="Duplicate template" style={{ color: '#D4AF37' }}>
                          <Copy size={15} />
                        </button>
                        <button onClick={() => setDeleteConfirm({ open: true, id: t.id, name: t.name })} className="p-1.5 rounded text-[#d63638] hover:bg-red-50 transition-colors" title="Delete template">
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
              <ClipboardList size={40} className="mx-auto mb-3 opacity-30" />
              <p className="font-medium text-[#50575e]">No questionnaire templates found</p>
              <p className="text-sm mt-1">
                {statusFilter !== 'all'
                  ? `No ${statusFilter} templates. Try a different filter or `
                  : 'Click '}
                <button onClick={openNewTemplate} className="text-[#2271b1] underline hover:text-[#135e96]">create your first template</button>
              </p>
            </div>
          )}
        </div>
      </div>

      {/* ═══ TEMPLATE MODAL (Create / Edit) ═══ */}
      <Dialog open={templateModal} onOpenChange={(open) => { if (!open) setTemplateModal(false); }}>
        <DialogContent className="sm:max-w-[860px] max-h-[90vh] overflow-y-auto wp-scrollbar">
          <DialogHeader>
            <DialogTitle className="text-lg" style={{ color: '#002B5C' }}>
              {editingTemplate ? 'Edit Questionnaire Template' : 'New Questionnaire Template'}
            </DialogTitle>
            <DialogDescription>
              {editingTemplate ? 'Update template info and manage sections & questions' : 'Create a new questionnaire with sections and questions'}
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-5 py-2">
            {/* Template Info */}
            <div className="p-4 rounded-lg border border-[#e5e7eb]" style={{ backgroundColor: '#fafafa' }}>
              <h3 className="text-[13px] font-bold text-[#1d2327] uppercase tracking-wide mb-3">Template Details</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="md:col-span-2">
                  <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Template Name *</Label>
                  <Input value={form.name || ''} onChange={(e) => setForm({ ...form, name: e.target.value })} className="text-sm" placeholder="Company Profile Questionnaire" />
                </div>
                <div>
                  <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Description</Label>
                  <Textarea value={form.description || ''} onChange={(e) => setForm({ ...form, description: e.target.value })} className="text-sm" rows={2} placeholder="Brief description of this questionnaire" />
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

            {/* Sections */}
            <div>
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                  <h3 className="text-[13px] font-bold text-[#1d2327] uppercase tracking-wide">Sections</h3>
                  <Badge variant="outline" className="text-[11px] px-2 py-0 text-[#50575e]">{sections.length} section{sections.length !== 1 ? 's' : ''}</Badge>
                  <Badge variant="outline" className="text-[11px] px-2 py-0 text-[#50575e]">{totalQuestions} question{totalQuestions !== 1 ? 's' : ''}</Badge>
                </div>
                <Button variant="outline" size="sm" onClick={openNewSection} className="text-xs border-[#D4AF37] text-[#D4AF37] hover:bg-[#D4AF37]/10">
                  <Plus size={14} className="mr-1" /> Add Section
                </Button>
              </div>

              {sections.length === 0 && (
                <div className="text-center py-10 text-[#a7aaad] border-2 border-dashed border-[#d1d5db] rounded-lg bg-[#fafafa]">
                  <ListOrdered size={28} className="mx-auto mb-2 opacity-40" />
                  <p className="text-sm font-medium text-[#50575e]">No sections yet</p>
                  <p className="text-xs mt-1 mb-3">Click &quot;Add Section&quot; to build your questionnaire</p>
                </div>
              )}

              <div className="space-y-2">
                {sections.map((sec, sIdx) => (
                  <CollapsibleSection
                    key={sIdx}
                    section={sec}
                    index={sIdx}
                    total={sections.length}
                    onEdit={() => openEditSection(sIdx)}
                    onDelete={() => removeSection(sIdx)}
                    onMoveUp={() => moveSection(sIdx, -1)}
                    onMoveDown={() => moveSection(sIdx, 1)}
                    onAddQuestion={() => openNewQuestion(sIdx)}
                    onEditQuestion={(qIdx) => openEditQuestion(sIdx, qIdx)}
                    onDeleteQuestion={(qIdx) => removeQuestion(sIdx, qIdx)}
                    onMoveQuestion={(qIdx, dir) => moveQuestion(sIdx, qIdx, dir)}
                  />
                ))}
              </div>
            </div>
          </div>

          <DialogFooter className="gap-2 border-t border-[#e5e7eb] pt-4">
            <Button variant="outline" onClick={() => setTemplateModal(false)}>Cancel</Button>
            <Button onClick={saveTemplate} disabled={saving || !form.name?.trim()} className="text-white font-semibold" style={{ backgroundColor: '#D4AF37' }}>
              {saving && <Loader2 size={14} className="mr-1.5 animate-spin" />}
              {editingTemplate ? 'Update Template' : 'Create Template'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* ═══ SECTION MODAL ═══ */}
      <Dialog open={sectionModal} onOpenChange={(open) => { if (!open) setSectionModal(false); }}>
        <DialogContent className="sm:max-w-[500px]">
          <DialogHeader>
            <DialogTitle className="text-lg" style={{ color: '#002B5C' }}>{editingSectionIdx !== null ? 'Edit Section' : 'Add Section'}</DialogTitle>
            <DialogDescription>Define a section grouping for your questions</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Section Title *</Label>
              <Input value={sectionForm.title} onChange={(e) => setSectionForm({ ...sectionForm, title: e.target.value })} className="text-sm" placeholder="Company Information" />
            </div>
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Description</Label>
              <Textarea value={sectionForm.description} onChange={(e) => setSectionForm({ ...sectionForm, description: e.target.value })} className="text-sm" rows={2} placeholder="Optional section description shown to clients" />
            </div>
            <div className="flex items-center gap-3 p-3 rounded-lg border border-[#e5e7eb] bg-[#fafafa]">
              <Switch checked={sectionForm.isShared} onCheckedChange={(v) => setSectionForm({ ...sectionForm, isShared: v })} />
              <div>
                <Label className="text-sm font-medium text-[#1d2327]">Shared Section</Label>
                <p className="text-xs text-[#a7aaad]">Reusable across multiple services (e.g., Company Profile)</p>
              </div>
            </div>
          </div>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setSectionModal(false)}>Cancel</Button>
            <Button onClick={saveSection} disabled={!sectionForm.title.trim()} className="text-white font-semibold" style={{ backgroundColor: '#D4AF37' }}>
              {editingSectionIdx !== null ? 'Update Section' : 'Add Section'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* ═══ QUESTION MODAL ═══ */}
      <Dialog open={questionModal} onOpenChange={(open) => { if (!open) setQuestionModal(false); }}>
        <DialogContent className="sm:max-w-[620px] max-h-[90vh] overflow-y-auto wp-scrollbar">
          <DialogHeader>
            <DialogTitle className="text-lg" style={{ color: '#002B5C' }}>{editingQuestionIdx !== null ? 'Edit Question' : 'Add Question'}</DialogTitle>
            <DialogDescription>
              In section: <span className="font-medium text-[#1d2327]">{sections[activeSectionIdx]?.title || 'Unknown'}</span>
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-2">
            {/* Type & Required */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Question Type *</Label>
                <Select value={questionForm.type} onValueChange={(v) => setQuestionForm({ ...questionForm, type: v })}>
                  <SelectTrigger className="w-full text-sm"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {QUESTION_TYPES.map((qt) => (
                      <SelectItem key={qt.value} value={qt.value}>
                        <span className="flex items-center gap-2">
                          <span className="w-5 h-5 rounded text-[10px] font-bold flex items-center justify-center text-white" style={{ backgroundColor: '#002B5C' }}>{qt.icon}</span>
                          {qt.label}
                        </span>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="flex items-end pb-1">
                <div className="flex items-center gap-3 p-3 rounded-lg border border-[#e5e7eb] bg-[#fafafa] flex-1">
                  <Switch checked={questionForm.required} onCheckedChange={(v) => setQuestionForm({ ...questionForm, required: v })} />
                  <div>
                    <Label className="text-sm font-medium text-[#1d2327]">Required</Label>
                    <p className="text-xs text-[#a7aaad]">Client must answer</p>
                  </div>
                </div>
              </div>
            </div>

            {/* Label */}
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Label *</Label>
              <Input value={questionForm.label} onChange={(e) => setQuestionForm({ ...questionForm, label: e.target.value })} className="text-sm" placeholder="Company Name" />
            </div>

            {/* Placeholder */}
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Placeholder</Label>
              <Input value={questionForm.placeholder} onChange={(e) => setQuestionForm({ ...questionForm, placeholder: e.target.value })} className="text-sm" placeholder="Enter your company name" />
            </div>

            {/* Help Text */}
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Help Text</Label>
              <Input value={questionForm.helpText} onChange={(e) => setQuestionForm({ ...questionForm, helpText: e.target.value })} className="text-sm" placeholder="Additional guidance for this question" />
            </div>

            {/* Options for select/radio/checkbox/multiselect */}
            {['select', 'radio', 'checkbox', 'multiselect'].includes(questionForm.type) && (
              <div className="p-3 rounded-lg border border-[#e5e7eb] bg-[#fafafa]">
                <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Options (JSON array) *</Label>
                <Textarea value={questionForm.options} onChange={(e) => setQuestionForm({ ...questionForm, options: e.target.value })} className="text-sm font-mono" rows={3} placeholder='["Option 1", "Option 2", "Option 3"]' />
                <p className="text-xs text-[#a7aaad] mt-1">Enter a JSON array of option strings. Each string becomes a choice.</p>
              </div>
            )}

            {/* Conditional Logic */}
            <div className="p-3 rounded-lg border border-[#e5e7eb] bg-[#fafafa]">
              <div className="flex items-center gap-2 mb-2">
                <ToggleLeft size={14} className="text-[#7c3aed]" />
                <Label className="text-[13px] font-semibold text-[#1d2327]">Conditional Logic</Label>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <Label className="block text-[12px] font-medium text-[#50575e] mb-1">Show if question answered</Label>
                  <Select value={questionForm.conditionalOn || '_none'} onValueChange={(v) => setQuestionForm({ ...questionForm, conditionalOn: v === '_none' ? '' : v })}>
                    <SelectTrigger className="w-full text-sm"><SelectValue placeholder="None" /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="_none">None (always visible)</SelectItem>
                      {sections[activeSectionIdx]?.questions.filter((q) => q.id !== questionForm.id).map((q) => (
                        <SelectItem key={q.id || q.label} value={q.id || q.label}>{q.label || 'Untitled'}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                {questionForm.conditionalOn && (
                  <div>
                    <Label className="block text-[12px] font-medium text-[#50575e] mb-1">With value</Label>
                    <Input value={questionForm.conditionalValue} onChange={(e) => setQuestionForm({ ...questionForm, conditionalValue: e.target.value })} className="text-sm" placeholder="Expected answer value" />
                  </div>
                )}
              </div>
            </div>
          </div>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setQuestionModal(false)}>Cancel</Button>
            <Button onClick={saveQuestion} disabled={!questionForm.label.trim()} className="text-white font-semibold" style={{ backgroundColor: '#D4AF37' }}>
              {editingQuestionIdx !== null ? 'Update Question' : 'Add Question'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* ═══ PREVIEW MODAL ═══ */}
      <Dialog open={previewModal} onOpenChange={(open) => { if (!open) setPreviewModal(false); }}>
        <DialogContent className="sm:max-w-[720px] max-h-[90vh] overflow-y-auto wp-scrollbar">
          <DialogHeader>
            <DialogTitle className="text-lg" style={{ color: '#002B5C' }}>Preview: {previewTemplate?.name}</DialogTitle>
            <DialogDescription>Read-only preview of how the questionnaire will appear to clients</DialogDescription>
          </DialogHeader>
          {previewTemplate && (
            <div className="space-y-6 py-2">
              {previewTemplate.sections?.length === 0 && (
                <div className="text-center py-12 text-[#a7aaad]">
                  <AlertTriangle size={32} className="mx-auto mb-2 opacity-40" />
                  <p className="text-sm font-medium">This template has no sections yet</p>
                </div>
              )}
              {previewTemplate.sections?.map((sec, secIdx) => (
                <div key={sec.id || secIdx} className="border border-[#e5e7eb] rounded-lg overflow-hidden">
                  <div className="px-4 py-2.5 border-b border-[#e5e7eb]" style={{ backgroundColor: '#f8f9fa' }}>
                    <h3 className="text-[14px] font-bold text-[#1d2327]">
                      {secIdx + 1}. {sec.title || 'Untitled Section'}
                      {sec.isShared && <Badge variant="outline" className="text-[10px] px-1.5 py-0 text-[#2A9D8F] border-[#2A9D8F] ml-2">Shared</Badge>}
                    </h3>
                    {sec.description && <p className="text-xs text-[#646970] mt-0.5">{sec.description}</p>}
                  </div>
                  <div className="p-4 space-y-4">
                    {sec.questions?.length === 0 && (
                      <p className="text-sm text-[#a7aaad] italic">No questions in this section</p>
                    )}
                    {sec.questions?.map((q, qIdx) => (
                      <div key={q.id || qIdx}>
                        {q.type === 'heading' ? (
                          <h4 className="text-[14px] font-bold text-[#1d2327] mt-2 pt-2 border-t border-[#f0f0f1]">{q.label}</h4>
                        ) : q.type === 'paragraph' ? (
                          <p className="text-sm text-[#50575e] bg-[#f8f9fa] p-3 rounded-lg mt-2">{q.label}</p>
                        ) : (
                          <div className="space-y-1.5">
                            <Label className="text-[13px] font-medium text-[#1d2327]">
                              {q.label} {q.required && <span className="text-[#d63638]">*</span>}
                            </Label>
                            {q.helpText && <p className="text-xs text-[#646970]">{q.helpText}</p>}
                            <div className="pointer-events-none opacity-50">
                              {q.type === 'textarea' ? (
                                <Textarea className="text-sm" placeholder={q.placeholder} rows={3} readOnly />
                              ) : q.type === 'select' ? (
                                <Select>
                                  <SelectTrigger className="w-full text-sm"><SelectValue placeholder={q.placeholder || 'Select...'} /></SelectTrigger>
                                  <SelectContent>
                                    {(() => { try { return JSON.parse(q.options || '[]').map((o: string, i: number) => <SelectItem key={i} value={o}>{o}</SelectItem>); } catch { return null; } })()}
                                  </SelectContent>
                                </Select>
                              ) : q.type === 'radio' ? (
                                <div className="space-y-1.5">
                                  {(() => { try { return JSON.parse(q.options || '[]').map((o: string, i: number) => (
                                    <div key={i} className="flex items-center gap-2 text-sm text-[#50575e]">
                                      <div className="w-4 h-4 rounded-full border-2 border-[#d1d5db] flex-shrink-0" />
                                      {o}
                                    </div>
                                  )); } catch { return null; } })()}
                                </div>
                              ) : q.type === 'checkbox' || q.type === 'multiselect' ? (
                                <div className="space-y-1.5">
                                  {(() => { try { return JSON.parse(q.options || '[]').map((o: string, i: number) => (
                                    <div key={i} className="flex items-center gap-2 text-sm text-[#50575e]">
                                      <div className="w-4 h-4 rounded border-2 border-[#d1d5db] flex-shrink-0" />
                                      {o}
                                    </div>
                                  )); } catch { return null; } })()}
                                </div>
                              ) : q.type === 'file' ? (
                                <div className="border-2 border-dashed border-[#d1d5db] rounded-lg p-4 text-center text-sm text-[#a7aaad]">
                                  <FileQuestion size={20} className="mx-auto mb-1" /> File upload area
                                </div>
                              ) : (
                                <Input className="text-sm" placeholder={q.placeholder} type={q.type === 'number' ? 'number' : q.type === 'email' ? 'email' : q.type === 'date' ? 'date' : 'text'} readOnly />
                              )}
                            </div>
                          </div>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          )}
        </DialogContent>
      </Dialog>

      {/* ═══ DELETE CONFIRMATION ═══ */}
      <Dialog open={deleteConfirm.open} onOpenChange={(open) => setDeleteConfirm({ ...deleteConfirm, open })}>
        <DialogContent className="sm:max-w-[420px]">
          <DialogHeader>
            <DialogTitle className="text-lg text-[#d63638] flex items-center gap-2">
              <AlertTriangle size={18} /> Delete Template?
            </DialogTitle>
            <DialogDescription>
              Are you sure you want to permanently delete <strong>&quot;{deleteConfirm.name}&quot;</strong>? This will also delete all sections and questions. This action cannot be undone.
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

/* ═══════════════════════════════════════════════════════════════
   CollapsibleSection — Inline sub-component for section management
   ═══════════════════════════════════════════════════════════════ */
function CollapsibleSection({ section, index, total, onEdit, onDelete, onMoveUp, onMoveDown, onAddQuestion, onEditQuestion, onDeleteQuestion, onMoveQuestion }: {
  section: Section;
  index: number;
  total: number;
  onEdit: () => void;
  onDelete: () => void;
  onMoveUp: () => void;
  onMoveDown: () => void;
  onAddQuestion: () => void;
  onEditQuestion: (qIdx: number) => void;
  onDeleteQuestion: (qIdx: number) => void;
  onMoveQuestion: (qIdx: number, dir: -1 | 1) => void;
}) {
  const [open, setOpen] = useState(false);

  return (
    <div className="border border-[#e5e7eb] rounded-lg overflow-hidden">
      {/* Section Header */}
      <div className="flex items-center gap-2 px-3 py-2.5 cursor-pointer hover:bg-[#f8f9fa] transition-colors" onClick={() => setOpen(!open)}>
        {open ? <ChevronDown size={16} className="text-[#646970] flex-shrink-0" /> : <ChevronRight size={16} className="text-[#646970] flex-shrink-0" />}
        <GripVertical size={14} className="text-[#c3c4c7] flex-shrink-0" />
        <span className="text-sm font-semibold text-[#1d2327]">{section.title || 'Untitled Section'}</span>
        {section.isShared && (
          <Badge variant="outline" className="text-[10px] px-1.5 py-0 text-[#2A9D8F] border-[#2A9D8F]">Shared</Badge>
        )}
        <div className="ml-auto flex items-center gap-1.5">
          <Badge variant="secondary" className="text-[10px] px-1.5 py-0">
            {section.questions.length} Q{section.questions.length !== 1 ? 's' : ''}
          </Badge>
        </div>
        <div className="flex items-center gap-0.5 ml-1" onClick={(e) => e.stopPropagation()}>
          <button onClick={onMoveUp} disabled={index === 0} className="p-1 rounded hover:bg-[#e5e7eb] disabled:opacity-20 transition-colors" title="Move section up">
            <ArrowUp size={13} />
          </button>
          <button onClick={onMoveDown} disabled={index === total - 1} className="p-1 rounded hover:bg-[#e5e7eb] disabled:opacity-20 transition-colors" title="Move section down">
            <ArrowDown size={13} />
          </button>
          <div className="w-px h-4 bg-[#d1d5db] mx-0.5" />
          <button onClick={onEdit} className="p-1 rounded text-[#2271b1] hover:bg-[#f0f0f1] transition-colors" title="Edit section">
            <Pencil size={13} />
          </button>
          <button onClick={onDelete} className="p-1 rounded text-[#d63638] hover:bg-red-50 transition-colors" title="Delete section">
            <Trash2 size={13} />
          </button>
        </div>
      </div>

      {/* Questions (expanded) */}
      {open && (
        <div className="border-t border-[#e5e7eb] bg-[#fafafa]">
          {section.questions.length === 0 ? (
            <div className="text-center py-8 text-[#a7aaad]">
              <p className="text-sm">No questions in this section</p>
              <Button variant="link" size="sm" className="text-[#2271b1] text-xs mt-1" onClick={onAddQuestion}>
                <Plus size={13} className="mr-0.5" /> Add First Question
              </Button>
            </div>
          ) : (
            <>
              <div className="divide-y divide-[#f0f0f1]">
                {section.questions.map((q, qIdx) => (
                  <div key={q.id || qIdx} className="flex items-center gap-3 px-4 py-2 hover:bg-white/60 transition-colors">
                    <div className="flex-shrink-0 w-5 text-[11px] text-[#a7aaad] text-center font-mono font-bold">{qIdx + 1}</div>
                    <div className="flex-1 min-w-0">
                      <div className="text-sm text-[#1d2327] flex items-center gap-2">
                        <span className="font-medium truncate">{q.label || 'Untitled'}</span>
                        {q.required && <span className="text-[9px] text-white bg-[#d63638] px-1 py-0.5 rounded font-bold">REQ</span>}
                      </div>
                      <div className="flex items-center gap-2 mt-0.5">
                        <Badge variant="outline" className="text-[10px] px-1.5 py-0 text-[#646970]">{q.type}</Badge>
                        {q.conditionalOn && <span className="text-[10px] text-[#7c3aed] font-medium">⚡ conditional</span>}
                        {q.helpText && <span className="text-[10px] text-[#a7aaad]">ℹ️ help</span>}
                      </div>
                    </div>
                    <div className="flex items-center gap-0.5 flex-shrink-0">
                      <button onClick={() => onMoveQuestion(qIdx, -1)} disabled={qIdx === 0} className="p-1 rounded hover:bg-[#e5e7eb] disabled:opacity-20 transition-colors" title="Move up">
                        <ArrowUp size={12} />
                      </button>
                      <button onClick={() => onMoveQuestion(qIdx, 1)} disabled={qIdx === section.questions.length - 1} className="p-1 rounded hover:bg-[#e5e7eb] disabled:opacity-20 transition-colors" title="Move down">
                        <ArrowDown size={12} />
                      </button>
                      <button onClick={() => onEditQuestion(qIdx)} className="p-1 rounded text-[#2271b1] hover:bg-[#f0f0f1] transition-colors" title="Edit question">
                        <Pencil size={12} />
                      </button>
                      <button onClick={() => onDeleteQuestion(qIdx)} className="p-1 rounded text-[#d63638] hover:bg-red-50 transition-colors" title="Delete question">
                        <Trash2 size={12} />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
              <div className="px-4 py-2 border-t border-[#e5e7eb]">
                <Button variant="ghost" size="sm" onClick={onAddQuestion} className="text-[#2271b1] text-xs h-7">
                  <Plus size={13} className="mr-1" /> Add Question
                </Button>
              </div>
            </>
          )}
        </div>
      )}
    </div>
  );
}
