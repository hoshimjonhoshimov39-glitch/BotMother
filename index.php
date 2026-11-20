<?php
/**
 * BOTMOTHER - Konstruktor Bot + Admin Panel + Faqat Admin Statistika + Tashqi API & Cookie
 */

date_default_timezone_set('Asia/Tashkent');

$baseDir = __DIR__;
$folders = ['insta', 'youtube', 'kino', 'users'];

foreach ($folders as $folder) {
    if (!is_dir("$baseDir/$folder")) {
        mkdir("$baseDir/$folder", 0777, true);
    }
}

$masterToken = $_GET['token'] ?? getenv('BOT_TOKEN') ?: 'BU_YERGA_ASOSIY_BOT_TOKENINI_YOZING';
$adminId = getenv('ADMIN_ID') ?: 'SIZNING_TELEGRAM_ID';

// =========================================================================
// SHABLON 1: INSTAGRAM BOT (insta/index.php)
// =========================================================================
$instaTemplate = <<<'PHP'
<?php
$token = $_GET['token'] ?? '';
if (!$token) exit('Token missing');

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

$chatId = $update['message']['chat']['id'] ?? null;
$text = trim($update['message']['text'] ?? '');

function sendTg($token, $method, $data) {
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function extractLastError($raw) {
    $lines = preg_split('/\r\n|\r|\n/', trim((string)$raw));
    foreach (array_reverse($lines) as $line) {
        $line = trim(strip_tags($line));
        if ($line !== '') {
            return mb_substr($line, 0, 220);
        }
    }
    return "Instagram yuklashda xatolik";
}

function extractUrlFromMixedText($raw) {
    foreach (preg_split('/\r\n|\r|\n/', trim((string)$raw)) as $line) {
        $line = trim($line);
        if (filter_var($line, FILTER_VALIDATE_URL)) {
            return $line;
        }
    }
    return '';
}

function fetchFromExternalApi($apiUrl, $mediaUrl) {
    if (empty($apiUrl)) return '';
    $apiKey = getenv('MEDIA_API_KEY') ?: '';
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: BotmotherClient/1.0'
    ];
    if ($apiKey !== '') {
        $headers[] = 'Authorization: Bearer ' . $apiKey;
        $headers[] = 'X-API-Key: ' . $apiKey;
    }

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 35);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['url' => $mediaUrl]));
    $res = curl_exec($ch);
    curl_close($ch);

    if (!$res) return '';
    $json = json_decode($res, true);
    if (!is_array($json)) return '';

    foreach (['video_url', 'url', 'download_url'] as $k) {
        if (!empty($json[$k]) && filter_var($json[$k], FILTER_VALIDATE_URL)) {
            return $json[$k];
        }
    }
    if (!empty($json['data']['url']) && filter_var($json['data']['url'], FILTER_VALIDATE_URL)) {
        return $json['data']['url'];
    }
    if (!empty($json['data']['video_url']) && filter_var($json['data']['video_url'], FILTER_VALIDATE_URL)) {
        return $json['data']['video_url'];
    }
    return '';
}

function runYtDlpCommand($baseCmd, $cookieOpt, $safeUrl) {
    $cmds = [];
    if ($cookieOpt !== '') {
        $cmds[] = str_replace('{COOKIE}', $cookieOpt, $baseCmd) . " {$safeUrl} 2>&1";
    }
    $cmds[] = str_replace('{COOKIE}', '', $baseCmd) . " {$safeUrl} 2>&1";

    $lastOut = '';
    foreach ($cmds as $cmd) {
        $out = trim((string)shell_exec($cmd));
        $lastOut = $out;
        $foundUrl = extractUrlFromMixedText($out);
        if ($foundUrl !== '') {
            return ['url' => $foundUrl, 'raw' => $out];
        }
    }
    return ['url' => '', 'raw' => $lastOut];
}

