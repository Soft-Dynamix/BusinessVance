'use client';

import { Shield, Clock, Lock, Star, Globe, Phone, Mail } from 'lucide-react';

const trustBadges = [
  {
    icon: Shield,
    label: 'PROFESSIONAL QUALITY REPORTS',
  },
  {
    icon: Clock,
    label: 'FAST TURNAROUND',
  },
  {
    icon: Lock,
    label: '100% CONFIDENTIAL & SECURE',
  },
  {
    icon: Star,
    label: 'ACTIONABLE RECOMMENDATIONS',
  },
];

export function PublicFooter() {
  return (
    <footer style={{ backgroundColor: '#002B5C' }} className="mt-auto">
      {/* Trust Badges */}
      <div className="border-t border-white/10">
        <div className="max-w-6xl mx-auto px-4 py-10">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-8">
            {trustBadges.map((badge) => (
              <div key={badge.label} className="flex flex-col items-center text-center gap-3">
                <div className="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center">
                  <badge.icon className="w-6 h-6 text-[#D4AF37]" />
                </div>
                <span className="text-white text-xs font-semibold tracking-wider leading-tight">
                  {badge.label}
                </span>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Contact Info */}
      <div className="border-t border-white/10">
        <div className="max-w-6xl mx-auto px-4 py-8">
          <div className="flex flex-col md:flex-row items-center justify-center gap-4 md:gap-8 text-white/80 text-sm">
            <a
              href="https://www.studyvance.co.za"
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-2 hover:text-[#D4AF37] transition-colors"
            >
              <Globe className="w-4 h-4" />
              www.studyvance.co.za
            </a>
            <span className="hidden md:block text-white/30">|</span>
            <a
              href="tel:0823777490"
              className="flex items-center gap-2 hover:text-[#D4AF37] transition-colors"
            >
              <Phone className="w-4 h-4" />
              082 377 7490
            </a>
            <span className="hidden md:block text-white/30">|</span>
            <a
              href="mailto:info@businessvance.co.za"
              className="flex items-center gap-2 hover:text-[#D4AF37] transition-colors"
            >
              <Mail className="w-4 h-4" />
              info@businessvance.co.za
            </a>
          </div>
        </div>
      </div>

      {/* Copyright */}
      <div className="border-t border-white/10">
        <div className="max-w-6xl mx-auto px-4 py-4">
          <p className="text-center text-white/40 text-xs">
            © {new Date().getFullYear()} BusinessVance. All rights reserved.
          </p>
        </div>
      </div>
    </footer>
  );
}