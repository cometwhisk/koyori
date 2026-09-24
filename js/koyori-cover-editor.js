(function (wp) {
    if (!wp || !wp.plugins || !wp.editPost || !wp.data) {
        return;
    }

    const { registerPlugin } = wp.plugins;
    const { PluginDocumentSettingPanel } = wp.editPost;
    const { MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { Button, TextControl } = wp.components;
    const { createElement: el, useEffect, useState } = wp.element;
    const { useDispatch, useSelect } = wp.data;

    const META_KEY = '_koyori_external_cover_url';

    function KoyoriCoverPanel() {
        const { editPost } = useDispatch('core/editor');
        const { featuredMedia, media, meta, postType } = useSelect(function (select) {
            const editor = select('core/editor');
            const core = select('core');
            const featuredId = editor.getEditedPostAttribute('featured_media');

            return {
                featuredMedia: featuredId || 0,
                media: featuredId ? core.getMedia(featuredId) : null,
                meta: editor.getEditedPostAttribute('meta') || {},
                postType: editor.getCurrentPostType(),
            };
        }, []);
        const externalImage = meta[META_KEY] || '';
        const [mode, setMode] = useState(externalImage ? 'external' : 'local');
        const [externalUrl, setExternalUrl] = useState(externalImage);

        useEffect(function () {
            setExternalUrl(externalImage);
        }, [externalImage]);

        if (['post', 'page', 'shuoshuo'].indexOf(postType) === -1) {
            return null;
        }

        const updateMeta = function (value) {
            editPost({
                meta: Object.assign({}, meta, { [META_KEY]: value }),
            });
        };

        const localImage = media && (
            media.source_url ||
            (media.media_details && media.media_details.sizes && media.media_details.sizes.full && media.media_details.sizes.full.source_url)
        );

        const selectMode = function (nextMode) {
            setMode(nextMode);
        };

        const renderLocalMode = function () {
            return el(
                'div',
                { className: 'koyori-cover-mode-content' },
                localImage && el('img', {
                    className: 'koyori-cover-preview',
                    src: localImage,
                    alt: '',
                }),
                el('div', { className: 'koyori-cover-actions' },
                    el(MediaUploadCheck, null,
                        el(MediaUpload, {
                            onSelect: function (image) {
                                editPost({ featured_media: image.id });
                            },
                            allowedTypes: ['image'],
                            value: featuredMedia,
                            render: function (props) {
                                return el(Button, {
                                    variant: 'secondary',
                                    onClick: props.open,
                                }, featuredMedia ? '替换本地图片' : '选择本地图片');
                            },
                        })
                    ),
                    featuredMedia && el(Button, {
                        variant: 'link',
                        isDestructive: true,
                        onClick: function () {
                            editPost({ featured_media: 0 });
                        },
                    }, '移除本地图片')
                )
            );
        };

        const renderExternalMode = function () {
            return el(
                'div',
                { className: 'koyori-cover-mode-content' },
                externalImage && el('img', {
                    className: 'koyori-cover-preview',
                    src: externalImage,
                    alt: '',
                }),
                el(TextControl, {
                    label: '图床图片 URL',
                    value: externalUrl,
                    onChange: setExternalUrl,
                    placeholder: 'https://static.smallmaple.com/.../cover.webp',
                    help: '填写后优先使用外链；清除后恢复本地图片。',
                    type: 'url',
                }),
                el('div', { className: 'koyori-cover-actions' },
                    el(Button, {
                        variant: 'primary',
                        disabled: externalUrl.trim() === externalImage,
                        onClick: function () {
                            updateMeta(externalUrl.trim());
                        },
                    }, '应用图床封面'),
                    externalImage && el(Button, {
                        variant: 'link',
                        isDestructive: true,
                        onClick: function () {
                            setExternalUrl('');
                            updateMeta('');
                        },
                    }, '清除图床封面')
                )
            );
        };

        return el(
            PluginDocumentSettingPanel,
            {
                name: 'koyori-cover-panel',
                title: '封面图',
                className: 'koyori-cover-panel',
                initialOpen: true,
            },
            el('div', { className: 'koyori-cover-tabs', role: 'tablist' },
                el(Button, {
                    className: 'koyori-cover-tab' + (mode === 'local' ? ' is-active' : ''),
                    variant: 'tertiary',
                    role: 'tab',
                    'aria-selected': mode === 'local',
                    onClick: function () { selectMode('local'); },
                }, '本地图片'),
                el(Button, {
                    className: 'koyori-cover-tab' + (mode === 'external' ? ' is-active' : ''),
                    variant: 'tertiary',
                    role: 'tab',
                    'aria-selected': mode === 'external',
                    onClick: function () { selectMode('external'); },
                }, '图床 URL')
            ),
            mode === 'local' ? renderLocalMode() : renderExternalMode()
        );
    }

    registerPlugin('koyori-cover-editor', {
        render: KoyoriCoverPanel,
    });
})(window.wp);
