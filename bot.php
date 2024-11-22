<?php

require_once 'Db.php';
require_once 'weather.php';
require_once 'currency_converter.php';
require_once 'nomoz-vaqtlari.php';

class TelegramBot {
    private $apiUrl;
    private $weather;
    private $currencyConverter;
    private $prayerTimes;

    public function __construct($config) {
        $this->apiUrl = "https://api.telegram.org/bot{$config['botToken']}/";
        $this->weather = new Weather($config['weatherApiToken']);
        $this->currencyConverter = new CurrencyConverter($config['cbuCurrencyApiUrl']);
        $this->prayerTimes = new PrayerTimes($config['aladhanApiUrl']);
    }

    public function handleRequest() {
        $update = json_decode(file_get_contents("php://input"), true);

        if (isset($update['message'])) {
            $chatId = $update['message']['chat']['id'];
            $username = $update['message']['chat']['username'] ?? null;
            $messageText = strtolower(trim($update['message']['text']));

            $this->saveUser($chatId, $username);
            $this->processMessage($chatId, $messageText);
        }
    }

    private function saveUser($chatId, $username) {
        $pdo = DB::connect();
        try {
            $stmt = $pdo->prepare("INSERT INTO users (chat_id, username) VALUES (:chat_id, :username) ON DUPLICATE KEY UPDATE username = :username");
            $stmt->execute([':chat_id' => $chatId, ':username' => $username]);
        } catch (PDOException $e) {
            error_log("Foydalanuvchini saqlashda xato:" . $e->getMessage());
        }
    }

    private function processMessage($chatId, $messageText) {
        if ($messageText == "/start") {
            $this->sendWelcomeMessage($chatId);
        } elseif ($messageText == "valyuta kurslari") {
            $this->sendMessage($chatId, "💵 Valyutalar kurslarini hisoblash uchun miqdor va valyuta kodini kiriting.\n\nMisol: `100 USD`", true);
        } elseif ($messageText == "ob-havo") {
            $this->sendMessage($chatId, "⛅ Ob-havo ma'lumotlarini olish uchun shahar nomini kiriting.\n\nMisol: `Toshkent`", true);
        } elseif ($messageText == "namoz vaqtlari") {
            $this->sendMessage($chatId, "🕌 Namoz vaqtlari uchun hafta kunini kiriting.\n\nMisol: `Dushanba`", true);
        } elseif (preg_match("/^([0-9]+)\s*([a-z]{3})$/i", $messageText, $matches)) {
            $response = $this->currencyConverter->convert($matches[1], $matches[2]);
            $this->sendMessage($chatId, $response);
        } elseif ($this->isDayOfWeek($messageText)) {
            $response = $this->prayerTimes->getPrayerTimes($messageText, "Toshkent");
            $this->sendMessage($chatId, $response);
        } else {
            $response = $this->weather->getWeather($messageText);
            $this->sendMessage($chatId, $response);
        }
    }

    private function sendWelcomeMessage($chatId) {
        $message = "Assalomu alaykum! 😊\nMen quyidagilarni bilishingizga yordam beraman:\n\n⛅ Ob-havo uchun 'Ob-havo' tugmasini bosing.\n🕌 Namoz vaqtlari uchun 'Namoz vaqtlari' tugmasini bosing.\n💵 Valyuta kurslari uchun 'Valyuta kurslari' tugmasini bosing.";
        $keyboard = [["Ob-havo"], ["Namoz vaqtlari"], ["Valyuta kurslari"]];
        $this->sendMessageWithKeyboard($chatId, $message, $keyboard);
    }

    private function sendMessage($chatId, $message, $isMarkdown = false) {
        $data = ['chat_id' => $chatId, 'text' => $message];
        if ($isMarkdown) {
            $data['parse_mode'] = 'Markdown';
        }
        file_get_contents($this->apiUrl . "sendMessage?" . http_build_query($data));
    }

    private function sendMessageWithKeyboard($chatId, $message, $keyboard) {
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false
            ])
        ];
        file_get_contents($this->apiUrl . "sendMessage?" . http_build_query($data));
    }

    private function isDayOfWeek($day) {
        $weekDays = ['dushanba', 'seshanba', 'chorshanba', 'payshanba', 'juma', 'shanba', 'yakshanba'];
        return in_array($day, $weekDays);
    }
}
