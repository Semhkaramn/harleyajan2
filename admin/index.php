<?php
session_start();
require_once 'config.php';

// Güvenlik kontrolü
if (!isSessionValid()) {
    header('Location: login.php');
    exit;
}

// Logout işlemi
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Kullanıcı bilgileri
$current_user = $_SESSION['admin_user'] ?? ['username' => 'admin', 'role' => 'admin', 'name' => 'Yönetici'];
$is_super_admin = $current_user['role'] === 'super_admin';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Site Yönetimi</title>
    <link rel="shortcut icon" href="../img/siteicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=<?= time() ?>">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="../img/siteicon.png" alt="Site Icon" style="width: 24px; height: 24px; margin-right: 8px;">
                Admin Panel
            </a>
            <div class="d-flex align-items-center">
                <a href="/" target="_blank" class="btn btn-outline-light btn-sm me-3">
                    <i class="bi bi-eye"></i>
                    Siteyi Görüntüle
                </a>
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle"></i>
                    Hoş geldiniz, <strong><?= htmlspecialchars($current_user['username']) ?></strong>
                </span>
                <div class="navbar-nav">
                    <a class="nav-link" href="?logout=1" title="Güvenli Çıkış">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Tabs -->
        <ul class="nav nav-tabs" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="sites-tab" data-bs-toggle="tab" data-bs-target="#sites" type="button">
                    <i class="bi bi-globe"></i> Site Yönetimi
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="sections-tab" data-bs-toggle="tab" data-bs-target="#sections" type="button">
                    <i class="bi bi-grid-3x3-gap"></i> Section Yönetimi
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="banners-tab" data-bs-toggle="tab" data-bs-target="#banners" type="button">
                    <i class="bi bi-images"></i> Banner'lar
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="slots-tab" data-bs-toggle="tab" data-bs-target="#slots" type="button">
                    <i class="bi bi-grid-3x3-gap"></i> Slot Alanları
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="social-tab" data-bs-toggle="tab" data-bs-target="#social" type="button">
                    <i class="bi bi-share"></i> Sosyal Medya
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button">
                    <i class="bi bi-gear"></i> Site Ayarları
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="popup-tab" data-bs-toggle="tab" data-bs-target="#popup" type="button">
                    <i class="bi bi-window"></i> Popup Ayarları
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button">
                    <i class="bi bi-bar-chart"></i> Analiz ve Raporlar
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button">
                    <i class="bi bi-person"></i> Profil
                </button>
            </li>
            <?php if ($is_super_admin): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="admin-tab" data-bs-toggle="tab" data-bs-target="#admin" type="button">
                    <i class="bi bi-people"></i> Admin Kullanıcıları
                </button>
            </li>
            <?php endif; ?>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="adminTabsContent">
            <!-- Site Yönetimi Tab -->
            <div class="tab-pane fade show active" id="sites" role="tabpanel">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-globe"></i> Tüm Siteler
                                </h5>
                                <button class="btn btn-primary" onclick="openSiteModal()">
                                    <i class="bi bi-plus-circle"></i> Yeni Site Ekle
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="allSitesList">
                                    <!-- Tüm siteler buraya yüklenecek -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Yönetimi Tab -->
            <div class="tab-pane fade" id="sections" role="tabpanel">
                <div class="row">
                    <!-- Section 1 -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Section 1</h6>
                                <span class="badge bg-primary" id="section1Count">0</span>
                            </div>
                            <div class="card-body">
                                <div id="section1Sites" class="section-container sortable-list">
                                    <!-- Section 1 siteleri buraya yüklenecek -->
                                </div>
                                <button class="add-site-btn" onclick="openSectionSiteModal('section1')">
                                    <i class="bi bi-plus-circle"></i> Section 1'e Site Ekle
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2 -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Section 2</h6>
                                <span class="badge bg-primary" id="section2Count">0</span>
                            </div>
                            <div class="card-body">
                                <div id="section2Sites" class="section-container sortable-list">
                                    <!-- Section 2 siteleri buraya yüklenecek -->
                                </div>
                                <button class="add-site-btn" onclick="openSectionSiteModal('section2')">
                                    <i class="bi bi-plus-circle"></i> Section 2'ye Site Ekle
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Banner'lar Tab -->
            <div class="tab-pane fade" id="banners" role="tabpanel">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-images"></i> Banner Yönetimi
                                </h5>
                                <button class="btn btn-primary" onclick="openBannerModal()">
                                    <i class="bi bi-plus-circle"></i> Yeni Banner Ekle
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="bannersContent">
                                    <!-- Banner listesi buraya yüklenecek -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slot Alanları Tab -->
            <div class="tab-pane fade" id="slots" role="tabpanel">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-grid-3x3-gap"></i> Slot Alanları
                                </h5>
                                <button class="btn btn-primary" onclick="openSlotModal()">
                                    <i class="bi bi-plus-circle"></i> Yeni Slot Ekle
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="slotsList">
                                    <!-- Slot listesi buraya yüklenecek -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sosyal Medya Tab -->
            <div class="tab-pane fade" id="social" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-share"></i> Sosyal Medya Linkleri
                                </h5>
                            </div>
                            <div class="card-body">
                                <form id="socialForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="socialTelegram" class="form-label">
                                                <i class="bi bi-telegram"></i> Telegram
                                            </label>
                                            <input type="text" class="form-control" autocomplete="off" name="telegram" id="socialTelegram" placeholder="Kullanıcı adını @ olmadan yazın (örn: sohbetslot)">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="socialInstagram" class="form-label">
                                                <i class="bi bi-instagram"></i> Instagram
                                            </label>
                                            <input type="text" class="form-control" autocomplete="off" name="instagram" id="socialInstagram" placeholder="Kullanıcı adını @ olmadan yazın (örn: sohbetslot)">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="socialYoutube" class="form-label">
                                                <i class="bi bi-youtube"></i> YouTube
                                            </label>
                                            <input type="text" class="form-control" autocomplete="off" name="youtube" id="socialYoutube" placeholder="Kanal adını @ olmadan yazın (örn: sohbetslot)">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="socialTwitter" class="form-label">
                                                <i class="bi bi-twitter"></i> Twitter/X
                                            </label>
                                            <input type="text" class="form-control" autocomplete="off" name="twitter" id="socialTwitter" placeholder="Kullanıcı adını @ olmadan yazın (örn: sohbetslot)">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="socialFacebook" class="form-label">
                                                <i class="bi bi-facebook"></i> Facebook
                                            </label>
                                            <input type="text" class="form-control" autocomplete="off" name="facebook" id="socialFacebook" placeholder="Sayfa adını yazın (örn: sohbetslot)">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="socialDiscord" class="form-label">
                                                <i class="bi bi-discord"></i> Discord
                                            </label>
                                            <input type="url" class="form-control" autocomplete="off" name="discord" id="socialDiscord" placeholder="Discord davet linkini yazın (örn: https://discord.gg/abc123)">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="socialWhatsapp" class="form-label">
                                                <i class="bi bi-whatsapp"></i> WhatsApp
                                            </label>
                                            <input type="tel" class="form-control" autocomplete="off" name="whatsapp" id="socialWhatsapp" placeholder="Telefon numarasını + ile başlayarak yazın (örn: +905551234567)">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="socialEmail" class="form-label">
                                                <i class="bi bi-envelope"></i> E-mail
                                            </label>
                                            <input type="email" class="form-control" autocomplete="off" name="email" id="socialEmail" placeholder="E-posta adresinizi yazın (örn: info@relaxbetsiteler.com)">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Sosyal Medya Linklerini Kaydet
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-eye"></i> Önizleme
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Doldurduğunuz sosyal medya linkleri sitede otomatik olarak görünecek:</p>
                                <div id="socialPreview">
                                    <!-- Sosyal medya önizlemesi -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
