'use client';

import React, { useEffect, useState, useCallback, type DragEndEvent } from 'react';
import {
  DndContext, closestCenter, PointerSensor, useSensor, useSensors,
} from '@dnd-kit/core';
import {
  SortableContext, useSortable, verticalListSortingStrategy, arrayMove,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
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
  Eye, EyeOff, Star, GripVertical, Pencil, Trash2, Plus, X,
  LayoutDashboard, FileText, CreditCard, FolderOpen, Settings,
  Menu, ChevronLeft, Copy, Shield, Loader2,
} from 'lucide-react';

/* ═══════════════════════════════════════════════════════════════
   Types
   ═══════════════════════════════════════════════════════════════ */
interface Settings {
  [key: string]: string;
}

interface CategoryItem {
  id: string;
  name: string;
  slug: string;
  color: string;
}

interface ServiceItem {
  id: string;
  name: string;
  description: string;
  price: number;
  icon: string;
  buttonLabel: string;
  buttonType: string;
  buttonUrl: string;
  woocommerceProductId: string;
  categoryId: string | null;
  visible: boolean;
  featured: boolean;
  displayOrder: number;
  category?: CategoryItem | null;
}

interface PlanItem {
  id: string;
  name: string;
  subtitle: string;
  price: number;
  color: string;
  buttonLabel: string;
  buttonType: string;
  buttonUrl: string;
  woocommerceProductId: string;
  categoryId: string | null;
  visible: boolean;
  featured: boolean;
  displayOrder: number;
  features: { id: string; text: string }[];
  category?: CategoryItem | null;
}

type TabId = 'dashboard' | 'services' | 'plans' | 'categories' | 'settings';

/* ═══════════════════════════════════════════════════════════════
   SVG Icon Paths (same 22 icons from public page)
   ═══════════════════════════════════════════════════════════════ */
const iconPaths: Record<string, string> = {
  shield: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
  'shield-check': '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
  'check-circle': '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
  award: '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>',
  star: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
  crown: '<path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/>',
  lock: '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  clock: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
  'clipboard-list': '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
  search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
  users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  calculator: '<rect width="16" height="20" x="4" y="2" rx="2"/><line x1="8" x2="16" y1="6" y2="6"/><line x1="16" x2="16" y1="14" y2="18"/><line x1="8" x2="8" y1="10" y2="10.01"/><line x1="12" x2="12" y1="10" y2="10.01"/><line x1="16" x2="16" y1="10" y2="10.01"/><line x1="8" x2="8" y1="14" y2="14.01"/><line x1="12" x2="12" y1="14" y2="14.01"/><line x1="8" x2="8" y1="18" y2="18.01"/><line x1="12" x2="12" y1="18" y2="18.01"/>',
  megaphone: '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
  'trending-up': '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
  'shield-alert': '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
  'file-text': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
  presentation: '<path d="M2 3h20"/><path d="M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3"/><path d="m7 21 5-5 5 5"/>',
  'heart-pulse': '<path d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572"/><path d="M12 6l-2 4h4l-2 4"/>',
  handshake: '<path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/><path d="M3 4h8"/>',
  wrench: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
  check: '<polyline points="20 6 9 17 4 12"/>',
};

const ICON_OPTIONS = Object.keys(iconPaths);

function BvIcon({ name, size = 20 }: { name: string; size?: number }) {
  const d = iconPaths[name];
  if (!d) return <FileText size={size} />;
  return (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" dangerouslySetInnerHTML={{ __html: d }} />
  );
}

/* ═══════════════════════════════════════════════════════════════
   Constants
   ═══════════════════════════════════════════════════════════════ */
const BUTTON_TYPES = [
  { value: 'cart', label: 'Add to Cart' },
  { value: 'quote', label: 'Request Quote' },
  { value: 'booking', label: 'Book Now' },
  { value: 'link', label: 'External Link' },
];

const SIDEBAR_MENU: { id: TabId; label: string; icon: typeof LayoutDashboard }[] = [
  { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { id: 'services', label: 'Services', icon: FileText },
  { id: 'plans', label: 'Subscription Plans', icon: CreditCard },
  { id: 'categories', label: 'Categories', icon: FolderOpen },
  { id: 'settings', label: 'Settings', icon: Settings },
];

/* ═══════════════════════════════════════════════════════════════
   Small Helper Components
   ═══════════════════════════════════════════════════════════════ */
function SortableRow({ id, children }: { id: string; children: React.ReactNode }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });
  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.4 : 1,
    background: isDragging ? '#FEF3C7' : undefined,
    zIndex: isDragging ? 50 : undefined,
  };
  return (
    <tr ref={setNodeRef} style={style} {...attributes}>
      {React.Children.map(children, (child, i) => {
        if (i === 0) return <td className="text-center p-2"><button className="cursor-grab active:cursor-grabbing text-[#9ca3af] hover:text-[#50575e] transition-colors" {...listeners} aria-label="Drag to reorder"><GripVertical size={18} /></button></td>;
        return child;
      })}
    </tr>
  );
}

function CopyBtn({ text }: { text: string }) {
  const [copied, setCopied] = useState(false);
  return (
    <button type="button" onClick={() => { navigator.clipboard.writeText(text); setCopied(true); setTimeout(() => setCopied(false), 1500); }} className="absolute top-2 right-2 p-1 rounded hover:bg-white/10 transition-colors" title="Copy">
      <Copy size={14} className={copied ? 'text-green-400' : 'text-white/60'} />
    </button>
  );
}

function SettingsCard({ title, description, children }: { title: string; description: string; children: React.ReactNode }) {
  return (
    <Card className="border-[#c3c4c7]/30 overflow-hidden">
      <div className="px-5 py-3 border-b border-[#c3c4c7]/30" style={{ backgroundColor: '#f8f9fa' }}>
        <h3 className="text-[15px] font-bold" style={{ color: '#002B5C' }}>{title}</h3>
        <p className="text-xs text-[#646970] mt-0.5">{description}</p>
      </div>
      <CardContent className="p-5">{children}</CardContent>
    </Card>
  );
}

function FormField({ label, value, onChange, placeholder }: { label: string; value: string; onChange: (v: string) => void; placeholder?: string }) {
  return (
    <div>
      <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">{label}</Label>
      <Input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className="text-sm" />
    </div>
  );
}

