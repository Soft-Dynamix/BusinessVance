/**
 * BusinessVance Platform – Response Helper
 *
 * Standardised API response builders for consistent output
 * across all endpoints. Works with Next.js NextResponse.
 *
 * Usage:
 *   import { bvResponse } from '@/lib/core';
 *   return bvResponse.success({ services }, 200);
 *   return bvResponse.error('Not found', 404);
 *   return bvResponse.paginated(services, 1, 20, 150);
 *
 * @package BusinessVance\Core
 * @since   2.0.0
 */

import { NextResponse } from 'next/server';
import type { ApiResponse, QueryResult } from '@/lib/interfaces';
import { logger } from './bv-logger';

/* ═══════════════════════════════════════════════════════════════
   Response Builder
   ═══════════════════════════════════════════════════════════════ */

class BV_Response {
  private static instance: BV_Response;

  private constructor() {}

  static getInstance(): BV_Response {
    if (!BV_Response.instance) {
      BV_Response.instance = new BV_Response();
    }
    return BV_Response.instance;
  }

  /* ─── Success Responses ────────────────────────────── */

  /**
   * Return a successful response with data.
   * @param data   - Response data
   * @param status - HTTP status code (default 200)
   */
  success<T>(data: T, status: number = 200): NextResponse<ApiResponse<T>> {
    return NextResponse.json(
      { success: true, data } as ApiResponse<T>,
      { status },
    );
  }

  /**
   * Return a success response with a message.
   */
  successMessage(message: string, status: number = 200): NextResponse<ApiResponse> {
    return NextResponse.json(
      { success: true, message },
      { status },
    );
  }

  /**
   * Return a success response with data and a message.
   */
  successWithData<T>(data: T, message: string, status: number = 200): NextResponse<ApiResponse<T>> {
    return NextResponse.json(
      { success: true, data, message } as ApiResponse<T>,
      { status },
    );
  }

  /**
   * Return a paginated response.
   */
  paginated<T>(
    data: T[],
    page: number,
    perPage: number,
    total: number,
  ): NextResponse<ApiResponse<T[]>> {
    const totalPages = Math.ceil(total / perPage);
    return NextResponse.json({
      success: true,
      data,
      meta: { page, perPage, total, totalPages },
    } as ApiResponse<T[]>);
  }

  /**
   * Build a QueryResult from an array and total count.
   */
  buildQueryResult<T>(data: T[], total: number, page: number, perPage: number): QueryResult<T> {
    return {
      data,
      total,
      page,
      perPage,
      totalPages: Math.ceil(total / perPage),
    };
  }

  /* ─── Error Responses ──────────────────────────────── */

  /**
   * Return an error response.
   * @param message - Human-readable error message
   * @param status  - HTTP status code (default 400)
   * @param data    - Optional extra error data
   */
  error(message: string, status: number = 400, data?: Record<string, unknown>): NextResponse<ApiResponse> {
    // Log errors at appropriate levels
    if (status >= 500) {
      logger.error(message, data, 'API');
    } else if (status >= 400) {
      logger.warning(message, data, 'API');
    }

    return NextResponse.json(
      { success: false, error: message },
      { status },
    );
  }

  /**
   * Return a 400 Bad Request error.
   */
  badRequest(message: string = 'Bad request'): NextResponse<ApiResponse> {
    return this.error(message, 400);
  }

  /**
   * Return a 401 Unauthorized error.
   */
  unauthorized(message: string = 'Authentication required'): NextResponse<ApiResponse> {
    return this.error(message, 401);
  }

  /**
   * Return a 403 Forbidden error.
   */
  forbidden(message: string = 'Insufficient permissions'): NextResponse<ApiResponse> {
    return this.error(message, 403);
  }

  /**
   * Return a 404 Not Found error.
   */
  notFound(message: string = 'Resource not found'): NextResponse<ApiResponse> {
    return this.error(message, 404);
  }

  /**
   * Return a 500 Internal Server Error.
   */
  serverError(message: string = 'Internal server error', data?: Record<string, unknown>): NextResponse<ApiResponse> {
    return this.error(message, 500, data);
  }

  /* ─── Validation Error ─────────────────────────────── */

  /**
   * Return a validation error with field-level messages.
   * @param errors - Array of error messages
   */
  validationError(errors: string[]): NextResponse<ApiResponse> {
    return NextResponse.json(
      {
        success: false,
        error: 'Validation failed',
        data: { errors },
      } as ApiResponse,
      { status: 422 },
    );
  }
}

/* ═══════════════════════════════════════════════════════════════
   Singleton Export
   ═══════════════════════════════════════════════════════════════ */

/** The global response helper instance */
export const bvResponse = BV_Response.getInstance();
