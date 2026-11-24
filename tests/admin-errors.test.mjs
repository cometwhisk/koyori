import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const root = new URL('../', import.meta.url);
const source = path => readFileSync(new URL(path, root), 'utf8');
function errors() {
  const window = {};
  runInNewContext(source('opt/assets/js/admin-errors.js'), { window });
  return window.koyoriAdminErrors;
}
const response = (body, status = 200) => new Response(body, { status });
const invalidSync = '服务器响应格式异常，无法确认同步结果。';

const saveCases = [
  ['unwrapped WordPress error', { error: '保存被拒绝。' }, undefined, '保存被拒绝。'],
  ['direct message', { message: ' 保存被拒绝。 ' }, undefined, '保存被拒绝。'],
  ['nested error', { success: false, data: { error: '保存被拒绝。' } }, undefined, '保存被拒绝。'],
  ['JSON HTTP error', { status: 403, responseJSON: { success: false, data: { message: '没有权限。' } } }, 'error', '没有权限。（HTTP 403）'],
  ['JSON responseText', { status: 500, responseText: '{"success":false,"data":{"error":"写入失败。"}}' }, 'error', '写入失败。（HTTP 500）'],
  ['Migrate error method', { status: 502, error() { throw new Error('must not call'); } }, 'error', '保存请求失败（HTTP 502），请稍后重试。'],
  ['non-string error', { error: { message: 'not a string' } }, undefined, '保存失败，无法确认保存结果，请稍后重试。'],
  ['HTML HTTP failure', { status: 500, responseText: '<html>PRIVATE_RESPONSE</html>' }, 'error', '保存请求失败（HTTP 500），请稍后重试。'],
  ['invalid JSON', { status: 200, responseText: '{PRIVATE_RESPONSE' }, 'parsererror', '服务器返回了无效的 JSON，无法确认保存结果。'],
  ['network failure', { status: 0 }, 'error', '网络请求失败，无法确认保存结果，请稍后重试。'],
  ['timeout', { status: 0 }, 'timeout', '保存请求超时，无法确认保存结果，请稍后重试。'],
  ['abort', { status: 0 }, 'abort', '保存请求已取消，无法确认保存结果。'],
  ['WordPress nonce failure', -1, undefined, '安全校验失败，请刷新页面后重试。'],
  ['WordPress empty result', 0, undefined, '保存接口未返回有效结果，请刷新页面后重试。'],
  ['null', null, undefined, '保存失败，无法确认保存结果，请稍后重试。'],
  ['undefined', undefined, undefined, '保存失败，无法确认保存结果，请稍后重试。'],
];
for (const [name, error, textStatus, expected] of saveCases) {
  test(`save diagnostic: ${name}`, () => assert.equal(errors().saveFailure(error, textStatus), expected));
}
test('save diagnostics never coerce functions, objects, raw text, or exception messages', () => {
  const formatter = errors().saveFailure;
  assert.equal(formatter(new Error('PRIVATE_RESPONSE')), '保存失败，无法确认保存结果，请稍后重试。');
  assert.equal(formatter({ status: 500, responseJSON: new Error('PRIVATE_RESPONSE') }), '保存请求失败（HTTP 500），请稍后重试。');
  assert.equal(formatter('PRIVATE_RESPONSE'), '保存失败，无法确认保存结果，请稍后重试。');
  for (const value of [() => 'PRIVATE_RESPONSE', {}, [], 42, true]) {
    const message = formatter({ status: 500, error: value, message: value, responseText: 'PRIVATE_RESPONSE' }, 'error', new Error('PRIVATE_RESPONSE'));
    assert.equal(message, '保存请求失败（HTTP 500），请稍后重试。');
    assert.ok(!message.includes('PRIVATE_RESPONSE'));
  }
});

