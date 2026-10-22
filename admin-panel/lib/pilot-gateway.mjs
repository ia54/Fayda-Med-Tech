// Server-only transport. Laravel still owns login, MFA and all authorization.
const UPSTREAM = 'https://pilot-api.faydamed.tech';
const PREVIEW = 'https://z1izdz2qau.preview.c36.airoapp.ai';
const LIMIT = 20 * 1024 * 1024;
const methods = new Set(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE']);
const failure = (status) => new Response(JSON.stringify({ message: 'Pilot request unavailable.' }), {
  status, headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' },
});

async function boundedBody(stream, signal) {
  if (!stream) return null;
  const reader = stream.getReader();
  const cancel = () => { reader.cancel().catch(() => {}); };
  signal.addEventListener('abort', cancel, { once: true });
  const chunks = []; let size = 0;
  try {
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      size += value.byteLength;
      if (size > LIMIT) { await reader.cancel(); throw new RangeError('Body limit'); }
      chunks.push(value);
    }
  } finally { signal.removeEventListener('abort', cancel); reader.releaseLock(); }
  if (signal.aborted) throw new Error('Timeout');
  const bytes = new Uint8Array(size); let offset = 0;
  for (const chunk of chunks) { bytes.set(chunk, offset); offset += chunk.byteLength; }
  return bytes;
}

export async function pilotGateway(request, env = process.env, transport = fetch) {
  if (env.SYNTHETIC_PILOT_GATEWAY !== 'true' || !/^[a-f0-9]{64}$/.test(env.PILOT_GATEWAY_KEY || '')) return failure(503);
  const url = new URL(request.url);
  if (!methods.has(request.method)) return failure(405);
  if (!/^\/api\/[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*\/?$/.test(url.pathname) || url.search.length > 8192) return failure(400);
  const origin = request.headers.get('origin');
  if ((origin && origin !== PREVIEW) || (!['GET', 'HEAD'].includes(request.method) && origin !== PREVIEW)) return failure(403);
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 15000);
  const abort = () => controller.abort();
  request.signal.addEventListener('abort', abort, { once: true });
  try {
    const headers = new Headers({ 'X-Fayda-Pilot-Key': env.PILOT_GATEWAY_KEY, Accept: 'application/json' });
    for (const name of ['authorization', 'content-type', 'accept']) {
      const value = request.headers.get(name);
      if (value) headers.set(name, value);
    }
    const body = await boundedBody(request.body, controller.signal);
    if (controller.signal.aborted) return failure(504);
    const response = await transport(UPSTREAM + url.pathname + url.search, {
      method: request.method, headers, body, redirect: 'manual', cache: 'no-store', signal: controller.signal,
    });
    if (response.status >= 300 && response.status < 400) { await response.body?.cancel(); return failure(502); }
    const output = await boundedBody(response.body, controller.signal);
    const resultHeaders = new Headers({ 'Cache-Control': 'no-store', 'X-Content-Type-Options': 'nosniff' });
    for (const name of ['content-type', 'content-disposition', 'retry-after']) {
      const value = response.headers.get(name);
      if (value) resultHeaders.set(name, value);
    }
    return new Response(request.method === 'HEAD' || [204, 205, 304].includes(response.status) ? null : output, {
      status: response.status, headers: resultHeaders,
    });
  } catch (error) {
    return failure(error instanceof RangeError ? 413 : controller.signal.aborted ? 504 : 502);
  } finally { clearTimeout(timer); request.signal.removeEventListener('abort', abort); }
}
