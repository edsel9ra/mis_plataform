import type { AccountRole } from './accounts';

export interface AuditRoute {
  name: string;
  path: string;
  role: AccountRole | null;
  wait?: string;
}

const MISSING_UUID = '11111111-2222-4333-8444-555555555555';

export const ROUTES: AuditRoute[] = [
  // Sin sesión
  { name: 'landing', path: '/', role: null },
  { name: 'login', path: '/login', role: null },
  { name: 'register', path: '/register', role: null },
  { name: 'register-personal', path: '/register/personal', role: null },
  { name: 'register-invalid', path: '/register/no-existe', role: null },
  { name: 'forgot-password', path: '/forgot-password', role: null },
  { name: 'reset-password', path: '/reset-password', role: null },
  { name: 'reset-password-invalid', path: '/reset-password?token=invalido&email=x%40audit.mis.test', role: null },
  { name: 'oauth-callback-error', path: '/oauth/callback?error=access_denied', role: null },
  { name: 'mentors-public', path: '/mentors', role: null },
  { name: 'mentor-public', path: '/mentors/{mentorId}', role: null },
  { name: 'mentor-not-a-uuid', path: '/mentors/not-a-uuid', role: null },
  { name: 'mentor-missing', path: `/mentors/${MISSING_UUID}`, role: null },

  // ana
  { name: 'ana-dashboard', path: '/dashboard/personal', role: 'ana' },
  { name: 'ana-progreso', path: '/dashboard/progreso', role: 'ana' },
  { name: 'ana-progreso-mapa', path: '/dashboard/progreso/mapa', role: 'ana' },
  { name: 'ana-progreso-historial', path: '/dashboard/progreso/historial', role: 'ana' },
  { name: 'ana-challenges', path: '/challenges', role: 'ana' },
  { name: 'ana-mentors', path: '/mentors', role: 'ana' },
  { name: 'ana-mentor', path: '/mentors/{mentorId}', role: 'ana' },
  { name: 'ana-learning-paths', path: '/learning-paths', role: 'ana' },
  { name: 'ana-certificates', path: '/certificates', role: 'ana' },
  { name: 'ana-recommendations', path: '/recommendations', role: 'ana' },
  { name: 'ana-profile', path: '/profile', role: 'ana' },
  { name: 'ana-sessions', path: '/sessions', role: 'ana' },
  { name: 'ana-session', path: '/sessions/{sessionId}', role: 'ana' },
  { name: 'ana-session-not-a-uuid', path: '/sessions/not-a-uuid', role: 'ana' },
  { name: 'ana-sessions-new', path: '/sessions/new', role: 'ana' },
  { name: 'ana-evaluations', path: '/evaluations', role: 'ana' },
  { name: 'ana-evaluation-open', path: '/evaluations/{evaluationOpenId}', role: 'ana' },
  { name: 'ana-evaluation-done', path: '/evaluations/{evaluationDoneId}', role: 'ana' },
  { name: 'ana-evaluation-not-a-uuid', path: '/evaluations/not-a-uuid', role: 'ana' },
  { name: 'ana-messages', path: '/messages', role: 'ana' },

  // kai / carlos / diana
  { name: 'kai-dashboard', path: '/dashboard/personal', role: 'kai' },
  { name: 'kai-personality-test', path: '/onboarding/test-personalidad', role: 'kai' },
  { name: 'carlos-dashboard', path: '/dashboard/familiar', role: 'carlos' },
  { name: 'diana-dashboard', path: '/dashboard/grupal', role: 'diana' },

  // empresa
  { name: 'empresa-admin-dashboard', path: '/dashboard/empresa', role: 'empresaAdmin' },
  { name: 'empresa-admin-employees', path: '/dashboard/empresa/employees', role: 'empresaAdmin' },
  { name: 'empleado-dashboard', path: '/dashboard/empresa', role: 'empleado' },
  { name: 'empresa-setup-dashboard', path: '/dashboard/empresa', role: 'empresaSetup' },
  { name: 'empresa-setup-employees', path: '/dashboard/empresa/employees', role: 'empresaSetup' },

  // mentor
  { name: 'mentor-dashboard', path: '/dashboard/mentor', role: 'mentor' },
  { name: 'mentor-challenges', path: '/dashboard/mentor/challenges', role: 'mentor' },
  { name: 'mentor-feedback', path: '/dashboard/mentor/feedback', role: 'mentor' },
  { name: 'mentor-progress', path: '/dashboard/mentor/progress', role: 'mentor' },
  { name: 'mentor-progress-ana', path: '/dashboard/mentor/progress/{anaId}', role: 'mentor' },
  { name: 'mentor-progress-ana-mapa', path: '/dashboard/mentor/progress/{anaId}/mapa', role: 'mentor' },
  { name: 'mentor-progress-ana-historial', path: '/dashboard/mentor/progress/{anaId}/historial', role: 'mentor' },
  { name: 'mentor-progress-kai-mapa-locked', path: '/dashboard/mentor/progress/{kaiId}/mapa', role: 'mentor' },
  { name: 'mentor-progress-kai-historial-locked', path: '/dashboard/mentor/progress/{kaiId}/historial', role: 'mentor' },
  { name: 'mentor-user-profile', path: '/dashboard/mentor/users/{anaId}?relationship_id={relationshipId}', role: 'mentor' },
  { name: 'mentor-availability', path: '/availability', role: 'mentor' },
  { name: 'mentor-profile', path: '/profile', role: 'mentor' },

  // admin
  { name: 'admin-dashboard', path: '/admin', role: 'admin' },
  { name: 'admin-users', path: '/admin/users', role: 'admin' },
  { name: 'admin-mentors', path: '/admin/mentors', role: 'admin' },
  { name: 'admin-sessions', path: '/admin/sessions', role: 'admin' },
  { name: 'admin-evaluations', path: '/admin/evaluations', role: 'admin' },
];