if ($text === '/start') {
    sendTg($token, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => "⚡ <b>Turbo Instagram Bot!</b>\n\n📥 Reels yoki Video havolasini yuboring:",
        'parse_mode' => 'HTML'
    ]);
} elseif (filter_var($text, FILTER_VALIDATE_URL) && strpos($text, 'instagram.com') !== false) {
    $startTime = microtime(true);
    sendTg($token, 'sendChatAction', ['chat_id' => $chatId, 'action' => 'upload_video']);

    $apiUrl = getenv('IG_API_URL') ?: '';
    $videoUrl = fetchFromExternalApi($apiUrl, $text);
    $rawOut = '';

    $cookieFile = '/etc/secrets/ig_cookies.txt';
    $cookieOpt = file_exists($cookieFile) ? " --cookies " . escapeshellarg($cookieFile) : "";
    $safeUrl = escapeshellarg($text);

    if (empty($videoUrl)) {
        $resGet = runYtDlpCommand(
            "yt-dlp --no-warnings --no-playlist{COOKIE} -f 'best[ext=mp4]/best' -g",
            $cookieOpt,
            $safeUrl
        );
        $videoUrl = $resGet['url'];
        $rawOut = $resGet['raw'];
    }

    $sent = false;
    if (!empty($videoUrl)) {
        $elapsed = round(microtime(true) - $startTime, 2);
        $res = sendTg($token, 'sendVideo', [
            'chat_id' => $chatId,
            'video' => $videoUrl,
            'caption' => "⚡ <b>{$elapsed} soniyada</b> yuklandi!",
            'parse_mode' => 'HTML'
        ]);
        if ($res['ok'] ?? false) $sent = true;
    }

    if (!$sent) {
        $tmpFile = sys_get_temp_dir() . '/ig_' . uniqid() . '.mp4';
        $dlOut = '';
        $tryCmds = [];
        if ($cookieOpt !== '') {
            $tryCmds[] = "yt-dlp --no-warnings --no-playlist{$cookieOpt} -f 'best[ext=mp4]/best' -o " . escapeshellarg($tmpFile) . " {$safeUrl} 2>&1";
        }
        $tryCmds[] = "yt-dlp --no-warnings --no-playlist -f 'best[ext=mp4]/best' -o " . escapeshellarg($tmpFile) . " {$safeUrl} 2>&1";

        foreach ($tryCmds as $cmd) {
            $dlOut = trim((string)shell_exec($cmd));
            if (file_exists($tmpFile) && filesize($tmpFile) > 0) {
                $elapsed = round(microtime(true) - $startTime, 2);
                $res = sendTg($token, 'sendVideo', [
                    'chat_id' => $chatId,
                    'video' => new CURLFile($tmpFile),
                    'caption' => "⚡ <b>{$elapsed} soniyada</b> yuklandi!",
                    'parse_mode' => 'HTML'
                ]);
                @unlink($tmpFile);
                if ($res['ok'] ?? false) {
                    $sent = true;
                    break;
                }
            }
        }
        @unlink($tmpFile);

        if (!$sent) {
            $errMsg = extractLastError($dlOut ?: $rawOut);
            sendTg($token, 'sendMessage', [
                'chat_id' => $chatId,
                'text' => "❌ Instagram videoni yuklab bo'lmadi.\nSabab: <code>" . htmlspecialchars($errMsg) . "</code>",
                'parse_mode' => 'HTML'
            ]);
        }
    }
} elseif ($chatId) {
    sendTg($token, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => "❌ Iltimos, to'g'ri Instagram havolasini yuboring!"
    ]);
}
PHP;
file_put_contents("$baseDir/insta/index.php", $instaTemplate);

// =========================================================================
// SHABLON 2: YOUTUBE BOT (youtube/index.php)
// =========================================================================
$youtubeTemplate = <<<'PHP'
<?php
$token = $_GET['token'] ?? '';
if (!$token) exit('Token missing');

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

$chatId = $update['message']['chat']['id'] ?? null;
$text = trim($update['message']['text'] ?? '');

