'use client';

import { useEffect, useState, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import {
  Plus, Search, Eye, Pencil, Trash2, X, Loader2, CheckCircle2, Circle, FileText,
  ClipboardList, FolderOpen, User, Mail, Phone, Building, Hash, StickyNote,
  ChevronRight, AlertTriangle, Clock, Download, Save,
} from 'lucide-react';

/* ═══════════════════════════════════════════════════════════════
   Types
   ═══════════════════════════════════════════════════════════════ */
interface ServiceOption {
  id: string;
  name: string;
}

interface ProjectService {
  id: string;
  serviceId: string;
  status: string;
}

interface AgreementTemplate {
  id: string;
  name: string;
}

interface ProjectAgreement {
  id: string;
  templateId: string;
  fullName: string;
  agreedAt: string;
  template?: AgreementTemplate;
}

interface QuestionnaireTemplate {
  id: string;
  name: string;
  sections?: { questions?: { id: string }[] }[];
}

interface ProjectQuestionnaire {
  id: string;
  templateId: string;
  status: string;
  completedAt: string | null;
  template?: QuestionnaireTemplate;
  _count?: { responses: number };
  responses?: { id: string }[];
}

interface ProjectDocument {
  id: string;
  projectId: string;
  name: string;
  filename: string;
  filepath: string;
  filesize: number;
  mimeType: string;
  category: string;
  createdAt: string;
}

interface Project {
  id: string;
  projectNumber: string;
  clientName: string;
  clientEmail: string;
  clientPhone: string;
  clientCompany: string;
  woocommerceOrderId: string;
  status: string;
  progressPercent: number;
  notes: string;
  assignedTo: string;
  internalNotes: string;
  createdAt: string;
  updatedAt: string;
  services: ProjectService[];
  agreements: ProjectAgreement[];
  questionnaires: ProjectQuestionnaire[];
  documents: ProjectDocument[];
}

/* ═══════════════════════════════════════════════════════════════
   Constants
   ═══════════════════════════════════════════════════════════════ */
const STATUS_OPTIONS = [
  { value: 'all', label: 'All Statuses' },
  { value: 'awaiting-agreement', label: 'Awaiting Agreement' },
  { value: 'awaiting-questionnaire', label: 'Awaiting Questionnaire' },
  { value: 'awaiting-documents', label: 'Awaiting Documents' },
  { value: 'information-review', label: 'Information Review' },
  { value: 'in-progress', label: 'In Progress' },
  { value: 'quality-check', label: 'Quality Check' },
  { value: 'completed', label: 'Completed' },
  { value: 'delivered', label: 'Delivered' },
  { value: 'archived', label: 'Archived' },
];

const STATUS_COLORS: Record<string, string> = {
  'awaiting-agreement': '#f59e0b',
  'awaiting-questionnaire': '#f97316',
  'awaiting-documents': '#8b5cf6',
  'information-review': '#3b82f6',
  'in-progress': '#2A9D8F',
  'quality-check': '#6366f1',
  'completed': '#22c55e',
  'delivered': '#10b981',
  'archived': '#6b7280',
};

const STATUS_LABELS: Record<string, string> = {
  'awaiting-agreement': 'Awaiting Agreement',
  'awaiting-questionnaire': 'Awaiting Questionnaire',
  'awaiting-documents': 'Awaiting Documents',
  'information-review': 'Information Review',
  'in-progress': 'In Progress',
  'quality-check': 'Quality Check',
  'completed': 'Completed',
  'delivered': 'Delivered',
  'archived': 'Archived',
};

const WORKFLOW_STAGES = [
  { key: 'agreement', label: 'Agreement Signed', statuses: ['awaiting-questionnaire', 'awaiting-documents', 'information-review', 'in-progress', 'quality-check', 'completed', 'delivered'] },
  { key: 'questionnaire', label: 'Questionnaires Completed', statuses: ['awaiting-documents', 'information-review', 'in-progress', 'quality-check', 'completed', 'delivered'] },
  { key: 'documents', label: 'Documents Uploaded', statuses: ['information-review', 'in-progress', 'quality-check', 'completed', 'delivered'] },
  { key: 'review', label: 'Information Review', statuses: ['in-progress', 'quality-check', 'completed', 'delivered'] },
  { key: 'progress', label: 'Work In Progress', statuses: ['quality-check', 'completed', 'delivered'] },
  { key: 'qc', label: 'Quality Check', statuses: ['completed', 'delivered'] },
  { key: 'done', label: 'Completed & Delivered', statuses: ['delivered'] },
];

const DOC_CATEGORIES: Record<string, string> = {
  'company-registration': 'Company Registration',
  'id': 'ID Documents',
  'financial': 'Financial',
  'logo': 'Logo',
  'branding': 'Branding',
  'other': 'Other',
};

function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function formatDate(dateStr: string): string {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatDateShort(dateStr: string): string {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric' });
}

/* ═══════════════════════════════════════════════════════════════
   Component
   ═══════════════════════════════════════════════════════════════ */
export function ProjectsTab({ toast }: { toast: (msg: string, type?: 'success' | 'error') => void }) {
  /* ── State ── */
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [serviceOptions, setServiceOptions] = useState<ServiceOption[]>([]);

  // Modals
  const [createModalOpen, setCreateModalOpen] = useState(false);
  const [detailModalOpen, setDetailModalOpen] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);

  // Selected project
  const [selectedProject, setSelectedProject] = useState<Project | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<{ id: string; name: string } | null>(null);

  // Create form
  const [createForm, setCreateForm] = useState({
    clientName: '',
    clientEmail: '',
    clientPhone: '',
    clientCompany: '',
    woocommerceOrderId: '',
    notes: '',
  });
  const [selectedServiceIds, setSelectedServiceIds] = useState<string[]>([]);
  const [creating, setCreating] = useState(false);

  // Detail edit state
  const [editInternalNotes, setEditInternalNotes] = useState('');
  const [editAssignedTo, setEditAssignedTo] = useState('');
  const [editStatus, setEditStatus] = useState('');
  const [savingNotes, setSavingNotes] = useState(false);
  const [savingStatus, setSavingStatus] = useState(false);

  // Detail loading
  const [detailLoading, setDetailLoading] = useState(false);

  /* ── Fetch Projects ── */
  const fetchProjects = useCallback(async () => {
    try {
      const params = new URLSearchParams();
      if (statusFilter !== 'all') params.set('status', statusFilter);
      if (searchQuery) params.set('search', searchQuery);
      const res = await fetch(`/api/projects?${params.toString()}`);
      if (!res.ok) throw new Error('Failed to fetch');
      const data = await res.json();
      setProjects(data.projects || []);
    } catch {
      toast('Failed to fetch projects', 'error');
    } finally {
      setLoading(false);
    }
  }, [statusFilter, searchQuery, toast]);

  /* ── Fetch Services ── */
  const fetchServices = useCallback(async () => {
    try {
      const res = await fetch('/api/services?all=true');
      if (!res.ok) return;
      const data = await res.json();
      setServiceOptions((data.services || []).map((s: { id: string; name: string }) => ({ id: s.id, name: s.name })));
    } catch {
      /* silently fail */
    }
  }, []);

  useEffect(() => { fetchProjects(); }, [fetchProjects]);
  useEffect(() => { fetchServices(); }, [fetchServices]);

  /* ── Stats ── */
  const stats = {
    total: projects.length,
    inProgress: projects.filter(p => ['in-progress', 'quality-check', 'information-review'].includes(p.status)).length,
    awaitingAction: projects.filter(p => ['awaiting-agreement', 'awaiting-questionnaire', 'awaiting-documents'].includes(p.status)).length,
    completed: projects.filter(p => ['completed', 'delivered'].includes(p.status)).length,
  };

  /* ── Create Project ── */
  const handleCreate = async () => {
    if (!createForm.clientName.trim()) {
      toast('Client name is required', 'error');
      return;
    }
    setCreating(true);
    try {
      const res = await fetch('/api/projects', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ...createForm,
          serviceIds: selectedServiceIds.length > 0 ? selectedServiceIds : undefined,
        }),
      });
      if (!res.ok) throw new Error('Failed to create');
      toast('Project created successfully');
      setCreateModalOpen(false);
      setCreateForm({ clientName: '', clientEmail: '', clientPhone: '', clientCompany: '', woocommerceOrderId: '', notes: '' });
      setSelectedServiceIds([]);
      fetchProjects();
    } catch {
      toast('Failed to create project', 'error');
    } finally {
      setCreating(false);
    }
  };

  /* ── Delete Project ── */
  const handleDelete = async () => {
    if (!deleteTarget) return;
    try {
      const res = await fetch(`/api/projects/${deleteTarget.id}`, { method: 'DELETE' });
      if (!res.ok) throw new Error('Failed to delete');
      toast('Project deleted successfully');
      setDeleteModalOpen(false);
      setDeleteTarget(null);
      fetchProjects();
    } catch {
      toast('Failed to delete project', 'error');
    }
  };

  /* ── View Detail ── */
  const handleViewDetail = async (project: Project) => {
    setSelectedProject(project);
    setEditInternalNotes(project.internalNotes);
    setEditAssignedTo(project.assignedTo);
    setEditStatus(project.status);
    setDetailModalOpen(true);
    setDetailLoading(true);
    try {
      const res = await fetch(`/api/projects/${project.id}`);
      if (res.ok) {
        const data = await res.json();
        setSelectedProject(data.project);
        setEditInternalNotes(data.project.internalNotes);
        setEditAssignedTo(data.project.assignedTo);
        setEditStatus(data.project.status);
      }
    } catch {
      /* use the list data */
    } finally {
      setDetailLoading(false);
    }
  };

  /* ── Save Internal Notes ── */
  const handleSaveNotes = async () => {
    if (!selectedProject) return;
    setSavingNotes(true);
    try {
      const res = await fetch(`/api/projects/${selectedProject.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ internalNotes: editInternalNotes, assignedTo: editAssignedTo }),
      });
      if (!res.ok) throw new Error('Failed');
      const data = await res.json();
      setSelectedProject(data.project);
      toast('Notes saved successfully');
      fetchProjects();
    } catch {
      toast('Failed to save notes', 'error');
    } finally {
      setSavingNotes(false);
    }
  };

  /* ── Update Status ── */
  const handleStatusUpdate = async () => {
    if (!selectedProject) return;
    setSavingStatus(true);
    try {
      const res = await fetch(`/api/projects/${selectedProject.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status: editStatus }),
      });
      if (!res.ok) throw new Error('Failed');
      const data = await res.json();
      setSelectedProject(data.project);
      toast('Status updated successfully');
      fetchProjects();
    } catch {
      toast('Failed to update status', 'error');
    } finally {
      setSavingStatus(false);
    }
  };

  /* ── Toggle Service Selection ── */
  const toggleServiceSelection = (serviceId: string) => {
    setSelectedServiceIds(prev =>
      prev.includes(serviceId) ? prev.filter(id => id !== serviceId) : [...prev, serviceId]
    );
  };

  /* ── Get Service Name ── */
  const getServiceName = (serviceId: string): string => {
    const found = serviceOptions.find(s => s.id === serviceId);
    return found ? found.name : serviceId;
  };

  /* ═══════════════════════════════════════════════════════════════
     RENDER
     ═══════════════════════════════════════════════════════════════ */
  return (
    <div className="space-y-6">
      {/* ═══ Section Header ═══ */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <ClipboardList size={24} style={{ color: '#D4AF37' }} />
          <div>
            <h1 className="text-2xl font-bold" style={{ color: '#002B5C' }}>Projects</h1>
            <p className="text-sm text-[#646970]">Manage client projects and track workflow progress</p>
          </div>
        </div>
        <Button
          onClick={() => setCreateModalOpen(true)}
          className="font-semibold text-white shadow-sm hover:opacity-90 transition-opacity"
          style={{ backgroundColor: '#D4AF37' }}
        >
          <Plus size={16} className="mr-1.5" /> New Project
        </Button>
      </div>

      {/* ═══ Summary Stats ═══ */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {[
          { label: 'Total Projects', value: stats.total, color: '#002B5C', icon: Hash },
          { label: 'In Progress', value: stats.inProgress, color: '#2A9D8F', icon: Clock },
          { label: 'Awaiting Action', value: stats.awaitingAction, color: '#f59e0b', icon: AlertTriangle },
          { label: 'Completed', value: stats.completed, color: '#22c55e', icon: CheckCircle2 },
        ].map(stat => (
          <Card key={stat.label} className="border border-[#c3c4c7]/30 hover:shadow-md transition-shadow">
            <CardContent className="p-4">
              <div className="flex items-center gap-3">
                <div
                  className="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                  style={{ backgroundColor: stat.color + '14', color: stat.color }}
                >
                  <stat.icon size={20} />
                </div>
                <div>
                  <div className="text-2xl font-extrabold" style={{ color: stat.color }}>{stat.value}</div>
                  <div className="text-[11px] text-[#646970] uppercase tracking-wide font-semibold">{stat.label}</div>
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* ═══ Filter Bar ═══ */}
      <div className="bg-white rounded-lg border border-[#c3c4c7]/30 p-4">
        <div className="flex flex-col sm:flex-row gap-3">
          <div className="w-full sm:w-[260px]">
            <Select value={statusFilter} onValueChange={(v) => { setStatusFilter(v); setLoading(true); }}>
              <SelectTrigger className="text-sm">
                <SelectValue placeholder="Filter by status" />
              </SelectTrigger>
              <SelectContent>
                {STATUS_OPTIONS.map(opt => (
                  <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="flex-1 relative">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-[#a7aaad]" />
            <Input
              placeholder="Search by project number, client name, company, or email..."
              value={searchQuery}
              onChange={(e) => { setSearchQuery(e.target.value); setLoading(true); }}
              className="pl-9 text-sm"
            />
          </div>
        </div>
      </div>

      {/* ═══ Projects Table ═══ */}
      <div className="bg-white rounded-lg border border-[#c3c4c7]/30 overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center py-20 text-[#646970]">
            <Loader2 size={24} className="animate-spin mr-2" /> Loading projects...
          </div>
        ) : projects.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-20 text-[#646970]">
            <ClipboardList size={40} className="mb-3 text-[#c3c4c7]" />
            <p className="text-sm font-medium">No projects found</p>
            <p className="text-xs text-[#a7aaad] mt-1">Create a new project or adjust your filters</p>
          </div>
        ) : (
          <div className="overflow-x-auto bv-scrollbar max-h-[520px] overflow-y-auto">
            <Table>
              <TableHeader>
                <TableRow style={{ backgroundColor: '#f8f9fa' }}>
                  <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Project #</TableHead>
                  <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Client</TableHead>
                  <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Services</TableHead>
                  <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Status</TableHead>
                  <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e] min-w-[140px]">Progress</TableHead>
                  <TableHead className="w-[120px] text-right text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {projects.map(project => (
                  <TableRow
                    key={project.id}
                    className="hover:bg-[#f8f9fa]/60 transition-colors"
                    style={{ borderColor: '#c3c4c7' }}
                  >
                    <TableCell className="py-3 px-3">
                      <span className="font-bold text-sm" style={{ color: '#002B5C' }}>{project.projectNumber}</span>
                    </TableCell>
                    <TableCell className="py-3 px-3">
                      <div className="font-medium text-sm text-[#1d2327]">{project.clientName || '—'}</div>
                      {project.clientCompany && (
                        <div className="text-xs text-[#646970] mt-0.5">{project.clientCompany}</div>
                      )}
                    </TableCell>
                    <TableCell className="py-3 px-3">
                      <div className="flex flex-wrap gap-1">
                        {project.services.length > 0 ? (
                          project.services.slice(0, 3).map(ps => (
                            <Badge
                              key={ps.id}
                              className="text-[11px] font-medium border-0"
                              style={{ backgroundColor: '#D4AF3720', color: '#92770C' }}
                            >
                              {getServiceName(ps.serviceId)}
                            </Badge>
                          ))
                        ) : (
                          <span className="text-xs text-[#a7aaad]">—</span>
                        )}
                        {project.services.length > 3 && (
                          <Badge variant="outline" className="text-[11px] text-[#646970]">
                            +{project.services.length - 3} more
                          </Badge>
                        )}
                      </div>
                    </TableCell>
                    <TableCell className="py-3 px-3">
                      <Badge
                        className="text-[11px] font-semibold border-0 text-white"
                        style={{ backgroundColor: STATUS_COLORS[project.status] || '#6b7280' }}
                      >
                        {STATUS_LABELS[project.status] || project.status}
                      </Badge>
                    </TableCell>
                    <TableCell className="py-3 px-3">
                      <div className="flex items-center gap-2">
                        <div className="flex-1 h-2 bg-[#e5e7eb] rounded-full overflow-hidden">
                          <div
                            className="h-full rounded-full transition-all duration-500"
                            style={{
                              width: `${project.progressPercent}%`,
                              backgroundColor: STATUS_COLORS[project.status] || '#2A9D8F',
                            }}
                          />
                        </div>
                        <span className="text-xs font-semibold text-[#50575e] w-8 text-right">
                          {project.progressPercent}%
                        </span>
                      </div>
                    </TableCell>
                    <TableCell className="py-3 px-3 text-right">
                      <div className="flex items-center justify-end gap-1">
                        <button
                          onClick={() => handleViewDetail(project)}
                          className="p-1.5 rounded hover:bg-[#f0f0f1] text-[#50575e] hover:text-[#2271b1] transition-colors"
                          title="View Details"
                          aria-label="View project details"
                        >
                          <Eye size={16} />
                        </button>
                        <button
                          onClick={() => {
                            setEditStatus(project.status);
                            setSelectedProject(project);
                            setDeleteTarget({ id: project.id, name: project.projectNumber });
                          }}
                          className="p-1.5 rounded hover:bg-[#f0f0f1] text-[#50575e] hover:text-[#D4AF37] transition-colors"
                          title="Quick Status Edit"
                          aria-label="Edit status"
                        >
                          <Pencil size={16} />
                        </button>
                        <button
                          onClick={() => {
                            setDeleteTarget({ id: project.id, name: project.projectNumber });
                            setDeleteModalOpen(true);
                          }}
                          className="p-1.5 rounded hover:bg-[#fef2f2] text-[#50575e] hover:text-[#dc2626] transition-colors"
                          title="Delete Project"
                          aria-label="Delete project"
                        >
                          <Trash2 size={16} />
                        </button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </div>

      {/* ═══════════════════════════════════════════════════════════
          CREATE PROJECT MODAL
      ═══════════════════════════════════════════════════════════ */}
      <Dialog open={createModalOpen} onOpenChange={setCreateModalOpen}>
        <DialogContent className="sm:max-w-[560px] max-h-[85vh] overflow-y-auto bv-scrollbar">
          <DialogHeader>
            <DialogTitle className="text-lg font-bold" style={{ color: '#002B5C' }}>Create New Project</DialogTitle>
            <DialogDescription>Enter client details and select services for the new project.</DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-2">
            {/* Client Name */}
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">
                <User size={13} className="inline mr-1" /> Client Name <span className="text-[#dc2626]">*</span>
              </Label>
              <Input
                placeholder="John Doe"
                value={createForm.clientName}
                onChange={(e) => setCreateForm(f => ({ ...f, clientName: e.target.value }))}
                className="text-sm"
              />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {/* Client Email */}
              <div>
                <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">
                  <Mail size={13} className="inline mr-1" /> Email
                </Label>
                <Input
                  type="email"
                  placeholder="john@example.com"
                  value={createForm.clientEmail}
                  onChange={(e) => setCreateForm(f => ({ ...f, clientEmail: e.target.value }))}
                  className="text-sm"
                />
              </div>

              {/* Client Phone */}
              <div>
                <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">
                  <Phone size={13} className="inline mr-1" /> Phone
                </Label>
                <Input
                  placeholder="+27 12 345 6789"
                  value={createForm.clientPhone}
                  onChange={(e) => setCreateForm(f => ({ ...f, clientPhone: e.target.value }))}
                  className="text-sm"
                />
              </div>
            </div>

            {/* Client Company */}
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">
                <Building size={13} className="inline mr-1" /> Company
              </Label>
              <Input
                placeholder="Acme Inc."
                value={createForm.clientCompany}
                onChange={(e) => setCreateForm(f => ({ ...f, clientCompany: e.target.value }))}
                className="text-sm"
              />
            </div>

            {/* WooCommerce Order ID */}
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">
                <Hash size={13} className="inline mr-1" /> WooCommerce Order ID
              </Label>
              <Input
                placeholder="12345"
                value={createForm.woocommerceOrderId}
                onChange={(e) => setCreateForm(f => ({ ...f, woocommerceOrderId: e.target.value }))}
                className="text-sm"
              />
            </div>

            {/* Services Multi-Select */}
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">
                <FileText size={13} className="inline mr-1" /> Services
              </Label>
              {serviceOptions.length === 0 ? (
                <p className="text-xs text-[#a7aaad]">No services available</p>
              ) : (
                <div className="border border-[#d1d5db] rounded-md p-3 max-h-[180px] overflow-y-auto bv-scrollbar space-y-1">
                  {serviceOptions.map(service => (
                    <label
                      key={service.id}
                      className="flex items-center gap-2.5 px-2 py-1.5 rounded cursor-pointer hover:bg-[#f8f9fa] transition-colors"
                    >
                      <input
                        type="checkbox"
                        checked={selectedServiceIds.includes(service.id)}
                        onChange={() => toggleServiceSelection(service.id)}
                        className="w-4 h-4 rounded border-[#d1d5db] accent-[#D4AF37]"
                      />
                      <span className="text-sm text-[#1d2327]">{service.name}</span>
                    </label>
                  ))}
                </div>
              )}
              {selectedServiceIds.length > 0 && (
                <p className="text-xs text-[#646970] mt-1">{selectedServiceIds.length} service(s) selected</p>
              )}
            </div>

            {/* Notes */}
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">
                <StickyNote size={13} className="inline mr-1" /> Notes
              </Label>
              <Textarea
                placeholder="Any additional notes about this project..."
                value={createForm.notes}
                onChange={(e) => setCreateForm(f => ({ ...f, notes: e.target.value }))}
                className="text-sm min-h-[80px]"
              />
            </div>
          </div>

          <DialogFooter className="gap-2 pt-2 border-t border-[#e5e7eb]">
            <Button variant="outline" onClick={() => setCreateModalOpen(false)} className="text-sm">Cancel</Button>
            <Button
              onClick={handleCreate}
              disabled={creating}
              className="font-semibold text-white hover:opacity-90 transition-opacity"
              style={{ backgroundColor: '#D4AF37' }}
            >
              {creating ? <Loader2 size={14} className="animate-spin mr-1.5" /> : <Plus size={14} className="mr-1.5" />}
              Create Project
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* ═══════════════════════════════════════════════════════════
          PROJECT DETAIL MODAL
      ═══════════════════════════════════════════════════════════ */}
      <Dialog open={detailModalOpen} onOpenChange={setDetailModalOpen}>
        <DialogContent className="sm:max-w-[880px] max-h-[90vh] overflow-hidden p-0">
          {detailLoading ? (
            <div className="flex items-center justify-center py-32 text-[#646970]">
              <Loader2 size={28} className="animate-spin mr-2" /> Loading project details...
            </div>
          ) : selectedProject ? (
            <div className="bv-scrollbar max-h-[90vh] overflow-y-auto">
              {/* ── Detail Header ── */}
              <div className="p-6 border-b border-[#e5e7eb]">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                  <div className="flex items-center gap-3">
                    <ClipboardList size={24} style={{ color: '#D4AF37' }} />
                    <div>
                      <h2 className="text-xl font-bold" style={{ color: '#002B5C' }}>{selectedProject.projectNumber}</h2>
                      <p className="text-sm text-[#646970]">Created {formatDateShort(selectedProject.createdAt)}</p>
                    </div>
                  </div>
                  <Badge
                    className="text-xs font-semibold border-0 text-white px-3 py-1"
                    style={{ backgroundColor: STATUS_COLORS[selectedProject.status] || '#6b7280' }}
                  >
                    {STATUS_LABELS[selectedProject.status] || selectedProject.status}
                  </Badge>
                </div>
              </div>

              <div className="p-6 space-y-6">
                {/* ── Client Info Card ── */}
                <div className="bg-[#f8f9fa] rounded-lg border border-[#e5e7eb] p-4">
                  <h3 className="text-sm font-bold uppercase tracking-wide mb-3" style={{ color: '#002B5C' }}>Client Information</h3>
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div className="flex items-center gap-2">
                      <User size={14} className="text-[#a7aaad] flex-shrink-0" />
                      <div>
                        <div className="text-[10px] text-[#a7aaad] uppercase font-semibold">Name</div>
                        <div className="text-sm text-[#1d2327] font-medium">{selectedProject.clientName || '—'}</div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <Mail size={14} className="text-[#a7aaad] flex-shrink-0" />
                      <div>
                        <div className="text-[10px] text-[#a7aaad] uppercase font-semibold">Email</div>
                        <div className="text-sm text-[#1d2327] font-medium">{selectedProject.clientEmail || '—'}</div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <Phone size={14} className="text-[#a7aaad] flex-shrink-0" />
                      <div>
                        <div className="text-[10px] text-[#a7aaad] uppercase font-semibold">Phone</div>
                        <div className="text-sm text-[#1d2327] font-medium">{selectedProject.clientPhone || '—'}</div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <Building size={14} className="text-[#a7aaad] flex-shrink-0" />
                      <div>
                        <div className="text-[10px] text-[#a7aaad] uppercase font-semibold">Company</div>
                        <div className="text-sm text-[#1d2327] font-medium">{selectedProject.clientCompany || '—'}</div>
                      </div>
                    </div>
                  </div>
                </div>

                {/* ── Progress Bar ── */}
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <h3 className="text-sm font-bold uppercase tracking-wide" style={{ color: '#002B5C' }}>Overall Progress</h3>
                    <span className="text-sm font-bold" style={{ color: STATUS_COLORS[selectedProject.status] || '#2A9D8F' }}>
                      {selectedProject.progressPercent}%
                    </span>
                  </div>
                  <div className="h-3 bg-[#e5e7eb] rounded-full overflow-hidden">
                    <div
                      className="h-full rounded-full transition-all duration-700"
                      style={{
                        width: `${selectedProject.progressPercent}%`,
                        backgroundColor: STATUS_COLORS[selectedProject.status] || '#2A9D8F',
                      }}
                    />
                  </div>
                </div>

                {/* ── Workflow Timeline ── */}
                <div>
                  <h3 className="text-sm font-bold uppercase tracking-wide mb-3" style={{ color: '#002B5C' }}>Workflow Timeline</h3>
                  <div className="relative pl-6">
                    {WORKFLOW_STAGES.map((stage, idx) => {
                      const isComplete = stage.statuses.includes(selectedProject.status);
                      const isCurrent = idx === WORKFLOW_STAGES.findIndex(s => s.statuses.includes(selectedProject.status));
                      return (
                        <div key={stage.key} className="relative pb-4 last:pb-0">
                          {/* Vertical line */}
                          {idx < WORKFLOW_STAGES.length - 1 && (
                            <div
                              className="absolute left-[-16px] top-5 w-0.5"
                              style={{
                                height: 'calc(100% + 8px)',
                                backgroundColor: isComplete ? '#22c55e' : '#e5e7eb',
                              }}
                            />
                          )}
                          {/* Circle icon */}
                          <div
                            className="absolute left-[-20px] top-0.5 w-5 h-5 rounded-full flex items-center justify-center border-2"
                            style={{
                              borderColor: isComplete ? '#22c55e' : (isCurrent ? (STATUS_COLORS[selectedProject.status] || '#f59e0b') : '#d1d5db'),
                              backgroundColor: isComplete ? '#22c55e' : (isCurrent ? (STATUS_COLORS[selectedProject.status] || '#f59e0b') + '30' : '#fff'),
                            }}
                          >
                            {isComplete ? (
                              <CheckCircle2 size={12} className="text-white" />
                            ) : isCurrent ? (
                              <Circle size={8} style={{ color: STATUS_COLORS[selectedProject.status] || '#f59e0b' }} fill={STATUS_COLORS[selectedProject.status] || '#f59e0b'} />
                            ) : null}
                          </div>
                          <div className="flex items-center gap-2">
                            <span className={`text-sm ${isCurrent ? 'font-bold' : 'font-medium'}`} style={{ color: isComplete ? '#22c55e' : (isCurrent ? '#1d2327' : '#9ca3af') }}>
                              {stage.label}
                            </span>
                            {isCurrent && (
                              <Badge className="text-[10px] font-semibold border-0 text-white" style={{ backgroundColor: STATUS_COLORS[selectedProject.status] || '#f59e0b' }}>
                                Current
                              </Badge>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>

                <Separator />

                {/* ── Linked Services ── */}
                <div>
                  <h3 className="text-sm font-bold uppercase tracking-wide mb-3" style={{ color: '#002B5C' }}>Services</h3>
                  {selectedProject.services.length === 0 ? (
                    <p className="text-sm text-[#a7aaad]">No services linked to this project</p>
                  ) : (
                    <div className="space-y-2">
                      {selectedProject.services.map(ps => {
                        const svcColor = STATUS_COLORS[ps.status] || '#6b7280';
                        return (
                          <div key={ps.id} className="flex items-center justify-between p-3 bg-white border border-[#e5e7eb] rounded-lg">
                            <div className="flex items-center gap-2">
                              <FileText size={14} style={{ color: '#D4AF37' }} />
                              <span className="text-sm font-medium text-[#1d2327]">{getServiceName(ps.serviceId)}</span>
                            </div>
                            <Badge
                              className="text-[10px] font-semibold border-0 text-white"
                              style={{ backgroundColor: svcColor }}
                            >
                              {ps.status}
                            </Badge>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>

                <Separator />

                {/* ── Agreement Section ── */}
                <div>
                  <h3 className="text-sm font-bold uppercase tracking-wide mb-3" style={{ color: '#002B5C' }}>Agreement</h3>
                  {selectedProject.agreements.length === 0 ? (
                    <div className="flex items-center gap-2 p-3 bg-[#fffbeb] border border-[#fde68a] rounded-lg">
                      <AlertTriangle size={16} style={{ color: '#f59e0b' }} />
                      <span className="text-sm text-[#92400e] font-medium">Not Signed</span>
                      <Badge className="text-[10px] font-semibold border-0 text-white ml-auto" style={{ backgroundColor: '#f59e0b' }}>
                        Pending
                      </Badge>
                    </div>
                  ) : (
                    <div className="space-y-2">
                      {selectedProject.agreements.map(ag => (
                        <div key={ag.id} className="flex items-center justify-between p-3 bg-[#f0fdf4] border border-[#bbf7d0] rounded-lg">
                          <div className="flex items-center gap-2">
                            <CheckCircle2 size={16} style={{ color: '#22c55e' }} />
                            <div>
                              <div className="text-sm font-medium text-[#1d2327]">{ag.template?.name || 'Agreement'}</div>
                              <div className="text-xs text-[#646970]">Signed by <strong>{ag.fullName}</strong> on {formatDate(ag.agreedAt)}</div>
                            </div>
                          </div>
                          <Badge className="text-[10px] font-semibold border-0 text-white" style={{ backgroundColor: '#22c55e' }}>
                            Signed
                          </Badge>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                <Separator />

                {/* ── Questionnaires Section ── */}
                <div>
                  <h3 className="text-sm font-bold uppercase tracking-wide mb-3" style={{ color: '#002B5C' }}>Questionnaires</h3>
                  {selectedProject.questionnaires.length === 0 ? (
                    <p className="text-sm text-[#a7aaad]">No questionnaires assigned to this project</p>
                  ) : (
                    <div className="space-y-2">
                      {selectedProject.questionnaires.map(q => {
                        const qColor = q.status === 'completed' ? '#22c55e' : q.status === 'in-progress' ? '#f59e0b' : '#6b7280';
                        const responseCount = q._count?.responses ?? q.responses?.length ?? 0;
                        // Calculate total questions for progress
                        const totalQuestions = q.template?.sections?.reduce((sum, s) => sum + (s.questions?.length ?? 0), 0) ?? 0;
                        const qProgress = totalQuestions > 0 ? Math.round((responseCount / totalQuestions) * 100) : 0;
                        return (
                          <div key={q.id} className="p-3 bg-white border border-[#e5e7eb] rounded-lg">
                            <div className="flex items-center justify-between mb-2">
                              <div className="flex items-center gap-2">
                                <ClipboardList size={14} style={{ color: '#6366f1' }} />
                                <span className="text-sm font-medium text-[#1d2327]">{q.template?.name || 'Questionnaire'}</span>
                              </div>
                              <div className="flex items-center gap-2">
                                <span className="text-xs text-[#646970]">{responseCount} response(s)</span>
                                <Badge className="text-[10px] font-semibold border-0 text-white" style={{ backgroundColor: qColor }}>
                                  {q.status}
                                </Badge>
                              </div>
                            </div>
                            <div className="flex items-center gap-2">
                              <div className="flex-1 h-1.5 bg-[#e5e7eb] rounded-full overflow-hidden">
                                <div
                                  className="h-full rounded-full transition-all duration-500"
                                  style={{ width: `${qProgress}%`, backgroundColor: qColor }}
                                />
                              </div>
                              <span className="text-[11px] font-semibold text-[#646970] w-8 text-right">{qProgress}%</span>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </div>

                <Separator />

                {/* ── Documents Section ── */}
                <div>
                  <h3 className="text-sm font-bold uppercase tracking-wide mb-3" style={{ color: '#002B5C' }}>Documents</h3>
                  {selectedProject.documents.length === 0 ? (
                    <p className="text-sm text-[#a7aaad]">No documents uploaded</p>
                  ) : (
                    <div className="space-y-2">
                      {selectedProject.documents.map(doc => (
                        <div key={doc.id} className="flex items-center justify-between p-3 bg-white border border-[#e5e7eb] rounded-lg">
                          <div className="flex items-center gap-3 min-w-0">
                            <div className="w-8 h-8 rounded flex items-center justify-center flex-shrink-0" style={{ backgroundColor: '#8b5cf620' }}>
                              <FileText size={14} style={{ color: '#8b5cf6' }} />
                            </div>
                            <div className="min-w-0">
                              <div className="text-sm font-medium text-[#1d2327] truncate">{doc.name}</div>
                              <div className="text-[11px] text-[#646970]">
                                {formatBytes(doc.filesize)} &middot; {DOC_CATEGORIES[doc.category] || doc.category} &middot; {formatDateShort(doc.createdAt)}
                              </div>
                            </div>
                          </div>
                          <a
                            href={doc.filepath}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="p-1.5 rounded hover:bg-[#f0f0f1] text-[#50575e] hover:text-[#2271b1] transition-colors flex-shrink-0"
                            title="Download"
                            aria-label={`Download ${doc.name}`}
                          >
                            <Download size={16} />
                          </a>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                <Separator />

                {/* ── Internal Notes & Assignment ── */}
                <div>
                  <h3 className="text-sm font-bold uppercase tracking-wide mb-3" style={{ color: '#002B5C' }}>Internal Notes</h3>
                  <div className="space-y-3">
                    <div>
                      <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Assigned To</Label>
                      <Input
                        placeholder="Team member name"
                        value={editAssignedTo}
                        onChange={(e) => setEditAssignedTo(e.target.value)}
                        className="text-sm"
                      />
                    </div>
                    <div>
                      <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Notes</Label>
                      <Textarea
                        placeholder="Internal notes about this project..."
                        value={editInternalNotes}
                        onChange={(e) => setEditInternalNotes(e.target.value)}
                        className="text-sm min-h-[100px]"
                      />
                    </div>
                    <div className="flex justify-end">
                      <Button
                        onClick={handleSaveNotes}
                        disabled={savingNotes}
                        size="sm"
                        className="font-semibold text-white hover:opacity-90 transition-opacity"
                        style={{ backgroundColor: '#D4AF37' }}
                      >
                        {savingNotes ? <Loader2 size={14} className="animate-spin mr-1.5" /> : <Save size={14} className="mr-1.5" />}
                        Save Notes
                      </Button>
                    </div>
                  </div>
                </div>

                <Separator />

                {/* ── Status Update ── */}
                <div>
                  <h3 className="text-sm font-bold uppercase tracking-wide mb-3" style={{ color: '#002B5C' }}>Update Status</h3>
                  <div className="flex flex-col sm:flex-row gap-3 items-end">
                    <div className="flex-1 w-full">
                      <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Project Status</Label>
                      <Select value={editStatus} onValueChange={setEditStatus}>
                        <SelectTrigger className="text-sm">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          {STATUS_OPTIONS.filter(o => o.value !== 'all').map(opt => (
                            <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <Button
                      onClick={handleStatusUpdate}
                      disabled={savingStatus || editStatus === selectedProject.status}
                      size="sm"
                      className="font-semibold text-white hover:opacity-90 transition-opacity"
                      style={{ backgroundColor: '#002B5C' }}
                    >
                      {savingStatus ? <Loader2 size={14} className="animate-spin mr-1.5" /> : <Pencil size={14} className="mr-1.5" />}
                      Update Status
                    </Button>
                  </div>
                </div>
              </div>
            </div>
          ) : null}
        </DialogContent>
      </Dialog>

      {/* ═══════════════════════════════════════════════════════════
          DELETE CONFIRMATION MODAL
      ═══════════════════════════════════════════════════════════ */}
      <Dialog open={deleteModalOpen} onOpenChange={setDeleteModalOpen}>
        <DialogContent className="sm:max-w-[420px]">
          <DialogHeader>
            <DialogTitle className="text-lg font-bold" style={{ color: '#002B5C' }}>Delete Project</DialogTitle>
            <DialogDescription>
              Are you sure you want to delete <strong>{deleteTarget?.name}</strong>? This action cannot be undone and will permanently remove the project and all associated data.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2 pt-2">
            <Button variant="outline" onClick={() => setDeleteModalOpen(false)} className="text-sm">Cancel</Button>
            <Button
              onClick={handleDelete}
              className="font-semibold text-white hover:opacity-90 transition-opacity"
              style={{ backgroundColor: '#dc2626' }}
            >
              <Trash2 size={14} className="mr-1.5" /> Delete Project
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
