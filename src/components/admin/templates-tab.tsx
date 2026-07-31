'use client'

import { useState, useEffect, useCallback } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Separator } from '@/components/ui/separator'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog'
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogAction,
  AlertDialogCancel,
} from '@/components/ui/alert-dialog'
import {
  Tabs,
  TabsList,
  TabsTrigger,
  TabsContent,
} from '@/components/ui/tabs'
import { ScrollArea } from '@/components/ui/scroll-area'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  FileText,
  FileCheck,
  Plus,
  Eye,
  Pencil,
  Trash2,
  Printer,
  X,
  Layers,
  HelpCircle,
  Hash,
  Link2,
  ClipboardCheck,
  Clock,
  CheckCircle2,
  Archive,
  ChevronRight,
} from 'lucide-react'

// ─── Types ───────────────────────────────────────────────────────────────────

interface Question {
  id: string
  type: string
  label: string
  placeholder: string
  required: boolean
  options: string
  helpText: string
  displayOrder: number
}

interface Section {
  id: string
  title: string
  description: string
  displayOrder: number
  isShared: boolean
  questions: Question[]
}

interface QuestionnaireTemplate {
  id: string
  name: string
  slug: string
  description: string
  version: string
  status: string
  createdAt: string
  updatedAt: string
  sections: Section[]
  _count: { services: number; projects: number }
}

