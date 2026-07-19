'use client';

import { motion } from 'framer-motion';
import { Shield } from 'lucide-react';

export function PublicHeader() {
  return (
    <motion.header
      initial={{ opacity: 0, y: -20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.6 }}
      className="bv-pattern-bg py-16 md:py-24 px-4"
    >
      <div className="max-w-4xl mx-auto text-center">
        {/* Logo Area */}
        <motion.div
          initial={{ scale: 0.8, opacity: 0 }}
          animate={{ scale: 1, opacity: 1 }}
          transition={{ delay: 0.2, duration: 0.5 }}
          className="flex items-center justify-center gap-3 mb-6"
        >
          <div
            className="w-16 h-16 rounded-2xl flex items-center justify-center shadow-lg"
            style={{ backgroundColor: '#002B5C' }}
          >
            <Shield className="w-9 h-9 text-white" />
          </div>
          {/* Decorative elements */}
          <div className="w-12 h-0.5 bg-[#D4AF37] rounded" />
          <div className="w-6 h-0.5 bg-[#008080] rounded" />
        </motion.div>

        {/* Business Name */}
        <motion.h1
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3, duration: 0.5 }}
          className="text-4xl md:text-6xl font-bold tracking-tight mb-4"
          style={{ color: '#002B5C' }}
        >
          BUSINESSVANCE
        </motion.h1>

        {/* Tagline */}
        <motion.p
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.5, duration: 0.5 }}
          className="text-sm md:text-base tracking-[0.3em] font-semibold mb-6"
          style={{ color: '#002B5C' }}
        >
          INSIGHT. STRATEGY. SUCCESS.
        </motion.p>

        {/* Subtitle */}
        <motion.p
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.7, duration: 0.5 }}
          className="text-base md:text-lg max-w-2xl mx-auto leading-relaxed"
          style={{ color: 'rgba(0, 43, 92, 0.7)' }}
        >
          Professional business reports and advisory services to help you make
          confident, informed decisions.
        </motion.p>

        {/* Decorative line */}
        <motion.div
          initial={{ width: 0 }}
          animate={{ width: 80 }}
          transition={{ delay: 0.9, duration: 0.5 }}
          className="h-1 mx-auto mt-8 rounded-full"
          style={{ backgroundColor: '#D4AF37' }}
        />
      </div>
    </motion.header>
  );
}