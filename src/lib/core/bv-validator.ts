/**
 * BusinessVance Platform – Validator
 *
 * Reusable validation methods for all modules.
 * Every field check returns a string (error message) or null (valid).
 *
 * Usage:
 *   const error = validator.validateEmail(input);
 *   if (error) { /* invalid *\/ }
 *
 *   const result = validator.validateFields(data, rules);
 *   if (!result.valid) { /* result.errors has messages *\/ }
 *
 * @package BusinessVance\Core
 * @since   2.0.0
 */

import type { ValidationResult, FieldRule } from '@/lib/interfaces';

/* ═══════════════════════════════════════════════════════════════
   Regex Patterns
   ═══════════════════════════════════════════════════════════════ */

const PATTERNS = {
  /** Basic email validation */
  email: /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/,

  /** South African phone: 10 digits, optional leading +27 or 0 */
  phone: /^(\+27|27|0)[0-9]{9}$/,

  /** Generic international phone: 7-15 digits with optional + */
  phoneInternational: /^\+?[1-9][0-9]{6,14}$/,

  /** URL with protocol */
  url: /^https?:\/\/[a-zA-Z0-9][a-zA-Z0-9.\-]*\.[a-zA-Z]{2,}(\/\S*)?$/,

  /** WooCommerce product ID: numeric */
  wooCommerceProductId: /^\d+$/,

  /** Project number: BV-YYYY-NNNNNN */
  projectNumber: /^BV-\d{4}-\d{6}$/,

  /** Slug: lowercase letters, numbers, hyphens */
  slug: /^[a-z0-9]+(?:-[a-z0-9]+)*$/,
} as const;

/* ═══════════════════════════════════════════════════════════════
   Validator Implementation
   ═══════════════════════════════════════════════════════════════ */

class BV_Validator {
  private static instance: BV_Validator;

  private constructor() {}

  static getInstance(): BV_Validator {
    if (!BV_Validator.instance) {
      BV_Validator.instance = new BV_Validator();
    }
    return BV_Validator.instance;
  }

  /* ─── Single-Field Validators ─────────────────────── */

  /** Validate an email address. Returns error string or null. */
  validateEmail(value: unknown): string | null {
    if (value === null || value === undefined || value === '') return 'Email is required';
    const str = String(value).trim();
    if (!str) return 'Email is required';
    if (!PATTERNS.email.test(str)) return 'Invalid email format';
    return null;
  }

  /** Validate a phone number (South African format preferred). Returns error string or null. */
  validatePhone(value: unknown): string | null {
    if (value === null || value === undefined || value === '') return 'Phone number is required';
    const str = String(value).trim().replace(/[\s\-()]/g, '');
    if (!str) return 'Phone number is required';
    if (PATTERNS.phone.test(str)) return null;
    if (PATTERNS.phoneInternational.test(str)) return null;
    return 'Invalid phone number format';
  }

  /** Validate a URL. Returns error string or null. */
  validateUrl(value: unknown): string | null {
    if (value === null || value === undefined || value === '') return 'URL is required';
    const str = String(value).trim();
    if (!str) return 'URL is required';
    if (!PATTERNS.url.test(str)) return 'Invalid URL format (must include http:// or https://)';
    return null;
  }

  /** Validate an integer value. Returns error string or null. */
  validateInteger(value: unknown, min?: number, max?: number): string | null {
    if (value === null || value === undefined || value === '') return 'Value is required';
    const num = Number(value);
    if (isNaN(num) || !Number.isInteger(num)) return 'Must be a whole number';
    if (min !== undefined && num < min) return `Must be at least ${min}`;
    if (max !== undefined && num > max) return `Must be at most ${max}`;
    return null;
  }

  /** Validate a price value (non-negative float). Returns error string or null. */
  validatePrice(value: unknown): string | null {
    if (value === null || value === undefined || value === '') return 'Price is required';
    const num = parseFloat(String(value));
    if (isNaN(num)) return 'Price must be a valid number';
    if (num < 0) return 'Price cannot be negative';
    return null;
  }

  /** Validate a required field (non-empty). Returns error string or null. */
  validateRequired(value: unknown, label: string): string | null {
    if (value === null || value === undefined) return `${label} is required`;
    if (typeof value === 'string' && value.trim() === '') return `${label} is required`;
    if (Array.isArray(value) && value.length === 0) return `${label} is required`;
    return null;
  }

  /** Validate a WooCommerce product ID (numeric string). Returns error string or null. */
  validateWooCommerceProductId(value: unknown): string | null {
    if (value === null || value === undefined || value === '') return null; // optional field
    const str = String(value).trim();
    if (!PATTERNS.wooCommerceProductId.test(str)) return 'WooCommerce Product ID must be a positive number';
    return null;
  }

