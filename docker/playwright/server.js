const express = require('express');
const dns = require('dns').promises;
const net = require('net');
const { chromium } = require('playwright');
const { PNG } = require('pngjs');

const app = express();
app.use(express.json({ limit: '256kb' }));

const port = Number.parseInt(process.env.PORT || '3000', 10);
const maxConcurrent = Number.parseInt(process.env.MAX_CONCURRENT || '2', 10);
const maxHtmlBytesDefault = Number.parseInt(process.env.MAX_HTML_BYTES || '2000000', 10);

let active = 0;
let browserPromise = null;

const allowedPortsEnv = String(process.env.ALLOWED_PORTS || '80,443');
const allowedPorts = new Set(
  allowedPortsEnv
    .split(',')
    .map((v) => Number.parseInt(v.trim(), 10))
    .filter((n) => Number.isFinite(n) && n > 0 && n <= 65535)
);

const hostCacheTtlMs = Number.parseInt(process.env.HOST_CACHE_TTL_MS || '0', 10);
const hostCache = new Map();

function isPrivateIpv4(ip) {
  const parts = ip.split('.').map((p) => Number.parseInt(p, 10));
  if (parts.length !== 4 || parts.some((p) => !Number.isFinite(p) || p < 0 || p > 255)) return true;

  const [a, b] = parts;

  if (a === 10) return true;
  if (a === 127) return true;
  if (a === 0) return true;
  if (a === 169 && b === 254) return true;
  if (a === 172 && b >= 16 && b <= 31) return true;
  if (a === 192 && b === 168) return true;
  if (a === 100 && b >= 64 && b <= 127) return true;
  if (a === 192 && b === 0) return true;
  if (a === 198 && (b === 18 || b === 19)) return true;
  if (a >= 224) return true;

  return false;
}

function isPrivateIpv6(ip) {
  const normalized = ip.toLowerCase();

  if (normalized === '::1') return true;
  if (normalized.startsWith('fe80:')) return true;
  if (normalized.startsWith('fc') || normalized.startsWith('fd')) return true;

  return false;
}

function isPublicIp(ip) {
  const version = net.isIP(ip);
  if (version === 4) return !isPrivateIpv4(ip) && ip !== '169.254.169.254';
  if (version === 6) return !isPrivateIpv6(ip);
  return false;
}

function hasAddressIntersection(left, right) {
  const rightSet = new Set(right);
  return left.some((value) => rightSet.has(value));
}

async function resolveHostAddresses(host, useCache) {
  const now = Date.now();

  if (useCache) {
    const cached = hostCache.get(host);
    if (cached && cached.expiresAt > now) {
      return cached.addresses;
    }
  }

  let addresses = [];

  if (net.isIP(host)) {
    addresses = [host];
  } else {
    const records = await dns.lookup(host, { all: true, verbatim: true });
    addresses = Array.isArray(records)
      ? records
          .map((record) => String(record?.address || '').trim())
          .filter((address) => address.length > 0)
      : [];
  }

  addresses = Array.from(new Set(addresses));

  if (useCache) {
    hostCache.set(host, {
      addresses,
      expiresAt: now + hostCacheTtlMs,
    });
  }

  return addresses;
}

async function isHostPublic(hostname, dnsDriftState = null) {
  const host = String(hostname || '').toLowerCase();
  if (!host) return false;

  if (host === 'localhost' || host.endsWith('.localhost') || host.endsWith('.local')) return false;
  if (host === 'metadata.google.internal' || host === 'metadata') return false;

  const useCache =
    dnsDriftState === null &&
    Number.isFinite(hostCacheTtlMs) &&
    hostCacheTtlMs > 0;

  let addresses = [];

  try {
    addresses = await resolveHostAddresses(host, useCache);
  } catch {
    return false;
  }

  if (!Array.isArray(addresses) || addresses.length === 0) {
    return false;
  }

  const isPublic = addresses.every((address) => isPublicIp(address));
  if (!isPublic) {
    return false;
  }

  if (dnsDriftState && !net.isIP(host)) {
    const previous = dnsDriftState.get(host);

    if (Array.isArray(previous) && previous.length > 0 && !hasAddressIntersection(previous, addresses)) {
      return false;
    }

    dnsDriftState.set(host, addresses);
  }

  return true;
}

