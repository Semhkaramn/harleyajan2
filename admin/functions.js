// Admin Panel JavaScript Functions - Enhanced with Custom Popup System

// Global variables
let allSites = [];
let currentTargetSection = '';

// Custom Popup System
class CustomPopup {
    constructor() {
        this.createPopupElement();
    }

    createPopupElement() {
        if (document.getElementById('customPopup')) return;

        const popupHTML = `
            <div id="customPopup" class="custom-popup">
                <div class="popup-content">
                    <div class="popup-icon" id="popupIcon"></div>
                    <h3 class="popup-title" id="popupTitle"></h3>
                    <p class="popup-message" id="popupMessage"></p>
                    <div class="popup-buttons" id="popupButtons"></div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', popupHTML);
    }

    show(options = {}) {
        const {
            type = 'info',
            title = 'Bilgi',
            message = '',
            buttons = [{ text: 'Tamam', class: 'btn-primary', action: () => this.hide() }],
            closeOnBackdrop = true
        } = options;

        const popup = document.getElementById('customPopup');
        const icon = document.getElementById('popupIcon');
        const titleEl = document.getElementById('popupTitle');
        const messageEl = document.getElementById('popupMessage');
        const buttonsEl = document.getElementById('popupButtons');

        // Set icon
        const icons = {
            success: '✓',
            warning: '⚠',
            danger: '✗',
            info: 'ℹ',
            question: '?'
        };

        icon.textContent = icons[type] || icons.info;
        icon.className = `popup-icon ${type}`;

        titleEl.textContent = title;
        messageEl.textContent = message;

        // Create buttons
        buttonsEl.innerHTML = '';
        buttons.forEach(btn => {
            const button = document.createElement('button');
            button.className = `btn ${btn.class || 'btn-secondary'}`;
            button.textContent = btn.text;
            button.onclick = () => {
                if (btn.action) btn.action();
                this.hide();
            };
            buttonsEl.appendChild(button);
        });

        // Show popup
        popup.classList.add('show');

        // Close on backdrop click
        if (closeOnBackdrop) {
            popup.onclick = (e) => {
                if (e.target === popup) this.hide();
            };
        }

        // Close on escape key
        document.addEventListener('keydown', this.escapeHandler);
    }

    hide() {
        const popup = document.getElementById('customPopup');
        popup.classList.remove('show');
        document.removeEventListener('keydown', this.escapeHandler);
    }

    escapeHandler = (e) => {
        if (e.key === 'Escape') {
            this.hide();
        }
    }

    confirm(title, message, onConfirm, onCancel = null) {
        this.show({
            type: 'question',
            title,
            message,
            buttons: [
                {
                    text: 'İptal',
                    class: 'btn-secondary',
                    action: onCancel
                },
                {
                    text: 'Tamam',
                    class: 'btn-primary',
                    action: onConfirm
                }
            ],
            closeOnBackdrop: false
        });
    }

    alert(title, message, type = 'info') {
        this.show({
            type,
            title,
            message,
            buttons: [{ text: 'Tamam', class: 'btn-primary' }]
        });
    }

    success(message, title = 'Başarılı!') {
        this.alert(title, message, 'success');
    }

    error(message, title = 'Hata!') {
        this.alert(title, message, 'danger');
    }

    warning(message, title = 'Uyarı!') {
        this.alert(title, message, 'warning');
    }
}

// Initialize popup system
const popup = new CustomPopup();

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    loadSites();
    loadSlots();
    loadBanners();
    loadSocialLinks();
    loadSettings();
    initializeEventHandlers();

    // Load admin users if super admin
    if (window.isSuperAdmin) {
        loadAdminUsers();
    }

    // Load popup settings when the popup tab is accessed
    const popupTab = document.getElementById('popup-tab');
    if (popupTab) {
        popupTab.addEventListener('shown.bs.tab', function() {
            loadSites(); // Sites yüklenerek dropdown doldurulur
            loadPopupSettings(); // Popup ayarları yüklenir
        });
    }
});

// Enhanced Toast notification system
function showToast(message, type = 'success') {
    const toastContainer = document.querySelector('.toast-container');
    const toastId = 'toast-' + Date.now();

    const icons = {
        success: '✓',
        danger: '✗',
        warning: '⚠',
        info: 'ℹ'
    };

    const toastHTML = `
        <div class="toast align-items-center text-bg-${type} border-0" role="alert" id="${toastId}" style="opacity: 0; transform: translateX(100%);">
            <div class="d-flex">
                <div class="toast-body">
                    <span class="me-2">${icons[type] || icons.info}</span>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    const toastElement = document.getElementById(toastId);

    // Animate in
    setTimeout(() => {
        toastElement.style.transition = 'all 0.3s ease';
        toastElement.style.opacity = '1';
        toastElement.style.transform = 'translateX(0)';
    }, 10);

    const toast = new bootstrap.Toast(toastElement, { delay: 4000 });
    toast.show();

    // Clean up after hiding
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}

// Modal management functions
function openSiteModal(siteId = null) {
    const modal = new bootstrap.Modal(document.getElementById('siteModal'));
    const title = document.getElementById('siteModalLabel');
    const submitBtn = document.getElementById('siteSubmitBtn');

    if (siteId) {
        title.innerHTML = '<i class="bi bi-pencil"></i> Site Düzenle';
        submitBtn.innerHTML = '<i class="bi bi-save"></i> Güncelle';
        loadSiteForEdit(siteId);
    } else {
        title.innerHTML = '<i class="bi bi-plus-circle"></i> Yeni Site Ekle';
        submitBtn.innerHTML = '<i class="bi bi-save"></i> Kaydet';
        resetSiteForm();
    }

    modal.show();
}

function openSectionSiteModal(section) {
    currentTargetSection = section;
    const modal = new bootstrap.Modal(document.getElementById('sectionSiteModal'));
    const sectionName = document.getElementById('targetSectionName');
    sectionName.textContent = section === 'section1' ? 'Section 1\'e' : 'Section 2\'ye';

    loadAvailableSites(section);
    modal.show();
}

function openBannerModal(bannerId = null) {
    const modal = new bootstrap.Modal(document.getElementById('bannerModal'));
    const title = document.getElementById('bannerModalLabel');
    const submitBtn = document.getElementById('bannerSubmitBtn');

    if (bannerId) {
        title.innerHTML = '<i class="bi bi-pencil"></i> Banner Düzenle';
        submitBtn.innerHTML = '<i class="bi bi-save"></i> Güncelle';
        loadBannerForEdit(bannerId);
    } else {
        title.innerHTML = '<i class="bi bi-plus-circle"></i> Yeni Banner Ekle';
        submitBtn.innerHTML = '<i class="bi bi-save"></i> Kaydet';
        resetBannerForm();
        populateSiteOptions(allSites);
    }

    modal.show();
}

function openSlotModal(slotId = null) {
    const modal = new bootstrap.Modal(document.getElementById('slotModal'));
    const title = document.getElementById('slotModalLabel');
    const submitBtn = document.getElementById('slotSubmitBtn');

    if (slotId) {
        title.innerHTML = '<i class="bi bi-pencil"></i> Slot Düzenle';
        submitBtn.innerHTML = '<i class="bi bi-save"></i> Güncelle';
        loadSlotForEdit(slotId);
    } else {
        title.innerHTML = '<i class="bi bi-plus-circle"></i> Yeni Slot Ekle';
        submitBtn.innerHTML = '<i class="bi bi-save"></i> Kaydet';
        resetSlotForm();
    }

    modal.show();
}

function openAdminUserModal(userId = null) {
    if (!window.isSuperAdmin) {
        popup.error('Bu işlem için yetkiniz yok.');
        return;
    }

    const modal = new bootstrap.Modal(document.getElementById('adminUserModal'));
    const title = document.getElementById('adminUserModalLabel');
    const submitBtn = document.getElementById('adminUserSubmitBtn');

    if (userId) {
        title.innerHTML = '<i class="bi bi-pencil"></i> Admin Düzenle';
        submitBtn.innerHTML = '<i class="bi bi-save"></i> Güncelle';
        loadAdminUserForEdit(userId);
    } else {
        title.innerHTML = '<i class="bi bi-person-plus"></i> Yeni Admin Kullanıcısı';
        submitBtn.innerHTML = '<i class="bi bi-save"></i> Kaydet';
        resetAdminUserForm();
    }

    modal.show();
}

// Enhanced logo preview with support for multiple formats
document.addEventListener('change', function(e) {
    if (e.target.id === 'siteLogo') {
        const file = e.target.files[0];
        const preview = document.getElementById('logoPreview');

        if (file) {
            // Check file type
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'video/webm'];

            if (!validTypes.includes(file.type)) {
                popup.error('Desteklenmeyen dosya formatı. Lütfen JPG, PNG, GIF, WEBP, SVG veya WEBM dosyası seçin.');
                e.target.value = '';
                preview.style.display = 'none';
                return;
            }

            // Check file size (15MB limit)
            if (file.size > 15 * 1024 * 1024) {
                popup.error('Dosya boyutu 15MB\'dan küçük olmalıdır.');
                e.target.value = '';
                preview.style.display = 'none';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';

                // Add loading animation
                preview.style.opacity = '0';
                preview.onload = () => {
                    preview.style.transition = 'opacity 0.3s ease';
                    preview.style.opacity = '1';
                };
            };
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }
    }
});

