/**
 * BusinessVance Platform – Constants
 *
 * Single source of truth for all platform-wide constants.
 * Import from here instead of duplicating values.
 *
 * @package BusinessVance\Core
 * @since   2.0.0
 */

/* ═══════════════════════════════════════════════════════════════
   Platform Identity
   ═══════════════════════════════════════════════════════════════ */

export const BV_PLATFORM_NAME = 'BusinessVance';
export const BV_PLATFORM_VERSION = '2.0.0';
export const BV_API_NAMESPACE = 'businessvance/v1';
export const BV_DB_VERSION = '2.0.0';

/* ═══════════════════════════════════════════════════════════════
   Project Statuses
   Refines the 9-state model to 10 states per architecture blueprint.
   ═══════════════════════════════════════════════════════════════ */

export const PROJECT_STATUSES = {
  ORDER_RECEIVED: 'order-received',
  PROJECT_CREATED: 'project-created',
  AWAITING_AGREEMENT: 'awaiting-agreement',
  AWAITING_QUESTIONNAIRE: 'awaiting-questionnaire',
  AWAITING_DOCUMENTS: 'awaiting-documents',
  INFORMATION_REVIEW: 'information-review',
  IN_PROGRESS: 'in-progress',
  QUALITY_CHECK: 'quality-check',
  COMPLETED: 'completed',
  DELIVERED: 'delivered',
  ARCHIVED: 'archived',
} as const;

/** All valid project status values */
export type ProjectStatus = (typeof PROJECT_STATUSES)[keyof typeof PROJECT_STATUSES];

/** Status display labels for the admin UI */
export const PROJECT_STATUS_LABELS: Record<ProjectStatus, string> = {
  'order-received': 'Order Received',
  'project-created': 'Project Created',
  'awaiting-agreement': 'Awaiting Agreement',
  'awaiting-questionnaire': 'Awaiting Questionnaire',
  'awaiting-documents': 'Awaiting Documents',
  'information-review': 'Information Review',
  'in-progress': 'In Progress',
  'quality-check': 'Quality Check',
  'completed': 'Completed',
  'delivered': 'Delivered',
  'archived': 'Archived',
};

/** Default progress percentage for each status */
export const PROJECT_STATUS_PROGRESS: Record<ProjectStatus, number> = {
  'order-received': 0,
  'project-created': 5,
  'awaiting-agreement': 10,
  'awaiting-questionnaire': 25,
  'awaiting-documents': 50,
  'information-review': 60,
  'in-progress': 75,
  'quality-check': 90,
  'completed': 100,
  'delivered': 100,
  'archived': 100,
};

/** Ordered list of statuses in lifecycle sequence */
export const PROJECT_STATUS_SEQUENCE: ProjectStatus[] = [
  'order-received',
  'project-created',
  'awaiting-agreement',
  'awaiting-questionnaire',
  'awaiting-documents',
  'information-review',
  'in-progress',
  'quality-check',
  'completed',
  'delivered',
  'archived',
];

/* ═══════════════════════════════════════════════════════════════
   Template Statuses
   ═══════════════════════════════════════════════════════════════ */

export const TEMPLATE_STATUSES = {
  DRAFT: 'draft',
  PUBLISHED: 'published',
  ARCHIVED: 'archived',
} as const;

export type TemplateStatus = (typeof TEMPLATE_STATUSES)[keyof typeof TEMPLATE_STATUSES];

/* ═══════════════════════════════════════════════════════════════
   Questionnaire Question Types
   ═══════════════════════════════════════════════════════════════ */

export const QUESTION_TYPES = [
  'text', 'textarea', 'select', 'multiselect', 'number',
  'email', 'phone', 'date', 'file', 'radio', 'checkbox',
  'heading', 'paragraph',
] as const;

export type QuestionType = (typeof QUESTION_TYPES)[number];

/* ═══════════════════════════════════════════════════════════════
   Service Button Types
   ═══════════════════════════════════════════════════════════════ */

export const BUTTON_TYPES = [
  'cart', 'quote', 'booking', 'link',
] as const;

export type ButtonType = (typeof BUTTON_TYPES)[number];

/* ═══════════════════════════════════════════════════════════════
   Document Categories
   ═══════════════════════════════════════════════════════════════ */

export const DOCUMENT_CATEGORIES = [
  'company-registration', 'id', 'financial', 'logo', 'branding', 'report', 'other',
] as const;