function sendTg($token, $method, $data) {
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function extractLastError($raw) {
    $lines = preg_split('/\r\n|\r|\n/', trim((string)$raw));
    foreach (array_reverse($lines) as $line) {
        $line = trim(strip_tags($line));
        if ($line !== '') {
            return mb_substr($line, 0, 220);
        }
    }
    return "YouTube yuklashda xatolik";
}

function extractUrlFromMixedText($raw) {
    foreach (preg_split('/\r\n|\r|\n/', trim((string)$raw)) as $line) {
        $line = trim($line);
        if (filter_var($line, FILTER_VALIDATE_URL)) {
            return $line;
        }
    }
    return '';
}

function fetchFromExternalApi($apiUrl, $mediaUrl) {
    if (empty($apiUrl)) return '';
    $apiKey = getenv('MEDIA_API_KEY') ?: '';
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: BotmotherClient/1.0'
    ];
    if ($apiKey !== '') {
        $headers[] = 'Authorization: Bearer ' . $apiKey;
        $headers[] = 'X-API-Key: ' . $apiKey;
    }

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 35);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['url' => $mediaUrl]));
    $res = curl_exec($ch);
    curl_close($ch);

    if (!$res) return '';
    $json = json_decode($res, true);
    if (!is_array($json)) return '';

    foreach (['video_url', 'url', 'download_url'] as $k) {
        if (!empty($json[$k]) && filter_var($json[$k], FILTER_VALIDATE_URL)) {
            return $json[$k];
        }
    }
    if (!empty($json['data']['url']) && filter_var($json['data']['url'], FILTER_VALIDATE_URL)) {
        return $json['data']['url'];
    }
    if (!empty($json['data']['video_url']) && filter_var($json['data']['video_url'], FILTER_VALIDATE_URL)) {
        return $json['data']['video_url'];
    }
    return '';
}

if ($text === '/start') {
    sendTg($token, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => "⚡ <b>Turbo YouTube Bot!</b>\n\n🎬 YouTube Video yoki Shorts havolasini yuboring:",
        'parse_mode' => 'HTML'
    ]);
} elseif (filter_var($text, FILTER_VALIDATE_URL) && (strpos($text, 'youtube.com') !== false || strpos($text, 'youtu.be') !== false)) {
    $startTime = microtime(true);
    sendTg($token, 'sendChatAction', ['chat_id' => $chatId, 'action' => 'upload_video']);

    $apiUrl = getenv('YT_API_URL') ?: '';
    $videoUrl = fetchFromExternalApi($apiUrl, $text);
    $rawOut = '';

    $cookieFile = '/etc/secrets/yt_cookies.txt';
    $cookieOpt = file_exists($cookieFile) ? " --cookies " . escapeshellarg($cookieFile) : "";
    $safeUrl = escapeshellarg($text);

    if (empty($videoUrl)) {
        $candidates = [];
        if ($cookieOpt !== '') {
            $candidates[] = "yt-dlp --no-warnings --no-playlist{$cookieOpt} -f 'best[ext=mp4][acodec!=none][vcodec!=none]/best[ext=mp4]/18/best' -g {$safeUrl} 2>&1";
            $candidates[] = "yt-dlp --no-warnings --no-playlist{$cookieOpt} --extractor-args 'youtube:player_client=android,web' -f 'best[ext=mp4]/18/best' -g {$safeUrl} 2>&1";
        }
        $candidates[] = "yt-dlp --no-warnings --no-playlist -f 'best[ext=mp4][acodec!=none][vcodec!=none]/best[ext=mp4]/18/best' -g {$safeUrl} 2>&1";

        foreach ($candidates as $cmd) {
            $rawOut = trim((string)shell_exec($cmd));
            $found = extractUrlFromMixedText($rawOut);
            if ($found !== '') {
                $videoUrl = $found;
                break;
            }
        }
    }

    $sent = false;
    if (!empty($videoUrl)) {
        $elapsed = round(microtime(true) - $startTime, 2);
        $res = sendTg($token, 'sendVideo', [
            'chat_id' => $chatId,
            'video' => $videoUrl,
            'caption' => "⚡ <b>{$elapsed} soniyada</b> yuklandi!",
            'parse_mode' => 'HTML'
        ]);
        if ($res['ok'] ?? false) $sent = true;
    }

    if (!$sent) {
        $tmpFile = sys_get_temp_dir() . '/yt_' . uniqid() . '.mp4';
        $dlOut = '';
        $dlCmds = [];
        if ($cookieOpt !== '') {
            $dlCmds[] = "yt-dlp --no-warnings --no-playlist{$cookieOpt} -f 'best[ext=mp4][filesize<48M]/18' -o " . escapeshellarg($tmpFile) . " {$safeUrl} 2>&1";
        }
        $dlCmds[] = "yt-dlp --no-warnings --no-playlist -f 'best[ext=mp4][filesize<48M]/18' -o " . escapeshellarg($tmpFile) . " {$safeUrl} 2>&1";

        foreach ($dlCmds as $cmd) {
            $dlOut = trim((string)shell_exec($cmd));
            if (file_exists($tmpFile) && filesize($tmpFile) > 0) {
                $elapsed = round(microtime(true) - $startTime, 2);
                $res = sendTg($token, 'sendVideo', [
                    'chat_id' => $chatId,
                    'video' => new CURLFile($tmpFile),
                    'caption' => "⚡ <b>{$elapsed} soniyada</b> yuklandi!",
                    'parse_mode' => 'HTML'
                ]);
                @unlink($tmpFile);
                if ($res['ok'] ?? false) {
                    $sent = true;
                    break;
                }
            }
        }
        @unlink($tmpFile);

        if (!$sent) {
            $errMsg = extractLastError($dlOut ?: $rawOut);
            sendTg($token, 'sendMessage', [
                'chat_id' => $chatId,
                'text' => "❌ YouTube videoni yuklab bo'lmadi.\nSabab: <code>" . htmlspecialchars($errMsg) . "</code>",
                'parse_mode' => 'HTML'
            ]);
        }
    }
} elseif ($chatId) {
    sendTg($token, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => "❌ Iltimos, to'g'ri YouTube havolasini yuboring!"
    ]);
}
PHP;
file_put_contents("$baseDir/youtube/index.php", $youtubeTemplate);