  /** Validate a slug format. Returns error string or null. */
  validateSlug(value: unknown): string | null {
    if (value === null || value === undefined || value === '') return 'Slug is required';
    const str = String(value).trim();
    if (!PATTERNS.slug.test(str)) return 'Invalid slug format (use lowercase letters, numbers, hyphens only)';
    return null;
  }

  /** Validate a project number format. Returns error string or null. */
  validateProjectNumber(value: unknown): string | null {
    if (value === null || value === undefined || value === '') return 'Project number is required';
    const str = String(value).trim();
    if (!PATTERNS.projectNumber.test(str)) return 'Invalid project number format (expected BV-YYYY-NNNNNN)';
    return null;
  }

  /* ─── Multi-Field Validation ───────────────────────── */

  /**
   * Validate multiple fields against defined rules.
   * @param data  - Object containing field values
   * @param rules - Array of field rules to validate against
   * @returns ValidationResult with valid flag and error messages
   */
  validateFields(data: Record<string, unknown>, rules: FieldRule[]): ValidationResult {
    const errors: string[] = [];

    for (const rule of rules) {
      const value = data[rule.field];

      // Required check
      if (rule.required) {
        const requiredError = this.validateRequired(value, rule.label);
        if (requiredError) {
          errors.push(requiredError);
          continue; // Don't run type-specific checks on empty required fields
        }
      }

      // Skip type checks if value is empty and not required
      if (value === null || value === undefined || value === '') continue;

      // Type-specific validation
      switch (rule.type) {
        case 'email': {
          const err = this.validateEmail(value);
          if (err) errors.push(`${rule.label}: ${err}`);
          break;
        }
        case 'phone': {
          const err = this.validatePhone(value);
          if (err) errors.push(`${rule.label}: ${err}`);
          break;
        }
        case 'url': {
          const err = this.validateUrl(value);
          if (err) errors.push(`${rule.label}: ${err}`);
          break;
        }
        case 'integer': {
          const err = this.validateInteger(value, rule.min, rule.max);
          if (err) errors.push(`${rule.label}: ${err}`);
          break;
        }
        case 'price': {
          const err = this.validatePrice(value);
          if (err) errors.push(`${rule.label}: ${err}`);
          break;
        }
        case 'number': {
          const num = Number(value);
          if (isNaN(num)) {
            errors.push(`${rule.label}: Must be a valid number`);
          } else if (rule.min !== undefined && num < rule.min) {
            errors.push(`${rule.label}: Must be at least ${rule.min}`);
          } else if (rule.max !== undefined && num > rule.max) {
            errors.push(`${rule.label}: Must be at most ${rule.max}`);
          }
          break;
        }
        case 'json': {
          if (typeof value === 'string') {
            try {
              JSON.parse(value);
            } catch {
              errors.push(`${rule.label}: Invalid JSON format`);
            }
          }
          break;
        }
        default:
          // string type – apply length checks
          break;
      }

      // String length checks
      if (typeof value === 'string') {
        if (rule.minLength !== undefined && value.length < rule.minLength) {
          errors.push(`${rule.label}: Must be at least ${rule.minLength} characters`);
        }
        if (rule.maxLength !== undefined && value.length > rule.maxLength) {
          errors.push(`${rule.label}: Must be no more than ${rule.maxLength} characters`);
        }
      }

      // Custom pattern
      if (rule.pattern && typeof value === 'string') {
        if (!rule.pattern.test(value)) {
          errors.push(`${rule.label}: Format does not match required pattern`);
        }
      }

      // Custom validator function
      if (rule.customValidator) {
        const customError = rule.customValidator(value);
        if (customError) {
          errors.push(`${rule.label}: ${customError}`);
        }
      }
    }

    return { valid: errors.length === 0, errors };
  }

  /* ─── JSON Validation ──────────────────────────────── */

  /**
   * Safely parse and validate a JSON string.
   * @returns Parsed value or null on failure
   */
  parseJsonSafe<T = unknown>(json: string): { value: T | null; error: string | null } {
    try {
      const parsed = JSON.parse(json) as T;
      return { value: parsed, error: null };
    } catch (err) {
      return { value: null, error: err instanceof Error ? err.message : 'Invalid JSON' };
    }
  }
}

/* ═══════════════════════════════════════════════════════════════
   Singleton Export
   ═══════════════════════════════════════════════════════════════ */

/** The global validator instance */
export const validator = BV_Validator.getInstance();