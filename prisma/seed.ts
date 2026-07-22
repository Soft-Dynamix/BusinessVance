import { db } from '@/lib/db';

async function seed() {
  console.log('Seeding database...');

  // ═══════════════════════════════════════════════════════════
  // 1. SETTINGS (28 configurable plugin settings)
  // ═══════════════════════════════════════════════════════════
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
  console.log('  ✓ Settings seeded (28 items)');

  // ═══════════════════════════════════════════════════════════
  // 2. CATEGORIES
  // ═══════════════════════════════════════════════════════════
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
  console.log('  ✓ Categories seeded (4)');

  // ═══════════════════════════════════════════════════════════
  // 3. ICONS (22 Lucide icons across 7 categories)
  // ═══════════════════════════════════════════════════════════
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
  console.log('  ✓ Icons seeded (22)');

  // ═══════════════════════════════════════════════════════════
  // 4. AGREEMENT TEMPLATE (Confidentiality Undertaking)
  // ═══════════════════════════════════════════════════════════
  const agreement = await db.agreementTemplate.upsert({
    where: { slug: 'client-confidentiality-undertaking' },
    update: { status: 'published' },
    create: {
      name: 'Client Confidentiality Undertaking',
      slug: 'client-confidentiality-undertaking',
      version: '1.0',
      status: 'published',
      content: `<div style="font-family: Georgia, serif; line-height: 1.8; color: #333;">
<h2 style="text-align: center; color: #0A2647; border-bottom: 2px solid #D4AF37; padding-bottom: 10px;">BUSINESSVANCE CONSULTING</h2>
<h3 style="text-align: center; color: #0A2647;">CLIENT CONFIDENTIALITY UNDERTAKING</h3>

<p><strong>Date:</strong> [DATE]</p>
<p><strong>Company:</strong> BusinessVance Consulting (hereinafter referred to as "BusinessVance")</p>

<h4 style="color: #0A2647; margin-top: 24px;">1. PURPOSE</h4>
<p>This Confidentiality Undertaking ("Undertaking") sets out the terms and conditions under which the Client agrees to maintain the confidentiality of all information, reports, data, and materials provided by BusinessVance in connection with the services rendered.</p>

<h4 style="color: #0A2647;">2. DEFINITION OF CONFIDENTIAL INFORMATION</h4>
<p>"Confidential Information" means all information, whether written, oral, electronic, or in any other form, disclosed by BusinessVance to the Client, including but not limited to:</p>
<ul>
<li>Business reports, analyses, and strategic recommendations</li>
<li>Financial data, projections, and forecasts</li>
<li>Market research findings and competitive intelligence</li>
<li>Business plans, feasibility studies, and assessments</li>
<li>Proprietary methodologies, tools, and frameworks</li>
<li>Any other information designated as confidential by BusinessVance</li>
</ul>

<h4 style="color: #0A2647;">3. CLIENT OBLIGATIONS</h4>
<p>The Client undertakes to:</p>
<ol>
<li>Keep all Confidential Information strictly confidential and not disclose it to any third party without the prior written consent of BusinessVance.</li>
<li>Use Confidential Information solely for the purpose for which it was provided.</li>
<li>Not reproduce, copy, or distribute any Confidential Information without prior written approval.</li>
<li>Take all reasonable measures to protect Confidential Information from unauthorised access or disclosure.</li>
<li>Return or destroy all Confidential Information upon request by BusinessVance.</li>
</ol>

<h4 style="color: #0A2647;">4. TERM</h4>
<p>This Undertaking shall remain in effect for a period of <strong>three (3) years</strong> from the date of the last disclosure of Confidential Information, unless terminated earlier by mutual agreement in writing.</p>

<h4 style="color: #0A2647;">5. BREACH</h4>
<p>Any breach of this Undertaking may result in legal action and the Client shall be liable for any damages, losses, or expenses incurred by BusinessVance as a result of such breach.</p>

<h4 style="color: #0A2647;">6. GOVERNING LAW</h4>
<p>This Undertaking shall be governed by and construed in accordance with the laws of the Republic of South Africa.</p>

<h4 style="color: #0A2647;">7. ACKNOWLEDGMENT</h4>
<p>By completing and submitting a BusinessVance questionnaire, the Client acknowledges and consents to the terms of this Undertaking.</p>
</div>`,
    },
  });
  console.log('  ✓ Agreement Template seeded (Confidentiality Undertaking)');

  // ═══════════════════════════════════════════════════════════
  // 5. QUESTIONNAIRE TEMPLATES
  // ═══════════════════════════════════════════════════════════

  // --- Business Plan Questionnaire ---
  const bpTemplate = await db.questionnaireTemplate.upsert({
    where: { slug: 'business-plan-questionnaire' },
    update: { status: 'published' },
    create: {
      name: 'Business Plan Questionnaire',
      slug: 'business-plan-questionnaire',
      description: 'Comprehensive questionnaire for business plan report preparation. Covers client info, business overview, target market, competitors, marketing, financials, SWOT, and growth.',
      version: '2.0',
      status: 'published',
    },
  });

  const bpSections = [
    { title: 'Client Information', description: 'Your personal and contact details', isShared: true, questions: [
      { type: 'text', label: 'Full Name', placeholder: 'e.g., John Smith', required: true },
      { type: 'email', label: 'Email Address', placeholder: 'e.g., john@company.co.za', required: true },
      { type: 'phone', label: 'Phone Number', placeholder: 'e.g., 082 123 4567', required: true },
      { type: 'text', label: 'Company Name', placeholder: 'e.g., ABC Engineering (Pty) Ltd', required: true },
      { type: 'text', label: 'Company Registration Number', placeholder: 'e.g., 2024/123456/07' },
      { type: 'select', label: 'Company Type', options: JSON.stringify(['Sole Proprietor', 'Private Company (Pty) Ltd', 'Close Corporation (CC)', 'Partnership', 'Non-Profit', 'Other']), required: true },
      { type: 'text', label: 'VAT Number', placeholder: 'If registered for VAT' },
      { type: 'date', label: 'Date of Establishment', required: false },
      { type: 'text', label: 'Physical Address', placeholder: 'Full business address', required: true },
      { type: 'textarea', label: 'Business Stage', placeholder: 'Idea stage, Startup (0-1 year), Growth (1-3 years), Established (3+ years)', required: true },
    ]},
    { title: 'Business Overview', description: 'Tell us about your business', isShared: false, questions: [
      { type: 'textarea', label: 'Describe Your Business', placeholder: 'What does your business do? What products or services do you offer?', required: true, helpText: 'Provide a clear and concise description of your business activities.' },
      { type: 'textarea', label: 'Problem Statement', placeholder: 'What problem does your business solve?', required: true },
      { type: 'textarea', label: 'Value Proposition', placeholder: 'What makes your business unique? Why should customers choose you?', required: true },
      { type: 'textarea', label: 'Business Vision', placeholder: 'Where do you see your business in 5 years?', required: false },
      { type: 'textarea', label: 'Business Mission', placeholder: 'What is the purpose of your business?', required: false },
      { type: 'number', label: 'Number of Founders/Owners', placeholder: 'e.g., 2', required: true },
      { type: 'textarea', label: 'Ownership Structure', placeholder: 'Describe the ownership breakdown (e.g., 60% John, 40% Sarah)', required: false },
      { type: 'number', label: 'Current Number of Employees', placeholder: 'e.g., 5', required: true },
      { type: 'select', label: 'Industry Sector', options: JSON.stringify(['Agriculture', 'Construction', 'Education', 'Finance & Banking', 'Healthcare', 'Hospitality & Tourism', 'Information Technology', 'Manufacturing', 'Mining', 'Professional Services', 'Retail', 'Transport & Logistics', 'Other']), required: true },
    ]},
    { title: 'Target Market', description: 'Who are your customers?', isShared: true, questions: [
      { type: 'textarea', label: 'Describe Your Target Market', placeholder: 'Who are your ideal customers? What are their demographics?', required: true },
      { type: 'text', label: 'Geographic Area', placeholder: 'e.g., Gauteng, South Africa, or Global', required: true },
      { type: 'text', label: 'Estimated Market Size', placeholder: 'e.g., R50 million per annum' },
      { type: 'radio', label: 'B2B or B2C?', options: JSON.stringify(['B2B (Business to Business)', 'B2C (Business to Consumer)', 'Both']), required: true },
      { type: 'textarea', label: 'Customer Pain Points', placeholder: 'What challenges does your target market face that your business addresses?', required: true },
    ]},
    { title: 'Competitive Analysis', description: 'Who are your competitors?', isShared: true, questions: [
      { type: 'textarea', label: 'Main Competitors', placeholder: 'List your top 3-5 competitors and briefly describe what they do', required: true },
      { type: 'textarea', label: 'Your Competitive Advantage', placeholder: 'What gives you an edge over your competitors?', required: true },
      { type: 'select', label: 'Competitive Position', options: JSON.stringify(['Market Leader', 'Challenger', 'Follower', 'Niche Player', 'New Entrant']), required: true },
      { type: 'textarea', label: 'Barriers to Entry', placeholder: 'What barriers prevent new competitors from entering your market?', required: false },
    ]},
    { title: 'Marketing Strategy', description: 'How do you reach your customers?', isShared: false, questions: [
      { type: 'multiselect', label: 'Current Marketing Channels', options: JSON.stringify(['Website', 'Social Media', 'Email Marketing', 'Google Ads', 'Word of Mouth', 'Referrals', 'Print Media', 'Radio/TV', 'Events/Exhibitions', 'None yet']), required: true },
      { type: 'number', label: 'Monthly Marketing Budget (ZAR)', placeholder: 'e.g., 5000', required: false },
      { type: 'textarea', label: 'Marketing Goals', placeholder: 'What do you want to achieve with your marketing?', required: false },
      { type: 'radio', label: 'Do you have a website?', options: JSON.stringify(['Yes', 'No']), required: true },
      { type: 'text', label: 'Website URL', placeholder: 'e.g., www.mycompany.co.za', conditionalOn: '', conditionalValue: 'Yes' },
    ]},
    { title: 'Financial Information', description: 'Your current financial situation', isShared: false, questions: [
      { type: 'select', label: 'Annual Revenue Range', options: JSON.stringify(['Pre-revenue (no sales yet)', 'R0 - R500,000', 'R500,001 - R1,000,000', 'R1,000,001 - R5,000,000', 'R5,000,001 - R20,000,000', 'R20,000,001+']), required: true },
      { type: 'number', label: 'Monthly Operating Expenses (ZAR)', placeholder: 'e.g., 25000', required: false },
      { type: 'radio', label: 'Are you currently profitable?', options: JSON.stringify(['Yes', 'No', 'Breaking even']), required: true },
      { type: 'radio', label: 'Do you have existing funding?', options: JSON.stringify(['Self-funded', 'Bank Loan', 'Investor Funding', 'Government Grant', 'Combination', 'None']), required: true },
      { type: 'number', label: 'Funding Required (ZAR)', placeholder: 'e.g., 500000', required: false },
      { type: 'textarea', label: 'Funding Purpose', placeholder: 'How will the funding be used?', required: false },
    ]},
    { title: 'SWOT Analysis', description: 'Strengths, Weaknesses, Opportunities, Threats', isShared: false, questions: [
      { type: 'textarea', label: 'Strengths', placeholder: 'What does your business do well? What unique resources do you have?', required: true, helpText: 'List 3-5 key strengths of your business.' },
      { type: 'textarea', label: 'Weaknesses', placeholder: 'What areas need improvement? What resources are you lacking?', required: true, helpText: 'Be honest about areas that need improvement.' },
      { type: 'textarea', label: 'Opportunities', placeholder: 'What market trends or opportunities can you take advantage of?', required: true },
      { type: 'textarea', label: 'Threats', placeholder: 'What obstacles or risks does your business face?', required: true },
    ]},
    { title: 'Growth & Future Plans', description: 'Where are you heading?', isShared: false, questions: [
      { type: 'textarea', label: 'Short-term Goals (1 year)', placeholder: 'What do you want to achieve in the next 12 months?', required: true },
      { type: 'textarea', label: 'Long-term Goals (3-5 years)', placeholder: 'Where do you see the business in 3-5 years?', required: true },
      { type: 'textarea', label: 'Expansion Plans', placeholder: 'Do you plan to expand geographically, add products/services, or scale operations?', required: false },
      { type: 'radio', label: 'Do you plan to hire in the next 12 months?', options: JSON.stringify(['Yes', 'No', 'Maybe']), required: false },
      { type: 'number', label: 'Planned Hires', placeholder: 'Number of employees to hire', required: false },
    ]},
  ];

  for (let sIdx = 0; sIdx < bpSections.length; sIdx++) {
    const s = bpSections[sIdx];
    const section = await db.questionnaireSection.create({
      data: {
        templateId: bpTemplate.id,
        title: s.title,
        description: s.description,
        displayOrder: sIdx,
        isShared: s.isShared,
      },
    });
    for (let qIdx = 0; qIdx < s.questions.length; qIdx++) {
      const q = s.questions[qIdx];
      await db.questionnaireQuestion.create({
        data: {
          sectionId: section.id,
          type: q.type,
          label: q.label,
          placeholder: q.placeholder || '',
          required: q.required || false,
          options: q.options || '[]',
          helpText: q.helpText || '',
          displayOrder: qIdx,
        },
      });
    }
  }
  console.log('  ✓ Business Plan Questionnaire seeded (8 sections, 45+ questions)');

  // --- Market Research Questionnaire ---
  const mrTemplate = await db.questionnaireTemplate.upsert({
    where: { slug: 'market-research-questionnaire' },
    update: { status: 'published' },
    create: {
      name: 'Market Research Questionnaire',
      slug: 'market-research-questionnaire',
      description: 'Questionnaire for comprehensive market research reports. Covers industry analysis, target demographics, market size, trends, and customer behaviour.',
      version: '1.0',
      status: 'published',
    },
  });

  const mrSections = [
    { title: 'Research Objectives', description: 'What do you need to find out?', isShared: false, questions: [
      { type: 'textarea', label: 'Primary Research Objectives', placeholder: 'What specific questions do you need answered?', required: true },
      { type: 'multiselect', label: 'Research Focus Areas', options: JSON.stringify(['Market Size & Growth', 'Customer Demographics', 'Competitor Analysis', 'Industry Trends', 'Pricing Analysis', 'Customer Behaviour', 'Regulatory Environment', 'Distribution Channels', 'Technology Impact', 'Other']), required: true },
      { type: 'textarea', label: 'Key Decisions This Research Will Inform', placeholder: 'What business decisions will be based on this research?', required: true },
    ]},
    { title: 'Target Market Details', description: 'Who are you researching?', isShared: true, questions: [
      { type: 'textarea', label: 'Ideal Customer Profile', placeholder: 'Describe your ideal customer in detail (age, income, location, interests, etc.)', required: true },
      { type: 'text', label: 'Geographic Scope', placeholder: 'e.g., South Africa, Gauteng only, SADC region', required: true },
      { type: 'number', label: 'Estimated Target Market Size (customers)', placeholder: 'e.g., 50000', required: false },
    ]},
    { title: 'Competitive Landscape', description: 'Who are the players?', isShared: true, questions: [
      { type: 'textarea', label: 'Known Competitors', placeholder: 'List competitors you are aware of, including their market share if known', required: true },
      { type: 'select', label: 'Market Position', options: JSON.stringify(['New Entrant', 'Small Player', 'Mid-size Player', 'Major Player', 'Market Leader']), required: true },
    ]},
    { title: 'Products & Services', description: 'What are you offering?', isShared: false, questions: [
      { type: 'textarea', label: 'Products/Services to Research', placeholder: 'Describe the specific products or services you need market data on', required: true },
      { type: 'radio', label: 'Is this a new or existing offering?', options: JSON.stringify(['New Product/Service', 'Existing Product/Service', 'Improvement to Existing', 'Diversification']), required: true },
      { type: 'number', label: 'Current Monthly Sales (units or R)', placeholder: 'If applicable', required: false },
    ]},
  ];

  for (let sIdx = 0; sIdx < mrSections.length; sIdx++) {
    const s = mrSections[sIdx];
    const section = await db.questionnaireSection.create({
      data: {
        templateId: mrTemplate.id,
        title: s.title,
        description: s.description,
        displayOrder: sIdx,
        isShared: s.isShared,
      },
    });
    for (let qIdx = 0; qIdx < s.questions.length; qIdx++) {
      const q = s.questions[qIdx];
      await db.questionnaireQuestion.create({
        data: {
          sectionId: section.id,
          type: q.type,
          label: q.label,
          placeholder: q.placeholder || '',
          required: q.required || false,
          options: q.options || '[]',
          helpText: q.helpText || '',
          displayOrder: qIdx,
        },
      });
    }
  }
  console.log('  ✓ Market Research Questionnaire seeded (4 sections, 12+ questions)');

  // --- Financial Assessment Questionnaire ---
  const faTemplate = await db.questionnaireTemplate.upsert({
    where: { slug: 'financial-assessment-questionnaire' },
    update: { status: 'published' },
    create: {
      name: 'Financial Assessment Questionnaire',
      slug: 'financial-assessment-questionnaire',
      description: 'Questionnaire for financial assessment reports. Covers revenue, expenses, profitability, cash flow, and financial health.',
      version: '1.0',
      status: 'published',
    },
  });

  const faSections = [
    { title: 'Revenue & Sales', description: 'Your income and revenue streams', isShared: false, questions: [
      { type: 'select', label: 'Primary Revenue Model', options: JSON.stringify(['Product Sales', 'Service Fees', 'Subscription/Recurring', 'Commission-based', 'Advertising', 'Licensing', 'Mixed/Other']), required: true },
      { type: 'number', label: 'Annual Revenue (ZAR)', placeholder: 'e.g., 1500000', required: true },
      { type: 'number', label: 'Monthly Revenue (ZAR)', placeholder: 'e.g., 125000', required: true },
      { type: 'radio', label: 'Revenue Trend', options: JSON.stringify(['Growing', 'Stable', 'Declining', 'Seasonal', 'Too early to tell']), required: true },
      { type: 'textarea', label: 'Main Revenue Streams', placeholder: 'List your top 3 revenue streams and their approximate contribution', required: true },
    ]},
    { title: 'Expenses & Costs', description: 'Your costs and expenditure', isShared: false, questions: [
      { type: 'number', label: 'Monthly Operating Expenses (ZAR)', placeholder: 'e.g., 80000', required: true },
      { type: 'number', label: 'Monthly Salary/Wage Bill (ZAR)', placeholder: 'e.g., 40000', required: true },
      { type: 'number', label: 'Monthly Rent/Lease (ZAR)', placeholder: 'e.g., 15000', required: false },
      { type: 'radio', label: 'Do you use an accountant/bookkeeper?', options: JSON.stringify(['Yes - Internal', 'Yes - External/Outsourced', 'No - I handle it myself', 'No - Not yet']), required: true },
      { type: 'select', label: 'Accounting Software', options: JSON.stringify(['QuickBooks', 'Xero', 'Sage', 'Pastel', 'Excel/Spreadsheets', 'Other', 'None']), required: false },
    ]},
    { title: 'Financial Health', description: 'Overall financial position', isShared: false, questions: [
      { type: 'radio', label: 'Is the business currently profitable?', options: JSON.stringify(['Yes - Consistently', 'Yes - Sometimes', 'Breaking Even', 'No - Making a Loss', 'Too early to tell']), required: true },
      { type: 'radio', label: 'Cash Flow Status', options: JSON.stringify(['Healthy', 'Adequate', 'Tight', 'Negative/Critical']), required: true },
      { type: 'radio', label: 'Do you have outstanding debt?', options: JSON.stringify(['No debt', 'Yes - Bank Loan', 'Yes - Overdraft', 'Yes - Investor Funding', 'Yes - Other']), required: true },
      { type: 'number', label: 'Total Outstanding Debt (ZAR)', placeholder: 'e.g., 200000', required: false },
      { type: 'textarea', label: 'Financial Concerns', placeholder: 'What are your biggest financial challenges?', required: false },
    ]},
  ];

  for (let sIdx = 0; sIdx < faSections.length; sIdx++) {
    const s = faSections[sIdx];
    const section = await db.questionnaireSection.create({
      data: {
        templateId: faTemplate.id,
        title: s.title,
        description: s.description,
        displayOrder: sIdx,
        isShared: s.isShared,
      },
    });
    for (let qIdx = 0; qIdx < s.questions.length; qIdx++) {
      const q = faSections[sIdx].questions[qIdx];
      await db.questionnaireQuestion.create({
        data: {
          sectionId: section.id,
          type: q.type,
          label: q.label,
          placeholder: q.placeholder || '',
          required: q.required || false,
          options: q.options || '[]',
          helpText: q.helpText || '',
          displayOrder: qIdx,
        },
      });
    }
  }
  console.log('  ✓ Financial Assessment Questionnaire seeded (3 sections, 15+ questions)');

  // ═══════════════════════════════════════════════════════════
  // 6. SERVICES (12 services with enriched fields)
  // ═══════════════════════════════════════════════════════════
  const services = [
    { name: 'Business Feasibility Report', shortDesc: 'Assess the viability and potential success of your business idea', desc: 'Comprehensive assessment of the viability and potential success of your business idea, including market demand, financial projections, and risk analysis.', price: 2500, icon: 'clipboard-list', featured: true, cat: 'business-planning', turnaround: 7, qt: 'business-plan-questionnaire', reqDocs: JSON.stringify([{ name: 'Company Registration Document', required: false, category: 'company-registration' }]) },
    { name: 'Market Research Report', shortDesc: 'In-depth analysis of your target market and competitive landscape', desc: 'In-depth analysis of your target market, customer demographics, industry trends, and competitive landscape to inform strategic decisions.', price: 3000, icon: 'search', featured: false, cat: 'marketing', turnaround: 10, qt: 'market-research-questionnaire', reqDocs: JSON.stringify([{ name: 'Existing Market Data (if any)', required: false, category: 'other' }]) },
    { name: 'Competitor Analysis', shortDesc: 'Detailed evaluation of your competitors strategies and positioning', desc: "Detailed evaluation of your competitors' strategies, strengths, weaknesses, and market positioning to identify opportunities.", price: 2000, icon: 'users', featured: false, cat: 'strategy', turnaround: 5, qt: 'market-research-questionnaire', reqDocs: JSON.stringify([]) },
    { name: 'Startup Cost Estimate', shortDesc: 'Thorough breakdown of all expected costs to launch', desc: 'Thorough breakdown of all expected costs to launch your business, including one-time and recurring expenses.', price: 1500, icon: 'calculator', featured: false, cat: 'finance', turnaround: 5, qt: 'financial-assessment-questionnaire', reqDocs: JSON.stringify([{ name: 'Quote Documents (if available)', required: false, category: 'financial' }]) },
    { name: 'Marketing Strategy Report', shortDesc: 'Custom marketing plan covering digital and traditional channels', desc: 'Custom marketing plan covering digital and traditional channels, target audience segmentation, and budget allocation.', price: 3500, icon: 'megaphone', featured: true, cat: 'marketing', turnaround: 10, qt: 'market-research-questionnaire', reqDocs: JSON.stringify([{ name: 'Logo', required: false, category: 'branding' }, { name: 'Brand Guidelines', required: false, category: 'branding' }]) },
    { name: 'Financial Forecast Report', shortDesc: '3-5 year financial projections for investors or planning', desc: '3-5 year financial projections including revenue, expenses, cash flow, and break-even analysis for investors or planning.', price: 3000, icon: 'trending-up', featured: false, cat: 'finance', turnaround: 7, qt: 'financial-assessment-questionnaire', reqDocs: JSON.stringify([{ name: 'Historical Financial Statements', required: true, category: 'financial' }, { name: 'Bank Statements (3 months)', required: false, category: 'financial' }]) },
    { name: 'Risk Assessment Report', shortDesc: 'Identification and evaluation of potential business risks', desc: 'Identification and evaluation of potential business risks with mitigation strategies and contingency planning.', price: 2500, icon: 'shield-alert', featured: false, cat: 'strategy', turnaround: 7, qt: 'business-plan-questionnaire', reqDocs: JSON.stringify([]) },
    { name: 'Business Plan', shortDesc: 'Comprehensive, investor-ready business plan', desc: 'Comprehensive, investor-ready business plan including executive summary, market analysis, financials, and operations plan.', price: 4000, icon: 'file-text', featured: true, cat: 'business-planning', turnaround: 14, qt: 'business-plan-questionnaire', reqDocs: JSON.stringify([{ name: 'Company Registration Document', required: true, category: 'company-registration' }, { name: 'Financial Statements', required: false, category: 'financial' }, { name: 'Logo', required: false, category: 'branding' }]) },
    { name: 'Investor Readiness Report', shortDesc: "Assessment of your business's readiness for investment", desc: "Assessment of your business's readiness for investment, covering financials, governance, growth potential, and pitch preparation.", price: 3500, icon: 'presentation', featured: false, cat: 'finance', turnaround: 10, qt: 'financial-assessment-questionnaire', reqDocs: JSON.stringify([{ name: 'Financial Statements (2 years)', required: true, category: 'financial' }, { name: 'Business Plan', required: true, category: 'other' }, { name: 'Cash Flow Projections', required: true, category: 'financial' }]) },
    { name: 'Business Health Check', shortDesc: 'Diagnostic review of your operations and strategy', desc: 'Diagnostic review of your existing business operations, financials, and strategy with actionable improvement recommendations.', price: 2000, icon: 'heart-pulse', featured: false, cat: 'strategy', turnaround: 7, qt: 'business-plan-questionnaire', reqDocs: JSON.stringify([]) },
    { name: 'Consulting & Strategy Session', shortDesc: 'One-on-one consulting session with expert advisors', desc: 'One-on-one consulting session with our expert advisors to address specific business challenges and opportunities.', price: 1200, icon: 'handshake', featured: false, cat: 'strategy', turnaround: 3, qt: '', reqDocs: JSON.stringify([]) },
    { name: 'Implementation Support', shortDesc: 'Hands-on support to implement recommended strategies', desc: 'Hands-on support to implement recommended strategies, processes, and systems for your business.', price: 2500, icon: 'wrench', featured: false, cat: 'business-planning', turnaround: 30, qt: '', reqDocs: JSON.stringify([]) },
  ];

  // Build a map of questionnaire template IDs by slug
  const qtMap: Record<string, string> = {
    'business-plan-questionnaire': bpTemplate.id,
    'market-research-questionnaire': mrTemplate.id,
    'financial-assessment-questionnaire': faTemplate.id,
  };

  for (let i = 0; i < services.length; i++) {
    const s = services[i];
    const slug = s.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    await db.service.upsert({
      where: { slug },
      update: {
        name: s.name,
        shortDescription: s.shortDesc,
        description: s.desc,
        price: s.price,
        icon: s.icon,
        featured: s.featured,
        categoryId: catIds[s.cat] || null,
        displayOrder: i,
        turnaround: s.turnaround,
        questionnaireTemplateId: qtMap[s.qt] || null,
        agreementTemplateId: agreement.id,
        requiredDocuments: s.reqDocs,
      },
      create: {
        name: s.name,
        slug,
        shortDescription: s.shortDesc,
        description: s.desc,
        price: s.price,
        icon: s.icon,
        featured: s.featured,
        categoryId: catIds[s.cat] || null,
        displayOrder: i,
        turnaround: s.turnaround,
        questionnaireTemplateId: qtMap[s.qt] || null,
        agreementTemplateId: agreement.id,
        requiredDocuments: s.reqDocs,
      },
    });
  }
  console.log('  ✓ Services seeded (12 with questionnaire/agreement links)');

  // ═══════════════════════════════════════════════════════════
  // 7. PLANS (3 subscription plans)
  // ═══════════════════════════════════════════════════════════
  const plansData = [
    { name: 'STARTER', subtitle: 'For new entrepreneurs', price: 299, color: '#2A9D8F', buttonLabel: 'GET STARTED', features: ['1 report per month', '10% discount on all services', 'Priority delivery', 'Email support', 'Monthly business tips'] },
    { name: 'PROFESSIONAL', subtitle: 'For growing businesses', price: 599, color: '#264653', buttonLabel: 'UPGRADE NOW', featured: true, features: ['2 reports per month', '15% discount on all services', 'Priority delivery', 'Email support', 'Monthly business tips', 'Early access to new reports'] },
    { name: 'BUSINESS PARTNER', subtitle: 'For serious entrepreneurs & teams', price: 999, color: '#F4A261', buttonLabel: 'JOIN NOW', features: ['4 reports per month', '20% discount on all services', 'Priority delivery', 'Email support', 'Monthly business tips', 'Early access to new reports', 'Dedicated account manager'] },
  ];

  for (let i = 0; i < plansData.length; i++) {
    const p = plansData[i];
    const slug = p.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    const plan = await db.plan.upsert({
      where: { slug },
      update: { name: p.name, subtitle: p.subtitle, price: p.price, color: p.color, buttonLabel: p.buttonLabel, featured: p.featured || false, displayOrder: i },
      create: { name: p.name, slug, subtitle: p.subtitle, price: p.price, color: p.color, buttonLabel: p.buttonLabel, featured: p.featured || false, displayOrder: i },
    });
    await db.planFeature.deleteMany({ where: { planId: plan.id } });
    for (const fText of p.features) {
      await db.planFeature.create({ data: { text: fText, planId: plan.id } });
    }
  }
  console.log('  ✓ Plans seeded (3 with features)');

  // ═══════════════════════════════════════════════════════════
  // 8. SAMPLE PROJECT (for demo/testing)
  // ═══════════════════════════════════════════════════════════
  const sampleProject = await db.project.create({
    data: {
      projectNumber: 'BV-2026-000001',
      clientName: 'Stefan van der Merwe',
      clientEmail: 'stefan@abcengineering.co.za',
      clientPhone: '082 377 7490',
      clientCompany: 'ABC Engineering (Pty) Ltd',
      woocommerceOrderId: 'WC-1054',
      status: 'awaiting-questionnaire',
      progressPercent: 25,
      assignedTo: '',
      notes: 'Client purchased Business Plan + Market Research. Awaiting questionnaire completion.',
    },
  });

  // Add services to the project
  const bpService = await db.service.findFirst({ where: { slug: 'business-plan' } });
  const mrService = await db.service.findFirst({ where: { slug: 'market-research-report' } });
  if (bpService) {
    await db.projectService.create({ data: { projectId: sampleProject.id, serviceId: bpService.id, status: 'in-progress' } });
  }
  if (mrService) {
    await db.projectService.create({ data: { projectId: sampleProject.id, serviceId: mrService.id, status: 'pending' } });
  }

  // Add signed agreement to the project
  await db.projectAgreement.create({
    data: {
      projectId: sampleProject.id,
      templateId: agreement.id,
      fullName: 'Stefan van der Merwe',
      ipAddress: '196.21.156.42',
      userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
      agreedAt: new Date('2026-07-20T14:30:00Z'),
    },
  });

  // Add questionnaire to the project (in-progress)
  if (bpService?.questionnaireTemplateId) {
    const pq = await db.projectQuestionnaire.create({
      data: {
        projectId: sampleProject.id,
        templateId: bpService.questionnaireTemplateId,
        status: 'in-progress',
      },
    });
    // Add some sample responses
    const bpSection = await db.questionnaireSection.findFirst({ where: { templateId: bpService.questionnaireTemplateId, title: 'Client Information' } });
    if (bpSection) {
      const questions = await db.questionnaireQuestion.findMany({ where: { sectionId: bpSection.id }, orderBy: { displayOrder: 'asc' }, take: 3 });
      const sampleResponses = ['Stefan van der Merwe', 'stefan@abcengineering.co.za', '082 377 7490'];
      for (let i = 0; i < questions.length; i++) {
        await db.projectResponse.create({
          data: { questionnaireId: pq.id, questionId: questions[i].id, value: sampleResponses[i] || '' },
        });
      }
    }
  }

  // Add sample documents to the project
  await db.projectDocument.createMany({
    data: [
      { projectId: sampleProject.id, name: 'Company Registration', filename: 'cipc-registration.pdf', filepath: '/documents/cipc-registration.pdf', filesize: 245000, mimeType: 'application/pdf', category: 'company-registration', uploadedBy: 'stefan@abcengineering.co.za' },
      { projectId: sampleProject.id, name: 'Logo', filename: 'abc-engineering-logo.png', filepath: '/documents/abc-engineering-logo.png', filesize: 89000, mimeType: 'image/png', category: 'branding', uploadedBy: 'stefan@abcengineering.co.za' },
    ],
  });

  console.log('  ✓ Sample Project seeded (BV-2026-000001 with services, agreement, questionnaire, documents)');

  console.log('\n✅ Database seeded successfully!');
  console.log('   Tables: Category, Service, Plan, PlanFeature, PluginSetting, Icon,');
  console.log('           QuestionnaireTemplate, QuestionnaireSection, QuestionnaireQuestion,');
  console.log('           AgreementTemplate, Project, ProjectService, ProjectAgreement,');
  console.log('           ProjectQuestionnaire, ProjectResponse, ProjectDocument');
}

seed()
  .catch((e) => {
    console.error('Seed failed:', e);
    process.exit(1);
  })
  .finally(() => process.exit(0));
