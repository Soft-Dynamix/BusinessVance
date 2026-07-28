'use client'

import { useState } from 'react'
import { AdminLayout } from '@/components/admin/admin-layout'
import { DashboardTab } from '@/components/admin/dashboard-tab'
import { ServicesTab } from '@/components/admin/services-tab'
import { PlansTab } from '@/components/admin/plans-tab'
import { CategoriesTab } from '@/components/admin/categories-tab'
import { SettingsTab } from '@/components/admin/settings-tab'
import { IconsTab } from '@/components/admin/icons-tab'
import { TemplatesTab } from '@/components/admin/templates-tab'

type TabId = 'dashboard' | 'services' | 'plans' | 'categories' | 'icons' | 'templates' | 'settings'

export default function AdminPage() {
  const [activeTab, setActiveTab] = useState<TabId>('dashboard')

  return (
    <div className="min-h-screen bg-[#f0f0f1]">
      <AdminLayout activeTab={activeTab} onTabChange={setActiveTab}>
        {activeTab === 'dashboard' && <DashboardTab />}
        {activeTab === 'services' && <ServicesTab />}
        {activeTab === 'plans' && <PlansTab />}
        {activeTab === 'categories' && <CategoriesTab />}
        {activeTab === 'icons' && <IconsTab />}
        {activeTab === 'templates' && <TemplatesTab />}
        {activeTab === 'settings' && <SettingsTab />}
      </AdminLayout>
    </div>
  )
}
