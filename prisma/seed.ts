import { db } from '@/lib/db';

async function seed() {
  // Create categories
  const reports = await db.category.create({
    data: { name: 'Business Reports', slug: 'business-reports', color: '#002B5C' },
  });
  const advisory = await db.category.create({
    data: { name: 'Advisory Services', slug: 'advisory-services', color: '#008080' },
  });

  // Create services
  const services = [
    { name: 'Company Profile Report', description: 'Comprehensive company profile with financial analysis, ownership structure, and operational overview.', price: 450, icon: 'ClipboardCheck', buttonLabel: 'ADD TO CART', buttonType: 'cart', category: reports, displayOrder: 0 },
    { name: 'Business Valuation Report', description: 'Detailed business valuation using multiple methodologies including income, market, and asset-based approaches.', price: 2500, icon: 'Calculator', buttonLabel: 'ADD TO CART', buttonType: 'cart', category: reports, displayOrder: 1 },
    { name: 'Market Research Report', description: 'In-depth market analysis covering industry trends, competitor landscape, and growth opportunities.', price: 1800, icon: 'Search', buttonLabel: 'ADD TO CART', buttonType: 'cart', category: reports, displayOrder: 2 },
    { name: 'Financial Analysis Report', description: 'Thorough financial health assessment including ratio analysis, cash flow analysis, and profitability metrics.', price: 1500, icon: 'BarChart3', buttonLabel: 'ADD TO CART', buttonType: 'cart', category: reports, displayOrder: 3 },
    { name: 'Compliance Audit Report', description: 'Regulatory compliance check covering all relevant South African business legislation and requirements.', price: 1200, icon: 'Shield', buttonLabel: 'ADD TO CART', buttonType: 'cart', category: advisory, displayOrder: 4 },
    { name: 'Strategic Business Plan', description: 'Custom strategic business plan with actionable recommendations, financial projections, and implementation roadmap.', price: 3500, icon: 'Target', buttonLabel: 'GET A QUOTE', buttonType: 'quote', category: advisory, displayOrder: 5, featured: true },
    { name: 'Industry Trend Analysis', description: 'Comprehensive analysis of current and emerging trends in your specific industry sector.', price: 950, icon: 'TrendingUp', buttonLabel: 'ADD TO CART', buttonType: 'cart', category: reports, displayOrder: 6 },
    { name: 'SWOT Analysis Report', description: 'Detailed SWOT analysis identifying strengths, weaknesses, opportunities, and threats for your business.', price: 750, icon: 'FileSpreadsheet', buttonLabel: 'ADD TO CART', buttonType: 'cart', category: advisory, displayOrder: 7 },
    { name: 'Marketing Strategy Report', description: 'Tailored marketing strategy with channel recommendations, target audience analysis, and budget allocation.', price: 2000, icon: 'Megaphone', buttonLabel: 'ADD TO CART', buttonType: 'cart', category: advisory, displayOrder: 8 },
    { name: 'Risk Assessment Report', description: 'Comprehensive risk identification, evaluation, and mitigation strategies for your business operations.', price: 1100, icon: 'Shield', buttonLabel: 'ADD TO CART', buttonType: 'cart', category: advisory, displayOrder: 9 },
  ];

  for (const s of services) {
    await db.service.create({
      data: {
        name: s.name,
        slug: s.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''),
        description: s.description,
        price: s.price,
        icon: s.icon,
        buttonLabel: s.buttonLabel,
        buttonType: s.buttonType,
        categoryId: s.category.id,
        displayOrder: s.displayOrder,
        featured: (s as Record<string, unknown>).featured === true,
        visible: true,
      },
    });
  }

  // Create plans
  const plans = [
    {
      name: 'Starter',
      subtitle: 'For small businesses getting started',
      price: 499,
      color: '#008080',
      featured: false,
      displayOrder: 0,
      features: ['2 Reports per month', 'Basic company profiles', 'Email support', 'PDF delivery', 'Standard turnaround (5 days)'],
    },
    {
      name: 'Professional',
      subtitle: 'For growing businesses that need more',
      price: 1299,
      color: '#002B5C',
      featured: true,
      displayOrder: 1,
      features: ['5 Reports per month', 'Advanced analytics & valuations', 'Priority email & phone support', 'PDF & Excel delivery', 'Fast turnaround (3 days)', 'Quarterly strategy review', 'Dedicated account manager', 'Custom report templates'],
    },
    {
      name: 'Enterprise',
      subtitle: 'For established businesses at scale',
      price: 2999,
      color: '#D4AF37',
      featured: false,
      displayOrder: 2,
      features: ['Unlimited reports', 'Full advisory services', '24/7 priority support', 'All delivery formats', 'Same-day turnaround', 'Monthly strategy sessions', 'Dedicated analyst team', 'Custom integrations', 'Executive presentations', 'Board-ready materials'],
    },
  ];

  for (const p of plans) {
    await db.plan.create({
      data: {
        name: p.name,
        subtitle: p.subtitle,
        price: p.price,
        color: p.color,
        featured: p.featured,
        displayOrder: p.displayOrder,
        visible: true,
        features: {
          create: p.features.map((text) => ({ text })),
        },
      },
    });
  }

  console.log('Seed data created successfully!');
}

seed()
  .catch(console.error)
  .finally(() => db.$disconnect());