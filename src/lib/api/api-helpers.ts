/**
 * BusinessVance Platform – API Helpers
 *
 * Utility functions for API route handlers.
 * Provides request parsing, parameter extraction, and middleware helpers.
 *
 * @package BusinessVance\API
 * @since   2.0.0
 */

import { NextRequest, NextResponse } from 'next/server';
import { bvResponse } from '@/lib/core/bv-response';
import { capabilities } from '@/lib/core/bv-capabilities';
import { logger } from '@/lib/core/bv-logger';
import { BV_API_NAMESPACE, BV_ROLES, type BvRoleType } from '@/lib/core/bv-constants';

/* ═══════════════════════════════════════════════════════════════
   API Namespace
   ═══════════════════════════════════════════════════════════════ */

/**
 * Get the BusinessVance REST API namespace.
 * All future WP REST API endpoints should be prefixed with this.
 */
export function getApiNamespace(): string {
  return BV_API_NAMESPACE;
}

/**
 * Build a full API path.
 * @example getApiPath('services') → '/wp-json/businessvance/v1/services'
 */
export function getApiPath(resource: string, id?: string): string {
  const base = `/${getApiNamespace()}/${resource}`;
  return id ? `${base}/${id}` : base;
}

/* ═══════════════════════════════════════════════════════════════
   Request Parameter Extraction
   ═══════════════════════════════════════════════════════════════ */

/**
 * Extract search parameters from a NextRequest as a typed object.
 */
export function getSearchParams(request: NextRequest): URLSearchParams {
  return new URL(request.url).searchParams;
}

/**
 * Get a string search parameter, or the default value.
 */
export function getStringParam(request: NextRequest, key: string, defaultValue: string = ''): string {
  return new URL(request.url).searchParams.get(key) || defaultValue;
}

/**
 * Get a boolean search parameter.
 */
export function getBoolParam(request: NextRequest, key: string, defaultValue: boolean = false): boolean {
  const val = new URL(request.url).searchParams.get(key);
  if (val === null) return defaultValue;
  return val === 'true' || val === '1';
}

/**
 * Get a numeric search parameter, or the default value.
 */
export function getNumericParam(request: NextRequest, key: string, defaultValue: number = 0): number {
  const val = new URL(request.url).searchParams.get(key);
  if (val === null) return defaultValue;
  const num = parseInt(val, 10);
  return isNaN(num) ? defaultValue : num;
}

/* ═══════════════════════════════════════════════════════════════
   Permission Callbacks
   ═══════════════════════════════════════════════════════════════ */

/**
 * Create a permission callback for Next.js middleware or route handlers.
 *
 * @param requiredCapability - The capability name to check
 * @param roleExtractor - Function to extract the current user's role from the request
 * @returns A function that returns null (allowed) or an error response (denied)
 *
 * @example
 * // In a route handler:
 * const permResult = checkPermission(request, 'bv_manage_services', (req) => 'administrator');
 * if (permResult) return permResult;
 */
export function checkPermission(
  request: NextRequest,
  requiredCapability: string,
  roleExtractor: (request: NextRequest) => BvRoleType | string | null,
): NextResponse | null {
  const role = roleExtractor(request);

  if (!role) {
    logger.warning(`Unauthenticated access attempt to ${requiredCapability}`, {
      path: request.url,
    }, 'API');
    return bvResponse.unauthorized();
  }

  if (!capabilities.can(role, requiredCapability)) {
    logger.warning(`Unauthorized access attempt: role=${role} capability=${requiredCapability}`, {
      path: request.url,
    }, 'API');
    return bvResponse.forbidden();
  }

  return null;
}

/**
 * Admin-only permission check. Returns null if the request has admin role.
 */
export function requireAdmin(
  request: NextRequest,
  roleExtractor: (request: NextRequest) => BvRoleType | string | null,
): NextResponse | null {
  return checkPermission(request, 'bv_manage_settings', roleExtractor);
}

/* ═══════════════════════════════════════════════════════════════
   CORS & Headers
   ═══════════════════════════════════════════════════════════════ */

/**
 * Standard response headers for BusinessVance API endpoints.
 */
export function getApiHeaders(): Record<string, string> {
  return {
    'X-BV-Namespace': BV_API_NAMESPACE,
    'X-BV-Version': '2.0.0',
  };
}