interface AgreementTemplate {
  id: string
  name: string
  slug: string
  content: string
  version: string
  status: string
  createdAt: string
  updatedAt: string
  _count: { services: number; projects: number }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const statusConfig: Record<string, { label: string; className: string; icon: React.ReactNode }> = {
  draft: {
    label: 'Draft',
    className: 'bg-gray-100 text-gray-700 border-gray-200',
    icon: <Clock className="w-3 h-3" />,
  },
  published: {
    label: 'Published',
    className: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    icon: <CheckCircle2 className="w-3 h-3" />,
  },
  archived: {
    label: 'Archived',
    className: 'bg-amber-50 text-amber-700 border-amber-200',
    icon: <Archive className="w-3 h-3" />,
  },
}

function StatusBadge({ status }: { status: string }) {
  const config = statusConfig[status] || statusConfig.draft
  return (
    <Badge variant="outline" className={`${config.className} gap-1 font-medium`}>
      {config.icon}
      {config.label}
    </Badge>
  )
}

function QuestionTypeBadge({ type }: { type: string }) {
  const colors: Record<string, string> = {
    text: 'bg-sky-50 text-sky-700 border-sky-200',
    textarea: 'bg-sky-50 text-sky-700 border-sky-200',
    number: 'bg-violet-50 text-violet-700 border-violet-200',
    email: 'bg-cyan-50 text-cyan-700 border-cyan-200',
    phone: 'bg-cyan-50 text-cyan-700 border-cyan-200',
    date: 'bg-orange-50 text-orange-700 border-orange-200',
    select: 'bg-pink-50 text-pink-700 border-pink-200',
    multiselect: 'bg-pink-50 text-pink-700 border-pink-200',
    radio: 'bg-rose-50 text-rose-700 border-rose-200',
    checkbox: 'bg-rose-50 text-rose-700 border-rose-200',
    file: 'bg-amber-50 text-amber-700 border-amber-200',
    heading: 'bg-gray-100 text-gray-500 border-gray-200',
    paragraph: 'bg-gray-100 text-gray-500 border-gray-200',
  }
  return (
    <Badge variant="outline" className={`${colors[type] || 'bg-gray-100 text-gray-600'} text-xs font-medium`}>
      {type}
    </Badge>
  )
}

function parseOptions(optionsStr: string): string[] {
  try {
    const parsed = JSON.parse(optionsStr)
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

// ─── Component ───────────────────────────────────────────────────────────────

export function TemplatesTab() {
  const [activeTab, setActiveTab] = useState('questionnaires')

  // Data
  const [questionnaireTemplates, setQuestionnaireTemplates] = useState<QuestionnaireTemplate[]>([])
  const [agreementTemplates, setAgreementTemplates] = useState<AgreementTemplate[]>([])
  const [loading, setLoading] = useState(true)

  // Dialogs
  const [viewQuestionnaire, setViewQuestionnaire] = useState<QuestionnaireTemplate | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<{ type: 'questionnaire' | 'agreement'; id: string; name: string } | null>(null)
  const [previewAgreement, setPreviewAgreement] = useState<AgreementTemplate | null>(null)
  const [editAgreement, setEditAgreement] = useState<AgreementTemplate | null>(null)
  const [addQuestionnaire, setAddQuestionnaire] = useState(false)
  const [addAgreement, setAddAgreement] = useState(false)

  // Form state for edit/add
  const [editForm, setEditForm] = useState({ name: '', status: '', version: '' })
  const [addQForm, setAddQForm] = useState({ name: '', description: '', version: '1.0', status: 'draft' })
  const [addAForm, setAddAForm] = useState({ name: '', content: '', version: '1.0', status: 'draft' })

  // Loading states
  const [actionLoading, setActionLoading] = useState(false)

  const fetchData = useCallback(async () => {
    try {
      const [qRes, aRes] = await Promise.all([
        fetch('/api/questionnaire-templates'),
        fetch('/api/agreement-templates'),
      ])
      if (qRes.ok) {
        const qData = await qRes.json()
        setQuestionnaireTemplates(qData.templates || [])
      }
      if (aRes.ok) {
        const aData = await aRes.json()
        setAgreementTemplates(aData.templates || [])
      }
    } catch (e) {
      console.error(e)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchData()
  }, [fetchData])

  // ─── Handlers ───────────────────────────────────────────────────────────

  const handleViewQuestionnaire = async (id: string) => {
    try {
      const res = await fetch(`/api/questionnaire-templates/${id}`)
      if (res.ok) {
        const data = await res.json()
        setViewQuestionnaire(data.template)
      }
    } catch (e) {
      console.error(e)
    }
  }

  const handleDelete = async () => {
    if (!deleteTarget) return
    setActionLoading(true)
    try {
      const endpoint = deleteTarget.type === 'questionnaire'
        ? `/api/questionnaire-templates/${deleteTarget.id}`
        : `/api/agreement-templates/${deleteTarget.id}`
      const res = await fetch(endpoint, { method: 'DELETE' })
      if (res.ok) {
        setDeleteTarget(null)
        fetchData()
      }
    } catch (e) {
      console.error(e)
    } finally {
      setActionLoading(false)
    }
  }

  const handleOpenEditAgreement = (template: AgreementTemplate) => {
    setEditForm({ name: template.name, status: template.status, version: template.version })
    setEditAgreement(template)
  }

  const handleSaveEditAgreement = async () => {
    if (!editAgreement) return
    setActionLoading(true)
    try {
      const res = await fetch(`/api/agreement-templates/${editAgreement.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(editForm),
      })
      if (res.ok) {
        setEditAgreement(null)
        fetchData()
      }
    } catch (e) {
      console.error(e)
    } finally {
      setActionLoading(false)
    }
  }

  const handleAddQuestionnaire = async () => {
    if (!addQForm.name.trim()) return
    setActionLoading(true)
    try {
      const res = await fetch('/api/questionnaire-templates', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(addQForm),
      })
      if (res.ok) {
        setAddQuestionnaire(false)
        setAddQForm({ name: '', description: '', version: '1.0', status: 'draft' })
        fetchData()
      }
    } catch (e) {
      console.error(e)
    } finally {
      setActionLoading(false)
    }
  }

  const handleAddAgreement = async () => {
    if (!addAForm.name.trim()) return
    setActionLoading(true)
    try {
      const res = await fetch('/api/agreement-templates', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(addAForm),
      })
      if (res.ok) {
        setAddAgreement(false)
        setAddAForm({ name: '', content: '', version: '1.0', status: 'draft' })
        fetchData()
      }
    } catch (e) {
      console.error(e)
    } finally {
      setActionLoading(false)
    }
  }

  const handlePrintAgreement = () => {
    if (!previewAgreement) return
    const printWindow = window.open('', '_blank')
    if (!printWindow) return
    printWindow.document.write(`
      <!DOCTYPE html>
      <html>
      <head>
        <title>${previewAgreement.name}</title>
        <style>
          * { margin: 0; padding: 0; box-sizing: border-box; }
          body { font-family: 'Georgia', 'Times New Roman', serif; color: #1a1a1a; line-height: 1.7; padding: 60px 80px; }
          .header { text-align: center; margin-bottom: 40px; border-bottom: 3px solid #D4AF37; padding-bottom: 20px; }
          .header h1 { font-size: 24px; color: #0A2647; margin-bottom: 4px; }
          .header p { font-size: 14px; color: #666; }
          .content { font-size: 13px; }
          .content h1, .content h2, .content h3 { color: #0A2647; margin-top: 24px; margin-bottom: 12px; }
          .content p { margin-bottom: 12px; }
          .content ul, .content ol { margin-left: 24px; margin-bottom: 12px; }
          .content li { margin-bottom: 4px; }
          .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 11px; color: #999; }
          @media print { body { padding: 40px; } }
        </style>
      </head>
      <body>
        <div class="header">
          <h1>${previewAgreement.name}</h1>
          <p>Version ${previewAgreement.version} &middot; Generated on ${new Date().toLocaleDateString('en-ZA')}</p>
        </div>
        <div class="content">${previewAgreement.content}</div>
        <div class="footer">BusinessVance &mdash; Professional Business Consulting</div>
      </body>
      </html>
    `)
    printWindow.document.close()
    printWindow.print()
  }

  // ─── Loading ────────────────────────────────────────────────────────────

  if (loading) {
    return (
      <div className="flex items-center justify-center py-16">
        <div className="animate-spin h-8 w-8 border-2 border-[#D4AF37] border-t-transparent rounded-full" />
      </div>
    )
  }

  // ─── Render ─────────────────────────────────────────────────────────────

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-[#0A2647]">Templates</h2>
          <p className="text-sm text-gray-500 mt-1">Manage questionnaire and agreement templates</p>
        </div>
      </div>

      {/* Main Tabs */}
      <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
        <TabsList className="bg-gray-100">
          <TabsTrigger value="questionnaires" className="gap-2 data-[state=active]:bg-white data-[state=active]:shadow-sm">
            <FileText className="w-4 h-4" />
            Questionnaire Templates
            <Badge variant="secondary" className="ml-1 h-5 px-1.5 text-xs">
              {questionnaireTemplates.length}
            </Badge>
          </TabsTrigger>
          <TabsTrigger value="agreements" className="gap-2 data-[state=active]:bg-white data-[state=active]:shadow-sm">
            <FileCheck className="w-4 h-4" />
            Agreement Templates
            <Badge variant="secondary" className="ml-1 h-5 px-1.5 text-xs">
              {agreementTemplates.length}
            </Badge>
          </TabsTrigger>
        </TabsList>

        {/* ─── Questionnaire Templates Tab ─────────────────────────────────── */}
        <TabsContent value="questionnaires">
          <Card className="border-gray-200 mt-2">
            <CardHeader className="pb-4">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <CardTitle className="flex items-center gap-2 text-[#0A2647]">
                  <ClipboardCheck className="w-5 h-5 text-[#D4AF37]" />
                  Questionnaire Templates
                </CardTitle>
                <Button
                  onClick={() => {
                    setAddQForm({ name: '', description: '', version: '1.0', status: 'draft' })
                    setAddQuestionnaire(true)
                  }}
                  className="bg-[#0A2647] hover:bg-[#0A2647]/90 gap-2"
                >
                  <Plus className="w-4 h-4" />
                  Add Template
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              {questionnaireTemplates.length === 0 ? (
                <div className="text-center py-12">
                  <FileText className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                  <p className="text-gray-500 font-medium">No questionnaire templates yet</p>
                  <p className="text-sm text-gray-400 mt-1">Create your first questionnaire template to get started</p>
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow className="hover:bg-transparent border-gray-200">
                        <TableHead className="text-xs font-semibold uppercase tracking-wider text-gray-500">Name</TableHead>
                        <TableHead className="text-xs font-semibold uppercase tracking-wider text-gray-500">Description</TableHead>
                        <TableHead className="text-xs font-semibold uppercase tracking-wider text-gray-500 text-center">Sections</TableHead>
                        <TableHead className="text-xs font-semibold uppercase tracking-wider text-gray-500 text-center">Questions</TableHead>
                        <TableHead className="text-xs font-semibold uppercase tracking-wider text-gray-500">Status</TableHead>
                        <TableHead className="text-xs font-semibold uppercase tracking-wider text-gray-500">Version</TableHead>
                        <TableHead className="text-xs font-semibold uppercase tracking-wider text-gray-500 text-center">Linked Services</TableHead>
                        <TableHead className="text-xs font-semibold uppercase tracking-wider text-gray-500 text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {questionnaireTemplates.map((t) => {
                        const totalQuestions = t.sections?.reduce((sum, s) => sum + (s.questions?.length || 0), 0) || 0
                        const sectionCount = t.sections?.length || 0
                        return (
                          <TableRow key={t.id} className="group">
                            <TableCell>
                              <div className="font-semibold text-gray-900">{t.name}</div>
                              <div className="text-xs text-gray-400 mt-0.5">/{t.slug}</div>
                            </TableCell>
                            <TableCell>
                              <div className="text-sm text-gray-600 max-w-[200px] truncate">{t.description || '—'}</div>
                            </TableCell>
                            <TableCell className="text-center">
                              <div className="inline-flex items-center gap-1.5 text-sm font-medium text-gray-700">
                                <Layers className="w-3.5 h-3.5 text-gray-400" />
                                {sectionCount}
                              </div>
                            </TableCell>
                            <TableCell className="text-center">
                              <div className="inline-flex items-center gap-1.5 text-sm font-medium text-gray-700">
                                <HelpCircle className="w-3.5 h-3.5 text-gray-400" />
                                {totalQuestions}
                              </div>
                            </TableCell>
                            <TableCell>
                              <StatusBadge status={t.status} />
                            </TableCell>
                            <TableCell>
                              <span className="text-sm font-mono text-gray-600 bg-gray-100 px-2 py-0.5 rounded">v{t.version}</span>
                            </TableCell>
                            <TableCell className="text-center">
                              <div className="inline-flex items-center gap-1.5 text-sm text-gray-600">
                                <Link2 className="w-3.5 h-3.5 text-gray-400" />
                                {t._count?.services || 0}
                              </div>
                            </TableCell>
                            <TableCell className="text-right">
                              <div className="flex items-center justify-end gap-1 opacity-60 group-hover:opacity-100 transition-opacity">
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  onClick={() => handleViewQuestionnaire(t.id)}
                                  className="h-8 w-8 p-0 text-gray-500 hover:text-[#0A2647]"
                                  title="View template"
                                >
                                  <Eye className="w-4 h-4" />
                                </Button>
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  onClick={() => setDeleteTarget({ type: 'questionnaire', id: t.id, name: t.name })}
                                  className="h-8 w-8 p-0 text-gray-500 hover:text-red-600"
                                  title="Delete template"
                                >
                                  <Trash2 className="w-4 h-4" />
                                </Button>
                              </div>
                            </TableCell>
                          </TableRow>
                        )
                      })}
                    </TableBody>
                  </Table>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* ─── Agreement Templates Tab ─────────────────────────────────────── */}
        <TabsContent value="agreements">
          <Card className="border-gray-200 mt-2">
            <CardHeader className="pb-4">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <CardTitle className="flex items-center gap-2 text-[#0A2647]">
                  <FileCheck className="w-5 h-5 text-[#D4AF37]" />
                  Agreement Templates
                </CardTitle>
                <Button
                  onClick={() => {
                    setAddAForm({ name: '', content: '', version: '1.0', status: 'draft' })
                    setAddAgreement(true)
                  }}
                  className="bg-[#0A2647] hover:bg-[#0A2647]/90 gap-2"
                >
                  <Plus className="w-4 h-4" />
                  Add Agreement
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              {agreementTemplates.length === 0 ? (
                <div className="text-center py-12">
                  <FileCheck className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                  <p className="text-gray-500 font-medium">No agreement templates yet</p>
                  <p className="text-sm text-gray-400 mt-1">Create your first agreement template for client signing</p>
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow className="hover:bg-transparent border-gray-200">
                        <TableHead className="text-xs font-semibold uppercase tracking-wider text-gray-500">Name</TableHead>
                        <TableHead className="text-xs font-semibold uppercase tracking-wider text-gray-500">Status</TableHead>
                        <TableHead className="text-xs font-semibold uppercase tracking-wider text-gray-500">Version</TableHead>
                        <TableHead className="text-xs font-semibold uppercase tracking-wider text-gray-500 text-center">Linked Services</TableHead>
                        <TableHead className="text-xs font-semibold uppercase tracking-wider text-gray-500 text-center">Projects Signed</TableHead>
                        <TableHead className="text-xs font-semibold uppercase tracking-wider text-gray-500 text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {agreementTemplates.map((t) => (
                        <TableRow key={t.id} className="group">
                          <TableCell>
                            <div className="font-semibold text-gray-900">{t.name}</div>
                            <div className="text-xs text-gray-400 mt-0.5">/{t.slug}</div>
                          </TableCell>
                          <TableCell>
                            <StatusBadge status={t.status} />
                          </TableCell>
                          <TableCell>
                            <span className="text-sm font-mono text-gray-600 bg-gray-100 px-2 py-0.5 rounded">v{t.version}</span>
                          </TableCell>
                          <TableCell className="text-center">
                            <div className="inline-flex items-center gap-1.5 text-sm text-gray-600">
                              <Link2 className="w-3.5 h-3.5 text-gray-400" />
                              {t._count?.services || 0}
                            </div>
                          </TableCell>
                          <TableCell className="text-center">
                            <div className="inline-flex items-center gap-1.5 text-sm text-gray-600">
                              <ClipboardCheck className="w-3.5 h-3.5 text-gray-400" />
                              {t._count?.projects || 0}
                            </div>
                          </TableCell>
                          <TableCell className="text-right">
                            <div className="flex items-center justify-end gap-1 opacity-60 group-hover:opacity-100 transition-opacity">
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setPreviewAgreement(t)}
                                className="h-8 w-8 p-0 text-gray-500 hover:text-[#0A2647]"
                                title="Preview agreement"
                              >
                                <Eye className="w-4 h-4" />
                              </Button>
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => handleOpenEditAgreement(t)}
                                className="h-8 w-8 p-0 text-gray-500 hover:text-[#D4AF37]"
                                title="Edit agreement"
                              >
                                <Pencil className="w-4 h-4" />
                              </Button>
                              <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setDeleteTarget({ type: 'agreement', id: t.id, name: t.name })}
                                className="h-8 w-8 p-0 text-gray-500 hover:text-red-600"
                                title="Delete agreement"
                              >
                                <Trash2 className="w-4 h-4" />
                              </Button>
                            </div>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {/* ─── View Questionnaire Dialog ────────────────────────────────────── */}
      <Dialog open={!!viewQuestionnaire} onOpenChange={() => setViewQuestionnaire(null)}>
        <DialogContent className="sm:max-w-3xl max-h-[85vh] p-0 overflow-hidden flex flex-col">
          <DialogHeader className="p-6 pb-4 border-b border-gray-100 shrink-0">
            <div className="flex items-start justify-between gap-4">
              <div className="space-y-1">
                <DialogTitle className="text-xl text-[#0A2647]">{viewQuestionnaire?.name}</DialogTitle>
                <DialogDescription className="text-sm">{viewQuestionnaire?.description || 'No description'}</DialogDescription>
              </div>
              <div className="flex items-center gap-2 shrink-0">
                <StatusBadge status={viewQuestionnaire?.status || 'draft'} />
                <span className="text-sm font-mono text-gray-500 bg-gray-100 px-2 py-0.5 rounded">
                  v{viewQuestionnaire?.version}
                </span>
              </div>
            </div>
            <div className="flex items-center gap-4 mt-3 text-xs text-gray-400">
              <span className="flex items-center gap-1">
                <Hash className="w-3 h-3" />
                {viewQuestionnaire?.slug}
              </span>
              <span>·</span>
              <span>{viewQuestionnaire?.sections?.length || 0} sections</span>
              <span>·</span>
              <span>{viewQuestionnaire?.sections?.reduce((s, sec) => s + (sec.questions?.length || 0), 0) || 0} questions</span>
              <span>·</span>
              <span>{viewQuestionnaire?._count?.services || 0} linked services</span>
            </div>
          </DialogHeader>
          <ScrollArea className="flex-1 max-h-[60vh]">
            <div className="p-6 space-y-6">
              {viewQuestionnaire?.sections?.map((section, sIdx) => (
                <div key={section.id} className="space-y-3">
                  <div className="flex items-center gap-2">
                    <div className="flex items-center justify-center w-6 h-6 rounded-full bg-[#0A2647] text-white text-xs font-bold shrink-0">
                      {sIdx + 1}
                    </div>
                    <h3 className="font-semibold text-gray-900">{section.title}</h3>
                    {section.isShared && (
                      <Badge variant="outline" className="bg-[#D4AF37]/10 text-[#D4AF37] border-[#D4AF37]/30 text-xs">
                        Shared
                      </Badge>
                    )}
                    <Badge variant="secondary" className="text-xs">
                      {section.questions?.length || 0} questions
                    </Badge>
                  </div>
                  {section.description && (
                    <p className="text-sm text-gray-500 ml-8">{section.description}</p>
                  )}
                  {section.questions && section.questions.length > 0 && (
                    <div className="ml-8 space-y-2">
                      {section.questions.map((q) => {
                        const opts = parseOptions(q.options)
                        return (
                          <div
                            key={q.id}
                            className="rounded-lg border border-gray-200 bg-gray-50/50 p-3 space-y-1.5"
                          >
                            <div className="flex items-start justify-between gap-2">
                              <div className="flex items-center gap-2 flex-wrap">
                                <span className="font-medium text-sm text-gray-800">{q.label}</span>
                                <QuestionTypeBadge type={q.type} />
                                {q.required && (
                                  <Badge className="bg-red-50 text-red-600 border-red-200 text-xs">
                                    Required
                                  </Badge>
                                )}
                              </div>
                              <span className="text-xs text-gray-400 shrink-0">
                                #{q.displayOrder + 1}
                              </span>
                            </div>
                            {q.placeholder && (
                              <p className="text-xs text-gray-400 italic">
                                Placeholder: &ldquo;{q.placeholder}&rdquo;
                              </p>
                            )}
                            {q.helpText && (
                              <p className="text-xs text-blue-600 flex items-center gap-1">
                                <HelpCircle className="w-3 h-3" />
                                {q.helpText}
                              </p>
                            )}
                            {opts.length > 0 && (
                              <div className="flex flex-wrap gap-1.5 pt-1">
                                {opts.map((opt, i) => (
                                  <Badge
                                    key={i}
                                    variant="outline"
                                    className="bg-white text-gray-600 border-gray-200 text-xs"
                                  >
                                    {opt}
                                  </Badge>
                                ))}
                              </div>
                            )}
                          </div>
                        )
                      })}
                    </div>
                  )}
                  {sIdx < (viewQuestionnaire?.sections?.length || 0) - 1 && (
                    <Separator className="ml-8" />
                  )}
                </div>
              ))}
              {(!viewQuestionnaire?.sections || viewQuestionnaire.sections.length === 0) && (
                <div className="text-center py-8 text-gray-400">
                  <Layers className="w-8 h-8 mx-auto mb-2 text-gray-300" />
                  <p>No sections in this template</p>
                </div>
              )}
            </div>
          </ScrollArea>
        </DialogContent>
      </Dialog>

      {/* ─── Delete Confirmation Dialog ───────────────────────────────────── */}
      <AlertDialog open={!!deleteTarget} onOpenChange={() => setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Template</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete &ldquo;{deleteTarget?.name}&rdquo;? This action cannot be undone.
              {deleteTarget?.type === 'questionnaire' && (
                <span className="block mt-2 text-amber-600">
                  Warning: All sections and questions within this questionnaire template will be permanently removed.
                </span>
              )}
              {deleteTarget?.type === 'agreement' && (
                <span className="block mt-2 text-amber-600">
                  Warning: This may affect services linked to this agreement template.
                </span>
              )}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={actionLoading}>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDelete}
              disabled={actionLoading}
              className="bg-red-600 hover:bg-red-700"
            >
              {actionLoading ? (
                <div className="flex items-center gap-2">
                  <div className="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full" />
                  Deleting...
                </div>
              ) : (
                'Delete Template'
              )}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* ─── Agreement Preview Modal (Full Screen) ────────────────────────── */}
      {previewAgreement && (
        <div className="fixed inset-0 z-[100] bg-black/60 flex items-center justify-center p-0 sm:p-6">
          <div
            className="bg-white w-full h-full sm:h-[90vh] sm:max-w-4xl sm:rounded-xl shadow-2xl flex flex-col overflow-hidden"
          >
            {/* Preview Header */}
            <div className="bg-[#0A2647] px-6 py-4 flex items-center justify-between shrink-0">
              <div className="flex items-center gap-3">
                <FileCheck className="w-5 h-5 text-[#D4AF37]" />
                <div>
                  <h2 className="text-white font-semibold text-lg">{previewAgreement.name}</h2>
                  <p className="text-gray-300 text-xs">Version {previewAgreement.version}</p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={handlePrintAgreement}
                  className="text-gray-300 hover:text-white hover:bg-white/10 gap-2"
                >
                  <Printer className="w-4 h-4" />
                  <span className="hidden sm:inline">Print</span>
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => setPreviewAgreement(null)}
                  className="text-gray-300 hover:text-white hover:bg-white/10"
                >
                  <X className="w-5 h-5" />
                </Button>
              </div>
            </div>

            {/* Agreement Document Content */}
            <ScrollArea className="flex-1">
              <div className="max-w-3xl mx-auto py-10 px-6 sm:px-12 print:p-0">
                {/* Document Header */}
                <div className="text-center mb-10 border-b-2 border-[#D4AF37] pb-6">
                  <div className="flex items-center justify-center gap-2 mb-2">
                    <div className="w-8 h-1 bg-[#D4AF37] rounded" />
                    <span className="text-xs font-semibold tracking-[0.2em] text-[#D4AF37] uppercase">BusinessVance</span>
                    <div className="w-8 h-1 bg-[#D4AF37] rounded" />
                  </div>
                  <h1 className="text-2xl font-bold text-[#0A2647]">{previewAgreement.name}</h1>
                  <p className="text-sm text-gray-500 mt-2">
                    Version {previewAgreement.version} &middot;{' '}
                    {new Date(previewAgreement.updatedAt).toLocaleDateString('en-ZA', {
                      year: 'numeric', month: 'long', day: 'numeric',
                    })}
                  </p>
                </div>

                {/* HTML Content */}
                <div
                  className="prose prose-sm max-w-none
                    [&_h1]:text-xl [&_h1]:font-bold [&_h1]:text-[#0A2647] [&_h1]:mt-8 [&_h1]:mb-4
                    [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-[#0A2647] [&_h2]:mt-6 [&_h2]:mb-3
                    [&_h3]:text-base [&_h3]:font-semibold [&_h3]:text-gray-800 [&_h3]:mt-5 [&_h3]:mb-2
                    [&_p]:text-gray-700 [&_p]:leading-relaxed [&_p]:mb-3
                    [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:mb-3 [&_ul]:space-y-1
                    [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:mb-3 [&_ol]:space-y-1
                    [&_li]:text-gray-700
                    [&_strong]:font-semibold [&_strong]:text-gray-900
                    [&_em]:italic
                    [&_a]:text-[#0A2647] [&_a]:underline [&_a]:hover:text-[#D4AF37]
                    [&_table]:w-full [&_table]:border-collapse [&_table]:mb-4
                    [&_th]:bg-gray-50 [&_th]:border [&_th]:border-gray-200 [&_th]:px-3 [&_th]:py-2 [&_th]:text-left [&_th]:text-sm [&_th]:font-semibold [&_th]:text-gray-700
                    [&_td]:border [&_td]:border-gray-200 [&_td]:px-3 [&_td]:py-2 [&_td]:text-sm [&_td]:text-gray-600
                    [&_blockquote]:border-l-4 [&_blockquote]:border-[#D4AF37] [&_blockquote]:pl-4 [&_blockquote]:italic [&_blockquote]:text-gray-600 [&_blockquote]:my-4
                  "
                  dangerouslySetInnerHTML={{ __html: previewAgreement.content }}
                />

                {/* Document Footer */}
                <div className="mt-12 pt-6 border-t border-gray-200 text-center">
                  <p className="text-xs text-gray-400">
                    This document was generated by BusinessVance &mdash; Professional Business Consulting
                  </p>
                  <p className="text-xs text-gray-300 mt-1">
                    Confidential &middot; For authorized use only
                  </p>
                </div>
              </div>
            </ScrollArea>
          </div>
        </div>
      )}

      {/* ─── Edit Agreement Dialog ────────────────────────────────────────── */}
      <Dialog open={!!editAgreement} onOpenChange={() => setEditAgreement(null)}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle className="text-[#0A2647]">Edit Agreement Template</DialogTitle>
            <DialogDescription>Update the agreement template details</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label htmlFor="edit-agreement-name">Name</Label>
              <Input
                id="edit-agreement-name"
                value={editForm.name}
                onChange={(e) => setEditForm({ ...editForm, name: e.target.value })}
                placeholder="Agreement name"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-agreement-status">Status</Label>
              <Select
                value={editForm.status}
                onValueChange={(val) => setEditForm({ ...editForm, status: val })}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Select status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="draft">Draft</SelectItem>
                  <SelectItem value="published">Published</SelectItem>
                  <SelectItem value="archived">Archived</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-agreement-version">Version</Label>
              <Input
                id="edit-agreement-version"
                value={editForm.version}
                onChange={(e) => setEditForm({ ...editForm, version: e.target.value })}
                placeholder="1.0"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setEditAgreement(null)} disabled={actionLoading}>
              Cancel
            </Button>
            <Button
              onClick={handleSaveEditAgreement}
              disabled={actionLoading || !editForm.name.trim()}
              className="bg-[#0A2647] hover:bg-[#0A2647]/90"
            >
              {actionLoading ? (
                <div className="flex items-center gap-2">
                  <div className="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full" />
                  Saving...
                </div>
              ) : (
                'Save Changes'
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* ─── Add Questionnaire Dialog ─────────────────────────────────────── */}
      <Dialog open={addQuestionnaire} onOpenChange={() => setAddQuestionnaire(false)}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle className="text-[#0A2647]">New Questionnaire Template</DialogTitle>
            <DialogDescription>Create a new questionnaire template for client onboarding</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label htmlFor="add-q-name">Template Name</Label>
              <Input
                id="add-q-name"
                value={addQForm.name}
                onChange={(e) => setAddQForm({ ...addQForm, name: e.target.value })}
                placeholder="e.g., Company Registration Questionnaire"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="add-q-desc">Description</Label>
              <Textarea
                id="add-q-desc"
                value={addQForm.description}
                onChange={(e) => setAddQForm({ ...addQForm, description: e.target.value })}
                placeholder="Brief description of what this questionnaire covers"
                rows={3}
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="add-q-version">Version</Label>
                <Input
                  id="add-q-version"
                  value={addQForm.version}
                  onChange={(e) => setAddQForm({ ...addQForm, version: e.target.value })}
                  placeholder="1.0"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="add-q-status">Status</Label>
                <Select
                  value={addQForm.status}
                  onValueChange={(val) => setAddQForm({ ...addQForm, status: val })}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="Select status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="draft">Draft</SelectItem>
                    <SelectItem value="published">Published</SelectItem>
                    <SelectItem value="archived">Archived</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setAddQuestionnaire(false)} disabled={actionLoading}>
              Cancel
            </Button>
            <Button
              onClick={handleAddQuestionnaire}
              disabled={actionLoading || !addQForm.name.trim()}
              className="bg-[#0A2647] hover:bg-[#0A2647]/90"
            >
              {actionLoading ? (
                <div className="flex items-center gap-2">
                  <div className="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full" />
                  Creating...
                </div>
              ) : (
                <>
                  <Plus className="w-4 h-4 mr-1" />
                  Create Template
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* ─── Add Agreement Dialog ─────────────────────────────────────────── */}
      <Dialog open={addAgreement} onOpenChange={() => setAddAgreement(false)}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle className="text-[#0A2647]">New Agreement Template</DialogTitle>
            <DialogDescription>Create a new agreement template for client signing</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label htmlFor="add-a-name">Agreement Name</Label>
              <Input
                id="add-a-name"
                value={addAForm.name}
                onChange={(e) => setAddAForm({ ...addAForm, name: e.target.value })}
                placeholder="e.g., Confidentiality Undertaking"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="add-a-content">Content (HTML)</Label>
              <Textarea
                id="add-a-content"
                value={addAForm.content}
                onChange={(e) => setAddAForm({ ...addAForm, content: e.target.value })}
                placeholder="<h1>Agreement Title</h1><p>Agreement content...</p>"
                rows={6}
                className="font-mono text-xs"
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="add-a-version">Version</Label>
                <Input
                  id="add-a-version"
                  value={addAForm.version}
                  onChange={(e) => setAddAForm({ ...addAForm, version: e.target.value })}
                  placeholder="1.0"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="add-a-status">Status</Label>
                <Select
                  value={addAForm.status}
                  onValueChange={(val) => setAddAForm({ ...addAForm, status: val })}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="Select status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="draft">Draft</SelectItem>
                    <SelectItem value="published">Published</SelectItem>
                    <SelectItem value="archived">Archived</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setAddAgreement(false)} disabled={actionLoading}>
              Cancel
            </Button>
            <Button
              onClick={handleAddAgreement}
              disabled={actionLoading || !addAForm.name.trim()}
              className="bg-[#0A2647] hover:bg-[#0A2647]/90"
            >
              {actionLoading ? (
                <div className="flex items-center gap-2">
                  <div className="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full" />
                  Creating...
                </div>
              ) : (
                <>
                  <Plus className="w-4 h-4 mr-1" />
                  Create Agreement
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
