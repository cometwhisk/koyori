(function (wp, config) {
    'use strict';

    if (!wp || !wp.data || !config || window.__koyoriAIExcerptEditorLoaded) {
        return;
    }
    window.__koyoriAIExcerptEditorLoaded = true;

    var select = wp.data.select;
    var dispatch = wp.data.dispatch;

    function getExcerptField() {
        return document.querySelector('.editor-post-excerpt__textarea, textarea[name="excerpt"]');
    }

    function setStatus(container, message, isError) {
        var status = container.querySelector('.koyori-ai-excerpt-status');
        if (!status) {
            status = document.createElement('span');
            status.className = 'koyori-ai-excerpt-status';
            status.style.marginLeft = '8px';
            status.style.fontSize = '12px';
            container.appendChild(status);
        }
        status.textContent = message || '';
        status.style.color = isError ? '#b32d2e' : '#2271b1';
    }

    function generateExcerpt(button, container) {
        var editor = select('core/editor');
        var postId = editor.getCurrentPostId();
        var title = editor.getEditedPostAttribute('title') || '';
        var content = editor.getEditedPostAttribute('content') || '';
        var form = new FormData();

        form.append('action', 'koyori_generate_chatgpt_excerpt');
        form.append('nonce', config.nonce);
        form.append('post_id', postId);
        form.append('title', title);
        form.append('content', content);

        button.disabled = true;
        button.textContent = '生成中…';
        setStatus(container, '正在请求 AI…', false);

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: form
        })
            .then(function (response) {
                return response.json().then(function (json) {
                    if (!response.ok || !json.success) {
                        throw new Error(json && json.data && json.data.message ? json.data.message : 'AI 摘要生成失败。');
                    }
                    return json.data;
                });
            })
            .then(function (data) {
                var currentMeta = editor.getEditedPostAttribute('meta') || {};
                dispatch('core/editor').editPost({
                    excerpt: data.excerpt,
                    meta: Object.assign({}, currentMeta, {
                        _koyori_ai_excerpt_hash: data.hash
                    })
                });
                setStatus(container, '摘要已填入，请点击“更新”保存文章。', false);
            })
            .catch(function (error) {
                setStatus(container, error.message || 'AI 摘要生成失败。', true);
            })
            .finally(function () {
                button.disabled = false;
                button.textContent = 'AI 生成摘要';
            });
    }

    function installButton() {
        var field = getExcerptField();
        if (!field || field.parentElement.querySelector('.koyori-ai-excerpt-control')) {
            return;
        }

        var container = document.createElement('div');
        container.className = 'koyori-ai-excerpt-control';
        container.style.marginTop = '8px';

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'button button-secondary';
        button.textContent = 'AI 生成摘要';
        button.addEventListener('click', function () {
            generateExcerpt(button, container);
        });

        container.appendChild(button);
        field.parentElement.appendChild(container);
    }

    installButton();
    var observer = new MutationObserver(installButton);
    observer.observe(document.body, { childList: true, subtree: true });
})(window.wp, window.koyoriAIExcerptData || null);
