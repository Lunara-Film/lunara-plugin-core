'use strict';

// Shared by the seven Lunara website repositories; keep the copies identical.
// A push to main skips the full workflow only when the exact same tree already
// passed this workflow on a same-repository pull request. Any doubt runs it.

const { execFileSync } = require('node:child_process');
const { appendFileSync } = require('node:fs');

const objectId = /^(?:[0-9a-f]{40}|[0-9a-f]{64})$/;

function artifactName(tree) {
  if (!objectId.test(String(tree))) throw new Error('Invalid tree id.');
  return `ci-passed-tree-${tree}`;
}

async function findTestedRun({ api, repository, runId, tree }) {
  const name = artifactName(tree);
  const current = await api(`/repos/${repository}/actions/runs/${runId}`);
  const { artifacts = [] } = await api(`/repos/${repository}/actions/artifacts?name=${name}&per_page=100`);
  for (const artifact of artifacts) {
    const source = artifact?.workflow_run;
    // Fork pull requests can upload artifacts too; never trust them here.
    if (artifact?.name !== name || artifact.expired || !source?.id
      || source.head_repository_id !== source.repository_id) continue;
    const run = await api(`/repos/${repository}/actions/runs/${source.id}`);
    if (run?.event === 'pull_request' && run.status === 'completed' && run.conclusion === 'success'
      && run.workflow_id === current?.workflow_id && run.head_repository?.id === run.repository?.id) return run;
  }
  return undefined;
}

async function decide(env, api, cwd = process.cwd()) {
  const full = (reason) => ({ tested: false, reason });
  if (env.GITHUB_EVENT_NAME !== 'push') return full('Not a push to main; run the full workflow.');
  try {
    if (!objectId.test(String(env.GITHUB_SHA)) || !/^\d+$/.test(String(env.GITHUB_RUN_ID))
      || !/^[\w.-]+\/[\w.-]+$/.test(String(env.GITHUB_REPOSITORY))) throw new Error('Missing workflow context.');
    const tree = execFileSync('git', ['rev-parse', '--verify', `${env.GITHUB_SHA}^{tree}`], {
      cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'],
    }).trim();
    const run = await findTestedRun({ api, repository: env.GITHUB_REPOSITORY, runId: env.GITHUB_RUN_ID, tree });
    return run
      ? { tested: true, reason: `Tree ${tree} already passed this workflow on pull request run ${run.id}; full jobs skipped.` }
      : full(`No passing same-repository pull request run for tree ${tree}; run the full workflow.`);
  } catch {
    return full('Earlier result unavailable; run the full workflow.');
  }
}

function github(token, base = 'https://api.github.com') {
  return async (path) => {
    const response = await fetch(`${base}${path}`, {
      headers: { accept: 'application/vnd.github+json', authorization: `Bearer ${token}`, 'x-github-api-version': '2022-11-28' },
      signal: AbortSignal.timeout(15000),
    });
    if (!response.ok) throw new Error(`GitHub API returned ${response.status}.`);
    return response.json();
  };
}

if (require.main === module) {
  if (process.argv[2] !== 'check') throw new Error('Expected check command.');
  decide(process.env, github(process.env.GITHUB_TOKEN, process.env.GITHUB_API_URL || undefined)).then((result) => {
    appendFileSync(process.env.GITHUB_OUTPUT, `tested=${result.tested}\n`);
    console.log(result.reason);
    if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, `${result.reason}\n`);
  });
}

module.exports = { artifactName, decide, findTestedRun, github };
