'use client';

import { useState, useEffect } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
import { iconNames } from '@/lib/icons';
import type { CategoryData } from './category-form';

export interface ServiceData {
  id: string;
  name: string;
  description: string;
  price: number;
  icon: string;
  buttonLabel: string;
  buttonType: string;
  buttonUrl: string;
  woocommerceProductId: string;
  categoryId: string | null;
  visible: boolean;
  featured: boolean;
  displayOrder: number;
  category?: CategoryData | null;
}

interface ServiceFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  service: ServiceData | null;
  categories: CategoryData[];
  onSave: () => void;
}

export function ServiceForm({
  open,
  onOpenChange,
  service,
  categories,
  onSave,
}: ServiceFormProps) {
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [price, setPrice] = useState('');
  const [icon, setIcon] = useState('FileText');
  const [buttonLabel, setButtonLabel] = useState('ADD TO CART');
  const [buttonType, setButtonType] = useState('cart');
  const [woocommerceProductId, setWooId] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [visible, setVisible] = useState(true);
  const [featured, setFeatured] = useState(false);
  const [displayOrder, setDisplayOrder] = useState('0');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (service) {
      setName(service.name);
      setDescription(service.description);
      setPrice(service.price.toString());
      setIcon(service.icon);
      setButtonLabel(service.buttonLabel);
      setButtonType(service.buttonType);
      setWooId(service.woocommerceProductId || '');
      setCategoryId(service.categoryId || '');
      setVisible(service.visible);
      setFeatured(service.featured);
      setDisplayOrder(service.displayOrder.toString());
    } else {
      setName('');
      setDescription('');
      setPrice('');
      setIcon('FileText');
      setButtonLabel('ADD TO CART');
      setButtonType('cart');
      setWooId('');
      setCategoryId('');
      setVisible(true);
      setFeatured(false);
      setDisplayOrder('0');
    }
  }, [service, open]);

  const handleSave = async () => {
    if (!name.trim()) {
      toast.error('Service name is required');
      return;
    }
    setSaving(true);
    try {
      const isEdit = !!service?.id;
      const method = isEdit ? 'PUT' : 'POST';
      const url = '/api/services';

      const body: Record<string, unknown> = {
        name,
        description,
        price: parseFloat(price) || 0,
        icon,
        buttonLabel,
        buttonType,
        woocommerceProductId: wooId,
        categoryId: categoryId || null,
        visible,
        featured,
        displayOrder: parseInt(displayOrder) || 0,
      };

      if (isEdit) body.id = service.id;

      const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });

      if (!res.ok) throw new Error('Failed to save service');

      toast.success(
        isEdit ? 'Service updated successfully' : 'Service created successfully'
      );
      onSave();
      onOpenChange(false);
    } catch {
      toast.error('Failed to save service');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg max-h-[90vh]">
        <DialogHeader>
          <DialogTitle>
            {service ? 'Edit Service' : 'New Service'}
          </DialogTitle>
          <DialogDescription>
            {service
              ? 'Update the service details below.'
              : 'Add a new service to your price list.'}
          </DialogDescription>
        </DialogHeader>

        <ScrollArea className="max-h-[60vh] pr-2 bv-scrollbar">
          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label htmlFor="svc-name">Name</Label>
              <Input
                id="svc-name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Service name"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="svc-desc">Description</Label>
              <Textarea
                id="svc-desc"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Service description"
                rows={3}
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="svc-price">Price (ZAR)</Label>
                <Input
                  id="svc-price"
                  type="number"
                  value={price}
                  onChange={(e) => setPrice(e.target.value)}
                  placeholder="0"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="svc-order">Display Order</Label>
                <Input
                  id="svc-order"
                  type="number"
                  value={displayOrder}
                  onChange={(e) => setDisplayOrder(e.target.value)}
                  placeholder="0"
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label>Icon</Label>
              <Select value={icon} onValueChange={setIcon}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Select icon" />
                </SelectTrigger>
                <SelectContent className="max-h-60 bv-scrollbar">
                  {iconNames.map((name) => (
                    <SelectItem key={name} value={name}>
                      {name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="svc-btn-label">Button Label</Label>
                <Input
                  id="svc-btn-label"
                  value={buttonLabel}
                  onChange={(e) => setButtonLabel(e.target.value)}
                  placeholder="ADD TO CART"
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
                    <SelectItem value="quote">Quote</SelectItem>
                    <SelectItem value="booking">Booking</SelectItem>
                    <SelectItem value="link">Link</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="svc-woo">WooCommerce Product ID</Label>
              <Input
                id="svc-woo"
                value={woocommerceProductId}
                onChange={(e) => setWooId(e.target.value)}
                placeholder="Optional product ID"
              />
            </div>

            <div className="space-y-2">
              <Label>Category</Label>
              <Select
                value={categoryId}
                onValueChange={setCategoryId}
              >
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

            <div className="flex items-center justify-between">
              <Label htmlFor="svc-visible">Visible</Label>
              <Switch
                id="svc-visible"
                checked={visible}
                onCheckedChange={setVisible}
              />
            </div>

            <div className="flex items-center justify-between">
              <Label htmlFor="svc-featured">Featured</Label>
              <Switch
                id="svc-featured"
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