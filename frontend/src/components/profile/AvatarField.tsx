'use client';

import { useRef, useState, type FormEvent } from 'react';
import { useTranslations } from 'next-intl';
import { ApiError } from '@/lib/api';
import { apiErrorMessage } from '@/lib/errors';
import {
  avatarSchema,
  avatarServerFieldErrors,
  AVATAR_MIME_TYPES,
} from '@/lib/schemas/profile';
import { useUploadAvatar } from '@/hooks/use-profile';
import { useAuth } from '@/components/auth/AuthProvider';
import { Alert } from '@/components/ui/alert';
import { Avatar } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { FileUploadField } from '@/components/ui/file-upload-field';

interface AvatarFieldProps {
  currentAvatar: string | null;
  initial: string;
}

export function AvatarField({ currentAvatar, initial }: AvatarFieldProps) {
  const t = useTranslations();
  const { refreshUser } = useAuth();
  const upload = useUploadAvatar();
  const inputRef = useRef<HTMLInputElement>(null);
  const [file, setFile] = useState<File | null>(null);
  const [fieldError, setFieldError] = useState<string | null>(null);
  const [formError, setFormError] = useState<unknown>(null);
  const [feedback, setFeedback] = useState('');

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setFeedback('');
    setFormError(null);

    const parsed = avatarSchema.safeParse({ avatar: file });
    if (!parsed.success) {
      setFieldError(parsed.error.issues[0]?.message ?? 'profile.validation.avatar_required');
      return;
    }
    setFieldError(null);

    try {
      await upload.mutateAsync(parsed.data.avatar);
      await refreshUser();
      setFile(null);
      if (inputRef.current) inputRef.current.value = '';
      setFeedback(t('profile.avatar.saved'));
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        const mapped = avatarServerFieldErrors(error.errors ?? {});
        if (mapped.fields.avatar) setFieldError(mapped.fields.avatar);
        else setFormError(mapped.formError ?? error);
      } else {
        setFormError(error);
      }
    }
  }

  return (
    <Card className="border-0 shadow-sm">
      <h2 className="text-lg font-semibold text-gray-950">{t('profile.avatar.title')}</h2>
      <p className="mt-1 text-sm text-gray-500">{t('profile.avatar.description')}</p>
      <div role="status" className="sr-only">{feedback}</div>
      <form onSubmit={(event) => void submit(event)} className="mt-4 space-y-4">
        <div className="flex items-center gap-4">
          <Avatar
            src={currentAvatar}
            size="lg"
            alt=""
            className="bg-primary-100 text-primary-700"
            fallback={<span className="text-2xl font-bold">{initial}</span>}
          />
          <div className="min-w-0 flex-1">
            <FileUploadField
              ref={inputRef}
              label={t('profile.avatar.label')}
              hint={file?.name || t('profile.avatar.hint')}
              accept={AVATAR_MIME_TYPES.join(',')}
              error={fieldError ? (fieldError.startsWith('profile.validation.') ? t(fieldError) : fieldError) : undefined}
              disabled={upload.isPending}
              onChange={(event) => {
                setFieldError(null);
                setFile(event.target.files?.[0] ?? null);
              }}
            />
          </div>
        </div>
        {Boolean(formError) && (
          <Alert variant="error">
            {typeof formError === 'string' ? formError : apiErrorMessage(formError, (key) => t(key)) || t('profile.avatar.error')}
          </Alert>
        )}
        <Button type="submit" loading={upload.isPending} className="min-h-11">
          {t('profile.avatar.submit')}
        </Button>
      </form>
    </Card>
  );
}
