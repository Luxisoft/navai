import test from 'node:test';
import assert from 'node:assert/strict';
import { createRealtimeClientSecret, createExpressClientSecretHandler } from '../packages/voice-backend/dist/index.js';
import { createNavaiBackendClient, buildNavaiAgent } from '../packages/voice-frontend/dist/index.js';
import { createNavaiMobileBackendClient } from '../packages/voice-mobile/src/backend.ts';
import { createNavaiMobileVoiceSession } from '../packages/voice-mobile/src/session.ts';
import { createRequire } from 'node:module';
const { RunContext } = createRequire(new URL('../packages/voice-frontend/package.json', import.meta.url))('@openai/agents');

const options = { openaiApiKey: 'test-server-key', defaultModel: 'gpt-realtime' };
const secret = { value: 'test-ephemeral', expires_at: 2000000000, session: { model: 'gpt-realtime' } };

async function withFetch(implementation: typeof fetch, run: () => Promise<void>) {
  const previous = globalThis.fetch;
  globalThis.fetch = implementation;
  try { await run(); } finally { globalThis.fetch = previous; }
}

test('backend uses the GA session schema and returns the effective model', async () => {
  await withFetch(async (url, init) => {
    assert.equal(url, 'https://api.openai.com/v1/realtime/client_secrets');
    assert.equal(init?.method, 'POST');
    const headers = new Headers(init?.headers);
    assert.equal(headers.get('authorization'), 'Bearer test-server-key');
    assert.equal(headers.has('OpenAI-Beta'), false);
    assert.ok(init?.signal instanceof AbortSignal);
    const body = JSON.parse(String(init?.body));
    assert.deepEqual(body.expires_after, { anchor: 'created_at', seconds: 600 });
    assert.equal(body.session.type, 'realtime');
    assert.equal(body.session.model, 'gpt-realtime');
    assert.deepEqual(body.session.audio.output, { voice: 'marin' });
    assert.match(body.session.instructions, /Always reply in Spanish/);
    return Response.json({ ...secret, session: { model: 'server-resolved-model' } });
  }, async () => {
    const result = await createRealtimeClientSecret(options, { language: 'Spanish' });
    assert.equal(result.model, 'server-resolved-model');
    assert.equal(result.value, secret.value);
  });
});

test('invalid client secret input and policy violations never reach OpenAI', async () => {
  let calls = 0;
  await withFetch(async () => { calls++; throw new Error('Unexpected fetch'); }, async () => {
    for (const input of [null, [], 'invalid', { model: 1 }, { voice: '' }, { instructions: 'x'.repeat(32001) }]) {
      await assert.rejects(createRealtimeClientSecret(options, input as any));
    }
    for (const ttl of [NaN, Infinity, 9, 7201, 10.5]) {
      await assert.rejects(createRealtimeClientSecret({ ...options, clientSecretTtlSeconds: ttl }));
    }
    for (const timeout of [NaN, 0, 499, 120001, 500.5]) {
      await assert.rejects(createRealtimeClientSecret({ ...options, requestTimeoutMs: timeout }));
    }
    await assert.rejects(createRealtimeClientSecret({ ...options, allowedModels: ['approved'] }, { model: 'other' }), /not allowed/);
    await assert.rejects(createRealtimeClientSecret({ allowApiKeyFromRequest: false }, { apiKey: 'browser-key' }), /Missing openaiApiKey/);
  });
  assert.equal(calls, 0);
});

test('backend key takes precedence over a request key', async () => {
  await withFetch(async (_url, init) => {
    assert.equal(new Headers(init?.headers).get('authorization'), 'Bearer test-server-key');
    return Response.json(secret);
  }, async () => { await createRealtimeClientSecret(options, { apiKey: 'ignored-request-key' }); });
});

test('explicit client key mode still works and allowed model is enforced', async () => {
  await withFetch(async (_url, init) => {
    assert.equal(new Headers(init?.headers).get('authorization'), 'Bearer browser-key');
    return Response.json(secret);
  }, async () => {
    await createRealtimeClientSecret({ allowApiKeyFromRequest: true, allowedModels: ['gpt-realtime'] }, { apiKey: 'browser-key' });
  });
});

test('backend rejects malformed provider success and keeps provider error bodies private', async () => {
  for (const payload of [null, {}, { value: '', expires_at: 1 }, { value: 'valid', expires_at: '1' }]) {
    await withFetch(async () => Response.json(payload), async () => {
      await assert.rejects(createRealtimeClientSecret(options), /Invalid OpenAI/);
    });
  }
  await withFetch(async () => new Response('PRIVATE_PROVIDER_DETAILS', { status: 401 }), async () => {
    await assert.rejects(createRealtimeClientSecret(options), (error: any) => {
      assert.match(error.message, /HTTP 401/);
      assert.doesNotMatch(error.message, /PRIVATE_PROVIDER_DETAILS/);
      return true;
    });
  });
});