const syncCases = [
  ['HTTP JSON application error', '{"success":false,"data":{"message":"登录态无效。"}}', 400, false, '登录态无效。（HTTP 400）'],
  ['HTTP error cannot report success', '{"success":true,"data":{"message":"成功","profile":{"nickname":"Nikki","level":"80"}}}', 500, false, '同步请求失败，请稍后重试。（HTTP 500）'],
  ['HTTP malformed success cannot supply a success message', '{"success":"true","data":{"message":"成功"}}', 500, false, '同步请求失败，请稍后重试。（HTTP 500）'],
  ['HTTP HTML error', '<html>PRIVATE_RESPONSE</html>', 502, false, '服务器返回了非 JSON 响应，请刷新页面后重试。（HTTP 502）'],
  ['HTML login response', '<html>PRIVATE_RESPONSE</html>', 200, false, '服务器返回了非 JSON 响应，请刷新页面后重试。'],
  ['malformed JSON', '{PRIVATE_RESPONSE', 200, false, '服务器返回了非 JSON 响应，请刷新页面后重试。'],
  ['empty response', '', 200, false, '服务器返回了非 JSON 响应，请刷新页面后重试。'],
  ['nonce failure', '-1', 403, false, '安全校验失败，请刷新页面后重试。（HTTP 403）'],
  ['missing action', '0', 400, false, '同步接口未返回有效结果，请刷新页面后重试。（HTTP 400）'],
  ['null', 'null', 200, false, invalidSync],
  ['array', '[]', 200, false, invalidSync],
  ['missing success', '{}', 200, false, invalidSync],
  ['truthy success', '{"success":"true","data":{}}', 200, false, invalidSync],
  ['missing data', '{"success":true}', 200, false, invalidSync],
  ['missing profile', '{"success":true,"data":{"message":"成功"}}', 200, false, invalidSync],
  ['wrong nickname type', '{"success":true,"data":{"message":"成功","profile":{"nickname":{},"level":"80"}}}', 200, false, invalidSync],
  ['empty nickname', '{"success":true,"data":{"message":"成功","profile":{"nickname":" ","level":"80"}}}', 200, false, invalidSync],
  ['wrong message type', '{"success":false,"data":{"message":{}}}', 200, false, '同步失败，请稍后重试。'],
  ['application error', '{"success":false,"data":{"message":"统计数据返回不完整。"}}', 200, false, '统计数据返回不完整。'],
  ['validated success', '{"success":true,"data":{"message":"资料同步成功。","profile":{"nickname":"Nikki","level":"80"}}}', 200, true, '资料同步成功。 Nikki / Lv.80'],
  ['numeric level', '{"success":true,"data":{"message":"资料同步成功。","profile":{"nickname":"Nikki","level":0}}}', 200, true, '资料同步成功。 Nikki / Lv.0'],
];
for (const [name, body, status, success, message] of syncCases) {
  test(`sync response: ${name}`, async () => {
    assert.deepEqual({ ...await errors().syncResponse(response(body, status)) }, { success, message });
  });
}
test('body read failure is distinct from invalid JSON or a rejected fetch', async () => {
  const result = await errors().syncResponse({ ok: false, status: 504, text: () => Promise.reject(new TypeError('PRIVATE_RESPONSE')) });
  assert.deepEqual({ ...result }, { success: false, message: '读取服务器响应失败，请稍后重试。（HTTP 504）' });
});