// Event handlers
function initializeEventHandlers() {
    // Site form
    document.getElementById('siteForm').addEventListener('submit', handleSiteSubmit);

    // Banner form
    document.getElementById('bannerForm').addEventListener('submit', handleBannerSubmit);

    // Slot form
    document.getElementById('slotForm').addEventListener('submit', handleSlotSubmit);

    // Social media form
    document.getElementById('socialForm').addEventListener('submit', handleSocialSubmit);

    // Settings form
    document.getElementById('settingsForm').addEventListener('submit', handleSettingsSubmit);

    // Profile update form
    document.getElementById('profileUpdateForm').addEventListener('submit', handleProfileUpdate);

    // Popup settings form
    document.getElementById('popupForm').addEventListener('submit', handlePopupUpdate);

    // Admin user form (if super admin)
    if (window.isSuperAdmin && document.getElementById('adminUserForm')) {
        document.getElementById('adminUserForm').addEventListener('submit', handleAdminUserSubmit);
    }

    // Social media input changes
    document.querySelectorAll('#socialForm input').forEach(input => {
        input.addEventListener('input', updateSocialPreview);
    });
}

// Profile update form handler
async function handleProfileUpdate(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    formData.append('action', 'update_profile');

    const data = await apiRequest('api.php', {
        method: 'POST',
        body: formData
    });

    if (data && data.success) {
        showToast(data.message);
    } else {
        popup.error(data?.message || 'Bir hata oluştu');
    }
}

