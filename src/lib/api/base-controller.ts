/**
 * BusinessVance Platform – Base API Controller
 *
 * Abstract base for all API route handlers.
 * Provides standard CRUD pattern, pagination, error handling,
 * and automatic event dispatching.
 *
 * Usage:
 *   class ServiceController extends BaseController {
 *     protected entity = 'service';
 *   }
 *
 * @package BusinessVance\API
 * @since   2.0.0
 */

import { NextRequest, NextResponse } from 'next/server';
import { bvResponse } from '@/lib/core/bv-response';
import { logger } from '@/lib/core/bv-logger';
import { events } from '@/lib/core/bv-events';
import { DEFAULT_PAGE, DEFAULT_PER_PAGE, MAX_PER_PAGE, BV_EVENTS } from '@/lib/core/bv-constants';
import { parseSafeInt } from '@/lib/core/bv-helper';
import { db } from '@/lib/db';
import type { PaginationParams, QueryResult } from '@/lib/interfaces';

/* ═══════════════════════════════════════════════════════════════
   Base Controller
   ═══════════════════════════════════════════════════════════════ */

export abstract class BaseController<T = unknown> {
  /** Human-readable entity name for logging (e.g., 'service', 'project') */
  protected abstract entity: string;

  /** Event prefix for this entity (e.g., 'businessvance_project') */
  protected abstract eventPrefix: string;

  /* ─── Pagination ─────────────────────────────────── */

  /**
   * Extract pagination params from a request URL.
   */
  extractPagination(request: NextRequest): PaginationParams {
    const { searchParams } = new URL(request.url);
    let page = parseSafeInt(searchParams.get('page')) || DEFAULT_PAGE;
    let perPage = parseSafeInt(searchParams.get('perPage') || searchParams.get('per_page')) || DEFAULT_PER_PAGE;

    if (page < 1) page = DEFAULT_PAGE;
    if (perPage < 1) perPage = DEFAULT_PER_PAGE;
    if (perPage > MAX_PER_PAGE) perPage = MAX_PER_PAGE;

    return {
      page,
      perPage,
      sortBy: searchParams.get('sortBy') || undefined,
      sortOrder: (searchParams.get('sortOrder') as 'asc' | 'desc') || undefined,
    };
  }

  /**
   * Build a Prisma-compatible skip/take object from pagination.
   */
  buildPagination(pagination: PaginationParams): { skip: number; take: number } {
    return {
      skip: (pagination.page - 1) * pagination.perPage,
      take: pagination.perPage,
    };
  }

  /**
   * Build a Prisma-compatible orderBy object.
   */
  buildOrderBy(pagination: PaginationParams): Record<string, 'asc' | 'desc'> | undefined {
    if (!pagination.sortBy) return undefined;
    return { [pagination.sortBy]: pagination.sortOrder || 'asc' };
  }

  /* ─── Request Parsing ────────────────────────────── */

  /**
   * Safely parse JSON body from a request.
   * Returns null on parse failure.
   */
  async parseBody(request: NextRequest): Promise<Record<string, unknown> | null> {
    try {
      return await request.json();
    } catch (err) {
      logger.error(`Failed to parse request body for ${this.entity}`, {
        error: err instanceof Error ? err.message : String(err),
      }, 'API');
      return null;
    }
  }

  /**
   * Extract an ID from route params (handles Next.js 16 Promise-based params).
   */
  async extractId(params: Promise<{ id: string }>): Promise<string> {
    const { id } = await params;
    return id;
  }

  /* ─── Event Dispatching ──────────────────────────── */

  /**
   * Dispatch a creation event after a new record is saved.
   */
  dispatchCreated(data: Record<string, unknown>): void {
    const eventName = `${this.eventPrefix}_created` as typeof BV_EVENTS[keyof typeof BV_EVENTS];
    events.dispatch(eventName, data);
    logger.info(`${this.entity} created`, { id: data.id }, 'API');
  }

  /**
   * Dispatch an update event after a record is modified.
   */
  dispatchUpdated(data: Record<string, unknown>): void {
    const eventName = `${this.eventPrefix}_updated` as typeof BV_EVENTS[keyof typeof BV_EVENTS];
    events.dispatch(eventName, data);
    logger.info(`${this.entity} updated`, { id: data.id }, 'API');
  }

  /**
   * Dispatch a deletion event after a record is removed.
   */
  dispatchDeleted(data: Record<string, unknown>): void {
    const eventName = `${this.eventPrefix}_deleted` as typeof BV_EVENTS[keyof typeof BV_EVENTS];
    events.dispatch(eventName, data);
    logger.info(`${this.entity} deleted`, { id: data.id }, 'API');
  }

  /* ─── Error Handling ─────────────────────────────── */

  /**
   * Wrap an async handler with standard error handling.
   * Returns a properly formatted error response on failure.
   */
  async handleErrors(fn: () => Promise<NextResponse>): Promise<NextResponse> {
    try {
      return await fn();
    } catch (error) {
      const message = error instanceof Error ? error.message : `Failed to process ${this.entity} request`;
      logger.error(`Unhandled error in ${this.entity} controller`, {
        error: message,
        stack: error instanceof Error ? error.stack : undefined,
      }, 'API');
      return bvResponse.serverError(message);
    }
  }

  /* ─── Utility: Fetch with Count ──────────────────── */

  /**
   * Execute a paginated findMany with total count.
   * Returns a QueryResult ready for bvResponse.paginated().
   */
  async paginatedFind(
    args: {
      where?: Record<string, unknown>;
      orderBy?: Record<string, 'asc' | 'desc'>;
      pagination: PaginationParams;
      include?: Record<string, unknown>;
      select?: Record<string, unknown>;
    },
    model: { findMany: (...args: unknown[]) => Promise<unknown[]>; count: (...args: unknown[]) => Promise<number> },
  ): Promise<QueryResult<T>> {
    const { where, orderBy, pagination, include, select } = args;
    const { skip, take } = this.buildPagination(pagination);

    const [data, total] = await Promise.all([
      model.findMany({
        where,
        orderBy: orderBy || this.buildOrderBy(pagination),
        skip,
        take,
        ...((include ? { include } : {}) as Record<string, unknown>),
        ...((select ? { select } : {}) as Record<string, unknown>),
      }),
      model.count({ where }),
    ]);

    return {
      data: data as T[],
      total,
      page: pagination.page,
      perPage: pagination.perPage,
      totalPages: Math.ceil(total / pagination.perPage),
    };
  }
}
