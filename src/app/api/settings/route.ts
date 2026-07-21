import { db } from '@/lib/db';
import { NextRequest, NextResponse } from 'next/server';

const DEFAULT_SETTINGS: Record<string, string> = {
  // Branding
  brand_name: 'BUSINESSVANCE',
  brand_tagline: 'INSIGHT. STRATEGY. SUCCESS.',
  brand_description: 'Professional business reports and advisory services to help you make confident, informed decisions.',
  header_icon: 'shield',

  // Colors
  color_primary: '#0A2647',
  color_primary_dark: '#071a33',
  color_primary_light: '#144272',
  color_accent: '#F4A261',
  color_accent_alt: '#2A9D8F',
  color_gold: '#D4AF37',
  color_text: '#333333',
  color_text_light: '#666666',
  color_bg: '#ffffff',

  // Services Section
  services_section_title: 'PRICE LIST',
  services_section_subtitle: 'ONE-TIME REPORTS & SERVICES',
  show_services_section: 'true',

  // Plans Section
  plans_section_title: 'MONTHLY SUBSCRIPTION PLANS',
  plans_section_subtitle: 'CHOOSE THE PLAN THAT GROWS WITH YOU',
  show_plans_section: 'true',

  // Currency
  currency_symbol: 'R',
  currency_position: 'before',

  // Footer
  footer_company_name: 'BUSINESSVANCE CONSULTING',
  footer_website: 'www.studyvance.co.za',
  footer_phone: '082 377 7490',
  footer_email: 'info@businessvance.co.za',
  show_trust_badges: 'true',
  footer_copyright: '',

  // Layout
  container_max_width: '1200px',
  enable_animations: 'true',
  show_featured_badge: 'true',

  // Default button label
  default_service_button_label: 'ADD TO CART',

  // Trust badges JSON
  trust_badges: JSON.stringify([
    { icon: 'shield-check', text: 'PROFESSIONAL QUALITY REPORTS' },
    { icon: 'clock', text: 'FAST TURNAROUND' },
    { icon: 'lock', text: '100% CONFIDENTIAL & SECURE' },
    { icon: 'star', text: 'ACTIONABLE RECOMMENDATIONS' },
  ]),
};

export async function GET() {
  try {
    // Get all settings from DB
    const settings = await db.pluginSetting.findMany();
    const settingsMap: Record<string, string> = {};

    for (const s of settings) {
      settingsMap[s.key] = s.value;
    }

    // Merge with defaults (DB values override defaults)
    const merged = { ...DEFAULT_SETTINGS, ...settingsMap };

    return NextResponse.json({ settings: merged });
  } catch (error) {
    console.error('Error fetching settings:', error);
    return NextResponse.json(
      { settings: DEFAULT_SETTINGS },
      { status: 200 }
    );
  }
}

export async function PUT(request: NextRequest) {
  try {
    const body = await request.json();
    const { settings } = body;

    if (!settings || typeof settings !== 'object') {
      return NextResponse.json(
        { error: 'Settings object is required' },
        { status: 400 }
      );
    }

    // Upsert each setting
    const operations = Object.entries(settings).map(([key, value]) =>
      db.pluginSetting.upsert({
        where: { key },
        update: { value: String(value) },
        create: { key, value: String(value) },
      })
    );

    await Promise.all(operations);

    // Return the merged settings
    const allSettings = await db.pluginSetting.findMany();
    const settingsMap: Record<string, string> = {};
    for (const s of allSettings) {
      settingsMap[s.key] = s.value;
    }
    const merged = { ...DEFAULT_SETTINGS, ...settingsMap };

    return NextResponse.json({ settings: merged });
  } catch (error) {
    console.error('Error updating settings:', error);
    return NextResponse.json(
      { error: 'Failed to update settings' },
      { status: 500 }
    );
  }
}