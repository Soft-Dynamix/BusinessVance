/**
 * BusinessVance Platform – Database Versioning
 *
 * Manages database schema version tracking and migration orchestration.
 * Does NOT create new tables. Simply prepares the migration framework
 * for future schema changes.
 *
 * Usage:
 *   import { dbVersion } from '@/lib/services';
 *
 *   // Register a migration
 *   dbVersion.register({
 *     version: '2.1.0',
 *     name: 'add_report_template_link',
 *     description: 'Add reportTemplateId to Service model',
 *     up: async () => { ... },
 *     down: async () => { ... },
 *   });
 *
 *   // Run pending migrations
 *   await dbVersion.runMigrations();
 *
 * @package BusinessVance\Services
 * @since   2.0.0
 */

import { logger } from '@/lib/core/bv-logger';
import { BV_DB_VERSION, BV_EVENTS } from '@/lib/core/bv-constants';
import { events } from '@/lib/core/bv-events';
import { db } from '@/lib/db';
import type { MigrationDefinition, MigrationRecord } from '@/lib/interfaces';

/* ═══════════════════════════════════════════════════════════════
   Version Manager Implementation
   ═══════════════════════════════════════════════════════════════ */

class BV_DatabaseVersion {
  private migrations: Map<string, MigrationDefinition> = new Map();
  private static instance: BV_DatabaseVersion;

  private constructor() {}

  static getInstance(): BV_DatabaseVersion {
    if (!BV_DatabaseVersion.instance) {
      BV_DatabaseVersion.instance = new BV_DatabaseVersion();
    }
    return BV_DatabaseVersion.instance;
  }

  /* ─── Migration Registration ─────────────────────── */

  /**
   * Register a migration definition.
   * Migrations are executed in version order.
   */
  register(migration: MigrationDefinition): void {
    if (this.migrations.has(migration.version)) {
      logger.warning(`Migration already registered: ${migration.version}`, {}, 'DBVersion');
      return;
    }
    this.migrations.set(migration.version, migration);
    logger.debug(`Migration registered: ${migration.version} - ${migration.name}`, {}, 'DBVersion');
  }

  /* ─── Version Tracking ───────────────────────────── */

  /**
   * Get the current database version from the PluginSetting table.
   */
  async getCurrentVersion(): Promise<string> {
    try {
      const setting = await db.pluginSetting.findUnique({
        where: { key: 'bv_db_version' },
      });
      return setting?.value || '1.0.0';
    } catch {
      logger.debug('bv_db_version setting not found, using default', {}, 'DBVersion');
      return '1.0.0';
    }
  }

  /**
   * Update the stored database version.
   */
  async setVersion(version: string): Promise<void> {
    await db.pluginSetting.upsert({
      where: { key: 'bv_db_version' },
      update: { value: version },
      create: { key: 'bv_db_version', value: version },
    });
    logger.info(`Database version updated to ${version}`, {}, 'DBVersion');
  }

  /**
   * Get all registered migration definitions.
   */
  getRegisteredMigrations(): MigrationDefinition[] {
    return Array.from(this.migrations.values()).sort((a, b) =>
      a.version.localeCompare(b.version, undefined, { numeric: true }),
    );
  }

  /* ─── Migration Execution ────────────────────────── */

  /**
   * Get all pending migrations (registered but not yet applied).
   */
  async getPendingMigrations(): Promise<MigrationDefinition[]> {
    const current = await this.getCurrentVersion();
    return this.getRegisteredMigrations().filter(
      (m) => m.version.localeCompare(current, undefined, { numeric: true }) > 0,
    );
  }

  /**
   * Run all pending migrations in version order.
   * @returns Array of migration results.
   */
  async runMigrations(): Promise<MigrationRecord[]> {
    const pending = await this.getPendingMigrations();
    if (pending.length === 0) {
      logger.info('No pending migrations', {}, 'DBVersion');
      return [];
    }

    logger.info(`Running ${pending.length} pending migration(s)...`, {}, 'DBVersion');
    const results: MigrationRecord[] = [];

    for (const migration of pending) {
      try {
        logger.info(`Applying migration: ${migration.version} - ${migration.name}`, {}, 'DBVersion');

        await migration.up();
        await this.setVersion(migration.version);

        const record: MigrationRecord = {
          version: migration.version,
          name: migration.name,
          appliedAt: new Date(),
        };
        results.push(record);

        logger.info(`Migration applied: ${migration.version}`, {}, 'DBVersion');
      } catch (err) {
        const errorMsg = err instanceof Error ? err.message : String(err);
        logger.error(`Migration FAILED: ${migration.version} - ${migration.name}`, {
          error: errorMsg,
        }, 'DBVersion');
        throw new Error(`Migration failed at ${migration.version}: ${errorMsg}`);
      }
    }

    // Dispatch initialization event after all migrations
    events.dispatch(BV_EVENTS.INITIALIZED, {
      version: BV_DB_VERSION,
      migrationsApplied: results.length,
    });

    return results;
  }

  /**
   * Check if the database schema is up to date.
   */
  async isUpToDate(): Promise<boolean> {
    const pending = await this.getPendingMigrations();
    return pending.length === 0;
  }
}

/* ═══════════════════════════════════════════════════════════════
   Singleton Export
   ═══════════════════════════════════════════════════════════════ */

/** The global database version manager instance */
export const dbVersion = BV_DatabaseVersion.getInstance();