async function isSafeUrl(urlString, dnsDriftState = null) {
  try {
    const url = new URL(urlString);
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return false;
    if (url.username || url.password) return false;

    const port = url.port ? Number.parseInt(url.port, 10) : url.protocol === 'https:' ? 443 : 80;
    if (allowedPorts.size > 0 && !allowedPorts.has(port)) return false;

    const hostOk = await isHostPublic(url.hostname, dnsDriftState);
    return hostOk;
  } catch {
    return false;
  }
}

async function getBrowser() {
  if (!browserPromise) {
    browserPromise = chromium.launch({
      headless: true,
      args: ['--no-sandbox'],
    });
  }

  return browserPromise;
}

function computeDHashHex(pngBuffer) {
  const png = PNG.sync.read(pngBuffer);

  const { width, height, data } = png;

  if (!width || !height) {
    throw new Error('Invalid PNG dimensions.');
  }

  const sampleCols = 9;
  const sampleRows = 8;
  const gray = Array.from({ length: sampleRows }, () => new Array(sampleCols).fill(0));

  for (let y = 0; y < sampleRows; y++) {
    const srcY = Math.min(height - 1, Math.floor(((y + 0.5) * height) / sampleRows));

    for (let x = 0; x < sampleCols; x++) {
      const srcX = Math.min(width - 1, Math.floor(((x + 0.5) * width) / sampleCols));
      const idx = (srcY * width + srcX) * 4;
      const r = data[idx] ?? 0;
      const g = data[idx + 1] ?? 0;
      const b = data[idx + 2] ?? 0;
      gray[y][x] = 0.299 * r + 0.587 * g + 0.114 * b;
    }
  }

  const bits = [];
  for (let y = 0; y < sampleRows; y++) {
    for (let x = 0; x < sampleCols - 1; x++) {
      bits.push(gray[y][x] > gray[y][x + 1] ? 1 : 0);
    }
  }

  let hex = '';
  for (let i = 0; i < bits.length; i += 4) {
    const nibble =
      (bits[i] << 3) |
      (bits[i + 1] << 2) |
      (bits[i + 2] << 1) |
      (bits[i + 3] << 0);
    hex += nibble.toString(16);
  }

  return hex;
}

function truncateUtf8(str, maxBytes) {
  if (typeof str !== 'string') return { value: '', truncated: false };
  if (!Number.isFinite(maxBytes) || maxBytes <= 0) return { value: str, truncated: false };

  const total = Buffer.byteLength(str, 'utf8');
  if (total <= maxBytes) return { value: str, truncated: false };

  // Best-effort truncation by characters; ensure we're under `maxBytes`.
  let end = Math.max(0, Math.floor((str.length * maxBytes) / total));
  let out = str.slice(0, end);

  while (end > 0 && Buffer.byteLength(out, 'utf8') > maxBytes) {
    end -= Math.max(1, Math.floor(end * 0.05));
    out = str.slice(0, end);
  }

  return { value: out, truncated: true };
}

app.get('/health', (req, res) => {
  res.json({ ok: true, active });
});

