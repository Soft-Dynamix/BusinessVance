'use client';

import { useState, useEffect } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogDescription,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { X, Plus } from 'lucide-react';
import type { CategoryData } from './category-form';

export interface PlanFeatureData {
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
  buttonUrl: string;
  woocommerceProductId: string;
  categoryId: string | null;
  visible: boolean;
  featured: boolean;
  displayOrder: number;
  features: PlanFeatureData[];
  category?: CategoryData | null;
}

interface PlanFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  plan: PlanData | null;
  categories: CategoryData[];
  onSave: () => void;
}

export function PlanForm({
  open,
  onOpenChange,
  plan,
  categories,
  onSave,
}: PlanFormProps) {
  const [name, setName] = useState('');
  const [subtitle, setSubtitle] = useState('');
  const [price, setPrice] = useState('');
  const [color, setColor] = useState('#002B5C');
  const [buttonLabel, setButtonLabel] = useState('GET STARTED');
  const [buttonType, setButtonType] = useState('cart');
  const [woocommerceProductId, setWooId] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [visible, setVisible] = useState(true);
  const [featured, setFeatured] = useState(false);
  const [displayOrder, setDisplayOrder] = useState('0');
  const [features, setFeatures] = useState<string[]>([]);
  const [newFeature, setNewFeature] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (plan) {
      setName(plan.name);
      setSubtitle(plan.subtitle);
      setPrice(plan.price.toString());
      setColor(plan.color);
      setButtonLabel(plan.buttonLabel);
      setButtonType(plan.buttonType);
      setWooId(plan.woocommerceProductId || '');
      setCategoryId(plan.categoryId || '');
      setVisible(plan.visible);
      setFeatured(plan.featured);
      setDisplayOrder(plan.displayOrder.toString());
      setFeatures(plan.features.map((f) => f.text));
    } else {
      setName('');
      setSubtitle('');
      setPrice('');
      setColor('#002B5C');
      setButtonLabel('GET STARTED');
      setButtonType('cart');
      setWooId('');
      setCategoryId('');
      setVisible(true);
      setFeatured(false);
      setDisplayOrder('0');
      setFeatures([]);
    }
    setNewFeature('');
  }, [plan, open]);

  const addFeature = () => {
    if (newFeature.trim()) {
      setFeatures([...features, newFeature.trim()]);
      setNewFeature('');
    }
  };

  const removeFeature = (index: number) => {
    setFeatures(features.filter((_, i) => i !== index));
  };

  const handleSave = async () => {
    if (!name.trim()) {
      toast.error('Plan name is required');
      return;
    }
    setSaving(true);
    try {
      const isEdit = !!plan?.id;
      const method = isEdit ? 'PUT' : 'POST';
      const url = '/api/plans';

      const body: Record<string, unknown> = {
        name,
        subtitle,
        price: parseFloat(price) || 0,
        color,
        buttonLabel,
        buttonType,
        woocommerceProductId: wooId,
        categoryId: categoryId || null,
        visible,
        featured,
        displayOrder: parseInt(displayOrder) || 0,
        features,
      };

      if (isEdit) body.id = plan.id;

      const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });

      if (!res.ok) throw new Error('Failed to save plan');

      toast.success(
        isEdit ? 'Plan updated successfully' : 'Plan created successfully'
      );
      onSave();
      onOpenChange(false);
    } catch {
      toast.error('Failed to save plan');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg max-h-[90vh]">
        <DialogHeader>
          <DialogTitle>
            {plan ? 'Edit Plan' : 'New Plan'}
          </DialogTitle>
          <DialogDescription>
            {plan
              ? 'Update the subscription plan details.'
              : 'Create a new monthly subscription plan.'}
          </DialogDescription>
        </DialogHeader>

        <ScrollArea className="max-h-[60vh] pr-2 bv-scrollbar">
          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label htmlFor="plan-name">Name</Label>
              <Input
                id="plan-name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Plan name"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="plan-subtitle">Subtitle</Label>
              <Input
                id="plan-subtitle"
                value={subtitle}
                onChange={(e) => setSubtitle(e.target.value)}
                placeholder="Plan subtitle"
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="plan-price">Price (ZAR)</Label>
                <Input
                  id="plan-price"
                  type="number"
                  value={price}
                  onChange={(e) => setPrice(e.target.value)}
                  placeholder="0"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="plan-order">Display Order</Label>
                <Input
                  id="plan-order"
                  type="number"
                  value={displayOrder}
                  onChange={(e) => setDisplayOrder(e.target.value)}
                  placeholder="0"
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label>Color</Label>
              <div className="flex items-center gap-3">
                <input
                  type="color"
                  value={color}
                  onChange={(e) => setColor(e.target.value)}
                  className="w-10 h-10 rounded border border-gray-200 cursor-pointer p-1"
                />
                <Input
                  value={color}
                  onChange={(e) => setColor(e.target.value)}
                  placeholder="#002B5C"
                  className="w-32"
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="plan-btn-label">Button Label</Label>
                <Input
                  id="plan-btn-label"
                  value={buttonLabel}
                  onChange={(e) => setButtonLabel(e.target.value)}
                  placeholder="GET STARTED"
                />
              </div>

              <div className="space-y-2">
                <Label>Button Type</Label>
                <Select value={buttonType} onValueChange={setButtonType}>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="cart">Cart</SelectItem>
                    <SelectItem value="link">Link</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="plan-woo">WooCommerce Product ID</Label>
              <Input
                id="plan-woo"
                value={woocommerceProductId}
                onChange={(e) => setWooId(e.target.value)}
                placeholder="Optional product ID"
              />
            </div>

            <div className="space-y-2">
              <Label>Category</Label>
              <Select value={categoryId} onValueChange={setCategoryId}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Select category" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">No Category</SelectItem>
                  {categories.map((cat) => (
                    <SelectItem key={cat.id} value={cat.id}>
                      {cat.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Features */}
            <div className="space-y-2">
              <Label>Features</Label>
              <div className="space-y-2">
                {features.map((f, i) => (
                  <div key={i} className="flex items-center gap-2">
                    <span className="flex-1 text-sm bg-gray-50 rounded px-3 py-2 border">
                      {f}
                    </span>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8 text-red-500 hover:text-red-600 hover:bg-red-50 shrink-0"
                      onClick={() => removeFeature(i)}
                    >
                      <X className="w-4 h-4" />
                    </Button>
                  </div>
                ))}
                <div className="flex gap-2">
                  <Input
                    value={newFeature}
                    onChange={(e) => setNewFeature(e.target.value)}
                    placeholder="Add a feature..."
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault();
                        addFeature();
                      }
                    }}
                  />
                  <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={addFeature}
                    className="shrink-0"
                  >
                    <Plus className="w-4 h-4" />
                  </Button>
                </div>
              </div>
            </div>

            <div className="flex items-center justify-between">
              <Label htmlFor="plan-visible">Visible</Label>
              <Switch
                id="plan-visible"
                checked={visible}
                onCheckedChange={setVisible}
              />
            </div>

            <div className="flex items-center justify-between">
              <Label htmlFor="plan-featured">Featured</Label>
              <Switch
                id="plan-featured"
                checked={featured}
                onCheckedChange={setFeatured}
              />
            </div>
          </div>
        </ScrollArea>

        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={saving}
          >
            Cancel
          </Button>
          <Button onClick={handleSave} disabled={saving}>
            {saving ? 'Saving...' : 'Save'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}