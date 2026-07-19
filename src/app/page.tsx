'use client';

import { useEffect } from 'react';

/* ── Seed data matching plugin's activation data ── */

const services = [
  { name: 'Business Feasibility Report', desc: 'Comprehensive assessment of the viability and potential success of your business idea, including market demand, financial projections, and risk analysis.', price: 'R2,500', icon: 'clipboard-list', featured: true },
  { name: 'Market Research Report', desc: 'In-depth analysis of your target market, customer demographics, industry trends, and competitive landscape to inform strategic decisions.', price: 'R3,000', icon: 'search', featured: false },
  { name: 'Competitor Analysis', desc: "Detailed evaluation of your competitors' strategies, strengths, weaknesses, and market positioning to identify opportunities.", price: 'R2,000', icon: 'users', featured: false },
  { name: 'Startup Cost Estimate', desc: 'Thorough breakdown of all expected costs to launch your business, including one-time and recurring expenses.', price: 'R1,500', icon: 'calculator', featured: false },
  { name: 'Marketing Strategy Report', desc: 'Custom marketing plan covering digital and traditional channels, target audience segmentation, and budget allocation.', price: 'R3,500', icon: 'megaphone', featured: true },
  { name: 'Financial Forecast Report', desc: '3-5 year financial projections including revenue, expenses, cash flow, and break-even analysis for investors or planning.', price: 'R3,000', icon: 'trending-up', featured: false },
  { name: 'Risk Assessment Report', desc: 'Identification and evaluation of potential business risks with mitigation strategies and contingency planning.', price: 'R2,500', icon: 'shield-alert', featured: false },
  { name: 'Business Plan', desc: 'Comprehensive, investor-ready business plan including executive summary, market analysis, financials, and operations plan.', price: 'R4,000', icon: 'file-text', featured: true },
  { name: 'Investor Readiness Report', desc: "Assessment of your business's readiness for investment, covering financials, governance, growth potential, and pitch preparation.", price: 'R3,500', icon: 'presentation', featured: false },
  { name: 'Business Health Check', desc: 'Diagnostic review of your existing business operations, financials, and strategy with actionable improvement recommendations.', price: 'R2,000', icon: 'heart-pulse', featured: false },
  { name: 'Consulting & Strategy Session', desc: 'One-on-one consulting session with our expert advisors to address specific business challenges and opportunities.', price: 'R1,200', icon: 'handshake', featured: false },
  { name: 'Implementation Support', desc: 'Hands-on support to implement recommended strategies, processes, and systems for your business.', price: 'R2,500', icon: 'wrench', featured: false },
];

const plans = [
  {
    name: 'STARTER', subtitle: 'For new entrepreneurs', price: 'R299/MONTH',
    color: '#2A9D8F', featured: false, btn: 'GET STARTED', btnClass: 'bv-btn-teal',
    features: ['1 report per month', '10% discount on all services', 'Priority delivery', 'Email support', 'Monthly business tips'],
  },
  {
    name: 'PROFESSIONAL', subtitle: 'For growing businesses', price: 'R599/MONTH',
    color: '#264653', featured: true, btn: 'UPGRADE NOW', btnClass: 'bv-btn-navy',
    features: ['2 reports per month', '15% discount on all services', 'Priority delivery', 'Email support', 'Monthly business tips', 'Early access to new reports'],
  },
  {
    name: 'BUSINESS PARTNER', subtitle: 'For serious entrepreneurs & teams', price: 'R999/MONTH',
    color: '#F4A261', featured: false, btn: 'JOIN NOW', btnClass: 'bv-btn-gold',
    features: ['4 reports per month', '20% discount on all services', 'Priority delivery', 'Email support', 'Monthly business tips', 'Early access to new reports', 'Dedicated account manager'],
  },
];

/* ── SVG icon paths (same as plugin) ── */

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

