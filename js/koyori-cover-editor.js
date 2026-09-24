(function (wp) {
    if (!wp || !wp.plugins || !wp.editPost || !wp.data) {
        return;
    }

    const { registerPlugin } = wp.plugins;
    const { PluginDocumentSettingPanel } = wp.editPost;
    const { MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { Button, TextControl } = wp.components;
    const { createElement: el, Fragment, useEffect, useState } = wp.element;
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
        const [externalUrl, setExternalUrl] = useState(meta[META_KEY] || '');

        useEffect(function () {
            setExternalUrl(meta[META_KEY] || '');
        }, [meta[META_KEY]]);

        if (['post', 'page', 'shuoshuo'].indexOf(postType) === -1) {
            return null;
        }

        const updateMeta = function (value) {
            editPost({
                meta: Object.assign({}, meta, { [META_KEY]: value }),
            });
        };

        const localImage = media && (media.source_url || (media.media_details && media.media_details.sizes && media.media_details.sizes.full && media.media_details.sizes.full.source_url));
        const externalImage = meta[META_KEY] || '';

        return el(
            PluginDocumentSettingPanel,
            {
                name: 'koyori-cover-panel',
                title: '封面图',
                className: 'koyori-cover-panel',
                initialOpen: true,
            },
            el('div', { className: 'koyori-cover-tabs' },
                el('span', { className: 'koyori-cover-tab is-active' }, '本地图片'),
                el('span', { className: 'koyori-cover-tab' }, '图床 URL')
            ),
            localImage && !externalImage && el('img', {
                className: 'koyori-cover-preview',
                src: localImage,
                alt: '',
            }),
            externalImage && el('img', {
                className: 'koyori-cover-preview',
                src: externalImage,
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
                    onClick: function () { editPost({ featured_media: 0 }); },
                }, '移除本地图片')
            ),
            el('div', { className: 'koyori-cover-divider' }, '或使用图床图片'),
            el(TextControl, {
                label: '图床图片 URL',
                value: externalUrl,
                onChange: setExternalUrl,
                placeholder: 'https://static.smallmaple.com/.../cover.webp',
                help: '填写后优先使用外链；留空则使用本地图片。',
                type: 'url',
            }),
            el('div', { className: 'koyori-cover-actions' },
                el(Button, {
                    variant: 'primary',
                    disabled: externalUrl === (meta[META_KEY] || ''),
                    onClick: function () { updateMeta(externalUrl.trim()); },
                }, '应用外链封面'),
                externalImage && el(Button, {
                    variant: 'link',
                    isDestructive: true,
                    onClick: function () {
                        setExternalUrl('');
                        updateMeta('');
                    },
                }, '清除外链')
            )
        );
    }

    registerPlugin('koyori-cover-editor', {
        render: KoyoriCoverPanel,
    });
})(window.wp);