// =========================================================================
// SHABLON 3: KINO BOT + ADMIN PANEL (kino/index.php)
// =========================================================================
$kinoTemplate = <<<'PHP'
<?php
$token = $_GET['token'] ?? '';
$adminId = $_GET['admin'] ?? '';
if (!$token) exit('Token missing');

$botHash = md5($token);
$dbFile = __DIR__ . "/movies_{$botHash}.json";
$stateFile = __DIR__ . "/state_{$botHash}.json";

if (!file_exists($dbFile)) file_put_contents($dbFile, json_encode([]));
if (!file_exists($stateFile)) file_put_contents($stateFile, json_encode([]));

$movies = json_decode(file_get_contents($dbFile), true);
$states = json_decode(file_get_contents($stateFile), true);

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

$msg = $update['message'] ?? null;
$chatId = $msg['chat']['id'] ?? null;
$userId = $msg['from']['id'] ?? null;
$text = trim($msg['text'] ?? '');

function sendTg($token, $method, $data) {
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

$isAdmin = ($userId == $adminId);
$userState = $states[$userId] ?? null;

$adminKeyboard = json_encode([
    'keyboard' => [
        [['text' => '➕ Kino qo\'shish'], ['text' => '📊 Kinolar soni']],
        [['text' => '❌ Bekor qilish']]
    ],
    'resize_keyboard' => true
]);

if ($text === '/start' || $text === '❌ Bekor qilish') {
    unset($states[$userId]);
    file_put_contents($stateFile, json_encode($states));

    $welcome = "🎬 Xush kelibsiz! Kino kodini yuboring (masalan: 101):";
    $params = ['chat_id' => $chatId, 'text' => $welcome];
    if ($isAdmin) {
        $params['text'] .= "\n\n👑 Siz ushbu botning Adminisiz!";
        $params['reply_markup'] = $adminKeyboard;
    }
    sendTg($token, 'sendMessage', $params);
    exit;
}

if ($isAdmin) {
    if ($text === '➕ Kino qo\'shish') {
        $states[$userId] = ['step' => 'wait_video'];
        file_put_contents($stateFile, json_encode($states));
        sendTg($token, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => "🎥 Kinoni (video fayl sifatida) botga yuboring:"
        ]);
        exit;
    }

    if ($text === '📊 Kinolar soni') {
        $count = count($movies);
        sendTg($token, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => "📁 Bazadagi jami kinolar soni: {$count} ta"
        ]);
        exit;
    }

    if (isset($userState['step']) && $userState['step'] === 'wait_video') {
        if (isset($msg['video'])) {
            $states[$userId] = [
                'step' => 'wait_code',
                'file_id' => $msg['video']['file_id'],
                'caption' => $msg['caption'] ?? 'Ajoyib kino!'
            ];
            file_put_contents($stateFile, json_encode($states));
            sendTg($token, 'sendMessage', [
                'chat_id' => $chatId,
                'text' => "🔢 Endi ushbu kino uchun maxsus KOD kiriting (masalan: 777):"
            ]);
        } else {
            sendTg($token, 'sendMessage', [
                'chat_id' => $chatId,
                'text' => "⚠️ Iltimos, video yuboring!"
            ]);
        }
        exit;
    }

    if (isset($userState['step']) && $userState['step'] === 'wait_code' && $text !== '') {
        $code = $text;
        $movies[$code] = [
            'file_id' => $userState['file_id'],
            'caption' => $userState['caption']
        ];
        file_put_contents($dbFile, json_encode($movies, JSON_PRETTY_PRINT));
        unset($states[$userId]);
        file_put_contents($stateFile, json_encode($states));

        sendTg($token, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => "✅ Kino muvaffaqiyatli saqlandi!\n🔑 Kino kodi: <b>{$code}</b>",
            'parse_mode' => 'HTML',
            'reply_markup' => $adminKeyboard
        ]);
        exit;
    }
}

