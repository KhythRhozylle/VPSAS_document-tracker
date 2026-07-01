/**
 * VPSAS Monitoring & Tracking System - Main JavaScript
 */
import './styles/app.css';
import { generateCsrfHeaders, generateCsrfToken } from './controllers/csrf_protection_controller.js';

document.addEventListener('DOMContentLoaded', () => {
    initLiveClock();
    initSidebar();
    initNavbar();
    initReportsPage();
    initActivityLogsPage();
    initHelpCenter();
    initDocumentForm();
});

function initLiveClock() {
    const elements = document.querySelectorAll('[data-live-clock]');
    if (!elements.length) {
        return;
    }

    let offsetMs = 0;
    const formatter = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Manila',
        month: 'long',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    });

    const render = (date) => {
        const parts = formatter.formatToParts(date);
        const get = (type) => parts.find((part) => part.type === type)?.value || '';
        const text = `${get('month')} ${get('day')}, ${get('year')} | ${get('hour')}:${get('minute')}:${get('second')} ${get('dayPeriod')}`;
        elements.forEach((element) => {
            element.textContent = text;
        });
    };

    fetch('/api/server-time', { headers: { Accept: 'application/json' } })
        .then((response) => response.json())
        .then((data) => {
            offsetMs = (data.timestamp * 1000) - Date.now();
            render(new Date(Date.now() + offsetMs));
        })
        .catch(() => render(new Date()));

    setInterval(() => {
        render(new Date(Date.now() + offsetMs));
    }, 1000);
}

function showExportLoading() {
    document.getElementById('export-loading-overlay')?.classList.remove('d-none');
}

function hideExportLoading() {
    document.getElementById('export-loading-overlay')?.classList.add('d-none');
}

function initDocumentForm() {
    document.querySelectorAll('form.document-form, #document-edit-form').forEach((form) => {
        bindDocumentTypeOtherField(form);
    });
}

function bindDocumentTypeOtherField(form) {
    const typeSelect = form.querySelector('[name*="[documentType]"]');
    const otherWrap = form.closest('.row')?.querySelector('.document-type-other-wrap')
        || form.querySelector('.document-type-other-wrap')
        || form.querySelector('[name*="[documentTypeOther]"]')?.closest('.mb-3');

    if (!typeSelect || !otherWrap) {
        return;
    }

    const toggleOther = () => {
        const isOther = typeSelect.value === 'Other';
        otherWrap.classList.toggle('d-none', !isOther);
        const input = otherWrap.querySelector('input');
        if (input) {
            input.required = isOther;
        }
    };

    typeSelect.addEventListener('change', toggleOther);
    toggleOther();
}

function initHelpCenter() {
    const root = document.getElementById('help-center-root');
    if (!root) {
        return;
    }

    const searchInput = document.getElementById('help-search');
    const items = root.querySelectorAll('[data-help-item]');

    searchInput?.addEventListener('input', () => {
        const query = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        items.forEach((item) => {
            const haystack = item.textContent?.toLowerCase() || '';
            const tags = item.dataset.helpTags?.toLowerCase() || '';
            const visible = query === '' || haystack.includes(query) || tags.includes(query);
            item.classList.toggle('d-none', !visible);
            if (visible) {
                visibleCount += 1;
            }
        });

        const empty = document.getElementById('help-search-empty');
        if (empty) {
            empty.classList.toggle('d-none', visibleCount > 0 || query === '');
        }
    });
}

function initSidebar() {
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (!toggle || !sidebar || !overlay) {
        return;
    }

    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    });

    overlay.addEventListener('click', () => {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    });
}

function initNavbar() {
    initNavbarSearch();
    initNavbarNotifications();
    initNavbarProfile();

    document.getElementById('sidebar-profile-link')?.addEventListener('click', (event) => {
        event.preventDefault();
        openProfileModal();
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.navbar-dropdown-wrap') && !event.target.closest('#navbar-search-form')) {
            closeNavbarDropdowns();
        }
    });
}