export type DocumentCategory = (typeof DOCUMENT_CATEGORIES)[number];

/* ═══════════════════════════════════════════════════════════════
   Activity Log Actions
   ═══════════════════════════════════════════════════════════════ */

export const ACTIVITY_ACTIONS = [
  'created', 'updated', 'deleted', 'signed', 'uploaded', 'delivered',
  'status_changed', 'message_sent', 'note_added', 'assigned',
] as const;

export type ActivityAction = (typeof ACTIVITY_ACTIONS)[number];

export const ACTIVITY_ENTITY_TYPES = [
  'project', 'service', 'questionnaire', 'agreement', 'document',
  'report', 'message', 'note', 'template', 'setting',
] as const;

export type ActivityEntityType = (typeof ACTIVITY_ENTITY_TYPES)[number];

/* ═══════════════════════════════════════════════════════════════
   Report Statuses
   ═══════════════════════════════════════════════════════════════ */

export const REPORT_STATUSES = {
  DRAFT: 'draft',
  READY: 'ready',
  DELIVERED: 'delivered',
} as const;

export type ReportStatus = (typeof REPORT_STATUSES)[keyof typeof REPORT_STATUSES];

/* ═══════════════════════════════════════════════════════════════
   Event Names
   All internal actions and filters used by the platform.
   ═══════════════════════════════════════════════════════════════ */

export const BV_EVENTS = {
  // Platform lifecycle
  INITIALIZED: 'businessvance_initialized',

  // Project events
  PROJECT_CREATED: 'businessvance_project_created',
  PROJECT_UPDATED: 'businessvance_project_updated',
  PROJECT_DELETED: 'businessvance_project_deleted',
  PROJECT_STATUS_CHANGED: 'businessvance_project_status_changed',

  // Questionnaire events
  QUESTIONNAIRE_COMPLETED: 'businessvance_questionnaire_completed',
  QUESTIONNAIRE_ASSIGNED: 'businessvance_questionnaire_assigned',

  // Agreement events
  AGREEMENT_SIGNED: 'businessvance_agreement_signed',
  AGREEMENT_ASSIGNED: 'businessvance_agreement_assigned',

  // Document events
  DOCUMENT_UPLOADED: 'businessvance_document_uploaded',
  DOCUMENT_DELETED: 'businessvance_document_deleted',

  // Report events
  REPORT_UPLOADED: 'businessvance_report_uploaded',
  REPORT_DELIVERED: 'businessvance_report_delivered',

  // Message events
  MESSAGE_SENT: 'businessvance_message_sent',

  // Note events
  NOTE_ADDED: 'businessvance_note_added',

  // Notification events
  NOTIFICATION_SENT: 'businessvance_notification_sent',
  NOTIFICATION_DISPATCHED: 'businessvance_notification_dispatched',

  // Template events
  TEMPLATE_CREATED: 'businessvance_template_created',
  TEMPLATE_UPDATED: 'businessvance_template_updated',

  // Filter hooks (return values are used)
  FILTER_PROJECT_NUMBER: 'businessvance_filter_project_number',
  FILTER_SLUG: 'businessvance_filter_slug',
  FILTER_API_RESPONSE: 'businessvance_filter_api_response',
  FILTER_NOTIFICATION_PAYLOAD: 'businessvance_filter_notification_payload',
} as const;

/** Union type of all action event names */
export type BvActionEvent = typeof BV_EVENTS[keyof typeof BV_EVENTS];

/* ═══════════════════════════════════════════════════════════════
   Pagination
   ═══════════════════════════════════════════════════════════════ */

export const DEFAULT_PAGE = 1;
export const DEFAULT_PER_PAGE = 20;
export const MAX_PER_PAGE = 100;

/* ═══════════════════════════════════════════════════════════════
   Project Number Format
   ═══════════════════════════════════════════════════════════════ */

export const PROJECT_NUMBER_PREFIX = 'BV';
export const PROJECT_NUMBER_PATTERN = /^BV-\d{4}-\d{6}$/;

/* ═══════════════════════════════════════════════════════════════
   Roles
   ═══════════════════════════════════════════════════════════════ */

export const BV_ROLES = {
  ADMINISTRATOR: 'administrator',
  CONSULTANT: 'consultant',
  CLIENT: 'client',
} as const;

export type BvRoleType = (typeof BV_ROLES)[keyof typeof BV_ROLES];