if (isset($movies[$text])) {
    sendTg($token, 'sendVideo', [
        'chat_id' => $chatId,
        'video' => $movies[$text]['file_id'],
        'caption' => "🎬 Kod: #{$text}\n" . $movies[$text]['caption']
    ]);
} else {
    sendTg($token, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => "😔 Bunday kodli kino topilmadi. Boshqa kod kiritib ko'ring."
    ]);
}
PHP;
file_put_contents("$baseDir/kino/index.php", $kinoTemplate);

// =========================================================================
// YORDAMCHI FUNKSIYALAR
// =========================================================================
function isSuperAdmin($userId, $adminId) {
    return !empty($adminId) && (string)$userId === (string)$adminId;
}

function getAllStats($baseDir) {
    $userDirs = glob("$baseDir/users/*", GLOB_ONLYDIR);
    $users = [];
    $totalVisits = 0;
    $totalBots = 0;
    $blockedCount = 0;

    foreach ($userDirs as $dir) {
        $pFile = "$dir/profile.json";
        if (file_exists($pFile)) {
            $u = json_decode(file_get_contents($pFile), true);
            if ($u) {
                $users[] = $u;
                $totalVisits += ($u['visits_count'] ?? 1);
                $totalBots += count($u['bots'] ?? []);
                if (($u['status'] ?? 'active') === 'left') {
                    $blockedCount++;
                }
            }
        }
    }

    usort($users, function($a, $b) {
        return strtotime($b['last_active'] ?? '2000-01-01') - strtotime($a['last_active'] ?? '2000-01-01');
    });

    return [
        'total_users' => count($users),
        'active_users' => count($users) - $blockedCount,
        'left_users' => $blockedCount,
        'total_visits' => $totalVisits,
        'total_bots' => $totalBots,
        'users' => $users
    ];
}