// Popup settings form handler
async function handlePopupUpdate(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    formData.append('action', 'update_popup_settings');

    const data = await apiRequest('api.php', {
        method: 'POST',
        body: formData
    });

    if (data && data.success) {
        showToast(data.message);
    } else {
        popup.error(data?.message || 'Bir hata oluştu');
    }
}

// Enhanced API request function with loading states
async function apiRequest(url, options = {}) {
    try {
        // Add loading state to triggering element
        const triggerBtn = document.activeElement;
        if (triggerBtn && triggerBtn.tagName === 'BUTTON') {
            triggerBtn.classList.add('loading');
        }

        const response = await fetch(url, options);
        const data = await response.json();

        // Remove loading state
        if (triggerBtn && triggerBtn.tagName === 'BUTTON') {
            triggerBtn.classList.remove('loading');
        }

        return data;
    } catch (error) {
        console.error('API Error:', error);
        popup.error('Bir hata oluştu: ' + error.message);

        // Remove loading state on error
        const triggerBtn = document.activeElement;
        if (triggerBtn && triggerBtn.tagName === 'BUTTON') {
            triggerBtn.classList.remove('loading');
        }

        return null;
    }
}

// Load sites with enhanced error handling
async function loadSites() {
    try {
        const data = await apiRequest('api.php?action=get_sites');
        if (data && data.sites) {
            allSites = data.sites;
            renderAllSites(data.sites);
            renderSections(data.sites, data.categories);
            populateSiteOptions(data.sites);
        } else {
            throw new Error('Siteler yüklenemedi');
        }
    } catch (error) {
        popup.error('Siteler yüklenirken hata oluştu: ' + error.message);
    }
}

// Enhanced site rendering
function renderAllSites(sites) {
    const container = document.getElementById('allSitesList');
    container.innerHTML = '';

    if (!sites.length) {
        container.innerHTML = `
            <div class="empty-section">
                <i class="bi bi-globe" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                <p>Henüz site eklenmedi.</p>
            </div>
        `;
        return;
    }

    sites.forEach((site, index) => {
        const card = document.createElement('div');
        card.className = 'site-card d-flex align-items-center justify-content-between';
        card.style.animationDelay = `${index * 0.1}s`;

        card.innerHTML = `
            <div class="d-flex align-items-center">
                <img src="../img/logo/${site.logo}" class="logo-preview me-3" alt="${site.name}" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2240%22><rect width=%22100%25%22 height=%22100%25%22 fill=%22transparent%22/><text x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23999%22>Logo</text></svg>'">
                <div>
                    <h6 class="mb-1">${site.name}</h6>
                    <small class="text-muted">ID: ${site.id} | <a href="${site.link}" target="_blank" class="text-decoration-none">Siteyi Ziyaret Et</a></small>
                    ${site.description ? `<p class="mb-0 text-muted small">${site.description}</p>` : ''}
                </div>
            </div>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-warning" onclick="openSiteModal(${site.id})" title="Düzenle">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-${site.active ? 'success' : 'secondary'}" onclick="toggleSite(${site.id})" title="${site.active ? 'Gizle' : 'Göster'}">
                    <i class="bi bi-${site.active ? 'eye' : 'eye-slash'}"></i>
                </button>
                <button class="btn btn-outline-danger" onclick="deleteSite(${site.id})" title="Sil">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        container.appendChild(card);
    });
}

// Enhanced section rendering
function renderSections(sites, categories) {
    const section1Container = document.getElementById('section1Sites');
    const section2Container = document.getElementById('section2Sites');

    section1Container.innerHTML = '';
    section2Container.innerHTML = '';

    const section1Sites = categories?.section1 ?
        categories.section1.map(id => sites.find(site => site.id === id)).filter(Boolean) : [];
    const section2Sites = categories?.section2 ?
        categories.section2.map(id => sites.find(site => site.id === id)).filter(Boolean) : [];

    // Render sections with enhanced UI
    renderSectionSites(section1Sites, section1Container, 'section1');
    renderSectionSites(section2Sites, section2Container, 'section2');

    document.getElementById('section1Count').textContent = section1Sites.length;
    document.getElementById('section2Count').textContent = section2Sites.length;

    initializeSortable();
}

function renderSectionSites(sites, container, section) {
    if (sites.length === 0) {
        container.innerHTML = `
            <div class="empty-section">
                <i class="bi bi-grid-3x3-gap" style="font-size: 2rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                <p>${section === 'section1' ? 'Section 1' : 'Section 2'}'de site yok.</p>
            </div>
        `;
    } else {
        sites.forEach((site, index) => {
            const siteCard = createSectionSiteCard(site, section);
            siteCard.style.animationDelay = `${index * 0.1}s`;
            container.appendChild(siteCard);
        });
    }
}

// Enhanced section site card
function createSectionSiteCard(site, section) {
    const card = document.createElement('div');
    card.className = 'site-card d-flex align-items-center justify-content-between sortable';
    card.dataset.siteId = site.id;
    card.dataset.section = section;

    card.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi bi-grip-vertical drag-handle me-3"></i>
            <img src="../img/logo/${site.logo}" class="logo-preview me-3" alt="${site.name}" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2240%22><rect width=%22100%25%22 height=%22100%25%22 fill=%22transparent%22/><text x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23999%22>Logo</text></svg>'">
            <div>
                <h6 class="mb-1">${site.name}</h6>
                <small class="text-muted">ID: ${site.id}</small>
            </div>
        </div>
        <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-danger" onclick="removeSiteFromSection(${site.id}, '${section}')" title="Kaldır">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `;
    return card;
}

// Enhanced delete functions with custom popup
async function deleteSite(siteId) {
    const site = allSites.find(s => s.id === siteId);
    const siteName = site ? site.name : 'Bu site';

    popup.confirm(
        'Site Silme Onayı',
        `"${siteName}" sitesini silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.`,
        async () => {
            const data = await apiRequest('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_site',
                    id: siteId
                })
            });

            if (data && data.success) {
                showToast(data.message, 'success');
                loadSites();
                // Analytics'i de yenile
                if (document.getElementById('analytics-tab').classList.contains('active')) {
                    loadAnalytics();
                }
            } else {
                popup.error(data?.message || 'Silme işlemi başarısız');
            }
        }
    );
}

