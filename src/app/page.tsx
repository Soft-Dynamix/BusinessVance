'use client';

import { useEffect, useState, useCallback } from 'react';

/* ── Types ── */
interface Settings {
  [key: string]: string;
}

interface ServiceItem {
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
  category?: { id: string; name: string; slug: string; color: string } | null;
}

interface PlanItem {
  id: string;
  name: string;
  subtitle: string;
  price: number;
  color: string;
  buttonLabel: string;
  buttonType: string;
  buttonUrl: string;
  woocommerceProductId: string;
  visible: boolean;
  featured: boolean;
  displayOrder: number;
  features: { id: string; text: string }[];
}

interface TrustBadge {
  icon: string;
  text: string;
}

/* ── SVG icon paths (same as WordPress plugin) ── */
const iconPaths: Record<string, string> = {
  shield: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
  'shield-check': '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
  'check-circle': '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
  award: '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>',
  star: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
  crown: '<path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/>',
  lock: '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  clock: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
  'clipboard-list': '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
  search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
  users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  calculator: '<rect width="16" height="20" x="4" y="2" rx="2"/><line x1="8" x2="16" y1="6" y2="6"/><line x1="16" x2="16" y1="14" y2="18"/><line x1="8" x2="8" y1="10" y2="10.01"/><line x1="12" x2="12" y1="10" y2="10.01"/><line x1="16" x2="16" y1="10" y2="10.01"/><line x1="8" x2="8" y1="14" y2="14.01"/><line x1="12" x2="12" y1="14" y2="14.01"/><line x1="8" x2="8" y1="18" y2="18.01"/><line x1="12" x2="12" y1="18" y2="18.01"/>',
  megaphone: '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
  'trending-up': '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
  'shield-alert': '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
  'file-text': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
  presentation: '<path d="M2 3h20"/><path d="M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3"/><path d="m7 21 5-5 5 5"/>',
  'heart-pulse': '<path d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572"/><path d="M12 6l-2 4h4l-2 4"/>',
  handshake: '<path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/><path d="M3 4h8"/>',
  wrench: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
  check: '<polyline points="20 6 9 17 4 12"/>',
};

function Icon({ name, size = 24 }: { name: string; size?: number }) {
  const d = iconPaths[name];
  if (!d) return null;
  return (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" dangerouslySetInnerHTML={{ __html: d }} />
  );
}

/* ── Helpers ── */
function formatPrice(price: number, symbol: string, position: string): string {
  const formatted = price.toLocaleString('en-ZA');
  if (position === 'after') return `${formatted} ${symbol}`;
  return `${symbol}${formatted}`;
}

function getButtonClass(color: string): string {
  // Determine button class based on plan color
  const teal = ['#2A9D8F', '#21867A', '#008080', '#006699'].map(c => c.toLowerCase());
  const navy = ['#264653', '#0A2647', '#003366', '#004080', '#144272'].map(c => c.toLowerCase());
  const lower = color.toLowerCase();
  if (teal.includes(lower)) return 'bv-btn-teal';
  if (navy.includes(lower)) return 'bv-btn-navy';
  return 'bv-btn-gold';
}

function getButtonUrl(service: ServiceItem | PlanItem): string {
  if (service.buttonType === 'link' && service.buttonUrl) return service.buttonUrl;
  if ((service.buttonType === 'cart' || service.buttonType === 'subscription') && service.woocommerceProductId) {
    return `?add-to-cart=${service.woocommerceProductId}`;
  }
  return '#';
}

