import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

import { api } from '@/lib/api';
import { submitObjectiveEvidence, uploadObjectiveEvidenceMedia } from './use-progress';

const OBJECTIVE_ID = '11111111-1111-4111-8111-111111111111';

describe('objective evidence media flow (T020.3)', () => {
  beforeEach(() => vi.clearAllMocks());

  it('uploads the file as multipart form data and returns the asset id', async () => {
    vi.mocked(api.post).mockResolvedValue({ data: { id: 'asset-1', purpose: 'objective_evidence' } });

    const file = new File(['hello'], 'note.txt', { type: 'text/plain' });
    const id = await uploadObjectiveEvidenceMedia(OBJECTIVE_ID, file);

    expect(id).toBe('asset-1');
    const [endpoint, body, options] = vi.mocked(api.post).mock.calls[0];
    expect(endpoint).toBe(`/objectives/${OBJECTIVE_ID}/media`);
    expect(body).toBeInstanceOf(FormData);
    expect((body as FormData).get('file')).toBe(file);
    expect(options).toMatchObject({ timeoutMs: expect.any(Number) });
  });

  it('submits note and media_asset_id after a successful upload', async () => {
    vi.mocked(api.post)
      .mockResolvedValueOnce({ data: { id: 'asset-9' } })
      .mockResolvedValueOnce({ data: { id: 'evidence-1' } });

    const file = new File(['hello'], 'note.txt', { type: 'text/plain' });
    await submitObjectiveEvidence(OBJECTIVE_ID, { note: ' avance ', file });

    expect(api.post).toHaveBeenCalledTimes(2);
    const [evidenceEndpoint, payload] = vi.mocked(api.post).mock.calls[1];
    expect(evidenceEndpoint).toBe(`/objectives/${OBJECTIVE_ID}/evidences`);
    expect(payload).toEqual({ note: 'avance', media_asset_id: 'asset-9' });
  });

  it('does not create the evidence when the upload fails', async () => {
    vi.mocked(api.post).mockRejectedValueOnce(new Error('upload failed'));

    const file = new File(['hello'], 'note.txt', { type: 'text/plain' });
    await expect(submitObjectiveEvidence(OBJECTIVE_ID, { note: 'x', file })).rejects.toThrow('upload failed');

    expect(api.post).toHaveBeenCalledTimes(1);
    expect(vi.mocked(api.post).mock.calls[0][0]).toBe(`/objectives/${OBJECTIVE_ID}/media`);
  });

  it('sends only the note when there is no file', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ data: { id: 'evidence-2' } });

    await submitObjectiveEvidence(OBJECTIVE_ID, { note: 'solo nota' });

    expect(api.post).toHaveBeenCalledTimes(1);
    const [endpoint, payload] = vi.mocked(api.post).mock.calls[0];
    expect(endpoint).toBe(`/objectives/${OBJECTIVE_ID}/evidences`);
    expect(payload).toEqual({ note: 'solo nota', media_asset_id: undefined });
  });
});