function saveHarness() {
  const bundle = source('opt/assets/js/main.min.js');
  const start = bundle.indexOf('S.fn.csf_save=function()');
  const end = bundle.indexOf(',S.fn.csf_options=', start);
  assert.ok(start >= 0 && end > start, 'real CSF save implementation is present');
  const requests = [];
  const alerts = [];
  let click;
  const state = { disabled: false, value: '保存', saving: false, warningHidden: false, successShown: false };
  const button = {
    each(fn) { fn.call(this); return this; },
    on(_event, fn) { click = fn; },
    data() { return '保存中'; },
    val() { return state.value; },
    hasClass() { return true; },
    attr(_name, value) { state.value = value; return this; },
    prop(_name, value) { state.disabled = value; return this; },
  };
  const options = {
    data() { return 'iro_options'; },
    addClass() { state.saving = true; return this; },
    removeClass() { state.saving = false; return this; },
  };
  const success = { empty() { return this; }, append() { return this; }, fadeIn() { state.successShown = true; return this; } };
  function S(selector) {
    if (selector === button || selector === '.csf-save') return button;
    if (selector === '.csf-options') return options;
    if (selector === '#csf-form') return { serializeJSONCSF: () => 'MOCK_FORM' };
    if (selector === '.csf-error') return { remove() {} };
    if (selector === '.csf-form-success') return success;
    throw new Error(`Unexpected selector ${selector}`);
  }
  S.fn = {};
  const j = { vars: { form_modified: true, $form_warning: { hide() { state.warningHidden = true; } } } };
  const window = { koyoriAdminErrors: errors(), wp: { ajax: { post(action, data) {
    const request = { action, data, done(fn) { this.resolve = fn; return this; }, fail(fn) { this.reject = fn; return this; } };
    requests.push(request);
    return request;
  } } } };
  new Function('S', '_', 'j', 'alert', 'clearTimeout', 'setTimeout', bundle.slice(start, end))(S, window, j, message => alerts.push(message), () => {}, () => {});
  S.fn.csf_save.call(button);
  return { state, requests, alerts, j, click: () => click({ preventDefault() {} }) };
}
test('real CSF failure restores controls, retains unsaved state, and permits retry', () => {
  const ctx = saveHarness();
  ctx.click();
  assert.equal(ctx.state.disabled, true);
  assert.equal(ctx.state.saving, true);
  ctx.click();
  assert.equal(ctx.requests.length, 1, 'duplicate click while pending is ignored');
  ctx.requests[0].reject({ status: 500, error() {} }, 'error');
  assert.deepEqual(ctx.alerts, ['保存请求失败（HTTP 500），请稍后重试。']);
  assert.equal(ctx.state.disabled, false);
  assert.equal(ctx.state.value, '保存');
  assert.equal(ctx.state.saving, false);
  assert.equal(ctx.j.vars.form_modified, true);
  assert.equal(ctx.state.warningHidden, false);
  assert.equal(ctx.state.successShown, false);
  ctx.click();
  assert.equal(ctx.requests.length, 2, 'failed request no longer locks the save button');
});
test('real CSF unwrapped application rejection preserves its message', () => {
  const ctx = saveHarness();
  ctx.click();
  ctx.requests[0].reject({ error: 'Error while saving the changes.' });
  assert.deepEqual(ctx.alerts, ['Error while saving the changes.']);
  assert.equal(ctx.state.disabled, false);
});
test('real CSF success handling is preserved', () => {
  const ctx = saveHarness();
  ctx.click();
  ctx.requests[0].resolve({ errors: {}, notice: 'Saved' });
  assert.equal(ctx.state.disabled, false);
  assert.equal(ctx.state.value, '保存');
  assert.equal(ctx.state.saving, false);
  assert.equal(ctx.state.successShown, true);
  assert.equal(ctx.j.vars.form_modified, false);
  assert.equal(ctx.state.warningHidden, true);
});

