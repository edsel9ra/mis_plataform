import { useMutation } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { ProfileDetailsPayload } from '@/lib/schemas/profile';

export function updateProfileDetails(payload: ProfileDetailsPayload) {
  return api.put('/profile', payload);
}

export function uploadAvatar(file: File): Promise<{ avatar: string }> {
  const body = new FormData();
  body.append('avatar', file);
  // Laravel no parsea multipart en PUT: se envía POST con _method.
  body.append('_method', 'PUT');
  return api.post('/profile/avatar', body, { timeoutMs: 60_000 });
}

export function useUpdateProfileDetails() {
  return useMutation({ mutationFn: updateProfileDetails });
}

export function useUploadAvatar() {
  return useMutation({ mutationFn: uploadAvatar });
}
