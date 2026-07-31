'use client';

import React, { useState, useEffect, useCallback } from 'react';
import {
  CheckCircle2, Circle, Upload, FileText, Shield, Clock,
  ChevronDown, ChevronRight, Lock, AlertCircle, User, Building2,
  Mail, Phone, Briefcase, Save, ArrowRight, ArrowLeft, LogOut,
  Menu, X, Bell, ChevronDown as ChevronDownIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Separator } from '@/components/ui/separator';
import { toast } from 'sonner';

/* ═══════════════════════════════════════════════════
   Client Portal – Standalone Page
   
   This is the client-facing portal accessed after
   purchasing a service. It is NOT part of the admin.
   ═══════════════════════════════════════════════════ */

/* ─── Types ──────────────────────────────────────── */

interface ClientProject {
  id: string;
  projectNumber: string;
  services: { name: string; status: string; progress: number; color: string }[];
  status: string;
  progressPercent: number;
}

/* ─── Page Component ─────────────────────────────── */

export default function ClientPortalPage() {
  const [activeProjectId, setActiveProjectId] = useState<string | null>(null);
  const [activeSection, setActiveSection] = useState<string | null>('business-overview');
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [formData, setFormData] = useState<Record<string, string>>({
    describeBusiness: '',
    businessStage: 'startup',
    yearsOperating: '',
    problemSolved: '',
  });
  const [projects, setProjects] = useState<ClientProject[]>([]);
  const [loading, setLoading] = useState(true);

  // Fetch client projects
  useEffect(() => {
    async function load() {
      try {
        const res = await fetch('/api/projects');
        const json = await res.json();
        const list: ClientProject[] = (json.projects || []).map((p: Record<string, unknown>) => ({
          id: p.id as string,
          projectNumber: p.projectNumber as string,
          services: [],
          status: p.status as string,
          progressPercent: p.progressPercent as number,
        }));
        setProjects(list);
        if (list.length > 0 && !activeProjectId) {
          setActiveProjectId(list[0].id);
        } else if (list.length === 0) {
          // No real projects yet – use demo data for preview
          const demo: ClientProject[] = [{
            id: 'demo',
            projectNumber: 'BV-2026-000001',
            services: [
              { name: 'Business Plan', status: 'in-progress', progress: 60, color: '#F4A261' },
              { name: 'Market Research Report', status: 'questionnaire-pending', progress: 25, color: '#f59e0b' },
            ],
            status: 'awaiting-questionnaire',
            progressPercent: 42,
          }];
          setProjects(demo);
          if (!activeProjectId) setActiveProjectId('demo');
        }
      } catch {
        // Use demo data if no real projects
        setProjects([{
          id: 'demo',
          projectNumber: 'BV-2026-000001',
          services: [
            { name: 'Business Plan', status: 'in-progress', progress: 60, color: '#F4A261' },
            { name: 'Market Research Report', status: 'questionnaire-pending', progress: 25, color: '#f59e0b' },
          ],
          status: 'awaiting-questionnaire',
          progressPercent: 42,
        }]);
        if (!activeProjectId) setActiveProjectId('demo');
      } finally {
        setLoading(false);
      }
    }
    load();
  }, []);

  const activeProject = projects.find(p => p.id === activeProjectId);

  /* ─── Demo data for the active project ──────────── */

  const checklist = [
    { label: 'Payment Received', done: true },
    { label: 'Confidentiality Agreement Signed', done: true },
    { label: 'Questionnaire Complete', done: false, sub: '60%' },
    { label: 'Documents Uploaded', done: false, sub: '1 of 4' },
    { label: 'Report In Progress', done: false },
    { label: 'Delivered', done: false },
  ];

  const sections = [
    { id: 'business-profile', title: 'Business Profile', status: 'completed' as const, questionCount: 4, completedCount: 4 },
    { id: 'business-overview', title: 'Business Overview', status: 'in-progress' as const, questionCount: 4, completedCount: 1 },
    { id: 'target-market', title: 'Target Market', status: 'not-started' as const, questionCount: 5, completedCount: 0 },
    { id: 'competition', title: 'Competition', status: 'not-started' as const, questionCount: 3, completedCount: 0 },
    { id: 'marketing', title: 'Marketing Strategy', status: 'not-started' as const, questionCount: 4, completedCount: 0 },
    { id: 'financials', title: 'Financials', status: 'not-started' as const, questionCount: 5, completedCount: 0 },
  ];

  const completedAnswers: Record<string, string> = {
    companyName: 'ABC Engineering (Pty) Ltd',
    email: 'stefan@abceng.co.za',
    phone: '082 377 7490',
    industry: 'Engineering & Construction',
  };

  const requiredDocs = [
    { name: 'Company Registration', required: true, uploaded: true, fileName: 'ABC_Eng_CIPC_Registration.pdf', fileSize: '245 KB', date: '2026-07-20' },
    { name: 'Company Logo', required: false, uploaded: true, fileName: 'abc-engineering-logo.png', fileSize: '89 KB', date: '2026-07-20' },
    { name: 'Financial Statements', required: true, uploaded: false },
    { name: 'Identity Document', required: true, uploaded: false },
  ];

  const getStatusStyle = (status: string) => {
    switch (status) {
      case 'completed': return { bg: '#dcfce7', text: '#166534', border: '#86efac' };
      case 'in-progress': return { bg: '#fff7ed', text: '#9a3412', border: '#fdba74' };
      default: return { bg: '#f9fafb', text: '#6b7280', border: '#e5e7eb' };
    }
  };

  const getServiceBadge = (status: string) => {
    switch (status) {
      case 'in-progress': return { label: 'In Progress', bg: '#fff7ed', color: '#9a3412' };
      case 'questionnaire-pending': return { label: 'Questionnaire Pending', bg: '#fefce8', color: '#854d0e' };
      default: return { label: status, bg: '#f9fafb', color: '#6b7280' };
    }
  };

  const questionnaireProgress = Math.round(
    (1 + (formData.describeBusiness ? 1 : 0) + (formData.yearsOperating ? 1 : 0) + (formData.problemSolved ? 1 : 0)) / 4 * 100
  );

  /* ─── Render ─────────────────────────────────────── */

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center" style={{ backgroundColor: '#f8fafc' }}>
        <div className="text-center">
          <div className="inline-block w-8 h-8 border-3 border-t-transparent rounded-full animate-spin mb-3" style={{ borderColor: '#002B5C', borderTopColor: 'transparent' }} />
          <p style={{ color: '#6b7280', fontSize: 14 }}>Loading your projects…</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex flex-col" style={{ backgroundColor: '#f8fafc' }}>
      {/* ═══ Top Navigation Bar ═══ */}
      <header style={{ backgroundColor: '#fff', borderBottom: '1px solid #e2e8f0', position: 'sticky', top: 0, zIndex: 40 }}>
        <div style={{ maxWidth: 1200, margin: '0 auto', padding: '0 16px', height: 56, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          {/* Logo */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <div style={{ width: 34, height: 34, borderRadius: 8, backgroundColor: '#002B5C', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <Shield size={18} style={{ color: '#D4AF37' }} />
            </div>
            <div>
              <span style={{ fontWeight: 800, fontSize: 16, color: '#002B5C', letterSpacing: 0.5 }}>BUSINESSVANCE</span>
              <span style={{ fontSize: 10, color: '#9ca3af', marginLeft: 8, letterSpacing: 1 }}>CLIENT PORTAL</span>
            </div>
          </div>

          {/* Right side */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <button
              onClick={() => toast.info('No new notifications')}
              style={{ position: 'relative', background: 'none', border: 'none', cursor: 'pointer', padding: 6 }}
              aria-label="Notifications"
            >
              <Bell size={18} style={{ color: '#6b7280' }} />
            </button>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '4px 12px 4px 4px', backgroundColor: '#f8fafc', borderRadius: 24, border: '1px solid #e5e7eb' }}>
              <div style={{ width: 28, height: 28, borderRadius: '50%', backgroundColor: '#002B5C', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <User size={14} style={{ color: '#D4AF37' }} />
              </div>
              <span style={{ fontSize: 13, fontWeight: 600, color: '#374151' }}>Stefan M.</span>
              <ChevronDownIcon size={14} style={{ color: '#9ca3af' }} />
            </div>
            {/* Mobile menu toggle */}
            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              style={{ display: 'none', background: 'none', border: 'none', cursor: 'pointer', padding: 6 }}
              className="lg:hidden"
              aria-label="Toggle menu"
            >
              {mobileMenuOpen ? <X size={20} /> : <Menu size={20} />}
            </button>
          </div>
        </div>

        {/* Mobile dropdown */}
        {mobileMenuOpen && (
          <div style={{ borderTop: '1px solid #e5e7eb', padding: '12px 16px', display: 'flex', flexDirection: 'column', gap: 8 }} className="lg:hidden">
            {projects.map(p => (
              <button
                key={p.id}
                onClick={() => { setActiveProjectId(p.id); setMobileMenuOpen(false); }}
                style={{ textAlign: 'left', padding: '8px 12px', border: 'none', borderRadius: 6, cursor: 'pointer', fontSize: 13, fontWeight: 600, backgroundColor: activeProjectId === p.id ? '#f0f4ff' : 'transparent', color: activeProjectId === p.id ? '#002B5C' : '#374151' }}
              >
                {p.projectNumber}
              </button>
            ))}
          </div>
        )}
      </header>

      {/* ═══ Main Content ═══ */}
      <main className="flex-1">
        {/* Project Selector (if multiple projects) */}
        {projects.length > 1 && (
          <div style={{ backgroundColor: '#fff', borderBottom: '1px solid #e5e7eb' }}>
            <div style={{ maxWidth: 1200, margin: '0 auto', padding: '8px 16px', display: 'flex', alignItems: 'center', gap: 8, overflowX: 'auto' }}>
              <span style={{ fontSize: 12, color: '#6b7280', fontWeight: 600, whiteSpace: 'nowrap' }}>Projects:</span>
              {projects.map(p => (
                <button
                  key={p.id}
                  onClick={() => setActiveProjectId(p.id)}
                  style={{
                    padding: '4px 14px', borderRadius: 20, border: '1px solid',
                    borderColor: activeProjectId === p.id ? '#002B5C' : '#e5e7eb',
                    backgroundColor: activeProjectId === p.id ? '#002B5C' : '#fff',
                    color: activeProjectId === p.id ? '#fff' : '#374151',
                    fontSize: 12, fontWeight: 600, cursor: 'pointer', whiteSpace: 'nowrap',
                    transition: 'all 0.15s',
                  }}
                >
                  {p.projectNumber}
                </button>
              ))}
            </div>
          </div>
        )}

        {activeProject && (
          <>
            {/* ── Welcome Header ── */}
            <div style={{
              background: 'linear-gradient(135deg, #002B5C 0%, #144272 100%)',
              padding: '32px 16px', color: '#fff',
            }}>
              <div style={{ maxWidth: 1000, margin: '0 auto' }}>
                <p style={{ fontSize: 13, opacity: 0.7, letterSpacing: 1, textTransform: 'uppercase', marginBottom: 4 }}>
                  Project #{activeProject.projectNumber}
                </p>
                <h1 style={{ fontSize: 28, fontWeight: 800, margin: '0 0 4px 0' }}>Welcome back, Stefan</h1>
                <p style={{ fontSize: 14, opacity: 0.8, margin: 0 }}>
                  We&apos;re working on your business reports. Complete the steps below to help us deliver the best results.
                </p>
              </div>
            </div>

            <div style={{ maxWidth: 1000, margin: '0 auto', padding: '24px 16px 60px' }}>
              {/* ── Progress Overview ── */}
              <Card style={{ marginBottom: 24, border: '1px solid #e2e8f0', boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <CardHeader style={{ paddingBottom: 12 }}>
                  <CardTitle style={{ fontSize: 16, color: '#002B5C', display: 'flex', alignItems: 'center', gap: 8 }}>
                    <CircularProgress percent={activeProject.progressPercent || 42} />
                    Project Progress
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: 8 }}>
                    {checklist.map((item, i) => (
                      <div key={i} style={{
                        display: 'flex', alignItems: 'center', gap: 10,
                        padding: '8px 12px', borderRadius: 8,
                        backgroundColor: item.done ? '#f0fdf4' : '#f9fafb', fontSize: 13,
                      }}>
                        {item.done ? (
                          <CheckCircle2 size={18} style={{ color: '#22c55e', flexShrink: 0 }} />
                        ) : (
                          <Circle size={18} style={{ color: '#d1d5db', flexShrink: 0 }} />
                        )}
                        <span style={{ color: item.done ? '#166534' : '#6b7280', flex: 1 }}>{item.label}</span>
                        {item.sub && !item.done && (
                          <Badge style={{ fontSize: 11, padding: '2px 8px', backgroundColor: '#fef3c7', color: '#92400e', border: 'none' }}>{item.sub}</Badge>
                        )}
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>

              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(340px, 1fr))', gap: 24, marginBottom: 24 }}>
                {/* ── My Services ── */}
                <Card style={{ border: '1px solid #e2e8f0', boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                  <CardHeader style={{ paddingBottom: 12 }}>
                    <CardTitle style={{ fontSize: 16, color: '#002B5C', display: 'flex', alignItems: 'center', gap: 8 }}>
                      <Briefcase size={18} style={{ color: '#2A9D8F' }} />
                      My Services
                    </CardTitle>
                  </CardHeader>
                  <CardContent style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                    {activeProject.services.length > 0 ? activeProject.services.map((svc, i) => {
                      const badge = getServiceBadge(svc.status);
                      return (
                        <div key={i} style={{ padding: 16, borderRadius: 10, border: '1px solid #e5e7eb', backgroundColor: '#fff' }}>
                          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
                            <span style={{ fontWeight: 700, color: '#002B5C', fontSize: 14 }}>{svc.name}</span>
                            <Badge style={{ fontSize: 11, padding: '2px 10px', backgroundColor: badge.bg, color: badge.color, border: 'none' }}>{badge.label}</Badge>
                          </div>
                          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                            <div style={{ flex: 1, height: 8, backgroundColor: '#e5e7eb', borderRadius: 4, overflow: 'hidden' }}>
                              <div style={{ width: `${svc.progress}%`, height: '100%', backgroundColor: svc.color, borderRadius: 4, transition: 'width 0.3s' }} />
                            </div>
                            <span style={{ fontSize: 12, fontWeight: 700, color: '#374151', minWidth: 36, textAlign: 'right' }}>{svc.progress}%</span>
                          </div>
                        </div>
                      );
                    }) : (
                      <p style={{ fontSize: 13, color: '#9ca3af', textAlign: 'center', padding: '20px 0' }}>Service details loading…</p>
                    )}
                  </CardContent>
                </Card>

                {/* ── Agreement Status ── */}
                <Card style={{ border: '1px solid #e2e8f0', boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                  <CardHeader style={{ paddingBottom: 12 }}>
                    <CardTitle style={{ fontSize: 16, color: '#002B5C', display: 'flex', alignItems: 'center', gap: 8 }}>
                      <Shield size={18} style={{ color: '#22c55e' }} />
                      Confidentiality Agreement
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div style={{ padding: 16, borderRadius: 10, backgroundColor: '#f0fdf4', border: '1px solid #86efac', marginBottom: 16 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
                        <CheckCircle2 size={20} style={{ color: '#22c55e' }} />
                        <span style={{ fontWeight: 700, color: '#166534', fontSize: 14 }}>Signed</span>
                      </div>
                      <div style={{ fontSize: 13, color: '#374151', lineHeight: 1.6 }}>
                        <p style={{ margin: '0 0 4px' }}><strong>Document:</strong> Confidentiality Undertaking v1.0</p>
                        <p style={{ margin: '0 0 4px' }}><strong>Signed by:</strong> Stefan M</p>
                        <p style={{ margin: '0 0 4px' }}><strong>Date:</strong> 20 July 2026, 14:32</p>
                        <p style={{ margin: 0, fontSize: 12, color: '#6b7280' }}><strong>IP:</strong> 196.xxx.xxx.xxx</p>
                      </div>
                    </div>
                    <Button
                      variant="outline"
                      style={{ width: '100%', fontSize: 13 }}
                      onClick={() => toast.success('Agreement PDF would open in a new tab')}
                    >
                      <FileText size={14} style={{ marginRight: 6 }} />
                      View Signed Agreement
                    </Button>
                  </CardContent>
                </Card>
              </div>

              {/* ── Questionnaire Section ── */}
              <Card style={{ marginBottom: 24, border: '1px solid #e2e8f0', boxShadow: '0 1px 3px rgba(0,0,0,0.06)' }}>
                <CardHeader style={{ paddingBottom: 12 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
                    <CardTitle style={{ fontSize: 16, color: '#002B5C', display: 'flex', alignItems: 'center', gap: 8 }}>
                      <FileText size={18} style={{ color: '#D4AF37' }} />
                      Questionnaire — Business Plan
                    </CardTitle>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                      <span style={{ fontSize: 12, color: '#6b7280' }}>{questionnaireProgress}% complete</span>
                      <div style={{ width: 120, height: 8, backgroundColor: '#e5e7eb', borderRadius: 4, overflow: 'hidden' }}>
                        <div style={{ width: `${questionnaireProgress}%`, height: '100%', backgroundColor: '#2A9D8F', borderRadius: 4, transition: 'width 0.3s' }} />
                      </div>
                    </div>
                  </div>
                </CardHeader>
                <CardContent>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                    {sections.map((sec, idx) => {
                      const st = getStatusStyle(sec.status);
                      const isOpen = activeSection === sec.id;
                      const isLocked = sec.status === 'not-started';
                      return (
                        <div key={sec.id} style={{ borderRadius: 8, overflow: 'hidden', border: `1px solid ${st.border}` }}>
                          <button
                            onClick={() => !isLocked && setActiveSection(isOpen ? null : sec.id)}
                            style={{
                              display: 'flex', alignItems: 'center', gap: 12,
                              width: '100%', padding: '14px 16px', border: 'none', cursor: isLocked ? 'not-allowed' : 'pointer',
                              backgroundColor: isOpen ? '#fff' : st.bg,
                              transition: 'background 0.15s', textAlign: 'left',
                            }}
                          >
                            <span style={{
                              width: 28, height: 28, borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center',
                              backgroundColor: sec.status === 'completed' ? '#22c55e' : sec.status === 'in-progress' ? '#F4A261' : '#e5e7eb',
                              color: '#fff', fontSize: 12, fontWeight: 700, flexShrink: 0,
                            }}>{idx + 1}</span>
                            <span style={{ flex: 1, fontWeight: 600, fontSize: 14, color: sec.status === 'completed' ? '#166534' : '#1f2937' }}>{sec.title}</span>
                            {sec.status === 'completed' && <CheckCircle2 size={18} style={{ color: '#22c55e' }} />}
                            {sec.status === 'in-progress' && <Clock size={18} style={{ color: '#F4A261' }} />}
                            {isLocked && <Lock size={16} style={{ color: '#9ca3af' }} />}
                            {!isLocked && (isOpen ? <ChevronDown size={18} style={{ color: '#6b7280' }} /> : <ChevronRight size={18} style={{ color: '#6b7280' }} />)}
                            <span style={{ fontSize: 12, color: '#9ca3af' }}>{sec.completedCount}/{sec.questionCount}</span>
                          </button>

                          {isOpen && (
                            <div style={{ padding: '16px 16px 16px 56px', backgroundColor: '#fff', borderTop: `1px solid ${st.border}` }}>
                              {sec.id === 'business-profile' && (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                                  {Object.entries(completedAnswers).map(([key, val]) => (
                                    <div key={key} style={{ display: 'flex', alignItems: 'flex-start', gap: 8 }}>
                                      <CheckCircle2 size={16} style={{ color: '#22c55e', marginTop: 3, flexShrink: 0 }} />
                                      <div>
                                        <span style={{ fontSize: 12, color: '#6b7280', textTransform: 'capitalize' }}>{key.replace(/([A-Z])/g, ' $1').trim()}</span>
                                        <p style={{ margin: 0, fontSize: 14, fontWeight: 600, color: '#1f2937' }}>{val}</p>
                                      </div>
                                    </div>
                                  ))}
                                </div>
                              )}

                              {sec.id === 'business-overview' && (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                                  <div>
                                    <label style={{ fontSize: 13, fontWeight: 600, color: '#374151', display: 'flex', alignItems: 'center', gap: 4, marginBottom: 4 }}>
                                      Describe your business <span style={{ color: '#ef4444' }}>*</span>
                                    </label>
                                    <Textarea
                                      value={formData.describeBusiness}
                                      onChange={(e) => setFormData({ ...formData, describeBusiness: e.target.value })}
                                      placeholder="Tell us about your business, what you do, and your products/services..."
                                      style={{ minHeight: 80, fontSize: 13 }}
                                    />
                                  </div>
                                  <div>
                                    <label style={{ fontSize: 13, fontWeight: 600, color: '#374151', display: 'flex', alignItems: 'center', gap: 4, marginBottom: 4 }}>
                                      Business stage <span style={{ color: '#ef4444' }}>*</span>
                                    </label>
                                    <select
                                      value={formData.businessStage}
                                      onChange={(e) => setFormData({ ...formData, businessStage: e.target.value })}
                                      style={{ width: '100%', padding: '8px 12px', border: '1px solid #d1d5db', borderRadius: 6, fontSize: 13, backgroundColor: '#fff' }}
                                    >
                                      <option value="startup">Startup / Pre-launch</option>
                                      <option value="early">Early Stage (0-2 years)</option>
                                      <option value="growth">Growth Stage (2-5 years)</option>
                                      <option value="established">Established (5+ years)</option>
                                    </select>
                                  </div>
                                  <div>
                                    <label style={{ fontSize: 13, fontWeight: 600, color: '#374151', marginBottom: 4, display: 'block' }}>
                                      Years operating
                                    </label>
                                    <Input
                                      type="number"
                                      value={formData.yearsOperating}
                                      onChange={(e) => setFormData({ ...formData, yearsOperating: e.target.value })}
                                      placeholder="e.g., 3"
                                      style={{ fontSize: 13 }}
                                    />
                                  </div>
                                  <div>
                                    <label style={{ fontSize: 13, fontWeight: 600, color: '#374151', marginBottom: 4, display: 'block' }}>
                                      What problem does your business solve?
                                    </label>
                                    <Textarea
                                      value={formData.problemSolved}
                                      onChange={(e) => setFormData({ ...formData, problemSolved: e.target.value })}
                                      placeholder="Describe the primary problem your business solves for customers..."
                                      style={{ minHeight: 70, fontSize: 13 }}
                                    />
                                  </div>
                                  <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end', paddingTop: 8 }}>
                                    <Button
                                      style={{ backgroundColor: '#D4AF37', color: '#fff', fontSize: 13 }}
                                      onClick={() => toast.success('Progress saved!')}
                                    >
                                      <Save size={14} style={{ marginRight: 6 }} />
                                      Save Progress
                                    </Button>
                                    <Button
                                      style={{ backgroundColor: '#002B5C', color: '#fff', fontSize: 13 }}
                                      onClick={() => toast.success('Section completed!')}
                                    >
                                      Complete Section <ArrowRight size={14} style={{ marginLeft: 6 }} />
                                    </Button>
                                  </div>
                                </div>
                              )}

                              {isLocked && (
                                <p style={{ fontSize: 13, color: '#9ca3af', fontStyle: 'italic', margin: 0 }}>
                                  Complete the previous sections to unlock this section.
                                </p>
                              )}
                            </div>
                          )}
                        </div>
                      );
                    })}
                  </div>
                </CardContent>
              </Card>

              {/* ── Document Upload Section ── */}
              <Card style={{ border: '1px solid #e2e8f0', boxShadow: '0 1px 3px rgba(0,0,0,0.06)', marginBottom: 24 }}>
                <CardHeader style={{ paddingBottom: 12 }}>
                  <CardTitle style={{ fontSize: 16, color: '#002B5C', display: 'flex', alignItems: 'center', gap: 8 }}>
                    <Upload size={18} style={{ color: '#2A9D8F' }} />
                    Required Documents
                    <Badge style={{ fontSize: 11, padding: '2px 8px', backgroundColor: '#fef3c7', color: '#92400e', border: 'none', marginLeft: 4 }}>
                      1 of 4 uploaded
                    </Badge>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: 12 }}>
                    {requiredDocs.map((doc, i) => (
                      <div key={i} style={{
                        padding: 16, borderRadius: 10, border: `1px solid ${doc.uploaded ? '#86efac' : '#e5e7eb'}`,
                        backgroundColor: doc.uploaded ? '#f0fdf4' : '#fff',
                      }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 8 }}>
                          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            {doc.uploaded ? (
                              <CheckCircle2 size={18} style={{ color: '#22c55e' }} />
                            ) : (
                              <AlertCircle size={18} style={{ color: doc.required ? '#f59e0b' : '#9ca3af' }} />
                            )}
                            <span style={{ fontWeight: 600, fontSize: 14, color: '#1f2937' }}>{doc.name}</span>
                          </div>
                          {doc.required && <Badge style={{ fontSize: 10, padding: '1px 6px', backgroundColor: '#fef3c7', color: '#92400e', border: 'none' }}>Required</Badge>}
                        </div>
                        {doc.uploaded && doc.fileName ? (
                          <div style={{ fontSize: 12, color: '#6b7280', marginLeft: 26 }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
                              <FileText size={12} /> {doc.fileName}
                            </div>
                            <span>{doc.fileSize} · {doc.date}</span>
                          </div>
                        ) : (
                          <Button
                            variant="outline"
                            size="sm"
                            style={{ marginLeft: 26, fontSize: 12, borderColor: '#002B5C', color: '#002B5C' }}
                            onClick={() => toast.success('File upload would open')}
                          >
                            <Upload size={12} style={{ marginRight: 4 }} />
                            Upload
                          </Button>
                        )}
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            </div>
          </>
        )}
      </main>

      {/* ═══ Footer ═══ */}
      <footer style={{
        backgroundColor: '#002B5C', color: '#fff', padding: '24px 16px', marginTop: 'auto',
      }}>
        <div style={{ maxWidth: 1200, margin: '0 auto', display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: 'center', gap: 16 }}>
          <div>
            <span style={{ fontWeight: 800, fontSize: 14, letterSpacing: 0.5 }}>BUSINESSVANCE</span>
            <span style={{ fontSize: 12, opacity: 0.6, marginLeft: 12 }}>Professional Business Reports & Advisory</span>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 16, fontSize: 12, opacity: 0.7 }}>
            <span style={{ display: 'flex', alignItems: 'center', gap: 4 }}><Mail size={12} /> info@businessvance.co.za</span>
            <span style={{ display: 'flex', alignItems: 'center', gap: 4 }}><Phone size={12} /> 082 377 7490</span>
          </div>
        </div>
      </footer>
    </div>
  );
}

/* ─── CircularProgress helper ──────────────────────── */

function CircularProgress({ percent }: { percent: number }) {
  const size = 40;
  const strokeWidth = 4;
  const radius = (size - strokeWidth) / 2;
  const circumference = 2 * Math.PI * radius;
  const offset = circumference - (percent / 100) * circumference;
  const color = percent >= 75 ? '#22c55e' : percent >= 40 ? '#F4A261' : '#f59e0b';

  return (
    <svg width={size} height={size} style={{ transform: 'rotate(-90deg)' }}>
      <circle cx={size / 2} cy={size / 2} r={radius} fill="none" stroke="#e5e7eb" strokeWidth={strokeWidth} />
      <circle cx={size / 2} cy={size / 2} r={radius} fill="none" stroke={color} strokeWidth={strokeWidth}
        strokeDasharray={circumference} strokeDashoffset={offset} strokeLinecap="round" />
      <text x={size / 2} y={size / 2} textAnchor="middle" dominantBaseline="central"
        style={{ fontSize: 11, fontWeight: 700, fill: color, transform: 'rotate(90deg)', transformOrigin: 'center' }}>
        {percent}%
      </text>
    </svg>
  );
}