async function removeSiteFromSection(siteId, section) {
    popup.confirm(
        'Site Kaldırma Onayı',
        'Bu siteyi bu section\'dan çıkarmak istiyor musunuz?',
        async () => {
            const data = await apiRequest('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'remove_site_from_section',
                    siteId: siteId,
                    section: section
                })
            });
            if (data && data.success) {
                showToast(data.message);
                loadSites();
            } else {
                popup.error(data?.message || 'Bir hata oluştu');
            }
        }
    );
}

// Enhanced form submission handlers
async function handleSiteSubmit(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const siteId = document.getElementById('siteId').value;

    formData.append('action', siteId ? 'edit_site' : 'add_site');

    const data = await apiRequest('api.php', {
        method: 'POST',
        body: formData
    });

    if (data && data.success) {
        showToast(data.message, 'success');
        resetSiteForm();
        bootstrap.Modal.getInstance(document.getElementById('siteModal')).hide();
        loadSites();
        // Analytics'i de yenile
        if (document.getElementById('analytics-tab').classList.contains('active')) {
            loadAnalytics();
        }
    } else {
        popup.error(data?.message || 'Bir hata oluştu');
    }
}

// Reset form functions
function resetSiteForm() {
    document.getElementById('siteForm').reset();
    document.getElementById('siteId').value = '';
    document.getElementById('logoPreview').style.display = 'none';
}

async function loadSiteForEdit(siteId) {
    const data = await apiRequest(`api.php?action=get_site&id=${siteId}`);
    if (data && data.site) {
        const site = data.site;

        document.getElementById('siteId').value = site.id;
        document.getElementById('siteName').value = site.name;
        document.getElementById('siteUrl').value = site.link;
        document.getElementById('siteDescription').value = site.description;

        // Logo önizlemesi
        if (site.logo) {
            const preview = document.getElementById('logoPreview');
            preview.src = `../img/logo/${site.logo}`;
            preview.style.display = 'block';
        }
    }
}

async function toggleSite(siteId) {
    const data = await apiRequest('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'toggle_site',
            id: siteId
        })
    });

    if (data && data.success) {
        showToast(data.message);
        loadSites();
    }
}

// Section'a site ekleme modalı için mevcut siteleri yükle
async function loadAvailableSites(section) {
    const data = await apiRequest('api.php?action=get_sites');
    if (data && data.sites && data.categories) {
        const usedIds = (section === 'section1' ? data.categories.section1 : data.categories.section2) || [];
        const available = data.sites.filter(site => !usedIds.includes(site.id));
        const container = document.getElementById('availableSitesList');
        container.innerHTML = '';

        if (available.length === 0) {
            container.innerHTML = '<div class="empty-section">Eklenebilecek site yok.</div>';
            return;
        }

        available.forEach(site => {
            const btn = document.createElement('button');
            btn.className = 'btn btn-outline-primary w-100 mb-2 d-flex align-items-center';
            btn.innerHTML = `<img src="../img/logo/${site.logo}" class="logo-preview me-2" style="max-width:40px;max-height:24px;"> ${site.name}`;
            btn.onclick = () => addSiteToSection(site.id, section);
            container.appendChild(btn);
        });
    }
}

