/**
 * BusinessVance Platform – Event System
 *
 * Internal pub/sub event bus for loose coupling between modules.
 * Supports both synchronous actions (fire-and-forget) and
 * synchronous filters (transform-and-return).
 *
 * Usage:
 *   // Listen to an action
 *   events.on('businessvance_project_created', (payload) => {
 *     logger.info('New project', { projectId: payload.id });
 *   });
 *
 *   // Use a filter to modify a value
 *   const slug = events.applyFilter('businessvance_filter_slug', rawSlug);
 *
 *   // Dispatch an action
 *   events.dispatch('businessvance_project_created', { id: 'abc', ... });
 *
 * @package BusinessVance\Core
 * @since   2.0.0
 */

import { logger } from './bv-logger';
import type { EventCallback, FilterCallback, EventSubscription } from '@/lib/interfaces';

/* ═══════════════════════════════════════════════════════════════
   Listener Entry
   ═══════════════════════════════════════════════════════════════ */

interface ListenerEntry {
  id: string;
  callback: EventCallback | FilterCallback;
  priority: number;
  once: boolean;
}

let subscriptionCounter = 0;

/* ═══════════════════════════════════════════════════════════════
   Event Bus Implementation
   ═══════════════════════════════════════════════════════════════ */

class BV_EventBus {
  private listeners: Map<string, ListenerEntry[]> = new Map();
  private static instance: BV_EventBus;

  private constructor() {}

  static getInstance(): BV_EventBus {
    if (!BV_EventBus.instance) {
      BV_EventBus.instance = new BV_EventBus();
    }
    return BV_EventBus.instance;
  }

  /* ─── Actions (fire-and-forget) ──────────────────────── */

  /**
   * Register an action listener.
   * @param event  - Event name (use BV_EVENTS constants)
   * @param callback - Function to execute when event fires
   * @param priority - Lower = earlier execution (default 10)
   * @returns Subscription descriptor for later removal
   */
  on<T = unknown>(event: string, callback: EventCallback<T>, priority: number = 10): EventSubscription {
    const id = `sub_${++subscriptionCounter}`;
    const entry: ListenerEntry = { id, callback: callback as EventCallback, priority, once: false };

    if (!this.listeners.has(event)) {
      this.listeners.set(event, []);
    }

    const list = this.listeners.get(event)!;
    list.push(entry);
    // Keep sorted by priority (lower first)
    list.sort((a, b) => a.priority - b.priority);

    return { id, event, priority, once: false };
  }

  /**
   * Register a one-time action listener.
   * Automatically removes itself after first execution.
   */
  once<T = unknown>(event: string, callback: EventCallback<T>, priority: number = 10): EventSubscription {
    const id = `sub_${++subscriptionCounter}`;
    const entry: ListenerEntry = { id, callback: callback as EventCallback, priority, once: true };

    if (!this.listeners.has(event)) {
      this.listeners.set(event, []);
    }

    const list = this.listeners.get(event)!;
    list.push(entry);
    list.sort((a, b) => a.priority - b.priority);

    return { id, event, priority, once: true };
  }

  /**
   * Remove a listener by subscription ID.
   * @returns true if found and removed
   */
  off(subscriptionId: string): boolean {
    for (const [, list] of this.listeners) {
      const index = list.findIndex((e) => e.id === subscriptionId);
      if (index !== -1) {
        list.splice(index, 1);
        return true;
      }
    }
    return false;
  }

  /**
   * Remove all listeners for a specific event.
   */
  offAll(event: string): void {
    this.listeners.delete(event);
  }

  /**
   * Dispatch an action event to all registered listeners.
   * Listeners are executed in priority order.
   */
  dispatch<T = unknown>(event: string, payload?: T): void {
    const list = this.listeners.get(event);
    if (!list || list.length === 0) return;

    logger.debug(`Event dispatched: ${event}`, { listenerCount: list.length }, 'EventBus');

    // Execute a copy of the list (once listeners may modify the original)
    const snapshot = [...list];
    for (const entry of snapshot) {
      try {
        (entry.callback as EventCallback<T>)(payload!);
      } catch (err) {
        logger.error(`Listener error on "${event}" [${entry.id}]`, {
          error: err instanceof Error ? err.message : String(err),
        }, 'EventBus');
      }

      // Remove one-time listeners after execution
      if (entry.once) {
        const idx = list.indexOf(entry);
        if (idx !== -1) list.splice(idx, 1);
      }
    }
  }

  /* ─── Filters (transform-and-return) ─────────────────── */

  /**
   * Apply a filter: passes a value through all registered filter callbacks.
   * Each callback receives the current value and must return the (possibly modified) value.
   * @param event  - Filter name (use BV_EVENTS.FILTER_* constants)
   * @param value  - Initial value to transform
   * @param args   - Extra arguments passed to each callback
   * @returns The final transformed value
   */
  applyFilter<T = unknown>(event: string, value: T, ...args: unknown[]): T {
    const list = this.listeners.get(event);
    if (!list || list.length === 0) return value;

    logger.debug(`Filter applied: ${event}`, { listenerCount: list.length }, 'EventBus');

    let result: T = value;
    const snapshot = [...list];
    for (const entry of snapshot) {
      try {
        result = (entry.callback as FilterCallback<T>)(result, ...args);
      } catch (err) {
        logger.error(`Filter error on "${event}" [${entry.id}]`, {
          error: err instanceof Error ? err.message : String(err),
        }, 'EventBus');
      }
    }
    return result;
  }

  /* ─── Inspection ─────────────────────────────────────── */

  /**
   * Get count of listeners for a specific event.
   */
  listenerCount(event: string): number {
    return this.listeners.get(event)?.length ?? 0;
  }

  /**
   * Get all registered event names.
   */
  registeredEvents(): string[] {
    return Array.from(this.listeners.keys());
  }

  /**
   * Check if a specific event has any listeners.
   */
  hasListeners(event: string): boolean {
    return (this.listeners.get(event)?.length ?? 0) > 0;
  }
}

/* ═══════════════════════════════════════════════════════════════
   Singleton Export
   ═══════════════════════════════════════════════════════════════ */

/** The global event bus instance */
export const events = BV_EventBus.getInstance();
