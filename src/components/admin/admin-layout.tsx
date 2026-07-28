'use client'

import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet'
import { ScrollArea } from '@/components/ui/scroll-area'
import {
  LayoutDashboard, Package, CreditCard, FolderOpen, Settings, Star, FileText, Users,
  Menu, ShieldCheck
} from 'lucide-react'

type TabId = 'dashboard' | 'services' | 'plans' | 'categories' | 'settings' | 'icons' | 'templates' | 'projects'

const navItems: { id: TabId; label: string; icon: React.ElementType }[] = [
  { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { id: 'services', label: 'Services', icon: Package },
  { id: 'plans', label: 'Plans', icon: CreditCard },
  { id: 'categories', label: 'Categories', icon: FolderOpen },
  { id: 'icons', label: 'Icons', icon: Star },
  { id: 'templates', label: 'Templates', icon: FileText },
  { id: 'projects', label: 'Projects', icon: Users },
  { id: 'settings', label: 'Settings', icon: Settings },
]

interface AdminLayoutProps {
  activeTab: TabId
  onTabChange: (tab: TabId) => void
  children: React.ReactNode
}

function SidebarNav({ activeTab, onTabChange, onNavigate }: {
  activeTab: TabId
  onTabChange: (tab: TabId) => void
  onNavigate?: () => void
}) {
  return (
    <nav className="flex flex-col gap-1 p-3">
      {navItems.map(item => {
        const Icon = item.icon
        const isActive = activeTab === item.id
        return (
          <button
            key={item.id}
            onClick={() => { onTabChange(item.id); onNavigate?.() }}
            className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all w-full text-left ${
              isActive
                ? 'bg-[#002B5C] text-white shadow-sm'
                : 'text-gray-600 hover:bg-gray-100 hover:text-[#002B5C]'
            }`}
          >
            <Icon className="h-4 w-4 shrink-0" />
            {item.label}
          </button>
        )
      })}
    </nav>
  )
}

export type { TabId }

export function AdminLayout({ activeTab, onTabChange, children }: AdminLayoutProps) {
  const [mobileOpen, setMobileOpen] = useState(false)

  return (
    <div className="min-h-screen flex flex-col bg-[#f0f0f1]">
      <header className="sticky top-0 z-40 bg-[#002B5C] text-white shadow-lg">
        <div className="flex items-center justify-between h-14 px-4">
          <div className="flex items-center gap-3">
            <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
              <SheetTrigger asChild className="lg:hidden">
                <Button variant="ghost" size="icon" className="text-white hover:bg-white/10">
                  <Menu className="h-5 w-5" />
                </Button>
              </SheetTrigger>
              <SheetContent side="left" className="w-64 p-0">
                <div className="flex items-center gap-2 p-4 border-b">
                  <ShieldCheck className="h-5 w-5 text-[#002B5C]" />
                  <span className="font-bold text-[#002B5C]">BusinessVance</span>
                </div>
                <ScrollArea className="h-[calc(100vh-4rem)]">
                  <SidebarNav activeTab={activeTab} onTabChange={onTabChange} onNavigate={() => setMobileOpen(false)} />
                </ScrollArea>
              </SheetContent>
            </Sheet>
            <ShieldCheck className="h-5 w-5 text-[#D4AF37] hidden sm:block" />
            <span className="font-bold text-sm sm:text-base">BusinessVance Admin</span>
          </div>
          <span className="text-xs text-white/60 hidden sm:block">Management Dashboard</span>
        </div>
      </header>
      <div className="flex flex-1">
        <aside className="hidden lg:block w-56 shrink-0 bg-white border-r border-gray-200">
          <div className="sticky top-14 h-[calc(100vh-3.5rem)] overflow-y-auto">
            <SidebarNav activeTab={activeTab} onTabChange={onTabChange} />
          </div>
        </aside>
        <main className="flex-1 p-4 md:p-6 overflow-auto">
          {children}
        </main>
      </div>
    </div>
  )
}
