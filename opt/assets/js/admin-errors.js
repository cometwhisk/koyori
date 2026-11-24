/* Koyori admin request diagnostics. Keep raw bodies and exception details private. */
(function (window) {
  'use strict';

  function record(value) {
    return value !== null && Object.prototype.toString.call(value) === '[object Object]';
  }

  function text(value) {
    return typeof value === 'string' ? value.trim() : '';
  }

  // wp.ajax rejects with unwrapped data; jqXHR holds a full responseJSON envelope.
  function errorMessage(value) {
    if (!record(value) || value.success === true) return '';
    var data = record(value.data) ? value.data : value;
    return text(data.message) || text(data.error);
  }

  function httpStatus(value) {
    return typeof value === 'number' && Number.isInteger(value) && value >= 100 && value <= 599 ? value : 0;
  }

  function parseJSON(value) {
    try {
      return JSON.parse(value);
    } catch (error) {
      return null;
    }
  }

  function saveFailure(error, textStatus) {
    var xhr = record(error) ? error : {};
    var status = httpStatus(xhr.status);
    var payload = xhr.responseJSON;
    if (payload === undefined && typeof xhr.responseText === 'string') {
      payload = parseJSON(xhr.responseText);
    }
    if (error === -1 || payload === -1) return '安全校验失败，请刷新页面后重试。';
    if (error === 0 || payload === 0) return '保存接口未返回有效结果，请刷新页面后重试。';
    var message = errorMessage(payload) || errorMessage(error);
    if (message) return message + (status >= 400 ? '（HTTP ' + status + '）' : '');
    if (textStatus === 'timeout') return '保存请求超时，无法确认保存结果，请稍后重试。';
    if (textStatus === 'abort') return '保存请求已取消，无法确认保存结果。';
    if (status >= 400) return '保存请求失败（HTTP ' + status + '），请稍后重试。';
    if (textStatus === 'parsererror') return '服务器返回了无效的 JSON，无法确认保存结果。';
    if (xhr.status === 0) return '网络请求失败，无法确认保存结果，请稍后重试。';
    return '保存失败，无法确认保存结果，请稍后重试。';
  }

  async function syncResponse(response) {
    var status = httpStatus(response.status);
    var suffix = !response.ok && status ? '（HTTP ' + status + '）' : '';
    function failure(message) {
      return { success: false, message: message + suffix };
    }
    var body;
    try {
      body = await response.text();
    } catch (error) {
      return failure('读取服务器响应失败，请稍后重试。');
    }
    var payload;
    try {
      payload = JSON.parse(body);
    } catch (error) {
      return failure('服务器返回了非 JSON 响应，请刷新页面后重试。');
    }
    if (payload === -1) return failure('安全校验失败，请刷新页面后重试。');
    if (payload === 0) return failure('同步接口未返回有效结果，请刷新页面后重试。');
    if (!response.ok) {
      return failure((record(payload) && payload.success === false && errorMessage(payload)) || '同步请求失败，请稍后重试。');
    }
    if (!record(payload) || typeof payload.success !== 'boolean') {
      return failure('服务器响应格式异常，无法确认同步结果。');
    }
    if (!payload.success) return failure(errorMessage(payload) || '同步失败，请稍后重试。');
    var data = payload.data;
    var profile = record(data) && data.profile;
    if (!record(profile) || !text(data.message) || !text(profile.nickname) ||
        !(text(profile.level) || (typeof profile.level === 'number' && Number.isFinite(profile.level)))) {
      return failure('服务器响应格式异常，无法确认同步结果。');
    }
    return { success: true, message: text(data.message) + ' ' + text(profile.nickname) + ' / Lv.' + profile.level };
  }

  window.koyoriAdminErrors = { saveFailure: saveFailure, syncResponse: syncResponse };
}(window));
