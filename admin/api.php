<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

$data_file = '../data/sites.json';

// JSON dosyasını oku
function getData() {
    global $data_file;
    if (file_exists($data_file)) {
        return json_decode(file_get_contents($data_file), true);
    }
    return null;
}

// JSON dosyasına yaz
function saveData($data) {
    global $data_file;
    return file_put_contents($data_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Yeni ID oluştur
function getNextId($items) {
    if (empty($items)) return 1;
    return max(array_column($items, 'id')) + 1;
}

// GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $data = getData();

    switch ($action) {
        case 'get_sites':
            echo json_encode([
                'sites' => $data['sites'] ?? [],
                'categories' => $data['categories'] ?? []
            ]);
            break;

        case 'get_site':
            $id = intval($_GET['id'] ?? 0);
            $site = null;
            if ($data && isset($data['sites'])) {
                foreach ($data['sites'] as $s) {
                    if ($s['id'] == $id) {
                        $site = $s;
                        break;
                    }
                }
            }
            echo json_encode(['site' => $site]);
            break;

        case 'get_settings':
            echo json_encode([
                'hero' => $data['hero'] ?? [],
                'sections' => $data['sections'] ?? [],
                'popup' => $data['popup'] ?? []
            ]);
            break;

        case 'get_banners':
            echo json_encode(['banners' => $data['banners'] ?? []]);
            break;

        case 'get_social':
            echo json_encode(['social_links' => $data['social_links'] ?? []]);
            break;

        case 'get_slots':
            echo json_encode(['slots' => $data['slots'] ?? []]);
            break;

        case 'get_admin_users':
            session_start();
            if (!isset($_SESSION['admin_user']) || $_SESSION['admin_user']['role'] !== 'super_admin') {
                echo json_encode(['error' => 'Yetkisiz erişim']);
                exit;
            }

            $users = getAdminUsers();
            echo json_encode(['users' => $users]);
            break;

        default:
            echo json_encode(['error' => 'Geçersiz action']);

        case 'get_analytics':
                    $analytics_file = '../data/analytics.json';
                    if (file_exists($analytics_file)) {
                        $analytics = json_decode(file_get_contents($analytics_file), true);
                        echo json_encode(['analytics' => $analytics]);
                    } else {
                        echo json_encode(['analytics' => null]);
                    }
                    break;
    }
    exit;
}

// POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getData();

    // JSON POST data için
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'add_site':
            $name = $_POST['name'] ?? '';
            $link = $_POST['link'] ?? '';
            $description = $_POST['description'] ?? '';

            if (empty($name) || empty($link)) {
                echo json_encode(['success' => false, 'message' => 'Site adı ve link gereklidir.']);
                exit;
            }

            // Logo upload - enhanced version
            if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Logo dosyası gereklidir.']);
                exit;
            }

            $uploadResult = uploadSecureLogo($_FILES['logo'], $name);
            if (!$uploadResult['success']) {
                echo json_encode($uploadResult);
                exit;
            }

            // Yeni site ekle
            $newSite = [
                'id' => getNextId($data['sites']),
                'name' => $name,
                'logo' => $uploadResult['filename'],
                'link' => $link,
                'description' => $description,
                'active' => true,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $data['sites'][] = $newSite;

            // Categories yapısını oluştur (ama otomatik ekleme)
            if (!isset($data['categories'])) {
                $data['categories'] = ['section1' => [], 'section2' => []];
            }

            // Banner yapısını oluştur (ama otomatik banner ekleme)
            if (!isset($data['banners'])) {
                $data['banners'] = [];
            }

            if (saveData($data)) {
                echo json_encode(['success' => true, 'message' => 'Site başarıyla eklendi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
            }
            break;

        case 'edit_site':
            $id = intval($_POST['id'] ?? 0);
            $name = $_POST['name'] ?? '';
            $link = $_POST['link'] ?? '';
            $description = $_POST['description'] ?? '';

            if (empty($name) || empty($link)) {
                echo json_encode(['success' => false, 'message' => 'Site adı ve link gereklidir.']);
                exit;
            }

            $siteIndex = -1;
            foreach ($data['sites'] as $index => $site) {
                if ($site['id'] == $id) {
                    $siteIndex = $index;
                    break;
                }
            }

            if ($siteIndex === -1) {
                echo json_encode(['success' => false, 'message' => 'Site bulunamadı.']);
                exit;
            }

            // Logo güncellemesi varsa
            $logoFilename = $data['sites'][$siteIndex]['logo'];
            $oldSiteName = $data['sites'][$siteIndex]['name'];

            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadSecureLogo($_FILES['logo'], $name);
                if (!$uploadResult['success']) {
                    echo json_encode($uploadResult);
                    exit;
                }

                // Eski logoyu sil
                $oldLogoPath = '../img/logo/' . $logoFilename;
                if (file_exists($oldLogoPath)) {
                    unlink($oldLogoPath);
                }

                $logoFilename = $uploadResult['filename'];
            } else if ($name !== $oldSiteName) {
                // Site adı değişti ama yeni logo yüklenmedi - mevcut logo dosyasının adını değiştir
                $oldLogoPath = '../img/logo/' . $logoFilename;
                if (file_exists($oldLogoPath)) {
                    // Dosya uzantısını al
                    $pathInfo = pathinfo($logoFilename);
                    $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

                    // Yeni logo dosya adını oluştur - site adını temizle
                    $cleanName = strtolower(trim($name));
                    $cleanName = preg_replace('/[^a-zA-Z0-9]/', '', $cleanName);
                    $newLogoFilename = $cleanName . $extension;
                    $newLogoPath = '../img/logo/' . $newLogoFilename;

                    // Eğer yeni dosya adı farklıysa dosyayı yeniden adlandır
                    if ($logoFilename !== $newLogoFilename) {
                        if (rename($oldLogoPath, $newLogoPath)) {
                            $logoFilename = $newLogoFilename;
                        }
                    }
                }
            }

            // Site güncelle
            $data['sites'][$siteIndex] = [
                'id' => $id,
                'name' => $name,
                'logo' => $logoFilename,
                'link' => $link,
                'description' => $description,
                'active' => $data['sites'][$siteIndex]['active'],
                'created_at' => $data['sites'][$siteIndex]['created_at'] ?? date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Bu siteyi kullanan tüm banner'ları da güncelle
            if (isset($data['banners'])) {
                foreach ($data['banners'] as &$banner) {
                    if ($banner['site_id'] == $id) {
                        $banner['link'] = $link; // Yeni linki güncelle
                    }
                }
            }

            // Popup da bu siteyi kullanıyorsa güncelle
            if (isset($data['popup']) && $data['popup']['site_id'] == $id) {
                // Popup için site bilgileri gerekirse buraya eklenebilir
            }

            if (saveData($data)) {
                echo json_encode(['success' => true, 'message' => 'Site ve ilgili banner\'lar başarıyla güncellendi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
            }
            break;

        case 'delete_site':
            $id = intval($input['id'] ?? 0);

            $siteIndex = -1;
            $logoFilename = '';
            foreach ($data['sites'] as $index => $site) {
                if ($site['id'] == $id) {
                    $siteIndex = $index;
                    $logoFilename = $site['logo'];
                    break;
                }
            }

            if ($siteIndex === -1) {
                echo json_encode(['success' => false, 'message' => 'Site bulunamadı.']);
                exit;
            }

            // Logo dosyasını sil
            $logoPath = '../img/logo/' . $logoFilename;
            if (file_exists($logoPath)) {
                unlink($logoPath);
            }

            // Site'ı listeden çıkar
            array_splice($data['sites'], $siteIndex, 1);

            // Categories'den de kaldır
            if (isset($data['categories'])) {
                foreach ($data['categories'] as &$category) {
                    $key = array_search($id, $category);
                    if ($key !== false) {
                        array_splice($category, $key, 1);
                    }
                }
            }

            // Banner'ları da kaldır
            if (isset($data['banners'])) {
                foreach ($data['banners'] as $index => $banner) {
                    if ($banner['site_id'] == $id) {
                        unset($data['banners'][$index]);
                    }
                }
                $data['banners'] = array_values($data['banners']);
            }

            if (saveData($data)) {
                echo json_encode(['success' => true, 'message' => 'Site başarıyla silindi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
            }
            break;

        case 'toggle_site':
            $id = intval($input['id'] ?? 0);

            $siteIndex = -1;
            foreach ($data['sites'] as $index => $site) {
                if ($site['id'] == $id) {
                    $siteIndex = $index;
                    break;
                }
            }

            if ($siteIndex === -1) {
                echo json_encode(['success' => false, 'message' => 'Site bulunamadı.']);
                exit;
            }

            $data['sites'][$siteIndex]['active'] = !$data['sites'][$siteIndex]['active'];

            if (saveData($data)) {
                $status = $data['sites'][$siteIndex]['active'] ? 'aktif' : 'pasif';
                echo json_encode(['success' => true, 'message' => "Site $status hale getirildi."]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
            }
            break;

        case 'reorder_sites':
            $section = $input['section'] ?? '';
            $siteIds = $input['siteIds'] ?? [];

            if (empty($section) || empty($siteIds)) {
                echo json_encode(['success' => false, 'message' => 'Section ve site ID\'leri gerekli.']);
                exit;
            }

            // Categories güncelle
            if (!isset($data['categories'])) {
                $data['categories'] = ['section1' => [], 'section2' => []];
            }

            $data['categories'][$section] = array_map('intval', $siteIds);

            if (saveData($data)) {
                echo json_encode(['success' => true, 'message' => 'Sıralama güncellendi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
            }
            break;

        case 'update_settings':
            $title = $_POST['title'] ?? '';
            $subtitle = $_POST['subtitle'] ?? '';
            $section1_title = $_POST['section1_title'] ?? 'Özel Sponsor Siteleri';
            $section2_title = $_POST['section2_title'] ?? 'Güncel Bahis Siteleri';

            $data['hero'] = [
                'title' => $title,
                'subtitle' => $subtitle
            ];

            $data['sections'] = [
                'section1' => [
                    'title' => $section1_title,
                    'active' => true
                ],
                'section2' => [
                    'title' => $section2_title,
                    'active' => true
                ]
            ];

            // Popup ayarları
            $popup_number = $_POST['popup_number'] ?? '5 0 0 T L';
            $popup_title = $_POST['popup_title'] ?? 'D E N E M E';
            $popup_subtitle = $_POST['popup_subtitle'] ?? 'B O N U S U';
            $popup_site_id = intval($_POST['popup_site_id'] ?? 1);
            $popup_active = isset($_POST['popup_active']) && $_POST['popup_active'] === 'on';

            $data['popup'] = [
                'number' => $popup_number,
                'title' => $popup_title,
                'subtitle' => $popup_subtitle,
                'site_id' => $popup_site_id,
                'active' => $popup_active
            ];

            if (saveData($data)) {
                echo json_encode(['success' => true, 'message' => 'Ayarlar başarıyla güncellendi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
            }
            break;

        case 'update_social':
            $socialData = [];

            $platforms = ['telegram', 'instagram', 'youtube', 'twitter', 'facebook', 'discord', 'whatsapp', 'tiktok', 'teams', 'email'];

            foreach ($platforms as $platform) {
                $inputValue = trim($_POST[$platform] ?? '');

                if (!empty($inputValue)) {
                    // Her platform için uygun URL formatına dönüştür
                    switch ($platform) {
                        case 'telegram':
                            $username = ltrim($inputValue, '@');
                            $socialData[$platform] = "https://t.me/" . $username;
                            break;

                        case 'instagram':
                            $username = ltrim($inputValue, '@');
                            $socialData[$platform] = "https://www.instagram.com/" . $username;
                            break;

                        case 'youtube':
                            $username = ltrim($inputValue, '@');
                            $socialData[$platform] = "https://www.youtube.com/@" . $username;
                            break;

                        case 'twitter':
                            $username = ltrim($inputValue, '@');
                            $socialData[$platform] = "https://twitter.com/" . $username;
                            break;

                        case 'facebook':
                            $socialData[$platform] = "https://www.facebook.com/" . $inputValue;
                            break;

                        case 'whatsapp':
                            $phone = preg_replace('/[^0-9+]/', '', $inputValue);
                            if (strpos($phone, '+') === 0) {
                                $phone = substr($phone, 1);
                            }
                            $socialData[$platform] = "https://wa.me/" . $phone;
                            break;

                        case 'email':
                            if (filter_var($inputValue, FILTER_VALIDATE_EMAIL)) {
                                $socialData[$platform] = "mailto:" . $inputValue;
                            } else {
                                $socialData[$platform] = '';
                            }
                            break;

                        case 'discord':
                            if (filter_var($inputValue, FILTER_VALIDATE_URL)) {
                                $socialData[$platform] = $inputValue;
                            } else {
                                $socialData[$platform] = '';
                            }
                            break;

                        default:
                            $socialData[$platform] = $inputValue;
                            break;
                    }
                } else {
                    $socialData[$platform] = '';
                }
            }

            $data['social_links'] = $socialData;

            if (saveData($data)) {
                echo json_encode(['success' => true, 'message' => 'Sosyal medya linkleri güncellendi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
            }
            break;

        case 'add_admin_user':
            session_start();
            if (!isset($_SESSION['admin_user']) || $_SESSION['admin_user']['role'] !== 'super_admin') {
                echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
                exit;
            }

            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $name = $_POST['name'] ?? '';
            $role = $_POST['role'] ?? 'admin';

            if (empty($username) || empty($password) || empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Tüm alanlar gereklidir.']);
                exit;
            }

            if (strlen($password) < 6) {
                echo json_encode(['success' => false, 'message' => 'Şifre en az 6 karakter olmalıdır.']);
                exit;
            }

            $result = saveAdminUser($username, $password, $name, $role);
            echo json_encode($result);
            break;

        case 'delete_admin_user':
            session_start();
            if (!isset($_SESSION['admin_user']) || $_SESSION['admin_user']['role'] !== 'super_admin') {
                echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
                exit;
            }

            $username = $input['username'] ?? '';
            $result = deleteAdminUser($username);
            echo json_encode($result);
            break;

        case 'toggle_banner':
            $id = intval($input['id'] ?? 0);

            $bannerIndex = -1;
            foreach ($data['banners'] as $index => $banner) {
                if ($banner['id'] == $id) {
                    $bannerIndex = $index;
                    break;
                }
            }

            if ($bannerIndex === -1) {
                echo json_encode(['success' => false, 'message' => 'Banner bulunamadı.']);
                exit;
            }

            $data['banners'][$bannerIndex]['active'] = !$data['banners'][$bannerIndex]['active'];

            if (saveData($data)) {
                $status = $data['banners'][$bannerIndex]['active'] ? 'aktif' : 'pasif';
                echo json_encode(['success' => true, 'message' => "Banner $status hale getirildi."]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
            }
            break;

        case 'add_slot':
            $title = $_POST['title'] ?? '';
            $link = $_POST['link'] ?? '';
            $text = $_POST['text'] ?? '';
            $type = $_POST['type'] ?? 'telegram';

            if (empty($title) || empty($link)) {
                echo json_encode(['success' => false, 'message' => 'Başlık ve link gereklidir.']);
                exit;
            }

            if (!isset($data['slots'])) {
                $data['slots'] = [];
            }

            $newSlot = [
                'id' => getNextId($data['slots']),
                'title' => $title,
                'link' => $link,
                'text' => $text,
                'type' => $type,
                'active' => true
            ];

            $data['slots'][] = $newSlot;

            if (saveData($data)) {
                echo json_encode(['success' => true, 'message' => 'Slot başarıyla eklendi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
            }
            break;

        case 'edit_slot':
            $id = intval($_POST['id'] ?? 0);
            $title = $_POST['title'] ?? '';
            $link = $_POST['link'] ?? '';
            $text = $_POST['text'] ?? '';
            $type = $_POST['type'] ?? 'telegram';

            if (empty($title) || empty($link)) {
                echo json_encode(['success' => false, 'message' => 'Başlık ve link gereklidir.']);
                exit;
            }

            $slotIndex = -1;
            if (isset($data['slots'])) {
                foreach ($data['slots'] as $index => $slot) {
                    if ($slot['id'] == $id) {
                        $slotIndex = $index;
                        break;
                    }
                }
            }

            if ($slotIndex === -1) {
                echo json_encode(['success' => false, 'message' => 'Slot bulunamadı.']);
                exit;
            }

            $data['slots'][$slotIndex] = [
                'id' => $id,
                'title' => $title,
                'link' => $link,
                'text' => $text,
                'type' => $type,
                'active' => $data['slots'][$slotIndex]['active']
            ];

            if (saveData($data)) {
                echo json_encode(['success' => true, 'message' => 'Slot başarıyla güncellendi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
            }
            break;

        case 'delete_slot':
            $id = intval($input['id'] ?? 0);

            $slotIndex = -1;
            if (isset($data['slots'])) {
                foreach ($data['slots'] as $index => $slot) {
                    if ($slot['id'] == $id) {
                        $slotIndex = $index;
                        break;
                    }
                }
            }

            if ($slotIndex === -1) {
                echo json_encode(['success' => false, 'message' => 'Slot bulunamadı.']);
                exit;
            }

            array_splice($data['slots'], $slotIndex, 1);

            if (saveData($data)) {
                echo json_encode(['success' => true, 'message' => 'Slot başarıyla silindi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
            }
            break;

        case 'toggle_slot':
            $id = intval($input['id'] ?? 0);

            $slotIndex = -1;
            if (isset($data['slots'])) {
                foreach ($data['slots'] as $index => $slot) {
                    if ($slot['id'] == $id) {
                        $slotIndex = $index;
                        break;
                    }
                }
            }

            if ($slotIndex === -1) {
                echo json_encode(['success' => false, 'message' => 'Slot bulunamadı.']);
                exit;
            }

            $data['slots'][$slotIndex]['active'] = !$data['slots'][$slotIndex]['active'];

            if (saveData($data)) {
                $status = $data['slots'][$slotIndex]['active'] ? 'aktif' : 'pasif';
                echo json_encode(['success' => true, 'message' => "Slot $status hale getirildi."]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
            }
            break;

        case 'add_banner':
            $siteId = intval($_POST['site_id'] ?? 0);
            $title = $_POST['title'] ?? '';
            $link = $_POST['link'] ?? '';
            $type = 'second'; // Sabit olarak second

            // Yeni banner'ı en sona eklemek için mevcut en yüksek order'ı bul
            $maxOrder = 0;
            if (isset($data['banners']) && !empty($data['banners'])) {
                foreach ($data['banners'] as $banner) {
                    if (isset($banner['order']) && $banner['order'] > $maxOrder) {
                        $maxOrder = $banner['order'];
                    }
                }
            }
            $order = $maxOrder + 1;

            if (empty($title) || empty($link) || $siteId == 0) {
                echo json_encode(['success' => false, 'message' => 'Site, başlık ve link gereklidir.']);
                exit;
            }

            if (!isset($data['banners'])) {
                $data['banners'] = [];
            }

            $newBanner = [
                'id' => getNextId($data['banners']),
                'site_id' => $siteId,
                'title' => $title,
                'link' => $link,
                'type' => $type,
                'order' => $order,
                'active' => true
            ];

            $data['banners'][] = $newBanner;

            if (saveData($data)) {
                echo json_encode(['success' => true, 'message' => 'Banner başarıyla eklendi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
            }
            break;

        case 'add_site_to_section':
            $siteId = intval($input['siteId'] ?? 0);
            $section = $input['section'] ?? '';

            if ($siteId == 0 || empty($section)) {
                echo json_encode(['success' => false, 'message' => 'Site ID ve section gerekli.']);
                exit;
            }

            // Site var mı kontrol et
            $siteExists = false;
            foreach ($data['sites'] as $site) {
                if ($site['id'] == $siteId) {
                    $siteExists = true;
                    break;
                }
            }

            if (!$siteExists) {
                echo json_encode(['success' => false, 'message' => 'Site bulunamadı.']);
                exit;
            }

            // Categories initialize et
            if (!isset($data['categories'])) {
                $data['categories'] = ['section1' => [], 'section2' => []];
            }

            if (!isset($data['categories'][$section])) {
                $data['categories'][$section] = [];
            }

            // Site zaten bu section'da mı kontrol et
            if (in_array($siteId, $data['categories'][$section])) {
                echo json_encode(['success' => false, 'message' => 'Site zaten bu section\'da mevcut.']);
                exit;
            }

            // Site'i section'a ekle
            $data['categories'][$section][] = $siteId;

            if (saveData($data)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Site başarıyla section\'a eklendi.',
                    'updated_categories' => $data['categories']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
            }
            break;

        case 'remove_site_from_section':
            $siteId = intval($input['siteId'] ?? 0);
            $section = $input['section'] ?? '';

            if ($siteId == 0 || empty($section)) {
                echo json_encode(['success' => false, 'message' => 'Site ID ve section gerekli.']);
                exit;
            }

            // Categories kontrol et
            if (!isset($data['categories']) || !isset($data['categories'][$section])) {
                echo json_encode(['success' => false, 'message' => 'Section bulunamadı.']);
                exit;
            }

            // Site'i section'dan çıkar
            $key = array_search($siteId, $data['categories'][$section]);
            if ($key !== false) {
                array_splice($data['categories'][$section], $key, 1);

                if (saveData($data)) {
                    echo json_encode(['success' => true, 'message' => 'Site başarıyla section\'dan çıkarıldı.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Site bu section\'da bulunamadı.']);
            }
            break;

        case 'edit_banner':
            $id = intval($_POST['id'] ?? 0);
            $siteId = intval($_POST['site_id'] ?? 0);
            $title = $_POST['title'] ?? '';
            $link = $_POST['link'] ?? '';
            $type = 'second'; // Sabit olarak second

            if (empty($title) || empty($link) || $siteId == 0) {
                echo json_encode(['success' => false, 'message' => 'Site, başlık ve link gereklidir.']);
                exit;
            }

            $bannerIndex = -1;
            if (isset($data['banners'])) {
                foreach ($data['banners'] as $index => $banner) {
                    if ($banner['id'] == $id) {
                        $bannerIndex = $index;
                        break;
                    }
                }
            }

            if ($bannerIndex === -1) {
                echo json_encode(['success' => false, 'message' => 'Banner bulunamadı.']);
                exit;
            }

            $data['banners'][$bannerIndex] = [
                'id' => $id,
                'site_id' => $siteId,
                'title' => $title,
                'link' => $link,
                'type' => $type,
                'active' => $data['banners'][$bannerIndex]['active'],
                'order' => $data['banners'][$bannerIndex]['order'] ?? 999
            ];

            if (saveData($data)) {
                echo json_encode(['success' => true, 'message' => 'Banner başarıyla güncellendi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
            }
            break;

        case 'delete_banner':
            $id = intval($input['id'] ?? 0);

            $bannerIndex = -1;
            if (isset($data['banners'])) {
                foreach ($data['banners'] as $index => $banner) {
                    if ($banner['id'] == $id) {
                        $bannerIndex = $index;
                        break;
                    }
                }
            }

            if ($bannerIndex === -1) {
                echo json_encode(['success' => false, 'message' => 'Banner bulunamadı.']);
                exit;
            }

            array_splice($data['banners'], $bannerIndex, 1);

            if (saveData($data)) {
                echo json_encode(['success' => true, 'message' => 'Banner başarıyla silindi.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Veri kaydedilirken hata oluştu.']);
            }
            break;

        case 'change_password':
            session_start();
            if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
                echo json_encode(['success' => false, 'message' => 'Yetki gerekli']);
                break;
            }

            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $username = $_SESSION['admin_user']['username'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                echo json_encode(['success' => false, 'message' => 'Tüm alanları doldurun']);
                break;
            }

            if ($new_password !== $confirm_password) {
                echo json_encode(['success' => false, 'message' => 'Yeni şifreler eşleşmiyor']);
                break;
            }

            if (strlen($new_password) < 6) {
                echo json_encode(['success' => false, 'message' => 'Şifre en az 6 karakter olmalıdır']);
                break;
            }

            // Mevcut şifreyi doğrula
            $auth_result = authenticateUser($username, $current_password);
            if (!$auth_result['success']) {
                echo json_encode(['success' => false, 'message' => 'Mevcut şifre hatalı']);
                break;
            }

            // Şifreyi değiştir
            $result = saveAdminUser($username, $new_password, $_SESSION['admin_user']['name'], $_SESSION['admin_user']['role']);

            if ($result['success']) {
                echo json_encode(['success' => true, 'message' => 'Şifre başarıyla değiştirildi']);
            } else {
                echo json_encode(['success' => false, 'message' => $result['message']]);
            }
            break;

        case 'reorder_banners':
            $input = json_decode(file_get_contents('php://input'), true);
            $bannerIds = $input['bannerIds'] ?? [];

            $data = getData();
            if ($data && isset($data['banners'])) {
                foreach ($data['banners'] as &$banner) {
                    $order = array_search($banner['id'], $bannerIds);
                    if ($order !== false) {
                        $banner['order'] = $order + 1;
                    }
                }

                if (saveData($data)) {
                    echo json_encode(['success' => true, 'message' => 'Banner sıralaması güncellendi']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Banner sıralaması güncellenemedi']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Banner verisi bulunamadı']);
            }
            break;

        case 'update_profile':
            session_start();
            $newUsername = $_POST['username'] ?? '';
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($newUsername) || empty($currentPassword)) {
                echo json_encode(['success' => false, 'message' => 'Kullanıcı adı ve mevcut şifre gerekli']);
                break;
            }

            $currentUser = $_SESSION['admin_user'] ?? null;
            if (!$currentUser) {
                echo json_encode(['success' => false, 'message' => 'Oturum bulunamadı']);
                break;
            }

            $oldUsername = $currentUser['username'];

            // Verify current password
            $auth_result = authenticateUser($oldUsername, $currentPassword);
            if (!$auth_result['success']) {
                echo json_encode(['success' => false, 'message' => 'Mevcut şifre yanlış']);
                break;
            }

            // Şifre değiştirme işlemi
            $passwordToUse = !empty($newPassword) ? $newPassword : $currentPassword;

            if (!empty($newPassword)) {
                if ($newPassword !== $confirmPassword) {
                    echo json_encode(['success' => false, 'message' => 'Yeni şifreler eşleşmiyor']);
                    break;
                }
                if (strlen($newPassword) < 6) {
                    echo json_encode(['success' => false, 'message' => 'Şifre en az 6 karakter olmalıdır']);
                    break;
                }
            }

            // Kullanıcı adı değişiyorsa, eski kaydı sil
            if ($oldUsername !== $newUsername) {
                $settings = loadAdminSettings();
                if (isset($settings['admin_users'][$oldUsername])) {
                    unset($settings['admin_users'][$oldUsername]);
                    saveAdminSettings($settings);
                }
            }

            // Yeni kullanıcı bilgilerini kaydet
            $result = saveAdminUser($newUsername, $passwordToUse, $currentUser['name'], $currentUser['role']);

            if ($result['success']) {
                // Session'ı tamamen yenile - yeni kullanıcı bilgilerini al
                $new_auth_result = authenticateUser($newUsername, $passwordToUse);
                if ($new_auth_result['success']) {
                    $_SESSION['admin_user'] = $new_auth_result['user'];
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['last_activity'] = time();
                    echo json_encode(['success' => true, 'message' => 'Profil bilgileri başarıyla güncellendi']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Güncelleme sonrası doğrulama hatası']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => $result['message']]);
            }
            break;

        case 'update_popup_settings':
            $popupNumber = $_POST['popup_number'] ?? '';
            $popupTitle = $_POST['popup_title'] ?? '';
            $popupSubtitle = $_POST['popup_subtitle'] ?? '';
            $popupSiteId = intval($_POST['popup_site_id'] ?? 0);
            $popupActive = isset($_POST['popup_active']);

            $data = getData();
            if (!$data) {
                echo json_encode(['success' => false, 'message' => 'Veri dosyası okunamadı']);
                break;
            }

            $data['popup'] = [
                'number' => $popupNumber,
                'title' => $popupTitle,
                'subtitle' => $popupSubtitle,
                'site_id' => $popupSiteId,
                'active' => $popupActive
            ];

            if (saveData($data)) {
                echo json_encode(['success' => true, 'message' => 'Popup ayarları güncellendi']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Popup ayarları güncellenemedi']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Geçersiz action']);
            
        case 'track_click':
                    $siteId = intval($input['site_id'] ?? 0);
                    $location = $input['location'] ?? ''; // banner, popup, section1, section2
                    $today = date('Y-m-d');
                    
                    $analytics_file = '../data/analytics.json';
                    $analytics = [];
                    
                    if (file_exists($analytics_file)) {
                        $analytics = json_decode(file_get_contents($analytics_file), true);
                    }
                    
                    // Initialize structure if not exists
                    if (!isset($analytics['site_clicks'])) {
                        $analytics['site_clicks'] = ['banner' => [], 'popup' => [], 'section1' => [], 'section2' => []];
                    }
                    if (!isset($analytics['daily_stats'])) {
                        $analytics['daily_stats'] = [];
                    }
                    if (!isset($analytics['total_stats'])) {
                        $analytics['total_stats'] = ['total_page_visits' => 0, 'total_site_clicks' => 0];
                    }
                    
                    // Track click by location and site
                    if (!isset($analytics['site_clicks'][$location])) {
                        $analytics['site_clicks'][$location] = [];
                    }
                    if (!isset($analytics['site_clicks'][$location][$siteId])) {
                        $analytics['site_clicks'][$location][$siteId] = [];
                    }
                    if (!isset($analytics['site_clicks'][$location][$siteId][$today])) {
                        $analytics['site_clicks'][$location][$siteId][$today] = 0;
                    }
                    
                    // Increment click count
                    $analytics['site_clicks'][$location][$siteId][$today]++;
                    
                    // Update daily stats
                    if (!isset($analytics['daily_stats'][$today])) {
                        $analytics['daily_stats'][$today] = ['clicks' => 0, 'visits' => 0];
                    }
                    $analytics['daily_stats'][$today]['clicks']++;
                    
                    // Update total stats
                    $analytics['total_stats']['total_site_clicks']++;
                    
                    // Save analytics data
                    if (file_put_contents($analytics_file, json_encode($analytics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                        echo json_encode(['success' => true]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Analytics kaydedilemedi']);
                    }
                    break;
        
                case 'track_visit':
                    $today = date('Y-m-d');
                    
                    $analytics_file = '../data/analytics.json';
                    $analytics = [];
                    
                    if (file_exists($analytics_file)) {
                        $analytics = json_decode(file_get_contents($analytics_file), true);
                    }
                    
                    // Initialize structure if not exists
                    if (!isset($analytics['page_visits'])) {
                        $analytics['page_visits'] = [];
                    }
                    if (!isset($analytics['daily_stats'])) {
                        $analytics['daily_stats'] = [];
                    }
                    if (!isset($analytics['total_stats'])) {
                        $analytics['total_stats'] = ['total_page_visits' => 0, 'total_site_clicks' => 0];
                    }
                    
                    // Track page visit
                    if (!isset($analytics['page_visits'][$today])) {
                        $analytics['page_visits'][$today] = 0;
                    }
                    $analytics['page_visits'][$today]++;
                    
                    // Update daily stats
                    if (!isset($analytics['daily_stats'][$today])) {
                        $analytics['daily_stats'][$today] = ['clicks' => 0, 'visits' => 0];
                    }
                    $analytics['daily_stats'][$today]['visits']++;
                    
                    // Update total stats
                    $analytics['total_stats']['total_page_visits']++;
                    
                    // Save analytics data
                    if (file_put_contents($analytics_file, json_encode($analytics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                        echo json_encode(['success' => true]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Analytics kaydedilemedi']);
                    }
                    break;
    }
    exit;
}

echo json_encode(['error' => 'Geçersiz method']);
?>
