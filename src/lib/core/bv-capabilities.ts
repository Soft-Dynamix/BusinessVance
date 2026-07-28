/**
 * BusinessVance Platform – Capability Framework
 *
 * Defines capabilities (permissions) for each role.
 * Provides reusable helpers for access control.
 * Does NOT create WordPress roles — only prepares the logic.
 *
 * Usage:
 *   import { capabilities } from '@/lib/core';
 *   capabilities.can('consultant', 'bv_view_assigned_projects'); // true
 *   capabilities.require('administrator', 'bv_manage_services'); // throws if false
 *
 * @package BusinessVance\Core
 * @since   2.0.0
 */

import { BV_ROLES, type BvRoleType } from './bv-constants';
import type { Capability } from '@/lib/interfaces';
import { BvRole } from '@/lib/interfaces';

/* ═══════════════════════════════════════════════════════════════
   Capability Definitions
   ═══════════════════════════════════════════════════════════════ */

const CAPABILITY_REGISTRY: Capability[] = [
  /* ─── Platform Management (Admin Only) ───────────── */
  {
    name: 'bv_manage_settings',
    description: 'Manage platform settings and configuration',
    roles: [BvRole.ADMINISTRATOR],
  },
  {
    name: 'bv_manage_categories',
    description: 'Create, edit, delete service categories',
    roles: [BvRole.ADMINISTRATOR],
  },
  {
    name: 'bv_manage_icons',
    description: 'Manage the icon registry',
    roles: [BvRole.ADMINISTRATOR],
  },

  /* ─── Service Catalog (Admin Only) ───────────────── */
  {
    name: 'bv_manage_services',
    description: 'Create, edit, delete services',
    roles: [BvRole.ADMINISTRATOR],
  },
  {
    name: 'bv_manage_plans',
    description: 'Create, edit, delete subscription plans',
    roles: [BvRole.ADMINISTRATOR],
  },

  /* ─── Templates (Admin Only) ─────────────────────── */
  {
    name: 'bv_manage_questionnaire_templates',
    description: 'Create, edit, delete questionnaire templates',
    roles: [BvRole.ADMINISTRATOR],
  },
  {
    name: 'bv_manage_agreement_templates',
    description: 'Create, edit, delete agreement templates',
    roles: [BvRole.ADMINISTRATOR],
  },
  {
    name: 'bv_manage_report_templates',
    description: 'Create, edit, delete report templates',
    roles: [BvRole.ADMINISTRATOR],
  },
  {
    name: 'bv_manage_workflow_templates',
    description: 'Create, edit, delete workflow templates',
    roles: [BvRole.ADMINISTRATOR],
  },
  {
    name: 'bv_manage_notification_templates',
    description: 'Create, edit, delete notification templates',
    roles: [BvRole.ADMINISTRATOR],
  },

  /* ─── Projects (Admin + Consultant) ──────────────── */
  {
    name: 'bv_view_all_projects',
    description: 'View all projects in the system',
    roles: [BvRole.ADMINISTRATOR],
  },
  {
    name: 'bv_view_assigned_projects',
    description: 'View projects assigned to the current user',
    roles: [BvRole.ADMINISTRATOR, BvRole.CONSULTANT],
  },
  {
    name: 'bv_create_projects',
    description: 'Manually create projects',
    roles: [BvRole.ADMINISTRATOR],
  },
  {
    name: 'bv_edit_projects',
    description: 'Edit project details',
    roles: [BvRole.ADMINISTRATOR, BvRole.CONSULTANT],
  },
  {
    name: 'bv_delete_projects',
    description: 'Delete projects',
    roles: [BvRole.ADMINISTRATOR],
  },
  {
    name: 'bv_change_project_status',
    description: 'Change project workflow status',
    roles: [BvRole.ADMINISTRATOR, BvRole.CONSULTANT],
  },
  {
    name: 'bv_assign_consultant',
    description: 'Assign a consultant to a project',
    roles: [BvRole.ADMINISTRATOR],
  },

  /* ─── Client Portal (Client) ──────────────────────── */
  {
    name: 'bv_view_own_projects',
    description: 'View own projects in the client portal',
    roles: [BvRole.CLIENT],
  },
  {
    name: 'bv_sign_agreement',
    description: 'Sign a project agreement',
    roles: [BvRole.CLIENT],
  },
  {
    name: 'bv_fill_questionnaire',
    description: 'Fill out a project questionnaire',
    roles: [BvRole.CLIENT],
  },
  {
    name: 'bv_upload_documents',
    description: 'Upload documents to a project',
    roles: [BvRole.CLIENT, BvRole.CONSULTANT],
  },
  {
    name: 'bv_view_reports',
    description: 'View delivered reports',
    roles: [BvRole.CLIENT, BvRole.ADMINISTRATOR],
  },
  {
    name: 'bv_send_messages',
    description: 'Send messages within a project',
    roles: [BvRole.CLIENT, BvRole.CONSULTANT, BvRole.ADMINISTRATOR],
  },

  /* ─── Internal Notes (Admin + Consultant, NOT Client) */
  {
    name: 'bv_view_internal_notes',
    description: 'View internal project notes',
    roles: [BvRole.ADMINISTRATOR, BvRole.CONSULTANT],
  },
  {
    name: 'bv_add_internal_notes',
    description: 'Add internal project notes',
    roles: [BvRole.ADMINISTRATOR, BvRole.CONSULTANT],
  },

  /* ─── Reports (Admin + Consultant) ───────────────── */
  {
    name: 'bv_upload_reports',
    description: 'Upload/create reports for a project',
    roles: [BvRole.ADMINISTRATOR, BvRole.CONSULTANT],
  },
  {
    name: 'bv_deliver_reports',
    description: 'Mark a report as delivered to client',
    roles: [BvRole.ADMINISTRATOR],
  },

  /* ─── Analytics (Admin Only) ──────────────────────── */
  {
    name: 'bv_view_analytics',
    description: 'View platform analytics and reports',
    roles: [BvRole.ADMINISTRATOR],
  },
];

