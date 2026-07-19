'use client';

import { useState, useEffect, useCallback } from 'react';
import { motion } from 'framer-motion';
import { Coins, Crown } from 'lucide-react';
import { Skeleton } from '@/components/ui/skeleton';
import { PublicHeader } from '@/components/public-header';
import { ServiceTable, type ServiceData } from '@/components/service-table';
import { PlanCards, type PlanData } from '@/components/plan-cards';
import { PublicFooter } from '@/components/public-footer';
import { AdminPanel } from '@/components/admin-panel';

function PageSkeleton() {
  return (
    <div className="min-h-screen flex flex-col bg-white">
      {/* Header skeleton */}
      <div className="py-16 md:py-24 px-4">
        <div className="max-w-4xl mx-auto text-center space-y-4">
          <div className="flex justify-center">
            <Skeleton className="h-16 w-16 rounded-2xl" />
          </div>
          <Skeleton className="h-12 w-80 mx-auto" />
          <Skeleton className="h-4 w-60 mx-auto" />
          <Skeleton className="h-4 w-96 mx-auto" />
        </div>
      </div>

      {/* Price list skeleton */}
      <div className="max-w-6xl mx-auto px-4 py-8 space-y-4">
        <Skeleton className="h-14 w-full rounded" />
        <Skeleton className="h-6 w-64 mx-auto" />
        <div className="space-y-3 mt-6">
          {Array.from({ length: 5 }).map((_, i) => (
            <Skeleton key={i} className="h-16 w-full rounded" />
          ))}
        </div>
      </div>

      {/* Plans skeleton */}
      <div className="max-w-6xl mx-auto px-4 py-8 space-y-4">
        <Skeleton className="h-14 w-full rounded" />
        <Skeleton className="h-6 w-80 mx-auto" />
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-96 w-full rounded-xl" />
          ))}
        </div>
      </div>
    </div>
  );
}

export default function Home() {
  const [services, setServices] = useState<ServiceData[]>([]);
  const [plans, setPlans] = useState<PlanData[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchData = useCallback(async () => {
    try {
      const [servicesRes, plansRes] = await Promise.all([
        fetch('/api/services'),
        fetch('/api/plans'),
      ]);

      const servicesData = await servicesRes.json();
      const plansData = await plansRes.json();

      setServices(servicesData.services || []);
      setPlans(plansData.plans || []);
    } catch (error) {
      console.error('Failed to fetch data:', error);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  if (loading) return <PageSkeleton />;

  return (
    <div className="min-h-screen flex flex-col bg-white">
      <PublicHeader />

      {/* Price List Section */}
      <section className="max-w-6xl mx-auto px-4 py-8 md:py-12 w-full">
        {/* Section Header */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
          className="rounded-xl overflow-hidden mb-2"
          style={{ backgroundColor: '#002B5C' }}
        >
          <div className="flex items-center gap-3 px-6 py-4">
            <Coins className="w-6 h-6 text-white" />
            <span className="text-white font-bold uppercase tracking-wider text-lg">
              Price List
            </span>
          </div>
        </motion.div>

        <motion.p
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.2, duration: 0.5 }}
          className="text-center font-bold uppercase tracking-widest text-sm mb-8"
          style={{ color: '#D4AF37' }}
        >
          One-Time Reports &amp; Services
        </motion.p>

        {/* Service Table */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3, duration: 0.5 }}
          className="rounded-xl overflow-hidden border border-gray-200 shadow-sm"
        >
          <ServiceTable services={services} />
        </motion.div>
      </section>

      {/* Monthly Plans Section */}
      <section className="max-w-6xl mx-auto px-4 py-8 md:py-12 w-full">
        {/* Section Header */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
          className="rounded-xl overflow-hidden mb-2"
          style={{ backgroundColor: '#002B5C' }}
        >
          <div className="flex items-center gap-3 px-6 py-4">
            <Crown className="w-6 h-6 text-white" />
            <span className="text-white font-bold uppercase tracking-wider text-lg">
              Monthly Subscription Plans
            </span>
          </div>
        </motion.div>

        <motion.p
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.2, duration: 0.5 }}
          className="text-center font-bold uppercase tracking-widest text-sm mb-8"
          style={{ color: '#D4AF37' }}
        >
          Choose the Plan That Grows With You
        </motion.p>

        {/* Plan Cards */}
        <PlanCards plans={plans} />
      </section>

      {/* Footer */}
      <PublicFooter />

      {/* Admin Panel */}
      <AdminPanel onRefresh={fetchData} />
    </div>
  );
}