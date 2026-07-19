'use client';

import { useState } from 'react';
import { toast } from 'sonner';
import { Pencil, Trash2, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { CategoryForm, type CategoryData } from './category-form';

interface AdminCategoriesProps {
  categories: CategoryData[];
  onDataChange: () => void;
}

export function AdminCategories({
  categories,
  onDataChange,
}: AdminCategoriesProps) {
  const [formOpen, setFormOpen] = useState(false);
  const [editCategory, setEditCategory] = useState<CategoryData | null>(null);

  const handleDelete = async (id: string, name: string) => {
    if (!confirm(`Are you sure you want to delete "${name}"?\nServices and plans in this category will have their category removed.`))
      return;

    try {
      const res = await fetch(`/api/categories?id=${id}`, { method: 'DELETE' });
      if (!res.ok) throw new Error('Failed to delete');
      toast.success(`"${name}" deleted successfully`);
      onDataChange();
    } catch {
      toast.error('Failed to delete category');
    }
  };

  const handleEdit = (category: CategoryData) => {
    setEditCategory(category);
    setFormOpen(true);
  };

  const handleCreate = () => {
    setEditCategory(null);
    setFormOpen(true);
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-xl font-bold" style={{ color: '#002B5C' }}>
            Categories
          </h2>
          <p className="text-sm text-gray-500 mt-1">
            Organize your services and plans into categories.
          </p>
        </div>
        <Button
          onClick={handleCreate}
          className="font-semibold text-sm text-white hover:opacity-90"
          style={{ backgroundColor: '#D4AF37' }}
        >
          <Plus className="w-4 h-4 mr-1" /> Add Category
        </Button>
      </div>

      <ScrollArea className="max-h-[60vh]">
        <div className="space-y-2 pr-2">
          {categories.length === 0 ? (
            <p className="text-sm text-gray-400 text-center py-8">
              No categories yet. Click &quot;Add Category&quot; to create one.
            </p>
          ) : (
            categories.map((category) => (
              <div
                key={category.id}
                className="flex items-center gap-3 p-3 rounded-lg border bg-white"
              >
                <div
                  className="w-4 h-4 rounded-full shrink-0 border border-gray-200"
                  style={{ backgroundColor: category.color }}
                />
                <div className="flex-1 min-w-0">
                  <span className="font-medium text-sm">{category.name}</span>
                  <p className="text-xs text-gray-400">/{category.slug}</p>
                </div>
                <div className="flex items-center gap-1 shrink-0">
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8"
                    onClick={() => handleEdit(category)}
                  >
                    <Pencil className="w-4 h-4 text-gray-500" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 hover:text-red-500 hover:bg-red-50"
                    onClick={() => handleDelete(category.id, category.name)}
                  >
                    <Trash2 className="w-4 h-4" />
                  </Button>
                </div>
              </div>
            ))
          )}
        </div>
      </ScrollArea>

      <CategoryForm
        open={formOpen}
        onOpenChange={setFormOpen}
        category={editCategory}
        categories={categories}
        onSave={onDataChange}
      />
    </div>
  );
}