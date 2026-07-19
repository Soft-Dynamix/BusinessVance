'use client';

import { useState } from 'react';
import { toast } from 'sonner';
import { Pencil, Trash2, Eye, EyeOff, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { PlanForm, type PlanData } from './plan-form';
import type { CategoryData } from './category-form';

interface AdminPlansProps {
  plans: PlanData[];
  categories: CategoryData[];
  onDataChange: () => void;
}

export function AdminPlans({
  plans,
  categories,
  onDataChange,
}: AdminPlansProps) {
  const [formOpen, setFormOpen] = useState(false);
  const [editPlan, setEditPlan] = useState<PlanData | null>(null);

  const handleDelete = async (id: string, name: string) => {
    if (!confirm(`Are you sure you want to delete "${name}"?`)) return;

    try {
      const res = await fetch(`/api/plans?id=${id}`, { method: 'DELETE' });
      if (!res.ok) throw new Error('Failed to delete');
      toast.success(`"${name}" deleted successfully`);
      onDataChange();
    } catch {
      toast.error('Failed to delete plan');
    }
  };

  const handleToggleVisibility = async (plan: PlanData) => {
    try {
      const res = await fetch('/api/plans', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: plan.id, visible: !plan.visible }),
      });
      if (!res.ok) throw new Error('Failed to update');
      toast.success(
        `"${plan.name}" is now ${plan.visible ? 'hidden' : 'visible'}`
      );
      onDataChange();
    } catch {
      toast.error('Failed to toggle visibility');
    }
  };

  const handleEdit = (plan: PlanData) => {
    setEditPlan(plan);
    setFormOpen(true);
  };

  const handleCreate = () => {
    setEditPlan(null);
    setFormOpen(true);
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-xl font-bold" style={{ color: '#002B5C' }}>
            Plans
          </h2>
          <p className="text-sm text-gray-500 mt-1">
            Manage your monthly subscription plans.
          </p>
        </div>
        <Button
          onClick={handleCreate}
          className="font-semibold text-sm text-white hover:opacity-90"
          style={{ backgroundColor: '#D4AF37' }}
        >
          <Plus className="w-4 h-4 mr-1" /> Add Plan
        </Button>
      </div>

      <ScrollArea className="max-h-[60vh]">
        <div className="space-y-2 pr-2">
          {plans.length === 0 ? (
            <p className="text-sm text-gray-400 text-center py-8">
              No plans yet. Click &quot;Add Plan&quot; to create one.
            </p>
          ) : (
            plans.map((plan) => (
              <div
                key={plan.id}
                className={`p-4 rounded-lg border bg-white ${
                  !plan.visible ? 'opacity-60' : ''
                }`}
              >
                <div className="flex items-center gap-3">
                  <div className="text-gray-300 cursor-grab">
                    <span className="text-sm">⠿</span>
                  </div>

                  <div
                    className="w-3 h-8 rounded-full shrink-0"
                    style={{ backgroundColor: plan.color }}
                  />

                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="font-medium text-sm truncate">
                        {plan.name}
                      </span>
                      {plan.featured && (
                        <Badge className="text-xs bg-[#D4AF37] text-white border-0 px-1.5 py-0">
                          ★ Featured
                        </Badge>
                      )}
                      {!plan.visible && (
                        <Badge variant="secondary" className="text-xs px-1.5 py-0">
                          Hidden
                        </Badge>
                      )}
                    </div>
                    <p className="text-xs text-gray-400">
                      R {plan.price.toLocaleString()}/mo ·{' '}
                      {plan.features.length} features · Order: {plan.displayOrder}
                    </p>
                  </div>

                  <div className="flex items-center gap-1 shrink-0">
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8"
                      onClick={() => handleToggleVisibility(plan)}
                      title={plan.visible ? 'Hide' : 'Show'}
                    >
                      {plan.visible ? (
                        <Eye className="w-4 h-4 text-gray-500" />
                      ) : (
                        <EyeOff className="w-4 h-4 text-gray-400" />
                      )}
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8"
                      onClick={() => handleEdit(plan)}
                    >
                      <Pencil className="w-4 h-4 text-gray-500" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 hover:text-red-500 hover:bg-red-50"
                      onClick={() => handleDelete(plan.id, plan.name)}
                    >
                      <Trash2 className="w-4 h-4" />
                    </Button>
                  </div>
                </div>
              </div>
            ))
          )}
        </div>
      </ScrollArea>

      <PlanForm
        open={formOpen}
        onOpenChange={setFormOpen}
        plan={editPlan}
        categories={categories}
        onSave={onDataChange}
      />
    </div>
  );
}