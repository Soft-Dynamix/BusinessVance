'use client'

import { useState, useEffect, useCallback } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { FileText, FileCheck, Clock } from 'lucide-react'

interface Template {
  id: string
  name: string
  slug: string
  status: string
  version: string
  createdAt: string
}

export function TemplatesTab() {
  const [questionnaireTemplates, setQuestionnaireTemplates] = useState<Template[]>([])
  const [agreementTemplates, setAgreementTemplates] = useState<Template[]>([])
  const [loading, setLoading] = useState(true)

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
    } catch (e) { console.error(e) }
    finally { setLoading(false) }
  }, [])

  useEffect(() => { fetchData() }, [fetchData])

  const statusBadge = (status: string) => {
    const colors: Record<string, string> = {
      draft: 'bg-gray-100 text-gray-700',
      published: 'bg-green-100 text-green-700',
      archived: 'bg-yellow-100 text-yellow-700',
      ready: 'bg-blue-100 text-blue-700',
      delivered: 'bg-purple-100 text-purple-700',
    }
    return <Badge className={colors[status] || 'bg-gray-100'}>{status}</Badge>
  }

  if (loading) return <div className="flex items-center justify-center py-12"><div className="animate-spin h-8 w-8 border-2 border-[#D4AF37] border-t-transparent rounded-full" /></div>

  return (
    <div className="space-y-6">
      {/* Questionnaire Templates */}
      <Card className="border-gray-200">
        <CardHeader className="pb-3">
          <CardTitle className="flex items-center gap-2 text-[#002B5C]">
            <FileText className="w-5 h-5" />
            Questionnaire Templates ({questionnaireTemplates.length})
          </CardTitle>
        </CardHeader>
        <CardContent>
          {questionnaireTemplates.length === 0 ? (
            <p className="text-sm text-gray-500 py-4 text-center">No questionnaire templates yet.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Slug</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Version</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {questionnaireTemplates.map(t => (
                  <TableRow key={t.id}>
                    <TableCell className="font-medium">{t.name}</TableCell>
                    <TableCell className="text-gray-500 text-sm">{t.slug}</TableCell>
                    <TableCell>{statusBadge(t.status)}</TableCell>
                    <TableCell className="text-sm">v{t.version}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      {/* Agreement Templates */}
      <Card className="border-gray-200">
        <CardHeader className="pb-3">
          <CardTitle className="flex items-center gap-2 text-[#002B5C]">
            <FileCheck className="w-5 h-5" />
            Agreement Templates ({agreementTemplates.length})
          </CardTitle>
        </CardHeader>
        <CardContent>
          {agreementTemplates.length === 0 ? (
            <p className="text-sm text-gray-500 py-4 text-center">No agreement templates yet.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Slug</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Version</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {agreementTemplates.map(t => (
                  <TableRow key={t.id}>
                    <TableCell className="font-medium">{t.name}</TableCell>
                    <TableCell className="text-gray-500 text-sm">{t.slug}</TableCell>
                    <TableCell>{statusBadge(t.status)}</TableCell>
                    <TableCell className="text-sm">v{t.version}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