function initNavbarSearch() {
    const input = document.getElementById('navbar-search-input');
    const dropdown = document.getElementById('navbar-search-dropdown');
    const form = document.getElementById('navbar-search-form');

    if (!input || !dropdown || !form) {
        return;
    }

    const reportsSearch = document.getElementById('reports-search');
    const urlParams = new URLSearchParams(window.location.search);
    const initialQuery = urlParams.get('q') || '';
    if (initialQuery && !input.value) {
        input.value = initialQuery;
    }
    if (reportsSearch && initialQuery && !reportsSearch.value) {
        reportsSearch.value = initialQuery;
    }

    let searchTimeout;

    input.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        const query = input.value.trim();

        if (query.length === 0) {
            dropdown.classList.add('d-none');
            dropdown.innerHTML = '';
            return;
        }

        searchTimeout = setTimeout(async () => {
            try {
                const response = await fetch(`/search?q=${encodeURIComponent(query)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                dropdown.innerHTML = await response.text();
                dropdown.classList.remove('d-none');
            } catch (error) {
                console.error('Search failed:', error);
            }
        }, 250);
    });

    input.addEventListener('focus', () => {
        if (input.value.trim() && dropdown.innerHTML) {
            dropdown.classList.remove('d-none');
        }
    });

    form.addEventListener('submit', (event) => {
        const query = input.value.trim();
        if (!query) {
            event.preventDefault();
            return;
        }

        if (document.getElementById('reports-root')) {
            event.preventDefault();
            const reportsSearchInput = document.getElementById('reports-search');
            if (reportsSearchInput) {
                reportsSearchInput.value = query;
                loadReportsTable(1);
            } else {
                window.location.href = `/reports?q=${encodeURIComponent(query)}`;
            }
        }
    });

    dropdown.addEventListener('click', (event) => {
        const link = event.target.closest('a.search-dropdown-item, a.search-dropdown-footer');
        if (!link) {
            return;
        }

        if (document.getElementById('reports-root')) {
            event.preventDefault();
            const query = input.value.trim();
            const reportsSearchInput = document.getElementById('reports-search');
            if (reportsSearchInput) {
                reportsSearchInput.value = query;
                loadReportsTable(1);
            }
            dropdown.classList.add('d-none');
        }
    });
}

function initNavbarNotifications() {
    const btn = document.getElementById('navbar-notifications-btn');
    const dropdown = document.getElementById('navbar-notifications-dropdown');

    if (!btn || !dropdown) {
        return;
    }

    btn.addEventListener('click', async (event) => {
        event.stopPropagation();
        const isOpen = !dropdown.classList.contains('d-none');

        closeNavbarDropdowns();

        if (isOpen) {
            return;
        }

        dropdown.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
        dropdown.classList.remove('d-none');
        btn.setAttribute('aria-expanded', 'true');

        try {
            const response = await fetch('/notifications', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            dropdown.innerHTML = await response.text();
            bindNotificationEvents(dropdown);
        } catch (error) {
            dropdown.innerHTML = '<div class="navbar-dropdown-empty"><p>Failed to load notifications.</p></div>';
        }
    });
}

function bindNotificationEvents(container) {
    container.querySelector('#notifications-mark-all-btn')?.addEventListener('click', async (event) => {
        event.stopPropagation();
        const token = event.currentTarget.dataset.token;

        try {
            const response = await fetch('/notifications/read-all', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({ _token: token }),
            });
            const data = await response.json();

            if (data.success) {
                updateUnreadBadge(0);
                container.querySelectorAll('.notification-item--unread').forEach((item) => {
                    item.classList.remove('notification-item--unread');
                    item.querySelector('.notification-dot')?.remove();
                });
                container.querySelector('#notifications-mark-all-btn')?.remove();
            }
        } catch (error) {
            showToast('Failed to mark notifications as read.', 'danger');
        }
    });

    container.querySelectorAll('.notification-item').forEach((item) => {
        item.addEventListener('click', async () => {
            const id = item.dataset.id;
            const link = item.dataset.link;

            if (id && item.classList.contains('notification-item--unread')) {
                try {
                    const response = await fetch(`/notifications/${id}/read`, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await response.json();
                    if (data.success) {
                        item.classList.remove('notification-item--unread');
                        item.querySelector('.notification-dot')?.remove();
                        updateUnreadBadge(data.unreadCount);
                    }
                } catch (error) {
                    console.error('Failed to mark notification as read:', error);
                }
            }

            if (link) {
                window.location.href = link;
            }
        });
    });
}

function initNavbarProfile() {
    document.getElementById('navbar-profile-btn')?.addEventListener('click', (event) => {
        event.stopPropagation();
        closeNavbarDropdowns();
        openProfileModal();
    });
}

async function openProfileModal() {
    const modalEl = document.getElementById('profileModal');
    const body = document.getElementById('profile-modal-body');

    if (!modalEl || !body) {
        return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    modal.show();

    try {
        const response = await fetch('/profile', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        body.innerHTML = await response.text();
        bindProfileForm(body);
    } catch (error) {
        body.innerHTML = '<div class="alert alert-danger">Failed to load profile.</div>';
    }
}

function bindProfileForm(container) {
    const form = container.querySelector('#profile-form');
    const fileInput = container.querySelector('#profile_picture_file') ?? container.querySelector('input[type="file"]');
    const fileNameLabel = container.querySelector('#profile-file-name');
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    const maxFileSize = 5 * 1024 * 1024;

    fileInput?.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        if (!file) {
            if (fileNameLabel) {
                fileNameLabel.textContent = 'No file chosen';
            }
            return;
        }

        if (!allowedTypes.includes(file.type)) {
            showToast('Please upload a JPG, JPEG, PNG, or WEBP image.', 'danger');
            fileInput.value = '';
            if (fileNameLabel) {
                fileNameLabel.textContent = 'No file chosen';
            }
            return;
        }

        if (file.size > maxFileSize) {
            showToast('Profile picture must be 5 MB or smaller.', 'danger');
            fileInput.value = '';
            if (fileNameLabel) {
                fileNameLabel.textContent = 'No file chosen';
            }
            return;
        }

        if (fileNameLabel) {
            fileNameLabel.textContent = file.name;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            const preview = container.querySelector('#profile-avatar-preview');
            if (preview) {
                preview.innerHTML = `<img src="${event.target.result}" alt="Preview" id="profile-avatar-img">`;
            }
        };
        reader.readAsDataURL(file);
    });

    container.querySelector('#profile-avatar-edit-btn')?.addEventListener('click', () => {
        fileInput?.click();
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const saveBtn = container.querySelector('#profile-save-btn');
        saveBtn?.setAttribute('disabled', 'disabled');

        try {
            generateCsrfToken(form);
            const csrfHeaders = generateCsrfHeaders(form);

            const response = await fetch(form.action || '/profile', {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    ...csrfHeaders,
                },
                credentials: 'same-origin',
            });

            let data;
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const text = await response.text();
                throw new Error(text.slice(0, 200) || `Server error (${response.status})`);
            }

            if (response.ok && data.success) {
                showToast(data.message, 'success');
                updateNavbarProfile(data.user);
                updateProfileModalPreview(container, data.user);
                const updatedAtEl = container.querySelector('#profile-updated-at');
                if (updatedAtEl && data.user.updatedAt) {
                    updatedAtEl.textContent = data.user.updatedAt;
                }
                bootstrap.Modal.getInstance(document.getElementById('profileModal'))?.hide();
            } else {
                const errorMessage = data.message
                    || Object.values(data.errors || {}).join(' ')
                    || `Unable to save profile (${response.status})`;
                showToast(errorMessage, 'danger');
            }
        } catch (error) {
            showToast(error.message || 'Failed to update profile.', 'danger');
        } finally {
            saveBtn?.removeAttribute('disabled');
        }
    });
}

function updateProfileModalPreview(container, user) {
    if (!container || !user) {
        return;
    }

    const preview = container.querySelector('#profile-avatar-preview');
    const displayName = container.querySelector('#profile-display-name');
    const displayEmail = container.querySelector('#profile-display-email');
    const pictureUrl = user.profilePictureUrlWithCache || user.profilePictureUrl;

    if (displayName) {
        displayName.textContent = user.fullName;
    }
    if (displayEmail) {
        displayEmail.textContent = user.email;
    }

    if (!preview) {
        return;
    }

    if (pictureUrl) {
        preview.innerHTML = `<img src="${escapeHtml(pictureUrl)}" alt="${escapeHtml(user.fullName)}" id="profile-avatar-img">`;
    } else {
        preview.innerHTML = `<span class="profile-avatar-initials" id="profile-avatar-initials">${escapeHtml(user.initials)}</span>`;
    }
}

function updateNavbarProfile(user) {
    const profileBtn = document.getElementById('navbar-profile-btn');
    if (!profileBtn || !user) {
        return;
    }

    const pictureUrl = user.profilePictureUrlWithCache || user.profilePictureUrl;
    if (pictureUrl) {
        profileBtn.innerHTML = `<img src="${escapeHtml(pictureUrl)}" alt="${escapeHtml(user.fullName)}" class="navbar-avatar-img" id="navbar-avatar-img">`;
    } else {
        profileBtn.innerHTML = `<span class="navbar-avatar-initials" id="navbar-avatar-initials">${escapeHtml(user.initials)}</span>`;
    }
}

function updateUnreadBadge(count) {
    const badge = document.getElementById('navbar-unread-badge');
    if (!badge) {
        return;
    }

    if (count > 0) {
        badge.textContent = count > 9 ? '9+' : String(count);
        badge.classList.remove('d-none');
    } else {
        badge.classList.add('d-none');
    }
}

function closeNavbarDropdowns() {
    document.getElementById('navbar-notifications-dropdown')?.classList.add('d-none');
    document.getElementById('navbar-search-dropdown')?.classList.add('d-none');
    document.getElementById('navbar-notifications-btn')?.setAttribute('aria-expanded', 'false');
}

function initReportsPage() {
    const reportsRoot = document.getElementById('reports-root');
    if (!reportsRoot) {
        return;
    }

    let searchTimeout;
    const filterIds = [
        'reports-search', 'reports-date-from', 'reports-date-to', 'reports-campus',
        'reports-document-type', 'reports-status', 'reports-user-role', 'reports-sort', 'reports-per-page',
    ];

    filterIds.forEach((id) => {
        const element = document.getElementById(id);
        element?.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => loadReportsTable(1), 300);
        });
        element?.addEventListener('change', () => loadReportsTable(1));
    });

    document.getElementById('reports-table-container')?.addEventListener('click', (event) => {
        const target = event.target.closest('[data-action]');
        if (!target || target.closest('.disabled')) {
            return;
        }

        const action = target.dataset.action;
        const id = target.dataset.id;

        if (action === 'sort') {
            handleSort(target.dataset.sort);
        } else if (action === 'page') {
            loadReportsTable(parseInt(target.dataset.page, 10));
        } else if (action === 'view') {
            openViewModal(id);
        } else if (action === 'edit') {
            openEditModal(id);
        } else if (action === 'delete') {
            openDeleteModal(id, target.dataset.campus || '', target.dataset.token || '');
        }
    });

    document.querySelectorAll('.content-card .export-btn').forEach((button) => {
        button.addEventListener('click', () => handleReportExport(button.dataset.exportType));
    });

    document.getElementById('edit-form-container')?.addEventListener('submit', async (event) => {
        if (event.target.id !== 'document-edit-form') {
            return;
        }
        event.preventDefault();
        await submitEditForm(event.target);
    });

    document.getElementById('confirm-delete-btn')?.addEventListener('click', confirmDelete);
}

function handleReportExport(type) {
    const params = getReportsParams(false);
    showExportLoading();

    if (type === 'print') {
        window.open(`/reports/print?${params.toString()}`, '_blank');
        hideExportLoading();
        return;
    }

    if (type === 'pdf') {
        window.open(`/reports/export/pdf?${params.toString()}`, '_blank');
        hideExportLoading();
        return;
    }

    if (type === 'excel') {
        window.location.href = `/reports/export/excel?${params.toString()}`;
        setTimeout(hideExportLoading, 1200);
        return;
    }

    if (type === 'csv') {
        window.location.href = `/reports/export/csv?${params.toString()}`;
        setTimeout(hideExportLoading, 1200);
    }
}

function getReportsState() {
    const root = document.getElementById('reports-root');
    const sortSelect = document.getElementById('reports-sort');

    return {
        search: document.getElementById('reports-search')?.value || '',
        dateFrom: document.getElementById('reports-date-from')?.value || '',
        dateTo: document.getElementById('reports-date-to')?.value || '',
        campus: document.getElementById('reports-campus')?.value || '',
        documentType: document.getElementById('reports-document-type')?.value || '',
        status: document.getElementById('reports-status')?.value || '',
        userRole: document.getElementById('reports-user-role')?.value || '',
        sort: root?.dataset.sort || 'date_approved',
        direction: sortSelect?.value || root?.dataset.direction || 'desc',
        page: parseInt(root?.dataset.page || '1', 10),
        perPage: document.getElementById('reports-per-page')?.value || root?.dataset.perPage || '10',
    };
}

function getReportsParams(includePage = true) {
    const state = getReportsState();
    const params = new URLSearchParams();

    if (state.search) params.set('q', state.search);
    if (state.dateFrom) params.set('date_from', state.dateFrom);
    if (state.dateTo) params.set('date_to', state.dateTo);
    if (state.campus) params.set('campus', state.campus);
    if (state.documentType) params.set('document_type', state.documentType);
    if (state.status) params.set('status', state.status);
    if (state.userRole) params.set('user_role', state.userRole);
    params.set('sort', state.sort);
    params.set('direction', state.direction);
    if (state.perPage) params.set('per_page', state.perPage);
    if (includePage) params.set('page', String(state.page));

    return params;
}

async function loadReportsTable(page = 1) {
    const container = document.getElementById('reports-table-container');
    const root = document.getElementById('reports-root');
    if (!container || !root) {
        return;
    }

    const params = getReportsParams();
    params.set('page', String(page));

    container.classList.add('opacity-50');

    try {
        const response = await fetch(`/reports/data?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        container.innerHTML = await response.text();
        root.dataset.page = String(page);
        root.dataset.direction = getReportsState().direction;
        root.dataset.perPage = getReportsState().perPage;
    } catch (error) {
        console.error('Failed to load reports:', error);
    } finally {
        container.classList.remove('opacity-50');
    }
}

function handleSort(sortField) {
    const root = document.getElementById('reports-root');
    if (!root) {
        return;
    }

    if (root.dataset.sort === sortField) {
        root.dataset.direction = root.dataset.direction === 'asc' ? 'desc' : 'asc';
    } else {
        root.dataset.sort = sortField;
        root.dataset.direction = 'asc';
    }

    loadReportsTable(1);
}

async function openViewModal(id) {
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('viewModal'));
    const body = document.getElementById('view-modal-body');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    modal.show();

    try {
        const response = await fetch(`/documents/${id}`);
        const data = await response.json();
        body.innerHTML = `
            <div class="detail-row"><span class="detail-label">Date Approved</span><span class="detail-value">${escapeHtml(data.dateApproved)}</span></div>
            <div class="detail-row"><span class="detail-label">Campus</span><span class="detail-value">${escapeHtml(data.campus)}</span></div>
            <div class="detail-row"><span class="detail-label">Document Type</span><span class="detail-value">${escapeHtml(data.documentType)}</span></div>
            <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value">${escapeHtml(data.status)}</span></div>
            <div class="detail-row"><span class="detail-label">Particulars</span><span class="detail-value">${escapeHtml(data.particulars)}</span></div>
            <div class="detail-row"><span class="detail-label">Amount</span><span class="detail-value">₱${escapeHtml(formatAmount(data.amount))}</span></div>
            <div class="detail-row"><span class="detail-label">Nature</span><span class="detail-value">${escapeHtml(data.nature)}</span></div>
            <div class="detail-row"><span class="detail-label">Created At</span><span class="detail-value">${escapeHtml(data.createdAt)}</span></div>
            <div class="detail-row"><span class="detail-label">Updated At</span><span class="detail-value">${escapeHtml(data.updatedAt)}</span></div>
        `;
    } catch (error) {
        body.innerHTML = '<div class="alert alert-danger">Failed to load document details.</div>';
    }
}

async function openEditModal(id) {
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal'));
    const container = document.getElementById('edit-form-container');
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    modal.show();

    try {
        const response = await fetch(`/documents/${id}/edit`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        container.innerHTML = await response.text();
        const editForm = container.querySelector('#document-edit-form') || container.querySelector('form');
        if (editForm) {
            bindDocumentTypeOtherField(editForm);
        }
    } catch (error) {
        container.innerHTML = '<div class="alert alert-danger">Failed to load edit form.</div>';
    }
}

function openDeleteModal(id, campus, token) {
    document.getElementById('delete-document-id').value = id;
    document.getElementById('delete-csrf-token').value = token;
    document.getElementById('delete-document-label').textContent = campus || `Document #${id}`;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteModal')).show();
}

async function submitEditForm(form) {
    const formData = new FormData(form);
    const id = form.action.match(/\/documents\/(\d+)\/edit/)?.[1];

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await response.json();

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editModal'))?.hide();
            showToast(data.message, 'success');
            loadReportsTable(getReportsState().page);
        } else {
            showToast(Object.values(data.errors || {}).join(' ') || 'Validation failed.', 'danger');
        }
    } catch (error) {
        showToast('Failed to update document.', 'danger');
    }
}

async function confirmDelete() {
    const id = document.getElementById('delete-document-id').value;
    const token = document.getElementById('delete-csrf-token').value;

    try {
        const response = await fetch(`/documents/${id}/delete`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({ _token: token }),
        });
        const data = await response.json();

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('deleteModal'))?.hide();
            showToast(data.message, 'success');
            loadReportsTable(getReportsState().page);
        } else {
            showToast(data.message || 'Failed to delete document.', 'danger');
        }
    } catch (error) {
        showToast('Failed to delete document.', 'danger');
    }
}

