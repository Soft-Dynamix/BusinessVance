import { db } from '@/lib/db';

async function seed() {
  console.log('Seeding database...');

  // 1. Seed Settings
  const settingsData: Record<string, string> = {
    brand_name: 'BUSINESSVANCE',
    brand_tagline: 'INSIGHT. STRATEGY. SUCCESS.',
    brand_description: 'Professional business reports and advisory services to help you make confident, informed decisions.',
    header_icon: 'shield',
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

  console.log('\n✅ Database seeded successfully!');
}

seed()
  .catch((e) => {
    console.error('Seed failed:', e);
    process.exit(1);
  })
  .finally(() => process.exit(0));