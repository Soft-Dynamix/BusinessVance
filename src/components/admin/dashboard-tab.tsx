'use client'

import { useState, useEffect } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Package, CreditCard, FolderOpen, FileText, Users, Star, Activity } from 'lucide-react'

interface Stats {
  services: number
  plans: number
  categories: number
  projects: number
  questionnaires: number
  agreements: number
  icons: number
}

export function DashboardTab() {
  const [stats, setStats] = useState<Stats | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    async function load() {
      try {
        const res = await fetch('/api/stats')
        const data = await res.json()
        setStats(data.stats)
      } catch (e) {
        console.error('Failed to load dashboard stats', e)
      } finally {
        setLoading(false)
      }
    }
    load()
  }, [])

  if (loading || !stats) {
    return (
      <div className="space-y-6">
        <h2 className="text-2xl font-bold text-[#002B5C]">Dashboard</h2>
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          {Array.from({ length: 7 }).map((_, i) => (
            <Card key={i}><CardContent className="p-6"><Skeleton className="h-20 w-full" /></CardContent></Card>
          ))}
        </div>
      </div>
    )
  }

  const cards: { label: string; value: number; icon: React.ElementType; color: string; bg: string }[] = [
    { label: 'Services', value: stats.services, icon: Package, color: 'text-[#002B5C]', bg: 'bg-[#002B5C]/10' },
    { label: 'Plans', value: stats.plans, icon: CreditCard, color: 'text-[#2A9D8F]', bg: 'bg-[#2A9D8F]/10' },
    { label: 'Categories', value: stats.categories, icon: FolderOpen, color: 'text-[#D4AF37]', bg: 'bg-[#D4AF37]/10' },
    { label: 'Projects', value: stats.projects, icon: Users, color: 'text-[#002B5C]', bg: 'bg-[#002B5C]/10' },
    { label: 'Questionnaires', value: stats.questionnaires, icon: FileText, color: 'text-[#2A9D8F]', bg: 'bg-[#2A9D8F]/10' },
    { label: 'Agreements', value: stats.agreements, icon: Activity, color: 'text-[#D4AF37]', bg: 'bg-[#D4AF37]/10' },
    { label: 'Icons', value: stats.icons, icon: Star, color: 'text-[#002B5C]', bg: 'bg-[#002B5C]/10' },
  ]

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold text-[#002B5C]">Dashboard</h2>
        <p className="text-gray-500 text-sm mt-1">Overview of your BusinessVance platform</p>
      </div>
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        {cards.map(card => {
          const Icon = card.icon
          return (
            <Card key={card.label} className="hover:shadow-md transition-shadow">
              <CardContent className="p-4 md:p-6">
                <div className="flex items-center justify-between mb-3">
                  <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">{card.label}</span>
                  <div className={`p-2 rounded-lg ${card.bg}`}>
                    <Icon className={`h-4 w-4 ${card.color}`} />
                  </div>
                </div>
                <p className="text-2xl md:text-3xl font-bold text-[#002B5C]">{card.value}</p>
              </CardContent>
            </Card>
          )
        })}
      </div>
      <Card>
        <CardHeader>
          <CardTitle className="text-lg text-[#002B5C]">Quick Info</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div className="space-y-2">
              <p className="font-medium text-gray-700">Platform Status</p>
              <div className="flex items-center gap-2"><div className="w-2 h-2 rounded-full bg-green-500" /><span className="text-gray-600">All systems operational</span></div>
            </div>
            <div className="space-y-2">
              <p className="font-medium text-gray-700">Database</p>
              <div className="flex items-center gap-2"><div className="w-2 h-2 rounded-full bg-green-500" /><span className="text-gray-600">SQLite connected</span></div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
