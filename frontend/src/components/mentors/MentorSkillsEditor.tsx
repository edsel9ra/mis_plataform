'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { type MentorSkillInput, type SkillTag, skillLevels } from '@/lib/mentor-skills';

interface MentorSkillsEditorProps {
  catalog: SkillTag[];
  value: MentorSkillInput[];
  onChange: (value: MentorSkillInput[]) => void;
  disabled?: boolean;
}

export function MentorSkillsEditor({ catalog, value, onChange, disabled = false }: MentorSkillsEditorProps) {
  const t = useTranslations();
  const [pendingTagId, setPendingTagId] = useState('');
  const selectedIds = new Set(value.map((skill) => skill.skill_tag_id));
  const availableTags = catalog.filter((tag) => tag.is_active && !selectedIds.has(tag.id));

  function addSkill() {
    if (!pendingTagId) return;

    onChange([...value, { skill_tag_id: pendingTagId, level: 'intermediate' }]);
    setPendingTagId('');
  }

  function updateLevel(skillTagId: string, level: MentorSkillInput['level']) {
    onChange(value.map((skill) => skill.skill_tag_id === skillTagId ? { ...skill, level } : skill));
  }

  function removeSkill(skillTagId: string) {
    onChange(value.filter((skill) => skill.skill_tag_id !== skillTagId));
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
        <div className="min-w-0 flex-1">
          <Select
            label={t('mentors.select_specialty')}
            value={pendingTagId}
            disabled={disabled || availableTags.length === 0}
            onChange={(event) => setPendingTagId(event.target.value)}
            options={availableTags.map((tag) => ({ value: tag.id, label: tag.name }))}
            placeholder={availableTags.length === 0 ? t('mentors.all_specialties_selected') : t('mentors.choose_specialty')}
          />
        </div>
        <Button type="button" variant="outline" onClick={addSkill} disabled={disabled || !pendingTagId}>
          {t('mentors.add_specialty')}
        </Button>
      </div>

      {value.length === 0 ? (
        <p className="rounded-lg border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-500">
          {t('mentors.no_specialties')}
        </p>
      ) : (
        <div className="space-y-2">
          {value.map((skill) => {
            const tag = catalog.find((candidate) => candidate.id === skill.skill_tag_id);

            return (
              <div key={skill.skill_tag_id} className="flex flex-col gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3 sm:flex-row sm:items-center">
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium text-gray-900">{tag?.name ?? skill.skill_tag_id}</p>
                  {tag?.category && <p className="mt-0.5 text-xs text-gray-500">{tag.category}</p>}
                </div>
                <Select
                  aria-label={`${t('mentors.level')} ${tag?.name ?? skill.skill_tag_id}`}
                  className="sm:w-44"
                  value={skill.level}
                  disabled={disabled}
                  onChange={(event) => updateLevel(skill.skill_tag_id, event.target.value as MentorSkillInput['level'])}
                  options={skillLevels.map((level) => ({ value: level, label: t(`learning_levels.${level}`) }))}
                />
                <Button type="button" variant="ghost" size="sm" onClick={() => removeSkill(skill.skill_tag_id)} disabled={disabled}>
                  {t('common.delete')}
                </Button>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
