<?php
/**
 * BOTMOTHER - To'liq Telegram Bot ichida ishlaydigan Bot Konstruktori + Statistika
 * - index.php (Asosiy Konstruktor Bot + Avto-papka yaratish + Webhook o'rnatish + Jonli Statistika)
 * - insta/index.php (Turbo Instagram yuklovchi bot)
 * - youtube/index.php (Turbo YouTube yuklovchi bot)
 * - kino/index.php (Admin panelli Kino bot)
 * - users/{user_id}/ (Foydalanuvchilar ID, kirish-chiqishlar soni va botlari bazasi)
 */

date_default_timezone_set('Asia/Tashkent');

// 1. KERAKLI PAPKALAR VA SHABLONLARNI AVTOMATIK YARATISH
$baseDir = __DIR__;
$folders = ['insta', 'youtube', 'kino', 'users'];

foreach ($folders as $folder) {
    if (!is_dir("$baseDir/$folder")) {
        mkdir("$baseDir/$folder", 0777, true);
    }
}

// =========================================================================
// SHABLON 1: INSTAGRAM BOT (insta/index.php) - MAKSIMAL TEZLIK
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

if ($text === '/start') {
    sendTg($token, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => "⚡ <b>Turbo Instagram Bot!</b>\n\n📥 Reels yoki Video havolasini yuboring — 3 soniyaga bormay yuklab beraman:",
        'parse_mode' => 'HTML'
    ]);
} elseif (filter_var($text, FILTER_VALIDATE_URL) && strpos($text, 'instagram.com') !== false) {
    $startTime = microtime(true);

    sendTg($token, 'sendChatAction', [
        'chat_id' => $chatId,
        'action' => 'upload_video'
    ]);

    $safeUrl = escapeshellarg($text);
    $cmd = "yt-dlp --no-warnings --no-playlist -f 'best[ext=mp4]/best' -g {$safeUrl} 2>/dev/null";
    $directUrl = trim(shell_exec($cmd));
    $lines = explode("\n", $directUrl);
    $videoUrl = trim($lines[0] ?? '');

    $sent = false;
    if (!empty($videoUrl) && filter_var($videoUrl, FILTER_VALIDATE_URL)) {
        $elapsed = round(microtime(true) - $startTime, 2);
        $res = sendTg($token, 'sendVideo', [
            'chat_id' => $chatId,
            'video' => $videoUrl,
            'caption' => "⚡ <b>{$elapsed} soniyada</b> yuklandi!",
            'parse_mode' => 'HTML'
        ]);
        if ($res['ok'] ?? false) {
            $sent = true;
        }
    }

    if (!$sent) {
        $tmpFile = sys_get_temp_dir() . '/ig_' . uniqid() . '.mp4';
        shell_exec("yt-dlp --no-warnings -f 'best[ext=mp4]/best' -o " . escapeshellarg($tmpFile) . " {$safeUrl} 2>/dev/null");
        if (file_exists($tmpFile) && filesize($tmpFile) > 0) {
            $elapsed = round(microtime(true) - $startTime, 2);
            sendTg($token, 'sendVideo', [
                'chat_id' => $chatId,
                'video' => new CURLFile($tmpFile),
                'caption' => "⚡ <b>{$elapsed} soniyada</b> yuklandi!",
                'parse_mode' => 'HTML'
            ]);
            @unlink($tmpFile);
            $sent = true;
        }
    }

    if (!$sent) {
        sendTg($token, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => "❌ Videoni yuklab bo'lmadi. Profil yopiq bo'lishi yoki havola noto'g'ri bo'lishi mumkin."
        ]);
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
// SHABLON 2: YOUTUBE BOT (youtube/index.php) - MAKSIMAL TEZLIK
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

if ($text === '/start') {
    sendTg($token, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => "⚡ <b>Turbo YouTube Bot!</b>\n\n🎬 YouTube Video yoki Shorts havolasini yuboring — chaqmoq tezligida yuklab beraman:",
        'parse_mode' => 'HTML'
    ]);
} elseif (filter_var($text, FILTER_VALIDATE_URL) && (strpos($text, 'youtube.com') !== false || strpos($text, 'youtu.be') !== false)) {
    $startTime = microtime(true);

    sendTg($token, 'sendChatAction', [
        'chat_id' => $chatId,
        'action' => 'upload_video'
    ]);

    $safeUrl = escapeshellarg($text);
    $cmd = "yt-dlp --no-warnings --no-playlist -f 'best[ext=mp4][acodec!=none][vcodec!=none]/18/best' -g {$safeUrl} 2>/dev/null";
    $directUrl = trim(shell_exec($cmd));
    $lines = explode("\n", $directUrl);
    $videoUrl = trim($lines[0] ?? '');

    $sent = false;
    if (!empty($videoUrl) && filter_var($videoUrl, FILTER_VALIDATE_URL)) {
        $elapsed = round(microtime(true) - $startTime, 2);
        $res = sendTg($token, 'sendVideo', [
            'chat_id' => $chatId,
            'video' => $videoUrl,
            'caption' => "⚡ <b>{$elapsed} soniyada</b> yuklandi!",
            'parse_mode' => 'HTML'
        ]);
        if ($res['ok'] ?? false) {
            $sent = true;
        }
    }

    if (!$sent) {
        $tmpFile = sys_get_temp_dir() . '/yt_' . uniqid() . '.mp4';
        shell_exec("yt-dlp --no-warnings --no-playlist -f 'best[ext=mp4][filesize<48M]/18' -o " . escapeshellarg($tmpFile) . " {$safeUrl} 2>/dev/null");
        if (file_exists($tmpFile) && filesize($tmpFile) > 0) {
            $elapsed = round(microtime(true) - $startTime, 2);
            sendTg($token, 'sendVideo', [
                'chat_id' => $chatId,
                'video' => new CURLFile($tmpFile),
                'caption' => "⚡ <b>{$elapsed} soniyada</b> yuklandi!",
                'parse_mode' => 'HTML'
            ]);
            @unlink($tmpFile);
            $sent = true;
        }
    }

    if (!$sent) {
        sendTg($token, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => "❌ Videoni yuklab bo'lmadi (video juda katta yoki yopiq bo'lishi mumkin)."
        ]);
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
// YORDAMCHI FUNKSIYA: BARCHA FOYDALANUVCHILAR STATISTIKASINI YIG'ISH
// =========================================================================
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

    // Oxirgi faol foydalanuvchilarni tepaga chiqarish
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

// =========================================================================
// 2. ASOSIY KONSTRUKTOR BOT LOGIKASI (index.php)
// =========================================================================
$masterToken = $_GET['token'] ?? getenv('BOT_TOKEN') ?: '8955043733:AAEc2nNY7wjNvjb-YKzZ5n602P8ZLADzq_4';

$rawInput = file_get_contents('php://input');

// BRAUZER YOKI UPTIMEROBOT KIRGANDA JONLI STATISTIKA PANELINI KO'RSATISH
if (empty($rawInput)) {
    $stats = getAllStats($baseDir);
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html lang='uz'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Botmother Statistika</title>
    <style>
        body { font-family: sans-serif; background: #0f172a; color: #f8fafc; padding: 20px; }
        .cards { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 25px; }
        .card { background: #1e293b; padding: 15px 20px; border-radius: 10px; border: 1px solid #334155; min-width: 150px; }
        .card h3 { margin: 0; font-size: 13px; color: #94a3b8; }
        .card p { margin: 8px 0 0; font-size: 24px; font-weight: bold; color: #38bdf8; }
        table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 10px; overflow: hidden; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #334155; font-size: 14px; }
        th { background: #334155; color: #e2e8f0; }
        .badge-active { background: #166534; color: #bbf7d0; padding: 3px 8px; border-radius: 6px; font-size: 12px; }
        .badge-left { background: #991b1b; color: #fecaca; padding: 3px 8px; border-radius: 6px; font-size: 12px; }
    </style></head><body>
    <h2>📊 Botmother — Jonli Monitoring Paneli</h2>
    <div class='cards'>
        <div class='card'><h3>👥 Jami foydalanuvchilar</h3><p>{$stats['total_users']} ta</p></div>
        <div class='card'><h3>🟢 Faol (ichkarida)</h3><p>{$stats['active_users']} ta</p></div>
        <div class='card'><h3>🔴 Chiqib ketgan (Block)</h3><p>{$stats['left_users']} ta</p></div>
        <div class='card'><h3>🔄 Jami kirishlar</h3><p>{$stats['total_visits']} marta</p></div>
        <div class='card'><h3>🤖 Yaratilgan botlar</h3><p>{$stats['total_bots']} ta</p></div>
    </div>
    <table>
        <tr><th>#</th><th>Telegram ID</th><th>Ism va Username</th><th>Holati</th><th>Kirishlar</th><th>Botlari</th><th>Oxirgi faollik</th></tr>";
    foreach ($stats['users'] as $idx => $u) {
        $n = $idx + 1;
        $uid = htmlspecialchars($u['id']);
        $fname = htmlspecialchars($u['first_name'] ?? '');
        $uname = !empty($u['username']) ? '@' . htmlspecialchars($u['username']) : '—';
        $visits = $u['visits_count'] ?? 1;
        $botsCount = count($u['bots'] ?? []);
        $last = htmlspecialchars($u['last_active'] ?? '');
        $statusHtml = (($u['status'] ?? 'active') === 'left')
            ? "<span class='badge-left'>Chiqib ketgan</span>"
            : "<span class='badge-active'>Faol</span>";
        echo "<tr><td>{$n}</td><td><b>{$uid}</b></td><td>{$fname} ({$uname})</td><td>{$statusHtml}</td><td>{$visits} marta</td><td>{$botsCount} ta</td><td>{$last}</td></tr>";
    }
    echo "</table></body></html>";
    exit;
}

$update = json_decode($rawInput, true);
if (!$update) exit;

// FOYDALANUVCHI BOTDAN CHIQIB KETGANINI (BLOCK) YOKI QAYTA KIRGANINI QAYD QILISH
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

function bot($token, $method, $data = []) {
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// Foydalanuvchini users/{user_id}/ papkasiga saqlash va kirish-chiqishlarini hisoblash
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

// Har safar /start bosganda yoki yangi kirganda kirishlar sonini oshirish
if ($text === '/start' || $isNewUser) {
    $userData['visits_count'] = ($userData['visits_count'] ?? 0) + 1;
}
file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Asosiy menyu tugmalari (Statistika tugmasi bilan)
$mainMenu = json_encode([
    'keyboard' => [
        [['text' => '📸 Instagram Bot'], ['text' => '🎬 YouTube Bot']],
        [['text' => '🎥 Kino Bot (+Admin)'], ['text' => '🤖 Mening botlarim']],
        [['text' => '📊 Statistika']]
    ],
    'resize_keyboard' => true
]);

$cancelMenu = json_encode([
    'keyboard' => [
        [['text' => '🔙 Bekor qilish']]
    ],
    'resize_keyboard' => true
]);

// /start yoki Bekor qilish
if ($text === '/start' || $text === '🔙 Bekor qilish') {
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

// STATISTIKA BO'LIMI (Kim foydalangani, IDlari, kirib-chiqqanlar soni)
if ($text === '📊 Statistika' || $text === '/stats') {
    $stats = getAllStats($baseDir);
    $report = "📊 <b>BOTMOTHER UMUMIY STATISTIKASI</b>\n\n";
    $report .= "👥 Jami foydalanuvchilar: <b>{$stats['total_users']} ta</b>\n";
    $report .= "🟢 Hozir faol (ichkarida): <b>{$stats['active_users']} ta</b>\n";
    $report .= "🔴 Chiqib ketgan (Block): <b>{$stats['left_users']} ta</b>\n";
    $report .= "🔄 Jami kirish-chiqishlar: <b>{$stats['total_visits']} marta</b>\n";
    $report .= "🤖 Yaratilgan botlar: <b>{$stats['total_bots']} ta</b>\n\n";
    $report .= "📋 <b>Foydalanuvchilar ro'yxati (ID va faollik):</b>\n";

    $limit = 0;
    foreach ($stats['users'] as $idx => $u) {
        if ($limit >= 30) {
            $report .= "\n<i>...va yana " . ($stats['total_users'] - 30) . " ta foydalanuvchi (to'liq ro'yxat sayt havolasida).</i>";
            break;
        }
        $n = $idx + 1;
        $statusIcon = (($u['status'] ?? 'active') === 'left') ? '🔴' : '🟢';
        $uNameStr = !empty($u['username']) ? " (@{$u['username']})" : "";
        $vCount = $u['visits_count'] ?? 1;
        $bCount = count($u['bots'] ?? []);
        $report .= "{$n}. {$statusIcon} <b>{$u['first_name']}</b>{$uNameStr}\n   🆔 ID: <code>{$u['id']}</code> | 🔄 {$vCount} marta | 🤖 {$bCount} bot\n";
        $limit++;
    }

    bot($masterToken, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => $report,
        'parse_mode' => 'HTML',
        'reply_markup' => $mainMenu
    ]);
    exit;
}

// Mening botlarim bo'limi
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

// Bo'lim tanlash
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
        'text' => "✅ <b>{$botTypes[$text]['name']}</b> tanlandi!\n\nEndi @BotFather dan olgan <b>Bot Tokenni</b> shu yerga yuboring:\n<i>(Masalan: 123456789:AAHdqTcvCH1vGWJxfSeof...)</i>",
        'parse_mode' => 'HTML',
        'reply_markup' => $cancelMenu
    ]);
    exit;
}

// Token qabul qilish va avtomatik Webhook o'rnatish
if ($userData['step'] === 'wait_token') {
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
