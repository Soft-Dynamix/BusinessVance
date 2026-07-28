/**
 * BusinessVance Platform – Notification Service
 *
 * Service interface for multi-channel notifications.
 * No notifications are actually sent — this is the abstraction layer.
 * Transport implementations are registered and dispatched through this service.
 *
 * Usage:
 *   import { notificationService } from '@/lib/services';
 *
 *   // Register a transport (once, at init)
 *   notificationService.registerTransport(new EmailTransport());
 *
 *   // Queue a notification
 *   await notificationService.notify({
 *     channel: NotificationChannel.EMAIL,
 *     recipient: 'client@example.com',
 *     subject: 'Your project is ready',
 *     body: 'Hello, your report is now available...',
 *     projectId: 'abc123',
 *   });
 *
 * @package BusinessVance\Services
 * @since   2.0.0
 */

import { logger } from '@/lib/core/bv-logger';
import { events } from '@/lib/core/bv-events';
import { BV_EVENTS } from '@/lib/core/bv-constants';
import {
  NotificationChannel,
  type NotificationPayload,
  type NotificationResult,
  type NotificationTransport,
} from '@/lib/interfaces';

/* ═══════════════════════════════════════════════════════════════
   Notification Service Implementation
   ═══════════════════════════════════════════════════════════════ */

class BV_NotificationService {
  private transports: Map<NotificationChannel, NotificationTransport> = new Map();
  private static instance: BV_NotificationService;
  private enabled: boolean = true;

  private constructor() {}

  static getInstance(): BV_NotificationService {
    if (!BV_NotificationService.instance) {
      BV_NotificationService.instance = new BV_NotificationService();
    }
    return BV_NotificationService.instance;
  }

  /* ─── Transport Management ───────────────────────── */

  /**
   * Register a notification transport for a channel.
   */
  registerTransport(transport: NotificationTransport): void {
    this.transports.set(transport.channel, transport);
    logger.info(`Notification transport registered: ${transport.channel}`, {}, 'NotificationService');
  }

  /**
   * Unregister a transport for a channel.
   */
  unregisterTransport(channel: NotificationChannel): void {
    this.transports.delete(channel);
  }

  /**
   * Check if a transport is registered for a channel.
   */
  hasTransport(channel: NotificationChannel): boolean {
    return this.transports.has(channel);
  }

  /**
   * Get all registered transport channels.
   */
  getRegisteredChannels(): NotificationChannel[] {
    return Array.from(this.transports.keys());
  }

  /* ─── Notification Dispatch ──────────────────────── */

  /**
   * Enable or disable the notification service.
   */
  setEnabled(enabled: boolean): void {
    this.enabled = enabled;
  }

  /**
   * Send a notification through the appropriate transport.
   * @returns The result from the transport, or a no-op result if disabled/no transport.
   */
  async notify(payload: NotificationPayload): Promise<NotificationResult> {
    const noTransportResult: NotificationResult = {
      success: false,
      channel: payload.channel,
      recipient: payload.recipient,
      error: 'No transport registered',
      timestamp: new Date(),
    };

    if (!this.enabled) {
      logger.debug(`Notification service disabled, skipping: ${payload.subject}`, {}, 'NotificationService');
      return { ...noTransportResult, error: 'Notification service disabled' };
    }

    const transport = this.transports.get(payload.channel);
    if (!transport) {
      logger.warning(`No transport registered for channel: ${payload.channel}`, {}, 'NotificationService');
      return noTransportResult;
    }

    // Dispatch pre-send event (allows listeners to modify the payload)
    const filteredPayload = events.applyFilter(
      BV_EVENTS.FILTER_NOTIFICATION_PAYLOAD,
      payload,
    );

    logger.info(`Sending notification via ${payload.channel} to ${payload.recipient}`, {
      subject: payload.subject,
      projectId: payload.projectId,
    }, 'NotificationService');

    try {
      const result = await transport.send(filteredPayload);

      // Dispatch post-send event
      events.dispatch(BV_EVENTS.NOTIFICATION_SENT, result);
      events.dispatch(BV_EVENTS.NOTIFICATION_DISPATCHED, result);

      return result;
    } catch (err) {
      const errorMsg = err instanceof Error ? err.message : String(err);
      logger.error(`Notification failed via ${payload.channel}`, {
        recipient: payload.recipient,
        error: errorMsg,
      }, 'NotificationService');

      return {
        success: false,
        channel: payload.channel,
        recipient: payload.recipient,
        error: errorMsg,
        timestamp: new Date(),
      };
    }
  }

  /**
   * Send the same notification through multiple channels.
   * @returns Array of results, one per channel.
   */
  async notifyMulti(channels: NotificationChannel[], payload: Omit<NotificationPayload, 'channel'>): Promise<NotificationResult[]> {
    const results = await Promise.all(
      channels.map((channel) =>
        this.notify({ ...payload, channel }),
      ),
    );
    return results;
  }
}

/* ═══════════════════════════════════════════════════════════════
   Singleton Export
   ═══════════════════════════════════════════════════════════════ */

/** The global notification service instance */
export const notificationService = BV_NotificationService.getInstance();
