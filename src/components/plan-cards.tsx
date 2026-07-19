'use client';

import { motion } from 'framer-motion';
import { Check, Star } from 'lucide-react';
import { Skeleton } from '@/components/ui/skeleton';

export interface PlanFeature {
  id: string;
  text: string;
}

export interface PlanData {
  id: string;
  name: string;
  subtitle: string;
  price: number;
  color: string;
  buttonLabel: string;
  buttonType: string;
  featured?: boolean;
  features: PlanFeature[];
}

interface PlanCardsProps {
  plans: PlanData[];
  loading?: boolean;
}

function PlanCardSkeleton() {
  return (
    <div className="rounded-xl border border-gray-200 overflow-hidden">
      <Skeleton className="h-28 w-full" />
      <div className="p-6 space-y-4">
        <Skeleton className="h-8 w-24 mx-auto" />
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-3/4" />
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-10 w-full mt-4 rounded-lg" />
      </div>
    </div>
  );
}

export function PlanCards({ plans, loading }: PlanCardsProps) {
  if (loading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {Array.from({ length: 3 }).map((_, i) => (
          <PlanCardSkeleton key={i} />
        ))}
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
      {plans.map((plan, index) => (
        <motion.div
          key={plan.id}
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: index * 0.15, duration: 0.5 }}
          whileHover={{ y: -4 }}
          className={`rounded-xl overflow-hidden border transition-shadow duration-300 ${
            plan.featured
              ? 'border-2 shadow-xl md:scale-105 md:z-10 relative'
              : 'border-gray-200 shadow-md'
          }`}
          style={{
            borderColor: plan.featured ? plan.color : undefined,
          }}
        >
          {/* Featured Badge */}
          {plan.featured && (
            <div
              className="absolute -top-0 left-1/2 -translate-x-1/2 z-20 px-4 py-1 text-white text-xs font-bold tracking-wider rounded-b-lg"
              style={{ backgroundColor: '#D4AF37' }}
            >
              <span className="flex items-center gap-1">
                <Star className="w-3 h-3 fill-white" /> RECOMMENDED
              </span>
            </div>
          )}

          {/* Colored Header */}
          <div
            className="px-6 pt-8 pb-6 text-center text-white"
            style={{ backgroundColor: plan.color }}
          >
            <h3 className="text-xl font-bold mb-1">{plan.name}</h3>
            <p className="text-sm opacity-90">{plan.subtitle}</p>
          </div>

          {/* Price */}
          <div className="px-6 py-6 text-center bg-white">
            <div className="flex items-baseline justify-center gap-1">
              <span className="text-sm font-medium text-gray-500">R</span>
              <span
                className="text-4xl font-bold"
                style={{ color: plan.color }}
              >
                {plan.price.toLocaleString()}
              </span>
              <span className="text-sm text-gray-500">/month</span>
            </div>

            {/* Gold Add to Cart */}
            <button
              className="mt-4 w-full font-bold tracking-wider text-sm h-10 rounded-lg text-white transition-all duration-200"
              style={{ backgroundColor: '#D4AF37' }}
              onMouseEnter={(e) => {
                e.currentTarget.style.backgroundColor = '#B8962E';
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.backgroundColor = '#D4AF37';
              }}
            >
              ADD TO CART
            </button>
          </div>

          {/* Features */}
          <div className="px-6 py-6 bg-white border-t border-gray-100">
            <ul className="space-y-3">
              {plan.features.map((feature) => (
                <li key={feature.id} className="flex items-start gap-3">
                  <Check
                    className="w-4 h-4 mt-0.5 shrink-0"
                    style={{ color: plan.color }}
                  />
                  <span className="text-sm text-gray-700 leading-snug">
                    {feature.text}
                  </span>
                </li>
              ))}
            </ul>
          </div>

          {/* CTA Button */}
          <div className="px-6 pb-6 bg-white">
            <button
              className="w-full font-semibold text-sm h-11 rounded-lg text-white transition-all duration-200"
              style={{ backgroundColor: plan.color }}
              onMouseEnter={(e) => {
                e.currentTarget.style.filter = 'brightness(0.9)';
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.filter = 'brightness(1)';
              }}
            >
              {plan.buttonLabel}
            </button>
          </div>
        </motion.div>
      ))}
    </div>
  );
}