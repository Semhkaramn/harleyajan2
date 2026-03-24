<?php
// JSON veriyi oku
$data_file = 'data/sites.json';
$data = [];

if (file_exists($data_file)) {
    $data = json_decode(file_get_contents($data_file), true);
}

$hero = $data['hero'] ?? [];
$social_links = $data['social_links'] ?? [];
$banners = $data['banners'] ?? [];
$sections = $data['sections'] ?? [];
$categories = $data['categories'] ?? [];
$popup = $data['popup'] ?? [];
$sites = array_filter($data['sites'] ?? [], function($site) {
    return $site['active'] === true;
});

// Türkçe karakterleri küçük harfe çeviren fonksiyon
function turkishToLower($text) {
    $search  = array('İ', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç');
    $replace = array('i', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç');
    $text = str_replace($search, $replace, $text);
    return mb_strtolower($text, 'UTF-8');
}

// Açıklamalardaki br etiketlerini satır sonlarına çeviren fonksiyon
function convertBrToNewlines($text) {
    if (empty($text)) return $text;
    $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
    $text = htmlspecialchars($text);
    $text = nl2br($text);
    return $text;
}

// Section'lara göre siteleri ayır
$section1_sites = [];
$section2_sites = [];

if (isset($categories['section1'])) {
    foreach ($categories['section1'] as $siteId) {
        foreach ($sites as $site) {
            if ($site['id'] == $siteId) {
                $section1_sites[] = $site;
                break;
            }
        }
    }
}

if (isset($categories['section2'])) {
    foreach ($categories['section2'] as $siteId) {
        foreach ($sites as $site) {
            if ($site['id'] == $siteId) {
                $section2_sites[] = $site;
                break;
            }
        }
    }
}

// Banner'ları sıralama için sıralayıp siteyle eşleştir (sadece aktif banner'lar)
usort($banners, function($a, $b) {
    $orderA = $a['order'] ?? 999;
    $orderB = $b['order'] ?? 999;
    return $orderA - $orderB;
});

$banner_sites = [];
foreach ($banners as $banner) {
    if (isset($banner['active']) && $banner['active']) {
        foreach ($sites as $site) {
            if ($site['id'] == $banner['site_id']) {
                $banner_sites[] = array_merge($banner, ['site' => $site]);
                break;
            }
        }
    }
}

// Sosyal medya platform isimleri
$social_names = [
    'telegram' => 'Telegram',
    'instagram' => 'Instagram',
    'youtube' => 'YouTube',
    'twitter' => 'Twitter',
    'discord' => 'Discord',
    'whatsapp' => 'WhatsApp',
    'facebook' => 'Facebook',
    'tiktok' => 'TikTok'
];

// Quicklinks verisi
$active_slots = [];
if (isset($data['slots'])) {
    foreach ($data['slots'] as $slot) {
        if (isset($slot['active']) && $slot['active']) {
            $active_slots[] = $slot;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="shortcut icon" href="img/siteicon.png?v=<?php echo time(); ?>" id="site-favicon" />
    <title>Relaxbet - Güvenilir Sponsor Bahis Siteleri</title>
    <meta name="robots" content="max-image-preview:large"/>
    <meta name="description" content="Relaxbet - Güvenilir bahis siteleri ve sponsorlar"/>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <!-- Ana Stil Dosyası -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>" />

    <!-- Loading Screen Styles -->
    <style>
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #0f0f0f;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        /* Art Deco Background Pattern */
        .page-loader::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                repeating-linear-gradient(45deg, transparent, transparent 40px, rgba(212, 175, 55, 0.03) 40px, rgba(212, 175, 55, 0.03) 80px),
                repeating-linear-gradient(-45deg, transparent, transparent 40px, rgba(16, 185, 129, 0.02) 40px, rgba(16, 185, 129, 0.02) 80px);
            animation: patternPulse 4s ease-in-out infinite;
        }

        /* Golden Glow Effect */
        .page-loader::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15), transparent 60%);
            animation: glowPulse 3s ease-in-out infinite;
        }

        .page-loader.hidden {
            opacity: 0;
            visibility: hidden;
        }

        .loader-content {
            position: relative;
            z-index: 10;
            text-align: center;
            animation: contentFadeIn 0.8s ease-out;
        }

        /* Casino Chip / Roulette Wheel Animation */
        .loader-wheel {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 30px;
        }

        .loader-wheel-outer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 3px solid rgba(212, 175, 55, 0.3);
            border-radius: 50%;
            animation: wheelRotate 3s linear infinite;
        }

        .loader-wheel-outer::before {
            content: '';
            position: absolute;
            top: -3px;
            left: 50%;
            transform: translateX(-50%);
            width: 8px;
            height: 8px;
            background: #d4af37;
            border-radius: 50%;
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.8);
        }

        .loader-wheel-inner {
            position: absolute;
            top: 15px;
            left: 15px;
            right: 15px;
            bottom: 15px;
            border: 2px solid rgba(16, 185, 129, 0.4);
            border-radius: 50%;
            animation: wheelRotateReverse 2s linear infinite;
        }

        .loader-wheel-inner::before {
            content: '';
            position: absolute;
            top: -2px;
            left: 50%;
            transform: translateX(-50%);
            width: 6px;
            height: 6px;
            background: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 12px rgba(16, 185, 129, 0.8);
        }

        .loader-wheel-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #d4af37, #f4d03f);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow:
                0 0 30px rgba(212, 175, 55, 0.5),
                inset 0 2px 10px rgba(255, 255, 255, 0.3);
            animation: centerPulse 2s ease-in-out infinite;
        }

        .loader-wheel-center i {
            font-size: 1.3rem;
            color: #0f0f0f;
        }

        /* Floating Particles */
        .loader-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }

        .loader-particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: #d4af37;
            border-radius: 50%;
            animation: particleFloat 4s ease-in-out infinite;
        }

        .loader-particle:nth-child(1) { left: 20%; animation-delay: 0s; }
        .loader-particle:nth-child(2) { left: 40%; animation-delay: 0.5s; background: #10b981; }
        .loader-particle:nth-child(3) { left: 60%; animation-delay: 1s; }
        .loader-particle:nth-child(4) { left: 80%; animation-delay: 1.5s; background: #10b981; }
        .loader-particle:nth-child(5) { left: 30%; animation-delay: 2s; }
        .loader-particle:nth-child(6) { left: 70%; animation-delay: 2.5s; background: #10b981; }

        /* Brand Text */
        .loader-brand {
            margin-bottom: 8px;
        }

        .loader-title {
            font-family: 'Outfit', sans-serif;
            font-size: clamp(1.5rem, 6vw, 2.2rem);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: #fafafa;
            margin: 0;
            animation: titleReveal 0.8s ease-out 0.2s both;
        }

        .loader-title span {
            background: linear-gradient(135deg, #d4af37, #f4d03f, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            background-size: 200% 100%;
            animation: gradientShift 3s ease-in-out infinite;
        }

        .loader-tagline {
            font-family: 'Outfit', sans-serif;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.4em;
            text-transform: uppercase;
            color: #d4af37;
            margin: 0;
            opacity: 0.8;
            animation: titleReveal 0.8s ease-out 0.4s both;
        }

        /* Progress Bar */
        .loader-progress {
            width: 180px;
            height: 3px;
            background: rgba(212, 175, 55, 0.15);
            margin: 25px auto 0;
            border-radius: 3px;
            overflow: hidden;
            position: relative;
        }

        .loader-progress-bar {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #d4af37, #10b981, #d4af37);
            background-size: 200% 100%;
            border-radius: 3px;
            animation: progressFill 1.2s cubic-bezier(0.4, 0, 0.2, 1) forwards, gradientMove 1s linear infinite;
        }

        /* Decorative Lines */
        .loader-decor {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 20px;
            animation: decorReveal 0.8s ease-out 0.5s both;
        }

        .loader-decor-line {
            width: 40px;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.5), transparent);
        }

        .loader-decor-diamond {
            width: 8px;
            height: 8px;
            background: #d4af37;
            transform: rotate(45deg);
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.5);
        }

        /* Corner Decorations */
        .loader-corner {
            position: absolute;
            width: 60px;
            height: 60px;
            border: 2px solid rgba(212, 175, 55, 0.2);
        }

        .loader-corner--tl {
            top: 30px;
            left: 30px;
            border-right: none;
            border-bottom: none;
            animation: cornerFadeIn 0.8s ease-out 0.3s both;
        }

        .loader-corner--tr {
            top: 30px;
            right: 30px;
            border-left: none;
            border-bottom: none;
            animation: cornerFadeIn 0.8s ease-out 0.4s both;
        }

        .loader-corner--bl {
            bottom: 30px;
            left: 30px;
            border-right: none;
            border-top: none;
            animation: cornerFadeIn 0.8s ease-out 0.5s both;
        }

        .loader-corner--br {
            bottom: 30px;
            right: 30px;
            border-left: none;
            border-top: none;
            animation: cornerFadeIn 0.8s ease-out 0.6s both;
        }

        /* Animations */
        @keyframes patternPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        @keyframes glowPulse {
            0%, 100% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
            50% {
                transform: translate(-50%, -50%) scale(1.1);
                opacity: 0.7;
            }
        }

        @keyframes contentFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes wheelRotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes wheelRotateReverse {
            from { transform: rotate(360deg); }
            to { transform: rotate(0deg); }
        }

        @keyframes centerPulse {
            0%, 100% {
                transform: translate(-50%, -50%) scale(1);
                box-shadow: 0 0 30px rgba(212, 175, 55, 0.5), inset 0 2px 10px rgba(255, 255, 255, 0.3);
            }
            50% {
                transform: translate(-50%, -50%) scale(1.05);
                box-shadow: 0 0 50px rgba(212, 175, 55, 0.7), inset 0 2px 10px rgba(255, 255, 255, 0.3);
            }
        }

        @keyframes particleFloat {
            0% {
                transform: translateY(100vh) scale(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-20vh) scale(1);
                opacity: 0;
            }
        }

        @keyframes titleReveal {
            from {
                opacity: 0;
                transform: translateY(15px);
                letter-spacing: 0.3em;
            }
            to {
                opacity: 1;
                transform: translateY(0);
                letter-spacing: 0.15em;
            }
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        @keyframes progressFill {
            0% { width: 0%; }
            100% { width: 100%; }
        }

        @keyframes gradientMove {
            0% { background-position: 0% 50%; }
            100% { background-position: 200% 50%; }
        }

        @keyframes decorReveal {
            from {
                opacity: 0;
                transform: scaleX(0);
            }
            to {
                opacity: 1;
                transform: scaleX(1);
            }
        }

        @keyframes cornerFadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* Mobile Adjustments */
        @media (max-width: 480px) {
            .loader-wheel {
                width: 90px;
                height: 90px;
            }

            .loader-wheel-center {
                width: 40px;
                height: 40px;
            }

            .loader-wheel-center i {
                font-size: 1rem;
            }

            .loader-corner {
                width: 40px;
                height: 40px;
            }

            .loader-corner--tl,
            .loader-corner--tr {
                top: 20px;
            }

            .loader-corner--bl,
            .loader-corner--br {
                bottom: 20px;
            }

            .loader-corner--tl,
            .loader-corner--bl {
                left: 20px;
            }

            .loader-corner--tr,
            .loader-corner--br {
                right: 20px;
            }
        }
    </style>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
    <!-- Loading Screen -->
    <div class="page-loader" id="pageLoader">
        <!-- Corner Decorations -->
        <div class="loader-corner loader-corner--tl"></div>
        <div class="loader-corner loader-corner--tr"></div>
        <div class="loader-corner loader-corner--bl"></div>
        <div class="loader-corner loader-corner--br"></div>

        <!-- Floating Particles -->
        <div class="loader-particles">
            <div class="loader-particle"></div>
            <div class="loader-particle"></div>
            <div class="loader-particle"></div>
            <div class="loader-particle"></div>
            <div class="loader-particle"></div>
            <div class="loader-particle"></div>
        </div>

        <div class="loader-content">
            <!-- Casino Wheel Animation -->
            <div class="loader-wheel">
                <div class="loader-wheel-outer"></div>
                <div class="loader-wheel-inner"></div>
                <div class="loader-wheel-center">
                    <i class="fas fa-gem"></i>
                </div>
            </div>

            <!-- Brand -->
            <div class="loader-brand">
                <h1 class="loader-title"><span>Relaxbet</span></h1>
                <p class="loader-tagline">Premium Sponsors</p>
            </div>

            <!-- Progress Bar -->
            <div class="loader-progress">
                <div class="loader-progress-bar"></div>
            </div>

            <!-- Decorative -->
            <div class="loader-decor">
                <div class="loader-decor-line"></div>
                <div class="loader-decor-diamond"></div>
                <div class="loader-decor-line"></div>
            </div>
        </div>
    </div>

    <!-- HEADER -->
    <header class="site-header">
        <div class="container">
            <div class="header-inner">
                <a href="/" class="header-brand">
                    <img src="img/logo.png" alt="Relaxbet" class="header-logo-img" />
                    <div class="header-brand-text">
                        <span class="brand-name">Relaxbet</span></span>
                        <span class="brand-tagline">Güvenilir Sponsorlar</span>
                    </div>
                </a>


            </div>
        </div>
    </header>



    <main>
        <!-- Search Section -->
        <section class="section search-section">
            <div class="container">
                <div class="search-wrapper">
                    <div class="search-input-wrapper">
                        <input type="text" class="search-input" id="searchInput" placeholder="Site ara..." autocomplete="off">
                        <i class="fas fa-search search-icon"></i>
                        <button class="search-clear" id="searchClear">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <!-- Arama Sonuçları Dropdown -->
                    <div class="search-results" id="searchResults">
                        <div class="search-results-grid">
                            <?php foreach ($sites as $site): ?>
                            <a href="<?= htmlspecialchars($site['link']) ?>" target="_blank" class="search-result-item" data-name="<?= htmlspecialchars(turkishToLower($site['name'])) ?>" data-name-original="<?= htmlspecialchars($site['name']) ?>">
                                <div class="search-result-logo">
                                    <img src="img/logo/<?= htmlspecialchars($site['logo']) ?>" alt="<?= htmlspecialchars($site['name']) ?>" />
                                </div>
                                <div class="search-result-info">
                                    <span class="search-result-name"><?= htmlspecialchars($site['name']) ?></span>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="search-no-result" id="searchNoResult">
                            <i class="fas fa-search"></i>
                            <span>Sonuç bulunamadı</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Banners -->
        <?php if (!empty($banner_sites)): ?>
        <section class="section section-banners">
            <div class="container">
                <div class="banners-grid">
                    <?php foreach ($banner_sites as $index => $banner): ?>
                    <a href="<?= htmlspecialchars($banner['link']) ?>" target="_blank" class="banner-card" data-banner-logo="img/logo/<?= htmlspecialchars($banner['site']['logo']) ?>">
                        <div class="banner-glow"></div>
                        <div class="banner-content">
                            <div class="banner-text">
                                <?= $banner['title'] ?>
                            </div>
                            <div class="banner-logo">
                                <img src="img/logo/<?= htmlspecialchars($banner['site']['logo']) ?>" alt="<?= htmlspecialchars($banner['site']['name']) ?>" />
                            </div>
                        </div>

                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Section 1 - Ana Sponsorlar -->
        <section class="section section-sponsors" id="section1">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">
                        <?= $sections['section1']['title'] ?? 'ANA <span>Sponsorlar</span>' ?>
                    </h2>
                    <div class="section-line"></div>
                </div>
                <div class="sponsors-grid sponsors-grid-main">
                    <?php foreach ($section1_sites as $index => $site): ?>
                    <div class="sponsor-card" data-logo="img/logo/<?= htmlspecialchars($site['logo']) ?>" data-name="<?= htmlspecialchars(turkishToLower($site['name'])) ?>">
                        <div class="card-shine"></div>
                        <div class="card-border"></div>
                        <div class="card-particles"></div>
                        <div class="card-content">

                            <div class="card-logo">
                                <img src="img/logo/<?= htmlspecialchars($site['logo']) ?>" alt="<?= htmlspecialchars($site['name']) ?>" />
                            </div>
                            <div class="card-info">
                                <div class="card-divider"></div>
                                <p class="card-desc"><?= convertBrToNewlines($site['description']) ?></p>
                            </div>
                            <a href="<?= htmlspecialchars($site['link']) ?>" target="_blank" class="card-btn" data-site-id="<?= $site['id'] ?>">
                                <span>Siteye Git</span>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="no-results" id="noResults1">
                    <i class="fas fa-search"></i>
                    <h3>Sonuç Bulunamadı</h3>
                    <p>Aradığınız kriterlere uygun site bulunamadı.</p>
                </div>
            </div>
        </section>

        <!-- Section 2 - VIP Sponsorlar -->
        <?php if (!empty($section2_sites)): ?>
        <section class="section section-vip" id="section2">
            <div class="container">
                <div class="section-header section-header-vip">
                    <div class="vip-crown"><i class="fas fa-gem"></i></div>
                    <div class="section-badge section-badge-vip">Exclusive</div>
                    <h2 class="section-title section-title-vip">
                        <?= $sections['section2']['title'] ?? 'VIP <span>Sponsorlar</span>' ?>
                    </h2>
                    <div class="section-line section-line-vip"></div>
                </div>
                <div class="sponsors-grid sponsors-grid-vip">
                    <?php foreach ($section2_sites as $index => $site): ?>
                    <div class="sponsor-card sponsor-card-vip" data-logo="img/logo/<?= htmlspecialchars($site['logo']) ?>" data-name="<?= htmlspecialchars(turkishToLower($site['name'])) ?>">
                        <div class="vip-badge"><i class="fas fa-star"></i> VIP</div>
                        <div class="card-shine card-shine-vip"></div>
                        <div class="card-border card-border-vip"></div>
                        <div class="card-particles"></div>
                        <div class="card-content">
                            <div class="card-logo card-logo-vip">
                                <img src="img/logo/<?= htmlspecialchars($site['logo']) ?>" alt="<?= htmlspecialchars($site['name']) ?>" />
                            </div>
                            <div class="card-info">
                                <div class="card-divider card-divider-vip"></div>
                                <p class="card-desc"><?= convertBrToNewlines($site['description']) ?></p>
                            </div>
                            <a href="<?= htmlspecialchars($site['link']) ?>" target="_blank" class="card-btn card-btn-vip" data-site-id="<?= $site['id'] ?>">
                                <span>Hemen Katıl</span>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="no-results" id="noResults2">
                    <i class="fas fa-search"></i>
                    <h3>Sonuç Bulunamadı</h3>
                    <p>Aradığınız kriterlere uygun site bulunamadı.</p>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Quicklinks Section - Now at the bottom -->
        <?php if (!empty($active_slots)): ?>
        <section class="section section-quicklinks">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title section-title-quicklinks">
                        Hızlı <span>Bağlantılar</span>
                    </h2>
                    <div class="section-line"></div>
                </div>
                <div class="quicklinks-grid">
                    <?php foreach ($active_slots as $slot): ?>
                    <a href="<?= htmlspecialchars($slot['link']) ?>" target="_blank" class="quicklink-card">
                        <div class="quicklink-icon">
                            <i class="fab fa-<?= $slot['type'] ?>"></i>
                        </div>
                        <div class="quicklink-content">
                            <div class="quicklink-title"><?= $slot['title'] ?></div>
                            <div class="quicklink-text"><?= htmlspecialchars($slot['text']) ?></div>
                        </div>

                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <?php
    // Popup
    if (!empty($popup) && isset($popup['active']) && $popup['active'] && isset($popup['site_id'])):
        $popupSite = null;
        foreach ($sites as $site) {
            if ($site['id'] == $popup['site_id']) {
                $popupSite = $site;
                break;
            }
        }

        $popupNumber = $popup['number'] ?? '5 0 0 T L';
        $popupTitle = $popup['title'] ?? 'D E N E M E';
        $popupSubtitle = $popup['subtitle'] ?? 'B O N U S U';

        if ($popupSite):
    ?>
    <div class="popup-overlay" id="popup" aria-hidden="true">
        <div class="popup-container">
            <button class="popup-close" id="popupClose"><i class="fas fa-times"></i></button>
            <div class="popup-content">
                <div class="popup-glow"></div>
                <div class="popup-logo">
                    <img src="img/logo/<?= htmlspecialchars($popupSite['logo']) ?>" alt="<?= htmlspecialchars($popupSite['name']) ?>" />
                </div>
                <div class="popup-number"><?= htmlspecialchars($popupNumber) ?></div>
                <div class="popup-title"><?= htmlspecialchars($popupTitle) ?></div>
                <div class="popup-subtitle"><?= htmlspecialchars($popupSubtitle) ?></div>
                <a href="<?= htmlspecialchars($popupSite['link']) ?>" target="_blank" class="popup-btn">
                    <span>Hemen Katıl</span>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <img src="img/logo.png" alt="Relaxbet" />
                    <p>Güvenilir bahis siteleri ve sponsorlarınız için tek adres.</p>
                </div>


            </div>
            <div class="footer-bottom">
                <p>Copyright &copy; <?= date('Y') ?> Relaxbet - <a href="https://t.me/xangeiletisim" target="_blank">Xange İletişim</a></p>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="assets/js/libs.js"></script>
    <script>
    // Türkçe karakterleri küçük harfe çeviren fonksiyon
    function turkishToLower(str) {
        const charMap = {
            'İ': 'i', 'I': 'ı', 'Ğ': 'ğ', 'Ü': 'ü', 'Ş': 'ş', 'Ö': 'ö', 'Ç': 'ç',
            'i': 'i', 'ı': 'ı', 'ğ': 'ğ', 'ü': 'ü', 'ş': 'ş', 'ö': 'ö', 'ç': 'ç'
        };
        let result = '';
        for (let i = 0; i < str.length; i++) {
            const char = str[i];
            result += charMap[char] || char.toLowerCase();
        }
        return result;
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Hide loading screen
        const pageLoader = document.getElementById('pageLoader');

        // Wait for images and colors to be extracted
        setTimeout(() => {
            pageLoader.classList.add('hidden');
            setTimeout(() => {
                pageLoader.style.display = 'none';
            }, 500);
        }, 800);



        // Search Functionality with Turkish character support
        const searchInput = document.getElementById('searchInput');
        const searchClear = document.getElementById('searchClear');
        const sponsorCards = document.querySelectorAll('.sponsor-card');
        const noResults1 = document.getElementById('noResults1');
        const noResults2 = document.getElementById('noResults2');
        const searchResults = document.getElementById('searchResults');
        const searchNoResult = document.getElementById('searchNoResult');
        const searchResultItems = document.querySelectorAll('.search-result-item');

        function filterCards(searchTerm) {
            searchTerm = turkishToLower(searchTerm.trim());
            let section1Visible = 0;
            let section2Visible = 0;

            sponsorCards.forEach(card => {
                const name = card.getAttribute('data-name') || '';
                const isVip = card.classList.contains('sponsor-card-vip');

                if (searchTerm === '' || name.includes(searchTerm)) {
                    card.classList.remove('hidden');
                    if (isVip) section2Visible++;
                    else section1Visible++;
                } else {
                    card.classList.add('hidden');
                }
            });

            // Show/hide no results messages
            if (noResults1) {
                noResults1.classList.toggle('visible', section1Visible === 0 && searchTerm !== '');
            }
            if (noResults2) {
                noResults2.classList.toggle('visible', section2Visible === 0 && searchTerm !== '');
            }

            // Show/hide clear button
            searchClear.classList.toggle('visible', searchTerm !== '');
        }

        // Search Dropdown with Turkish character support
        function filterSearchDropdown(searchTerm) {
            searchTerm = turkishToLower(searchTerm.trim());
            let found = 0;
            searchResultItems.forEach(item => {
                const name = item.getAttribute('data-name') || '';
                if (searchTerm !== '' && name.includes(searchTerm)) {
                    item.style.display = '';
                    found++;
                } else {
                    item.style.display = 'none';
                }
            });
            if (searchTerm === '') {
                searchResults.classList.remove('active');
            } else {
                searchResults.classList.add('active');
            }
            if (found === 0 && searchTerm !== '') {
                searchNoResult.classList.add('visible');
            } else {
                searchNoResult.classList.remove('visible');
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                filterCards(e.target.value);
                filterSearchDropdown(e.target.value);
            });
            searchInput.addEventListener('focus', (e) => {
                if (searchInput.value.trim() !== '') {
                    searchResults.classList.add('active');
                }
            });
            searchInput.addEventListener('blur', (e) => {
                setTimeout(() => {
                    searchResults.classList.remove('active');
                }, 200);
            });
        }

        if (searchClear) {
            searchClear.addEventListener('click', () => {
                searchInput.value = '';
                filterCards('');
                filterSearchDropdown('');
                searchInput.focus();
            });
        }

        // Search result item click closes dropdown
        searchResultItems.forEach(item => {
            item.addEventListener('click', () => {
                searchResults.classList.remove('active');
            });
        });

        // Popup
        const popup = document.getElementById('popup');
        const popupClose = document.getElementById('popupClose');

        if (popup) {
            // Show popup after 2 seconds
            setTimeout(() => {
                popup.classList.add('active');
            }, 2000);

            if (popupClose) {
                popupClose.addEventListener('click', () => {
                    popup.classList.remove('active');
                });
            }

            // Close on overlay click
            popup.addEventListener('click', (e) => {
                if (e.target === popup) {
                    popup.classList.remove('active');
                }
            });
        }

        // Header scroll effect
        const header = document.querySelector('.site-header');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Card color extraction from logo
        function extractColors() {
            document.querySelectorAll('.sponsor-card').forEach(card => {
                const logoImg = card.querySelector('.card-logo img');
                if (logoImg && logoImg.complete) {
                    setCardColor(card, logoImg);
                } else if (logoImg) {
                    logoImg.addEventListener('load', () => setCardColor(card, logoImg));
                }
            });
            // Banner renk çıkarma
            document.querySelectorAll('.banner-card').forEach(card => {
                const logoImg = card.querySelector('.banner-logo img');
                if (logoImg && logoImg.complete) {
                    setBannerColor(card, logoImg);
                } else if (logoImg) {
                    logoImg.addEventListener('load', () => setBannerColor(card, logoImg));
                }
            });
        }

        function setCardColor(card, img) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = img.naturalWidth || 100;
            canvas.height = img.naturalHeight || 100;

            try {
                ctx.drawImage(img, 0, 0);
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height).data;

                let colorCounts = {};
                for (let i = 0; i < imageData.length; i += 4) {
                    const r = imageData[i];
                    const g = imageData[i + 1];
                    const b = imageData[i + 2];
                    const a = imageData[i + 3];

                    // Skip transparent, white-ish, and black-ish colors
                    if (a < 128) continue;
                    if (r > 200 && g > 200 && b > 200) continue;
                    if (r < 30 && g < 30 && b < 30) continue;

                    // Check if it's a colorful pixel
                    const max = Math.max(r, g, b);
                    const min = Math.min(r, g, b);
                    if (max - min < 30) continue; // Skip gray tones

                    const key = `${Math.round(r/10)*10},${Math.round(g/10)*10},${Math.round(b/10)*10}`;
                    colorCounts[key] = (colorCounts[key] || 0) + 1;
                }

                // Find dominant color
                let dominantColor = null;
                let maxCount = 0;
                for (const [color, count] of Object.entries(colorCounts)) {
                    if (count > maxCount) {
                        maxCount = count;
                        dominantColor = color;
                    }
                }

                if (dominantColor) {
                    const [r, g, b] = dominantColor.split(',').map(Number);
                    card.style.setProperty('--card-accent', `rgb(${r}, ${g}, ${b})`);
                    card.style.setProperty('--card-accent-light', `rgba(${r}, ${g}, ${b}, 0.15)`);
                    card.style.setProperty('--card-accent-glow', `rgba(${r}, ${g}, ${b}, 0.4)`);
                }
            } catch (e) {
                // CORS or other error - use default color
            }
        }

        // Banner renk çıkarma fonksiyonu
        function setBannerColor(card, img) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = img.naturalWidth || 100;
            canvas.height = img.naturalHeight || 100;

            try {
                ctx.drawImage(img, 0, 0);
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height).data;

                let colorCounts = {};
                for (let i = 0; i < imageData.length; i += 4) {
                    const r = imageData[i];
                    const g = imageData[i + 1];
                    const b = imageData[i + 2];
                    const a = imageData[i + 3];

                    // Skip transparent, white-ish, and black-ish colors
                    if (a < 128) continue;
                    if (r > 200 && g > 200 && b > 200) continue;
                    if (r < 30 && g < 30 && b < 30) continue;

                    // Check if it's a colorful pixel
                    const max = Math.max(r, g, b);
                    const min = Math.min(r, g, b);
                    if (max - min < 30) continue; // Skip gray tones

                    const key = `${Math.round(r/10)*10},${Math.round(g/10)*10},${Math.round(b/10)*10}`;
                    colorCounts[key] = (colorCounts[key] || 0) + 1;
                }

                // Find dominant color
                let dominantColor = null;
                let maxCount = 0;
                for (const [color, count] of Object.entries(colorCounts)) {
                    if (count > maxCount) {
                        maxCount = count;
                        dominantColor = color;
                    }
                }

                if (dominantColor) {
                    const [r, g, b] = dominantColor.split(',').map(Number);
                    card.style.setProperty('--banner-accent', `rgb(${r}, ${g}, ${b})`);
                    card.style.setProperty('--banner-accent-light', `rgba(${r}, ${g}, ${b}, 0.15)`);
                    card.style.setProperty('--banner-accent-glow', `rgba(${r}, ${g}, ${b}, 0.4)`);
                }
            } catch (e) {
                // CORS or other error - use default color
            }
        }

        // Run color extraction
        setTimeout(extractColors, 300);

        // Analytics Tracking
        fetch('admin/api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'track_visit' })
        }).catch(() => {});

        // Track clicks
        document.querySelectorAll('.card-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const siteId = this.getAttribute('data-site-id');
                const section = this.closest('#section1') ? 'section1' : 'section2';
                if (siteId) {
                    fetch('admin/api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'track_click', site_id: parseInt(siteId), location: section })
                    }).catch(() => {});
                }
            });
        });
    });
    </script>
</body>
</html>
