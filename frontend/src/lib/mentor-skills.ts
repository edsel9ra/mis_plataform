export const skillLevels = ['beginner', 'intermediate', 'advanced', 'expert'] as const;

export type SkillLevel = (typeof skillLevels)[number];

export interface SkillTag {
  id: string;
  name: string;
  category: string | null;
  is_active: boolean;
}

export interface UserSkill {
  id: string;
  skill: SkillTag | null;
  level: SkillLevel;
}

export interface MentorSkillInput {
  skill_tag_id: string;
  level: SkillLevel;
}

export interface SkillTagPayload {
  name: string;
  category?: string | null;
}

export type CollectionResponse<T> = T[] | { data: T[] };

export function unwrapCollection<T>(response: CollectionResponse<T>): T[] {
  return Array.isArray(response) ? response : response.data;
}

export function toSkillInputs(skills: UserSkill[] | undefined): MentorSkillInput[] {
  return (skills ?? [])
    .filter((skill): skill is UserSkill & { skill: SkillTag } => Boolean(skill.skill?.is_active))
    .map((skill) => ({
      skill_tag_id: skill.skill.id,
      level: skill.level,
    }));
}
