'use client';

import { motion } from 'framer-motion';
import { Coins, ShoppingCart } from 'lucide-react';
import { Skeleton } from '@/components/ui/skeleton';
import { IconFromName } from '@/lib/icons';

export interface ServiceData {
  id: string;
  name: string;
  description: string;
  price: number;
  icon: string;
  buttonLabel: string;
  buttonType: string;
  featured?: boolean;
}

interface ServiceTableProps {
  services: ServiceData[];
  loading?: boolean;
}

function ServiceTableSkeleton() {
  return (
    <div className="space-y-3">
      {Array.from({ length: 5 }).map((_, i) => (
        <div
          key={i}
          className="flex items-center gap-4 p-4"
          style={{ backgroundColor: i % 2 === 0 ? '#F8F9FA' : '#FFFFFF' }}
        >
          <Skeleton className="h-10 w-10 rounded-lg" />
          <div className="flex-1 space-y-2">
            <Skeleton className="h-4 w-48" />
            <Skeleton className="h-3 w-full max-w-md" />
          </div>
          <Skeleton className="h-4 w-20" />
          <Skeleton className="h-8 w-28 rounded-md" />
        </div>
      ))}
    </div>
  );
}

export function ServiceTable({ services, loading }: ServiceTableProps) {
  if (loading) return <ServiceTableSkeleton />;

  return (
    <>
      {/* Desktop Table */}
      <div className="hidden md:block overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr style={{ backgroundColor: '#002B5C' }}>
              <th className="text-left px-4 py-3 text-white font-semibold uppercase tracking-wider text-xs">
                Service
              </th>
              <th className="text-left px-4 py-3 text-white font-semibold uppercase tracking-wider text-xs">
                Description
              </th>
              <th className="text-right px-4 py-3 text-white font-semibold uppercase tracking-wider text-xs w-48">
                Price (ZAR)
              </th>
            </tr>
          </thead>
          <tbody>
            {services.map((service, index) => (
              <motion.tr
                key={service.id}
                initial={{ opacity: 0, x: -10 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: index * 0.05, duration: 0.3 }}
                style={{
                  backgroundColor: index % 2 === 0 ? '#F8F9FA' : '#FFFFFF',
                }}
                className="border-b border-gray-100 hover:bg-gray-50 transition-colors"
              >
                <td className="px-4 py-4">
                  <div className="flex items-center gap-3">
                    <div
                      className="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
                      style={{ backgroundColor: 'rgba(0, 43, 92, 0.08)' }}
                    >
                      <IconFromName
                        name={service.icon}
                        className="w-5 h-5"
                        style={{ color: '#002B5C' }}
                      />
                    </div>
                    <span
                      className="font-semibold whitespace-nowrap"
                      style={{ color: '#002B5C' }}
                    >
                      {service.name}
                    </span>
                    {service.featured && (
                      <span className="text-xs px-2 py-0.5 rounded-full font-medium text-white" style={{ backgroundColor: '#D4AF37' }}>
                        Featured
                      </span>
                    )}
                  </div>
                </td>
                <td className="px-4 py-4 text-gray-600 text-sm leading-relaxed">
                  {service.description}
                </td>
                <td className="px-4 py-4">
                  <div className="flex items-center justify-end gap-3">
                    <span
                      className="font-bold text-lg whitespace-nowrap"
                      style={{ color: '#002B5C' }}
                    >
                      R {service.price.toLocaleString()}
                    </span>
                    <button
                      className="text-xs font-bold tracking-wider px-3 py-1.5 h-8 rounded text-white transition-colors duration-200"
                      style={{ backgroundColor: '#D4AF37' }}
                      onMouseEnter={(e) => {
                        e.currentTarget.style.backgroundColor = '#B8962E';
                      }}
                      onMouseLeave={(e) => {
                        e.currentTarget.style.backgroundColor = '#D4AF37';
                      }}
                    >
                      {service.buttonType === 'quote' ? (
                        service.buttonLabel
                      ) : (
                        <>
                          <ShoppingCart className="w-3 h-3 mr-1 inline" />
                          {service.buttonLabel}
                        </>
                      )}
                    </button>
                  </div>
                </td>
              </motion.tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Mobile Cards */}
      <div className="md:hidden space-y-3">
        {services.map((service, index) => (
          <motion.div
            key={service.id}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.05, duration: 0.3 }}
            className="rounded-xl border border-gray-100 p-4 bg-white shadow-sm"
            style={{ backgroundColor: index % 2 === 0 ? '#F8F9FA' : '#FFFFFF' }}
          >
            <div className="flex items-start gap-3 mb-3">
              <div
                className="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
                style={{ backgroundColor: 'rgba(0, 43, 92, 0.08)' }}
              >
                <IconFromName
                  name={service.icon}
                  className="w-5 h-5"
                  style={{ color: '#002B5C' }}
                />
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <span
                    className="font-semibold"
                    style={{ color: '#002B5C' }}
                  >
                    {service.name}
                  </span>
                  {service.featured && (
                    <span className="text-xs px-2 py-0.5 rounded-full font-medium text-white shrink-0" style={{ backgroundColor: '#D4AF37' }}>
                      Featured
                    </span>
                  )}
                </div>
                <p className="text-gray-600 text-sm mt-1 leading-relaxed">
                  {service.description}
                </p>
              </div>
            </div>
            <div className="flex items-center justify-between pt-3 border-t border-gray-100">
              <span
                className="font-bold text-lg"
                style={{ color: '#002B5C' }}
              >
                R {service.price.toLocaleString()}
              </span>
              <button
                className="text-xs font-bold tracking-wider px-3 py-1.5 h-8 rounded text-white transition-colors duration-200"
                style={{ backgroundColor: '#D4AF37' }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.backgroundColor = '#B8962E';
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.backgroundColor = '#D4AF37';
                }}
              >
                {service.buttonType === 'quote' ? (
                  service.buttonLabel
                ) : (
                  <>
                    <ShoppingCart className="w-3 h-3 mr-1 inline" />
                    {service.buttonLabel}
                  </>
                )}
              </button>
            </div>
          </motion.div>
        ))}
      </div>
    </>
  );
}