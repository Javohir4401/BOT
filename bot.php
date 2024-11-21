<?php
$botToken = "7678622082:AAF6l8QQ7-iEqq33OwCx52AB2YyxXQtmSjc";
$apiUrl = "https://api.telegram.org/bot$botToken/";

$weatherApiToken = "570d6bcba80a484fabbf080f31f3f185";
$aladhanApiUrl = "https://api.aladhan.com/v1/timingsByAddress";
$cbuCurrencyApiUrl = "https://cbu.uz/uz/arkhiv-kursov-valyut/json/";

$host = "localhost";
$db = "telegram_bot";
$user = "root";
$pass = "1112";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ma'lumotlar bazasiga ulanishda xato: " . $e->getMessage());
}

$update = json_decode(file_get_contents("php://input"), true);

if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $username = $update['message']['chat']['username'] ?? null;

    saveUser($chatId, $username);

    $messageText = strtolower(trim($update['message']['text']));

    if ($messageText == "/start") {
        $welcomeMessage = "Assalomu alaykum! 😊\nMen quyidagilarni bilishingizga yordam beraman:\n\n⛅ Ob-havo uchun 'Ob-havo' tugmasini bosing.\n🕌 Namoz vaqtlari uchun 'Namoz vaqtlari' tugmasini bosing.\n💵 Valyuta kurslari uchun 'Valyuta kurslari' tugmasini bosing.";
        sendMessageWithKeyboard($chatId, $welcomeMessage, [["Ob-havo"], ["Namoz vaqtlari"], ["Valyuta kurslari"]]);
    } elseif ($messageText == "valyuta kurslari") {
        $response = "💵 Valyutalar kurslarini hisoblash uchun miqdor va valyuta kodini kiriting.\n\nMisol: `100 USD`";
        sendMessage($chatId, $response, true);
    } elseif ($messageText == "ob-havo") {
        $response = "⛅ Ob-havo ma'lumotlarini olish uchun shahar nomini kiriting.\n\nMisol: `Toshkent`";
        sendMessage($chatId, $response, true);
    } elseif ($messageText == "namoz vaqtlari") {
        $response = "🕌 Namoz vaqtlari uchun hafta kunini kiriting.\n\nMisol: `Dushanba`";
        sendMessage($chatId, $response, true);
    } elseif (preg_match("/^([0-9]+)\s*([a-z]{3})$/i", $messageText, $matches)) {
        $amount = $matches[1];
        $currencyCode = strtoupper($matches[2]);
        $response = calculateCurrency($amount, $currencyCode);
        sendMessage($chatId, $response);
    } elseif (isDayOfWeek($messageText)) {
        $response = getPrayerTimes($messageText, "Toshkent");
        sendMessage($chatId, $response);
    } else {
        $response = getWeather($messageText);
        sendMessage($chatId, $response);
    }
}

function saveUser($chatId, $username) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO users (chat_id, username) VALUES (:chat_id, :username) ON DUPLICATE KEY UPDATE username = :username");
        $stmt->execute([
            ':chat_id' => $chatId,
            ':username' => $username
        ]);
    } catch (PDOException $e) {
        error_log("Foydalanuvchini saqlashda xato: " . $e->getMessage());
    }
}

function isDayOfWeek($day) {
    $weekDays = ['dushanba', 'seshanba', 'chorshanba', 'payshanba', 'juma', 'shanba', 'yakshanba'];
    return in_array($day, $weekDays);
}

function getWeather($city) {
    global $weatherApiToken;
    $weatherUrl = "https://api.openweathermap.org/data/2.5/weather?q=$city&appid=$weatherApiToken&units=metric";
    $weatherData = json_decode(file_get_contents($weatherUrl), true);

    if ($weatherData && isset($weatherData['main'])) {
        $temp = $weatherData['main']['temp'];
        $description = ucfirst($weatherData['weather'][0]['description']);
        $humidity = $weatherData['main']['humidity'];
        $windSpeed = $weatherData['wind']['speed'];

        $response = "🌆 Shahar: $city\n";
        $response .= "🌡 Harorat: $temp °C\n";
        $response .= "🌤 Ob-havo: $description\n";
        $response .= "💧 Namlik: $humidity%\n";
        $response .= "🌬 Shamol tezligi: $windSpeed m/s\n";
    } else {
        $response = "❌ Ob-havo ma'lumotlari topilmadi. Iltimos, boshqa shahar nomini kiriting.";
    }

    return $response;
}

function getPrayerTimes($day, $address) {
    global $aladhanApiUrl;

    $date = getNextDateForDay($day);
    $prayerUrl = "$aladhanApiUrl?date=$date&address=" . urlencode($address);
    $prayerData = json_decode(file_get_contents($prayerUrl), true);

    if ($prayerData && $prayerData['code'] === 200) {
        $prayerTimes = $prayerData['data']['timings'];
        $response = "🕌 Namoz vaqtlari ($day, $date):\n";
        foreach ($prayerTimes as $prayer => $time) {
            $response .= "$prayer: $time\n";
        }
    } else {
        $response = "❌ Namoz vaqtlarini olishda xato yuz berdi.";
    }

    return $response;
}

function getNextDateForDay($day) {
    $daysOfWeek = ['yakshanba', 'dushanba', 'seshanba', 'chorshanba', 'payshanba', 'juma', 'shanba'];
    $todayIndex = date('w');
    $targetIndex = array_search($day, $daysOfWeek);

    $daysUntilNext = ($targetIndex - $todayIndex + 7) % 7;
    $nextDate = date('d-m-Y', strtotime("+$daysUntilNext days"));

    return $nextDate;
}

function calculateCurrency($amount, $currencyCode) {
    global $cbuCurrencyApiUrl;
    $currencyData = file_get_contents($cbuCurrencyApiUrl);
    $currency = json_decode($currencyData, true);

    if (!$currency) {
        return "❌ Valyuta kurslarini olishda xato yuz berdi.";
    }

    foreach ($currency as $rate) {
        if ($rate['Ccy'] === $currencyCode) {
            $convertedAmount = $amount * $rate['Rate'];
            return "$amount $currencyCode = " . number_format($convertedAmount, 2) . " UZS";
        }
    }
    return "❌ $currencyCode uchun valyuta kursi topilmadi.";
}

function sendMessage($chatId, $message, $isMarkdown = false) {
    global $apiUrl;
    $data = [
        'chat_id' => $chatId,
        'text' => $message
    ];
    if ($isMarkdown) {
        $data['parse_mode'] = 'Markdown';
    }
    file_get_contents($apiUrl . "sendMessage?" . http_build_query($data));
}

function sendMessageWithKeyboard($chatId, $message, $keyboard) {
    global $apiUrl;
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'reply_markup' => json_encode([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ])
    ];
    file_get_contents($apiUrl . "sendMessage?" . http_build_query($data));
}
?>
