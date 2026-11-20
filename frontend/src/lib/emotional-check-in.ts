export const CHECK_IN_SCALE = [1, 2, 3, 4, 5] as const;

export type CheckInScore = (typeof CHECK_IN_SCALE)[number];

export function moodLabelKey(score: number): string {
  return `mentee.check_in.mood_levels.${score}`;
}

export function stressLabelKey(score: number | null | undefined): string {
  return score == null
    ? 'mentee.check_in.stress_not_set'
    : `mentee.check_in.stress_levels.${score}`;
}