function showToast(message, type = 'success') {
    const toastEl = document.getElementById('app-toast');
    if (!toastEl) {
        return;
    }
    toastEl.className = `toast align-items-center text-bg-${type} border-0`;
    toastEl.querySelector('.toast-body').textContent = message;
    bootstrap.Toast.getOrCreateInstance(toastEl).show();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function formatAmount(amount) {
    return parseFloat(amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function initActivityLogsPage() {
    const root = document.getElementById('activity-logs-root');
    if (!root) {
        return;
    }

    let filterTimeout;

    ['activity-logs-search', 'activity-logs-role', 'activity-logs-action', 'activity-logs-date-from', 'activity-logs-date-to', 'activity-logs-sort', 'activity-logs-per-page'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', () => {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(() => loadActivityLogsTable(1), 300);
        });
        document.getElementById(id)?.addEventListener('change', () => loadActivityLogsTable(1));
    });

    document.getElementById('activity-logs-table-container')?.addEventListener('click', (event) => {
        const target = event.target.closest('[data-action]');
        if (!target || target.closest('.disabled')) {
            return;
        }

        if (target.dataset.action === 'page') {
            loadActivityLogsTable(parseInt(target.dataset.page, 10));
        } else if (target.dataset.action === 'view-log') {
            openActivityLogModal(target.dataset.id);
        }
    });

    document.querySelectorAll('.activity-logs-toolbar .export-btn').forEach((button) => {
        button.addEventListener('click', () => handleActivityLogExport(button.dataset.exportType));
    });
}