test('backend aborts stalled provider requests within the configured deadline', async () => {
  await withFetch(async (_url, init) => new Promise((_resolve, reject) => {
    init?.signal?.addEventListener('abort', () => reject(init.signal?.reason), { once: true });
  }), async () => {
    const keepAlive = setTimeout(() => {}, 1500);
    try { await assert.rejects(createRealtimeClientSecret({ ...options, requestTimeoutMs: 500 }), { name: 'TimeoutError' }); }
    finally { clearTimeout(keepAlive); }
  });
});

test('Express client-secret response is not cacheable and does not expose session configuration', async () => {
  await withFetch(async () => Response.json(secret), async () => {
    const headers: Record<string, string> = {};
    let payload: any;
    await createExpressClientSecretHandler(options)({ body: {} } as any, {
      setHeader: (key: string, value: string) => { headers[key] = value; },
      json: (data: any) => { payload = data; },
    } as any, error => { throw error; });
    assert.equal(headers['Cache-Control'], 'no-store');
    assert.equal(payload.model, 'gpt-realtime');
    assert.equal('session' in payload, false);
    assert.equal('openaiApiKey' in payload, false);
  });
});

for (const [name, factory] of [['web', createNavaiBackendClient], ['mobile', createNavaiMobileBackendClient]] as const) {
  test(`${name} client supports new and older backend response formats`, async () => {
    for (const [payload, expected] of [
      [{ ...secret, model: 'explicit-model' }, 'explicit-model'],
      [secret, 'gpt-realtime'],
      [{ value: secret.value }, undefined],
    ] as const) {
      const client = factory({ apiBaseUrl: 'https://api.example.test', fetchImpl: async () => Response.json(payload) });
      assert.equal((await client.createClientSecret()).model, expected);
    }
    const client = factory({ fetchImpl: async () => Response.json({ value: '  ' }) });
    await assert.rejects(client.createClientSecret(), /Invalid client-secret/);
  });
}

test('mobile session propagates server-selected model, supports legacy fallback and shuts down', async () => {
  for (const model of ['server-model', undefined]) {
    let connected: any;
    let stopped = false;
    const session = createNavaiMobileVoiceSession({
      backendClient: createNavaiMobileBackendClient({ fetchImpl: async () => Response.json({ value: secret.value, model }) }),
      transport: {
        connect: async input => { connected = input; },
        disconnect: async () => { stopped = true; },
      },
    });
    await session.start({ model: 'requested-model', preloadBackendFunctions: false });
    assert.equal(connected.model, model ?? 'requested-model');
    assert.equal(connected.clientSecret, secret.value);
    assert.equal(session.getSnapshot().state, 'connected');
    await assert.rejects(session.start(), /already connected/);
    await session.stop();
    assert.equal(stopped, true);
    assert.equal(session.getSnapshot().state, 'idle');
  }
});

test('Realtime SDK accepts arbitrary payload schemas without bypassing function or route permissions', async () => {
  const calls: unknown[] = [];
  const navigations: string[] = [];
  const { agent, warnings } = await buildNavaiAgent({
    navigate: path => { navigations.push(path); },
    routes: [{ name: 'home', path: '/', description: 'Home' }],
    backendFunctions: [{ name: 'read_status', description: 'Read status' }],
    executeBackendFunction: input => { calls.push(input); return { ready: true }; },
  });
  assert.deepEqual(warnings, []);
  const context = new RunContext({});
  const invoke = async (name: string, args: unknown) => {
    const selected = agent.tools.find(tool => tool.type === 'function' && tool.name === name);
    assert.ok(selected && selected.type === 'function');
    const result = await selected.invoke(context, JSON.stringify(args));
    return typeof result === 'string' ? JSON.parse(result) : result;
  };
  const payload = { args: ['safe', { nested: [1, true] }] };
  assert.equal((await invoke('read_status', { payload })).ok, true);
  assert.equal((await invoke('execute_app_function', { function_name: 'read_status', payload: null })).ok, true);
  assert.equal((await invoke('execute_app_function', { function_name: 'unknown_action', payload: null })).ok, false);
  assert.equal((await invoke('navigate_to', { target: 'https://untrusted.example' })).ok, false);
  assert.equal((await invoke('navigate_to', { target: '/' })).ok, true);
  assert.deepEqual(navigations, ['/']);
  assert.deepEqual(calls, [{ functionName: 'read_status', payload }, { functionName: 'read_status', payload: null }]);
});
