<?php

class CurrencyConverter {
    private $cbuCurrencyApiUrl;

    public function __construct($cbuCurrencyApiUrl) {
        $this->cbuCurrencyApiUrl = $cbuCurrencyApiUrl;
    }

    public function convert($amount, $currencyCode) {
        $currencyData = file_get_contents($this->cbuCurrencyApiUrl);
        $currency = json_decode($currencyData, true);

        foreach ($currency as $rate) {
            if ($rate['Ccy'] === strtoupper($currencyCode)) {
                $convertedAmount = $amount * $rate['Rate'];
                return "$amount $currencyCode = " . number_format($convertedAmount, 2) . " UZS";
            }
        }
        return "❌ $currencyCode valyuta kursi topilmadi.";
    }
}
