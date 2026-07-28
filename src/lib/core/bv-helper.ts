/**
 * BusinessVance Platform – Helper Utilities
 *
 * General-purpose helper functions used across the platform.
 * Stateless functions with no side effects where possible.
 *
 * @package BusinessVance\Core
 * @since   2.0.0
 */

import { PROJECT_NUMBER_PREFIX, PROJECT_STATUS_SEQUENCE, type ProjectStatus } from './bv-constants';
import { logger } from './bv-logger';

/* ═══════════════════════════════════════════════════════════════
   String Helpers
   ═══════════════════════════════════════════════════════════════ */

/**
 * Generate a URL-safe slug from a name string.
 * @param name - The input string (e.g., "My Service Name")
 * @returns URL-safe slug (e.g., "my-service-name")
 */
export function generateSlug(name: string): string {
  return name
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/[\s_]+/g, '-')
    .replace(/-+/g, '-')
    .replace(/(^-|-$)/g, '');
}

/**
 * Generate a project number in the format BV-YYYY-NNNNNN.
 * @param lastNumber - The previous project number (or empty string for the first)
 * @returns New project number string
 */
export function generateProjectNumber(lastNumber?: string): string {
  const year = new Date().getFullYear();
  let nextSeq = 1;

  if (lastNumber) {
    const match = lastNumber.match(/BV-\d{4}-(\d+)/);
    if (match) {
      nextSeq = parseInt(match[1], 10) + 1;
    }
  }

  return `${PROJECT_NUMBER_PREFIX}-${year}-${String(nextSeq).padStart(6, '0')}`;
}

/**
 * Sanitize a string for safe storage (trim, collapse whitespace).
 */
export function sanitizeString(value: unknown): string {
  if (value === null || value === undefined) return '';
  return String(value).trim().replace(/\s+/g, ' ');
}

/**
 * Truncate a string to a maximum length with ellipsis.
 */
export function truncate(str: string, maxLength: number): string {
  if (str.length <= maxLength) return str;
  return str.slice(0, maxLength - 3) + '...';
}

/* ═══════════════════════════════════════════════════════════════
   Number Helpers
   ═══════════════════════════════════════════════════════════════ */

/**
 * Safely parse a float, returning 0 on failure.
 */
export function parseSafeFloat(value: unknown): number {
  const num = parseFloat(String(value));
  return isNaN(num) ? 0 : num;
}

/**
 * Safely parse an integer, returning 0 on failure.
 */
export function parseSafeInt(value: unknown): number {
  const num = parseInt(String(value), 10);
  return isNaN(num) ? 0 : num;
}

/**
 * Format a number as currency.
 * @param amount   - The numeric amount
 * @param symbol   - Currency symbol (default 'R')
 * @param position - 'before' | 'after' (default 'before')
 */
