'use client';

import { motion } from 'framer-motion';
import {
  Shield,
  Settings,
  CreditCard,
  GripVertical,
  Eye,
  Star,
  Code2,
  Smartphone,
  Database,
  LayoutGrid,
  Palette,
  ArrowRight,
  Download,
  FileCode2,
  FolderTree,
  Copy,
  Check,
  ExternalLink,
  Phone,
  Mail,
  Globe,
  ChevronRight,
  Zap,
  Layers,
  RefreshCw,
  Package,
  CheckCircle2,
  Circle,
  Table2,
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useState } from 'react';

// ─── Brand Colors ───────────────────────────────────────────────────────────
const NAVY = '#002B5C';
const GOLD = '#D4AF37';
const GOLD_DARK = '#B8962E';
const TEAL = '#008080';
const TEAL_LIGHT = '#00A0A0';
const NAVY_LIGHT = '#003d7a';

// ─── Animation Variants ─────────────────────────────────────────────────────
const fadeInUp = {
  hidden: { opacity: 0, y: 24 },
  visible: (i: number) => ({
    opacity: 1,
    y: 0,
    transition: { delay: i * 0.08, duration: 0.5, ease: 'easeOut' },
  }),
};

const staggerContainer = {
  hidden: {},
  visible: { transition: { staggerChildren: 0.08 } },
};

const scaleIn = {
  hidden: { opacity: 0, scale: 0.95 },
  visible: { opacity: 1, scale: 1, transition: { duration: 0.5, ease: 'easeOut' } },
};

