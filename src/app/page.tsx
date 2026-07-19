'use client';

import { useEffect } from 'react';

/* ── Seed data matching plugin's default activation data ── */

const services = [
  { name: 'Business Registration', desc: 'Complete company registration with CIPC including all necessary documentation and compliance certificates.', price: 'R1,500', icon: 'building', featured: true, btn: 'Register Now' },
  { name: 'Tax Clearance Certificate', desc: 'Obtain your tax clearance certificate from SARS. Includes all supporting documentation preparation.', price: 'R800', icon: 'file-check', featured: true, btn: 'Apply Now' },
  { name: 'BEE Affidavit', desc: 'Professional B-BBEE affidavit preparation and certification for your business.', price: 'R600', icon: 'award', featured: false, btn: 'Get Affidavit' },
  { name: 'Business Plan Writing', desc: 'Comprehensive business plan tailored for funding applications or strategic planning.', price: 'R3,500', icon: 'file-text', featured: false, btn: 'Get Started' },
  { name: 'Financial Statements', desc: 'Annual financial statements prepared according to IFRS for SME standards.', price: 'R4,000', icon: 'bar-chart-3', featured: false, btn: 'Get Started' },
  { name: 'Tax Returns (Individual)', desc: 'Professional personal income tax return filing and optimization.', price: 'R1,200', icon: 'calculator', featured: false, btn: 'File Now' },
  { name: 'Tax Returns (Business)', desc: 'Complete business tax return preparation and submission to SARS.', price: 'R2,500', icon: 'receipt', featured: false, btn: 'File Now' },
  { name: 'Payroll Registration', desc: 'Register your business for PAYE, UI-19 and SDL with SARS.', price: 'R1,000', icon: 'users', featured: false, btn: 'Register Now' },
  { name: 'Logo & Brand Identity', desc: 'Professional logo design and brand identity package including guidelines.', price: 'R5,000', icon: 'palette', featured: false, btn: 'Get Started' },
  { name: 'Social Media Setup', desc: 'Complete social media profile setup and optimization across all major platforms.', price: 'R2,000', icon: 'share-2', featured: false, btn: 'Get Started' },
  { name: 'Website Development', desc: 'Professional responsive website design and development for your business.', price: 'R8,000', icon: 'globe', featured: false, btn: 'Get Started' },
];

const plans = [
  {
    name: 'Starter', subtitle: 'Perfect for new entrepreneurs', price: 'R299/mo',
    color: '#008080', featured: false, btn: 'Get Started',
    features: ['Basic business consultation', 'Monthly financial health check', 'Email support (48hr response)', 'Access to resource library'],
  },
  {
    name: 'Professional', subtitle: 'For growing businesses', price: 'R599/mo',
    color: '#002B5C', featured: true, btn: 'Get Started',
    features: ['Everything in Starter', 'Dedicated account manager', 'Quarterly business reviews', 'Priority support (24hr response)', 'Tax planning & optimization', 'Marketing strategy sessions'],
  },
  {
    name: 'Business Partner', subtitle: 'Full-service partnership', price: 'R999/mo',
    color: '#D4AF37', featured: false, btn: 'Get Started',
    features: ['Everything in Professional', 'Unlimited consultations', 'Monthly strategic planning', 'CFO advisory services', 'BEE compliance management', '24/7 priority support', 'Annual business retreat planning'],
  },
];

/* ── SVG icon paths (same as plugin's class-bv-shortcodes.php) ── */