function syncHarness(fetch, api = errors()) {
  const php = source('opt/options/theme-options.php');
  const start = php.lastIndexOf('<script>', php.indexOf("const button=document.querySelector('#koyori-nikki-sync')"));
  const end = php.indexOf('</script>', start);
  assert.ok(start >= 0 && end > start, 'real inline sync handler is present');
  const script = php.slice(start + '<script>'.length, end).replace(/<\?= esc_js\(\$nonce\) \?>/g, 'MOCK_NONCE').replace(/<\?= esc_url\(\$ajax_url\) \?>/g, 'https://example.invalid/admin-ajax.php');
  let click;
  const button = { dataset: {}, disabled: false, addEventListener(_type, fn) { click = fn; } };
  const status = { textContent: '', className: '' };
  const fields = { nikki_client_id: { value: '123' }, nikki_token: { value: 'MOCK_TOKEN' }, nikki_openid: { value: 'MOCK_OPENID' } };
  const document = { querySelector(selector) {
    if (selector === '#koyori-nikki-sync') return button;
    if (selector === '#koyori-nikki-status') return status;
    const id = selector.match(/iro_options\[([^\]]+)\]/)?.[1];
    return fields[id] ?? null;
  } };
  runInNewContext(script, { document, fetch, URLSearchParams, window: { koyoriAdminErrors: api } });
  return { button, status, fields, click: async () => { await click(); await new Promise(setImmediate); } };
}
test('real sync handler reports only rejected fetch as network failure and restores button', async () => {
  const ctx = syncHarness(() => Promise.reject(new TypeError('PRIVATE_RESPONSE')));
  await ctx.click();
  assert.equal(ctx.status.textContent, '网络请求失败，请稍后重试。');
  assert.equal(ctx.status.className, 'is-error');
  assert.equal(ctx.button.disabled, false);
});
test('real sync handler distinguishes non-JSON HTTP failure from network failure', async () => {
  const ctx = syncHarness(() => Promise.resolve(response('<html>PRIVATE_RESPONSE</html>', 502)));
  await ctx.click();
  assert.equal(ctx.status.textContent, '服务器返回了非 JSON 响应，请刷新页面后重试。（HTTP 502）');
  assert.equal(ctx.status.className, 'is-error');
  assert.equal(ctx.button.disabled, false);
});
test('real sync handler distinguishes unexpected processing exceptions from network failure', async () => {
  const ctx = syncHarness(() => Promise.resolve(response('{}')), { syncResponse() { throw new Error('PRIVATE_RESPONSE'); } });
  await ctx.click();
  assert.equal(ctx.status.textContent, '处理同步响应时发生错误，无法确认同步结果。');
  assert.equal(ctx.status.className, 'is-error');
  assert.equal(ctx.button.disabled, false);
});
test('real sync handler displays validated success and preserves request parameters', async () => {
  const calls = [];
  const ctx = syncHarness((url, options) => {
    calls.push({ url, options });
    return Promise.resolve(response('{"success":true,"data":{"message":"资料同步成功。","profile":{"nickname":"<Nikki>","level":"80"}}}'));
  });
  await ctx.click();
  assert.equal(ctx.status.textContent, '资料同步成功。 <Nikki> / Lv.80');
  assert.equal(ctx.status.className, 'is-ok');
  assert.equal(ctx.button.disabled, false);
  assert.equal(calls.length, 1);
  assert.equal(calls[0].options.method, 'POST');
  assert.deepEqual(Object.fromEntries(calls[0].options.body), { action: 'koyori_nikki_sync_profile', nonce: 'MOCK_NONCE', client_id: '123', token: 'MOCK_TOKEN', openid: 'MOCK_OPENID' });
});
test('real sync handler validates empty fields before requesting', async () => {
  let called = false;
  const ctx = syncHarness(() => { called = true; throw new Error('must not request'); });
  ctx.fields.nikki_token.value = ' ';
  await ctx.click();
  assert.equal(called, false);
  assert.equal(ctx.status.textContent, '请填写完整的三个字段。');
  assert.equal(ctx.status.className, 'is-error');
  assert.equal(ctx.button.disabled, false);
});
test('helper is loaded before the CSF consumer with a cache-busting version', () => {
  const setup = source('opt/classes/setup.class.php');
  assert.match(setup, /wp_enqueue_script\( 'koyori-admin-errors',/);
  assert.match(setup, /array\( 'csf-plugins', 'koyori-admin-errors' \)/);
  assert.match(setup, /filemtime\(.*assets\/js\/admin-errors\.js/);
});
