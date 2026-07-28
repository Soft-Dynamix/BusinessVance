/**
 * BusinessVance Platform – Logger
 *
 * Centralised logging service for the entire platform.
 * Supports console and database output, configurable log levels.
 *
 * Usage:
 *   import { logger } from '@/lib/core';
 *   logger.info('Service created', { serviceId: 'abc' }, 'ServiceModule');
 *
 * @package BusinessVance\Core
 * @since   2.0.0
 */

import { LogLevel, type LogEntry, type LoggerConfig } from '@/lib/interfaces';

/* ═══════════════════════════════════════════════════════════════
   Level Labels & Console Styles
   ═══════════════════════════════════════════════════════════════ */

const LEVEL_LABELS: Record<LogLevel, string> = {
  [LogLevel.DEBUG]: 'DEBUG',
  [LogLevel.INFO]: 'INFO',
  [LogLevel.WARNING]: 'WARN',
  [LogLevel.ERROR]: 'ERROR',
};

const CONSOLE_STYLES: Record<LogLevel, string> = {
  [LogLevel.DEBUG]: 'color: #888',
  [LogLevel.INFO]: 'color: #2A9D8F; font-weight: bold',
  [LogLevel.WARNING]: 'color: #F4A261; font-weight: bold',
  [LogLevel.ERROR]: 'color: #E63946; font-weight: bold',
};

/* ═══════════════════════════════════════════════════════════════
   Logger Implementation
   ═══════════════════════════════════════════════════════════════ */

class BV_Logger {
  private config: LoggerConfig;
  private buffer: LogEntry[] = [];
  private static instance: BV_Logger;

  private constructor(config?: Partial<LoggerConfig>) {
    this.config = {
      minLevel: config?.minLevel ?? (process.env.NODE_ENV === 'production' ? LogLevel.INFO : LogLevel.DEBUG),
      consoleOutput: config?.consoleOutput ?? true,
      dbOutput: config?.dbOutput ?? false,
    };
  }

  /** Get the singleton logger instance */
  static getInstance(): BV_Logger {
    if (!BV_Logger.instance) {
      BV_Logger.instance = new BV_Logger();
    }
    return BV_Logger.instance;
  }

  /** Reconfigure the logger (e.g., for testing) */
  configure(config: Partial<LoggerConfig>): void {
    this.config = { ...this.config, ...config };
  }

  /** Get current configuration (read-only) */
  getConfig(): Readonly<LoggerConfig> {
    return { ...this.config };
  }

  /* ─── Public API ───────────────────────────────────────── */

  /** Log a DEBUG message */
  debug(message: string, data?: Record<string, unknown>, context?: string): void {
    this.log(LogLevel.DEBUG, message, context ?? 'General', data);
  }

  /** Log an INFO message */
  info(message: string, data?: Record<string, unknown>, context?: string): void {
    this.log(LogLevel.INFO, message, context ?? 'General', data);
  }

  /** Log a WARNING message */
  warning(message: string, data?: Record<string, unknown>, context?: string): void {
    this.log(LogLevel.WARNING, message, context ?? 'General', data);
  }

  /** Log an ERROR message */
  error(message: string, data?: Record<string, unknown>, context?: string): void {
    this.log(LogLevel.ERROR, message, context ?? 'General', data);
  }

  /** Create a child logger with a fixed context */
  withContext(context: string): BV_ContextLogger {
    return new BV_ContextLogger(this, context);
  }

  /** Get buffered entries (for testing / inspection) */
  getBuffer(): ReadonlyArray<LogEntry> {
    return [...this.buffer];
  }

  /** Clear the buffer */
  clearBuffer(): void {
    this.buffer = [];
  }

  /* ─── Internal ─────────────────────────────────────────── */

  private log(level: LogLevel, message: string, context: string, data?: Record<string, unknown>): void {
    if (level < this.config.minLevel) return;

    const entry: LogEntry = {
      level,
      message,
      context,
      timestamp: new Date(),
      data,
    };

    // Buffer for programmatic access
    this.buffer.push(entry);
    if (this.buffer.length > 1000) {
      this.buffer.shift();
    }

    // Console output
    if (this.config.consoleOutput) {
      this.writeToConsole(entry);
    }
  }

  private writeToConsole(entry: LogEntry): void {
    const label = LEVEL_LABELS[entry.level];
    const style = CONSOLE_STYLES[entry.level];
    const timestamp = entry.timestamp.toISOString();
    const prefix = `[${timestamp}] [${label}] [${entry.context}]`;

    switch (entry.level) {
      case LogLevel.DEBUG:
        console.debug(`%c${prefix} ${entry.message}`, style, entry.data ?? '');
        break;
      case LogLevel.INFO:
        console.info(`%c${prefix} ${entry.message}`, style, entry.data ?? '');
        break;
      case LogLevel.WARNING:
        console.warn(`%c${prefix} ${entry.message}`, style, entry.data ?? '');
        break;
      case LogLevel.ERROR:
        console.error(`%c${prefix} ${entry.message}`, style, entry.data ?? '');
        break;
    }
  }
}

/* ═══════════════════════════════════════════════════════════════
   Context Logger
   A pre-configured logger with a fixed context string.
   ═══════════════════════════════════════════════════════════════ */

class BV_ContextLogger {
  constructor(
    private parent: BV_Logger,
    private context: string,
  ) {}

  debug(message: string, data?: Record<string, unknown>): void {
    this.parent.debug(message, data, this.context);
  }

  info(message: string, data?: Record<string, unknown>): void {
    this.parent.info(message, data, this.context);
  }

  warning(message: string, data?: Record<string, unknown>): void {
    this.parent.warning(message, data, this.context);
  }

  error(message: string, data?: Record<string, unknown>): void {
    this.parent.error(message, data, this.context);
  }
}

/* ═══════════════════════════════════════════════════════════════
   Singleton Export
   ═══════════════════════════════════════════════════════════════ */

/** The global logger instance */
export const logger = BV_Logger.getInstance();

/** Type for context-scoped loggers */
export type { BV_ContextLogger as ContextLogger };