export default function Home() {
  useEffect(() => {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '/bv-frontend.css';
    document.head.appendChild(link);
    return () => { document.head.removeChild(link); };
  }, []);

  return (
    <div className="bv-page-wrapper" style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column' }}>

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
              <Icon name="shield" size={36} />
            </div>
            <div className="bv-brand-text">
              <h1 className="bv-brand-name">BUSINESSVANCE</h1>
              <p className="bv-brand-tagline">INSIGHT. STRATEGY. SUCCESS.</p>
            </div>
          </div>
          <p className="bv-brand-description">Professional business reports and advisory services to help you make confident, informed decisions.</p>
        </div>
      </header>

      {/* ═══ SERVICES TABLE ═══ */}
      <section className="bv-services-section">
        <div className="bv-section-header">
          <div className="bv-section-banner">PRICE LIST</div>
          <div className="bv-section-sub">ONE-TIME REPORTS &amp; SERVICES</div>
        </div>

        {/* Desktop Table */}
        <div className="bv-table-wrapper bv-desktop-only">
          <table className="bv-services-table">
            <thead>
              <tr>
                <th style={{ width: '28%' }}>SERVICE</th>
                <th style={{ width: '47%' }}>DESCRIPTION</th>
                <th className="bv-col-price" style={{ width: '25%' }}>PRICE (ZAR)</th>
              </tr>
            </thead>
            <tbody>
              {services.map((s, i) => (
                <tr key={i}>
                  <td>
                    <div className="bv-service-info">
                      <div className="bv-service-icon">
                        <Icon name={s.icon} size={20} />
                      </div>
                      <div className="bv-service-name">
                        {s.name}
                        {s.featured && <span className="bv-featured-badge">★ Popular</span>}
                      </div>
                    </div>
                  </td>
                  <td><p className="bv-service-desc">{s.desc}</p></td>
                  <td>
                    <div className="bv-price-block">
                      <span className="bv-service-price">{s.price}</span>
                      <a href="#" className="bv-btn bv-btn-gold bv-btn-sm" onClick={e => e.preventDefault()}>ADD TO CART</a>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Mobile Cards */}
        <div className="bv-mobile-cards bv-mobile-only">
          {services.map((s, i) => (
            <div className="bv-service-card" key={i}>
              <div className="bv-card-header">
                <div className="bv-card-icon">
                  <Icon name={s.icon} size={20} />
                </div>
                <div>
                  <h3 className="bv-card-name">
                    {s.name}
                    {s.featured && <span className="bv-featured-badge">★ Popular</span>}
                  </h3>
                </div>
              </div>
              <p className="bv-card-desc">{s.desc}</p>
              <div className="bv-card-footer">
                <span className="bv-card-price">{s.price}</span>
                <a href="#" className="bv-btn bv-btn-gold bv-btn-sm" onClick={e => e.preventDefault()}>ADD TO CART</a>
              </div>
            </div>
          ))}
        </div>
      </section>

      {/* ═══ SUBSCRIPTION PLANS ═══ */}
      <section className="bv-plans-section">
        <div className="bv-section-header">
          <div className="bv-section-banner">
            <Icon name="crown" size={18} />
            &nbsp; MONTHLY SUBSCRIPTION PLANS
          </div>
          <div className="bv-section-sub-alt">CHOOSE THE PLAN THAT GROWS WITH YOU</div>
        </div>

        <div className="bv-plans-grid">
          {plans.map((p, i) => (
            <div
              key={i}
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
                <span className="bv-plan-price">{p.price}</span>
              </div>

              <div className="bv-plan-features">
                <ul>
                  {p.features.map((f, j) => (
                    <li key={j}>
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12" /></svg>
                      {f}
                    </li>
                  ))}
                </ul>
              </div>

              <div className="bv-plan-action">
                <a href="#" className={`bv-btn ${p.btnClass} bv-btn-block`} onClick={e => e.preventDefault()}>
                  {p.btn}
                </a>
              </div>
            </div>
          ))}
        </div>
      </section>

      {/* ═══ FOOTER ═══ */}
      <footer className="bv-footer" style={{ marginTop: 'auto' }}>
        <div className="bv-footer-inner">
          <div className="bv-trust-badges">
            <div className="bv-trust-badge">
              <Icon name="shield-check" size={20} />
              <span>PROFESSIONAL QUALITY REPORTS</span>
            </div>
            <div className="bv-trust-badge">
              <Icon name="clock" size={20} />
              <span>FAST TURNAROUND</span>
            </div>
            <div className="bv-trust-badge">
              <Icon name="lock" size={20} />
              <span>100% CONFIDENTIAL &amp; SECURE</span>
            </div>
            <div className="bv-trust-badge">
              <Icon name="star" size={20} />
              <span>ACTIONABLE RECOMMENDATIONS</span>
            </div>
          </div>
          <div className="bv-footer-contact">
            <p><strong>BUSINESSVANCE CONSULTING</strong></p>
            <p>www.studyvance.co.za &nbsp;|&nbsp; 082 377 7490 &nbsp;|&nbsp; info@businessvance.co.za</p>
          </div>
          <div className="bv-footer-copyright">
            <p>&copy; {new Date().getFullYear()} BusinessVance Consulting. All rights reserved.</p>
          </div>
        </div>
      </footer>

    </div>
  );
}