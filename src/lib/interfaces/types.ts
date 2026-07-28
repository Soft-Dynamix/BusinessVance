/**
 * BusinessVance Platform – Shared Interfaces & Types
 *
 * Canonical type definitions used across all core modules.
 * No implementation logic — pure contracts only.
 *
 * @package BusinessVance\Core
 * @since   2.0.0
 */

/* ═══════════════════════════════════════════════════════════════
   Event System Types
   ═══════════════════════════════════════════════════════════════ */

/** Callback signature for event listeners */
export type EventCallback<T = unknown> = (payload: T) => void | Promise<void>;

/** Callback signature for filter listeners (must return a value) */
export type FilterCallback<T = unknown> = (value: T, ...args: unknown[]) => T | Promise<T>;

/** Event subscription descriptor */
export interface EventSubscription {
  /** Unique subscription ID */
  id: string;
  /** Event name */
  event: string;
  /** Listener priority (lower = earlier execution) */
  priority: number;
  /** Whether this subscription is one-time only */
  once: boolean;
}

/* ═══════════════════════════════════════════════════════════════
   Logger Types
   ═══════════════════════════════════════════════════════════════ */

export enum LogLevel {
  DEBUG = 0,
  INFO = 1,
  WARNING = 2,
  ERROR = 3,
}

export interface LogEntry {
  level: LogLevel;
  message: string;
  context: string;
  timestamp: Date;
  data?: Record<string, unknown>;
}

export interface LoggerConfig {
  /** Minimum log level to record */
  minLevel: LogLevel;
  /** Whether to output to console */
  consoleOutput: boolean;
  /** Whether to persist to database */
  dbOutput: boolean;
}

/* ═══════════════════════════════════════════════════════════════
   Validation Types
   ═══════════════════════════════════════════════════════════════ */

export interface ValidationResult {
  valid: boolean;
  errors: string[];
}

export interface FieldRule {
  field: string;
  label: string;
  required?: boolean;
  type?: 'string' | 'number' | 'email' | 'phone' | 'url' | 'integer' | 'price' | 'json';
  minLength?: number;
  maxLength?: number;
  min?: number;
  max?: number;
  pattern?: RegExp;
  customValidator?: (value: unknown) => string | null;
}

/* ═══════════════════════════════════════════════════════════════
   Response Types
   ═══════════════════════════════════════════════════════════════ */

export interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  error?: string;
  message?: string;
  meta?: {
    page?: number;
    perPage?: number;
    total?: number;
    totalPages?: number;
  };
}

/* ═══════════════════════════════════════════════════════════════
   Capability / Role Types
   ═══════════════════════════════════════════════════════════════ */

export enum BvRole {
  ADMINISTRATOR = 'administrator',
  CONSULTANT = 'consultant',
  CLIENT = 'client',
}

export interface Capability {
  /** Capability name (e.g., 'bv_manage_services') */
  name: string;
  /** Human-readable description */
  description: string;
  /** Roles that possess this capability */
  roles: BvRole[];
}

/* ═══════════════════════════════════════════════════════════════
   Notification Types
   ═══════════════════════════════════════════════════════════════ */

export enum NotificationChannel {
  EMAIL = 'email',
  DASHBOARD = 'dashboard',
  SMS = 'sms',
  PUSH = 'push',
}

export interface NotificationPayload {
  channel: NotificationChannel;
  recipient: string;
  subject: string;
  body: string;
  projectId?: string;
  data?: Record<string, unknown>;
}

export interface NotificationResult {
  success: boolean;
  channel: NotificationChannel;
  recipient: string;
  error?: string;
  timestamp: Date;
}

/** Interface for notification channel transport implementations */
export interface NotificationTransport {
  channel: NotificationChannel;
  send(payload: NotificationPayload): Promise<NotificationResult>;
}

/* ═══════════════════════════════════════════════════════════════
   Database Versioning Types
   ═══════════════════════════════════════════════════════════════ */

export interface MigrationRecord {
  version: string;
  name: string;
  appliedAt: Date;
  checksum?: string;
}

export interface MigrationDefinition {
  version: string;
  name: string;
  description: string;
  up: () => Promise<void>;
  down?: () => Promise<void>;
}

/* ═══════════════════════════════════════════════════════════════
   API Controller Types
   ═══════════════════════════════════════════════════════════════ */

export interface PaginationParams {
  page?: number;
  perPage?: number;
  sortBy?: string;
  sortOrder?: 'asc' | 'desc';
}

export interface QueryResult<T> {
  data: T[];
  total: number;
  page: number;
  perPage: number;
  totalPages: number;
}
