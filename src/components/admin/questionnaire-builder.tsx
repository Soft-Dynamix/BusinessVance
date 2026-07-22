'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Card, CardContent } from '@/components/ui/card';
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
  ClipboardList, Plus, Pencil, Trash2, Copy, Eye, ChevronDown, ChevronRight, Loader2, GripVertical, X, CheckCircle2,
} from 'lucide-react';

/* ═══════════════════════════════════════════════════════════════
   Types
   ═══════════════════════════════════════════════════════════════ */
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

const QUESTION_TYPES = [
  { value: 'text', label: 'Text Input' },
  { value: 'textarea', label: 'Text Area' },
  { value: 'number', label: 'Number' },
  { value: 'email', label: 'Email' },
  { value: 'phone', label: 'Phone' },
  { value: 'date', label: 'Date' },
  { value: 'select', label: 'Dropdown Select' },
  { value: 'radio', label: 'Radio Buttons' },
  { value: 'checkbox', label: 'Checkbox' },
  { value: 'multiselect', label: 'Multi-Select' },
  { value: 'file', label: 'File Upload' },
  { value: 'heading', label: 'Heading (Label Only)' },
  { value: 'paragraph', label: 'Paragraph (Info Only)' },
];

const STATUS_STYLES: Record<string, { bg: string; text: string; label: string }> = {
  draft: { bg: '#f3f4f6', text: '#6b7280', label: 'Draft' },
  published: { bg: '#ecfdf5', text: '#059669', label: 'Published' },
  archived: { bg: '#fef2f2', text: '#dc2626', label: 'Archived' },
};

const defaultQuestion = (): Question => ({
  type: 'text', label: '', placeholder: '', required: false, options: '[]',
  conditionalOn: '', conditionalValue: '', helpText: '', displayOrder: 0,
});

const defaultSection = (): Section => ({
  title: '', description: '', displayOrder: 0, isShared: false, questions: [],
});

/* ═══════════════════════════════════════════════════════════════
   Toast helper (inline)
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
   QuestionnaireBuilderTab Component
   ═══════════════════════════════════════════════════════════════ */