app.post('/screenshot', async (req, res) => {
  if (active >= maxConcurrent) {
    return res.status(429).json({ message: 'Too many concurrent requests.' });
  }

  const url = req.body?.url;
  const viewport = req.body?.viewport ?? {};
  const fullPage = !!req.body?.fullPage;
  const waitUntil = req.body?.waitUntil || 'domcontentloaded';
  const timeoutMs = Number.parseInt(req.body?.timeoutMs ?? '30000', 10);

  const width = Number.parseInt(viewport.width ?? '1280', 10);
  const height = Number.parseInt(viewport.height ?? '720', 10);

  if (typeof url !== 'string' || url.length > 4096) {
    return res.status(422).json({ message: 'Invalid url.' });
  }

  const dnsDriftState = new Map();

  if (!(await isSafeUrl(url, dnsDriftState))) {
    return res.status(422).json({ message: 'Unsafe url.' });
  }

  if (!Number.isFinite(timeoutMs) || timeoutMs < 1000 || timeoutMs > 120000) {
    return res.status(422).json({ message: 'Invalid timeoutMs.' });
  }

  if (!Number.isFinite(width) || width < 200 || width > 3840 || !Number.isFinite(height) || height < 200 || height > 2160) {
    return res.status(422).json({ message: 'Invalid viewport.' });
  }

  if (!['load', 'domcontentloaded', 'networkidle', 'commit'].includes(waitUntil)) {
    return res.status(422).json({ message: 'Invalid waitUntil.' });
  }

  active += 1;

  let context = null;

  try {
    const browser = await getBrowser();

    context = await browser.newContext({
      viewport: { width, height },
    });

    const page = await context.newPage();

    const consoleErrors = [];
    const pageErrors = [];
    const requestFailed = [];

    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push({ type: msg.type(), text: msg.text() });
      }
    });

    page.on('pageerror', (err) => {
      pageErrors.push({ message: err?.message || String(err) });
    });

    page.on('requestfailed', (req) => {
      const failure = req.failure();
      requestFailed.push({
        url: req.url(),
        method: req.method(),
        resourceType: req.resourceType(),
        errorText: failure?.errorText,
      });
    });

    await page.route('**/*', async (route) => {
      const requestUrl = route.request().url();
      if (!(await isSafeUrl(requestUrl, dnsDriftState))) {
        return route.abort();
      }
      return route.continue();
    });

    await page.goto(url, { waitUntil, timeout: timeoutMs });

    const finalUrl = page.url();

    const pngBuffer = await page.screenshot({
      type: 'png',
      fullPage,
    });

    const phash = computeDHashHex(pngBuffer);

    return res.json({
      url,
      final_url: finalUrl,
      bytes: pngBuffer.length,
      phash,
      png_base64: pngBuffer.toString('base64'),
      console_errors: consoleErrors,
      page_errors: pageErrors,
      request_failed: requestFailed,
    });
  } catch (err) {
    return res.status(500).json({ message: err instanceof Error ? err.message : 'Unknown error.' });
  } finally {
    active -= 1;
    if (context) {
      try {
        await context.close();
      } catch {
      }
    }
  }
});

