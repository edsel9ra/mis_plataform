import path from 'node:path';

/**
 * Accounts seeded by AccessibilityAuditSeeder (see
 * specs/019-verificacion-accesible/fixtures.md). The password is never in the
 * repository: it arrives via A11Y_PASSWORD.
 */
export const ACCOUNTS = {
  admin: 'admin.audit@audit.mis.test',
  ana: 'ana.audit@audit.mis.test',
  kai: 'kai.audit@audit.mis.test',
  carlos: 'carlos.audit@audit.mis.test',
  diana: 'diana.audit@audit.mis.test',
  empresaAdmin: 'empresa.admin.audit@audit.mis.test',
  empleado: 'empleado.audit@audit.mis.test',
  empresaSetup: 'empresa.setup.audit@audit.mis.test',
  mentor: 'mentor.audit@audit.mis.test',
  relleno04: 'relleno.04.audit@audit.mis.test',
  relleno05: 'relleno.05.audit@audit.mis.test',
} as const;

export type AccountRole = keyof typeof ACCOUNTS;

export function password(): string {
  const value = process.env.A11Y_PASSWORD;
  if (!value) {
    throw new Error('A11Y_PASSWORD is required: it must match A11Y_SEED_PASSWORD from the audit seeding.');
  }

  return value;
}

export function storageStatePath(role: AccountRole): string {
  return path.join(__dirname, '..', '.auth', `${role}.json`);
}