export default function QuestionnaireBuilderTab() {
  const [templates, setTemplates] = useState<Template[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState<string | null>(null);
  const [deleteConfirm, setDeleteConfirm] = useState<{ open: boolean; id: string; name: string }>({ open: false, id: '', name: '' });

  // Template modal state
  const [templateModal, setTemplateModal] = useState(false);
  const [editingTemplate, setEditingTemplate] = useState<Template | null>(null);
  const [form, setForm] = useState<Record<string, unknown>>({});
  const [sections, setSections] = useState<Section[]>([]);

  // Section modal state
  const [sectionModal, setSectionModal] = useState(false);
  const [editingSectionIdx, setEditingSectionIdx] = useState<number | null>(null);
  const [sectionForm, setSectionForm] = useState<Record<string, unknown>>({});

  // Question modal state
  const [questionModal, setQuestionModal] = useState(false);
  const [editingQuestionIdx, setEditingQuestionIdx] = useState<number | null>(null);
  const [questionForm, setQuestionForm] = useState<Record<string, unknown>>({});
  const [activeSectionIdx, setActiveSectionIdx] = useState<number>(0);

  // Preview modal
  const [previewModal, setPreviewModal] = useState(false);
  const [previewTemplate, setPreviewTemplate] = useState<Template | null>(null);

  const showToast = useCallback((msg: string) => { setToast(msg); setTimeout(() => setToast(null), 3000); }, []);

  // Fetch templates
  const fetchTemplates = useCallback(async () => {
    try {
      const res = await fetch('/api/questionnaire-templates?include=services');
      const data = await res.json();
      setTemplates(data.templates || []);
    } catch (err) { console.error('Failed to fetch:', err); }
    finally { setLoading(false); }
  }, []);

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
      setForm({ name: full.name, description: full.description, version: full.version, status: full.status });
      setSections(full.sections || []);
      setTemplateModal(true);
    } catch { showToast('Error loading template'); }
  };

  const saveTemplate = async () => {
    setSaving(true);
    try {
      const isEdit = !!editingTemplate;
      const url = isEdit ? `/api/questionnaire-templates/${editingTemplate.id}` : '/api/questionnaire-templates';
      const method = isEdit ? 'PUT' : 'POST';
      const body: Record<string, unknown> = { ...form, sections };
      const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      if (!res.ok) throw new Error();
      showToast(isEdit ? 'Template updated!' : 'Template created!');
      setTemplateModal(false);
      fetchTemplates();
    } catch { showToast('Error saving template'); }
    finally { setSaving(false); }
  };

  const duplicateTemplate = async (t: Template) => {
    try {
      const res = await fetch('/api/questionnaire-templates', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: `${t.name} (Copy)`, description: t.description, version: t.version, status: 'draft', sections: t.sections }),
      });
      if (!res.ok) throw new Error();
      showToast('Template duplicated!');
      fetchTemplates();
    } catch { showToast('Error duplicating template'); }
  };

  const deleteTemplate = async () => {
    setSaving(true);
    try {
      await fetch(`/api/questionnaire-templates/${deleteConfirm.id}`, { method: 'DELETE' });
      showToast('Template deleted!');
      setDeleteConfirm({ open: false, id: '', name: '' });
      fetchTemplates();
    } catch { showToast('Error deleting template'); }
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
    const newSection: Section = {
      title: String(sectionForm.title || ''),
      description: String(sectionForm.description || ''),
      displayOrder: 0,
      isShared: Boolean(sectionForm.isShared),
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
    const q: Question = {
      id: questionForm.id as string | undefined,
      type: String(questionForm.type || 'text'),
      label: String(questionForm.label || ''),
      placeholder: String(questionForm.placeholder || ''),
      required: Boolean(questionForm.required),
      options: String(questionForm.options || '[]'),
      conditionalOn: String(questionForm.conditionalOn || ''),
      conditionalValue: String(questionForm.conditionalValue || ''),
      helpText: String(questionForm.helpText || ''),
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

  // ── Preview ──
  const openPreview = async (t: Template) => {
    try {
      const res = await fetch(`/api/questionnaire-templates/${t.id}`);
      const data = await res.json();
      setPreviewTemplate(data.template);
      setPreviewModal(true);
    } catch { showToast('Error loading preview'); }
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
    <section aria-label="Questionnaire Builder">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
        <div className="flex items-center gap-3">
          <ClipboardList size={24} style={{ color: '#D4AF37' }} />
          <div>
            <h1 className="text-2xl font-bold" style={{ color: '#002B5C' }}>Questionnaire Builder</h1>
            <p className="text-sm text-[#646970]">Create and manage intake questionnaires</p>
          </div>
        </div>
        <Button onClick={openNewTemplate} className="font-semibold text-white shadow-sm hover:opacity-90 transition-opacity" style={{ backgroundColor: '#D4AF37' }}>
          <Plus size={16} className="mr-1.5" /> Add New Template
        </Button>
      </div>

      {/* Templates Table */}
      <div className="bg-white rounded-lg border border-[#c3c4c7]/30 overflow-hidden">
        <div className="overflow-x-auto max-h-[600px] overflow-y-auto wp-scrollbar">
          <Table>
            <TableHeader>
              <TableRow style={{ backgroundColor: '#f8f9fa' }}>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Name</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Description</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Version</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Status</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e] text-center">Sections</TableHead>
                <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e] text-center">Linked Services</TableHead>
                <TableHead className="w-36 text-right text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Actions</TableHead>
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
                    <TableCell className="py-3 px-3 text-sm text-[#50575e] max-w-[200px] truncate">{t.description || '—'}</TableCell>
                    <TableCell className="py-3 px-3 text-sm font-mono text-[#50575e]">{t.version}</TableCell>
                    <TableCell className="py-3 px-3">
                      <Badge className="text-xs font-medium border-0" style={{ backgroundColor: st.bg, color: st.text }}>{st.label}</Badge>
                    </TableCell>
                    <TableCell className="py-3 px-3 text-center text-sm text-[#50575e]">{t.sections?.length || 0}</TableCell>
                    <TableCell className="py-3 px-3 text-center text-sm text-[#50575e]">{t._count?.services || 0}</TableCell>
                    <TableCell className="py-3 px-3 text-right">
                      <div className="flex items-center justify-end gap-1">
                        <button onClick={() => openPreview(t)} className="p-1.5 rounded text-[#2A9D8F] hover:bg-[#f0f0f1] transition-colors" title="Preview"><Eye size={15} /></button>
                        <button onClick={() => openEditTemplate(t)} className="p-1.5 rounded text-[#2271b1] hover:bg-[#f0f0f1] transition-colors" title="Edit"><Pencil size={15} /></button>
                        <button onClick={() => duplicateTemplate(t)} className="p-1.5 rounded text-[#D4AF37] hover:bg-[#f0f0f1] transition-colors" title="Duplicate"><Copy size={15} /></button>
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
              <ClipboardList size={40} className="mx-auto mb-3 opacity-40" />
              <p className="font-medium">No questionnaire templates yet</p>
              <p className="text-sm mt-1">Click &quot;Add New Template&quot; to get started</p>
            </div>
          )}
        </div>
      </div>

      {/* ═══ TEMPLATE MODAL (Create / Edit) ═══ */}
      <Dialog open={templateModal} onOpenChange={(open) => { if (!open) setTemplateModal(false); }}>
        <DialogContent className="sm:max-w-[800px] max-h-[90vh] overflow-y-auto wp-scrollbar">
          <DialogHeader>
            <DialogTitle className="text-lg" style={{ color: '#002B5C' }}>{editingTemplate ? 'Edit Questionnaire Template' : 'New Questionnaire Template'}</DialogTitle>
            <DialogDescription>{editingTemplate ? 'Update template info and manage sections/questions' : 'Create a new questionnaire template'}</DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-2">
            {/* Template Info */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Template Name *</Label>
                <Input value={String(form.name || '')} onChange={(e) => setForm({ ...form, name: e.target.value })} className="text-sm" placeholder="Company Profile Questionnaire" />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Version</Label>
                  <Input value={String(form.version || '1.0')} onChange={(e) => setForm({ ...form, version: e.target.value })} className="text-sm" />
                </div>
                <div>
                  <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Status</Label>
                  <Select value={String(form.status || 'draft')} onValueChange={(v) => setForm({ ...form, status: v })}>
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
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Description</Label>
              <Textarea value={String(form.description || '')} onChange={(e) => setForm({ ...form, description: e.target.value })} className="text-sm" rows={2} placeholder="Brief description of this questionnaire" />
            </div>

            {/* Sections */}
            <div className="border-t border-[#e5e7eb] pt-4 mt-4">
              <div className="flex items-center justify-between mb-3">
                <h3 className="text-[15px] font-bold text-[#1d2327]">Sections ({sections.length})</h3>
                <Button variant="outline" size="sm" onClick={openNewSection} className="text-xs">
                  <Plus size={14} className="mr-1" /> Add Section
                </Button>
              </div>

              {sections.length === 0 && (
                <div className="text-center py-8 text-[#a7aaad] border-2 border-dashed border-[#d1d5db] rounded-lg">
                  <p className="text-sm">No sections yet. Click &quot;Add Section&quot; to get started.</p>
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
                  />
                ))}
              </div>
            </div>
          </div>

          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setTemplateModal(false)}>Cancel</Button>
            <Button onClick={saveTemplate} disabled={saving || !String(form.name || '').trim()} className="text-white" style={{ backgroundColor: '#D4AF37' }}>
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
            <DialogDescription>Define a section of the questionnaire</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Section Title *</Label>
              <Input value={String(sectionForm.title || '')} onChange={(e) => setSectionForm({ ...sectionForm, title: e.target.value })} className="text-sm" placeholder="Company Information" />
            </div>
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Description</Label>
              <Textarea value={String(sectionForm.description || '')} onChange={(e) => setSectionForm({ ...sectionForm, description: e.target.value })} className="text-sm" rows={2} placeholder="Optional section description" />
            </div>
            <div className="flex items-center gap-3">
              <Switch checked={Boolean(sectionForm.isShared)} onCheckedChange={(v) => setSectionForm({ ...sectionForm, isShared: v })} />
              <Label className="text-sm text-[#50575e]">Shared section (reused across services)</Label>
            </div>
          </div>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setSectionModal(false)}>Cancel</Button>
            <Button onClick={saveSection} disabled={!String(sectionForm.title || '').trim()} className="text-white" style={{ backgroundColor: '#D4AF37' }}>
              {editingSectionIdx !== null ? 'Update Section' : 'Add Section'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* ═══ QUESTION MODAL ═══ */}
      <Dialog open={questionModal} onOpenChange={(open) => { if (!open) setQuestionModal(false); }}>
        <DialogContent className="sm:max-w-[600px] max-h-[90vh] overflow-y-auto wp-scrollbar">
          <DialogHeader>
            <DialogTitle className="text-lg" style={{ color: '#002B5C' }}>{editingQuestionIdx !== null ? 'Edit Question' : 'Add Question'}</DialogTitle>
            <DialogDescription>In {sections[activeSectionIdx]?.title || 'this section'}</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Question Type *</Label>
                <Select value={String(questionForm.type || 'text')} onValueChange={(v) => setQuestionForm({ ...questionForm, type: v })}>
                  <SelectTrigger className="w-full text-sm"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {QUESTION_TYPES.map((qt) => (
                      <SelectItem key={qt.value} value={qt.value}>{qt.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="flex items-end pb-1">
                <div className="flex items-center gap-3">
                  <Switch checked={Boolean(questionForm.required)} onCheckedChange={(v) => setQuestionForm({ ...questionForm, required: v })} />
                  <Label className="text-sm text-[#50575e]">Required</Label>
                </div>
              </div>
            </div>

            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Label *</Label>
              <Input value={String(questionForm.label || '')} onChange={(e) => setQuestionForm({ ...questionForm, label: e.target.value })} className="text-sm" placeholder="Company Name" />
            </div>

            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Placeholder</Label>
              <Input value={String(questionForm.placeholder || '')} onChange={(e) => setQuestionForm({ ...questionForm, placeholder: e.target.value })} className="text-sm" placeholder="Enter your company name" />
            </div>

            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Help Text</Label>
              <Input value={String(questionForm.helpText || '')} onChange={(e) => setQuestionForm({ ...questionForm, helpText: e.target.value })} className="text-sm" placeholder="Additional guidance for this question" />
            </div>

            {(String(questionForm.type || '') === 'select' || String(questionForm.type || '') === 'radio' || String(questionForm.type || '') === 'checkbox' || String(questionForm.type || '') === 'multiselect') && (
              <div>
                <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Options (JSON array)</Label>
                <Textarea value={String(questionForm.options || '[]')} onChange={(e) => setQuestionForm({ ...questionForm, options: e.target.value })} className="text-sm font-mono" rows={3} placeholder='["Option 1", "Option 2", "Option 3"]' />
                <p className="text-xs text-[#a7aaad] mt-1">Enter a JSON array of option strings</p>
              </div>
            )}

            <div className="border-t border-[#e5e7eb] pt-3 mt-2">
              <p className="text-[13px] font-semibold text-[#1d2327] mb-2">Conditional Logic</p>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <Label className="block text-[12px] font-medium text-[#50575e] mb-1">Show if question answered</Label>
                  <Select value={String(questionForm.conditionalOn || '_none')} onValueChange={(v) => setQuestionForm({ ...questionForm, conditionalOn: v === '_none' ? '' : v })}>
                    <SelectTrigger className="w-full text-sm"><SelectValue placeholder="None" /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="_none">None</SelectItem>
                      {sections[activeSectionIdx]?.questions.filter((q) => q.id !== questionForm.id).map((q) => (
                        <SelectItem key={q.id || q.label} value={q.id || q.label}>{q.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                {questionForm.conditionalOn && (
                  <div>
                    <Label className="block text-[12px] font-medium text-[#50575e] mb-1">With value</Label>
                    <Input value={String(questionForm.conditionalValue || '')} onChange={(e) => setQuestionForm({ ...questionForm, conditionalValue: e.target.value })} className="text-sm" placeholder="Expected value" />
                  </div>
                )}
              </div>
            </div>
          </div>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setQuestionModal(false)}>Cancel</Button>
            <Button onClick={saveQuestion} disabled={!String(questionForm.label || '').trim()} className="text-white" style={{ backgroundColor: '#D4AF37' }}>
              {editingQuestionIdx !== null ? 'Update Question' : 'Add Question'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* ═══ PREVIEW MODAL ═══ */}
      <Dialog open={previewModal} onOpenChange={(open) => { if (!open) setPreviewModal(false); }}>
        <DialogContent className="sm:max-w-[700px] max-h-[90vh] overflow-y-auto wp-scrollbar">
          <DialogHeader>
            <DialogTitle className="text-lg" style={{ color: '#002B5C' }}>Preview: {previewTemplate?.name}</DialogTitle>
            <DialogDescription>Read-only preview of the questionnaire form</DialogDescription>
          </DialogHeader>
          {previewTemplate && (
            <div className="space-y-6 py-2">
              {previewTemplate.sections?.map((sec) => (
                <div key={sec.id} className="border border-[#e5e7eb] rounded-lg p-4">
                  <h3 className="text-[15px] font-bold text-[#1d2327] mb-1">{sec.title}</h3>
                  {sec.description && <p className="text-sm text-[#646970] mb-4">{sec.description}</p>}
                  <div className="space-y-4">
                    {sec.questions?.map((q, qIdx) => (
                      <div key={q.id || qIdx}>
                        {q.type === 'heading' ? (
                          <h4 className="text-[14px] font-bold text-[#1d2327] mt-2">{q.label}</h4>
                        ) : q.type === 'paragraph' ? (
                          <p className="text-sm text-[#50575e] bg-[#f8f9fa] p-3 rounded mt-2">{q.label}</p>
                        ) : (
                          <div className="space-y-1.5">
                            <Label className="text-[13px] font-medium text-[#1d2327]">
                              {q.label} {q.required && <span className="text-[#d63638]">*</span>}
                            </Label>
                            {q.helpText && <p className="text-xs text-[#646970]">{q.helpText}</p>}
                            <div className="pointer-events-none opacity-60">
                              {q.type === 'textarea' ? (
                                <Textarea className="text-sm" placeholder={q.placeholder} rows={3} readOnly />
                              ) : q.type === 'select' ? (
                                <Select>
                                  <SelectTrigger className="w-full text-sm"><SelectValue placeholder={q.placeholder || 'Select...'} /></SelectTrigger>
                                  <SelectContent>
                                    {(() => { try { return JSON.parse(q.options || '[]').map((o: string, i: number) => <SelectItem key={i} value={o}>{o}</SelectItem>); } catch { return null; } })()}
                                  </SelectContent>
                                </Select>
                              ) : q.type === 'radio' || q.type === 'checkbox' ? (
                                <div className="space-y-1">
                                  {(() => { try { return JSON.parse(q.options || '[]').map((o: string, i: number) => (
                                  <div key={i} className="flex items-center gap-2 text-sm text-[#50575e]">
                                    <div className="w-4 h-4 rounded-full border-2 border-[#d1d5db]" />
                                    {o}
                                  </div>
                                )); } catch { return null; } })()}
                                </div>
                              ) : q.type === 'file' ? (
                                <div className="border-2 border-dashed border-[#d1d5db] rounded-lg p-4 text-center text-sm text-[#a7aaad]">File upload area</div>
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
              {(!previewTemplate.sections || previewTemplate.sections.length === 0) && (
                <p className="text-center text-[#a7aaad] py-8">No sections in this template</p>
              )}
            </div>
          )}
        </DialogContent>
      </Dialog>

      {/* ═══ DELETE CONFIRMATION ═══ */}
      <Dialog open={deleteConfirm.open} onOpenChange={(open) => setDeleteConfirm({ ...deleteConfirm, open })}>
        <DialogContent className="sm:max-w-[420px]">
          <DialogHeader>
            <DialogTitle className="text-lg text-[#d63638]">Delete Template?</DialogTitle>
            <DialogDescription>
              Are you sure you want to permanently delete <strong>&quot;{deleteConfirm.name}&quot;</strong>? This will also delete all sections and questions.
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

/* ═══════════════════════════════════════════════════════════════
   CollapsibleSection — Inline sub-component
   ═══════════════════════════════════════════════════════════════ */
function CollapsibleSection({ section, index, total, onEdit, onDelete, onMoveUp, onMoveDown, onAddQuestion, onEditQuestion, onDeleteQuestion }: {
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
}) {
  const [open, setOpen] = useState(false);

  return (
    <div className="border border-[#e5e7eb] rounded-lg overflow-hidden">
      {/* Section Header */}
      <div className="flex items-center gap-2 px-3 py-2.5 cursor-pointer hover:bg-[#f8f9fa] transition-colors" onClick={() => setOpen(!open)}>
        {open ? <ChevronDown size={16} className="text-[#646970]" /> : <ChevronRight size={16} className="text-[#646970]" />}
        <div className="flex items-center gap-1.5 text-[#1d2327]">
          <GripVertical size={14} className="text-[#c3c4c7]" />
          <span className="text-sm font-semibold">{section.title || 'Untitled Section'}</span>
          {section.isShared && <Badge variant="outline" className="text-[10px] px-1.5 py-0 text-[#2A9D8F] border-[#2A9D8F]">Shared</Badge>}
        </div>
        <span className="text-xs text-[#a7aaad] ml-auto">{section.questions.length} question{section.questions.length !== 1 ? 's' : ''}</span>
        <div className="flex items-center gap-0.5 ml-2" onClick={(e) => e.stopPropagation()}>
          <button onClick={onMoveUp} disabled={index === 0} className="p-1 rounded hover:bg-[#e5e7eb] disabled:opacity-30" title="Move up"><ChevronRight size={14} className="rotate-[-90deg]" /></button>
          <button onClick={onMoveDown} disabled={index === total - 1} className="p-1 rounded hover:bg-[#e5e7eb] disabled:opacity-30" title="Move down"><ChevronRight size={14} className="rotate-90" /></button>
          <button onClick={onEdit} className="p-1 rounded text-[#2271b1] hover:bg-[#f0f0f1]" title="Edit section"><Pencil size={14} /></button>
          <button onClick={onDelete} className="p-1 rounded text-[#d63638] hover:bg-red-50" title="Delete section"><Trash2 size={14} /></button>
        </div>
      </div>

      {/* Questions (expanded) */}
      {open && (
        <div className="border-t border-[#e5e7eb] bg-[#fafafa]">
          {section.questions.length === 0 ? (
            <div className="text-center py-6 text-[#a7aaad] text-sm">
              <p>No questions yet</p>
              <Button variant="link" size="sm" className="text-[#2271b1] text-xs mt-1" onClick={onAddQuestion}>+ Add Question</Button>
            </div>
          ) : (
            <>
              <div className="divide-y divide-[#f0f0f1]">
                {section.questions.map((q, qIdx) => (
                  <div key={q.id || qIdx} className="flex items-center gap-3 px-4 py-2">
                    <div className="flex-shrink-0 w-6 text-[11px] text-[#a7aaad] text-center font-mono">{qIdx + 1}</div>
                    <div className="flex-1 min-w-0">
                      <div className="text-sm text-[#1d2327] flex items-center gap-2">
                        <span className="font-medium truncate">{q.label || 'Untitled'}</span>
                        {q.required && <span className="text-[10px] text-[#d63638] font-bold">REQ</span>}
                      </div>
                      <div className="flex items-center gap-2 mt-0.5">
                        <Badge variant="outline" className="text-[10px] px-1.5 py-0 text-[#646970]">{q.type}</Badge>
                        {q.conditionalOn && <span className="text-[10px] text-[#7c3aed]">conditional</span>}
                      </div>
                    </div>
                    <div className="flex items-center gap-0.5">
                      <button onClick={() => onEditQuestion(qIdx)} className="p-1 rounded text-[#2271b1] hover:bg-[#f0f0f1]" title="Edit"><Pencil size={13} /></button>
                      <button onClick={() => onDeleteQuestion(qIdx)} className="p-1 rounded text-[#d63638] hover:bg-red-50" title="Delete"><Trash2 size={13} /></button>
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
