/**
 * AI News Control Center Admin App
 * React-based admin interface
 */

(function() {
    'use strict';

    const { createElement: h, useState, useEffect, useCallback } = React;
    const { createRoot } = ReactDOM;

    // API helper
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
                ...options,
            };

            if (config.body && typeof config.body === 'object') {
                config.body = JSON.stringify(config.body);
            }

            try {
                const response = await fetch(url, config);
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'API Error');
                }

                return data;
            } catch (error) {
                console.error('API Error:', error);
                throw error;
            }
        },

        get(endpoint) {
            return this.request(endpoint, { method: 'GET' });
        },

        post(endpoint, data) {
            return this.request(endpoint, { method: 'POST', body: data });
        },

        put(endpoint, data) {
            return this.request(endpoint, { method: 'PUT', body: data });
        },

        delete(endpoint) {
            return this.request(endpoint, { method: 'DELETE' });
        },
    };

    // Toast notification system
    const toasts = {
        container: null,

        init() {
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
            }, 3000);
        },

        success(message) { this.show(message, 'success'); },
        error(message) { this.show(message, 'error'); },
        info(message) { this.show(message, 'info'); },
    };

    // Icons
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
        analytics: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('path', { d: 'M18 20V10M12 20V4M6 20v-6' })
        ),
        settings: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('circle', { cx: 12, cy: 12, r: 3 }),
            h('path', { d: 'M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83' })
        ),
        check: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('path', { d: 'M20 6L9 17l-5-5' })
        ),
        x: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('path', { d: 'M18 6L6 18M6 6l12 12' })
        ),
        edit: h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
            h('path', { d: 'M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7' }),
            h('path', { d: 'M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z' })
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
            h('path', { d: 'M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15' })
        ),
    };

    // Status badge component
    const StatusBadge = ({ status }) => {
        const statusMap = {
            pending_ok: { label: 'Needs Review', class: 'pending' },
            auto_ready: { label: 'Auto Ready', class: 'auto-ready' },
            published: { label: 'Published', class: 'published' },
            rejected: { label: 'Rejected', class: 'rejected' },
            processing: { label: 'Processing', class: 'processing' },
            scheduled: { label: 'Scheduled', class: 'auto-ready' },
        };

        const { label, class: className } = statusMap[status] || { label: status, class: 'pending' };

        return h('span', { className: `aincc-status ${className}` }, label);
    };

    // Sidebar component
    const Sidebar = ({ currentPage, onNavigate }) => {
        const navItems = [
            { id: 'dashboard', label: 'Dashboard', icon: Icons.dashboard },
            { id: 'create', label: 'Create Article', icon: Icons.create },
            { id: 'analytics', label: 'Analytics', icon: Icons.analytics },
            { id: 'settings', label: 'Settings', icon: Icons.settings },
        ];

        return h('aside', { className: 'aincc-sidebar' },
            h('div', { className: 'aincc-logo' },
                h('div', { className: 'aincc-logo-icon' }, '📰'),
                h('span', { className: 'aincc-logo-text' }, 'AI News')
            ),
            h('nav', { className: 'aincc-nav' },
                navItems.map(item =>
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

    // Dashboard Stats component
    const DashboardStats = ({ stats }) => {
        return h('div', { className: 'aincc-stats-grid' },
            h('div', { className: 'aincc-stat-card' },
                h('div', { className: 'aincc-stat-label' }, 'Pending Review'),
                h('div', { className: 'aincc-stat-value' }, stats.pending || 0)
            ),
            h('div', { className: 'aincc-stat-card' },
                h('div', { className: 'aincc-stat-label' }, 'Auto Ready'),
                h('div', { className: 'aincc-stat-value' }, stats.auto_ready || 0)
            ),
            h('div', { className: 'aincc-stat-card' },
                h('div', { className: 'aincc-stat-label' }, 'Published (7 days)'),
                h('div', { className: 'aincc-stat-value' }, stats.published || 0)
            ),
            h('div', { className: 'aincc-stat-card' },
                h('div', { className: 'aincc-stat-label' }, 'Rejected'),
                h('div', { className: 'aincc-stat-value' }, stats.rejected || 0)
            )
        );
    };

    // Draft row component
    const DraftRow = ({ draft, onAction }) => {
        return h('tr', null,
            h('td', null,
                new Date(draft.created_at).toLocaleDateString('de-DE', {
                    day: '2-digit',
                    month: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                })
            ),
            h('td', null, h(StatusBadge, { status: draft.status })),
            h('td', null,
                h('div', { className: 'aincc-article-title' }, draft.title),
                draft.category && h('div', { className: 'aincc-article-lead' }, draft.category)
            ),
            h('td', null,
                h('div', { className: 'aincc-source' },
                    h('span', { className: 'aincc-source-name' }, draft.source_name || 'Manual'),
                    draft.source_trust && h('span', { className: 'aincc-source-trust' },
                        `Trust: ${(draft.source_trust * 100).toFixed(0)}%`
                    )
                )
            ),
            h('td', null,
                h('div', { className: 'aincc-actions' },
                    h('button', {
                        className: 'aincc-btn aincc-btn-icon',
                        title: 'View',
                        onClick: () => onAction('view', draft),
                    }, Icons.eye),
                    draft.status === 'pending_ok' && h('button', {
                        className: 'aincc-btn aincc-btn-success aincc-btn-sm',
                        onClick: () => onAction('approve', draft),
                    }, Icons.check, ' OK'),
                    draft.status === 'pending_ok' && h('button', {
                        className: 'aincc-btn aincc-btn-danger aincc-btn-sm',
                        onClick: () => onAction('reject', draft),
                    }, Icons.x),
                    (draft.status === 'pending_ok' || draft.status === 'auto_ready') && h('button', {
                        className: 'aincc-btn aincc-btn-primary aincc-btn-sm',
                        onClick: () => onAction('publish', draft),
                    }, Icons.send)
                )
            )
        );
    };

    // Preview Modal component
    const PreviewModal = ({ draft, onClose, onAction }) => {
        const [currentLang, setCurrentLang] = useState('de');
        const [loading, setLoading] = useState(false);

        if (!draft) return null;

        const handlePublish = async () => {
            setLoading(true);
            try {
                await api.post(`/drafts/${draft.id}/publish`, { channels: ['wordpress', 'telegram'] });
                toasts.success('Article published successfully!');
                onClose();
            } catch (error) {
                toasts.error('Failed to publish: ' + error.message);
            }
            setLoading(false);
        };

        return h('div', { className: 'aincc-modal-overlay', onClick: (e) => e.target === e.currentTarget && onClose() },
            h('div', { className: 'aincc-modal' },
                h('div', { className: 'aincc-modal-header' },
                    h('h2', null, 'Article Preview'),
                    h('button', { className: 'aincc-modal-close', onClick: onClose }, '✕')
                ),
                h('div', { className: 'aincc-modal-body' },
                    h('div', { className: 'aincc-lang-tabs' },
                        ['de', 'ua', 'ru', 'en'].map(lang =>
                            h('button', {
                                key: lang,
                                className: `aincc-lang-tab ${currentLang === lang ? 'active' : ''}`,
                                onClick: () => setCurrentLang(lang),
                            }, lang.toUpperCase())
                        )
                    ),
                    h('div', { className: 'aincc-preview' },
                        draft.image_url && h('img', {
                            className: 'aincc-preview-image',
                            src: draft.image_url,
                            alt: draft.title,
                        }),
                        h('h1', { className: 'aincc-preview-title' }, draft.title),
                        draft.lead && h('p', { className: 'aincc-preview-lead' }, draft.lead),
                        h('div', {
                            className: 'aincc-preview-content',
                            dangerouslySetInnerHTML: { __html: draft.body_html || '' },
                        })
                    ),
                    h('div', { style: { marginTop: 20 } },
                        h('strong', { style: { color: '#94a3b8' } }, 'SEO Title: '),
                        h('span', { style: { color: 'white' } }, draft.seo_title || '-'),
                        h('br'),
                        h('strong', { style: { color: '#94a3b8' } }, 'Meta Description: '),
                        h('span', { style: { color: 'white' } }, draft.meta_description || '-')
                    )
                ),
                h('div', { className: 'aincc-modal-footer' },
                    h('button', { className: 'aincc-btn aincc-btn-secondary', onClick: onClose }, 'Close'),
                    draft.status !== 'published' && h('button', {
                        className: 'aincc-btn aincc-btn-primary',
                        onClick: handlePublish,
                        disabled: loading,
                    }, loading ? 'Publishing...' : 'Publish Now')
                )
            )
        );
    };

    // Dashboard page
    const DashboardPage = () => {
        const [drafts, setDrafts] = useState([]);
        const [stats, setStats] = useState({});
        const [filter, setFilter] = useState('pending_ok,auto_ready');
        const [loading, setLoading] = useState(true);
        const [selectedDraft, setSelectedDraft] = useState(null);
        const [page, setPage] = useState(1);
        const [total, setTotal] = useState(0);

        const loadData = useCallback(async () => {
            setLoading(true);
            try {
                const [draftsData, analyticsData] = await Promise.all([
                    api.get(`/drafts?status=${filter}&page=${page}&per_page=10`),
                    api.get('/analytics?period=7d'),
                ]);

                setDrafts(draftsData.items);
                setTotal(draftsData.total);
                setStats(analyticsData.stats);
            } catch (error) {
                toasts.error('Failed to load data');
            }
            setLoading(false);
        }, [filter, page]);

        useEffect(() => {
            loadData();
        }, [loadData]);

        const handleAction = async (action, draft) => {
            switch (action) {
                case 'view':
                    try {
                        const fullDraft = await api.get(`/drafts/${draft.id}`);
                        setSelectedDraft(fullDraft);
                    } catch (error) {
                        toasts.error('Failed to load draft');
                    }
                    break;

                case 'approve':
                    try {
                        await api.post(`/drafts/${draft.id}/approve`);
                        toasts.success('Draft approved');
                        loadData();
                    } catch (error) {
                        toasts.error('Failed to approve');
                    }
                    break;

                case 'reject':
                    const reason = prompt('Reason for rejection:');
                    if (reason !== null) {
                        try {
                            await api.post(`/drafts/${draft.id}/reject`, { reason });
                            toasts.success('Draft rejected');
                            loadData();
                        } catch (error) {
                            toasts.error('Failed to reject');
                        }
                    }
                    break;

                case 'publish':
                    try {
                        await api.post(`/drafts/${draft.id}/publish`, { channels: ['wordpress', 'telegram'] });
                        toasts.success('Published successfully!');
                        loadData();
                    } catch (error) {
                        toasts.error('Failed to publish: ' + error.message);
                    }
                    break;
            }
        };

        const filters = [
            { id: 'pending_ok,auto_ready', label: 'Queue', count: (stats.pending || 0) + (stats.auto_ready || 0) },
            { id: 'pending_ok', label: 'Needs Review', count: stats.pending || 0 },
            { id: 'auto_ready', label: 'Auto Ready', count: stats.auto_ready || 0 },
            { id: 'published', label: 'Published', count: stats.published || 0 },
            { id: 'rejected', label: 'Rejected', count: stats.rejected || 0 },
        ];

        return h('div', null,
            h('div', { className: 'aincc-header' },
                h('h1', null, 'Dashboard'),
                h('button', {
                    className: 'aincc-btn aincc-btn-secondary',
                    onClick: loadData,
                }, Icons.refresh, ' Refresh')
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
                    h('p', null, 'Loading...')
                ) : drafts.length === 0 ? h('div', { className: 'aincc-empty' },
                    h('div', { className: 'aincc-empty-icon' }, '📭'),
                    h('p', { className: 'aincc-empty-text' }, 'No articles found')
                ) : h('table', { className: 'aincc-table' },
                    h('thead', null,
                        h('tr', null,
                            h('th', null, 'Date'),
                            h('th', null, 'Status'),
                            h('th', null, 'Title'),
                            h('th', null, 'Source'),
                            h('th', null, 'Actions')
                        )
                    ),
                    h('tbody', null,
                        drafts.map(draft =>
                            h(DraftRow, { key: draft.id, draft, onAction: handleAction })
                        )
                    )
                )
            ),
            total > 10 && h('div', { className: 'aincc-pagination' },
                h('button', {
                    disabled: page === 1,
                    onClick: () => setPage(p => p - 1),
                }, '← Previous'),
                h('span', { style: { color: '#94a3b8', padding: '8px 16px' } },
                    `Page ${page} of ${Math.ceil(total / 10)}`
                ),
                h('button', {
                    disabled: page >= Math.ceil(total / 10),
                    onClick: () => setPage(p => p + 1),
                }, 'Next →')
            ),
            selectedDraft && h(PreviewModal, {
                draft: selectedDraft,
                onClose: () => setSelectedDraft(null),
                onAction: handleAction,
            })
        );
    };

    // Create Article page
    const CreateArticlePage = () => {
        const [formData, setFormData] = useState({
            title: '',
            lead: '',
            body: '',
            source_lang: 'de',
            target_langs: ['de', 'ua', 'ru', 'en'],
            category: 'gesellschaft',
            tags: [],
        });
        const [loading, setLoading] = useState(false);

        const handleSubmit = async (e) => {
            e.preventDefault();
            if (!formData.title || !formData.body) {
                toasts.error('Title and content are required');
                return;
            }

            setLoading(true);
            try {
                const result = await api.post('/articles/create', formData);
                toasts.success('Article created and processing!');
                setFormData({
                    title: '',
                    lead: '',
                    body: '',
                    source_lang: 'de',
                    target_langs: ['de', 'ua', 'ru', 'en'],
                    category: 'gesellschaft',
                    tags: [],
                });
            } catch (error) {
                toasts.error('Failed to create article: ' + error.message);
            }
            setLoading(false);
        };

        return h('div', null,
            h('div', { className: 'aincc-header' },
                h('h1', null, 'Create Article')
            ),
            h('form', { onSubmit: handleSubmit, style: { maxWidth: 800 } },
                h('div', { className: 'aincc-form-group' },
                    h('label', { className: 'aincc-label' }, 'Source Language'),
                    h('select', {
                        className: 'aincc-select',
                        value: formData.source_lang,
                        onChange: (e) => setFormData({ ...formData, source_lang: e.target.value }),
                    },
                        h('option', { value: 'de' }, 'German (DE)'),
                        h('option', { value: 'ua' }, 'Ukrainian (UA)'),
                        h('option', { value: 'ru' }, 'Russian (RU)'),
                        h('option', { value: 'en' }, 'English (EN)')
                    )
                ),
                h('div', { className: 'aincc-form-group' },
                    h('label', { className: 'aincc-label' }, 'Category'),
                    h('select', {
                        className: 'aincc-select',
                        value: formData.category,
                        onChange: (e) => setFormData({ ...formData, category: e.target.value }),
                    },
                        h('option', { value: 'politik' }, 'Politik'),
                        h('option', { value: 'wirtschaft' }, 'Wirtschaft'),
                        h('option', { value: 'gesellschaft' }, 'Gesellschaft'),
                        h('option', { value: 'migration' }, 'Migration'),
                        h('option', { value: 'lokales' }, 'Lokales'),
                        h('option', { value: 'kultur' }, 'Kultur'),
                        h('option', { value: 'verkehr' }, 'Verkehr')
                    )
                ),
                h('div', { className: 'aincc-form-group' },
                    h('label', { className: 'aincc-label' }, 'Title *'),
                    h('input', {
                        type: 'text',
                        className: 'aincc-input',
                        value: formData.title,
                        onChange: (e) => setFormData({ ...formData, title: e.target.value }),
                        placeholder: 'Enter article title',
                        required: true,
                    })
                ),
                h('div', { className: 'aincc-form-group' },
                    h('label', { className: 'aincc-label' }, 'Lead (Summary)'),
                    h('textarea', {
                        className: 'aincc-textarea',
                        value: formData.lead,
                        onChange: (e) => setFormData({ ...formData, lead: e.target.value }),
                        placeholder: '2-3 sentences summarizing the article',
                        rows: 3,
                    })
                ),
                h('div', { className: 'aincc-form-group' },
                    h('label', { className: 'aincc-label' }, 'Content *'),
                    h('textarea', {
                        className: 'aincc-textarea',
                        value: formData.body,
                        onChange: (e) => setFormData({ ...formData, body: e.target.value }),
                        placeholder: 'Full article content...',
                        rows: 10,
                        required: true,
                    })
                ),
                h('div', { className: 'aincc-form-group' },
                    h('label', { className: 'aincc-label' }, 'Translate to'),
                    h('div', { style: { display: 'flex', gap: 16 } },
                        ['de', 'ua', 'ru', 'en'].map(lang =>
                            h('label', { key: lang, style: { color: '#94a3b8', display: 'flex', alignItems: 'center', gap: 8 } },
                                h('input', {
                                    type: 'checkbox',
                                    checked: formData.target_langs.includes(lang),
                                    onChange: (e) => {
                                        const langs = e.target.checked
                                            ? [...formData.target_langs, lang]
                                            : formData.target_langs.filter(l => l !== lang);
                                        setFormData({ ...formData, target_langs: langs });
                                    },
                                }),
                                lang.toUpperCase()
                            )
                        )
                    )
                ),
                h('div', { style: { marginTop: 24 } },
                    h('button', {
                        type: 'submit',
                        className: 'aincc-btn aincc-btn-primary',
                        disabled: loading,
                    }, loading ? 'Processing...' : 'Create & Process Article')
                )
            )
        );
    };

    // Analytics page
    const AnalyticsPage = () => {
        const [data, setData] = useState(null);
        const [loading, setLoading] = useState(true);

        useEffect(() => {
            api.get('/analytics?period=7d')
                .then(setData)
                .catch(() => toasts.error('Failed to load analytics'))
                .finally(() => setLoading(false));
        }, []);

        if (loading) {
            return h('div', { className: 'aincc-loading' },
                h('div', { className: 'aincc-spinner' }),
                h('p', null, 'Loading analytics...')
            );
        }

        return h('div', null,
            h('div', { className: 'aincc-header' },
                h('h1', null, 'Analytics')
            ),
            h(DashboardStats, { stats: data?.stats || {} }),
            h('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24, marginTop: 24 } },
                h('div', { className: 'aincc-stat-card' },
                    h('h3', { style: { color: 'white', marginBottom: 16 } }, 'Top Categories'),
                    (data?.categories || []).map((cat, i) =>
                        h('div', {
                            key: cat.category,
                            style: { display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid #334155', color: '#e2e8f0' }
                        },
                            h('span', null, cat.category || 'Unknown'),
                            h('span', { style: { color: '#3b82f6' } }, cat.count)
                        )
                    )
                ),
                h('div', { className: 'aincc-stat-card' },
                    h('h3', { style: { color: 'white', marginBottom: 16 } }, 'Top Sources'),
                    (data?.sources || []).map((src, i) =>
                        h('div', {
                            key: src.name,
                            style: { display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: '1px solid #334155', color: '#e2e8f0' }
                        },
                            h('span', null, src.name),
                            h('span', { style: { color: '#22c55e' } }, src.article_count, ' articles')
                        )
                    )
                )
            )
        );
    };

    // Settings page placeholder (uses WP native settings page)
    const SettingsPage = () => {
        return h('div', null,
            h('div', { className: 'aincc-header' },
                h('h1', null, 'Settings')
            ),
            h('div', { className: 'aincc-stat-card', style: { maxWidth: 600 } },
                h('p', { style: { color: '#94a3b8', marginBottom: 16 } },
                    'Plugin settings are managed through the WordPress settings page.'
                ),
                h('a', {
                    href: ainccData.adminUrl + 'admin.php?page=ai-news-center-settings',
                    className: 'aincc-btn aincc-btn-primary',
                }, 'Go to Settings')
            )
        );
    };

    // Main App component
    const App = () => {
        const [currentPage, setCurrentPage] = useState(ainccData.currentPage || 'dashboard');

        const renderPage = () => {
            switch (currentPage) {
                case 'dashboard': return h(DashboardPage);
                case 'create': return h(CreateArticlePage);
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

    // Initialize app
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('aincc-root');
        if (root) {
            toasts.init();
            createRoot(root).render(h(App));
        }
    });
})();
