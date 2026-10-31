import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const bundle = readFileSync(new URL('../js/app.js', import.meta.url), 'utf8');
const prismModuleStart = bundle.indexOf('const u=_iro.code_highlight_prism?.autoload_path||');
const initStart = bundle.indexOf('function b(){', prismModuleStart);
const initEnd = bundle.indexOf('async function v(){', initStart);
const init = bundle.slice(initStart, initEnd);

test('Prism initialization is shared and retries incomplete setup', () => {
  assert.ok(prismModuleStart >= 0 && initStart > prismModuleStart && initEnd > initStart, 'Prism module boundaries are present');
  assert.ok(bundle.slice(prismModuleStart, initStart).includes('let h,prismInitPromise,prismInitDone=!1;'), 'shared init state is declared in the Prism module');
  assert.ok(init.includes('if(prismInitPromise)return prismInitPromise'), 'concurrent calls share pending initialization');
  assert.ok(init.includes('prismInitPromise=null'), 'failed initialization can retry');
  assert.ok(init.includes('throw new Error("Prism autoloader failed to initialize")'), 'incomplete setup is not silently accepted');
  assert.ok(init.includes('prismToolbarReady'), 'success and warm paths validate toolbar registration');
  assert.ok(init.includes('!prismCoreReady()||!prismAutoloaderReady()||!prismToolbarReady()'), 'bundled path initializes missing toolbar');
});

test('external Prism dependencies load in dependency order, not concurrently', () => {
  const core = init.indexOf('(0,s.J)("components/prism-core.min.js"');
  const autoloader = init.indexOf('(0,s.J)("plugins/autoloader/prism-autoloader.min.js"');
  const toolbar = init.indexOf('(0,s.J)("plugins/toolbar/prism-toolbar.min.js"');
  const showLanguage = init.indexOf('(0,s.J)("plugins/show-language/prism-show-language.min.js"');
  assert.ok(core >= 0 && core < autoloader && autoloader < toolbar && toolbar < showLanguage, 'core precedes autoloader, toolbar, and show-language');
  assert.ok(!init.includes('Promise.all([(0,s.J)("components/prism-core.min.js"'), 'core and dependent plugins are not launched concurrently');
});

test('optional Prism plugins wait for the base Prism initialization', () => {
  assert.ok(bundle.includes('await b();await Promise.all([t&&'), 'line-number and other optional plugins wait for core/autoloader initialization');
});

function createLoaderContext(preexisting = []) {
  const scripts = [...preexisting];
  const timers = new Map();
  let nextTimer = 1;
  function makeScript() {
    const listeners = new Map();
    return {
      dataset: {},
      addEventListener(type, callback) {
        if (!listeners.has(type)) listeners.set(type, new Set());
        listeners.get(type).add(callback);
      },
      removeEventListener(type, callback) { listeners.get(type)?.delete(callback); },
      dispatch(type) {
        if (typeof this[`on${type}`] === 'function') this[`on${type}`]({ type, target: this });
        for (const callback of [...(listeners.get(type) ?? [])]) callback({ type, target: this });
      },
      remove() { const i = scripts.indexOf(this); if (i >= 0) scripts.splice(i, 1); },
    };
  }
  const document = {
    getElementById: id => scripts.find(script => script.id === id) ?? null,
    createElement: () => makeScript(),
    body: { append(script) { scripts.push(script); } },
  };
  const setTimer = (callback, delay) => { const id = nextTimer++; timers.set(id, { callback, delay }); return id; };
  const clearTimer = id => timers.delete(id);
  const exports = {};
  const runtime = { d(target, definitions) { for (const [key, getter] of Object.entries(definitions)) Object.defineProperty(target, key, { enumerable: true, get: getter }); } };
  const modulePrefix = '8529:(e,t,n)=>{';
  const moduleStart = bundle.indexOf(modulePrefix);
  const moduleEnd = bundle.indexOf('},8668:function(e){', moduleStart);
  assert.ok(moduleStart >= 0 && moduleEnd > moduleStart, 'script-loader module boundaries are present');
  const moduleBody = bundle.slice(moduleStart + modulePrefix.length, moduleEnd);
  new Function('e', 't', 'n', 'document', 'localStorage', 'setTimeout', 'clearTimeout', moduleBody)(
    {}, exports, runtime, document, { getItem: () => null }, setTimer, clearTimer,
  );
  return { loadScript: exports.J, scripts, timers, makeScript };
}

test('duplicate requests share one in-flight script and loaded scripts are reused', async () => {
  const ctx = createLoaderContext();
  const first = ctx.loadScript('dist/typed.umd.js', 'typed.js');
  const second = ctx.loadScript('dist/typed.umd.js', 'typed.js');
  assert.strictEqual(first, second, 'concurrent calls return the same pending promise');
  assert.equal(ctx.scripts.length, 1, 'concurrent calls append only one script');
  const script = ctx.scripts[0];
  script.dispatch('load');
  await first;
  assert.equal(script.dataset.sakurairoLoaded, 'true', 'successful script is marked loaded');
  await ctx.loadScript('dist/typed.umd.js', 'typed.js');
  assert.equal(ctx.scripts.length, 1, 'loaded script is not appended again');
});

