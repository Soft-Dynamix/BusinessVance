import { db } from '@/lib/db';

async function seed() {
  console.log('Seeding database...');

  // 1. Seed Settings
  const settingsData: Record<string, string> = {
    brand_name: 'BUSINESSVANCE',
    brand_tagline: 'INSIGHT. STRATEGY. SUCCESS.',
    brand_description: 'Professional business reports and advisory services to help you make confident, informed decisions.',
    header_icon: 'shield',
    logo_url: '',
    color_primary: '#0A2647',
    color_primary_dark: '#071a33',
    color_primary_light: '#144272',
    color_accent: '#F4A261',
    color_accent_alt: '#2A9D8F',
    color_gold: '#D4AF37',
    color_text: '#333333',
    color_text_light: '#666666',
    color_bg: '#ffffff',
    services_section_title: 'PRICE LIST',
    services_section_subtitle: 'ONE-TIME REPORTS & SERVICES',
    show_services_section: 'true',
    plans_section_title: 'MONTHLY SUBSCRIPTION PLANS',
    plans_section_subtitle: 'CHOOSE THE PLAN THAT GROWS WITH YOU',
    show_plans_section: 'true',
    currency_symbol: 'R',
    currency_position: 'before',
    footer_company_name: 'BUSINESSVANCE CONSULTING',
    footer_website: 'www.studyvance.co.za',
    footer_phone: '082 377 7490',
    footer_email: 'info@businessvance.co.za',
    show_trust_badges: 'true',
    footer_copyright: '',
    container_max_width: '1200px',
    enable_animations: 'true',
    show_featured_badge: 'true',
    default_service_button_label: 'ADD TO CART',
    trust_badges: JSON.stringify([
      { icon: 'shield-check', text: 'PROFESSIONAL QUALITY REPORTS' },
      { icon: 'clock', text: 'FAST TURNAROUND' },
      { icon: 'lock', text: '100% CONFIDENTIAL & SECURE' },
      { icon: 'star', text: 'ACTIONABLE RECOMMENDATIONS' },
    ]),
  };

  for (const [key, value] of Object.entries(settingsData)) {
    await db.pluginSetting.upsert({
      where: { key },
      update: { value },
      create: { key, value },
    });
  }
  console.log('  ✓ Settings seeded');

  // 2. Seed Categories
  const categories = [
    { name: 'Business Planning', slug: 'business-planning', color: '#0A2647' },
    { name: 'Finance', slug: 'finance', color: '#2A9D8F' },
    { name: 'Marketing', slug: 'marketing', color: '#F4A261' },
    { name: 'Strategy', slug: 'strategy', color: '#264653' },
  ];

  const catIds: Record<string, string> = {};
  for (const c of categories) {
    const cat = await db.category.upsert({
      where: { slug: c.slug },
      update: { name: c.name, color: c.color },
      create: c,
    });
    catIds[c.slug] = cat.id;
  }
  console.log('  ✓ Categories seeded');

  // 3. Seed Services
  const services = [
    { name: 'Business Feasibility Report', desc: 'Comprehensive assessment of the viability and potential success of your business idea, including market demand, financial projections, and risk analysis.', price: 2500, icon: 'clipboard-list', featured: true, cat: 'business-planning' },
    { name: 'Market Research Report', desc: 'In-depth analysis of your target market, customer demographics, industry trends, and competitive landscape to inform strategic decisions.', price: 3000, icon: 'search', featured: false, cat: 'marketing' },
    { name: 'Competitor Analysis', desc: "Detailed evaluation of your competitors' strategies, strengths, weaknesses, and market positioning to identify opportunities.", price: 2000, icon: 'users', featured: false, cat: 'strategy' },
    { name: 'Startup Cost Estimate', desc: 'Thorough breakdown of all expected costs to launch your business, including one-time and recurring expenses.', price: 1500, icon: 'calculator', featured: false, cat: 'finance' },
    { name: 'Marketing Strategy Report', desc: 'Custom marketing plan covering digital and traditional channels, target audience segmentation, and budget allocation.', price: 3500, icon: 'megaphone', featured: true, cat: 'marketing' },
    { name: 'Financial Forecast Report', desc: '3-5 year financial projections including revenue, expenses, cash flow, and break-even analysis for investors or planning.', price: 3000, icon: 'trending-up', featured: false, cat: 'finance' },
    { name: 'Risk Assessment Report', desc: 'Identification and evaluation of potential business risks with mitigation strategies and contingency planning.', price: 2500, icon: 'shield-alert', featured: false, cat: 'strategy' },
    { name: 'Business Plan', desc: 'Comprehensive, investor-ready business plan including executive summary, market analysis, financials, and operations plan.', price: 4000, icon: 'file-text', featured: true, cat: 'business-planning' },
    { name: 'Investor Readiness Report', desc: "Assessment of your business's readiness for investment, covering financials, governance, growth potential, and pitch preparation.", price: 3500, icon: 'presentation', featured: false, cat: 'finance' },
    { name: 'Business Health Check', desc: 'Diagnostic review of your existing business operations, financials, and strategy with actionable improvement recommendations.', price: 2000, icon: 'heart-pulse', featured: false, cat: 'strategy' },
    { name: 'Consulting & Strategy Session', desc: 'One-on-one consulting session with our expert advisors to address specific business challenges and opportunities.', price: 1200, icon: 'handshake', featured: false, cat: 'strategy' },
    { name: 'Implementation Support', desc: 'Hands-on support to implement recommended strategies, processes, and systems for your business.', price: 2500, icon: 'wrench', featured: false, cat: 'business-planning' },
  ];

  for (let i = 0; i < services.length; i++) {
    const s = services[i];
    const slug = s.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    await db.service.upsert({
      where: { slug },
      update: {
        name: s.name,
        description: s.desc,
        price: s.price,
        icon: s.icon,
        featured: s.featured,
        categoryId: catIds[s.cat] || null,
        displayOrder: i,
      },
      create: {
        name: s.name,
        slug,
        description: s.desc,
        price: s.price,
        icon: s.icon,
        featured: s.featured,
        categoryId: catIds[s.cat] || null,
        displayOrder: i,
      },
    });
  }
  console.log('  ✓ Services seeded');

  // 4. Seed Plans
  const plansData = [
    {
      name: 'STARTER',
      subtitle: 'For new entrepreneurs',
      price: 299,
      color: '#2A9D8F',
      buttonLabel: 'GET STARTED',
      features: [
        '1 report per month',
        '10% discount on all services',
        'Priority delivery',
        'Email support',
        'Monthly business tips',
      ],
    },
    {
      name: 'PROFESSIONAL',
      subtitle: 'For growing businesses',
      price: 599,
      color: '#264653',
      buttonLabel: 'UPGRADE NOW',
      featured: true,
      features: [
        '2 reports per month',
        '15% discount on all services',
        'Priority delivery',
        'Email support',
        'Monthly business tips',
        'Early access to new reports',
      ],
    },
    {
      name: 'BUSINESS PARTNER',
      subtitle: 'For serious entrepreneurs & teams',
      price: 999,
      color: '#F4A261',
      buttonLabel: 'JOIN NOW',
      features: [
        '4 reports per month',
        '20% discount on all services',
        'Priority delivery',
        'Email support',
        'Monthly business tips',
        'Early access to new reports',
        'Dedicated account manager',
      ],
    },
  ];

  for (let i = 0; i < plansData.length; i++) {
    const p = plansData[i];
    const slug = p.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');

    const plan = await db.plan.upsert({
      where: { slug },
      update: {
        name: p.name,
        subtitle: p.subtitle,
        price: p.price,
        color: p.color,
        buttonLabel: p.buttonLabel,
        featured: p.featured || false,
        displayOrder: i,
      },
      create: {
        name: p.name,
        slug,
        subtitle: p.subtitle,
        price: p.price,
        color: p.color,
        buttonLabel: p.buttonLabel,
        featured: p.featured || false,
        displayOrder: i,
      },
    });

    // Delete old features and re-create
    await db.planFeature.deleteMany({ where: { planId: plan.id } });
    for (const fText of p.features) {
      await db.planFeature.create({
        data: { text: fText, planId: plan.id },
      });
    }
  }
  console.log('  ✓ Plans seeded');

  // 5. Seed Icons
  const iconsData = [
    { name: 'shield', label: 'Shield', svgPath: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>', category: 'security' },
    { name: 'shield-check', label: 'Shield Check', svgPath: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>', category: 'security' },
    { name: 'check-circle', label: 'Check Circle', svgPath: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>', category: 'status' },
    { name: 'award', label: 'Award', svgPath: '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>', category: 'status' },
    { name: 'star', label: 'Star', svgPath: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>', category: 'status' },
    { name: 'crown', label: 'Crown', svgPath: '<path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/>', category: 'status' },
    { name: 'lock', label: 'Lock', svgPath: '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>', category: 'security' },
    { name: 'clock', label: 'Clock', svgPath: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>', category: 'general' },
    { name: 'clipboard-list', label: 'Clipboard List', svgPath: '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>', category: 'business' },
    { name: 'search', label: 'Search', svgPath: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>', category: 'general' },
    { name: 'users', label: 'Users', svgPath: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>', category: 'people' },
    { name: 'calculator', label: 'Calculator', svgPath: '<rect width="16" height="20" x="4" y="2" rx="2"/><line x1="8" x2="16" y1="6" y2="6"/><line x1="16" x2="16" y1="14" y2="18"/><line x1="8" x2="8" y1="10" y2="10.01"/><line x1="12" x2="12" y1="10" y2="10.01"/><line x1="16" x2="16" y1="10" y2="10.01"/><line x1="8" x2="8" y1="14" y2="14.01"/><line x1="12" x2="12" y1="14" y2="14.01"/><line x1="8" x2="8" y1="18" y2="18.01"/><line x1="12" x2="12" y1="18" y2="18.01"/>', category: 'finance' },
    { name: 'megaphone', label: 'Megaphone', svgPath: '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>', category: 'marketing' },
    { name: 'trending-up', label: 'Trending Up', svgPath: '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>', category: 'finance' },
    { name: 'shield-alert', label: 'Shield Alert', svgPath: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4"/><path d="M12 16h.01"/>', category: 'security' },
    { name: 'file-text', label: 'File Text', svgPath: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>', category: 'business' },
    { name: 'presentation', label: 'Presentation', svgPath: '<path d="M2 3h20"/><path d="M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3"/><path d="m7 21 5-5 5 5"/>', category: 'business' },
    { name: 'heart-pulse', label: 'Heart Pulse', svgPath: '<path d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572"/><path d="M12 6l-2 4h4l-2 4"/>', category: 'general' },
    { name: 'handshake', label: 'Handshake', svgPath: '<path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/><path d="M3 4h8"/>', category: 'business' },
    { name: 'wrench', label: 'Wrench', svgPath: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>', category: 'general' },
    { name: 'check', label: 'Check', svgPath: '<polyline points="20 6 9 17 4 12"/>', category: 'status' },
  ];

  for (let i = 0; i < iconsData.length; i++) {
    const icon = iconsData[i];
    await db.icon.upsert({
      where: { name: icon.name },
      update: { label: icon.label, svgPath: icon.svgPath, category: icon.category, displayOrder: i },
      create: { name: icon.name, label: icon.label, svgPath: icon.svgPath, category: icon.category, displayOrder: i },
    });
  }
  console.log('  ✓ Icons seeded');

  console.log('\n✅ Database seeded successfully!');
}

seed()
  .catch((e) => {
    console.error('Seed failed:', e);
    process.exit(1);
  })
  .finally(() => process.exit(0));