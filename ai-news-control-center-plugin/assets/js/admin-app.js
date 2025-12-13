/**
 * AI News Control Center Admin App
 * Полнофункциональный интерфейс на русском языке
 */

(function() {
    'use strict';

    const { createElement: h, useState, useEffect, useCallback, useRef } = React;
    const { createRoot } = ReactDOM;

    // API helper с правильным nonce
    const api = {
        baseUrl: ainccData.apiUrl,
        nonce: ainccData.nonce,

        async request(endpoint, options = {}) {
            const url = this.baseUrl + endpoint;
            const config = {
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.nonce,
                },
                credentials: 'same-origin',
                ...options,
            };

            if (config.body && typeof config.body === 'object') {
                config.body = JSON.stringify(config.body);
            }

            try {
                const response = await fetch(url, config);
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || data.error || 'Ошибка API');
                }

                return data;
            } catch (error) {
                console.error('API Error:', error);
                throw error;
            }
        },

        get(endpoint) { return this.request(endpoint, { method: 'GET' }); },
        post(endpoint, data) { return this.request(endpoint, { method: 'POST', body: data }); },
        put(endpoint, data) { return this.request(endpoint, { method: 'PUT', body: data }); },
        delete(endpoint) { return this.request(endpoint, { method: 'DELETE' }); },
    };

    // Toast уведомления
    const toasts = {
        container: null,
        init() {
            if (this.container) return;
            this.container = document.createElement('div');
            this.container.className = 'aincc-toast-container';
            document.body.appendChild(this.container);
        },
        show(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `aincc-toast ${type}`;
            toast.innerHTML = `<span class="aincc-toast-message">${message}</span>`;
            this.container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        },
        success(msg) { this.show(msg, 'success'); },
        error(msg) { this.show(msg, 'error'); },
        info(msg) { this.show(msg, 'info'); },
    };

    // Иконки SVG
    const Icons = {
        dashboard: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('rect', { x: 3, y: 3, width: 7, height: 9 }),
            h('rect', { x: 14, y: 3, width: 7, height: 5 }),
            h('rect', { x: 14, y: 12, width: 7, height: 9 }),
            h('rect', { x: 3, y: 16, width: 7, height: 5 })
        ),
        create: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('path', { d: 'M12 5v14M5 12h14' })
        ),
        sources: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('path', { d: 'M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16M5 19a1 1 0 1 0 0-2 1 1 0 0 0 0 2z' })
        ),
        analytics: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('path', { d: 'M18 20V10M12 20V4M6 20v-6' })
        ),
        settings: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('circle', { cx: 12, cy: 12, r: 3 }),
            h('path', { d: 'M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z' })
        ),
        link: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('path', { d: 'M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71' }),
            h('path', { d: 'M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71' })
        ),
        check: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('path', { d: 'M20 6L9 17l-5-5' })
        ),
        x: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('path', { d: 'M18 6L6 18M6 6l12 12' })
        ),
        eye: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('path', { d: 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z' }),
            h('circle', { cx: 12, cy: 12, r: 3 })
        ),
        send: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('path', { d: 'M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z' })
        ),
        refresh: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('path', { d: 'M23 4v6h-6M1 20v-6h6' }),
            h('path', { d: 'M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15' })
        ),
        play: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('polygon', { points: '5 3 19 12 5 21 5 3' })
        ),
        trash: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('path', { d: 'M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2' })
        ),
        image: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('rect', { x: 3, y: 3, width: 18, height: 18, rx: 2 }),
            h('circle', { cx: 8.5, cy: 8.5, r: 1.5 }),
            h('path', { d: 'M21 15l-5-5L5 21' })
        ),
        magic: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('path', { d: 'M15 4V2M15 16v-2M8 9h2M20 9h2M17.8 11.8L19 13M17.8 6.2L19 5M3 21l9-9M12.2 6.2L11 5' })
        ),
    };

    // Статус бейдж
    const StatusBadge = ({ status }) => {
        const map = {
            pending_ok: { label: 'На проверку', class: 'pending' },
            auto_ready: { label: 'Готово', class: 'auto-ready' },
            published: { label: 'Опубликовано', class: 'published' },
            rejected: { label: 'Отклонено', class: 'rejected' },
            processing: { label: 'Обработка...', class: 'processing' },
            scheduled: { label: 'Запланировано', class: 'auto-ready' },
        };
        const { label, class: cls } = map[status] || { label: status, class: 'pending' };
        return h('span', { className: `aincc-status ${cls}` }, label);
    };

    // Сайдбар
    const Sidebar = ({ currentPage, onNavigate }) => {
        const items = [
            { id: 'dashboard', label: 'Панель управления', icon: Icons.dashboard },
            { id: 'create', label: 'Создать статью', icon: Icons.create },
            { id: 'fromurl', label: 'Из ссылки', icon: Icons.link },
            { id: 'sources', label: 'Источники RSS', icon: Icons.sources },
            { id: 'analytics', label: 'Аналитика', icon: Icons.analytics },
            { id: 'settings', label: 'Настройки', icon: Icons.settings },
        ];

        return h('aside', { className: 'aincc-sidebar' },
            h('div', { className: 'aincc-logo' },
                h('div', { className: 'aincc-logo-icon' }, '📰'),
                h('span', { className: 'aincc-logo-text' }, 'AI News')
            ),
            h('nav', { className: 'aincc-nav' },
                items.map(item =>
                    h('a', {
                        key: item.id,
                        href: `#${item.id}`,
                        className: `aincc-nav-item ${currentPage === item.id ? 'active' : ''}`,
                        onClick: (e) => { e.preventDefault(); onNavigate(item.id); },
                    },
                        h('span', { style: { width: 20, height: 20 } }, item.icon),
                        item.label
                    )
                )
            )
        );
    };

    // Статистика
    const DashboardStats = ({ stats }) => {
        return h('div', { className: 'aincc-stats-grid' },
            h('div', { className: 'aincc-stat-card' },
                h('div', { className: 'aincc-stat-label' }, 'На проверку'),
                h('div', { className: 'aincc-stat-value' }, stats.pending || 0)
            ),
            h('div', { className: 'aincc-stat-card' },
                h('div', { className: 'aincc-stat-label' }, 'Готово к публикации'),
                h('div', { className: 'aincc-stat-value' }, stats.auto_ready || 0)
            ),
            h('div', { className: 'aincc-stat-card' },
                h('div', { className: 'aincc-stat-label' }, 'Опубликовано (7 дней)'),
                h('div', { className: 'aincc-stat-value' }, stats.published || 0)
            ),
            h('div', { className: 'aincc-stat-card' },
                h('div', { className: 'aincc-stat-label' }, 'Отклонено'),
                h('div', { className: 'aincc-stat-value' }, stats.rejected || 0)
            )
        );
    };

    // Строка черновика
    const DraftRow = ({ draft, onAction }) => {
        return h('tr', null,
            h('td', null,
                new Date(draft.created_at).toLocaleDateString('ru-RU', {
                    day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
                })
            ),
            h('td', null, h(StatusBadge, { status: draft.status })),
            h('td', null,
                h('div', { className: 'aincc-article-title' }, draft.title || 'Без названия'),
                draft.category && h('div', { className: 'aincc-article-lead' }, draft.category)
            ),
            h('td', null,
                h('div', { className: 'aincc-source' },
                    h('span', { className: 'aincc-source-name' }, draft.source_name || 'Вручную'),
                    draft.source_trust && h('span', { className: 'aincc-source-trust' },
                        `Доверие: ${(draft.source_trust * 100).toFixed(0)}%`
                    )
                )
            ),
            h('td', null,
                h('div', { className: 'aincc-actions' },
                    h('button', { className: 'aincc-btn aincc-btn-icon', title: 'Просмотр', onClick: () => onAction('view', draft) }, Icons.eye),
                    draft.status === 'pending_ok' && h('button', { className: 'aincc-btn aincc-btn-success aincc-btn-sm', onClick: () => onAction('approve', draft) }, Icons.check, ' ОК'),
                    draft.status === 'pending_ok' && h('button', { className: 'aincc-btn aincc-btn-danger aincc-btn-sm', onClick: () => onAction('reject', draft) }, Icons.x),
                    (draft.status === 'pending_ok' || draft.status === 'auto_ready') && h('button', { className: 'aincc-btn aincc-btn-primary aincc-btn-sm', onClick: () => onAction('publish', draft) }, Icons.send)
                )
            )
        );
    };

    // Модалка просмотра и редактирования
    const PreviewModal = ({ draft, onClose, onAction, onReload }) => {
        const [loading, setLoading] = useState(false);
        const [editing, setEditing] = useState(false);
        const [editData, setEditData] = useState({
            title: draft?.title || '',
            lead: draft?.lead || '',
            body_html: draft?.body_html || '',
            seo_title: draft?.seo_title || '',
            meta_description: draft?.meta_description || '',
            category: draft?.category || '',
        });

        useEffect(() => {
            if (draft) {
                setEditData({
                    title: draft.title || '',
                    lead: draft.lead || '',
                    body_html: draft.body_html || '',
                    seo_title: draft.seo_title || '',
                    meta_description: draft.meta_description || '',
                    category: draft.category || '',
                });
            }
        }, [draft]);

        if (!draft) return null;

        const handlePublish = async () => {
            setLoading(true);
            try {
                await api.post(`/drafts/${draft.id}/publish`, { channels: ['wordpress'] });
                toasts.success('Статья опубликована!');
                onClose();
                onReload && onReload();
            } catch (error) {
                toasts.error('Ошибка: ' + error.message);
            }
            setLoading(false);
        };

        const handleSave = async () => {
            setLoading(true);
            try {
                await api.put(`/drafts/${draft.id}`, editData);
                toasts.success('Изменения сохранены!');
                setEditing(false);
                onReload && onReload();
            } catch (error) {
                toasts.error('Ошибка сохранения: ' + error.message);
            }
            setLoading(false);
        };

        const categories = [
            { value: 'politik', label: 'Политика' },
            { value: 'wirtschaft', label: 'Экономика' },
            { value: 'gesellschaft', label: 'Общество' },
            { value: 'migration', label: 'Миграция' },
            { value: 'lokales', label: 'Местные новости' },
            { value: 'kultur', label: 'Культура' },
            { value: 'verkehr', label: 'Транспорт' },
            { value: 'sport', label: 'Спорт' },
            { value: 'wetter', label: 'Погода' },
            { value: 'nachrichten', label: 'Новости' },
        ];

        return h('div', { className: 'aincc-modal-overlay', onClick: (e) => e.target === e.currentTarget && onClose() },
            h('div', { className: 'aincc-modal', style: { maxWidth: 1000 } },
                h('div', { className: 'aincc-modal-header' },
                    h('h2', null, editing ? 'Редактирование статьи' : 'Просмотр статьи'),
                    h('div', { style: { display: 'flex', alignItems: 'center', gap: 12 } },
                        h('span', { style: { color: '#64748b', fontSize: 13 } }, 'Язык: ', draft.lang?.toUpperCase()),
                        h('button', { className: 'aincc-modal-close', onClick: onClose }, '✕')
                    )
                ),
                h('div', { className: 'aincc-modal-body', style: { maxHeight: '70vh', overflow: 'auto' } },
                    editing ? (
                        // Режим редактирования
                        h('div', { style: { display: 'flex', flexDirection: 'column', gap: 16 } },
                            h('div', { className: 'aincc-form-group' },
                                h('label', { className: 'aincc-label' }, 'Заголовок'),
                                h('input', {
                                    className: 'aincc-input', value: editData.title,
                                    onChange: (e) => setEditData({ ...editData, title: e.target.value }),
                                })
                            ),
                            h('div', { className: 'aincc-form-group' },
                                h('label', { className: 'aincc-label' }, 'Лид'),
                                h('textarea', {
                                    className: 'aincc-textarea', rows: 3, value: editData.lead,
                                    onChange: (e) => setEditData({ ...editData, lead: e.target.value }),
                                })
                            ),
                            h('div', { className: 'aincc-form-group' },
                                h('label', { className: 'aincc-label' }, 'Текст статьи (HTML)'),
                                h('textarea', {
                                    className: 'aincc-textarea', rows: 12, value: editData.body_html,
                                    onChange: (e) => setEditData({ ...editData, body_html: e.target.value }),
                                })
                            ),
                            h('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 } },
                                h('div', { className: 'aincc-form-group' },
                                    h('label', { className: 'aincc-label' }, 'SEO Заголовок'),
                                    h('input', {
                                        className: 'aincc-input', value: editData.seo_title,
                                        onChange: (e) => setEditData({ ...editData, seo_title: e.target.value }),
                                    })
                                ),
                                h('div', { className: 'aincc-form-group' },
                                    h('label', { className: 'aincc-label' }, 'Категория'),
                                    h('select', {
                                        className: 'aincc-select', value: editData.category,
                                        onChange: (e) => setEditData({ ...editData, category: e.target.value }),
                                    },
                                        categories.map(c => h('option', { key: c.value, value: c.value }, c.label))
                                    )
                                )
                            ),
                            h('div', { className: 'aincc-form-group' },
                                h('label', { className: 'aincc-label' }, 'Meta Description'),
                                h('textarea', {
                                    className: 'aincc-textarea', rows: 2, value: editData.meta_description,
                                    onChange: (e) => setEditData({ ...editData, meta_description: e.target.value }),
                                })
                            )
                        )
                    ) : (
                        // Режим просмотра
                        h('div', null,
                            h('div', { className: 'aincc-preview' },
                                draft.image_url && h('img', { className: 'aincc-preview-image', src: draft.image_url, alt: draft.title }),
                                h('h1', { className: 'aincc-preview-title' }, draft.title),
                                draft.lead && h('p', { className: 'aincc-preview-lead' }, draft.lead),
                                h('div', { className: 'aincc-preview-content', dangerouslySetInnerHTML: { __html: draft.body_html || '' } })
                            ),
                            h('div', { style: { marginTop: 20, padding: 16, background: '#1e293b', borderRadius: 8 } },
                                h('div', { style: { marginBottom: 8 } }, h('strong', { style: { color: '#94a3b8' } }, 'SEO Title: '), h('span', { style: { color: '#e2e8f0' } }, draft.seo_title || '-')),
                                h('div', { style: { marginBottom: 8 } }, h('strong', { style: { color: '#94a3b8' } }, 'Description: '), h('span', { style: { color: '#e2e8f0' } }, draft.meta_description || '-')),
                                h('div', { style: { marginBottom: 8 } }, h('strong', { style: { color: '#94a3b8' } }, 'Категория: '), h('span', { style: { color: '#e2e8f0' } }, draft.category || '-')),
                                h('div', null, h('strong', { style: { color: '#94a3b8' } }, 'Slug: '), h('span', { style: { color: '#e2e8f0' } }, draft.slug || '-'))
                            )
                        )
                    )
                ),
                h('div', { className: 'aincc-modal-footer' },
                    editing ? (
                        h(React.Fragment, null,
                            h('button', { className: 'aincc-btn aincc-btn-secondary', onClick: () => setEditing(false) }, 'Отмена'),
                            h('button', { className: 'aincc-btn aincc-btn-primary', onClick: handleSave, disabled: loading },
                                loading ? 'Сохранение...' : 'Сохранить изменения')
                        )
                    ) : (
                        h(React.Fragment, null,
                            h('button', { className: 'aincc-btn aincc-btn-secondary', onClick: onClose }, 'Закрыть'),
                            draft.status !== 'published' && h('button', { className: 'aincc-btn aincc-btn-secondary', onClick: () => setEditing(true), style: { marginLeft: 8 } }, '✏️ Редактировать'),
                            draft.status !== 'published' && h('button', {
                                className: 'aincc-btn aincc-btn-primary', onClick: handlePublish, disabled: loading, style: { marginLeft: 8 }
                            }, loading ? 'Публикация...' : 'Опубликовать')
                        )
                    )
                )
            )
        );
    };

    // ============================================
    // СТРАНИЦА DASHBOARD
    // ============================================
    const DashboardPage = () => {
        const [drafts, setDrafts] = useState([]);
        const [stats, setStats] = useState({});
        const [filter, setFilter] = useState('pending_ok,auto_ready');
        const [loading, setLoading] = useState(true);
        const [selectedDraft, setSelectedDraft] = useState(null);
        const [page, setPage] = useState(1);
        const [total, setTotal] = useState(0);
        const [fetching, setFetching] = useState(false);

        const loadData = useCallback(async () => {
            setLoading(true);
            try {
                const [draftsData, analyticsData] = await Promise.all([
                    api.get(`/drafts?status=${filter}&page=${page}&per_page=10`),
                    api.get('/analytics?period=7d'),
                ]);
                setDrafts(draftsData.items || []);
                setTotal(draftsData.total || 0);
                setStats(analyticsData.stats || {});
            } catch (error) {
                toasts.error('Ошибка загрузки: ' + error.message);
            }
            setLoading(false);
        }, [filter, page]);

        useEffect(() => { loadData(); }, [loadData]);

        const handleFetchNow = async () => {
            setFetching(true);
            try {
                await api.post('/system/cron/trigger', { hook: 'aincc_fetch_sources' });
                toasts.success('Сбор новостей запущен!');
                setTimeout(loadData, 3000);
            } catch (error) {
                toasts.error('Ошибка: ' + error.message);
            }
            setFetching(false);
        };

        const handleAction = async (action, draft) => {
            switch (action) {
                case 'view':
                    try {
                        const full = await api.get(`/drafts/${draft.id}`);
                        setSelectedDraft(full);
                    } catch (e) { toasts.error('Ошибка загрузки'); }
                    break;
                case 'approve':
                    try {
                        await api.post(`/drafts/${draft.id}/approve`);
                        toasts.success('Одобрено!');
                        loadData();
                    } catch (e) { toasts.error('Ошибка'); }
                    break;
                case 'reject':
                    const reason = prompt('Причина отклонения:');
                    if (reason !== null) {
                        try {
                            await api.post(`/drafts/${draft.id}/reject`, { reason });
                            toasts.success('Отклонено');
                            loadData();
                        } catch (e) { toasts.error('Ошибка'); }
                    }
                    break;
                case 'publish':
                    try {
                        await api.post(`/drafts/${draft.id}/publish`, { channels: ['wordpress', 'telegram'] });
                        toasts.success('Опубликовано!');
                        loadData();
                    } catch (e) { toasts.error('Ошибка: ' + e.message); }
                    break;
            }
        };

        const filters = [
            { id: 'pending_ok,auto_ready', label: 'Очередь', count: (stats.pending || 0) + (stats.auto_ready || 0) },
            { id: 'pending_ok', label: 'На проверку', count: stats.pending || 0 },
            { id: 'auto_ready', label: 'Готово', count: stats.auto_ready || 0 },
            { id: 'published', label: 'Опубликовано', count: stats.published || 0 },
            { id: 'rejected', label: 'Отклонено', count: stats.rejected || 0 },
        ];

        return h('div', null,
            h('div', { className: 'aincc-header' },
                h('h1', null, 'Панель управления'),
                h('div', { style: { display: 'flex', gap: 8 } },
                    h('button', { className: 'aincc-btn aincc-btn-primary', onClick: handleFetchNow, disabled: fetching },
                        fetching ? 'Загрузка...' : [Icons.play, ' Собрать новости']
                    ),
                    h('button', { className: 'aincc-btn aincc-btn-secondary', onClick: loadData }, Icons.refresh, ' Обновить')
                )
            ),
            h(DashboardStats, { stats }),
            h('div', { className: 'aincc-filters' },
                filters.map(f =>
                    h('button', {
                        key: f.id,
                        className: `aincc-filter-btn ${filter === f.id ? 'active' : ''}`,
                        onClick: () => { setFilter(f.id); setPage(1); },
                    }, f.label, h('span', { className: 'count' }, f.count))
                )
            ),
            h('div', { className: 'aincc-table-container' },
                loading ? h('div', { className: 'aincc-loading' },
                    h('div', { className: 'aincc-spinner' }),
                    h('p', null, 'Загрузка...')
                ) : drafts.length === 0 ? h('div', { className: 'aincc-empty' },
                    h('div', { className: 'aincc-empty-icon' }, '📭'),
                    h('p', { className: 'aincc-empty-text' }, 'Нет статей'),
                    h('p', { style: { color: '#64748b', marginTop: 8 } }, 'Нажмите "Собрать новости" чтобы загрузить из RSS')
                ) : h('table', { className: 'aincc-table' },
                    h('thead', null,
                        h('tr', null,
                            h('th', null, 'Дата'),
                            h('th', null, 'Статус'),
                            h('th', null, 'Заголовок'),
                            h('th', null, 'Источник'),
                            h('th', null, 'Действия')
                        )
                    ),
                    h('tbody', null, drafts.map(d => h(DraftRow, { key: d.id, draft: d, onAction: handleAction })))
                )
            ),
            total > 10 && h('div', { className: 'aincc-pagination' },
                h('button', { disabled: page === 1, onClick: () => setPage(p => p - 1) }, '← Назад'),
                h('span', { style: { color: '#94a3b8', padding: '8px 16px' } }, `Стр. ${page} из ${Math.ceil(total / 10)}`),
                h('button', { disabled: page >= Math.ceil(total / 10), onClick: () => setPage(p => p + 1) }, 'Далее →')
            ),
            selectedDraft && h(PreviewModal, { draft: selectedDraft, onClose: () => setSelectedDraft(null), onReload: loadData })
        );
    };

    // ============================================
    // СТРАНИЦА СОЗДАНИЯ СТАТЬИ
    // ============================================
    const CreateArticlePage = () => {
        const [form, setForm] = useState({
            title: '', lead: '', body: '', source_lang: 'de',
            target_langs: ['de', 'ua', 'ru', 'en'], category: 'gesellschaft', tags: [],
        });
        const [loading, setLoading] = useState(false);
        const [aiChecking, setAiChecking] = useState(false);

        const handleSubmit = async (e) => {
            e.preventDefault();
            if (!form.title || !form.body) { toasts.error('Заполните заголовок и текст'); return; }
            setLoading(true);
            try {
                await api.post('/articles/create', form);
                toasts.success('Статья создана и обрабатывается!');
                setForm({ title: '', lead: '', body: '', source_lang: 'de', target_langs: ['de', 'ua', 'ru', 'en'], category: 'gesellschaft', tags: [] });
            } catch (e) { toasts.error('Ошибка: ' + e.message); }
            setLoading(false);
        };

        const handleAICheck = async () => {
            if (!form.body) { toasts.error('Введите текст для проверки'); return; }
            setAiChecking(true);
            try {
                const result = await api.post('/ai/check-text', { text: form.body, lang: form.source_lang });
                if (result.improved) {
                    setForm({ ...form, body: result.improved });
                    toasts.success('Текст улучшен с помощью AI!');
                }
            } catch (e) { toasts.error('AI проверка недоступна'); }
            setAiChecking(false);
        };

        const categories = [
            { value: 'politik', label: 'Политика' },
            { value: 'wirtschaft', label: 'Экономика' },
            { value: 'gesellschaft', label: 'Общество' },
            { value: 'migration', label: 'Миграция' },
            { value: 'lokales', label: 'Местные новости' },
            { value: 'kultur', label: 'Культура' },
            { value: 'verkehr', label: 'Транспорт' },
            { value: 'sport', label: 'Спорт' },
            { value: 'wetter', label: 'Погода' },
        ];

        return h('div', null,
            h('div', { className: 'aincc-header' }, h('h1', null, 'Создать статью')),
            h('form', { onSubmit: handleSubmit, style: { maxWidth: 800 } },
                h('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16, marginBottom: 16 } },
                    h('div', { className: 'aincc-form-group' },
                        h('label', { className: 'aincc-label' }, 'Язык оригинала'),
                        h('select', { className: 'aincc-select', value: form.source_lang, onChange: (e) => setForm({ ...form, source_lang: e.target.value }) },
                            h('option', { value: 'de' }, 'Немецкий (DE)'),
                            h('option', { value: 'ua' }, 'Украинский (UA)'),
                            h('option', { value: 'ru' }, 'Русский (RU)'),
                            h('option', { value: 'en' }, 'Английский (EN)')
                        )
                    ),
                    h('div', { className: 'aincc-form-group' },
                        h('label', { className: 'aincc-label' }, 'Категория'),
                        h('select', { className: 'aincc-select', value: form.category, onChange: (e) => setForm({ ...form, category: e.target.value }) },
                            categories.map(c => h('option', { key: c.value, value: c.value }, c.label))
                        )
                    )
                ),
                h('div', { className: 'aincc-form-group' },
                    h('label', { className: 'aincc-label' }, 'Заголовок *'),
                    h('input', { type: 'text', className: 'aincc-input', value: form.title, onChange: (e) => setForm({ ...form, title: e.target.value }), placeholder: 'Введите заголовок статьи', required: true })
                ),
                h('div', { className: 'aincc-form-group' },
                    h('label', { className: 'aincc-label' }, 'Лид (краткое описание)'),
                    h('textarea', { className: 'aincc-textarea', value: form.lead, onChange: (e) => setForm({ ...form, lead: e.target.value }), placeholder: '2-3 предложения, суть статьи', rows: 3 })
                ),
                h('div', { className: 'aincc-form-group' },
                    h('label', { className: 'aincc-label' }, 'Текст статьи *'),
                    h('textarea', { className: 'aincc-textarea', value: form.body, onChange: (e) => setForm({ ...form, body: e.target.value }), placeholder: 'Полный текст статьи...', rows: 12, required: true })
                ),
                h('div', { className: 'aincc-form-group' },
                    h('label', { className: 'aincc-label' }, 'Перевести на языки'),
                    h('div', { style: { display: 'flex', gap: 16 } },
                        ['de', 'ua', 'ru', 'en'].map(lang =>
                            h('label', { key: lang, style: { color: '#94a3b8', display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer' } },
                                h('input', {
                                    type: 'checkbox', checked: form.target_langs.includes(lang),
                                    onChange: (e) => {
                                        const langs = e.target.checked ? [...form.target_langs, lang] : form.target_langs.filter(l => l !== lang);
                                        setForm({ ...form, target_langs: langs });
                                    },
                                }),
                                { de: '🇩🇪 Немецкий', ua: '🇺🇦 Украинский', ru: '🇷🇺 Русский', en: '🇬🇧 Английский' }[lang]
                            )
                        )
                    )
                ),
                h('div', { style: { marginTop: 24, display: 'flex', gap: 12 } },
                    h('button', { type: 'submit', className: 'aincc-btn aincc-btn-primary', disabled: loading }, loading ? 'Обработка...' : 'Создать и обработать'),
                    h('button', { type: 'button', className: 'aincc-btn aincc-btn-secondary', onClick: handleAICheck, disabled: aiChecking }, aiChecking ? 'Проверка...' : [Icons.magic, ' Проверить AI'])
                )
            )
        );
    };

    // ============================================
    // СТРАНИЦА ГЕНЕРАЦИИ ПО ССЫЛКЕ
    // ============================================
    const FromURLPage = () => {
        const [url, setUrl] = useState('');
        const [loading, setLoading] = useState(false);
        const [result, setResult] = useState(null);

        const handleGenerate = async () => {
            if (!url) { toasts.error('Введите ссылку'); return; }
            setLoading(true);
            setResult(null);
            try {
                const res = await api.post('/articles/from-url', { url });
                setResult(res);
                toasts.success('Статья сгенерирована! Проверьте результат.');
            } catch (e) {
                toasts.error('Ошибка: ' + e.message);
            }
            setLoading(false);
        };

        return h('div', null,
            h('div', { className: 'aincc-header' }, h('h1', null, 'Генерация из ссылки')),
            h('div', { style: { maxWidth: 800 } },
                h('div', { className: 'aincc-stat-card', style: { marginBottom: 24 } },
                    h('p', { style: { color: '#94a3b8', marginBottom: 16 } },
                        'Вставьте ссылку на новость и AI автоматически извлечет контент, перепишет его в стиле Deutsche Welle и подготовит к публикации.'
                    ),
                    h('div', { style: { display: 'flex', gap: 12 } },
                        h('input', {
                            type: 'url', className: 'aincc-input', style: { flex: 1 },
                            value: url, onChange: (e) => setUrl(e.target.value),
                            placeholder: 'https://example.com/news/article...'
                        }),
                        h('button', { className: 'aincc-btn aincc-btn-primary', onClick: handleGenerate, disabled: loading },
                            loading ? 'Генерация...' : [Icons.magic, ' Сгенерировать']
                        )
                    )
                ),
                result && h('div', { className: 'aincc-stat-card' },
                    h('h3', { style: { color: '#22c55e', marginBottom: 12 } }, '✓ Статья создана'),
                    h('p', { style: { color: '#e2e8f0' } }, 'Заголовок: ', result.title),
                    h('p', { style: { color: '#94a3b8', marginTop: 8 } }, 'ID черновика: ', result.draft_id),
                    h('a', {
                        href: `#dashboard`,
                        className: 'aincc-btn aincc-btn-secondary',
                        style: { marginTop: 16, display: 'inline-block' },
                    }, 'Перейти к черновикам')
                )
            )
        );
    };

    // ============================================
    // СТРАНИЦА ИСТОЧНИКОВ RSS
    // ============================================
    const SourcesPage = () => {
        const [sources, setSources] = useState([]);
        const [loading, setLoading] = useState(true);
        const [showAdd, setShowAdd] = useState(false);
        const [newSource, setNewSource] = useState({ name: '', url: '', lang: 'de', category: 'media', trust_score: 0.8, enabled: true });

        const loadSources = async () => {
            setLoading(true);
            try {
                const data = await api.get('/sources');
                setSources(data.items || []);
            } catch (e) {
                toasts.error('Ошибка загрузки источников');
            }
            setLoading(false);
        };

        useEffect(() => { loadSources(); }, []);

        const handleToggle = async (source) => {
            try {
                const result = await api.post(`/sources/${source.id}/toggle`, {});
                loadSources();
                toasts.success(result.message || (source.enabled ? 'Отключено' : 'Включено'));
            } catch (e) {
                toasts.error('Ошибка: ' + e.message);
            }
        };

        const handleDelete = async (source) => {
            if (!confirm(`Удалить источник "${source.name}"?`)) return;
            try {
                await api.delete(`/sources/${source.id}`);
                loadSources();
                toasts.success('Удалено');
            } catch (e) {
                toasts.error('Ошибка');
            }
        };

        const handleAdd = async () => {
            if (!newSource.name || !newSource.url) { toasts.error('Заполните название и URL'); return; }
            try {
                await api.post('/sources', newSource);
                setShowAdd(false);
                setNewSource({ name: '', url: '', lang: 'de', category: 'media', trust_score: 0.8, enabled: true });
                loadSources();
                toasts.success('Источник добавлен!');
            } catch (e) {
                toasts.error('Ошибка: ' + e.message);
            }
        };

        const categories = ['official', 'media', 'ukraine', 'transport', 'weather', 'economy', 'international', 'aggregator'];

        return h('div', null,
            h('div', { className: 'aincc-header' },
                h('h1', null, 'Источники RSS'),
                h('button', { className: 'aincc-btn aincc-btn-primary', onClick: () => setShowAdd(true) }, Icons.create, ' Добавить источник')
            ),
            showAdd && h('div', { className: 'aincc-stat-card', style: { marginBottom: 24 } },
                h('h3', { style: { color: 'white', marginBottom: 16 } }, 'Новый источник'),
                h('div', { style: { display: 'grid', gridTemplateColumns: '1fr 2fr', gap: 12, marginBottom: 12 } },
                    h('input', { className: 'aincc-input', placeholder: 'Название', value: newSource.name, onChange: (e) => setNewSource({ ...newSource, name: e.target.value }) }),
                    h('input', { className: 'aincc-input', placeholder: 'RSS URL', value: newSource.url, onChange: (e) => setNewSource({ ...newSource, url: e.target.value }) }),
                ),
                h('div', { style: { display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 12, marginBottom: 12 } },
                    h('select', { className: 'aincc-select', value: newSource.lang, onChange: (e) => setNewSource({ ...newSource, lang: e.target.value }) },
                        h('option', { value: 'de' }, 'Немецкий'),
                        h('option', { value: 'ua' }, 'Украинский'),
                        h('option', { value: 'ru' }, 'Русский'),
                        h('option', { value: 'en' }, 'Английский')
                    ),
                    h('select', { className: 'aincc-select', value: newSource.category, onChange: (e) => setNewSource({ ...newSource, category: e.target.value }) },
                        categories.map(c => h('option', { key: c, value: c }, c))
                    ),
                    h('input', { type: 'number', className: 'aincc-input', placeholder: 'Доверие 0-1', step: 0.1, min: 0, max: 1, value: newSource.trust_score, onChange: (e) => setNewSource({ ...newSource, trust_score: parseFloat(e.target.value) }) }),
                    h('div', { style: { display: 'flex', gap: 8 } },
                        h('button', { className: 'aincc-btn aincc-btn-primary', onClick: handleAdd }, 'Добавить'),
                        h('button', { className: 'aincc-btn aincc-btn-secondary', onClick: () => setShowAdd(false) }, 'Отмена')
                    )
                )
            ),
            loading ? h('div', { className: 'aincc-loading' }, h('div', { className: 'aincc-spinner' }), h('p', null, 'Загрузка...'))
            : sources.length === 0 ? h('div', { className: 'aincc-empty' }, h('div', { className: 'aincc-empty-icon' }, '📡'), h('p', null, 'Нет источников'))
            : h('div', { className: 'aincc-table-container' },
                h('table', { className: 'aincc-table' },
                    h('thead', null, h('tr', null,
                        h('th', null, 'Статус'),
                        h('th', null, 'Название'),
                        h('th', null, 'Категория'),
                        h('th', null, 'Язык'),
                        h('th', null, 'Доверие'),
                        h('th', null, 'Действия')
                    )),
                    h('tbody', null, sources.map(s =>
                        h('tr', { key: s.id, style: { opacity: s.enabled ? 1 : 0.6 } },
                            h('td', null,
                                h('label', { className: 'aincc-toggle', title: s.enabled ? 'Выключить источник' : 'Включить источник' },
                                    h('input', { type: 'checkbox', checked: !!s.enabled, onChange: () => handleToggle(s) }),
                                    h('span', { className: 'aincc-toggle-slider' })
                                )
                            ),
                            h('td', null, h('div', null, s.name), h('div', { style: { fontSize: 11, color: '#64748b', maxWidth: 300, overflow: 'hidden', textOverflow: 'ellipsis' } }, s.url)),
                            h('td', null, s.category),
                            h('td', null, s.lang?.toUpperCase()),
                            h('td', null, `${(s.trust_score * 100).toFixed(0)}%`),
                            h('td', null,
                                h('button', { className: 'aincc-btn aincc-btn-icon aincc-btn-danger', title: 'Удалить', onClick: () => handleDelete(s) }, Icons.trash)
                            )
                        )
                    ))
                )
            ),
            h('div', { style: { marginTop: 16, color: '#64748b' } }, `Всего источников: ${sources.length}`)
        );
    };

    // ============================================
    // СТРАНИЦА АНАЛИТИКИ
    // ============================================
    const AnalyticsPage = () => {
        const [data, setData] = useState(null);
        const [loading, setLoading] = useState(true);

        useEffect(() => {
            api.get('/analytics?period=7d')
                .then(setData)
                .catch(() => toasts.error('Ошибка загрузки'))
                .finally(() => setLoading(false));
        }, []);

        if (loading) return h('div', { className: 'aincc-loading' }, h('div', { className: 'aincc-spinner' }));

        return h('div', null,
            h('div', { className: 'aincc-header' }, h('h1', null, 'Аналитика')),
            h(DashboardStats, { stats: data?.stats || {} }),
            h('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24, marginTop: 24 } },
                h('div', { className: 'aincc-stat-card' },
                    h('h3', { style: { color: 'white', marginBottom: 16 } }, 'Топ категории'),
                    (data?.categories || []).length === 0 ? h('p', { style: { color: '#64748b' } }, 'Нет данных')
                    : data.categories.map(c => h('div', { key: c.category, style: { display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid #334155', color: '#e2e8f0' } },
                        h('span', null, c.category || 'Без категории'),
                        h('span', { style: { color: '#3b82f6' } }, c.count)
                    ))
                ),
                h('div', { className: 'aincc-stat-card' },
                    h('h3', { style: { color: 'white', marginBottom: 16 } }, 'Топ источники'),
                    (data?.sources || []).length === 0 ? h('p', { style: { color: '#64748b' } }, 'Нет данных')
                    : data.sources.map(s => h('div', { key: s.name, style: { display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid #334155', color: '#e2e8f0' } },
                        h('span', null, s.name),
                        h('span', { style: { color: '#22c55e' } }, s.article_count, ' статей')
                    ))
                )
            )
        );
    };

    // ============================================
    // СТРАНИЦА НАСТРОЕК
    // ============================================
    const SettingsPage = () => {
        return h('div', null,
            h('div', { className: 'aincc-header' }, h('h1', null, 'Настройки')),
            h('div', { className: 'aincc-stat-card', style: { maxWidth: 600 } },
                h('p', { style: { color: '#94a3b8', marginBottom: 16 } }, 'Настройки плагина управляются через стандартную страницу WordPress.'),
                h('a', { href: ainccData.adminUrl + 'admin.php?page=ai-news-center-settings', className: 'aincc-btn aincc-btn-primary' }, 'Открыть настройки')
            )
        );
    };

    // ============================================
    // ГЛАВНОЕ ПРИЛОЖЕНИЕ
    // ============================================
    const App = () => {
        const [currentPage, setCurrentPage] = useState(ainccData.currentPage || 'dashboard');

        const renderPage = () => {
            switch (currentPage) {
                case 'dashboard': return h(DashboardPage);
                case 'create': return h(CreateArticlePage);
                case 'fromurl': return h(FromURLPage);
                case 'sources': return h(SourcesPage);
                case 'analytics': return h(AnalyticsPage);
                case 'settings': return h(SettingsPage);
                default: return h(DashboardPage);
            }
        };

        return h('div', { className: 'aincc-app-container' },
            h(Sidebar, { currentPage, onNavigate: setCurrentPage }),
            h('main', { className: 'aincc-main' }, renderPage())
        );
    };

    // Инициализация
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('aincc-root');
        if (root) {
            toasts.init();
            createRoot(root).render(h(App));
        }
    });
})();