test('an unmarked pre-existing script gets bounded recovery rather than a permanent wait', async () => {
  const id = 'prismjs@1.30.0plugins/show-language/prism-show-language.min.js';
  const ctx = createLoaderContext();
  const stale = ctx.makeScript();
  stale.id = id;
  ctx.scripts.push(stale);
  const pending = ctx.loadScript('plugins/show-language/prism-show-language.min.js', 'prismjs', '1.30.0');
  assert.equal(ctx.timers.size, 1, 'ambiguous existing element has a timeout');
  const [timerId, timer] = [...ctx.timers.entries()][0];
  assert.equal(timer.delay, 4000, 'ambiguous wait is bounded');
  ctx.timers.delete(timerId);
  timer.callback();
  assert.equal(ctx.scripts.length, 1, 'stale element is replaced with one retry');
  const retry = ctx.scripts[0];
  assert.notStrictEqual(retry, stale);
  retry.dispatch('load');
  await pending;
  assert.equal(retry.dataset.sakurairoLoaded, 'true');
});

test('an unmarked script with a recognized completed state is reused', async () => {
  const ctx = createLoaderContext();
  const existing = ctx.makeScript();
  existing.id = 'prismjs@1.30.0plugins/show-language/prism-show-language.min.js';
  existing.readyState = 'complete';
  ctx.scripts.push(existing);
  await ctx.loadScript('plugins/show-language/prism-show-language.min.js', 'prismjs', '1.30.0');
  assert.equal(ctx.scripts.length, 1, 'completed script is not duplicated');
  assert.equal(ctx.timers.size, 0, 'completed script does not start a timeout');
});

test('waiting on an existing in-flight script preserves other listeners', async () => {
  const id = 'prismjs@1.30.0plugins/autoloader/prism-autoloader.min.js';
  const ctx = createLoaderContext();
  const existing = ctx.makeScript();
  existing.id = id;
  existing.readyState = 'loading';
  ctx.scripts.push(existing);
  let otherListenerCalled = false;
  let previousOnloadCalled = false;
  existing.onload = () => { previousOnloadCalled = true; };
  existing.addEventListener('load', () => { otherListenerCalled = true; });
  const pending = ctx.loadScript('plugins/autoloader/prism-autoloader.min.js', 'prismjs', '1.30.0');
  assert.equal(ctx.scripts.length, 1, 'existing in-flight element is reused');
  let settled = false;
  pending.then(() => { settled = true; });
  await Promise.resolve();
  assert.equal(settled, false, 'request remains pending until the existing load event');
  existing.dispatch('load');
  await pending;
  assert.equal(previousOnloadCalled, true, 'existing onload property handler is preserved');
  assert.equal(otherListenerCalled, true, 'existing listener still runs');
  assert.equal(existing.dataset.sakurairoLoaded, 'true');
});

test('network errors remove failed scripts so later calls retry', async () => {
  const ctx = createLoaderContext();
  const failed = ctx.loadScript('plugins/autoloader/prism-autoloader.min.js', 'prismjs', '1.30.0');
  ctx.scripts[0].dispatch('error');
  await assert.rejects(failed, /加载失败/);
  assert.equal(ctx.scripts.length, 0, 'failed element was removed');
  const retry = ctx.loadScript('plugins/autoloader/prism-autoloader.min.js', 'prismjs', '1.30.0');
  assert.equal(ctx.scripts.length, 1, 'a new element is created on retry');
  ctx.scripts[0].dispatch('load');
  await retry;
});

test('Prism preconfiguration stubs do not count as an initialized core', async () => {
  const previous = Object.getOwnPropertyDescriptor(globalThis, 'window');
  globalThis.window = { Prism: { manual: true } };
  try {
    const ctx = createLoaderContext();
    const pending = ctx.loadScript('components/prism-core.min.js', 'prismjs', '1.30.0');
    assert.equal(ctx.scripts.length, 1, 'core is requested when only Prism.manual is preconfigured');
    ctx.scripts[0].dispatch('load');
    await pending;
  } finally {
    if (previous) Object.defineProperty(globalThis, 'window', previous);
    else delete globalThis.window;
  }
});

