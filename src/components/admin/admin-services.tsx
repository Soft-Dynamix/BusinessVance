'use client';

import { useState } from 'react';
import { toast } from 'sonner';
import { Pencil, Trash2, Eye, EyeOff, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { ServiceForm, type ServiceData } from './service-form';
import { CategoryForm, type CategoryData } from './category-form';

interface AdminServicesProps {
  services: ServiceData[];
  categories: CategoryData[];
  onDataChange: () => void;
}

export function AdminServices({
  services,
  categories,
  onDataChange,
}: AdminServicesProps) {
  const [formOpen, setFormOpen] = useState(false);
  const [editService, setEditService] = useState<ServiceData | null>(null);

  const handleDelete = async (id: string, name: string) => {
    if (!confirm(`Are you sure you want to delete "${name}"?`)) return;

    try {
      const res = await fetch(`/api/services?id=${id}`, { method: 'DELETE' });
      if (!res.ok) throw new Error('Failed to delete');
      toast.success(`"${name}" deleted successfully`);
      onDataChange();
    } catch {
      toast.error('Failed to delete service');
    }
  };

  const handleToggleVisibility = async (service: ServiceData) => {
    try {
      const res = await fetch('/api/services', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: service.id, visible: !service.visible }),
      });
      if (!res.ok) throw new Error('Failed to update');
      toast.success(
        `"${service.name}" is now ${service.visible ? 'hidden' : 'visible'}`
      );
      onDataChange();
    } catch {
      toast.error('Failed to toggle visibility');
    }
  };

  const handleEdit = (service: ServiceData) => {
    setEditService(service);
    setFormOpen(true);
  };

  const handleCreate = () => {
    setEditService(null);
    setFormOpen(true);
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-xl font-bold" style={{ color: '#002B5C' }}>
            Services
          </h2>
          <p className="text-sm text-gray-500 mt-1">
            Manage your once-off reports and services.
          </p>
        </div>
        <Button
          onClick={handleCreate}
          className="font-semibold text-sm text-white hover:opacity-90"
          style={{ backgroundColor: '#D4AF37' }}
        >
          <Plus className="w-4 h-4 mr-1" /> Add Service
        </Button>
      </div>

      <ScrollArea className="max-h-[60vh]">
        <div className="space-y-2 pr-2">
          {services.length === 0 ? (
            <p className="text-sm text-gray-400 text-center py-8">
              No services yet. Click &quot;Add Service&quot; to create one.
            </p>
          ) : (
            services.map((service) => (
              <div
                key={service.id}
                className={`flex items-center gap-3 p-3 rounded-lg border bg-white ${
                  !service.visible ? 'opacity-60' : ''
                }`}
              >
                {/* Drag Handle */}
                <div className="text-gray-300 cursor-grab">
                  <span className="text-sm">⠿</span>
                </div>

                {/* Info */}
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="font-medium text-sm truncate">
                      {service.name}
                    </span>
                    {service.featured && (
                      <Badge className="text-xs bg-[#D4AF37] text-white border-0 px-1.5 py-0">
                        ★
                      </Badge>
                    )}
                    {!service.visible && (
                      <Badge variant="secondary" className="text-xs px-1.5 py-0">
                        Hidden
                      </Badge>
                    )}
                  </div>
                  <p className="text-xs text-gray-400 truncate">
                    R {service.price.toLocaleString()} · {service.buttonType} ·
                    Order: {service.displayOrder}
                    {service.category && ` · ${service.category.name}`}
                  </p>
                </div>

                {/* Actions */}
                <div className="flex items-center gap-1 shrink-0">
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8"
                    onClick={() => handleToggleVisibility(service)}
                    title={service.visible ? 'Hide' : 'Show'}
                  >
                    {service.visible ? (
                      <Eye className="w-4 h-4 text-gray-500" />
                    ) : (
                      <EyeOff className="w-4 h-4 text-gray-400" />
                    )}
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8"
                    onClick={() => handleEdit(service)}
                  >
                    <Pencil className="w-4 h-4 text-gray-500" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 hover:text-red-500 hover:bg-red-50"
                    onClick={() => handleDelete(service.id, service.name)}
                  >
                    <Trash2 className="w-4 h-4" />
                  </Button>
                </div>
              </div>
            ))
          )}
        </div>
      </ScrollArea>

      <ServiceForm
        open={formOpen}
        onOpenChange={setFormOpen}
        service={editService}
        categories={categories}
        onSave={onDataChange}
      />
    </div>
  );
}