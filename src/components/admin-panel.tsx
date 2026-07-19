'use client';

import { useState, useEffect, useCallback } from 'react';
import { Settings } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Skeleton } from '@/components/ui/skeleton';
import { AdminDashboard } from './admin/admin-dashboard';
import { AdminServices } from './admin/admin-services';
import { AdminPlans } from './admin/admin-plans';
import { AdminCategories } from './admin/admin-categories';
import type { ServiceData } from './admin/service-form';
import type { PlanData } from './admin/plan-form';
import type { CategoryData } from './admin/category-form';

interface AdminPanelProps {
  onRefresh: () => void;
}

export function AdminPanel({ onRefresh }: AdminPanelProps) {
  const [open, setOpen] = useState(false);
  const [activeTab, setActiveTab] = useState('dashboard');
  const [loading, setLoading] = useState(true);

  // Data states
  const [allServices, setAllServices] = useState<ServiceData[]>([]);
  const [allPlans, setAllPlans] = useState<PlanData[]>([]);
  const [categories, setCategories] = useState<CategoryData[]>([]);

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const [servicesRes, plansRes, categoriesRes] = await Promise.all([
        fetch('/api/services?all=true'),
        fetch('/api/plans?all=true'),
        fetch('/api/categories'),
      ]);

      const servicesData = await servicesRes.json();
      const plansData = await plansRes.json();
      const categoriesData = await categoriesRes.json();

      setAllServices(servicesData.services || []);
      setAllPlans(plansData.plans || []);
      setCategories(categoriesData.categories || []);
    } catch (error) {
      console.error('Failed to fetch admin data:', error);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (open) {
      fetchData();
    }
  }, [open, fetchData]);

  const handleDataChange = useCallback(() => {
    fetchData();
    onRefresh();
  }, [fetchData, onRefresh]);

  // Dashboard stats
  const visibleServices = allServices.filter((s) => s.visible).length;
  const hiddenServices = allServices.filter((s) => !s.visible).length;
  const visiblePlans = allPlans.filter((p) => p.visible).length;
  const hiddenPlans = allPlans.filter((p) => !p.visible).length;
  const featuredServices = allServices.filter((s) => s.featured).length;
  const featuredPlans = allPlans.filter((p) => p.featured).length;

  return (
    <>
      {/* Admin Button */}
      <button
        onClick={() => setOpen(true)}
        className="fixed bottom-6 right-6 z-40 w-11 h-11 rounded-full bg-gray-800/80 hover:bg-gray-700 text-white shadow-lg flex items-center justify-center transition-all hover:scale-105"
        title="Admin Panel"
      >
        <Settings className="w-5 h-5" />
      </button>

      {/* Admin Dialog */}
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent
          className="sm:max-w-4xl max-w-[calc(100%-1rem)] max-h-[95vh] p-0 flex flex-col overflow-hidden"
          showCloseButton={true}
        >
          <DialogHeader className="px-6 pt-6 pb-0 shrink-0">
            <div className="flex items-center justify-between">
              <div>
                <DialogTitle className="text-xl" style={{ color: '#002B5C' }}>
                  BusinessVance Admin
                </DialogTitle>
                <DialogDescription>
                  Manage your services, plans, and categories.
                </DialogDescription>
              </div>
              <div
                className="w-9 h-9 rounded-lg flex items-center justify-center"
                style={{ backgroundColor: '#002B5C' }}
              >
                <Settings className="w-4 h-4 text-white" />
              </div>
            </div>
          </DialogHeader>

          {loading ? (
            <div className="p-6 space-y-4">
              <Skeleton className="h-10 w-full" />
              <div className="grid grid-cols-3 gap-4">
                {Array.from({ length: 6 }).map((_, i) => (
                  <Skeleton key={i} className="h-24 w-full rounded-xl" />
                ))}
              </div>
            </div>
          ) : (
            <Tabs
              value={activeTab}
              onValueChange={setActiveTab}
              className="flex-1 flex flex-col min-h-0 px-6 pb-6"
            >
              <TabsList className="shrink-0 mb-4">
                <TabsTrigger value="dashboard">Dashboard</TabsTrigger>
                <TabsTrigger value="services">Services</TabsTrigger>
                <TabsTrigger value="plans">Plans</TabsTrigger>
                <TabsTrigger value="categories">Categories</TabsTrigger>
              </TabsList>

              <TabsContent
                value="dashboard"
                className="flex-1 overflow-y-auto bv-scrollbar -mx-1"
              >
                <AdminDashboard
                  totalServices={allServices.length}
                  totalPlans={allPlans.length}
                  visibleServices={visibleServices}
                  hiddenServices={hiddenServices}
                  visiblePlans={visiblePlans}
                  hiddenPlans={hiddenPlans}
                  featuredServices={featuredServices}
                  featuredPlans={featuredPlans}
                  totalCategories={categories.length}
                />
              </TabsContent>

              <TabsContent
                value="services"
                className="flex-1 overflow-y-auto bv-scrollbar -mx-1"
              >
                <AdminServices
                  services={allServices}
                  categories={categories}
                  onDataChange={handleDataChange}
                />
              </TabsContent>

              <TabsContent
                value="plans"
                className="flex-1 overflow-y-auto bv-scrollbar -mx-1"
              >
                <AdminPlans
                  plans={allPlans}
                  categories={categories}
                  onDataChange={handleDataChange}
                />
              </TabsContent>

              <TabsContent
                value="categories"
                className="flex-1 overflow-y-auto bv-scrollbar -mx-1"
              >
                <AdminCategories
                  categories={categories}
                  onDataChange={handleDataChange}
                />
              </TabsContent>
            </Tabs>
          )}
        </DialogContent>
      </Dialog>
    </>
  );
}