/* ═══════════════════════════════════════════════════════════════
   Capability Manager
   ═══════════════════════════════════════════════════════════════ */

class BV_Capabilities {
  private static instance: BV_Capabilities;
  private registry: Map<string, Capability> = new Map();

  private constructor() {
    // Index the registry for O(1) lookups
    for (const cap of CAPABILITY_REGISTRY) {
      this.registry.set(cap.name, cap);
    }
  }

  static getInstance(): BV_Capabilities {
    if (!BV_Capabilities.instance) {
      BV_Capabilities.instance = new BV_Capabilities();
    }
    return BV_Capabilities.instance;
  }

  /* ─── Query Methods ──────────────────────────────── */

  /**
   * Check if a role has a specific capability.
   */
  can(role: BvRoleType | string, capabilityName: string): boolean {
    const cap = this.registry.get(capabilityName);
    if (!cap) {
      // Unknown capability — deny by default
      return false;
    }
    return cap.roles.includes(role as BvRole);
  }

  /**
   * Check if a role has ALL listed capabilities.
   */
  canAll(role: BvRoleType | string, capabilityNames: string[]): boolean {
    return capabilityNames.every((cap) => this.can(role, cap));
  }

  /**
   * Check if a role has ANY of the listed capabilities.
   */
  canAny(role: BvRoleType | string, capabilityNames: string[]): boolean {
    return capabilityNames.some((cap) => this.can(role, cap));
  }

  /**
   * Assert that a role has a capability. Throws if not.
   */
  require(role: BvRoleType | string, capabilityName: string): void {
    if (!this.can(role, capabilityName)) {
      throw new Error(
        `Authorization failed: role '${role}' does not have capability '${capabilityName}'`,
      );
    }
  }

  /* ─── Registry Inspection ────────────────────────── */

  /**
   * Get all capabilities for a specific role.
   */
  getCapabilitiesForRole(role: BvRoleType | string): Capability[] {
    return CAPABILITY_REGISTRY.filter((cap) =>
      cap.roles.includes(role as BvRole),
    );
  }

  /**
   * Get the full capability registry.
   */
  getAllCapabilities(): Capability[] {
    return [...CAPABILITY_REGISTRY];
  }

  /**
   * Get a single capability definition by name.
   */
  getCapability(name: string): Capability | undefined {
    return this.registry.get(name);
  }

  /**
   * Check if a capability exists in the registry.
   */
  capabilityExists(name: string): boolean {
    return this.registry.has(name);
  }
}

/* ═══════════════════════════════════════════════════════════════
   Singleton Export
   ═══════════════════════════════════════════════════════════════ */

/** The global capabilities instance */
export const capabilities = BV_Capabilities.getInstance();