function ColorField({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) {
  return (
    <div>
      <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">{label}</Label>
      <div className="flex items-center gap-2">
        <input type="color" value={value} onChange={(e) => onChange(e.target.value)} className="w-10 h-10 rounded border border-[#d1d5db] cursor-pointer p-0.5" />
        <Input value={value} onChange={(e) => onChange(e.target.value)} className="text-sm font-mono flex-1" />
      </div>
    </div>
  );
}

function Toast({ message }: { message: string }) {
  if (!message) return null;
  return (
    <div className="fixed bottom-4 right-4 z-[9999] px-4 py-3 rounded-lg text-white text-sm font-medium shadow-lg" style={{ backgroundColor: '#002B5C', animation: 'bvSlideIn 0.3s ease-out' }}>
      {message}
    </div>
  );
}

/* ═══════════════════════════════════════════════════════════════
   Main Page Component
   ═══════════════════════════════════════════════════════════════ */
export default function Home() {
  /* ── State ── */
  const [activeTab, setActiveTab] = useState<TabId>('dashboard');
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState<string | null>(null);

  const [settings, setSettings] = useState<Settings>({});
  const [services, setServices] = useState<ServiceItem[]>([]);
  const [plans, setPlans] = useState<PlanItem[]>([]);
  const [categories, setCategories] = useState<CategoryItem[]>([]);

  const [serviceModal, setServiceModal] = useState<{ open: boolean; data: ServiceItem | null }>({ open: false, data: null });
  const [planModal, setPlanModal] = useState<{ open: boolean; data: PlanItem | null }>({ open: false, data: null });
  const [categoryModal, setCategoryModal] = useState<{ open: boolean; data: CategoryItem | null }>({ open: false, data: null });
  const [deleteConfirm, setDeleteConfirm] = useState<{ open: boolean; type: 'service' | 'plan' | 'category'; id: string; name: string }>({ open: false, type: 'service', id: '', name: '' });

  const [serviceForm, setServiceForm] = useState<Record<string, unknown>>({});
  const [planForm, setPlanForm] = useState<Record<string, unknown>>({});
  const [planFeatures, setPlanFeatures] = useState<string[]>(['']);
  const [categoryForm, setCategoryForm] = useState<Record<string, unknown>>({});
  const [settingsForm, setSettingsForm] = useState<Settings>({});

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }));

  /* ── Toast ── */
  const showToast = useCallback((msg: string) => { setToast(msg); setTimeout(() => setToast(null), 3000); }, []);

  /* ── Data Fetching ── */
  const fetchData = useCallback(async () => {
    try {
      const [settingsRes, servicesRes, plansRes, categoriesRes] = await Promise.all([
        fetch('/api/settings'),
        fetch('/api/services?all=true'),
        fetch('/api/plans?all=true'),
        fetch('/api/categories'),
      ]);
      const [settingsData, servicesData, plansData, categoriesData] = await Promise.all([
        settingsRes.json(), servicesRes.json(), plansRes.json(), categoriesRes.json(),
      ]);
      const s = settingsData.settings || {};
      setSettings(s);
      setSettingsForm({ ...s });
      setServices((servicesData.services || []).sort((a: ServiceItem, b: ServiceItem) => a.displayOrder - b.displayOrder));
      setPlans((plansData.plans || []).sort((a: PlanItem, b: PlanItem) => a.displayOrder - b.displayOrder));
      setCategories(categoriesData.categories || []);
    } catch (err) { console.error('Failed to fetch:', err); }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { fetchData(); }, [fetchData]);

  /* ── CRUD ── */
  const saveService = async () => {
    setSaving(true);
    try {
      const f = serviceForm;
      const isEdit = !!f.id;
      const url = isEdit ? `/api/services/${f.id}` : '/api/services';
      const method = isEdit ? 'PUT' : 'POST';
      const body = { ...f };
      if (isEdit) delete (body as Record<string, unknown>).id;
      const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      if (!res.ok) throw new Error();
      showToast(isEdit ? 'Service updated!' : 'Service created!');
      setServiceModal({ open: false, data: null });
      fetchData();
    } catch { showToast('Error saving service'); }
    finally { setSaving(false); }
  };

  const savePlan = async () => {
    setSaving(true);
    try {
      const f = planForm;
      const isEdit = !!f.id;
      const url = isEdit ? `/api/plans/${f.id}` : '/api/plans';
      const method = isEdit ? 'PUT' : 'POST';
      const body = { ...f, features: planFeatures.filter(Boolean) };
      if (isEdit) delete (body as Record<string, unknown>).id;
      const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      if (!res.ok) throw new Error();
      showToast(isEdit ? 'Plan updated!' : 'Plan created!');
      setPlanModal({ open: false, data: null });
      fetchData();
    } catch { showToast('Error saving plan'); }
    finally { setSaving(false); }
  };

  const saveCategory = async () => {
    setSaving(true);
    try {
      const f = categoryForm;
      const isEdit = !!f.id;
      const url = isEdit ? `/api/categories/${f.id}` : '/api/categories';
      const method = isEdit ? 'PUT' : 'POST';
      const body = { ...f };
      if (isEdit) delete (body as Record<string, unknown>).id;
      const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      if (!res.ok) throw new Error();
      showToast(isEdit ? 'Category updated!' : 'Category created!');
      setCategoryModal({ open: false, data: null });
      fetchData();
    } catch { showToast('Error saving category'); }
    finally { setSaving(false); }
  };

  const deleteItem = async () => {
    const { type, id } = deleteConfirm;
    setSaving(true);
    try {
      const url = type === 'service' ? `/api/services/${id}` : type === 'plan' ? `/api/plans/${id}` : `/api/categories/${id}`;
      const res = await fetch(url, { method: 'DELETE' });
      if (!res.ok) throw new Error();
      showToast('Deleted successfully!');
      setDeleteConfirm({ open: false, type: 'service', id: '', name: '' });
      fetchData();
    } catch { showToast('Error deleting'); }
    finally { setSaving(false); }
  };

  const toggleVisibility = async (type: 'service' | 'plan', id: string, current: boolean) => {
    try {
      await fetch(type === 'service' ? `/api/services/${id}` : `/api/plans/${id}`, {
        method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ visible: !current }),
      });
      fetchData();
    } catch { showToast('Error toggling visibility'); }
  };

  const saveSettings = async () => {
    setSaving(true);
    try {
      const res = await fetch('/api/settings', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ settings: settingsForm }) });
      if (!res.ok) throw new Error();
      const data = await res.json();
      setSettings(data.settings || {});
      showToast('Settings saved!');
    } catch { showToast('Error saving settings'); }
    finally { setSaving(false); }
  };

  const handleServiceReorder = async (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const ids = services.map((s) => s.id);
    const oldIdx = ids.indexOf(String(active.id));
    const newIdx = ids.indexOf(String(over.id));
    if (oldIdx === -1 || newIdx === -1) return;
    const reordered = arrayMove(services, oldIdx, newIdx);
    setServices(reordered);
    try {
      await fetch('/api/services/reorder', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ items: reordered.map((s, i) => ({ id: s.id, displayOrder: i })) }) });
    } catch { showToast('Reorder failed'); }
  };

  const handlePlanReorder = async (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const ids = plans.map((p) => p.id);
    const oldIdx = ids.indexOf(String(active.id));
    const newIdx = ids.indexOf(String(over.id));
    if (oldIdx === -1 || newIdx === -1) return;
    const reordered = arrayMove(plans, oldIdx, newIdx);
    setPlans(reordered);
    try {
      await fetch('/api/plans/reorder', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ items: reordered.map((p, i) => ({ id: p.id, displayOrder: i })) }) });
    } catch { showToast('Reorder failed'); }
  };

  /* ── Derived Stats ── */
  const stats = {
    totalServices: services.length,
    totalPlans: plans.length,
    visibleItems: services.filter((s) => s.visible).length + plans.filter((p) => p.visible).length,
    hiddenItems: services.filter((s) => !s.visible).length + plans.filter((p) => !p.visible).length,
    featuredItems: services.filter((s) => s.featured).length + plans.filter((p) => p.featured).length,
    categories: categories.length,
  };

  const catServiceCount = (catId: string) => services.filter((s) => s.categoryId === catId).length;
  const catPlanCount = (catId: string) => plans.filter((p) => p.categoryId === catId).length;

  /* ── Open Modal Helpers ── */
  const openNewService = () => {
    setServiceForm({ name: '', description: '', price: 0, icon: 'file-text', buttonLabel: 'ADD TO CART', buttonType: 'cart', buttonUrl: '', woocommerceProductId: '', categoryId: null, visible: true, featured: false, displayOrder: services.length });
    setServiceModal({ open: true, data: null });
  };
  const openEditService = (s: ServiceItem) => { setServiceForm({ ...s }); setServiceModal({ open: true, data: s }); };

  const openNewPlan = () => {
    setPlanForm({ name: '', subtitle: '', price: 0, color: '#002B5C', buttonLabel: 'GET STARTED', buttonType: 'cart', buttonUrl: '', woocommerceProductId: '', categoryId: null, visible: true, featured: false, displayOrder: plans.length });
    setPlanFeatures(['']);
    setPlanModal({ open: true, data: null });
  };
  const openEditPlan = (p: PlanItem) => {
    setPlanForm({ ...p });
    setPlanFeatures(p.features.map((f) => f.text));
    setPlanModal({ open: true, data: p });
  };

  const openNewCategory = () => { setCategoryForm({ name: '', slug: '', color: '#002B5C' }); setCategoryModal({ open: true, data: null }); };
  const openEditCategory = (c: CategoryItem) => { setCategoryForm({ ...c }); setCategoryModal({ open: true, data: c }); };

  /* ── Loading ── */
  if (loading) {
    return (
      <div className="min-h-screen bg-[#f0f0f1] flex items-center justify-center">
        <div className="flex flex-col items-center gap-3">
          <Loader2 className="animate-spin" size={40} style={{ color: '#D4AF37' }} />
          <p className="text-[#646970] text-sm">Loading BusinessVance Admin...</p>
        </div>
      </div>
    );
  }

  /* ═══════════════════════════════════════════════════════════════
     RENDER
     ═══════════════════════════════════════════════════════════════ */
  return (
    <div className="min-h-screen flex flex-col bg-[#f0f0f1]">
      {/* ═══ Inline Styles ═══ */}
      <style>{`
        @keyframes bvSlideIn { from { transform: translateY(10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .wp-scrollbar::-webkit-scrollbar { width: 6px; }
        .wp-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .wp-scrollbar::-webkit-scrollbar-thumb { background: #c3c4c7; border-radius: 3px; }
        .wp-scrollbar::-webkit-scrollbar-thumb:hover { background: #a7aaad; }
      `}</style>

      {/* ═══ Admin Bar ═══ */}
      <header className="fixed top-0 left-0 right-0 z-50 flex items-center justify-between px-4" style={{ height: 32, backgroundColor: '#1d2327', color: '#c3c4c7' }}>
        <div className="flex items-center gap-2 text-[13px]">
          <button className="lg:hidden p-0.5 hover:text-white" onClick={() => setSidebarOpen(!sidebarOpen)} aria-label="Toggle menu"><Menu size={18} /></button>
          <div className="flex items-center gap-2"><Shield size={14} style={{ color: '#D4AF37' }} /><span className="font-semibold text-white">BusinessVance</span></div>
        </div>
        <div className="text-[13px]"><span>Howdy, </span><span className="text-white font-medium">Admin</span></div>
      </header>

      {/* ═══ Sidebar Overlay (mobile) ═══ */}
      {sidebarOpen && <div className="fixed inset-0 bg-black/50 z-40 lg:hidden" onClick={() => setSidebarOpen(false)} />}

      {/* ═══ Sidebar ═══ */}
      <aside className={`fixed top-8 left-0 bottom-0 z-40 flex flex-col transition-transform duration-200 lg:translate-x-0 ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}`} style={{ width: 250, backgroundColor: '#1d2327' }}>
        <div className="flex items-center justify-center py-2 border-b border-white/10">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M1 1h18v18H1V1zm2 2v14h14V3H3zm2 2h10v2H5V5zm0 4h10v2H5V9zm0 4h7v2H5v-2z" fill="#c3c4c7" /></svg>
        </div>
        <nav className="flex-1 overflow-y-auto py-2" role="navigation">
          {SIDEBAR_MENU.map((item) => {
            const isActive = activeTab === item.id;
            const IconComp = item.icon;
            return (
              <button key={item.id} onClick={() => { setActiveTab(item.id); setSidebarOpen(false); }}
                className={`w-full flex items-center gap-3 px-4 py-2.5 text-left text-[14px] transition-colors border-none cursor-pointer ${isActive ? 'bg-[#2271b1] text-white' : 'text-[#c3c4c7] hover:bg-[#2c3338] hover:text-white'}`}
                aria-current={isActive ? 'page' : undefined}>
                <IconComp size={18} />{item.label}
              </button>
            );
          })}
        </nav>
        <button className="hidden lg:flex items-center justify-center py-3 border-t border-white/10 text-[#c3c4c7] hover:text-white transition-colors" style={{ height: 36 }} onClick={() => setSidebarOpen(false)} aria-label="Collapse menu">
          <ChevronLeft size={18} />
        </button>
      </aside>

      {/* ═══ Main Content ═══ */}
      <main className="flex-1 lg:ml-[250px] pt-12 min-h-screen">
        <div className="p-4 md:p-6 lg:p-8 max-w-[1200px]">

          {/* ══════════════════════════════════════
              DASHBOARD TAB
          ══════════════════════════════════════ */}
          {activeTab === 'dashboard' && (
            <section aria-label="Dashboard">
              <div className="flex items-center gap-3 mb-6">
                <Shield size={28} style={{ color: '#D4AF37' }} />
                <div>
                  <h1 className="text-2xl font-bold" style={{ color: '#002B5C' }}>BusinessVance Dashboard</h1>
                  <p className="text-sm text-[#646970]">Services Manager — Plugin Overview</p>
                </div>
              </div>

              {/* Stats */}
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
                {[
                  { label: 'Total Services', value: stats.totalServices, color: '#002B5C' },
                  { label: 'Total Plans', value: stats.totalPlans, color: '#2A9D8F' },
                  { label: 'Visible Items', value: stats.visibleItems, color: '#16a34a' },
                  { label: 'Hidden Items', value: stats.hiddenItems, color: '#dc2626' },
                  { label: 'Featured Items', value: stats.featuredItems, color: '#D4AF37' },
                  { label: 'Categories', value: stats.categories, color: '#7c3aed' },
                ].map((stat) => (
                  <Card key={stat.label} className="border border-[#c3c4c7]/30 hover:shadow-md transition-shadow">
                    <CardContent className="p-4 text-center">
                      <div className="text-3xl font-extrabold" style={{ color: stat.color }}>{stat.value}</div>
                      <div className="text-[11px] text-[#646970] uppercase tracking-wide mt-1 font-semibold">{stat.label}</div>
                    </CardContent>
                  </Card>
                ))}
              </div>

              {/* Shortcodes */}
              <div className="bg-white rounded-lg border border-[#c3c4c7]/30 p-6 mb-6">
                <h2 className="text-lg font-bold mb-4" style={{ color: '#002B5C' }}>Shortcode Reference</h2>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  {[
                    { code: '[businessvance_services]', desc: 'Full services page with both once-off services and subscription plans' },
                    { code: '[businessvance_onceoff]', desc: 'Displays only once-off services/price list' },
                    { code: '[businessvance_subscriptions]', desc: 'Displays only monthly subscription plans' },
                  ].map((sc) => (
                    <div key={sc.code} className="rounded-lg p-4 relative" style={{ backgroundColor: '#002B5C' }}>
                      <CopyBtn text={sc.code} />
                      <p className="text-[13px] text-white/80 mb-3 leading-snug pr-8">{sc.desc}</p>
                      <code className="block text-[13px] font-semibold px-3 py-2 rounded" style={{ color: '#D4AF37', backgroundColor: 'rgba(0,0,0,0.25)' }}>{sc.code}</code>
                    </div>
                  ))}
                </div>
              </div>

              {/* Quick Start */}
              <div className="bg-white rounded-lg border border-[#c3c4c7]/30 p-6">
                <h2 className="text-lg font-bold mb-4" style={{ color: '#002B5C' }}>Quick Start Guide</h2>
                <ol className="list-decimal list-inside space-y-2 text-sm text-[#50575e] leading-relaxed">
                  <li>Add your <strong>Categories</strong> to organize services and plans</li>
                  <li>Create <strong>Services</strong> (once-off reports and services) with pricing</li>
                  <li>Set up <strong>Subscription Plans</strong> with features and pricing</li>
                  <li>Configure <strong>Settings</strong> — branding, colors, currency, footer</li>
                  <li>Insert one of the shortcodes above on any WordPress page</li>
                  <li>Preview your page to see the fully styled services section</li>
                </ol>
              </div>
            </section>
          )}

          {/* ══════════════════════════════════════
              SERVICES TAB
          ══════════════════════════════════════ */}
          {activeTab === 'services' && (
            <section aria-label="Services">
              <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                <div className="flex items-center gap-3">
                  <FileText size={24} style={{ color: '#D4AF37' }} />
                  <div>
                    <h1 className="text-2xl font-bold" style={{ color: '#002B5C' }}>Services</h1>
                    <p className="text-sm text-[#646970]">Manage once-off services and price list</p>
                  </div>
                </div>
                <Button onClick={openNewService} className="font-semibold text-white shadow-sm hover:opacity-90 transition-opacity" style={{ backgroundColor: '#D4AF37' }}>
                  <Plus size={16} className="mr-1.5" /> Add New Service
                </Button>
              </div>

              <div className="bg-white rounded-lg border border-[#c3c4c7]/30 overflow-hidden">
                <div className="overflow-x-auto max-h-[600px] overflow-y-auto wp-scrollbar">
                  <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleServiceReorder}>
                    <Table>
                      <TableHeader>
                        <TableRow style={{ backgroundColor: '#f8f9fa' }}>
                          <TableHead className="w-12" />
                          <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Service</TableHead>
                          <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Category</TableHead>
                          <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Price</TableHead>
                          <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Button</TableHead>
                          <TableHead className="w-16 text-center text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Visible</TableHead>
                          <TableHead className="w-16 text-center text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Featured</TableHead>
                          <TableHead className="w-28 text-right text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Actions</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        <SortableContext items={services.map((s) => s.id)} strategy={verticalListSortingStrategy}>
                          {services.map((s) => (
                            <SortableRow key={s.id} id={s.id}>
                              <TableCell className="py-3 px-3">
                                <div className="flex items-start gap-2.5">
                                  <div className="flex-shrink-0 w-9 h-9 rounded-md flex items-center justify-center" style={{ backgroundColor: (s.category?.color || '#002B5C') + '18', color: s.category?.color || '#002B5C' }}>
                                    <BvIcon name={s.icon} size={18} />
                                  </div>
                                  <div className="min-w-0">
                                    <div className="font-medium text-sm text-[#1d2327]">{s.name}</div>
                                    <div className="text-xs text-[#646970] mt-0.5 truncate max-w-[220px]">{s.description}</div>
                                  </div>
                                </div>
                              </TableCell>
                              <TableCell className="py-3 px-3">
                                {s.category ? (
                                  <div className="flex items-center gap-1.5">
                                    <span className="w-2.5 h-2.5 rounded-full flex-shrink-0" style={{ backgroundColor: s.category.color }} />
                                    <span className="text-sm text-[#50575e]">{s.category.name}</span>
                                  </div>
                                ) : <span className="text-xs text-[#a7aaad]">—</span>}
                              </TableCell>
                              <TableCell className="py-3 px-3 text-sm font-semibold" style={{ color: '#002B5C' }}>
                                {settings.currency_symbol || 'R'}{s.price.toLocaleString('en-ZA')}
                              </TableCell>
                              <TableCell className="py-3 px-3">
                                <Badge variant="outline" className="text-xs capitalize">{s.buttonType}</Badge>
                              </TableCell>
                              <TableCell className="py-3 px-3 text-center">
                                <button onClick={() => toggleVisibility('service', s.id, s.visible)} className={`p-1 rounded transition-opacity ${s.visible ? 'text-[#2271b1] hover:opacity-70' : 'text-[#a7aaad] opacity-40 hover:opacity-60'}`} title={s.visible ? 'Visible' : 'Hidden'} aria-label="Toggle visibility">
                                  {s.visible ? <Eye size={18} /> : <EyeOff size={18} />}
                                </button>
                              </TableCell>
                              <TableCell className="py-3 px-3 text-center">
                                {s.featured && <span className="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold text-white" style={{ background: 'linear-gradient(135deg, #D4AF37, #F0D060)' }}>★</span>}
                              </TableCell>
                              <TableCell className="py-3 px-3 text-right">
                                <div className="flex items-center justify-end gap-1">
                                  <button onClick={() => openEditService(s)} className="p-1.5 rounded text-[#2271b1] hover:bg-[#f0f0f1] transition-colors" title="Edit"><Pencil size={15} /></button>
                                  <button onClick={() => setDeleteConfirm({ open: true, type: 'service', id: s.id, name: s.name })} className="p-1.5 rounded text-[#d63638] hover:bg-red-50 transition-colors" title="Delete"><Trash2 size={15} /></button>
                                </div>
                              </TableCell>
                            </SortableRow>
                          ))}
                        </SortableContext>
                      </TableBody>
                    </Table>
                  </DndContext>
                  {services.length === 0 && (
                    <div className="text-center py-16 text-[#a7aaad]">
                      <FileText size={40} className="mx-auto mb-3 opacity-40" />
                      <p className="font-medium">No services yet</p>
                      <p className="text-sm mt-1">Click &quot;Add New Service&quot; to get started</p>
                    </div>
                  )}
                </div>
              </div>
            </section>
          )}

          {/* ══════════════════════════════════════
              PLANS TAB
          ══════════════════════════════════════ */}
          {activeTab === 'plans' && (
            <section aria-label="Subscription Plans">
              <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                <div className="flex items-center gap-3">
                  <CreditCard size={24} style={{ color: '#D4AF37' }} />
                  <div>
                    <h1 className="text-2xl font-bold" style={{ color: '#002B5C' }}>Subscription Plans</h1>
                    <p className="text-sm text-[#646970]">Manage monthly subscription plan cards</p>
                  </div>
                </div>
                <Button onClick={openNewPlan} className="font-semibold text-white shadow-sm hover:opacity-90 transition-opacity" style={{ backgroundColor: '#D4AF37' }}>
                  <Plus size={16} className="mr-1.5" /> Add New Plan
                </Button>
              </div>

              <div className="bg-white rounded-lg border border-[#c3c4c7]/30 overflow-hidden">
                <div className="overflow-x-auto max-h-[600px] overflow-y-auto wp-scrollbar">
                  <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handlePlanReorder}>
                    <Table>
                      <TableHeader>
                        <TableRow style={{ backgroundColor: '#f8f9fa' }}>
                          <TableHead className="w-12" />
                          <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Plan</TableHead>
                          <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Color</TableHead>
                          <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Price</TableHead>
                          <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Button</TableHead>
                          <TableHead className="w-16 text-center text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Visible</TableHead>
                          <TableHead className="w-16 text-center text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Featured</TableHead>
                          <TableHead className="w-28 text-right text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Actions</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        <SortableContext items={plans.map((p) => p.id)} strategy={verticalListSortingStrategy}>
                          {plans.map((p) => (
                            <SortableRow key={p.id} id={p.id}>
                              <TableCell className="py-3 px-3">
                                <div>
                                  <div className="font-medium text-sm text-[#1d2327]">{p.name}</div>
                                  {p.subtitle && <div className="text-xs text-[#646970] mt-0.5">{p.subtitle}</div>}
                                </div>
                              </TableCell>
                              <TableCell className="py-3 px-3">
                                <div className="flex items-center gap-2">
                                  <span className="w-5 h-5 rounded" style={{ backgroundColor: p.color, border: '1px solid rgba(0,0,0,0.1)' }} />
                                  <span className="text-xs text-[#646970] font-mono">{p.color}</span>
                                </div>
                              </TableCell>
                              <TableCell className="py-3 px-3 text-sm font-semibold" style={{ color: '#002B5C' }}>
                                {settings.currency_symbol || 'R'}{p.price.toLocaleString('en-ZA')}/mo
                              </TableCell>
                              <TableCell className="py-3 px-3 text-sm text-[#50575e]">{p.buttonLabel || 'GET STARTED'}</TableCell>
                              <TableCell className="py-3 px-3 text-center">
                                <button onClick={() => toggleVisibility('plan', p.id, p.visible)} className={`p-1 rounded transition-opacity ${p.visible ? 'text-[#2271b1] hover:opacity-70' : 'text-[#a7aaad] opacity-40 hover:opacity-60'}`} title={p.visible ? 'Visible' : 'Hidden'} aria-label="Toggle visibility">
                                  {p.visible ? <Eye size={18} /> : <EyeOff size={18} />}
                                </button>
                              </TableCell>
                              <TableCell className="py-3 px-3 text-center">
                                {p.featured && <span className="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold text-white" style={{ background: 'linear-gradient(135deg, #D4AF37, #F0D060)' }}>★</span>}
                              </TableCell>
                              <TableCell className="py-3 px-3 text-right">
                                <div className="flex items-center justify-end gap-1">
                                  <button onClick={() => openEditPlan(p)} className="p-1.5 rounded text-[#2271b1] hover:bg-[#f0f0f1] transition-colors" title="Edit"><Pencil size={15} /></button>
                                  <button onClick={() => setDeleteConfirm({ open: true, type: 'plan', id: p.id, name: p.name })} className="p-1.5 rounded text-[#d63638] hover:bg-red-50 transition-colors" title="Delete"><Trash2 size={15} /></button>
                                </div>
                              </TableCell>
                            </SortableRow>
                          ))}
                        </SortableContext>
                      </TableBody>
                    </Table>
                  </DndContext>
                  {plans.length === 0 && (
                    <div className="text-center py-16 text-[#a7aaad]">
                      <CreditCard size={40} className="mx-auto mb-3 opacity-40" />
                      <p className="font-medium">No plans yet</p>
                      <p className="text-sm mt-1">Click &quot;Add New Plan&quot; to get started</p>
                    </div>
                  )}
                </div>
              </div>
            </section>
          )}

          {/* ══════════════════════════════════════
              CATEGORIES TAB
          ══════════════════════════════════════ */}
          {activeTab === 'categories' && (
            <section aria-label="Categories">
              <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                <div className="flex items-center gap-3">
                  <FolderOpen size={24} style={{ color: '#D4AF37' }} />
                  <div>
                    <h1 className="text-2xl font-bold" style={{ color: '#002B5C' }}>Categories</h1>
                    <p className="text-sm text-[#646970]">Organize services and plans</p>
                  </div>
                </div>
                <Button onClick={openNewCategory} className="font-semibold text-white shadow-sm hover:opacity-90 transition-opacity" style={{ backgroundColor: '#D4AF37' }}>
                  <Plus size={16} className="mr-1.5" /> Add New Category
                </Button>
              </div>

              <div className="bg-white rounded-lg border border-[#c3c4c7]/30 overflow-hidden">
                <div className="overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow style={{ backgroundColor: '#f8f9fa' }}>
                        <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Name</TableHead>
                        <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Slug</TableHead>
                        <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Color</TableHead>
                        <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e] text-center">Services</TableHead>
                        <TableHead className="text-[12px] uppercase tracking-wide font-semibold text-[#50575e] text-center">Plans</TableHead>
                        <TableHead className="w-28 text-right text-[12px] uppercase tracking-wide font-semibold text-[#50575e]">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {categories.map((c) => (
                        <TableRow key={c.id}>
                          <TableCell className="py-3 px-3 font-medium text-sm text-[#1d2327]">{c.name}</TableCell>
                          <TableCell className="py-3 px-3 text-sm text-[#646970] font-mono">{c.slug}</TableCell>
                          <TableCell className="py-3 px-3">
                            <div className="flex items-center gap-2">
                              <span className="w-5 h-5 rounded" style={{ backgroundColor: c.color, border: '1px solid rgba(0,0,0,0.1)' }} />
                              <span className="text-xs text-[#646970] font-mono">{c.color}</span>
                            </div>
                          </TableCell>
                          <TableCell className="py-3 px-3 text-center"><Badge variant="secondary" className="text-xs font-semibold">{catServiceCount(c.id)}</Badge></TableCell>
                          <TableCell className="py-3 px-3 text-center"><Badge variant="secondary" className="text-xs font-semibold">{catPlanCount(c.id)}</Badge></TableCell>
                          <TableCell className="py-3 px-3 text-right">
                            <div className="flex items-center justify-end gap-1">
                              <button onClick={() => openEditCategory(c)} className="p-1.5 rounded text-[#2271b1] hover:bg-[#f0f0f1] transition-colors" title="Edit"><Pencil size={15} /></button>
                              <button onClick={() => setDeleteConfirm({ open: true, type: 'category', id: c.id, name: c.name })} className="p-1.5 rounded text-[#d63638] hover:bg-red-50 transition-colors" title="Delete"><Trash2 size={15} /></button>
                            </div>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                  {categories.length === 0 && (
                    <div className="text-center py-16 text-[#a7aaad]">
                      <FolderOpen size={40} className="mx-auto mb-3 opacity-40" />
                      <p className="font-medium">No categories yet</p>
                      <p className="text-sm mt-1">Click &quot;Add New Category&quot; to get started</p>
                    </div>
                  )}
                </div>
              </div>
            </section>
          )}

          {/* ══════════════════════════════════════
              SETTINGS TAB
          ══════════════════════════════════════ */}
          {activeTab === 'settings' && (
            <section aria-label="Settings">
              <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
                <div className="flex items-center gap-3">
                  <Settings size={24} style={{ color: '#D4AF37' }} />
                  <div>
                    <h1 className="text-2xl font-bold" style={{ color: '#002B5C' }}>Plugin Settings</h1>
                    <p className="text-sm text-[#646970]">Configure branding, colors, content, and layout</p>
                  </div>
                </div>
                <Button onClick={saveSettings} disabled={saving} className="font-semibold text-white shadow-sm hover:opacity-90 transition-opacity" style={{ backgroundColor: '#D4AF37' }}>
                  {saving && <Loader2 size={16} className="mr-1.5 animate-spin" />}
                  {saving ? 'Saving...' : 'Save Changes'}
                </Button>
              </div>

              <div className="space-y-6">
                {/* Branding */}
                <SettingsCard title="Branding" description="Site-wide branding options">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormField label="Brand Name" value={settingsForm.brand_name || ''} onChange={(v) => setSettingsForm({ ...settingsForm, brand_name: v })} />
                    <FormField label="Brand Tagline" value={settingsForm.brand_tagline || ''} onChange={(v) => setSettingsForm({ ...settingsForm, brand_tagline: v })} />
                    <div className="md:col-span-2">
                      <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Brand Description</Label>
                      <Textarea value={settingsForm.brand_description || ''} onChange={(e) => setSettingsForm({ ...settingsForm, brand_description: e.target.value })} className="text-sm" rows={2} />
                    </div>
                    <div className="md:col-span-2">
                      <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Header Icon</Label>
                      <Select value={settingsForm.header_icon || 'shield'} onValueChange={(v) => setSettingsForm({ ...settingsForm, header_icon: v })}>
                        <SelectTrigger className="w-full text-sm"><SelectValue /></SelectTrigger>
                        <SelectContent>
                          {ICON_OPTIONS.map((icon) => (
                            <SelectItem key={icon} value={icon} className="capitalize">{icon.replace(/-/g, ' ')}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                </SettingsCard>

                {/* Colors */}
                <SettingsCard title="Colors" description="Theme colors used across the plugin">
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <ColorField label="Primary Color" value={settingsForm.color_primary || '#0A2647'} onChange={(v) => setSettingsForm({ ...settingsForm, color_primary: v })} />
                    <ColorField label="Primary Dark" value={settingsForm.color_primary_dark || '#071a33'} onChange={(v) => setSettingsForm({ ...settingsForm, color_primary_dark: v })} />
                    <ColorField label="Primary Light" value={settingsForm.color_primary_light || '#144272'} onChange={(v) => setSettingsForm({ ...settingsForm, color_primary_light: v })} />
                    <ColorField label="Accent Color" value={settingsForm.color_accent || '#F4A261'} onChange={(v) => setSettingsForm({ ...settingsForm, color_accent: v })} />
                    <ColorField label="Accent Alt" value={settingsForm.color_accent_alt || '#2A9D8F'} onChange={(v) => setSettingsForm({ ...settingsForm, color_accent_alt: v })} />
                    <ColorField label="Gold Accent" value={settingsForm.color_gold || '#D4AF37'} onChange={(v) => setSettingsForm({ ...settingsForm, color_gold: v })} />
                  </div>
                </SettingsCard>

                {/* Services Section */}
                <SettingsCard title="Services Section" description="Configure the once-off services section">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormField label="Section Title" value={settingsForm.services_section_title || ''} onChange={(v) => setSettingsForm({ ...settingsForm, services_section_title: v })} />
                    <FormField label="Section Subtitle" value={settingsForm.services_section_subtitle || ''} onChange={(v) => setSettingsForm({ ...settingsForm, services_section_subtitle: v })} />
                    <div className="flex items-center gap-3">
                      <Switch checked={settingsForm.show_services_section !== 'false'} onCheckedChange={(checked) => setSettingsForm({ ...settingsForm, show_services_section: String(checked) })} />
                      <Label className="text-sm text-[#50575e]">Show services section</Label>
                    </div>
                  </div>
                </SettingsCard>

                {/* Plans Section */}
                <SettingsCard title="Plans Section" description="Configure the subscription plans section">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormField label="Section Title" value={settingsForm.plans_section_title || ''} onChange={(v) => setSettingsForm({ ...settingsForm, plans_section_title: v })} />
                    <FormField label="Section Subtitle" value={settingsForm.plans_section_subtitle || ''} onChange={(v) => setSettingsForm({ ...settingsForm, plans_section_subtitle: v })} />
                    <div className="flex items-center gap-3">
                      <Switch checked={settingsForm.show_plans_section !== 'false'} onCheckedChange={(checked) => setSettingsForm({ ...settingsForm, show_plans_section: String(checked) })} />
                      <Label className="text-sm text-[#50575e]">Show plans section</Label>
                    </div>
                  </div>
                </SettingsCard>

                {/* Currency */}
                <SettingsCard title="Currency" description="Currency display settings">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormField label="Currency Symbol" value={settingsForm.currency_symbol || ''} onChange={(v) => setSettingsForm({ ...settingsForm, currency_symbol: v })} placeholder="R" />
                    <div>
                      <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Currency Position</Label>
                      <Select value={settingsForm.currency_position || 'before'} onValueChange={(v) => setSettingsForm({ ...settingsForm, currency_position: v })}>
                        <SelectTrigger className="w-full text-sm"><SelectValue /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="before">Before price (R 1,000)</SelectItem>
                          <SelectItem value="after">After price (1,000 R)</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                </SettingsCard>

                {/* Footer */}
                <SettingsCard title="Footer" description="Footer content and contact information">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormField label="Company Name" value={settingsForm.footer_company_name || ''} onChange={(v) => setSettingsForm({ ...settingsForm, footer_company_name: v })} />
                    <FormField label="Website" value={settingsForm.footer_website || ''} onChange={(v) => setSettingsForm({ ...settingsForm, footer_website: v })} />
                    <FormField label="Phone" value={settingsForm.footer_phone || ''} onChange={(v) => setSettingsForm({ ...settingsForm, footer_phone: v })} />
                    <FormField label="Email" value={settingsForm.footer_email || ''} onChange={(v) => setSettingsForm({ ...settingsForm, footer_email: v })} />
                    <div className="md:col-span-2">
                      <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Copyright Text</Label>
                      <Input value={settingsForm.footer_copyright || ''} onChange={(e) => setSettingsForm({ ...settingsForm, footer_copyright: e.target.value })} placeholder="Leave empty for auto-generated" className="text-sm" />
                    </div>
                  </div>
                </SettingsCard>

                {/* Trust Badges */}
                <SettingsCard title="Trust Badges" description="JSON array of trust badges displayed in the footer. Each badge has an icon name and text.">
                  <div>
                    <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Trust Badges JSON</Label>
                    <Textarea value={settingsForm.trust_badges || '[]'} onChange={(e) => setSettingsForm({ ...settingsForm, trust_badges: e.target.value })} className="text-sm font-mono" rows={6} placeholder='[{"icon": "shield-check", "text": "PROFESSIONAL QUALITY"}]' />
                    <p className="text-xs text-[#a7aaad] mt-1">Available icons: {ICON_OPTIONS.join(', ')}</p>
                  </div>
                </SettingsCard>

                {/* Layout */}
                <SettingsCard title="Layout & Display" description="Control the overall layout and visibility of elements">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormField label="Container Max Width" value={settingsForm.container_max_width || ''} onChange={(v) => setSettingsForm({ ...settingsForm, container_max_width: v })} placeholder="1200px" />
                    <div className="flex items-center gap-3">
                      <Switch checked={settingsForm.show_trust_badges !== 'false'} onCheckedChange={(checked) => setSettingsForm({ ...settingsForm, show_trust_badges: String(checked) })} />
                      <Label className="text-sm text-[#50575e]">Show trust badges</Label>
                    </div>
                    <div className="flex items-center gap-3">
                      <Switch checked={settingsForm.show_featured_badge !== 'false'} onCheckedChange={(checked) => setSettingsForm({ ...settingsForm, show_featured_badge: String(checked) })} />
                      <Label className="text-sm text-[#50575e]">Show featured badge on items</Label>
                    </div>
                  </div>
                </SettingsCard>
              </div>
            </section>
          )}
        </div>
      </main>

      {/* ═══ SERVICE MODAL ═══ */}
      <Dialog open={serviceModal.open} onOpenChange={(open) => setServiceModal({ open, data: null })}>
        <DialogContent className="sm:max-w-[700px] max-h-[90vh] overflow-y-auto p-0">
          <div className="sticky top-0 bg-[#f8f9fa] px-6 py-4 border-b border-[#e5e7eb] rounded-t-lg z-10">
            <DialogHeader className="flex flex-row items-center justify-between">
              <DialogTitle className="text-lg" style={{ color: '#002B5C' }}>{serviceModal.data ? 'Edit Service' : 'Add New Service'}</DialogTitle>
            </DialogHeader>
          </div>
          <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Name *</Label>
              <Input value={String(serviceForm.name || '')} onChange={(e) => setServiceForm({ ...serviceForm, name: e.target.value })} className="text-sm" />
            </div>
            <div className="md:col-span-2">
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Description</Label>
              <Textarea value={String(serviceForm.description || '')} onChange={(e) => setServiceForm({ ...serviceForm, description: e.target.value })} className="text-sm" rows={2} />
            </div>
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Price</Label>
              <Input type="number" step="0.01" value={String(serviceForm.price ?? '')} onChange={(e) => setServiceForm({ ...serviceForm, price: parseFloat(e.target.value) || 0 })} className="text-sm" />
            </div>
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Icon</Label>
              <Select value={String(serviceForm.icon || 'file-text')} onValueChange={(v) => setServiceForm({ ...serviceForm, icon: v })}>
                <SelectTrigger className="w-full text-sm"><SelectValue /></SelectTrigger>
                <SelectContent className="max-h-60">
                  {ICON_OPTIONS.map((icon) => (
                    <SelectItem key={icon} value={icon} className="capitalize">{icon.replace(/-/g, ' ')}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Button Label</Label>
              <Input value={String(serviceForm.buttonLabel || '')} onChange={(e) => setServiceForm({ ...serviceForm, buttonLabel: e.target.value })} className="text-sm" />
            </div>
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Button Type</Label>
              <Select value={String(serviceForm.buttonType || 'cart')} onValueChange={(v) => setServiceForm({ ...serviceForm, buttonType: v })}>
                <SelectTrigger className="w-full text-sm"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {BUTTON_TYPES.map((bt) => (
                    <SelectItem key={bt.value} value={bt.value}>{bt.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            {(serviceForm.buttonType === 'link' || serviceForm.buttonType === 'booking' || serviceForm.buttonType === 'quote') && (
              <div className="md:col-span-2">
                <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Button URL</Label>
                <Input value={String(serviceForm.buttonUrl || '')} onChange={(e) => setServiceForm({ ...serviceForm, buttonUrl: e.target.value })} className="text-sm" placeholder="https://..." />
              </div>
            )}
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">WooCommerce Product ID</Label>
              <Input value={String(serviceForm.woocommerceProductId || '')} onChange={(e) => setServiceForm({ ...serviceForm, woocommerceProductId: e.target.value })} className="text-sm" placeholder="Optional" />
            </div>
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Category</Label>
              <Select value={String(serviceForm.categoryId || '_none')} onValueChange={(v) => setServiceForm({ ...serviceForm, categoryId: v === '_none' ? null : v })}>
                <SelectTrigger className="w-full text-sm"><SelectValue placeholder="None" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="_none">— None —</SelectItem>
                  {categories.map((c) => (
                    <SelectItem key={c.id} value={c.id}>{c.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex items-center gap-3">
              <Switch checked={!!serviceForm.visible} onCheckedChange={(checked) => setServiceForm({ ...serviceForm, visible: checked })} />
              <Label className="text-sm text-[#50575e]">Visible</Label>
            </div>
            <div className="flex items-center gap-3">
              <Switch checked={!!serviceForm.featured} onCheckedChange={(checked) => setServiceForm({ ...serviceForm, featured: checked })} />
              <Label className="text-sm text-[#50575e]">Featured</Label>
            </div>
          </div>
          <div className="sticky bottom-0 bg-[#f8f9fa] px-6 py-4 border-t border-[#e5e7eb] rounded-b-lg">
            <DialogFooter className="gap-2">
              <Button variant="outline" onClick={() => setServiceModal({ open: false, data: null })}>Cancel</Button>
              <Button onClick={saveService} disabled={saving || !String(serviceForm.name || '').trim()} className="text-white" style={{ backgroundColor: '#D4AF37' }}>
                {saving && <Loader2 size={14} className="mr-1.5 animate-spin" />}
                {serviceModal.data ? 'Update Service' : 'Create Service'}
              </Button>
            </DialogFooter>
          </div>
        </DialogContent>
      </Dialog>

      {/* ═══ PLAN MODAL ═══ */}
      <Dialog open={planModal.open} onOpenChange={(open) => setPlanModal({ open, data: null })}>
        <DialogContent className="sm:max-w-[700px] max-h-[90vh] overflow-y-auto p-0">
          <div className="sticky top-0 bg-[#f8f9fa] px-6 py-4 border-b border-[#e5e7eb] rounded-t-lg z-10">
            <DialogHeader>
              <DialogTitle className="text-lg" style={{ color: '#002B5C' }}>{planModal.data ? 'Edit Plan' : 'Add New Plan'}</DialogTitle>
            </DialogHeader>
          </div>
          <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Plan Name *</Label>
              <Input value={String(planForm.name || '')} onChange={(e) => setPlanForm({ ...planForm, name: e.target.value })} className="text-sm" />
            </div>
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Subtitle</Label>
              <Input value={String(planForm.subtitle || '')} onChange={(e) => setPlanForm({ ...planForm, subtitle: e.target.value })} className="text-sm" />
            </div>
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Price (monthly)</Label>
              <Input type="number" step="0.01" value={String(planForm.price ?? '')} onChange={(e) => setPlanForm({ ...planForm, price: parseFloat(e.target.value) || 0 })} className="text-sm" />
            </div>
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Color</Label>
              <div className="flex items-center gap-2">
                <input type="color" value={String(planForm.color || '#002B5C')} onChange={(e) => setPlanForm({ ...planForm, color: e.target.value })} className="w-10 h-10 rounded border border-[#d1d5db] cursor-pointer p-0.5" />
                <Input value={String(planForm.color || '')} onChange={(e) => setPlanForm({ ...planForm, color: e.target.value })} className="text-sm font-mono flex-1" />
              </div>
            </div>
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Button Label</Label>
              <Input value={String(planForm.buttonLabel || '')} onChange={(e) => setPlanForm({ ...planForm, buttonLabel: e.target.value })} className="text-sm" />
            </div>
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">WooCommerce Product ID</Label>
              <Input value={String(planForm.woocommerceProductId || '')} onChange={(e) => setPlanForm({ ...planForm, woocommerceProductId: e.target.value })} className="text-sm" placeholder="Optional" />
            </div>
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Category</Label>
              <Select value={String(planForm.categoryId || '_none')} onValueChange={(v) => setPlanForm({ ...planForm, categoryId: v === '_none' ? null : v })}>
                <SelectTrigger className="w-full text-sm"><SelectValue placeholder="None" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="_none">— None —</SelectItem>
                  {categories.map((c) => (
                    <SelectItem key={c.id} value={c.id}>{c.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex items-center gap-3">
              <Switch checked={!!planForm.visible} onCheckedChange={(checked) => setPlanForm({ ...planForm, visible: checked })} />
              <Label className="text-sm text-[#50575e]">Visible</Label>
            </div>
            <div className="flex items-center gap-3">
              <Switch checked={!!planForm.featured} onCheckedChange={(checked) => setPlanForm({ ...planForm, featured: checked })} />
              <Label className="text-sm text-[#50575e]">Featured</Label>
            </div>
            {/* Features */}
            <div className="md:col-span-2">
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-2">Features</Label>
              <div className="space-y-2">
                {planFeatures.map((f, i) => (
                  <div key={i} className="flex items-center gap-2">
                    <Input value={f} onChange={(e) => { const updated = [...planFeatures]; updated[i] = e.target.value; setPlanFeatures(updated); }} className="text-sm flex-1" placeholder="Feature text..." />
                    <button type="button" onClick={() => setPlanFeatures(planFeatures.filter((_, idx) => idx !== i))} className="p-2 rounded text-[#d63638] hover:bg-red-50 transition-colors flex-shrink-0" aria-label="Remove feature"><X size={16} /></button>
                  </div>
                ))}
                <button type="button" onClick={() => setPlanFeatures([...planFeatures, ''])} className="flex items-center gap-1 text-sm text-[#2271b1] hover:text-[#135e96] transition-colors font-medium">
                  <Plus size={14} /> Add Feature
                </button>
              </div>
            </div>
          </div>
          <div className="sticky bottom-0 bg-[#f8f9fa] px-6 py-4 border-t border-[#e5e7eb] rounded-b-lg">
            <DialogFooter className="gap-2">
              <Button variant="outline" onClick={() => setPlanModal({ open: false, data: null })}>Cancel</Button>
              <Button onClick={savePlan} disabled={saving || !String(planForm.name || '').trim()} className="text-white" style={{ backgroundColor: '#D4AF37' }}>
                {saving && <Loader2 size={14} className="mr-1.5 animate-spin" />}
                {planModal.data ? 'Update Plan' : 'Create Plan'}
              </Button>
            </DialogFooter>
          </div>
        </DialogContent>
      </Dialog>

      {/* ═══ CATEGORY MODAL ═══ */}
      <Dialog open={categoryModal.open} onOpenChange={(open) => setCategoryModal({ open, data: null })}>
        <DialogContent className="sm:max-w-[480px]">
          <DialogHeader>
            <DialogTitle className="text-lg" style={{ color: '#002B5C' }}>{categoryModal.data ? 'Edit Category' : 'Add New Category'}</DialogTitle>
            <DialogDescription>{categoryModal.data ? 'Update category details' : 'Create a new category'}</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Name *</Label>
              <Input value={String(categoryForm.name || '')} onChange={(e) => {
                const name = e.target.value;
                const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
                setCategoryForm({ ...categoryForm, name, slug });
              }} className="text-sm" />
            </div>
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Slug</Label>
              <Input value={String(categoryForm.slug || '')} onChange={(e) => setCategoryForm({ ...categoryForm, slug: e.target.value })} className="text-sm font-mono" />
            </div>
            <div>
              <Label className="block text-[13px] font-semibold text-[#1d2327] mb-1.5">Color</Label>
              <div className="flex items-center gap-2">
                <input type="color" value={String(categoryForm.color || '#002B5C')} onChange={(e) => setCategoryForm({ ...categoryForm, color: e.target.value })} className="w-10 h-10 rounded border border-[#d1d5db] cursor-pointer p-0.5" />
                <Input value={String(categoryForm.color || '')} onChange={(e) => setCategoryForm({ ...categoryForm, color: e.target.value })} className="text-sm font-mono flex-1" />
              </div>
            </div>
          </div>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setCategoryModal({ open: false, data: null })}>Cancel</Button>
            <Button onClick={saveCategory} disabled={saving || !String(categoryForm.name || '').trim()} className="text-white" style={{ backgroundColor: '#D4AF37' }}>
              {saving && <Loader2 size={14} className="mr-1.5 animate-spin" />}
              {categoryModal.data ? 'Update' : 'Create'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* ═══ DELETE CONFIRMATION ═══ */}
      <Dialog open={deleteConfirm.open} onOpenChange={(open) => setDeleteConfirm({ ...deleteConfirm, open })}>
        <DialogContent className="sm:max-w-[420px]">
          <DialogHeader>
            <DialogTitle className="text-lg text-[#d63638]">Delete {deleteConfirm.type}?</DialogTitle>
            <DialogDescription>
              Are you sure you want to permanently delete <strong>&quot;{deleteConfirm.name}&quot;</strong>? This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setDeleteConfirm({ ...deleteConfirm, open: false })}>Cancel</Button>
            <Button onClick={deleteItem} disabled={saving} className="bg-[#d63638] hover:bg-[#b32d2e] text-white">
              {saving && <Loader2 size={14} className="mr-1.5 animate-spin" />}
              Delete
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* ═══ TOAST ═══ */}
      <Toast message={toast} />
    </div>
  );
}