function openExportWindow(url) {
    const popup = window.open(url, '_blank', 'noopener,noreferrer');
    if (!popup) {
        showToast('Pop-up blocked. Allow pop-ups for this site to open print/PDF exports.', 'danger');
        window.location.href = url;
    }
}

function handleActivityLogExport(type) {
    const params = getActivityLogsParams(false);
    showExportLoading();

    try {
        if (type === 'print') {
            openExportWindow(`/activity-logs/print?${params.toString()}`);
            hideExportLoading();
            return;
        }

        if (type === 'pdf') {
            openExportWindow(`/activity-logs/export/pdf?${params.toString()}`);
            hideExportLoading();
            return;
        }

        if (type === 'excel') {
            window.location.assign(`/activity-logs/export/excel?${params.toString()}`);
            setTimeout(hideExportLoading, 1500);
            return;
        }

        if (type === 'csv') {
            window.location.assign(`/activity-logs/export/csv?${params.toString()}`);
            setTimeout(hideExportLoading, 1500);
        }
    } catch (error) {
        hideExportLoading();
        showToast('Export failed. Please try again.', 'danger');
        console.error('Activity log export failed:', error);
    }
}

function getActivityLogsState() {
    const root = document.getElementById('activity-logs-root');
    const sortSelect = document.getElementById('activity-logs-sort');

    return {
        search: document.getElementById('activity-logs-search')?.value || '',
        role: document.getElementById('activity-logs-role')?.value || '',
        action: document.getElementById('activity-logs-action')?.value || '',
        dateFrom: document.getElementById('activity-logs-date-from')?.value || '',
        dateTo: document.getElementById('activity-logs-date-to')?.value || '',
        sort: root?.dataset.sort || 'created_at',
        direction: sortSelect?.value || root?.dataset.direction || 'desc',
        page: parseInt(root?.dataset.page || '1', 10),
        perPage: document.getElementById('activity-logs-per-page')?.value || root?.dataset.perPage || '25',
    };
}

