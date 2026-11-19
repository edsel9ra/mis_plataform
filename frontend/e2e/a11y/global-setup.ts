import { request, type FullConfig } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { ACCOUNTS, password, storageStatePath, type AccountRole } from './accounts';

/**
 * Logs every audit account in through the frontend origin (the same rewrites
 * the browser uses) so each storageState carries the mis_auth_token cookie for
 * the right host. The auth-login limiter allows 5/minute per IP+email pair, so
 * one login per account is far below the threshold.
 *
 * Then resolves the dynamic IDs the route matrix needs, through the same API
 * endpoints the frontend uses, and writes them to e2e/.auth/fixtures.json.
 */
export default async function globalSetup(config: FullConfig): Promise<void> {
  const baseURL = config.projects[0]?.use?.baseURL as string | undefined;
  if (!baseURL) {
    throw new Error('playwright.config.ts must set use.baseURL (A11Y_BASE_URL).');
  }

  const secret = password();
  const roles = (process.env.A11Y_ROLES?.split(',').map((role) => role.trim()).filter(Boolean)
    ?? Object.keys(ACCOUNTS)) as AccountRole[];

  const userIds = new Map<AccountRole, string>();
  let mentorLastName: string | undefined;

  for (const role of roles) {
    const email = ACCOUNTS[role];
    if (!email) {
      throw new Error(`Unknown audit role in A11Y_ROLES: ${role}`);
    }

    const context = await request.newContext({ baseURL });
    try {
      const csrf = await context.get('/sanctum/csrf-cookie', {
        headers: { Accept: 'application/json' },
      });
      if (!csrf.ok()) {
        throw new Error(`CSRF cookie request failed for ${role}: ${csrf.status()}`);
      }

      const xsrfCookie = (await context.storageState()).cookies.find((cookie) => cookie.name === 'XSRF-TOKEN');
      const login = await context.post('/api/v1/auth/login', {
        data: { email, password: secret },
        headers: {
          Accept: 'application/json',
          ...(xsrfCookie ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrfCookie.value) } : {}),
        },
      });
      if (login.status() !== 200) {
        throw new Error(`Login failed for ${role} (${email}): HTTP ${login.status()} ${await login.text()}`);
      }

      const body = (await login.json()) as { user?: { id?: string; last_name?: string } };
      if (body.user?.id) userIds.set(role, body.user.id);
      if (role === 'mentor' && body.user?.last_name) mentorLastName = body.user.last_name;
      await context.storageState({ path: storageStatePath(role) });
    } finally {
      await context.dispose();
    }
  }

  await resolveFixtures(baseURL, userIds, mentorLastName);
}

async function resolveFixtures(baseURL: string, userIds: Map<AccountRole, string>, mentorLastName: string | undefined): Promise<void> {
  const mentorId = userIds.get('mentor');
  const anaId = userIds.get('ana');
  const kaiId = userIds.get('kai');
  if (!mentorId || !anaId || !kaiId) {
    throw new Error('Fixture resolution needs the mentor, ana and kai logins (A11Y_ROLES must include them).');
  }

  const ana = await request.newContext({ baseURL, storageState: storageStatePath('ana') });
  try {
    const relationships = (await getJson(ana, '/api/v1/relationships', { per_page: 50 })) as {
      data?: Array<{ id: string; mentor?: { id: string } }>;
    };
    const relationshipId = relationships.data?.find((rel) => rel.mentor?.id === mentorId)?.id;
    if (!relationshipId) throw new Error('No relationship found between ana and mentor.audit');

    const sessions = (await getJson(ana, '/api/v1/sessions', { per_page: 50 })) as {
      data?: Array<{ id: string; scheduled_at: string; status?: string }>;
    };
    const now = Date.now();
    const sessionId = sessions.data
      ?.filter((session) => new Date(session.scheduled_at).getTime() > now)
      .sort((a, b) => new Date(a.scheduled_at).getTime() - new Date(b.scheduled_at).getTime())[0]?.id;
    if (!sessionId) throw new Error('No future session found for ana');

    // Most recent past session still scheduled: the attendance window is open.
    const pastSessionId = sessions.data
      ?.filter((session) => new Date(session.scheduled_at).getTime() < now
        && session.status !== 'completed' && session.status !== 'canceled' && session.status !== 'not_completed')
      .sort((a, b) => new Date(b.scheduled_at).getTime() - new Date(a.scheduled_at).getTime())[0]?.id;
    if (!pastSessionId) throw new Error('No past attendance-window session found for ana');

    const evaluations = (await getJson(ana, '/api/v1/evaluations', { per_page: 100 })) as {
      data?: Array<{ id: string; user_result?: { id: string } | null }>;
    };
    const items = evaluations.data ?? [];
    const unanswered = items.filter((item) => !item.user_result).map((item) => item.id);
    const evaluationOpenId = unanswered[0];
    // The submit test consumes one unanswered evaluation per run: pick the
    // last one so evaluationOpenId (used read-only by other tests) is untouched.
    const evaluationSubmitId = unanswered[unanswered.length - 1];
    const evaluationDoneId = items.find((item) => item.user_result)?.id;
    if (!evaluationOpenId || !evaluationDoneId || unanswered.length < 2) {
      throw new Error('Expected an answered evaluation and at least two unanswered ones for ana');
    }

    const fixturesPath = path.join(__dirname, '..', '.auth', 'fixtures.json');
    fs.mkdirSync(path.dirname(fixturesPath), { recursive: true });
    fs.writeFileSync(fixturesPath, `${JSON.stringify({
      mentorId, mentorLastName, anaId, kaiId, relationshipId, sessionId, pastSessionId,
      evaluationOpenId, evaluationSubmitId, evaluationDoneId,
    }, null, 2)}\n`);
  } finally {
    await ana.dispose();
  }
}

async function getJson(context: Awaited<ReturnType<typeof request.newContext>>, url: string, params: Record<string, string | number>): Promise<unknown> {
  const response = await context.get(url, { headers: { Accept: 'application/json' }, params });
  if (!response.ok()) {
    throw new Error(`GET ${url} failed: HTTP ${response.status()} ${await response.text()}`);
  }

  return response.json();
}