// Section'a site ekle
async function addSiteToSection(siteId, section) {
    const data = await apiRequest('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'add_site_to_section',
            siteId: siteId,
            section: section
        })
    });
    if (data && data.success) {
        showToast(data.message);
        loadSites();
        bootstrap.Modal.getInstance(document.getElementById('sectionSiteModal')).hide();
    } else {
        popup.error(data?.message || 'Bir hata oluştu');
    }
}

// Site seçeneklerini doldur (Banner ve Popup formu için)
function populateSiteOptions(sites) {
    // Banner site dropdown'ını doldur
    const siteSelect = document.getElementById('bannerSite');
    if (siteSelect) {
        siteSelect.innerHTML = '<option value="">Site Seçin</option>';
        sites.forEach(site => {
            const option = document.createElement('option');
            option.value = site.id;
            option.textContent = site.name;
            siteSelect.appendChild(option);
        });
    }

    // Popup site dropdown'ını doldur
    const popupSiteSelect = document.getElementById('popupSiteId');
    if (popupSiteSelect) {
        popupSiteSelect.innerHTML = '';
        sites.forEach(site => {
            const option = document.createElement('option');
            option.value = site.id;
            option.textContent = site.name;
            popupSiteSelect.appendChild(option);
        });
    }
}

// Banner formunda site seçilince linki doldur
function updateBannerLink() {
    const siteId = document.getElementById('bannerSite').value;
    const site = allSites.find(s => s.id == siteId);
    document.getElementById('bannerLink').value = site ? site.link : '';
}

// Enhanced sortable functionality
function initializeSortable() {
    ['section1Sites', 'section2Sites'].forEach(containerId => {
        const container = document.getElementById(containerId);
        const section = containerId.replace('Sites', '');

        new Sortable(container, {
            group: 'sites',
            animation: 300,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onStart: function(evt) {
                evt.item.style.opacity = '0.5';
            },
            onEnd: function(evt) {
                evt.item.style.opacity = '1';
                updateSiteOrder(section, evt.to);
            }
        });
    });
}

// Site sıralamasını güncelle
async function updateSiteOrder(section, container) {
    const siteIds = Array.from(container.children)
        .filter(card => card.dataset.siteId)
        .map(card => parseInt(card.dataset.siteId));

    const data = await apiRequest('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'reorder_sites',
            section: section,
            siteIds: siteIds
        })
    });

    if (data && data.success) {
        showToast('Sıralama güncellendi');
    }
}

// Banner functions
async function loadBanners() {
    const [bannerData, siteData] = await Promise.all([
        apiRequest('api.php?action=get_banners'),
        apiRequest('api.php?action=get_sites')
    ]);

    if (bannerData && bannerData.banners && siteData && siteData.sites) {
        renderBanners(bannerData.banners, siteData.sites);
    }
}

