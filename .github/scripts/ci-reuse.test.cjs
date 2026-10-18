'use strict';

const assert = require('node:assert/strict');
const { execFileSync, spawn } = require('node:child_process');
const { mkdtempSync, readFileSync, realpathSync, rmSync, writeFileSync } = require('node:fs');
const { createServer } = require('node:http');
const { tmpdir } = require('node:os');
const { dirname, join } = require('node:path');
const { test } = require('node:test');
const { artifactName, decide, findTestedRun } = require('./ci-reuse.cjs');

const repository = 'Lunara-Film/example';

function checkout(t) {
  const tempRoot = realpathSync(tmpdir());
  const cwd = mkdtempSync(join(tempRoot, 'lunara-ci-reuse-'));
  t.after(() => {
    assert.equal(dirname(realpathSync(cwd)), tempRoot);
    rmSync(cwd, { recursive: true, force: true });
  });
  const git = (...args) => execFileSync('git', args, { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
  git('init', '-q', '-b', 'main');
  git('config', 'user.email', 'ci-test@example.invalid');
  git('config', 'user.name', 'CI test');
  git('config', 'commit.gpgsign', 'false');
  writeFileSync(join(cwd, 'plugin.php'), '<?php echo "merged";\n');
  git('add', '--all');
  git('commit', '-qm', 'Merged tree');
  return { cwd, sha: git('rev-parse', 'HEAD'), tree: git('rev-parse', 'HEAD^{tree}') };
}

// A same-repository PR run of workflow 7 that passed on `tree`, plus the current main run 900.
function fixtures(tree, { artifact = {}, source = {}, run = {} } = {}) {
  const repo = { id: 1, full_name: repository };
  return {
    [`/repos/${repository}/actions/runs/900`]: { id: 900, event: 'push', workflow_id: 7 },
    [`/repos/${repository}/actions/artifacts?name=ci-passed-tree-${tree}&per_page=100`]: {
      artifacts: [{ name: `ci-passed-tree-${tree}`, expired: false,
        workflow_run: { id: 42, repository_id: 1, head_repository_id: 1, ...source }, ...artifact }],
    },
    [`/repos/${repository}/actions/runs/42`]: { id: 42, event: 'pull_request', status: 'completed',
      conclusion: 'success', workflow_id: 7, repository: repo, head_repository: repo, ...run },
  };
}

const fakeApi = (responses, calls = []) => async (path) => {
  calls.push(path);
  if (!Object.hasOwn(responses, path)) throw new Error(`Unexpected ${path}`);
  return responses[path];
};
const pushEnv = (sha) => ({ GITHUB_EVENT_NAME: 'push', GITHUB_SHA: sha, GITHUB_RUN_ID: '900', GITHUB_REPOSITORY: repository });

test('a push reuses a passing same-repository PR run of the identical tree', async (t) => {
  const c = checkout(t);
  const result = await decide(pushEnv(c.sha), fakeApi(fixtures(c.tree)), c.cwd);
  assert.equal(result.tested, true);
  assert.match(result.reason, /pull request run 42/);
});

test('pull requests and other events always run the full workflow without API calls', async () => {
  const calls = [];
  for (const event of ['pull_request', 'workflow_dispatch', undefined]) {
    assert.equal((await decide({ GITHUB_EVENT_NAME: event }, fakeApi({}, calls))).tested, false);
  }
  assert.deepEqual(calls, []);
});

test('fork, expired, failed, unfinished, push and other-workflow results are never reused', async (t) => {
  const c = checkout(t);
  for (const change of [
    { source: { head_repository_id: 2 } }, { run: { head_repository: { id: 2 } } },
    { artifact: { expired: true } }, { artifact: { name: 'ci-passed-tree-other' } }, { source: { id: undefined } },
    { run: { conclusion: 'failure' } }, { run: { conclusion: null, status: 'in_progress' } },
    { run: { event: 'push' } }, { run: { workflow_id: 8 } },
  ]) {
    assert.equal((await decide(pushEnv(c.sha), fakeApi(fixtures(c.tree, change)), c.cwd)).tested, false, JSON.stringify(change));
  }
});

test('a different tree, missing context or unavailable API runs the full workflow', async (t) => {
  const c = checkout(t);
  const other = 'a'.repeat(40);
  assert.equal((await decide(pushEnv(c.sha), fakeApi(fixtures(other)), c.cwd)).tested, false);
  assert.equal((await decide(pushEnv('f'.repeat(40)), fakeApi(fixtures(c.tree)), c.cwd)).tested, false);
  for (const env of [{ GITHUB_SHA: '--help' }, { GITHUB_RUN_ID: '9 0' }, { GITHUB_REPOSITORY: '../x?y' }, { GITHUB_SHA: undefined }]) {
    assert.equal((await decide({ ...pushEnv(c.sha), ...env }, fakeApi(fixtures(c.tree)), c.cwd)).tested, false, JSON.stringify(env));
  }
  const failing = async () => { throw new Error('GitHub API returned 403.'); };
  assert.equal((await decide(pushEnv(c.sha), failing, c.cwd)).tested, false);
  assert.equal(await findTestedRun({ api: fakeApi({ ...fixtures(c.tree),
    [`/repos/${repository}/actions/artifacts?name=ci-passed-tree-${c.tree}&per_page=100`]: {} }), repository, runId: 900, tree: c.tree }), undefined);
});

test('artifact names accept only Git object ids', () => {
  assert.equal(artifactName('b'.repeat(40)), `ci-passed-tree-${'b'.repeat(40)}`);
  assert.equal(artifactName('c'.repeat(64)), `ci-passed-tree-${'c'.repeat(64)}`);
  for (const tree of ['', 'B'.repeat(40), 'b'.repeat(39), `${'b'.repeat(40)}&per_page=1`, undefined]) {
    assert.throws(() => artifactName(tree), String(tree));
  }
});

test('CLI asks the GitHub API with the token and writes the Actions output', async (t) => {
  const c = checkout(t);
  const responses = fixtures(c.tree);
  const seen = [];
  const server = createServer((request, response) => {
    seen.push([request.url, request.headers.authorization]);
    const body = responses[request.url];
    response.writeHead(body ? 200 : 404, { 'content-type': 'application/json' });
    response.end(JSON.stringify(body ?? { message: 'Not Found' }));
  });
  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  t.after(() => server.close());
  const output = join(c.cwd, 'output.txt');
  const run = (extra) => new Promise((resolve) => {
    writeFileSync(output, '');
    const child = spawn(process.execPath, [join(__dirname, 'ci-reuse.cjs'), 'check'], {
      cwd: c.cwd, stdio: ['ignore', 'pipe', 'pipe'],
      env: { ...process.env, ...pushEnv(c.sha), GITHUB_TOKEN: 'test-token', GITHUB_OUTPUT: output,
        GITHUB_STEP_SUMMARY: '', GITHUB_API_URL: `http://127.0.0.1:${server.address().port}`, ...extra },
    });
    child.on('close', (status) => resolve({ status, output: readFileSync(output, 'utf8') }));
  });
  assert.deepEqual(await run({}), { status: 0, output: 'tested=true\n' });
  assert.ok(seen.length > 0 && seen.every(([, auth]) => auth === 'Bearer test-token'));
  assert.deepEqual(await run({ GITHUB_RUN_ID: '901' }), { status: 0, output: 'tested=false\n' });
  assert.deepEqual(await run({ GITHUB_EVENT_NAME: 'pull_request' }), { status: 0, output: 'tested=false\n' });
});