export function formatCurrency(amount: number, symbol: string = 'R', position: string = 'before'): string {
  const formatted = amount.toLocaleString('en-ZA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  return position === 'before' ? `${symbol}${formatted}` : `${formatted}${symbol}`;
}

/* ═══════════════════════════════════════════════════════════════
   Date Helpers
   ═══════════════════════════════════════════════════════════════ */

/**
 * Format a date to a human-readable string.
 */
export function formatDate(date: string | Date, format: 'short' | 'long' | 'iso' = 'short'): string {
  const d = typeof date === 'string' ? new Date(date) : date;
  if (isNaN(d.getTime())) return 'Invalid date';

  switch (format) {
    case 'iso':
      return d.toISOString();
    case 'long':
      return d.toLocaleDateString('en-ZA', {
        year: 'numeric', month: 'long', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
      });
    case 'short':
    default:
      return d.toLocaleDateString('en-ZA', {
        year: 'numeric', month: 'short', day: 'numeric',
      });
  }
}

/**
 * Get the relative time string (e.g., "2 hours ago").
 */
export function timeAgo(date: string | Date): string {
  const now = Date.now();
  const then = new Date(date).getTime();
  const diffMs = now - then;
  const diffSec = Math.floor(diffMs / 1000);
  const diffMin = Math.floor(diffSec / 60);
  const diffHr = Math.floor(diffMin / 60);
  const diffDay = Math.floor(diffHr / 24);

  if (diffSec < 60) return 'just now';
  if (diffMin < 60) return `${diffMin} minute${diffMin > 1 ? 's' : ''} ago`;
  if (diffHr < 24) return `${diffHr} hour${diffHr > 1 ? 's' : ''} ago`;
  if (diffDay < 7) return `${diffDay} day${diffDay > 1 ? 's' : ''} ago`;
  return formatDate(date);
}

/* ═══════════════════════════════════════════════════════════════
   Project Status Helpers
   ═══════════════════════════════════════════════════════════════ */

/**
 * Check if a status transition is valid (forward in the lifecycle).
 */
export function isValidStatusTransition(from: string, to: string): boolean {
  const fromIndex = PROJECT_STATUS_SEQUENCE.indexOf(from as ProjectStatus);
  const toIndex = PROJECT_STATUS_SEQUENCE.indexOf(to as ProjectStatus);

  if (fromIndex === -1 || toIndex === -1) return false;

  // Admins can transition anywhere, so we allow all valid statuses
  // The API layer handles whether to allow backward transitions
  return toIndex !== -1;
}

/**
 * Check if 'to' status is forward from 'from' status.
 */
export function isForwardTransition(from: string, to: string): boolean {
  const fromIndex = PROJECT_STATUS_SEQUENCE.indexOf(from as ProjectStatus);
  const toIndex = PROJECT_STATUS_SEQUENCE.indexOf(to as ProjectStatus);

  if (fromIndex === -1 || toIndex === -1) return false;
  return toIndex > fromIndex;
}

/**
 * Get the next status in the sequence.
 */
export function getNextStatus(current: string): ProjectStatus | null {
  const index = PROJECT_STATUS_SEQUENCE.indexOf(current as ProjectStatus);
  if (index === -1 || index >= PROJECT_STATUS_SEQUENCE.length - 1) return null;
  return PROJECT_STATUS_SEQUENCE[index + 1];
}

/* ═══════════════════════════════════════════════════════════════
   Data Helpers
   ═══════════════════════════════════════════════════════════════ */

/**
 * Safely parse JSON, returning the fallback on failure.
 */
export function parseJsonSafe<T = unknown>(json: string, fallback: T): T {
  try {
    return JSON.parse(json) as T;
  } catch {
    return fallback;
  }
}

/**
 * Pick specific keys from an object.
 */
export function pick<T extends Record<string, unknown>, K extends keyof T>(
  obj: T,
  keys: K[],
): Pick<T, K> {
  const result = {} as Pick<T, K>;
  for (const key of keys) {
    if (key in obj) {
      result[key] = obj[key];
    }
  }
  return result;
}

/**
 * Omit specific keys from an object.
 */
export function omit<T extends Record<string, unknown>, K extends keyof T>(
  obj: T,
  keys: K[],
): Omit<T, K> {
  const result = { ...obj };
  for (const key of keys) {
    delete result[key];
  }
  return result as Omit<T, K>;
}

/**
 * Group an array by a key function.
 */
export function groupBy<T>(array: T[], keyFn: (item: T) => string): Record<string, T[]> {
  return array.reduce((acc, item) => {
    const key = keyFn(item);
    if (!acc[key]) acc[key] = [];
    acc[key].push(item);
    return acc;
  }, {} as Record<string, T[]>);
}

/**
 * Remove undefined and null values from an object (for clean DB updates).
 */
export function cleanObject<T extends Record<string, unknown>>(obj: T): Partial<T> {
  const cleaned: Partial<T> = {};
  for (const [key, value] of Object.entries(obj)) {
    if (value !== undefined && value !== null) {
      cleaned[key as keyof T] = value;
    }
  }
  return cleaned;
}

/* ═══════════════════════════════════════════════════════════════
   File Size Helpers
   ═══════════════════════════════════════════════════════════════ */

/**
 * Format a byte count to human-readable form.
 */
export function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  const k = 1024;
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${units[i]}`;
}