const iconPaths: Record<string, string> = {
  shield: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
  'shield-check': '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
  'check-circle': '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
  award: '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>',
  star: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
  building: '<rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>',
  'file-check': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/>',
  'file-text': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
  'bar-chart-3': '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
  calculator: '<rect width="16" height="20" x="4" y="2" rx="2"/><line x1="8" x2="16" y1="6" y2="6"/><line x1="16" x2="16" y1="14" y2="18"/><line x1="8" x2="8" y1="10" y2="10.01"/><line x1="12" x2="12" y1="10" y2="10.01"/><line x1="16" x2="16" y1="10" y2="10.01"/><line x1="8" x2="8" y1="14" y2="14.01"/><line x1="12" x2="12" y1="14" y2="14.01"/><line x1="8" x2="8" y1="18" y2="18.01"/><line x1="12" x2="12" y1="18" y2="18.01"/>',
  receipt: '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 17.5v-11"/>',
  users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  palette: '<circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
  'share-2': '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" x2="15.42" y1="13.51" y2="17.49"/><line x1="15.41" x2="8.59" y1="6.51" y2="10.49"/>',
  globe: '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
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
  // Load the plugin's own CSS
  useEffect(() => {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '/bv-frontend.css';
    document.head.appendChild(link);
    return () => { document.head.removeChild(link); };
  }, []);

  return (
    <div className="bv-page-wrapper" style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column' }}>

      {/* ═══ HEADER — exact plugin markup ═══ */}
      <header className="bv-header">
        <div className="bv-header-inner">
          <div className="bv-header-brand">
            <div className="bv-shield-logo">
              <Icon name="shield" size={32} />
            </div>
            <div className="bv-brand-text">
              <h1 className="bv-brand-name">BusinessVance</h1>
              <p className="bv-brand-tagline">INSIGHT. STRATEGY. SUCCESS.</p>
            </div>
          </div>
        </div>
      </header>

      {/* ═══ SERVICES TABLE — exact plugin markup ═══ */}
      <section className="bv-services-section">
        <div className="bv-section-header">
          <h2 className="bv-section-title">Our Services</h2>
          <p className="bv-section-subtitle">Professional business solutions tailored to your needs</p>
        </div>

        {/* Desktop Table */}
        <div className="bv-table-wrapper bv-desktop-only">
          <table className="bv-services-table">
            <thead>
              <tr>
                <th className="bv-col-service">Service</th>
                <th className="bv-col-price">Price</th>
                <th className="bv-col-action">Action</th>
              </tr>
            </thead>
            <tbody>
              {services.map((s, i) => (
                <tr key={i}>
                  <td>
                    <div className="bv-service-info">
                      <div className="bv-service-icon">
                        <Icon name={s.icon} size={22} />
                      </div>
                      <div className="bv-service-details">
                        <div className="bv-service-name">
                          {s.name}
                          {s.featured && <span className="bv-featured-badge">★ Featured</span>}
                        </div>
                        <div className="bv-service-desc">{s.desc}</div>
                      </div>
                    </div>
                  </td>
                  <td><span className="bv-service-price">{s.price}</span></td>
                  <td><a href="#" className="bv-btn bv-btn-gold" onClick={e => e.preventDefault()}>{s.btn}</a></td>
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
                  <Icon name={s.icon} size={24} />
                </div>
                <div className="bv-card-info">
                  <h3 className="bv-card-name">
                    {s.name}
                    {s.featured && <span className="bv-featured-badge">★ Featured</span>}
                  </h3>
                  <p className="bv-card-desc">{s.desc.length > 80 ? s.desc.slice(0, 80) + '...' : s.desc}</p>
                </div>
              </div>
              <div className="bv-card-footer">
                <span className="bv-card-price">{s.price}</span>
                <a href="#" className="bv-btn bv-btn-gold bv-btn-sm" onClick={e => e.preventDefault()}>{s.btn}</a>
              </div>
            </div>
          ))}
        </div>
      </section>

      {/* ═══ SUBSCRIPTION PLANS — exact plugin markup ═══ */}
      <section className="bv-plans-section">
        <div className="bv-section-header">
          <h2 className="bv-section-title">Monthly Subscription Plans</h2>
          <p className="bv-section-subtitle">Choose the plan that fits your business needs</p>
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
                {p.subtitle && <p className="bv-plan-subtitle">{p.subtitle}</p>}
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
                <a href="#" className="bv-btn bv-btn-gold bv-btn-block" onClick={e => e.preventDefault()}>
                  {p.btn}
                </a>
              </div>
            </div>
          ))}
        </div>
      </section>

      {/* ═══ FOOTER — exact plugin markup ═══ */}
      <footer className="bv-footer" style={{ marginTop: 'auto' }}>
        <div className="bv-footer-inner">
          <div className="bv-trust-badges">
            <div className="bv-trust-badge">
              <Icon name="shield-check" size={20} />
              <span>Trusted &amp; Verified</span>
            </div>
            <div className="bv-trust-badge">
              <Icon name="check-circle" size={20} />
              <span>CIPC Registered</span>
            </div>
            <div className="bv-trust-badge">
              <Icon name="award" size={20} />
              <span>BEE Compliant</span>
            </div>
            <div className="bv-trust-badge">
              <Icon name="star" size={20} />
              <span>5-Star Rated</span>
            </div>
          </div>
          <div className="bv-footer-contact">
            <p><strong>BusinessVance Consulting</strong></p>
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