function bot($token, $method, $data = []) {
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function buildMainMenu($isAdmin) {
    $rows = [
        [['text' => '📸 Instagram Bot'], ['text' => '🎬 YouTube Bot']],
        [['text' => '🎥 Kino Bot (+Admin)'], ['text' => '🤖 Mening botlarim']]
    ];
    if ($isAdmin) {
        $rows[] = [['text' => '🛠 Admin Panel'], ['text' => '📊 Statistika']];
    }
    return json_encode([
        'keyboard' => $rows,
        'resize_keyboard' => true
    ]);
}

$adminPanelMenu = json_encode([
    'keyboard' => [
        [['text' => '📊 Statistika'], ['text' => '📢 Xabar yuborish']],
        [['text' => '🔍 User ID qidirish'], ['text' => '🔙 Asosiy menyu']]
    ],
    'resize_keyboard' => true
]);

$rawInput = file_get_contents('php://input');

if (empty($rawInput)) {
    $stats = getAllStats($baseDir);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Botmother OK | Active users: {$stats['active_users']} | Bots: {$stats['total_bots']}";
    exit;
}

$update = json_decode($rawInput, true);
if (!$update) exit;

if (isset($update['my_chat_member'])) {
    $member = $update['my_chat_member'];
    $uId = $member['from']['id'] ?? null;
    $newStatus = $member['new_chat_member']['status'] ?? '';
    if ($uId) {
        $uDir = "$baseDir/users/$uId";
        if (!is_dir($uDir)) mkdir($uDir, 0777, true);
        $uFile = "$uDir/profile.json";
        $uData = file_exists($uFile) ? json_decode(file_get_contents($uFile), true) : [
            'id' => $uId,
            'first_name' => $member['from']['first_name'] ?? 'Foydalanuvchi',
            'username' => $member['from']['username'] ?? '',
            'visits_count' => 1,
            'bots' => []
        ];
        if ($newStatus === 'kicked' || $newStatus === 'left') {
            $uData['status'] = 'left';
            $uData['left_at'] = date('Y-m-d H:i:s');
        } else {
            $uData['status'] = 'active';
            $uData['visits_count'] = ($uData['visits_count'] ?? 0) + 1;
        }
        $uData['last_active'] = date('Y-m-d H:i:s');
        file_put_contents($uFile, json_encode($uData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    exit;
}

if (!isset($update['message'])) exit;

$msg = $update['message'];
$chatId = $msg['chat']['id'];
$userId = $msg['from']['id'];
$firstName = $msg['from']['first_name'] ?? 'Foydalanuvchi';
$username = $msg['from']['username'] ?? '';
$text = trim($msg['text'] ?? '');
$isAdmin = isSuperAdmin($userId, $adminId);
$mainMenu = buildMainMenu($isAdmin);

$userDir = "$baseDir/users/$userId";
if (!is_dir($userDir)) {
    mkdir($userDir, 0777, true);
}
$userFile = "$userDir/profile.json";
$isNewUser = !file_exists($userFile);

$userData = !$isNewUser ? json_decode(file_get_contents($userFile), true) : [
    'id' => $userId,
    'first_name' => $firstName,
    'username' => $username,
    'joined_at' => date('Y-m-d H:i:s'),
    'visits_count' => 0,
    'status' => 'active',
    'step' => 'menu',
    'selected_type' => '',
    'bots' => []
];

$userData['first_name'] = $firstName;
$userData['username'] = $username;
$userData['status'] = 'active';
$userData['last_active'] = date('Y-m-d H:i:s');

if ($text === '/start' || $isNewUser) {
    $userData['visits_count'] = ($userData['visits_count'] ?? 0) + 1;
}
file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$cancelMenu = json_encode([
    'keyboard' => [
        [['text' => '🔙 Bekor qilish']]
    ],
    'resize_keyboard' => true
]);

if ($text === '/start' || $text === '🔙 Bekor qilish' || $text === '🔙 Asosiy menyu') {
    $userData['step'] = 'menu';
    $userData['selected_type'] = '';
    file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    bot($masterToken, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => "👋 Salom, <b>{$firstName}</b>! <b>Botmother</b> konstruktoriga xush kelibsiz.\n\nO'zingizga kerakli tayyor bot turini tanlang 👇",
        'parse_mode' => 'HTML',
        'reply_markup' => $mainMenu
    ]);
    exit;
}

if ($text === '📊 Statistika' || $text === '/stats') {
    if (!$isAdmin) {
        bot($masterToken, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => "⛔ Bu bo'lim faqat bot administratori uchun ochiq.",
            'reply_markup' => $mainMenu
        ]);
        exit;
    }

    $stats = getAllStats($baseDir);
    $report = "📊 <b>BOTMOTHER STATISTIKASI (ADMIN)</b>\n\n";
    $report .= "👥 Jami foydalanuvchilar: <b>{$stats['total_users']} ta</b>\n";
    $report .= "🟢 Faol: <b>{$stats['active_users']} ta</b>\n";
    $report .= "🔴 Chiqib ketgan: <b>{$stats['left_users']} ta</b>\n";
    $report .= "🔄 Jami kirishlar: <b>{$stats['total_visits']} marta</b>\n";
    $report .= "🤖 Yaratilgan botlar: <b>{$stats['total_bots']} ta</b>\n\n";

    $limit = 0;
    foreach ($stats['users'] as $idx => $u) {
        if ($limit >= 25) {
            $report .= "\n<i>...va yana " . ($stats['total_users'] - 25) . " ta foydalanuvchi.</i>";
            break;
        }
        $n = $idx + 1;
        $statusIcon = (($u['status'] ?? 'active') === 'left') ? '🔴' : '🟢';
        $uNameStr = !empty($u['username']) ? " (@{$u['username']})" : "";
        $vCount = $u['visits_count'] ?? 1;
        $bCount = count($u['bots'] ?? []);
        $report .= "{$n}. {$statusIcon} <b>{$u['first_name']}</b>{$uNameStr}\n   🆔 <code>{$u['id']}</code> | 🔄 {$vCount} | 🤖 {$bCount}\n";
        $limit++;
    }

    bot($masterToken, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => $report,
        'parse_mode' => 'HTML',
        'reply_markup' => $adminPanelMenu
    ]);
    exit;
}

if ($text === '🛠 Admin Panel' || $text === '/admin') {
    if (!$isAdmin) {
        bot($masterToken, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => "⛔ Sizda admin huquqi mavjud emas.",
            'reply_markup' => $mainMenu
        ]);
        exit;
    }

    $stats = getAllStats($baseDir);
    bot($masterToken, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => "🛠 <b>ADMIN PANEL</b>\n\n👤 Foydalanuvchilar: <b>{$stats['total_users']}</b>\n🤖 Botlar: <b>{$stats['total_bots']}</b>\n\nKerakli amalni tanlang:",
        'parse_mode' => 'HTML',
        'reply_markup' => $adminPanelMenu
    ]);
    exit;
}