function renderBanners(banners, sites) {
    const container = document.getElementById('bannersContent');
    container.innerHTML = '';

    if (banners.length === 0) {
        container.innerHTML = '<p class="text-white">Henüz banner eklenmedi.</p>';
        return;
    }

    // Sort banners by order
    banners.sort((a, b) => (a.order || 999) - (b.order || 999));

    // Add drag info
    const dragInfo = document.createElement('div');
    dragInfo.className = 'banner-drag-info';
    dragInfo.innerHTML = '<i class="bi bi-info-circle"></i> Banner\'ları sürükleyerek sıralayabilirsiniz. Değişiklikler otomatik kaydedilir.';
    container.appendChild(dragInfo);

    // Create sortable container
    const sortableContainer = document.createElement('div');
    sortableContainer.className = 'sortable-banner-list';
    sortableContainer.id = 'sortableBanners';

    banners.forEach(banner => {
        const bannerCard = document.createElement('div');
        bannerCard.className = 'card banner-card';
        bannerCard.setAttribute('data-banner-id', banner.id);
        bannerCard.innerHTML = `
            <div class="banner-drag-handle">
                <i class="bi bi-grip-vertical"></i>
            </div>
            <div class="card-body banner-content">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1 text-white">${banner.title}</h6>
                        <small class="text-white">
                            Site: ${sites.find(s => s.id == banner.site_id)?.name || 'Bilinmiyor'} | Tip: ${banner.type} |
                            <span class="status-indicator ${banner.active ? 'status-active' : 'status-inactive'}"></span>
                            ${banner.active ? 'Aktif' : 'Pasif'}
                        </small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-warning" onclick="openBannerModal(${banner.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-${banner.active ? 'success' : 'secondary'}" onclick="toggleBanner(${banner.id})">
                            <i class="bi bi-${banner.active ? 'eye' : 'eye-slash'}"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteBanner(${banner.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        sortableContainer.appendChild(bannerCard);
    });

    container.appendChild(sortableContainer);

    // Initialize sortable functionality
    if (window.Sortable) {
        new Sortable(sortableContainer, {
            animation: 150,
            handle: '.banner-drag-handle',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onEnd: async function(evt) {
                await updateBannerOrder();
            }
        });
    }
}

async function updateBannerOrder() {
    const sortableContainer = document.getElementById('sortableBanners');
    if (!sortableContainer) return;

    const bannerIds = Array.from(sortableContainer.children)
        .filter(card => card.getAttribute('data-banner-id'))
        .map(card => parseInt(card.getAttribute('data-banner-id')));

    const data = await apiRequest('api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'reorder_banners',
            bannerIds: bannerIds
        })
    });

    if (data && data.success) {
        showToast('Banner sıralaması güncellendi');
    }
}

async function handleBannerSubmit(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const bannerId = document.getElementById('bannerId').value;

    formData.append('action', bannerId ? 'edit_banner' : 'add_banner');

    const data = await apiRequest('api.php', {
        method: 'POST',
        body: formData
    });

    if (data && data.success) {
        showToast(data.message);
        resetBannerForm();
        bootstrap.Modal.getInstance(document.getElementById('bannerModal')).hide();
        loadBanners();
    } else {
        popup.error(data?.message || 'Bir hata oluştu');
    }
}

function resetBannerForm() {
    document.getElementById('bannerForm').reset();
    document.getElementById('bannerId').value = '';
    document.getElementById('bannerType').value = 'second';
}

async function loadBannerForEdit(bannerId) {
    const data = await apiRequest('api.php?action=get_banners');
    if (data && data.banners) {
        const banner = data.banners.find(b => b.id == bannerId);
        if (banner) {
            document.getElementById('bannerId').value = banner.id;
            document.getElementById('bannerSite').value = banner.site_id;
            document.getElementById('bannerTitle').value = banner.title;
            document.getElementById('bannerLink').value = banner.link;
            document.getElementById('bannerType').value = 'second';
            // Trigger the link update
            updateBannerLink();
        }
    }
}

async function deleteBanner(bannerId) {
    popup.confirm(
        'Banner Silme Onayı',
        'Bu banner\'ı silmek istediğinizden emin misiniz?',
        async () => {
            const data = await apiRequest('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_banner',
                    id: bannerId
                })
            });

            if (data && data.success) {
                showToast(data.message);
                loadBanners();
            } else {
                popup.error(data?.message || 'Silme işlemi başarısız');
            }
        }
    );
}

async function toggleBanner(bannerId) {
    const data = await apiRequest('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'toggle_banner',
            id: bannerId
        })
    });

    if (data && data.success) {
        showToast(data.message);
        loadBanners();
    }
}

// Slot functions
async function loadSlots() {
    const data = await apiRequest('api.php?action=get_slots');
    if (data && data.slots) {
        renderSlots(data.slots);
    }
}

function renderSlots(slots) {
    const container = document.getElementById('slotsList');
    container.innerHTML = '';

    if (slots.length === 0) {
        container.innerHTML = '<p class="text-muted">Henüz slot alanı eklenmedi.</p>';
        return;
    }

    slots.forEach(slot => {
        const slotCard = document.createElement('div');
        slotCard.className = 'card mb-2';
        slotCard.innerHTML = `
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${slot.title}</h6>
                        <small class="text-muted">
                            <i class="bi bi-${slot.type}"></i> ${slot.text} |
                            <span class="status-indicator ${slot.active ? 'status-active' : 'status-inactive'}"></span>
                            ${slot.active ? 'Aktif' : 'Pasif'}
                        </small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-warning" onclick="openSlotModal(${slot.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-${slot.active ? 'success' : 'secondary'}" onclick="toggleSlot(${slot.id})">
                            <i class="bi bi-${slot.active ? 'eye' : 'eye-slash'}"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteSlot(${slot.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(slotCard);
    });
}

async function handleSlotSubmit(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const slotId = document.getElementById('slotId').value;

    formData.append('action', slotId ? 'edit_slot' : 'add_slot');

    const data = await apiRequest('api.php', {
        method: 'POST',
        body: formData
    });

    if (data && data.success) {
        showToast(data.message);
        resetSlotForm();
        bootstrap.Modal.getInstance(document.getElementById('slotModal')).hide();
        loadSlots();
    } else {
        popup.error(data?.message || 'Bir hata oluştu');
    }
}

function resetSlotForm() {
    document.getElementById('slotForm').reset();
    document.getElementById('slotId').value = '';
}

async function loadSlotForEdit(slotId) {
    const data = await apiRequest('api.php?action=get_slots');
    if (data && data.slots) {
        const slot = data.slots.find(s => s.id == slotId);
        if (slot) {
            document.getElementById('slotId').value = slot.id;
            document.getElementById('slotTitle').value = slot.title;
            document.getElementById('slotLink').value = slot.link;
            document.getElementById('slotText').value = slot.text;
            document.getElementById('slotType').value = slot.type;
        }
    }
}

async function deleteSlot(slotId) {
    popup.confirm(
        'Slot Silme Onayı',
        'Bu slot alanını silmek istediğinizden emin misiniz?',
        async () => {
            const data = await apiRequest('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_slot',
                    id: slotId
                })
            });

            if (data && data.success) {
                showToast(data.message);
                loadSlots();
            } else {
                popup.error(data?.message || 'Silme işlemi başarısız');
            }
        }
    );
}

async function toggleSlot(slotId) {
    const data = await apiRequest('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'toggle_slot',
            id: slotId
        })
    });

    if (data && data.success) {
        showToast(data.message);
        loadSlots();
    }
}

