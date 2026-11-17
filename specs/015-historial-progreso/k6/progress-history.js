// Spec 015 benchmark (benchmark.md §Escenario k6). Run on the Compose network
// against the isolated bench stack, with the fixtures written by seed-dataset.php:
//
//   docker run --rm --network mis-project_mis-network \
//     -v "$PWD/specs/015-historial-progreso/k6:/scripts:ro" -v /tmp/mis-bench/out:/bench-out \
//     -e BASE_URL=http://mis-bench-nginx grafana/k6 run /scripts/progress-history.js
//
// 50 subject VUs with no think time keep 50 history requests in flight
// (the RNF-015.4 "50 consultas interactivas simultáneas"), plus one mentor
// VU and one support VU for the whole run.
import http from 'k6/http';
import { check, fail } from 'k6';
import { Counter } from 'k6/metrics';
import { SharedArray } from 'k6/data';

const BASE_URL = __ENV.BASE_URL || 'http://mis-bench-nginx';
const API = `${BASE_URL}/api/v1`;
const fixtures = JSON.parse(open('/bench-out/fixtures.json'));
const subjects = new SharedArray('subjects', () => fixtures.subjects);

const duplicateIds = new Counter('history_duplicate_ids');
const contractViolations = new Counter('history_contract_violations');

const P95 = ['p(95)<2000'];

export const options = {
  scenarios: {
    subjects: {
      executor: 'ramping-vus',
      exec: 'subjectFlow',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 50 },
        { duration: '2m', target: 50 },
        { duration: '30s', target: 0 },
      ],
      gracefulRampDown: '10s',
    },
    mentor: { executor: 'constant-vus', exec: 'mentorFlow', vus: 1, duration: '3m' },
    support: { executor: 'constant-vus', exec: 'supportFlow', vus: 1, duration: '3m' },
  },
  thresholds: {
    'http_req_duration{endpoint:history_first}': P95,
    'http_req_duration{endpoint:history_next}': P95,
    'http_req_duration{endpoint:history_mentor}': P95,
    'http_req_duration{endpoint:history_support}': P95,
    'http_req_duration{endpoint:panel}': P95,
    http_req_failed: ['rate<0.01'],
    history_duplicate_ids: ['count==0'],
    history_contract_violations: ['count==0'],
  },
  summaryTrendStats: ['med', 'p(95)', 'p(99)', 'max', 'count'],
};

function params(token, endpoint) {
  return {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    tags: { endpoint },
  };
}

// 200 + exactly {data, undated, next_cursor}; returns the parsed body or null.
function checkPage(res) {
  let body = null;
  try {
    body = res.json();
  } catch (_) {
    body = null;
  }
  const keys = body && typeof body === 'object' ? Object.keys(body).sort().join(',') : '';
  const ok = check(res, {
    'status 200': (r) => r.status === 200,
    'exact keys data/undated/next_cursor': () => keys === 'data,next_cursor,undated',
  });
  if (!ok) {
    contractViolations.add(1);
    return null;
  }
  return body;
}

export function subjectFlow() {
  const subject = subjects[(__VU - 1) % subjects.length];
  const seen = new Set();
  let cursor = null;

  for (let page = 0; page < 3; page++) {
    const endpoint = page === 0 ? 'history_first' : 'history_next';
    const query = cursor ? `limit=25&cursor=${encodeURIComponent(cursor)}` : 'limit=25';
    const body = checkPage(http.get(`${API}/progress/history?${query}`, params(subject.token, endpoint)));
    if (body === null) break;
    for (const event of body.data) {
      if (seen.has(event.id)) duplicateIds.add(1);
      seen.add(event.id);
    }
    cursor = body.next_cursor;
    if (!cursor) break;
  }

  if (__ITER % 5 === 0) {
    const res = http.get(`${API}/progress/panel`, params(subject.token, 'panel'));
    check(res, { 'panel status 200': (r) => r.status === 200 });
  }
}

export function mentorFlow() {
  const ids = fixtures.mentor.mentee_ids;
  if (!ids.length) fail('no mentee ids in fixtures');
  const id = ids[__ITER % ids.length];
  checkPage(http.get(`${API}/mentor/users/${id}/progress/history?limit=25`, params(fixtures.mentor.token, 'history_mentor')));
}

export function supportFlow() {
  const subject = subjects[__ITER % subjects.length];
  checkPage(http.get(`${API}/admin/progress/history?user_id=${subject.id}&limit=25`, params(fixtures.support.token, 'history_support')));
}

export function handleSummary(data) {
  return {
    '/bench-out/summary.json': JSON.stringify(data, null, 2),
    stdout: textSummaryLite(data),
  };
}

function textSummaryLite(data) {
  const lines = ['endpoint            count    p50(ms)   p95(ms)   p99(ms)   max(ms)'];
  for (const tag of ['history_first', 'history_next', 'history_mentor', 'history_support', 'panel']) {
    const m = data.metrics[`http_req_duration{endpoint:${tag}}`];
    if (!m) continue;
    const v = m.values;
    lines.push(
      `${tag.padEnd(18)} ${String(v.count).padStart(6)} ${v.med.toFixed(1).padStart(10)} ${v['p(95)'].toFixed(1).padStart(9)} ${v['p(99)'].toFixed(1).padStart(9)} ${v.max.toFixed(1).padStart(9)}`,
    );
  }
  const failed = data.metrics.http_req_failed;
  lines.push(`http_req_failed rate: ${failed ? failed.values.rate : 'n/a'}`);
  lines.push(`duplicate ids: ${data.metrics.history_duplicate_ids ? data.metrics.history_duplicate_ids.values.count : 0}`);
  lines.push(`contract violations: ${data.metrics.history_contract_violations ? data.metrics.history_contract_violations.values.count : 0}`);
  return lines.join('\n') + '\n';
}
