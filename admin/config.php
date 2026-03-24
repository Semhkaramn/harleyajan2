<?php
// Admin Panel Configuration
// Güvenlik ve kullanıcı yönetimi ayarları

// Admin kullanıcıları - artık JSON dosyasından okunuyor
$admin_users = [
    // Tüm kullanıcılar admin_settings.json dosyasında saklanıyor
];

// Ayarlar dosyası
$settings_file = '../data/admin_settings.json';

// Admin ayarlarını yükle
function loadAdminSettings() {
    global $settings_file;
    if (file_exists($settings_file)) {
        return json_decode(file_get_contents($settings_file), true);
    }
    return [
        'site_name' => 'Admin Panel',
        'admin_users' => [],
        'security' => [
            'session_timeout' => 3600 // 1 saat
        ]
    ];
}

// Admin ayarlarını kaydet
function saveAdminSettings($settings) {
    global $settings_file;

    // Dizini oluştur
    $dir = dirname($settings_file);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }

    return file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Kullanıcı doğrulama
function authenticateUser($username, $password) {
    global $admin_users;

    // Sadece JSON dosyasındaki kullanıcıları kontrol et (düz metin şifre)
    $settings = loadAdminSettings();
    if (isset($settings['admin_users'][$username])) {
        $user = $settings['admin_users'][$username];
        // Düz metin şifre karşılaştırması
        if ($user['password'] === $password) {
            return [
                'success' => true,
                'user' => [
                    'username' => $username,
                    'role' => $user['role'] ?? 'admin',
                    'name' => $user['name'] ?? 'Yönetici'
                ]
            ];
        }
    }

    return ['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı'];
}

// Kullanıcı listesini al
function getAdminUsers() {
    global $admin_users;
    $settings = loadAdminSettings();

    $users = [];

    // Varsayılan kullanıcıları ekle (görünür olanları)
    foreach ($admin_users as $username => $user) {
        if ($user['visible']) {
            $users[$username] = [
                'name' => $user['name'],
                'role' => $user['role'],
                'editable' => false
            ];
        }
    }

    // Özel kullanıcıları ekle
    if (isset($settings['admin_users'])) {
        foreach ($settings['admin_users'] as $username => $user) {
            $users[$username] = [
                'name' => $user['name'] ?? 'Yönetici',
                'role' => $user['role'] ?? 'admin',
                'editable' => true
            ];
        }
    }

    return $users;
}

// Kullanıcı ekle/güncelle
function saveAdminUser($username, $password, $name, $role = 'admin') {
    global $admin_users;

    // Süper admin değiştirilemez
    if ($username === 'semhkaramn') {
        return ['success' => false, 'message' => 'Süper admin kullanıcısı değiştirilemez'];
    }

    $settings = loadAdminSettings();

    if (!isset($settings['admin_users'])) {
        $settings['admin_users'] = [];
    }

    $settings['admin_users'][$username] = [
        'password' => $password, // Düz metin şifre kaydet
        'name' => $name,
        'role' => $role,
        'created_at' => date('Y-m-d H:i:s')
    ];

    if (saveAdminSettings($settings)) {
        return ['success' => true, 'message' => 'Kullanıcı başarıyla kaydedildi'];
    } else {
        return ['success' => false, 'message' => 'Kullanıcı kaydedilirken hata oluştu'];
    }
}

// Kullanıcı sil
function deleteAdminUser($username) {
    global $admin_users;

    // Süper admin ve varsayılan admin silinemez
    if ($username === 'semhkaramn' || $username === 'admin') {
        return ['success' => false, 'message' => 'Bu kullanıcı silinemez'];
    }

    $settings = loadAdminSettings();

    if (isset($settings['admin_users'][$username])) {
        unset($settings['admin_users'][$username]);

        if (saveAdminSettings($settings)) {
            return ['success' => true, 'message' => 'Kullanıcı başarıyla silindi'];
        } else {
            return ['success' => false, 'message' => 'Kullanıcı silinirken hata oluştu'];
        }
    }

    return ['success' => false, 'message' => 'Kullanıcı bulunamadı'];
}

// Session güvenliği
function isSessionValid() {
    if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        return false;
    }

    // Session timeout kontrolü
    if (isset($_SESSION['last_activity'])) {
        $settings = loadAdminSettings();
        $timeout = $settings['security']['session_timeout'] ?? 3600;

        if (time() - $_SESSION['last_activity'] > $timeout) {
            session_destroy();
            return false;
        }
    }

    $_SESSION['last_activity'] = time();
    return true;
}

// Güvenlik fonksiyonları - log sistemi kaldırıldı

// Lockout özelliği kaldırıldı - artık kullanılmıyor

// Logo upload için desteklenen formatlar
$allowed_logo_formats = [
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'webm', 'avif', 'bmp'
];

// MIME type kontrolü
$allowed_mime_types = [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/svg+xml',
    'video/webm',
    'image/avif',
    'image/bmp'
];

// Güvenli dosya upload
function uploadSecureLogo($file, $siteName) {
    global $allowed_logo_formats, $allowed_mime_types;

    $uploadDir = '../img/logo/';

    // Dizin yoksa oluştur
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Dosya bilgilerini al
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    $mimeType = mime_content_type($file['tmp_name']);

    // Format kontrolü
    if (!in_array($extension, $allowed_logo_formats)) {
        return [
            'success' => false,
            'message' => 'Desteklenmeyen dosya formatı. İzin verilen: ' . implode(', ', $allowed_logo_formats)
        ];
    }

    // MIME type kontrolü
    if (!in_array($mimeType, $allowed_mime_types)) {
        return [
            'success' => false,
            'message' => 'Geçersiz dosya türü tespit edildi.'
        ];
    }

    // Dosya boyutu kontrolü (15MB)
    if ($file['size'] > 15 * 1024 * 1024) {
        return [
            'success' => false,
            'message' => 'Dosya boyutu 15MB\'dan küçük olmalıdır.'
        ];
    }

    // Güvenli dosya adı oluştur
    $safeName = preg_replace('/[^a-zA-Z0-9]/', '', $siteName);
    $fileName = strtolower($safeName) . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $fileName;

    // Dosyayı yükle
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => true, 'filename' => $fileName];
    } else {
        return ['success' => false, 'message' => 'Dosya yüklenirken hata oluştu.'];
    }
}

?>
