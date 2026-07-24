/**
 * BusinessVance Platform – Core Module
 *
 * Single entry point for all core functionality.
 * Import from '@/lib/core' instead of individual files.
 *
 * @package BusinessVance\Core
 * @since   2.0.0
 */

// Constants
export * from './bv-constants';

// Logger
export { logger } from './bv-logger';

// Events
export { events } from './bv-events';

// Validator
export { validator } from './bv-validator';

// Response Helper
export { bvResponse } from './bv-response';

// Capabilities
export { capabilities } from './bv-capabilities';

// Helpers (named exports)
export {
  generateSlug,
  generateProjectNumber,
  sanitizeString,
  truncate,
  parseSafeFloat,
  parseSafeInt,
  formatCurrency,
  formatDate,
  timeAgo,
  isValidStatusTransition,
  isForwardTransition,
  getNextStatus,
  parseJsonSafe,
  pick,
  omit,
  groupBy,
  cleanObject,
  formatFileSize,
} from './bv-helper';