// ─── Copy Button Component ──────────────────────────────────────────────────
function CopyButton({ text }: { text: string }) {
  const [copied, setCopied] = useState(false);
  const handleCopy = () => {
    navigator.clipboard.writeText(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };
  return (
    <button
      onClick={handleCopy}
      className="absolute top-2 right-2 p-1.5 rounded-md transition-colors hover:bg-white/20"
      style={{ color: 'rgba(255,255,255,0.7)' }}
      aria-label="Copy to clipboard"
    >
      {copied ? <Check className="w-3.5 h-3.5" /> : <Copy className="w-3.5 h-3.5" />}
    </button>
  );
}

// ─── Feature Data ───────────────────────────────────────────────────────────
const features = [
  {
    icon: Settings,
    title: 'Dynamic Service Management',
    description: 'Full CRUD for services and plans directly from WordPress Admin. No coding required.',
    color: NAVY,
  },
  {
    icon: CreditCard,
    title: 'WooCommerce + Yoco Payments',
    description: 'Seamless product linking with Yoco payment gateway for South African businesses.',
    color: TEAL,
  },
  {
    icon: GripVertical,
    title: 'Drag & Drop Reordering',
    description: 'Intuitively reorder services and plans with simple drag-and-drop in the admin panel.',
    color: GOLD,
  },
  {
    icon: Eye,
    title: 'Visibility & Featured Badges',
    description: 'Toggle service visibility and mark items as featured to highlight key offerings.',
    color: NAVY,
  },
  {
    icon: Code2,
    title: '3 Flexible Shortcodes',
    description: 'Full page, once-off only, or subscriptions only — choose what to display.',
    color: TEAL,
  },
  {
    icon: Smartphone,
    title: 'Mobile Responsive',
    description: 'Services table transforms to stacked cards on mobile for optimal user experience.',
    color: GOLD,
  },
  {
    icon: Database,
    title: '4 Custom Database Tables',
    description: 'Dedicated tables for services, plans, categories, and settings — fully normalized.',
    color: NAVY,
  },
  {
    icon: LayoutGrid,
    title: 'Pre-loaded Sample Data',
    description: '11 sample services and 3 subscription plans included so you can start immediately.',
    color: TEAL,
  },
  {
    icon: Palette,
    title: '60+ SVG Icons Available',
    description: 'Extensive icon library to visually represent each service in the frontend table.',
    color: GOLD,
  },
];

// ─── Installation Steps ─────────────────────────────────────────────────────
const installSteps = [
  {
    step: 1,
    title: 'Upload Plugin',
    description: 'Upload the `businessvance-services-manager` folder to `/wp-content/plugins/` via FTP or the WordPress plugin uploader.',
  },
  {
    step: 2,
    title: 'Activate Plugin',
    description: 'Go to WP Admin → Plugins and activate "BusinessVance Services Manager". Database tables are created automatically.',
  },
  {
    step: 3,
    title: 'Ensure WooCommerce',
    description: 'Make sure WooCommerce is installed and active. This plugin requires WooCommerce for payment integration.',
  },
  {
    step: 4,
    title: 'Configure Yoco Gateway',
    description: 'Set up the Yoco payment gateway in WooCommerce → Settings → Payments. Obtain your Yoco API keys.',
  },
  {
    step: 5,
    title: 'Create Products',
    description: 'Create WooCommerce products for each service and plan. Note down the Product IDs for linking.',
  },
  {
    step: 6,
    title: 'Manage Services & Plans',
    description: 'Navigate to the BusinessVance menu in WP Admin. Add, edit, reorder, and configure your services and plans.',
  },
  {
    step: 7,
    title: 'Link to Products',
    description: 'Link each service/plan to its corresponding WooCommerce Product ID to enable the "Order Now" / "Subscribe" buttons.',
  },
  {
    step: 8,
    title: 'Add Shortcode',
    description: 'Create a new WordPress page and add the shortcode `[businessvance_services]` to display the full services page.',
  },
];

// ─── Shortcode Data ─────────────────────────────────────────────────────────
const shortcodes = [
  {
    code: '[businessvance_services]',
    name: 'Full Page',
    description: 'Renders the complete services page with header, once-off services table, subscription plan cards, and footer — all in one shortcode.',
    includes: ['Page header with branding', 'Services table with icons', 'Subscription plan cards', 'Footer section'],
  },
  {
    code: '[businessvance_onceoff]',
    name: 'Once-Off Services',
    description: 'Displays only the once-off services section — ideal for embedding in an existing page alongside your own content.',
    includes: ['Services table with icons', 'Order Now buttons', 'Mobile responsive layout'],
  },
  {
    code: '[businessvance_subscriptions]',
    name: 'Subscription Plans',
    description: 'Renders only the monthly subscription plan cards — perfect for a dedicated pricing/plans page.',
    includes: ['3-column plan cards', 'Feature lists', 'Subscribe buttons', 'Popular badge on featured plan'],
  },
];

// ─── File Tree Data ─────────────────────────────────────────────────────────
const fileTree = [
  { name: 'businessvance-services-manager/', type: 'folder', depth: 0 },
  { name: 'businessvance-services-manager.php', type: 'file', depth: 1, desc: 'Main plugin file' },
  { name: 'includes/', type: 'folder', depth: 1 },
  { name: 'class-bv-activator.php', type: 'file', depth: 2, desc: 'Database creation' },
  { name: 'class-bv-admin.php', type: 'file', depth: 2, desc: 'Admin menus, pages, AJAX CRUD' },
  { name: 'class-bv-shortcodes.php', type: 'file', depth: 2, desc: 'Frontend rendering' },
  { name: 'assets/', type: 'folder', depth: 1 },
  { name: 'css/', type: 'folder', depth: 2 },
  { name: 'admin.css', type: 'file', depth: 3, desc: 'Admin panel styles' },
  { name: 'frontend.css', type: 'file', depth: 3, desc: 'Frontend styles' },
  { name: 'js/', type: 'folder', depth: 2 },
  { name: 'admin.js', type: 'file', depth: 3, desc: 'Admin panel scripts' },
];

// ─── Mock Service Data for Preview ──────────────────────────────────────────
const mockServices = [
  { name: 'Company Registration', price: 'R 1,500', icon: '🏢' },
  { name: 'B-BBEE Affidavit', price: 'R 500', icon: '📋' },
  { name: 'Tax Clearance Certificate', price: 'R 350', icon: '✅' },
  { name: 'Annual Financial Statements', price: 'R 3,500', icon: '📊' },
  { name: 'Business Plan', price: 'R 2,000', icon: '📝' },
];

const mockPlans = [
  {
    name: 'Starter',
    price: 'R 499',
    period: '/month',
    color: TEAL,
    features: ['Up to 5 services', 'Basic support', 'Email reports', 'Standard templates'],
    popular: false,
  },
  {
    name: 'Professional',
    price: 'R 999',
    period: '/month',
    color: NAVY,
    features: ['Unlimited services', 'Priority support', 'Custom branding', 'Advanced analytics', 'API access'],
    popular: true,
  },
  {
    name: 'Enterprise',
    price: 'R 2,499',
    period: '/month',
    color: GOLD,
    features: ['Everything in Pro', 'Dedicated manager', 'White-label option', 'Custom integrations', 'SLA guarantee', 'Training sessions'],
    popular: false,
  },
];

// ═══════════════════════════════════════════════════════════════════════════
// Main Page Component
// ═══════════════════════════════════════════════════════════════════════════
export default function Home() {
  return (
    <div className="min-h-screen flex flex-col bg-white">
      {/* ─── Navigation Bar ─────────────────────────────────────────────── */}
      <nav
        className="sticky top-0 z-50 border-b backdrop-blur-md"
        style={{
          backgroundColor: 'rgba(0, 43, 92, 0.97)',
          borderColor: 'rgba(212, 175, 55, 0.2)',
        }}
      >
        <div className="max-w-6xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <Shield className="w-5 h-5" style={{ color: GOLD }} />
            <span className="text-white font-bold text-sm tracking-wide">BusinessVance</span>
            <Badge
              className="text-[10px] font-semibold ml-1"
              style={{ backgroundColor: 'rgba(212,175,55,0.15)', color: GOLD, borderColor: 'rgba(212,175,55,0.3)' }}
              variant="outline"
            >
              v1.0
            </Badge>
          </div>
          <div className="flex items-center gap-4">
            <a
              href="#features"
              className="text-white/70 hover:text-white text-sm transition-colors hidden sm:block"
            >
              Features
            </a>
            <a
              href="#installation"
              className="text-white/70 hover:text-white text-sm transition-colors hidden sm:block"
            >
              Install
            </a>
            <a
              href="#shortcodes"
              className="text-white/70 hover:text-white text-sm transition-colors hidden sm:block"
            >
              Shortcodes
            </a>
            <a
              href="#preview"
              className="text-white/70 hover:text-white text-sm transition-colors hidden sm:block"
            >
              Preview
            </a>
          </div>
        </div>
      </nav>

      <main className="flex-1">
        {/* ─── Hero Section ─────────────────────────────────────────────── */}
        <section
          className="relative overflow-hidden"
          style={{ background: `linear-gradient(135deg, ${NAVY} 0%, ${NAVY_LIGHT} 50%, ${TEAL} 100%)` }}
        >
          {/* Decorative elements */}
          <div className="absolute inset-0 overflow-hidden pointer-events-none">
            <div
              className="absolute -top-24 -right-24 w-96 h-96 rounded-full opacity-10"
              style={{ backgroundColor: GOLD }}
            />
            <div
              className="absolute -bottom-32 -left-32 w-[28rem] h-[28rem] rounded-full opacity-5"
              style={{ backgroundColor: GOLD }}
            />
            <div
              className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[40rem] h-[40rem] rounded-full opacity-[0.03]"
              style={{ backgroundColor: '#fff' }}
            />
          </div>

          <div className="relative max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-24 lg:py-32">
            <motion.div
              initial="hidden"
              animate="visible"
              variants={staggerContainer}
              className="text-center"
            >
              {/* Shield Icon */}
              <motion.div variants={fadeInUp} custom={0} className="flex justify-center mb-6">
                <div
                  className="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl flex items-center justify-center shadow-2xl"
                  style={{
                    background: `linear-gradient(135deg, ${GOLD} 0%, ${GOLD_DARK} 100%)`,
                    boxShadow: `0 20px 60px -10px rgba(212, 175, 55, 0.4)`,
                  }}
                >
                  <Shield className="w-10 h-10 sm:w-12 sm:h-12" style={{ color: NAVY }} />
                </div>
              </motion.div>

              {/* Title */}
              <motion.h1
                variants={fadeInUp}
                custom={1}
                className="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-white tracking-tight mb-4"
              >
                BusinessVance{' '}
                <span style={{ color: GOLD }}>Services Manager</span>
              </motion.h1>

              {/* Tagline */}
              <motion.p
                variants={fadeInUp}
                custom={2}
                className="text-lg sm:text-xl text-white/70 max-w-2xl mx-auto mb-8 leading-relaxed"
              >
                WordPress Plugin for Dynamic Service Management
              </motion.p>

              {/* Description */}
              <motion.p
                variants={fadeInUp}
                custom={3}
                className="text-sm sm:text-base text-white/50 max-w-xl mx-auto mb-10 leading-relaxed"
              >
                A complete solution for managing and displaying business services with WooCommerce integration,
                Yoco payments, and beautiful frontend rendering — designed for South African businesses.
              </motion.p>

              {/* CTA Buttons */}
              <motion.div
                variants={fadeInUp}
                custom={4}
                className="flex flex-col sm:flex-row items-center justify-center gap-4"
              >
                <a href="#installation">
                  <Button
                    size="lg"
                    className="font-semibold text-base px-8 h-12 rounded-lg shadow-lg transition-all hover:shadow-xl hover:-translate-y-0.5"
                    style={{
                      backgroundColor: GOLD,
                      color: NAVY,
                    }}
                    onMouseEnter={(e) => {
                      e.currentTarget.style.backgroundColor = GOLD_DARK;
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.backgroundColor = GOLD;
                    }}
                  >
                    <Download className="w-4 h-4" />
                    Get Started
                  </Button>
                </a>
                <a href="#preview">
                  <Button
                    variant="outline"
                    size="lg"
                    className="font-semibold text-base px-8 h-12 rounded-lg border-white/20 text-white hover:bg-white/10 hover:text-white"
                  >
                    <Eye className="w-4 h-4" />
                    View Preview
                  </Button>
                </a>
              </motion.div>

              {/* Quick Stats */}
              <motion.div
                variants={fadeInUp}
                custom={5}
                className="flex flex-wrap items-center justify-center gap-6 sm:gap-10 mt-14 pt-10 border-t border-white/10"
              >
                {[
                  { label: 'Shortcodes', value: '3' },
                  { label: 'DB Tables', value: '4' },
                  { label: 'Sample Services', value: '11' },
                  { label: 'SVG Icons', value: '60+' },
                ].map((stat) => (
                  <div key={stat.label} className="text-center">
                    <div className="text-2xl sm:text-3xl font-bold" style={{ color: GOLD }}>
                      {stat.value}
                    </div>
                    <div className="text-xs sm:text-sm text-white/50 mt-1">{stat.label}</div>
                  </div>
                ))}
              </motion.div>
            </motion.div>
          </div>
        </section>

        {/* ─── Features Section ─────────────────────────────────────────── */}
        <section id="features" className="py-16 sm:py-24 bv-pattern-bg">
          <div className="max-w-6xl mx-auto px-4 sm:px-6">
            <motion.div
              initial="hidden"
              whileInView="visible"
              viewport={{ once: true, margin: '-80px' }}
              variants={staggerContainer}
            >
              {/* Section Header */}
              <motion.div variants={fadeInUp} custom={0} className="text-center mb-12 sm:mb-16">
                <Badge
                  className="mb-4 font-semibold"
                  style={{ backgroundColor: 'rgba(0,128,128,0.1)', color: TEAL, borderColor: 'rgba(0,128,128,0.2)' }}
                  variant="outline"
                >
                  <Zap className="w-3 h-3 mr-1" />
                  Features
                </Badge>
                <h2
                  className="text-3xl sm:text-4xl font-bold tracking-tight"
                  style={{ color: NAVY }}
                >
                  Everything You Need
                </h2>
                <p className="text-muted-foreground mt-3 max-w-xl mx-auto">
                  A comprehensive plugin built for businesses that need professional service display
                  with seamless payment integration.
                </p>
              </motion.div>

              {/* Feature Grid */}
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                {features.map((feature, i) => (
                  <motion.div key={feature.title} variants={fadeInUp} custom={i + 1}>
                    <Card className="h-full hover:shadow-lg transition-all duration-300 hover:-translate-y-1 border-gray-100 bg-white group">
                      <CardContent className="pt-6">
                        <div
                          className="w-11 h-11 rounded-xl flex items-center justify-center mb-4 transition-transform group-hover:scale-110"
                          style={{ backgroundColor: `${feature.color}10` }}
                        >
                          <feature.icon className="w-5 h-5" style={{ color: feature.color }} />
                        </div>
                        <h3 className="font-semibold text-base mb-2" style={{ color: NAVY }}>
                          {feature.title}
                        </h3>
                        <p className="text-sm text-muted-foreground leading-relaxed">
                          {feature.description}
                        </p>
                      </CardContent>
                    </Card>
                  </motion.div>
                ))}
              </div>
            </motion.div>
          </div>
        </section>

        {/* ─── Installation Section ─────────────────────────────────────── */}
        <section id="installation" style={{ backgroundColor: '#F8F9FA' }}>
          <div className="max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-24">
            <motion.div
              initial="hidden"
              whileInView="visible"
              viewport={{ once: true, margin: '-80px' }}
              variants={staggerContainer}
            >
              {/* Section Header */}
              <motion.div variants={fadeInUp} custom={0} className="text-center mb-12 sm:mb-16">
                <Badge
                  className="mb-4 font-semibold"
                  style={{ backgroundColor: 'rgba(0,43,92,0.1)', color: NAVY, borderColor: 'rgba(0,43,92,0.15)' }}
                  variant="outline"
                >
                  <Package className="w-3 h-3 mr-1" />
                  Installation
                </Badge>
                <h2
                  className="text-3xl sm:text-4xl font-bold tracking-tight"
                  style={{ color: NAVY }}
                >
                  Up & Running in 8 Steps
                </h2>
                <p className="text-muted-foreground mt-3 max-w-xl mx-auto">
                  From upload to live page — get your services displayed in minutes.
                </p>
              </motion.div>

              {/* Steps */}
              <div className="max-w-3xl mx-auto space-y-4">
                {installSteps.map((item, i) => (
                  <motion.div key={item.step} variants={fadeInUp} custom={i + 1}>
                    <Card className="border-gray-100 bg-white hover:shadow-md transition-shadow">
                      <CardContent className="py-5 px-6 flex gap-4">
                        <div
                          className="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center font-bold text-sm"
                          style={{ backgroundColor: `${NAVY}10`, color: NAVY }}
                        >
                          {item.step}
                        </div>
                        <div className="min-w-0">
                          <h3 className="font-semibold text-sm mb-1" style={{ color: NAVY }}>
                            {item.title}
                          </h3>
                          <p className="text-sm text-muted-foreground leading-relaxed">
                            {item.description}
                          </p>
                        </div>
                      </CardContent>
                    </Card>
                  </motion.div>
                ))}
              </div>
            </motion.div>
          </div>
        </section>

        {/* ─── Shortcodes Section ───────────────────────────────────────── */}
        <section id="shortcodes" className="py-16 sm:py-24 bv-pattern-bg">
          <div className="max-w-6xl mx-auto px-4 sm:px-6">
            <motion.div
              initial="hidden"
              whileInView="visible"
              viewport={{ once: true, margin: '-80px' }}
              variants={staggerContainer}
            >
              {/* Section Header */}
              <motion.div variants={fadeInUp} custom={0} className="text-center mb-12 sm:mb-16">
                <Badge
                  className="mb-4 font-semibold"
                  style={{ backgroundColor: `rgba(212,175,55,0.1)`, color: GOLD_DARK, borderColor: 'rgba(212,175,55,0.2)' }}
                  variant="outline"
                >
                  <Code2 className="w-3 h-3 mr-1" />
                  Shortcodes
                </Badge>
                <h2
                  className="text-3xl sm:text-4xl font-bold tracking-tight"
                  style={{ color: NAVY }}
                >
                  Flexible Shortcode API
                </h2>
                <p className="text-muted-foreground mt-3 max-w-xl mx-auto">
                  Three shortcodes give you full control over what content appears on each page.
                </p>
              </motion.div>

              {/* Shortcode Cards */}
              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {shortcodes.map((sc, i) => (
                  <motion.div key={sc.code} variants={fadeInUp} custom={i + 1}>
                    <Card className="h-full border-gray-100 bg-white hover:shadow-lg transition-all hover:-translate-y-1">
                      <CardHeader className="pb-0">
                        <div className="flex items-center gap-2 mb-2">
                          <div
                            className="w-8 h-8 rounded-lg flex items-center justify-center"
                            style={{ backgroundColor: `${TEAL}10` }}
                          >
                            <Code2 className="w-4 h-4" style={{ color: TEAL }} />
                          </div>
                          <Badge
                            variant="secondary"
                            className="text-xs font-semibold"
                            style={{ backgroundColor: `${NAVY}08`, color: NAVY }}
                          >
                            {sc.name}
                          </Badge>
                        </div>
                      </CardHeader>
                      <CardContent className="space-y-4">
                        {/* Code Block */}
                        <div className="relative rounded-lg p-4 font-mono text-sm" style={{ backgroundColor: NAVY }}>
                          <code className="text-white/90 text-xs sm:text-sm">{sc.code}</code>
                          <CopyButton text={sc.code} />
                        </div>
                        <p className="text-sm text-muted-foreground leading-relaxed">
                          {sc.description}
                        </p>
                        <Separator />
                        <div>
                          <p className="text-xs font-semibold uppercase tracking-wider mb-2" style={{ color: NAVY }}>
                            Includes
                          </p>
                          <ul className="space-y-1.5">
                            {sc.includes.map((item) => (
                              <li key={item} className="flex items-center gap-2 text-sm text-muted-foreground">
                                <CheckCircle2 className="w-3.5 h-3.5 flex-shrink-0" style={{ color: TEAL }} />
                                {item}
                              </li>
                            ))}
                          </ul>
                        </div>
                      </CardContent>
                    </Card>
                  </motion.div>
                ))}
              </div>
            </motion.div>
          </div>
        </section>

        {/* ─── File Structure Section ───────────────────────────────────── */}
        <section style={{ backgroundColor: '#F8F9FA' }}>
          <div className="max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-24">
            <motion.div
              initial="hidden"
              whileInView="visible"
              viewport={{ once: true, margin: '-80px' }}
              variants={staggerContainer}
            >
              {/* Section Header */}
              <motion.div variants={fadeInUp} custom={0} className="text-center mb-12 sm:mb-16">
                <Badge
                  className="mb-4 font-semibold"
                  style={{ backgroundColor: 'rgba(0,128,128,0.1)', color: TEAL, borderColor: 'rgba(0,128,128,0.2)' }}
                  variant="outline"
                >
                  <FolderTree className="w-3 h-3 mr-1" />
                  Structure
                </Badge>
                <h2
                  className="text-3xl sm:text-4xl font-bold tracking-tight"
                  style={{ color: NAVY }}
                >
                  Plugin File Structure
                </h2>
                <p className="text-muted-foreground mt-3 max-w-xl mx-auto">
                  Clean, organized architecture following WordPress best practices.
                </p>
              </motion.div>

              {/* File Tree */}
              <motion.div variants={fadeInUp} custom={1} className="max-w-2xl mx-auto">
                <Card className="border-gray-100 bg-white shadow-sm overflow-hidden">
                  <CardHeader
                    className="pb-0"
                    style={{ backgroundColor: NAVY, borderBottom: 'none' }}
                  >
                    <div className="flex items-center gap-2">
                      <FolderTree className="w-4 h-4 text-white/70" />
                      <CardTitle className="text-sm font-semibold text-white/80">
                        Plugin Directory
                      </CardTitle>
                    </div>
                  </CardHeader>
                  <CardContent className="p-4 sm:p-6">
                    <div className="font-mono text-sm space-y-1 bv-scrollbar max-h-96 overflow-y-auto">
                      {fileTree.map((item, i) => (
                        <div
                          key={i}
                          className="flex items-center gap-2.5 py-1.5 px-2 rounded-md hover:bg-gray-50 transition-colors"
                          style={{ paddingLeft: `${item.depth * 1.25 + 0.5}rem` }}
                        >
                          {item.type === 'folder' ? (
                            <Layers className="w-4 h-4 flex-shrink-0" style={{ color: GOLD }} />
                          ) : (
                            <FileCode2 className="w-4 h-4 flex-shrink-0 text-muted-foreground/50" />
                          )}
                          <span
                            className={
                              item.type === 'folder' ? 'font-semibold' : 'text-muted-foreground'
                            }
                          >
                            {item.name}
                          </span>
                          {item.desc && (
                            <span className="text-xs text-muted-foreground/60 ml-auto hidden sm:inline">
                              {/* {item.desc} */}
                            </span>
                          )}
                        </div>
                      ))}
                    </div>
                  </CardContent>
                </Card>
              </motion.div>
            </motion.div>
          </div>
        </section>

        {/* ─── Preview Section ──────────────────────────────────────────── */}
        <section id="preview" className="py-16 sm:py-24 bv-pattern-bg">
          <div className="max-w-6xl mx-auto px-4 sm:px-6">
            <motion.div
              initial="hidden"
              whileInView="visible"
              viewport={{ once: true, margin: '-80px' }}
              variants={staggerContainer}
            >
              {/* Section Header */}
              <motion.div variants={fadeInUp} custom={0} className="text-center mb-12 sm:mb-16">
                <Badge
                  className="mb-4 font-semibold"
                  style={{ backgroundColor: `${GOLD}15`, color: GOLD_DARK, borderColor: `${GOLD}30` }}
                  variant="outline"
                >
                  <Eye className="w-3 h-3 mr-1" />
                  Preview
                </Badge>
                <h2
                  className="text-3xl sm:text-4xl font-bold tracking-tight"
                  style={{ color: NAVY }}
                >
                  What Your Visitors See
                </h2>
                <p className="text-muted-foreground mt-3 max-w-xl mx-auto">
                  A static mockup of the services table and subscription plan cards as rendered by the plugin.
                </p>
              </motion.div>

              {/* Mock Services Table */}
              <motion.div variants={fadeInUp} custom={1} className="mb-16">
                <div className="max-w-4xl mx-auto">
                  {/* Table Header */}
                  <div
                    className="rounded-t-xl px-6 py-4 flex items-center gap-3"
                    style={{ backgroundColor: NAVY }}
                  >
                    <Table2 className="w-5 h-5 text-white/80" />
                    <span className="text-white font-bold uppercase tracking-wider text-sm sm:text-base">
                      Price List
                    </span>
                  </div>
                  <p
                    className="text-center font-bold uppercase tracking-widest text-xs sm:text-sm py-3"
                    style={{ color: GOLD }}
                  >
                    One-Time Reports &amp; Services
                  </p>
                  {/* Table */}
                  <div className="rounded-xl overflow-hidden border border-gray-200 shadow-sm">
                    {/* Table Head */}
                    <div
                      className="hidden sm:grid grid-cols-12 gap-4 px-6 py-3 text-xs font-semibold uppercase tracking-wider text-white/80"
                      style={{ backgroundColor: NAVY }}
                    >
                      <div className="col-span-1 text-center">#</div>
                      <div className="col-span-6">Service</div>
                      <div className="col-span-3 text-right">Price</div>
                      <div className="col-span-2 text-right">Action</div>
                    </div>
                    {/* Table Body */}
                    {mockServices.map((service, i) => (
                      <div
                        key={service.name}
                        className={`grid sm:grid-cols-12 gap-2 sm:gap-4 px-4 sm:px-6 py-4 items-center transition-colors hover:bg-gray-50 ${
                          i < mockServices.length - 1 ? 'border-b border-gray-100' : ''
                        }`}
                      >
                        {/* Mobile: stacked layout */}
                        <div className="sm:hidden flex items-center gap-3">
                          <span className="text-lg flex-shrink-0">{service.icon}</span>
                          <div className="flex-1 min-w-0">
                            <p className="font-semibold text-sm" style={{ color: NAVY }}>
                              {service.name}
                            </p>
                            <p className="text-sm font-bold mt-1" style={{ color: GOLD }}>
                              {service.price}
                            </p>
                          </div>
                          <button
                            className="flex-shrink-0 px-4 py-2 rounded-lg text-xs font-semibold transition-colors"
                            style={{ backgroundColor: GOLD, color: NAVY }}
                          >
                            Order
                          </button>
                        </div>
                        {/* Desktop: table layout */}
                        <div className="hidden sm:block col-span-1 text-center text-muted-foreground text-sm">
                          {i + 1}
                        </div>
                        <div className="hidden sm:flex col-span-6 items-center gap-3">
                          <span className="text-lg">{service.icon}</span>
                          <span className="font-medium text-sm" style={{ color: NAVY }}>
                            {service.name}
                          </span>
                        </div>
                        <div className="hidden sm:block col-span-3 text-right">
                          <span className="font-bold text-sm" style={{ color: NAVY }}>
                            {service.price}
                          </span>
                        </div>
                        <div className="hidden sm:block col-span-2 text-right">
                          <button
                            className="px-4 py-2 rounded-lg text-xs font-semibold transition-all hover:shadow-md"
                            style={{ backgroundColor: GOLD, color: NAVY }}
                          >
                            Order Now
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </motion.div>

              {/* Mock Plan Cards */}
              <motion.div variants={fadeInUp} custom={2}>
                <div className="max-w-4xl mx-auto">
                  {/* Section Header */}
                  <div
                    className="rounded-t-xl px-6 py-4 flex items-center gap-3"
                    style={{ backgroundColor: NAVY }}
                  >
                    <RefreshCw className="w-5 h-5 text-white/80" />
                    <span className="text-white font-bold uppercase tracking-wider text-sm sm:text-base">
                      Monthly Subscription Plans
                    </span>
                  </div>
                  <p
                    className="text-center font-bold uppercase tracking-widest text-xs sm:text-sm py-3"
                    style={{ color: GOLD }}
                  >
                    Choose the Plan That Grows With You
                  </p>

                  {/* Cards Grid */}
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {mockPlans.map((plan) => (
                      <div
                        key={plan.name}
                        className="rounded-xl overflow-hidden border border-gray-200 shadow-sm hover:shadow-lg transition-all bg-white"
                      >
                        {/* Card Header */}
                        <div
                          className="px-5 py-4 text-center relative"
                          style={{ backgroundColor: plan.color }}
                        >
                          {plan.popular && (
                            <div
                              className="absolute -top-0 right-4 px-3 py-1 rounded-b-lg text-[10px] font-bold uppercase tracking-wider"
                              style={{ backgroundColor: GOLD, color: NAVY }}
                            >
                              Popular
                            </div>
                          )}
                          <h3
                            className="font-bold text-lg"
                            style={{ color: plan.color === GOLD ? NAVY : '#fff' }}
                          >
                            {plan.name}
                          </h3>
                        </div>
                        {/* Card Body */}
                        <div className="px-5 py-6">
                          <div className="text-center mb-6">
                            <span
                              className="text-3xl font-bold"
                              style={{ color: plan.color === GOLD ? GOLD_DARK : NAVY }}
                            >
                              {plan.price}
                            </span>
                            <span className="text-muted-foreground text-sm">{plan.period}</span>
                          </div>
                          <ul className="space-y-3 mb-6">
                            {plan.features.map((feature) => (
                              <li key={feature} className="flex items-start gap-2.5 text-sm text-muted-foreground">
                                <CheckCircle2
                                  className="w-4 h-4 flex-shrink-0 mt-0.5"
                                  style={{ color: plan.color === GOLD ? GOLD_DARK : TEAL }}
                                />
                                {feature}
                              </li>
                            ))}
                          </ul>
                          <button
                            className="w-full py-2.5 rounded-lg text-sm font-semibold transition-all hover:shadow-md"
                            style={{
                              backgroundColor: plan.color === GOLD ? GOLD : plan.color,
                              color: plan.color === GOLD ? NAVY : '#fff',
                            }}
                          >
                            Subscribe
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </motion.div>
            </motion.div>
          </div>
        </section>

        {/* ─── CTA Section ──────────────────────────────────────────────── */}
        <section
          className="relative overflow-hidden"
          style={{ background: `linear-gradient(135deg, ${NAVY} 0%, ${TEAL} 100%)` }}
        >
          <div className="absolute inset-0 overflow-hidden pointer-events-none">
            <div
              className="absolute -top-20 -right-20 w-72 h-72 rounded-full opacity-10"
              style={{ backgroundColor: GOLD }}
            />
          </div>
          <div className="relative max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-20 text-center">
            <motion.div
              initial="hidden"
              whileInView="visible"
              viewport={{ once: true, margin: '-80px' }}
              variants={staggerContainer}
            >
              <motion.h2
                variants={fadeInUp}
                custom={0}
                className="text-2xl sm:text-3xl md:text-4xl font-bold text-white tracking-tight mb-4"
              >
                Ready to Showcase Your Services?
              </motion.h2>
              <motion.p
                variants={fadeInUp}
                custom={1}
                className="text-white/60 max-w-xl mx-auto mb-8"
              >
                Get BusinessVance Services Manager today and transform how you display and sell
                your business services online.
              </motion.p>
              <motion.div variants={fadeInUp} custom={2} className="flex flex-col sm:flex-row items-center justify-center gap-4">
                <Button
                  size="lg"
                  className="font-semibold text-base px-8 h-12 rounded-lg shadow-lg transition-all hover:shadow-xl hover:-translate-y-0.5"
                  style={{ backgroundColor: GOLD, color: NAVY }}
                  onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = GOLD_DARK; }}
                  onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = GOLD; }}
                >
                  <Download className="w-4 h-4" />
                  Download Plugin
                </Button>
                <Button
                  variant="outline"
                  size="lg"
                  className="font-semibold text-base px-8 h-12 rounded-lg border-white/20 text-white hover:bg-white/10 hover:text-white"
                >
                  <ExternalLink className="w-4 h-4" />
                  Visit Website
                </Button>
              </motion.div>
            </motion.div>
          </div>
        </section>
      </main>

      {/* ─── Footer ─────────────────────────────────────────────────────── */}
      <footer
        className="border-t"
        style={{ backgroundColor: NAVY, borderColor: 'rgba(255,255,255,0.05)' }}
      >
        <div className="max-w-6xl mx-auto px-4 sm:px-6 py-10 sm:py-12">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            {/* Brand */}
            <div className="sm:col-span-2 lg:col-span-1">
              <div className="flex items-center gap-2.5 mb-4">
                <Shield className="w-6 h-6" style={{ color: GOLD }} />
                <span className="text-white font-bold text-lg tracking-wide">BusinessVance</span>
              </div>
              <p className="text-white/40 text-sm leading-relaxed max-w-xs">
                Professional WordPress plugin for dynamic service management and display with
                WooCommerce payment integration.
              </p>
            </div>

            {/* Quick Links */}
            <div>
              <h4 className="text-white/70 font-semibold text-sm uppercase tracking-wider mb-4">
                Quick Links
              </h4>
              <ul className="space-y-2.5">
                {['Features', 'Installation', 'Shortcodes', 'Preview'].map((link) => (
                  <li key={link}>
                    <a
                      href={`#${link.toLowerCase()}`}
                      className="text-white/40 hover:text-white text-sm transition-colors flex items-center gap-1.5"
                    >
                      <ChevronRight className="w-3 h-3" />
                      {link}
                    </a>
                  </li>
                ))}
              </ul>
            </div>

            {/* Plugin Info */}
            <div>
              <h4 className="text-white/70 font-semibold text-sm uppercase tracking-wider mb-4">
                Plugin Info
              </h4>
              <ul className="space-y-2.5">
                {[
                  'WordPress 6.0+',
                  'WooCommerce Required',
                  'Yoco Gateway',
                  'PHP 7.4+',
                  'South Africa',
                ].map((info) => (
                  <li key={info} className="text-white/40 text-sm flex items-center gap-2">
                    <Circle className="w-1.5 h-1.5 fill-current" style={{ color: GOLD }} />
                    {info}
                  </li>
                ))}
              </ul>
            </div>

            {/* Contact */}
            <div>
              <h4 className="text-white/70 font-semibold text-sm uppercase tracking-wider mb-4">
                Contact
              </h4>
              <ul className="space-y-3">
                <li>
                  <a
                    href="https://www.studyvance.co.za"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-white/40 hover:text-white text-sm transition-colors flex items-center gap-2.5"
                  >
                    <Globe className="w-4 h-4" style={{ color: GOLD }} />
                    www.studyvance.co.za
                  </a>
                </li>
                <li>
                  <a
                    href="tel:0823777490"
                    className="text-white/40 hover:text-white text-sm transition-colors flex items-center gap-2.5"
                  >
                    <Phone className="w-4 h-4" style={{ color: GOLD }} />
                    082 377 7490
                  </a>
                </li>
                <li>
                  <a
                    href="mailto:info@businessvance.co.za"
                    className="text-white/40 hover:text-white text-sm transition-colors flex items-center gap-2.5"
                  >
                    <Mail className="w-4 h-4" style={{ color: GOLD }} />
                    info@businessvance.co.za
                  </a>
                </li>
              </ul>
            </div>
          </div>

          {/* Bottom Bar */}
          <Separator className="my-8" style={{ backgroundColor: 'rgba(255,255,255,0.08)' }} />
          <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
            <p className="text-white/30 text-xs sm:text-sm">
              &copy; {new Date().getFullYear()} BusinessVance. All rights reserved.
            </p>
            <p className="text-white/20 text-xs">
              Designed for South African Businesses
            </p>
          </div>
        </div>
      </footer>
    </div>
  );
}