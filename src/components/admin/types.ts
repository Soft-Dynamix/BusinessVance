export interface Settings {
  [key: string]: string;
}

export interface CategoryItem {
  id: string;
  name: string;
  slug: string;
  color: string;
}

export interface ServiceItem {
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
  category?: CategoryItem | null;
}

export interface PlanItem {
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
  features: { id: string; text: string }[];
  category?: CategoryItem | null;
}

export interface IconItem {
  id: string;
  name: string;
  label: string;
  svgPath: string;
  category: string;
  displayOrder: number;
}

export type TabId = 'dashboard' | 'services' | 'plans' | 'categories' | 'icons' | 'settings';