<!-- Analiz Tab -->
            <div class="tab-pane fade" id="analytics" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>Analiz ve Raporlar</h3>
                    <button class="btn btn-primary" onclick="refreshAnalytics()">
                        <i class="bi bi-arrow-clockwise"></i> Yenile
                    </button>
                </div>

                <!-- Genel İstatistikler -->
                <!-- Ziyaret İstatistikleri -->
                <div class="row mb-3">
                    <div class="col-12">
                        <h5 class="mb-3"><i class="bi bi-eye"></i> Ziyaret İstatistikleri</h5>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title">Toplam Ziyaret</h6>
                                <h3 class="text-primary" id="total-visits">0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title">Bugünkü Ziyaret</h6>
                                <h3 class="text-info" id="today-visits">0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title">Haftalık Ziyaret</h6>
                                <h3 class="text-success" id="weekly-visits">0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title">Aylık Ziyaret</h6>
                                <h3 class="text-warning" id="monthly-visits">0</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tıklama İstatistikleri -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="mb-3"><i class="bi bi-cursor"></i> Tıklama İstatistikleri</h5>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title">Toplam Tıklama</h6>
                                <h3 class="text-primary" id="total-clicks">0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title">Bugünkü Tıklama</h6>
                                <h3 class="text-info" id="today-clicks">0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title">Haftalık Tıklama</h6>
                                <h3 class="text-success" id="weekly-clicks">0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h6 class="card-title">Aylık Tıklama</h6>
                                <h3 class="text-warning" id="monthly-clicks">0</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtre Seçenekleri -->
                <!-- Filtre Seçenekleri -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Tarih Aralığı:</label>
                        <select class="form-select" id="date-filter" onchange="filterAnalytics()">
                            <option value="today">Bugün</option>
                            <option value="week">Son 7 Gün</option>
                            <option value="month">Son 30 Gün</option>
                            <option value="all">Tüm Zamanlar</option>
                        </select>
                    </div>
                </div>

                <!-- Site Bazlı İstatistikler -->
                <div class="card">
                    <div class="card-header">
                        <h5>Site Bazlı Tıklama İstatistikleri</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="site-analytics-table">
                                <thead>
                                    <tr>
                                        <th>Site</th>
                                        <th>Logo</th>
                                        <th>Banner Tıklamaları</th>
                                        <th>Popup Tıklamaları</th>
                                        <th>Sponsor 1 Tıklamaları</th>
                                        <th>Sponsor 2 Tıklamaları</th>
                                        <th>Toplam</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Analytics verileri buraya gelecek -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Günlük Ziyaret Grafiği -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Günlük Ziyaret ve Tıklama Trendi</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="visits-chart" style="height: 400px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Profil Tab -->
            <div class="tab-pane fade" id="profile" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-person-gear"></i> Profil Bilgilerini Güncelle
                                </h5>
                            </div>
                            <div class="card-body">
                                <form id="profileUpdateForm">
                                    <div class="mb-3">
                                        <label class="form-label">Kullanıcı Adı</label>
                                        <input type="text" class="form-control" autocomplete="off" name="username" id="profileUsername" value="<?= htmlspecialchars($current_user['username']) ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Mevcut Şifre</label>
                                        <input type="text" class="form-control" autocomplete="off" name="current_password" id="currentPassword" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Yeni Şifre (İsteğe Bağlı)</label>
                                        <input type="text" class="form-control" autocomplete="off" name="new_password" id="newPassword">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Yeni Şifre Tekrar (İsteğe Bağlı)</label>
                                        <input type="text" class="form-control" autocomplete="off" name="confirm_password" id="confirmPassword">
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Bilgileri Güncelle
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-info-circle"></i> Mevcut Bilgiler
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Mevcut Kullanıcı Adı:</strong>
                                    <span class="text-white"><?= htmlspecialchars($current_user['username']) ?></span>
                                </div>

                                <div class="mb-3">
                                    <small class="text-white">
                                        <i class="bi bi-shield-check"></i>
                                        Hesap güvenliğiniz için düzenli olarak bilgilerinizi güncelleyin.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Site Ayarları Tab -->
            <div class="tab-pane fade" id="settings" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-gear"></i> Site Ayarları
                                </h5>
                            </div>
                            <div class="card-body">
                                <form id="settingsForm">
                                    <div class="mb-3">
                                        <label class="form-label">Site Başlığı</label>
                                        <input type="text" class="form-control" autocomplete="off" name="title" id="heroTitle">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Site Alt Başlığı</label>
                                        <input type="text" class="form-control" autocomplete="off" name="subtitle" id="heroSubtitle">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Section 1 Başlığı</label>
                                            <input type="text" class="form-control" autocomplete="off" name="section1_title" id="section1Title">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Section 2 Başlığı</label>
                                            <input type="text" class="form-control" autocomplete="off" name="section2_title" id="section2Title">
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Ayarları Kaydet
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Popup Ayarları Tab -->
            <div class="tab-pane fade" id="popup" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-window"></i> Popup Ayarları
                                </h5>
                            </div>
                            <div class="card-body">
                                <form id="popupForm">
                                    <div class="mb-3">
                                        <label class="form-label">Popup Başlık</label>
                                        <input type="text" class="form-control" autocomplete="off" name="popup_number" id="popupNumber" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Popup Başlık 2</label>
                                        <input type="text" class="form-control" autocomplete="off" name="popup_title" id="popupTitle" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Popup Alt Başlığı</label>
                                        <input type="text" class="form-control" autocomplete="off" name="popup_subtitle" id="popupSubtitle" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Site Seçimi</label>
                                        <select class="form-select" autocomplete="off" name="popup_site_id" id="popupSiteId" required>

                                            <!-- Siteler buraya yüklenecek -->
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Popup Aktif</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="popup_active" id="popupActive">
                                            <label class="form-check-label" for="popupActive">
                                                Popup'ı Aktif Et
                                            </label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Ayarları Kaydet
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Kullanıcıları Tab (Sadece Süper Admin) -->
            <?php if ($is_super_admin): ?>
            <div class="tab-pane fade" id="admin" role="tabpanel">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-people"></i> Admin Kullanıcıları
                                </h5>
                                <button class="btn btn-primary" onclick="openAdminUserModal()">
                                    <i class="bi bi-person-plus"></i> Yeni Admin Ekle
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="adminUsersList">
                                    <!-- Admin kullanıcıları buraya yüklenecek -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Site Modal -->
    <div class="modal fade" id="siteModal" tabindex="-1" aria-labelledby="siteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="siteModalLabel">
                        <i class="bi bi-plus-circle"></i> Yeni Site Ekle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="siteForm" enctype="multipart/form-data">
                        <input type="hidden" id="siteId" name="id">

                        <div class="mb-3">
                            <label class="form-label">Site Adı</label>
                            <input type="text" class="form-control" autocomplete="off" id="siteName" name="name" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Site URL</label>
                            <input type="url" class="form-control" autocomplete="off" id="siteUrl" name="link" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Açıklama</label>
                            <textarea class="form-control" autocomplete="off" id="siteDescription" name="description" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Logo</label>
                            <div class="file-input-wrapper">
                                <input type="file" class="form-control" autocomplete="off" id="siteLogo" name="logo" accept="image/*,video/webm">
                                <button type="button" class="btn btn-outline-secondary w-100" onclick="document.getElementById('siteLogo').click()">
                                    <i class="bi bi-upload"></i> Logo Seç (JPG, PNG, GIF, WEBP, SVG, WEBM)
                                </button>
                            </div>
                            <img id="logoPreview" class="logo-upload-preview">
                            <small class="text-muted">Desteklenen formatlar: JPG, PNG, GIF, WEBP, SVG, WEBM - Maksimum 15MB</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" form="siteForm" class="btn btn-primary" id="siteSubmitBtn">
                        <i class="bi bi-save"></i> Kaydet
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Section Site Seçme Modal -->
    <div class="modal fade" id="sectionSiteModal" tabindex="-1" aria-labelledby="sectionSiteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sectionSiteModalLabel">
                        <i class="bi bi-plus-circle"></i> Section'a Site Ekle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Hangi siteyi <span id="targetSectionName"></span> eklemek istiyorsunuz?</p>
                    <div id="availableSitesList">
                        <!-- Mevcut siteler buraya yüklenecek -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Banner Modal -->
    <div class="modal fade" id="bannerModal" tabindex="-1" aria-labelledby="bannerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bannerModalLabel">
                        <i class="bi bi-plus-circle"></i> Yeni Banner Ekle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="bannerForm">
                        <input type="hidden" id="bannerId" name="id">

                        <div class="mb-3">
                            <label class="form-label">Site</label>
                            <select class="form-select" autocomplete="off" id="bannerSite" name="site_id" required onchange="updateBannerLink()">
                                <option value="">Site Seçin</option>
                                <!-- Siteler buraya yüklenecek -->
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Banner Başlığı</label>
                            <input type="text" class="form-control" autocomplete="off" id="bannerTitle" name="title" required>
                            <small class="form-text text-muted">HTML etiketleri kullanabilirsiniz (örn: &lt;span&gt;500₺&lt;/span&gt; BONUS)</small>
                        </div>

                        <input type="hidden" id="bannerLink" name="link">

                        <input type="hidden" id="bannerType" name="type" value="second">

                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" form="bannerForm" class="btn btn-primary" id="bannerSubmitBtn">
                        <i class="bi bi-save"></i> Kaydet
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Slot Modal -->
    <div class="modal fade" id="slotModal" tabindex="-1" aria-labelledby="slotModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="slotModalLabel">
                        <i class="bi bi-plus-circle"></i> Yeni Slot Ekle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="slotForm">
                        <input type="hidden" id="slotId" name="id">

                        <div class="mb-3">
                            <label class="form-label">Başlık</label>
                            <input type="text" class="form-control" autocomplete="off" id="slotTitle" name="title" required>
                            <small class="form-text text-muted">HTML etiketleri kullanabilirsiniz (örn: &lt;span&gt;vurgu&lt;/span&gt;)</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Link</label>
                            <input type="url" class="form-control" autocomplete="off" id="slotLink" name="link" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Alt Metin</label>
                            <input type="text" class="form-control" autocomplete="off" id="slotText" name="text" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tip</label>
                            <select class="form-select" autocomplete="off" id="slotType" name="type" required>
                                <option value="telegram">Telegram</option>
                                <option value="instagram">Instagram</option>
                                <option value="youtube">YouTube</option>
                                <option value="twitter">Twitter</option>
                                <option value="discord">Discord</option>
                                <option value="whatsapp">WhatsApp</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" form="slotForm" class="btn btn-primary" id="slotSubmitBtn">
                        <i class="bi bi-save"></i> Kaydet
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_super_admin): ?>
    <!-- Admin User Modal -->
    <div class="modal fade" id="adminUserModal" tabindex="-1" aria-labelledby="adminUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="adminUserModalLabel">
                        <i class="bi bi-person-plus"></i> Yeni Admin Kullanıcısı
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="adminUserForm">
                        <input type="hidden" id="adminUserId" name="user_id">

                        <div class="mb-3">
                            <label class="form-label">Kullanıcı Adı</label>
                            <input type="text" class="form-control" autocomplete="off" id="adminUsername" name="username" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tam Adı</label>
                            <input type="text" class="form-control" autocomplete="off" id="adminName" name="name" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Şifre</label>
                            <input type="password" class="form-control" autocomplete="off" id="adminPassword" name="password" required>
                            <small class="text-muted">Minimum 6 karakter</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Rol</label>
                            <select class="form-select" autocomplete="off" id="adminRole" name="role" required>
                                <option value="admin">Admin</option>
                            </select>
                            <small class="text-muted">Süper admin rolü sadece sistem tarafından atanır</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" form="adminUserForm" class="btn btn-primary" id="adminUserSubmitBtn">
                        <i class="bi bi-save"></i> Kaydet
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Toast Container -->
    <div class="toast-container">
        <!-- Toast'lar buraya eklenecek -->
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <!-- Admin Functions -->
    <script src="functions.js?v=<?= time() ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    let analyticsData = null;
    let chart = null;

    // Analytics verilerini yükle
    function loadAnalytics() {
        fetch('api.php?action=get_analytics')
            .then(response => response.json())
            .then(data => {
                analyticsData = data.analytics;
                updateAnalyticsDisplay();
            })
            .catch(error => {
                console.error('Analytics yüklenirken hata:', error);
            });
    }

    // Analytics görünümünü güncelle
    function updateAnalyticsDisplay() {
        if (!analyticsData) return;

        const today = new Date();
        const todayStr = today.toISOString().split('T')[0];

        // Haftalık ve aylık tarih aralıklarını hesapla
        const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
        const monthAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);

        // Toplam istatistikler
        document.getElementById('total-visits').textContent = analyticsData.total_stats?.total_page_visits || 0;
        document.getElementById('total-clicks').textContent = analyticsData.total_stats?.total_site_clicks || 0;

        // Bugünkü istatistikler
        const todayStats = analyticsData.daily_stats?.[todayStr];
        document.getElementById('today-visits').textContent = todayStats?.visits || 0;
        document.getElementById('today-clicks').textContent = todayStats?.clicks || 0;

        // Haftalık istatistikler hesapla
        let weeklyVisits = 0;
        let weeklyClicks = 0;

        if (analyticsData.page_visits) {
            Object.keys(analyticsData.page_visits).forEach(date => {
                const visitDate = new Date(date);
                if (visitDate >= weekAgo && visitDate <= today) {
                    weeklyVisits += analyticsData.page_visits[date] || 0;
                }
            });
        }

        if (analyticsData.daily_stats) {
            Object.keys(analyticsData.daily_stats).forEach(date => {
                const clickDate = new Date(date);
                if (clickDate >= weekAgo && clickDate <= today) {
                    weeklyClicks += analyticsData.daily_stats[date]?.clicks || 0;
                }
            });
        }

        document.getElementById('weekly-visits').textContent = weeklyVisits;
        document.getElementById('weekly-clicks').textContent = weeklyClicks;

        // Aylık istatistikler hesapla
        let monthlyVisits = 0;
        let monthlyClicks = 0;

        if (analyticsData.page_visits) {
            Object.keys(analyticsData.page_visits).forEach(date => {
                const visitDate = new Date(date);
                if (visitDate >= monthAgo && visitDate <= today) {
                    monthlyVisits += analyticsData.page_visits[date] || 0;
                }
            });
        }

        if (analyticsData.daily_stats) {
            Object.keys(analyticsData.daily_stats).forEach(date => {
                const clickDate = new Date(date);
                if (clickDate >= monthAgo && clickDate <= today) {
                    monthlyClicks += analyticsData.daily_stats[date]?.clicks || 0;
                }
            });
        }

        document.getElementById('monthly-visits').textContent = monthlyVisits;
        document.getElementById('monthly-clicks').textContent = monthlyClicks;

        // Site tablosunu güncelle
        updateSiteAnalyticsTable();

        // Grafik güncelle
        updateVisitsChart();
    }

    // Site analytics tablosunu güncelle
    function updateSiteAnalyticsTable() {
        const tableBody = document.querySelector('#site-analytics-table tbody');
        tableBody.innerHTML = '';

        // Siteleri al
        fetch('api.php?action=get_sites')
            .then(response => response.json())
            .then(data => {
                const sites = data.sites || [];

                // Unique sites to avoid duplicates
                const uniqueSites = [];
                const seenIds = new Set();

                sites.forEach(site => {
                    if (!seenIds.has(site.id)) {
                        seenIds.add(site.id);
                        uniqueSites.push(site);
                    }
                });

                uniqueSites.forEach(site => {
                    const bannerClicks = calculateSiteClicks(site.id, 'banner');
                    const popupClicks = calculateSiteClicks(site.id, 'popup');
                    const section1Clicks = calculateSiteClicks(site.id, 'section1');
                    const section2Clicks = calculateSiteClicks(site.id, 'section2');
                    const totalClicks = bannerClicks + popupClicks + section1Clicks + section2Clicks;

                    const row = `
                        <tr>
                            <td>${site.name}</td>
                            <td><img src="../img/logo/${site.logo}" alt="${site.name}" style="height: 30px;"></td>
                            <td><span class="badge bg-primary">${bannerClicks}</span></td>
                            <td><span class="badge bg-info">${popupClicks}</span></td>
                            <td><span class="badge bg-success">${section1Clicks}</span></td>
                            <td><span class="badge bg-warning">${section2Clicks}</span></td>
                            <td><strong class="text-dark">${totalClicks}</strong></td>
                        </tr>
                    `;
                    tableBody.innerHTML += row;
                });
                isUpdatingTable = false; // İşlem tamamlandı
            })
            .catch(error => {
                console.error('Site listesi yüklenirken hata:', error);
                isUpdatingTable = false; // Hata durumunda da kilidi kaldır
            });
    }

    // Site için toplam tıklama sayısını hesapla
    function calculateSiteClicks(siteId, location) {
        if (!analyticsData?.site_clicks?.[location]?.[siteId]) return 0;

        const dateFilter = document.getElementById('date-filter').value;
        const siteClicks = analyticsData.site_clicks[location][siteId];
        let total = 0;

        const today = new Date();

        Object.keys(siteClicks).forEach(date => {
            const clickDate = new Date(date);
            let include = false;

            switch(dateFilter) {
                case 'today':
                    include = date === today.toISOString().split('T')[0];
                    break;
                case 'week':
                    const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                    include = clickDate >= weekAgo;
                    break;
                case 'month':
                    const monthAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
                    include = clickDate >= monthAgo;
                    break;
                case 'all':
                    include = true;
                    break;
            }

            if (include) {
                total += siteClicks[date];
            }
        });

        return total;
    }

    // Ziyaret grafiğini güncelle
    function updateVisitsChart() {
        const ctx = document.getElementById('visits-chart').getContext('2d');

        if (chart) {
            chart.destroy();
        }

        const dates = [];
        const visits = [];
        const clicks = [];

        // Son 30 günlük veri hazırla
        for (let i = 29; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            const dateStr = date.toISOString().split('T')[0];

            dates.push(date.toLocaleDateString('tr-TR'));
            visits.push(analyticsData.page_visits?.[dateStr] || 0);
            clicks.push(analyticsData.daily_stats?.[dateStr]?.clicks || 0);
        }

        chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Ziyaretler',
                    data: visits,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }, {
                    label: 'Tıklamalar',
                    data: clicks,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Analytics filtrele
    function filterAnalytics() {
        updateAnalyticsDisplay();
    }

    // Analytics yenile
    function refreshAnalytics() {
        loadAnalytics();
    }

    // Sayfa yüklendiğinde analytics yükle
    document.addEventListener('DOMContentLoaded', function() {
        loadAnalytics();
    });

    // Analytics tab'ı açıldığında yenile
    document.getElementById('analytics-tab').addEventListener('shown.bs.tab', function() {
        loadAnalytics(); // Bu zaten updateSiteAnalyticsTable()'ı çağırıyor
    });
    </script>

    <script>
        // Set global variables for admin system
        window.isSuperAdmin = <?= $is_super_admin ? 'true' : 'false' ?>;
        window.currentUser = <?= json_encode($current_user) ?>;
    </script>
</body>
</html>
