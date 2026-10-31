#!/usr/bin/env node
/**
 * uxui-audit — generic screenshot capture (Playwright).
 *
 * Framework-agnostic: point it at ANY running web app via a JSON config.
 * It does NOT know about your stack, test runner, or routes — you describe
 * them in the config.
 *
 * Usage:
 *   node capture.mjs [config.json]      # default: ./uxui-audit.config.json
 *   node capture.mjs --help
 *
 * Requires: Node >= 18 and Playwright.
 *   npm i -D playwright && npx playwright install chromium
 *
 * Config shape (see capture.config.example.json):
 * {
 *   "baseUrl": "http://localhost:3000",
 *   "routes": [ "/", { "name": "users", "path": "/admin/users" } ],
 *   "viewports": [ { "name": "mobile", "width": 390, "height": 844 },
 *                  { "name": "desktop", "width": 1440, "height": 900 } ],
 *   "storageState": "./storageState.json",   // optional, for auth-gated UIs
 *   "fullPage": true,                          // optional, default true
 *   "waitMs": 600,                             // optional settle delay
 *   "waitUntil": "networkidle",                // optional goto waitUntil
 *   "outDir": ".uxui-audit-runs"                // optional, timestamped subdir created inside
 * }
 */

import { mkdirSync, existsSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

const HELP = `uxui-audit capture — screenshot any running web app for review.

  node capture.mjs [config.json]   (default: ./uxui-audit.config.json)
  node capture.mjs --help

Requires Node >= 18 and Playwright:
  npm i -D playwright && npx playwright install chromium

Config keys: baseUrl (required), routes[] (string or {name,path}),
viewports[] ({name,width,height}), storageState (optional auth),
fullPage, waitMs, waitUntil, outDir. See capture.config.example.json.`;

const DEFAULT_VIEWPORTS = [
  { name: 'mobile', width: 390, height: 844 },
  { name: 'tablet', width: 768, height: 1024 },
  { name: 'desktop', width: 1440, height: 900 },
];

function fail(msg) {
  console.error(`✗ ${msg}`);
  process.exit(1);
}

function slug(s) {
  return String(s).replace(/^https?:\/\//, '').replace(/[^a-zA-Z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || 'root';
}

function timestamp() {
  // YYYYMMDD-HHMMSS in local time, filename-safe.
  const d = new Date();
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}-${p(d.getHours())}${p(d.getMinutes())}${p(d.getSeconds())}`;
}

async function main() {
  const arg = process.argv[2];
  if (arg === '--help' || arg === '-h') {
    console.log(HELP);
    return;
  }

  const configPath = resolve(arg || 'uxui-audit.config.json');
  if (!existsSync(configPath)) {
    fail(`config not found: ${configPath}\n  Copy capture.config.example.json and edit it, or pass a path.`);
  }

  let cfg;
  try {
    cfg = JSON.parse(readFileSync(configPath, 'utf8'));
  } catch (e) {
    fail(`could not parse ${configPath}: ${e.message}`);
  }

  if (!cfg.baseUrl) fail('config.baseUrl is required (e.g. "http://localhost:3000")');

  const routes = (cfg.routes && cfg.routes.length ? cfg.routes : ['/']).map((r) =>
    typeof r === 'string' ? { name: slug(r), path: r } : { name: r.name || slug(r.path), path: r.path }
  );
  const viewports = cfg.viewports && cfg.viewports.length ? cfg.viewports : DEFAULT_VIEWPORTS;
  const fullPage = cfg.fullPage !== false;
  const waitMs = Number.isFinite(cfg.waitMs) ? cfg.waitMs : 600;
  const waitUntil = cfg.waitUntil || 'networkidle';

  let chromium;
  try {
    ({ chromium } = await import('playwright'));
  } catch {
    fail('Playwright is not installed.\n  Run: npm i -D playwright && npx playwright install chromium');
  }

  const runDir = join(resolve(cfg.outDir || '.uxui-audit-runs'), timestamp());
  mkdirSync(runDir, { recursive: true });

  if (cfg.storageState && !existsSync(resolve(cfg.storageState))) {
    fail(`storageState file not found: ${cfg.storageState}\n  Generate it once, e.g.: npx playwright codegen --save-storage=storageState.json ${cfg.baseUrl}`);
  }

  const browser = await chromium.launch();
  const results = [];

  for (const vp of viewports) {
    const context = await browser.newContext({
      viewport: { width: vp.width, height: vp.height },
      storageState: cfg.storageState ? resolve(cfg.storageState) : undefined,
      deviceScaleFactor: 1,
    });
    const page = await context.newPage();
    for (const route of routes) {
      const url = new URL(route.path, cfg.baseUrl).toString();
      const file = join(runDir, `${route.name}-${vp.name}.png`);
      try {
        const resp = await page.goto(url, { waitUntil, timeout: 30000 });
        if (waitMs) await page.waitForTimeout(waitMs);
        await page.screenshot({ path: file, fullPage });
        const status = resp ? resp.status() : '??';
        results.push({ ok: true, route: route.name, vp: vp.name, status, file });
        console.log(`  ✓ ${route.name} @ ${vp.name}  [${status}]  ${url}`);
        if (resp && resp.status() >= 400) {
          console.log(`    ⚠ HTTP ${resp.status()} — capture kept; this is itself a finding.`);
        }
      } catch (e) {
        // Keep going; a failed page is a finding, not a stop.
        try { await page.screenshot({ path: file.replace(/\.png$/, '-ERROR.png') }); } catch {}
        results.push({ ok: false, route: route.name, vp: vp.name, error: e.message, file });
        console.log(`  ✗ ${route.name} @ ${vp.name}  ${url}\n    ${e.message.split('\n')[0]}`);
      }
    }
    await context.close();
  }

  await browser.close();

  const ok = results.filter((r) => r.ok).length;
  console.log(`\n${ok}/${results.length} screenshots → ${runDir}`);
  console.log('Next: ask your agent to "/uxui-audit — analyze ' + runDir + '".');
}

main().catch((e) => fail(e.stack || e.message));
