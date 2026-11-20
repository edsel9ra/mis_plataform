import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

import { api } from '@/lib/api';
import { queryKeys } from '@/lib/query-keys';
import { activateEmployee, deactivateEmployee, inviteEmployee, updateEmployee } from './use-company';

const COMPANY = 'comp-1';

describe('company api (T020.6)', () => {
  beforeEach(() => vi.clearAllMocks());

  it('posts the invite payload to the company employees endpoint', async () => {
    vi.mocked(api.post).mockResolvedValue({ employee: { id: 'emp-9', status: 'invited' } });
    const payload = {
      email: 'nuevo@example.com',
      name: 'Nueva',
      last_name: null,
      position: null,
      department: 'Operaciones',
    };

    const result = await inviteEmployee(COMPANY, payload);

    expect(api.post).toHaveBeenCalledWith(`/companies/${COMPANY}/employees`, payload);
    expect(result).toEqual({ employee: { id: 'emp-9', status: 'invited' } });
  });

  it('puts the update payload to the employee endpoint', async () => {
    vi.mocked(api.put).mockResolvedValue({ id: 'emp-9', position: 'Lead' });

    const result = await updateEmployee('emp-9', { position: 'Lead', department: 'Finanzas' });

    expect(api.put).toHaveBeenCalledWith('/employees/emp-9', { position: 'Lead', department: 'Finanzas' });
    expect(result).toEqual({ id: 'emp-9', position: 'Lead' });
  });

  it('posts to the activate endpoint', async () => {
    vi.mocked(api.post).mockResolvedValue({ id: 'emp-9', status: 'active' });

    const result = await activateEmployee('emp-9');

    expect(api.post).toHaveBeenCalledWith('/employees/emp-9/activate');
    expect(result).toEqual({ id: 'emp-9', status: 'active' });
  });

  it('deletes via the deactivate endpoint', async () => {
    vi.mocked(api.delete).mockResolvedValue({ id: 'emp-9', status: 'inactive' });

    const result = await deactivateEmployee('emp-9');

    expect(api.delete).toHaveBeenCalledWith('/employees/emp-9');
    expect(result).toEqual({ id: 'emp-9', status: 'inactive' });
  });

  it('scopes the employees query keys to the company', () => {
    expect(queryKeys.companyEmployeesRoot(COMPANY)).toEqual(['company-employees', COMPANY]);
    expect(queryKeys.companyEmployees(COMPANY, 2, 12)).toEqual([
      'company-employees',
      COMPANY,
      { page: 2, per_page: 12 },
    ]);
  });
});