function createInitializerHarness({
  shared = true,
  initialPrism = null,
  coreLoads = true,
  autoloaderLoads = true,
  toolbarLoads = true,
  chunkCoreLoads = true,
  chunkAutoloaderLoads = true,
  chunkToolbarLoads = true,
} = {}) {
  const calls = [];
  const window = { Prism: initialPrism };
  const document = { addEventListener() {} };
  let harness;
  let manualAtCoreLoad;
  const makeCore = () => ({ languages: {}, plugins: {}, highlightElement() {}, hooks: {} });
  const ensurePlugins = () => {
    const prism = harness.getPrism();
    if (prism && typeof prism === 'object') prism.plugins ??= {};
    return prism;
  };
  const s = { J: async path => {
    calls.push(path);
    if (path.includes('components/prism-core')) {
      manualAtCoreLoad = window.Prism?.manual;
      if (coreLoads) harness.setPrism(makeCore());
    } else if (path.includes('plugins/autoloader/')) {
      if (autoloaderLoads) ensurePlugins().plugins.autoloader = {};
    } else if (path.includes('plugins/toolbar/')) {
      if (toolbarLoads) ensurePlugins().plugins.toolbar = { registerButton() {} };
    }
  } };
  const n = {
    e: id => { calls.push(`chunk:${id}`); return Promise.resolve(); },
    bind: (_self, id) => () => {
      calls.push(`module:${id}`);
      if (chunkCoreLoads) {
        manualAtCoreLoad = window.Prism?.manual;
        harness.setPrism(makeCore());
      }
      const prism = ensurePlugins();
      if (prism && chunkAutoloaderLoads) prism.plugins.autoloader = {};
      if (prism && chunkToolbarLoads) prism.plugins.toolbar = { registerButton() {} };
    },
  };
  const factory = new Function(
    'window', 'document', 'p', 'g', 'i', 'u', '_iro', 's', 'r', 'n', 'o', 'console', 'URL',
    `let Prism=window.Prism;Object.defineProperty(window,'Prism',{configurable:true,get(){return Prism},set(value){Prism=value}});let prismInitPromise,prismInitDone=!1;${init};return{b,setPrism(value){Prism=value;window.Prism=value},getPrism(){return Prism},isDone(){return prismInitDone}};`,
  );
  harness = factory(window, document, () => {}, () => {}, { loadCSS() {} }, 'https://example.test/prismjs@1.30.0/', { ext_shared_lib: shared }, s, () => '', n, { jJ: () => false }, { warn() {} }, URL);
  return { harness, calls, getManualAtCoreLoad: () => manualAtCoreLoad };
}

test('external initialization loads core for a manual-only Prism stub', async () => {
  const { harness, calls } = createInitializerHarness({ shared: true, initialPrism: { manual: true } });
  await harness.b();
  assert.equal(calls[0], 'components/prism-core.min.js');
  assert.equal(typeof harness.getPrism().highlightElement, 'function');
  assert.equal(typeof harness.getPrism().plugins.toolbar.registerButton, 'function');
  assert.equal(harness.isDone(), true);
});

test('external initialization sets Prism manual mode before requesting core', async () => {
  const { harness, getManualAtCoreLoad } = createInitializerHarness({ shared: true, initialPrism: null });
  await harness.b();
  assert.equal(getManualAtCoreLoad(), true);
});

test('bundled initialization sets Prism manual mode before loading the core chunk', async () => {
  const { harness, getManualAtCoreLoad } = createInitializerHarness({ shared: false, initialPrism: null });
  await harness.b();
  assert.equal(getManualAtCoreLoad(), true);
});

test('external initialization rejects a toolbar load without registration before show-language', async () => {
  const { harness, calls } = createInitializerHarness({ shared: true, toolbarLoads: false });
  await assert.rejects(harness.b(), /Prism toolbar/);
  assert.equal(calls.some(path => path.includes('show-language')), false);
  assert.equal(harness.isDone(), false);
});

test('external warm initialization repairs a missing toolbar', async () => {
  const { harness, calls } = createInitializerHarness({ shared: true });
  await harness.b();
  delete harness.getPrism().plugins.toolbar;
  calls.length = 0;
  await harness.b();
  assert.ok(calls.includes('plugins/toolbar/prism-toolbar.min.js'));
  assert.equal(harness.isDone(), true);
});

test('bundled initialization loads its chunk when an existing Prism lacks toolbar', async () => {
  const initialPrism = { languages: {}, plugins: { autoloader: {} }, highlightElement() {}, hooks: {} };
  const { harness, calls } = createInitializerHarness({ shared: false, initialPrism });
  await harness.b();
  assert.ok(calls.includes('chunk:3818'));
  assert.equal(typeof harness.getPrism().plugins.toolbar.registerButton, 'function');
  assert.equal(harness.isDone(), true);
});

test('bundled initialization does not finish when the chunk lacks toolbar registration', async () => {
  const initialPrism = { languages: {}, plugins: { autoloader: {} }, highlightElement() {}, hooks: {} };
  const { harness, calls } = createInitializerHarness({ shared: false, initialPrism, chunkToolbarLoads: false });
  await assert.rejects(harness.b(), /Prism toolbar/);
  assert.ok(calls.includes('chunk:3818'));
  assert.equal(harness.isDone(), false);
});