if ($isAdmin && $text === '📢 Xabar yuborish') {
    $userData['step'] = 'admin_broadcast';
    file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    bot($masterToken, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => "✍️ Barcha faol foydalanuvchilarga yuboriladigan xabar matnini yozing:",
        'reply_markup' => $cancelMenu
    ]);
    exit;
}

if ($isAdmin && ($userData['step'] ?? '') === 'admin_broadcast' && $text !== '') {
    $stats = getAllStats($baseDir);
    $sentCount = 0;
    foreach ($stats['users'] as $u) {
        if (($u['status'] ?? 'active') !== 'left' && !empty($u['id'])) {
            $res = bot($masterToken, 'sendMessage', [
                'chat_id' => $u['id'],
                'text' => "📢 <b>Admin xabari:</b>\n\n{$text}",
                'parse_mode' => 'HTML'
            ]);
            if ($res['ok'] ?? false) $sentCount++;
        }
    }
    $userData['step'] = 'menu';
    file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    bot($masterToken, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => "✅ Xabar <b>{$sentCount} ta</b> foydalanuvchiga yuborildi!",
        'parse_mode' => 'HTML',
        'reply_markup' => $adminPanelMenu
    ]);
    exit;
}

if ($isAdmin && $text === '🔍 User ID qidirish') {
    $userData['step'] = 'admin_find_user';
    file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    bot($masterToken, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => "🆔 Tekshiriladigan foydalanuvchining Telegram ID'sini yuboring:",
        'reply_markup' => $cancelMenu
    ]);
    exit;
}

if ($isAdmin && ($userData['step'] ?? '') === 'admin_find_user' && $text !== '') {
    $targetId = preg_replace('/[^0-9]/', '', $text);
    $targetFile = "$baseDir/users/{$targetId}/profile.json";
    $userData['step'] = 'menu';
    file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if ($targetId && file_exists($targetFile)) {
        $tu = json_decode(file_get_contents($targetFile), true);
        $info = "👤 <b>Foydalanuvchi ma'lumoti:</b>\n\n";
        $info .= "🆔 ID: <code>{$tu['id']}</code>\n";
        $info .= "Ism: <b>" . htmlspecialchars($tu['first_name'] ?? '') . "</b>\n";
        $info .= "Username: " . (!empty($tu['username']) ? "@" . htmlspecialchars($tu['username']) : "yo'q") . "\n";
        $info .= "Holati: " . (($tu['status'] ?? 'active') === 'left' ? "🔴 Chiqib ketgan" : "🟢 Faol") . "\n";
        $info .= "Kirishlar: <b>" . ($tu['visits_count'] ?? 1) . "</b>\n";
        $info .= "Botlari soni: <b>" . count($tu['bots'] ?? []) . "</b>\n";
        $info .= "Oxirgi faollik: " . ($tu['last_active'] ?? '—');
        bot($masterToken, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $info,
            'parse_mode' => 'HTML',
            'reply_markup' => $adminPanelMenu
        ]);
    } else {
        bot($masterToken, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => "❌ Bunday ID bilan foydalanuvchi topilmadi.",
            'reply_markup' => $adminPanelMenu
        ]);
    }
    exit;
}

