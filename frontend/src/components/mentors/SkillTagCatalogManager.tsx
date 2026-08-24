'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { apiErrorMessage } from '@/lib/errors';
import {
  useAdminSkillTags,
  useArchiveSkillTag,
  useCreateSkillTag,
  useUpdateSkillTag,
} from '@/hooks/use-mentor-skills';
import type { SkillTag } from '@/lib/mentor-skills';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { PageSpinner } from '@/components/ui/spinner';

const emptyForm = { name: '', category: '' };

export function SkillTagCatalogManager() {
  const t = useTranslations();
  const { data: tags = [], isLoading, error } = useAdminSkillTags();
  const createMutation = useCreateSkillTag();
  const updateMutation = useUpdateSkillTag();
  const archiveMutation = useArchiveSkillTag();
  const [showModal, setShowModal] = useState(false);
  const [editingTag, setEditingTag] = useState<SkillTag | null>(null);
  const [form, setForm] = useState(emptyForm);
  const mutationError = createMutation.error ?? updateMutation.error ?? archiveMutation.error;

  function openCreate() {
    setEditingTag(null);
    setForm(emptyForm);
    setShowModal(true);
  }

  function openEdit(tag: SkillTag) {
    setEditingTag(tag);
    setForm({ name: tag.name, category: tag.category ?? '' });
    setShowModal(true);
  }

  function closeModal() {
    setShowModal(false);
    setEditingTag(null);
    setForm(emptyForm);
  }

  function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const payload = { name: form.name.trim(), category: form.category.trim() || null };

    if (editingTag) {
      updateMutation.mutate({ id: editingTag.id, payload }, { onSuccess: closeModal });
    } else {
      createMutation.mutate(payload, { onSuccess: closeModal });
    }
  }

  function toggleActive(tag: SkillTag) {
    if (tag.is_active && !window.confirm(t('admin.confirm_archive_specialty'))) return;

    updateMutation.mutate({ id: tag.id, payload: { is_active: !tag.is_active } });
  }

  return (
    <Card className="border-0 shadow-sm ring-1 ring-slate-100">
      <CardHeader>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <CardTitle className="text-base">{t('admin.specialties_catalog')}</CardTitle>
            <p className="mt-1 text-sm text-gray-500">{t('admin.specialties_catalog_description')}</p>
          </div>
          <Button type="button" size="sm" onClick={openCreate}>+ {t('admin.add_specialty')}</Button>
        </div>
      </CardHeader>

      {isLoading ? (
        <PageSpinner />
      ) : error ? (
        <Alert variant="error">{apiErrorMessage(error, (key) => t(key)) || t('common.load_error')}</Alert>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {tags.map((tag) => (
            <div key={tag.id} className="rounded-xl border border-gray-200 bg-gray-50 p-3">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="truncate text-sm font-semibold text-gray-900">{tag.name}</p>
                  <p className="mt-1 truncate text-xs text-gray-500">{tag.category || t('common.not_applicable')}</p>
                </div>
                <Badge variant={tag.is_active ? 'success' : 'default'}>
                  {tag.is_active ? t('common.active') : t('common.inactive')}
                </Badge>
              </div>
              <div className="mt-3 flex gap-2">
                <Button type="button" variant="outline" size="sm" onClick={() => openEdit(tag)}>{t('common.edit')}</Button>
                <Button type="button" variant="ghost" size="sm" onClick={() => toggleActive(tag)}>
                  {tag.is_active ? t('admin.archive_specialty') : t('admin.restore_specialty')}
                </Button>
              </div>
            </div>
          ))}
          {tags.length === 0 && <p className="text-sm text-gray-500">{t('common.no_results')}</p>}
        </div>
      )}

      {mutationError && (
        <Alert variant="error" className="mt-4">
          {apiErrorMessage(mutationError, (key) => t(key)) || t('common.error')}
        </Alert>
      )}

      <Dialog open={showModal} onOpenChange={(open) => open ? setShowModal(true) : closeModal()}>
        <DialogContent aria-labelledby="specialty-dialog-title">
          <DialogHeader>
            <DialogTitle id="specialty-dialog-title">
              {editingTag ? t('admin.edit_specialty') : t('admin.add_specialty')}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <Input
              label={t('admin.specialty_name')}
              value={form.name}
              maxLength={100}
              required
              onChange={(event) => setForm({ ...form, name: event.target.value })}
            />
            <Input
              label={t('admin.specialty_category')}
              value={form.category}
              maxLength={50}
              onChange={(event) => setForm({ ...form, category: event.target.value })}
            />
            <DialogFooter>
              <Button type="button" variant="outline" onClick={closeModal}>{t('common.cancel')}</Button>
              <Button type="submit" loading={createMutation.isPending || updateMutation.isPending}>
                {t('common.save')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </Card>
  );
}