// Social functions
async function loadSocialLinks() {
    const data = await apiRequest('api.php?action=get_social');
    if (data && data.social_links) {
        const socialLinks = data.social_links;

        Object.keys(socialLinks).forEach(platform => {
            const input = document.getElementById(`social${platform.charAt(0).toUpperCase() + platform.slice(1)}`);
            if (input) {
                let displayValue = socialLinks[platform] || '';

                // URL'lerden kullanıcı adı/telefon numarası çıkar
                if (displayValue) {
                    switch (platform) {
                        case 'telegram':
                            // https://t.me/username -> username
                            displayValue = displayValue.replace(/https?:\/\/t\.me\//, '');
                            break;

                        case 'instagram':
                            // https://www.instagram.com/username -> username
                            displayValue = displayValue.replace(/https?:\/\/(www\.)?instagram\.com\//, '');
                            break;

                        case 'youtube':
                            // https://www.youtube.com/@username -> username
                            displayValue = displayValue.replace(/https?:\/\/(www\.)?youtube\.com\/@/, '');
                            break;

                        case 'twitter':
                            // https://twitter.com/username -> username
                            displayValue = displayValue.replace(/https?:\/\/(www\.)?twitter\.com\//, '');
                            break;

                        case 'facebook':
                            // https://www.facebook.com/pagename -> pagename
                            displayValue = displayValue.replace(/https?:\/\/(www\.)?facebook\.com\//, '');
                            break;

                        case 'whatsapp':
                            // https://wa.me/905551234567 -> +905551234567
                            displayValue = displayValue.replace(/https?:\/\/wa\.me\//, '+');
                            break;

                        case 'email':
                            // mailto:email@domain.com -> email@domain.com
                            displayValue = displayValue.replace(/^mailto:/, '');
                            break;

                        case 'discord':
                            // Discord için URL olarak bırak
                            break;

                        default:
                            // Diğer platformlar için olduğu gibi bırak
                            break;
                    }
                }

                input.value = displayValue;
            }
        });

        updateSocialPreview();
    }
}

async function handleSocialSubmit(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    formData.append('action', 'update_social');

    const data = await apiRequest('api.php', {
        method: 'POST',
        body: formData
    });

    if (data && data.success) {
        showToast(data.message);
        updateSocialPreview();
    } else {
        popup.error(data?.message || 'Bir hata oluştu');
    }
}

function updateSocialPreview() {
    const preview = document.getElementById('socialPreview');
    let html = '';

    const platforms = {
        telegram: 'Telegram',
        instagram: 'Instagram',
        youtube: 'YouTube',
        twitter: 'Twitter/X',
        facebook: 'Facebook',
        discord: 'Discord',
        whatsapp: 'WhatsApp',
        email: 'Email'
    };

    Object.keys(platforms).forEach(platform => {
        const input = document.getElementById(`social${platform.charAt(0).toUpperCase() + platform.slice(1)}`);
        if (input && input.value) {
            html += `
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-${platform === 'email' ? 'envelope' : platform} social-icon"></i>
                    <span>${platforms[platform]}: ${input.value}</span>
                </div>
            `;
        }
    });

    preview.innerHTML = html || '<p class="text-muted">Henüz sosyal medya linki eklenmedi.</p>';
}

// Settings functions
async function loadSettings() {
    const data = await apiRequest('api.php?action=get_settings');
    if (data) {
        if (data.hero) {
            document.getElementById('heroTitle').value = data.hero.title || '';
            document.getElementById('heroSubtitle').value = data.hero.subtitle || '';
        }

        if (data.sections) {
            document.getElementById('section1Title').value = data.sections.section1?.title || '';
            document.getElementById('section2Title').value = data.sections.section2?.title || '';
        }
    }
}

// Load popup settings
async function loadPopupSettings() {
    const data = await apiRequest('api.php?action=get_settings');
    if (data && data.popup) {
        const popup = data.popup;
        document.getElementById('popupNumber').value = popup.number || '';
        document.getElementById('popupTitle').value = popup.title || '';
        document.getElementById('popupSubtitle').value = popup.subtitle || '';
        document.getElementById('popupSiteId').value = popup.site_id || 0;
        document.getElementById('popupActive').checked = popup.active || false;
    }
}

async function handleSettingsSubmit(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    formData.append('action', 'update_settings');

    const data = await apiRequest('api.php', {
        method: 'POST',
        body: formData
    });

    if (data && data.success) {
        showToast(data.message);
    } else {
        popup.error(data?.message || 'Bir hata oluştu');
    }
}

// Admin User Management Functions
async function loadAdminUsers() {
    if (!window.isSuperAdmin) return;

    const data = await apiRequest('api.php?action=get_admin_users');
    if (data && data.users) {
        renderAdminUsers(data.users);
    }
}

function renderAdminUsers(users) {
    const container = document.getElementById('adminUsersList');
    container.innerHTML = '';

    if (Object.keys(users).length === 0) {
        container.innerHTML = '<p class="text-muted">Henüz admin kullanıcısı eklenmedi.</p>';
        return;
    }

    Object.keys(users).forEach(username => {
        const user = users[username];
        const userCard = document.createElement('div');
        userCard.className = 'card mb-2';

        const isSystemUser = !user.editable;
        const isSuperAdmin = user.role === 'super_admin';

        userCard.innerHTML = `
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">
                            ${user.name}
                            ${isSuperAdmin ? '<span class="badge bg-warning text-dark ms-2">Süper Admin</span>' : ''}
                            ${isSystemUser ? '<span class="badge bg-secondary ms-1">Sistem</span>' : ''}
                        </h6>
                        <small class="text-muted">
                            Kullanıcı Adı: <strong>${username}</strong> |
                            Rol: ${user.role === 'super_admin' ? 'Süper Admin' : 'Admin'}
                        </small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        ${!isSuperAdmin && user.editable ? ``
                            + `<button class="btn btn-outline-danger" onclick="deleteAdminUser('${username}')">
                                <i class="bi bi-trash"></i>
                            </button>` : ''}
                    </div>
                </div>
            </div>
        `;
        container.appendChild(userCard);
    });
}

async function handleAdminUserSubmit(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    formData.append('action', 'add_admin_user');

    const data = await apiRequest('api.php', {
        method: 'POST',
        body: formData
    });

    if (data && data.success) {
        showToast(data.message);
        resetAdminUserForm();
        bootstrap.Modal.getInstance(document.getElementById('adminUserModal')).hide();
        loadAdminUsers();
    } else {
        popup.error(data?.message || 'Bir hata oluştu');
    }
}

function resetAdminUserForm() {
    document.getElementById('adminUserForm').reset();
    document.getElementById('adminUserId').value = '';
}

async function deleteAdminUser(username) {
    popup.confirm(
        'Admin Kullanıcısı Silme Onayı',
        `"${username}" kullanıcısını silmek istediğinizden emin misiniz?"`,
        async () => {
            const data = await apiRequest('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_admin_user',
                    username: username
                })
            });

            if (data && data.success) {
                showToast(data.message);
                loadAdminUsers();
            } else {
                popup.error(data?.message || 'Silme işlemi başarısız');
            }
        }
    );
}

// Add CSS classes for sortable states
const style = document.createElement('style');
style.textContent = `
    .sortable-ghost {
        opacity: 0.4;
        background: var(--primary-color) !important;
    }
    .sortable-chosen {
        transform: scale(1.05);
    }
    .sortable-drag {
        transform: rotate(5deg);
    }
    .sortable-banner-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .banner-card {
        display: flex;
        align-items: stretch;
        background: #23272b;
        color: #fff;
        border: 1px solid #444;
        border-radius: 0.5rem;
        margin-bottom: 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .banner-drag-handle {
        display: flex;
        align-items: center;
        padding: 0 0.75rem;
        cursor: grab;
        background: #181a1b;
        border-right: 1px solid #333;
        border-radius: 0.5rem 0 0 0.5rem;
    }
    .banner-drag-handle:active {
        cursor: grabbing;
    }
    .banner-drag-handle:hover {
        cursor: grab;
    }

    /* Section drag handle'ları için */
    .drag-handle {
        cursor: grab !important;
    }
    .drag-handle:active {
        cursor: grabbing !important;
    }
    .drag-handle:hover {
        cursor: grab !important;
    }

    /* Sadece grip ikonunun üstünde el işareti */
    .bi-grip-vertical {
        cursor: grab !important;
        pointer-events: auto;
    }
    .bi-grip-vertical:hover {
        cursor: grab !important;
    }
    .bi-grip-vertical:active {
        cursor: grabbing !important;
    }

    /* Kartların geri kalanında normal fare */
    .site-card, .banner-card {
        cursor: default !important;
    }
    .site-card *, .banner-card * {
        cursor: default;
    }
    .site-card .drag-handle, .banner-card .banner-drag-handle,
    .site-card .bi-grip-vertical, .banner-card .bi-grip-vertical {
        cursor: grab !important;
    }
    .banner-content {
        flex: 1 1 0%;
    }
`;
document.head.appendChild(style);