if ($text === '🤖 Mening botlarim') {
    if (empty($userData['bots'])) {
        bot($masterToken, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => "📭 Sizda hali yaratilgan botlar yo'q. Pastdagi tugmalardan birini tanlab bot yarating!",
            'reply_markup' => $mainMenu
        ]);
    } else {
        $listText = "🤖 <b>Siz yaratgan botlar ro'yxati:</b>\n\n";
        foreach ($userData['bots'] as $i => $b) {
            $num = $i + 1;
            $listText .= "{$num}. @{$b['bot_username']} — <i>" . strtoupper($b['type']) . "</i>\n";
        }
        bot($masterToken, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $listText,
            'parse_mode' => 'HTML',
            'reply_markup' => $mainMenu
        ]);
    }
    exit;
}

$botTypes = [
    '📸 Instagram Bot' => ['type' => 'insta', 'name' => 'Instagram Yuklovchi'],
    '🎬 YouTube Bot' => ['type' => 'youtube', 'name' => 'YouTube Yuklovchi'],
    '🎥 Kino Bot (+Admin)' => ['type' => 'kino', 'name' => 'Kino Bot (Admin panelli)']
];

if (isset($botTypes[$text])) {
    $userData['step'] = 'wait_token';
    $userData['selected_type'] = $botTypes[$text]['type'];
    file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    bot($masterToken, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => "✅ <b>{$botTypes[$text]['name']}</b> tanlandi!\n\nEndi @BotFather dan olgan <b>Bot Tokenni</b> shu yerga yuboring:",
        'parse_mode' => 'HTML',
        'reply_markup' => $cancelMenu
    ]);
    exit;
}

if (($userData['step'] ?? '') === 'wait_token') {
    $newToken = $text;

    if (!preg_match('/^[0-9]+:[a-zA-Z0-9_-]+$/', $newToken)) {
        bot($masterToken, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => "❌ Token formati noto'g'ri! Iltimos, @BotFather bergan haqiqiy tokenni yuboring yoki bekor qiling:"
        ]);
        exit;
    }

    bot($masterToken, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => "⏳ Token tekshirilmoqda va serverga ulanmoqda..."
    ]);

    $botInfo = json_decode(@file_get_contents("https://api.telegram.org/bot{$newToken}/getMe"), true);
    if (!$botInfo || !($botInfo['ok'] ?? false)) {
        bot($masterToken, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => "❌ Ushbu token ishlamayapti yoki bekor qilingan. Boshqa token yuboring:"
        ]);
        exit;
    }

    $newBotUsername = $botInfo['result']['username'];
    $type = $userData['selected_type'];

    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $baseUrl = "https://{$host}{$scriptDir}";

    $webhookUrl = "{$baseUrl}/{$type}/index.php?token=" . urlencode($newToken);
    if ($type === 'kino') {
        $webhookUrl .= "&admin=" . urlencode($userId);
    }

    $webhookRes = json_decode(@file_get_contents("https://api.telegram.org/bot{$newToken}/setWebhook?url=" . urlencode($webhookUrl)), true);

    if (!($webhookRes['ok'] ?? false)) {
        bot($masterToken, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => "⚠️ Webhook o'rnatishda xatolik yuz berdi: " . ($webhookRes['description'] ?? 'Noma\'lum xato')
        ]);
        exit;
    }

    $userData['bots'][] = [
        'type' => $type,
        'bot_username' => $newBotUsername,
        'token' => $newToken,
        'created_at' => date('Y-m-d H:i:s')
    ];
    $userData['step'] = 'menu';
    $userData['selected_type'] = '';
    file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $extraText = ($type === 'kino')
        ? "\n👑 <b>Admin panel ulandi!</b> Botingizga kirib /start bosing va <b>➕ Kino qo'shish</b> tugmasi orqali kino yuklang."
        : "\n📥 Botingizga kirib /start bosing va havola yuboring!";

    bot($masterToken, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => "🎉 <b>Tabriklaymiz! Botingiz ishga tushdi!</b>\n\n🤖 Bot manzili: @{$newBotUsername}{$extraText}",
        'parse_mode' => 'HTML',
        'reply_markup' => $mainMenu
    ]);
    exit;
}