function getActivityLogsParams(includePage = true) {
    const state = getActivityLogsState();
    const params = new URLSearchParams();

    if (state.search) params.set('q', state.search);
    if (state.role) params.set('role', state.role);
    if (state.action) params.set('action', state.action);
    if (state.dateFrom) params.set('date_from', state.dateFrom);
    if (state.dateTo) params.set('date_to', state.dateTo);
    params.set('sort', state.sort);
    params.set('direction', state.direction);
    if (state.perPage) params.set('per_page', state.perPage);
    if (includePage) params.set('page', String(state.page));

    return params;
}

async function loadActivityLogsTable(page = 1) {
    const container = document.getElementById('activity-logs-table-container');
    const root = document.getElementById('activity-logs-root');
    if (!container || !root) {
        return;
    }

    const params = getActivityLogsParams();
    params.set('page', String(page));

    container.classList.add('opacity-50');

    try {
        const response = await fetch(`/activity-logs/data?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        container.innerHTML = await response.text();
        root.dataset.page = String(page);
        root.dataset.direction = getActivityLogsState().direction;
        root.dataset.perPage = getActivityLogsState().perPage;
    } catch (error) {
        console.error('Failed to load activity logs:', error);
    } finally {
        container.classList.remove('opacity-50');
    }
}

async function openActivityLogModal(id) {
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('activityLogModal'));
    const body = document.getElementById('activity-log-modal-body');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    modal.show();

    try {
        const response = await fetch(`/activity-logs/${id}`, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await response.json();

        body.innerHTML = `
            <div class="detail-row"><span class="detail-label">Date & Time</span><span class="detail-value">${escapeHtml(data.createdAt)}</span></div>
            <div class="detail-row"><span class="detail-label">User</span><span class="detail-value">${escapeHtml(data.fullName)}</span></div>
            <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value">${escapeHtml(data.email)}</span></div>
            <div class="detail-row"><span class="detail-label">Role</span><span class="detail-value">${escapeHtml(data.role)}</span></div>
            <div class="detail-row"><span class="detail-label">Action</span><span class="detail-value">${escapeHtml(data.action)}</span></div>
            <div class="detail-row"><span class="detail-label">Module</span><span class="detail-value">${escapeHtml(data.module)}</span></div>
            <div class="detail-row"><span class="detail-label">Record ID</span><span class="detail-value">${escapeHtml(String(data.recordId ?? '—'))}</span></div>
            <div class="detail-row"><span class="detail-label">Description</span><span class="detail-value">${escapeHtml(data.description ?? '—')}</span></div>
            <div class="detail-row"><span class="detail-label">IP Address</span><span class="detail-value">${escapeHtml(data.ipAddress ?? '—')}</span></div>
            <div class="detail-row"><span class="detail-label">User Agent</span><span class="detail-value">${escapeHtml(data.userAgent ?? '—')}</span></div>
            <div class="mt-3">
                <h6 class="text-white">Previous Data</h6>
                <pre class="activity-log-json">${escapeHtml(JSON.stringify(data.oldData ?? {}, null, 2))}</pre>
            </div>
            <div class="mt-3">
                <h6 class="text-white">New Data</h6>
                <pre class="activity-log-json">${escapeHtml(JSON.stringify(data.newData ?? {}, null, 2))}</pre>
            </div>
        `;
    } catch (error) {
        body.innerHTML = '<div class="alert alert-danger">Failed to load log details.</div>';
    }
}

// Expose for inline page scripts
window.initActivityLogsPage = initActivityLogsPage;
