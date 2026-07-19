'use client';

import {
  Package,
  CreditCard,
  Eye,
  EyeOff,
  Star,
} from 'lucide-react';

interface AdminDashboardProps {
  totalServices: number;
  totalPlans: number;
  visibleServices: number;
  hiddenServices: number;
  visiblePlans: number;
  hiddenPlans: number;
  featuredServices: number;
  featuredPlans: number;
  totalCategories: number;
}

export function AdminDashboard({
  totalServices,
  totalPlans,
  visibleServices,
  hiddenServices,
  visiblePlans,
  hiddenPlans,
  featuredServices,
  featuredPlans,
  totalCategories,
}: AdminDashboardProps) {
  const stats = [
    {
      label: 'Total Services',
      value: totalServices,
      icon: Package,
      color: '#002B5C',
      sub: `${visibleServices} visible, ${hiddenServices} hidden`,
    },
    {
      label: 'Total Plans',
      value: totalPlans,
      icon: CreditCard,
      color: '#008080',
      sub: `${visiblePlans} visible, ${hiddenPlans} hidden`,
    },
    {
      label: 'Visible Items',
      value: visibleServices + visiblePlans,
      icon: Eye,
      color: '#16a34a',
      sub: 'Publicly displayed',
    },
    {
      label: 'Hidden Items',
      value: hiddenServices + hiddenPlans,
      icon: EyeOff,
      color: '#dc2626',
      sub: 'Not displayed',
    },
    {
      label: 'Featured',
      value: featuredServices + featuredPlans,
      icon: Star,
      color: '#D4AF37',
      sub: 'Featured items',
    },
    {
      label: 'Categories',
      value: totalCategories,
      icon: Package,
      color: '#002B5C',
      sub: 'Service categories',
    },
  ];

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-bold" style={{ color: '#002B5C' }}>
          Dashboard Overview
        </h2>
        <p className="text-sm text-gray-500 mt-1">
          Summary of your BusinessVance services and plans.
        </p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {stats.map((stat) => (
          <div
            key={stat.label}
            className="rounded-xl border border-gray-200 p-5 bg-white hover:shadow-md transition-shadow"
          >
            <div className="flex items-center gap-4">
              <div
                className="w-11 h-11 rounded-lg flex items-center justify-center"
                style={{ backgroundColor: stat.color + '15' }}
              >
                <stat.icon
                  className="w-5 h-5"
                  style={{ color: stat.color }}
                />
              </div>
              <div>
                <p className="text-2xl font-bold" style={{ color: stat.color }}>
                  {stat.value}
                </p>
                <p className="text-sm font-medium text-gray-600">
                  {stat.label}
                </p>
                <p className="text-xs text-gray-400">{stat.sub}</p>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}