/* ── Component ── */
export default function Home() {
  const [settings, setSettings] = useState<Settings>({});
  const [services, setServices] = useState<ServiceItem[]>([]);
  const [plans, setPlans] = useState<PlanItem[]>([]);
  const [trustBadges, setTrustBadges] = useState<TrustBadge[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchData = useCallback(async () => {
    try {
      const [settingsRes, servicesRes, plansRes] = await Promise.all([
        fetch('/api/settings'),
        fetch('/api/services'),
        fetch('/api/plans'),
      ]);

      const settingsData = await settingsRes.json();
      const servicesData = await servicesRes.json();
      const plansData = await plansRes.json();

      const s = settingsData.settings || {};
      setSettings(s);

      // Parse trust badges from settings
      try {
        const badges = JSON.parse(s.trust_badges || '[]');
        setTrustBadges(badges);
      } catch {
        setTrustBadges([]);
      }

      // Filter and sort services
      const visibleServices = (servicesData.services || [])
        .filter((sv: ServiceItem) => sv.visible)
        .sort((a: ServiceItem, b: ServiceItem) => a.displayOrder - b.displayOrder);
      setServices(visibleServices);

      // Filter and sort plans
      const visiblePlans = (plansData.plans || [])
        .filter((p: PlanItem) => p.visible)
        .sort((a: PlanItem, b: PlanItem) => a.displayOrder - b.displayOrder);
      setPlans(visiblePlans);
    } catch (err) {
      console.error('Failed to fetch data:', err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Load CSS dynamically
  useEffect(() => {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '/bv-frontend.css';
    document.head.appendChild(link);
    return () => { document.head.removeChild(link); };
  }, []);

  // Derive values from settings with fallbacks
  const brandName = settings.brand_name || 'BUSINESSVANCE';
  const brandTagline = settings.brand_tagline || '';
  const brandDescription = settings.brand_description || '';
  const headerIcon = settings.header_icon || 'shield';
  const currencySymbol = settings.currency_symbol || 'R';
  const currencyPosition = settings.currency_position || 'before';
  const showServices = settings.show_services_section !== 'false';
  const showPlans = settings.show_plans_section !== 'false';
  const showTrustBadges = settings.show_trust_badges !== 'false';
  const showFeaturedBadge = settings.show_featured_badge !== 'false';
  const servicesTitle = settings.services_section_title || 'PRICE LIST';
  const servicesSubtitle = settings.services_section_subtitle || 'ONE-TIME REPORTS & SERVICES';
  const plansTitle = settings.plans_section_title || 'MONTHLY SUBSCRIPTION PLANS';
  const plansSubtitle = settings.plans_section_subtitle || 'CHOOSE THE PLAN THAT GROWS WITH YOU';
  const footerCompanyName = settings.footer_company_name || '';
  const footerWebsite = settings.footer_website || '';
  const footerPhone = settings.footer_phone || '';
  const footerEmail = settings.footer_email || '';
  const footerCopyright = settings.footer_copyright || '';
  const defaultButtonLabel = settings.default_service_button_label || 'ADD TO CART';
  const containerMaxWidth = settings.container_max_width || '1200px';

  // CSS custom properties from settings
  const cssVars: React.CSSProperties = {
    '--bv-color-primary': settings.color_primary || '#0A2647',
    '--bv-color-primary-dark': settings.color_primary_dark || '#071a33',
    '--bv-color-primary-light': settings.color_primary_light || '#144272',
    '--bv-color-accent': settings.color_accent || '#F4A261',
    '--bv-color-accent-alt': settings.color_accent_alt || '#2A9D8F',
    '--bv-color-gold': settings.color_gold || '#D4AF37',
    '--bv-color-text': settings.color_text || '#333333',
    '--bv-color-text-light': settings.color_text_light || '#666666',
    '--bv-color-bg': settings.color_bg || '#ffffff',
    '--bv-container-max-width': containerMaxWidth,
  } as React.CSSProperties;

  if (loading) {
    return (
      <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <div className="bv-loading">
          <div className="bv-loading-spinner" />
          <span>Loading...</span>
        </div>
      </div>
    );
  }

  return (
    <div className="bv-page-wrapper" style={{ ...cssVars, minHeight: '100vh', display: 'flex', flexDirection: 'column' }}>

      {/* ═══ HEADER ═══ */}
      <header className="bv-header">
        <div className="bv-header-inner">
          <div className="bv-header-stars">
            <Icon name="star" size={18} />
            <Icon name="star" size={18} />
            <Icon name="star" size={18} />
          </div>
          <div className="bv-header-brand">
            <div className="bv-shield-logo">
              <Icon name={headerIcon} size={36} />
            </div>
            <div className="bv-brand-text">
              <h1 className="bv-brand-name">{brandName}</h1>
              {brandTagline && <p className="bv-brand-tagline">{brandTagline}</p>}
            </div>
          </div>
          {brandDescription && <p className="bv-brand-description">{brandDescription}</p>}
        </div>
      </header>

      {/* ═══ SERVICES TABLE ═══ */}
      {showServices && services.length > 0 && (
        <section className="bv-services-section">
          <div className="bv-section-header">
            <div className="bv-section-banner">{servicesTitle}</div>
            <div className="bv-section-sub">{servicesSubtitle}</div>
          </div>

          {/* Desktop Table */}
          <div className="bv-table-wrapper bv-desktop-only">
            <table className="bv-services-table">
              <thead>
                <tr>
                  <th style={{ width: '28%' }}>SERVICE</th>
                  <th style={{ width: '47%' }}>DESCRIPTION</th>
                  <th className="bv-col-price" style={{ width: '25%' }}>PRICE ({currencySymbol} ZAR)</th>
                </tr>
              </thead>
              <tbody>
                {services.map((s) => (
                  <tr key={s.id}>
                    <td>
                      <div className="bv-service-info">
                        <div className="bv-service-icon">
                          <Icon name={s.icon} size={20} />
                        </div>
                        <div className="bv-service-name">
                          {s.name}
                          {showFeaturedBadge && s.featured && <span className="bv-featured-badge">★ Popular</span>}
                        </div>
                      </div>
                    </td>
                    <td><p className="bv-service-desc">{s.description}</p></td>
                    <td>
                      <div className="bv-price-block">
                        <span className="bv-service-price">{formatPrice(s.price, currencySymbol, currencyPosition)}</span>
                        <a
                          href={getButtonUrl(s)}
                          className="bv-btn bv-btn-gold bv-btn-sm"
                          onClick={e => { if (getButtonUrl(s) === '#') e.preventDefault(); }}
                        >
                          {s.buttonLabel || defaultButtonLabel}
                        </a>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Mobile Cards */}
          <div className="bv-mobile-cards bv-mobile-only">
            {services.map((s) => (
              <div className="bv-service-card" key={s.id}>
                <div className="bv-card-header">
                  <div className="bv-card-icon">
                    <Icon name={s.icon} size={20} />
                  </div>
                  <div>
                    <h3 className="bv-card-name">
                      {s.name}
                      {showFeaturedBadge && s.featured && <span className="bv-featured-badge">★ Popular</span>}
                    </h3>
                  </div>
                </div>
                <p className="bv-card-desc">{s.description}</p>
                <div className="bv-card-footer">
                  <span className="bv-card-price">{formatPrice(s.price, currencySymbol, currencyPosition)}</span>
                  <a
                    href={getButtonUrl(s)}
                    className="bv-btn bv-btn-gold bv-btn-sm"
                    onClick={e => { if (getButtonUrl(s) === '#') e.preventDefault(); }}
                  >
                    {s.buttonLabel || defaultButtonLabel}
                  </a>
                </div>
              </div>
            ))}
          </div>
        </section>
      )}

      {/* ═══ SUBSCRIPTION PLANS ═══ */}
      {showPlans && plans.length > 0 && (
        <section className="bv-plans-section">
          <div className="bv-section-header">
            <div className="bv-section-banner">
              <Icon name="crown" size={18} />
              &nbsp; {plansTitle}
            </div>
            <div className="bv-section-sub-alt">{plansSubtitle}</div>
          </div>

          <div className="bv-plans-grid">
            {plans.map((p) => (
              <div
                key={p.id}
                className={`bv-plan-card ${p.featured ? 'bv-plan-featured' : ''}`}
                style={p.featured ? { borderColor: p.color } : undefined}
              >
                {p.featured && (
                  <div className="bv-plan-popular" style={{ backgroundColor: p.color }}>
                    MOST POPULAR
                  </div>
                )}

                <div className="bv-plan-header" style={{ backgroundColor: p.color }}>
                  <h3 className="bv-plan-name">{p.name}</h3>
                  <p className="bv-plan-subtitle">{p.subtitle}</p>
                </div>

                <div className="bv-plan-price-wrap">
                  <span className="bv-plan-price">{formatPrice(p.price, currencySymbol, currencyPosition)}/MONTH</span>
                </div>

                <div className="bv-plan-features">
                  <ul>
                    {p.features.map((f) => (
                      <li key={f.id}>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12" /></svg>
                        {f.text}
                      </li>
                    ))}
                  </ul>
                </div>

                <div className="bv-plan-action">
                  <a
                    href={getButtonUrl(p)}
                    className={`bv-btn ${getButtonClass(p.color)} bv-btn-block`}
                    onClick={e => { if (getButtonUrl(p) === '#') e.preventDefault(); }}
                  >
                    {p.buttonLabel}
                  </a>
                </div>
              </div>
            ))}
          </div>
        </section>
      )}

      {/* ═══ FOOTER ═══ */}
      <footer className="bv-footer" style={{ marginTop: 'auto' }}>
        <div className="bv-footer-inner">
          {showTrustBadges && trustBadges.length > 0 && (
            <div className="bv-trust-badges">
              {trustBadges.map((badge, i) => (
                <div className="bv-trust-badge" key={i}>
                  <Icon name={badge.icon} size={20} />
                  <span>{badge.text}</span>
                </div>
              ))}
            </div>
          )}
          <div className="bv-footer-contact">
            {footerCompanyName && <p><strong>{footerCompanyName}</strong></p>}
            {(footerWebsite || footerPhone || footerEmail) && (
              <p>
                {[footerWebsite, footerPhone, footerEmail].filter(Boolean).join('  |  ')}
              </p>
            )}
          </div>
          <div className="bv-footer-copyright">
            <p>
              {footerCopyright
                ? footerCopyright.replace('{year}', String(new Date().getFullYear()))
                : `© ${new Date().getFullYear()} ${footerCompanyName || 'BusinessVance Consulting'}. All rights reserved.`
              }
            </p>
          </div>
        </div>
      </footer>

    </div>
  );
}