app.post('/journey', async (req, res) => {
  if (active >= maxConcurrent) {
    return res.status(429).json({ message: 'Too many concurrent requests.' });
  }

  const url = req.body?.url;
  const steps = Array.isArray(req.body?.steps) ? req.body.steps : [];
  const viewport = req.body?.viewport ?? {};
  const fullPage = !!req.body?.fullPage;
  const waitUntil = req.body?.waitUntil || 'domcontentloaded';
  const timeoutMs = Number.parseInt(req.body?.timeoutMs ?? '30000', 10);
  const maxHtmlBytes = Number.parseInt(req.body?.maxHtmlBytes ?? String(maxHtmlBytesDefault), 10);

  const width = Number.parseInt(viewport.width ?? '1280', 10);
  const height = Number.parseInt(viewport.height ?? '720', 10);

  if (typeof url !== 'string' || url.length > 4096) {
    return res.status(422).json({ message: 'Invalid url.' });
  }

  if (!Array.isArray(steps) || steps.length > 30) {
    return res.status(422).json({ message: 'Invalid steps.' });
  }

  const dnsDriftState = new Map();

  if (!(await isSafeUrl(url, dnsDriftState))) {
    return res.status(422).json({ message: 'Unsafe url.' });
  }

  if (!Number.isFinite(timeoutMs) || timeoutMs < 1000 || timeoutMs > 180000) {
    return res.status(422).json({ message: 'Invalid timeoutMs.' });
  }

  if (!Number.isFinite(width) || width < 200 || width > 3840 || !Number.isFinite(height) || height < 200 || height > 2160) {
    return res.status(422).json({ message: 'Invalid viewport.' });
  }

  if (!['load', 'domcontentloaded', 'networkidle', 'commit'].includes(waitUntil)) {
    return res.status(422).json({ message: 'Invalid waitUntil.' });
  }

  active += 1;

  let context = null;

  try {
    const browser = await getBrowser();

    context = await browser.newContext({
      viewport: { width, height },
    });

    const page = await context.newPage();

    const consoleErrors = [];
    const pageErrors = [];
    const requestFailed = [];

    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push({ type: msg.type(), text: msg.text() });
      }
    });

    page.on('pageerror', (err) => {
      pageErrors.push({ message: err?.message || String(err) });
    });

    page.on('requestfailed', (req) => {
      const failure = req.failure();
      requestFailed.push({
        url: req.url(),
        method: req.method(),
        resourceType: req.resourceType(),
        errorText: failure?.errorText,
      });
    });

    await page.route('**/*', async (route) => {
      const requestUrl = route.request().url();
      if (!(await isSafeUrl(requestUrl, dnsDriftState))) {
        return route.abort();
      }
      return route.continue();
    });

    const startedAt = Date.now();
    const stepLog = [];
    let ok = true;
    let journeyError = null;

    let lastNavigation = null;

    const effectiveSteps = steps.length > 0 ? steps : [{ action: 'goto', url }];
    const first = effectiveSteps[0];
    const firstAction = typeof first?.action === 'string' ? first.action.toLowerCase() : '';
    const stepsWithGoto = firstAction === 'goto' ? effectiveSteps : [{ action: 'goto', url }, ...effectiveSteps];

    for (const rawStep of stepsWithGoto) {
      const action = typeof rawStep?.action === 'string' ? rawStep.action.toLowerCase().trim() : '';

      const startedStepAt = Date.now();
      const entry = { action, ok: true };

      try {
        if (action === 'goto') {
          const stepUrl = typeof rawStep?.url === 'string' ? rawStep.url : url;
          if (!(await isSafeUrl(stepUrl, dnsDriftState))) throw new Error('Unsafe url.');

          const stepWaitUntil = typeof rawStep?.waitUntil === 'string' ? rawStep.waitUntil : waitUntil;
          const stepTimeoutMs = Number.parseInt(rawStep?.timeoutMs ?? String(timeoutMs), 10);

          const response = await page.goto(stepUrl, { waitUntil: stepWaitUntil, timeout: stepTimeoutMs });

          if (response) {
            lastNavigation = {
              url: response.url(),
              status: response.status(),
              headers: response.headers(),
            };
          }
        } else if (action === 'click') {
          const selector = typeof rawStep?.selector === 'string' ? rawStep.selector : '';
          if (!selector) throw new Error('Missing selector.');

          const stepTimeoutMs = Number.parseInt(rawStep?.timeoutMs ?? String(timeoutMs), 10);
          const nav = !!rawStep?.waitForNavigation;

          if (nav) {
            const stepWaitUntil = typeof rawStep?.waitUntil === 'string' ? rawStep.waitUntil : waitUntil;
            const [response] = await Promise.all([
              page.waitForNavigation({ waitUntil: stepWaitUntil, timeout: stepTimeoutMs }),
              page.click(selector, { timeout: stepTimeoutMs }),
            ]);
            if (response) {
              lastNavigation = {
                url: response.url(),
                status: response.status(),
                headers: response.headers(),
              };
            }
          } else {
            await page.click(selector, { timeout: stepTimeoutMs });
          }

          entry.selector = selector;
        } else if (action === 'fill') {
          const selector = typeof rawStep?.selector === 'string' ? rawStep.selector : '';
          const value = typeof rawStep?.value === 'string' ? rawStep.value : '';
          if (!selector) throw new Error('Missing selector.');

          const stepTimeoutMs = Number.parseInt(rawStep?.timeoutMs ?? String(timeoutMs), 10);
          await page.fill(selector, value, { timeout: stepTimeoutMs });

          entry.selector = selector;
          entry.redacted = true;
        } else if (action === 'wait') {
          const ms = Number.parseInt(rawStep?.ms ?? '0', 10);
          if (!Number.isFinite(ms) || ms < 0 || ms > 60000) throw new Error('Invalid ms.');
          await page.waitForTimeout(ms);
        } else if (action === 'waitforselector' || action === 'wait_for_selector') {
          const selector = typeof rawStep?.selector === 'string' ? rawStep.selector : '';
          if (!selector) throw new Error('Missing selector.');

          const state = typeof rawStep?.state === 'string' ? rawStep.state : 'visible';
          const stepTimeoutMs = Number.parseInt(rawStep?.timeoutMs ?? String(timeoutMs), 10);
          await page.waitForSelector(selector, { timeout: stepTimeoutMs, state });

          entry.selector = selector;
          entry.state = state;
        } else if (action === 'asserttext' || action === 'assert_text') {
          const text = typeof rawStep?.text === 'string' ? rawStep.text : '';
          if (!text) throw new Error('Missing text.');

          const bodyText = await page.evaluate(() => (document.body ? document.body.innerText : ''));
          if (typeof bodyText !== 'string' || !bodyText.includes(text)) {
            throw new Error('Text not found.');
          }

          entry.text = text.length > 200 ? text.slice(0, 200) + '...' : text;
        } else if (action === 'assertselector' || action === 'assert_selector') {
          const selector = typeof rawStep?.selector === 'string' ? rawStep.selector : '';
          if (!selector) throw new Error('Missing selector.');

          const stepTimeoutMs = Number.parseInt(rawStep?.timeoutMs ?? String(timeoutMs), 10);
          await page.waitForSelector(selector, { timeout: stepTimeoutMs, state: 'attached' });

          entry.selector = selector;
        } else if (action === 'asserturlcontains' || action === 'assert_url_contains') {
          const text = typeof rawStep?.text === 'string' ? rawStep.text : '';
          if (!text) throw new Error('Missing text.');

          const currentUrl = page.url();
          if (!currentUrl.includes(text)) {
            throw new Error('URL does not contain text.');
          }

          entry.text = text;
        } else {
          throw new Error('Unsupported action.');
        }
      } catch (err) {
        ok = false;
        entry.ok = false;
        entry.error = err instanceof Error ? err.message : 'Unknown error.';
        journeyError = entry.error;
      } finally {
        entry.duration_ms = Date.now() - startedStepAt;
        stepLog.push(entry);
      }

      if (!ok) break;
    }

    const finalUrl = page.url();

    let html = '';
    let htmlTruncated = false;

    try {
      const rawHtml = await page.content();
      const truncated = truncateUtf8(rawHtml, maxHtmlBytes);
      html = truncated.value;
      htmlTruncated = truncated.truncated;
    } catch {
      html = '';
      htmlTruncated = false;
    }

    const pngBuffer = await page.screenshot({
      type: 'png',
      fullPage,
    });

    const phash = computeDHashHex(pngBuffer);

    const durationMs = Date.now() - startedAt;

    return res.json({
      ok,
      url,
      final_url: finalUrl,
      http_status: lastNavigation?.status ?? null,
      headers: lastNavigation?.headers ?? null,
      html,
      html_truncated: htmlTruncated,
      bytes: pngBuffer.length,
      phash,
      png_base64: pngBuffer.toString('base64'),
      console_errors: consoleErrors,
      page_errors: pageErrors,
      request_failed: requestFailed,
      journey: {
        ok,
        error: journeyError,
        steps: stepLog,
        duration_ms: durationMs,
      },
    });
  } catch (err) {
    return res.status(500).json({ message: err instanceof Error ? err.message : 'Unknown error.' });
  } finally {
    active -= 1;
    if (context) {
      try {
        await context.close();
      } catch {
      }
    }
  }
});

const server = app.listen(port, '0.0.0.0', () => {
  console.log(`Playwright service listening on :${port} (maxConcurrent=${maxConcurrent})`);
});

async function shutdown() {
  server.close(() => {});

  if (browserPromise) {
    try {
      const browser = await browserPromise;
      await browser.close();
    } catch {
    }
  }

  process.exit(0);
}

process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
