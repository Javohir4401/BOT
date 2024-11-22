<?php

class PrayerTimes {
    private $aladhanApiUrl;

    public function __construct($aladhanApiUrl) {
        $this->aladhanApiUrl = $aladhanApiUrl;
    }

    public function getPrayerTimes($day, $address) {
        $date = $this->getNextDateForDay($day);
        $prayerUrl = "{$this->aladhanApiUrl}?date=$date&address=" . urlencode($address);
        $prayerData = json_decode(file_get_contents($prayerUrl), true);

        if ($prayerData && $prayerData['code'] === 200) {
            $prayerTimes = $prayerData['data']['timings'];
            $response = "🕌 Namoz vaqtlari ($day, $date):\n";
            foreach ($prayerTimes as $prayer => $time) {
                $response .= "$prayer: $time\n";
            }
            return $response;
        }
        return "❌ Namoz vaqtlarini olishda xato yuz berdi.";
    }

    private function getNextDateForDay($day) {
        $daysOfWeek = ['yakshanba', 'dushanba', 'seshanba', 'chorshanba', 'payshanba', 'juma', 'shanba'];
        $todayIndex = date('w');
        $targetIndex = array_search($day, $daysOfWeek);

        $daysUntilNext = ($targetIndex - $todayIndex + 7) % 7;
        return date('d-m-Y', strtotime("+$daysUntilNext days"));
    }
}
