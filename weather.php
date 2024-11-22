<?php

class Weather {
    private $weatherApiToken;

    public function __construct($weatherApiToken) {
        $this->weatherApiToken = $weatherApiToken;
    }

    public function getWeather($city) {
        $weatherUrl = "https://api.openweathermap.org/data/2.5/weather?q=$city&appid={$this->weatherApiToken}&units=metric";
        $weatherData = json_decode(file_get_contents($weatherUrl), true);

        if ($weatherData && isset($weatherData['main'])) {
            return "🌆 Shaxar: $city
🌡 Harorat: {$weatherData['main']['temp']}°C
🌤 Ob-havo: " . ucfirst($weatherData['weather'][0]['description']) . "
💧 Namlik: {$weatherData['main']['humidity']}%
🌬 Shamol tezligi: {$weatherData['wind']['speed']} m/s";
        }
        return "❌ Ob-havo maʼlumotlari topilmadi. Boshqa shahar nomini kiriting.";
    }
}
