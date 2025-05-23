<?php
/**
 * Currency conversion utility functions
 */

// Default exchange rate (can be updated via API)
define('USD_TO_RWF_RATE', 1200); // Example rate: 1 USD = 1200 RWF

/**
 * Convert amount from USD to RWF
 * 
 * @param float $amount Amount in USD
 * @return float Amount in RWF
 */
function usdToRwf($amount) {
    return $amount * USD_TO_RWF_RATE;
}

/**
 * Convert amount from RWF to USD
 * 
 * @param float $amount Amount in RWF
 * @return float Amount in USD
 */
function rwfToUsd($amount) { 
    return $amount / USD_TO_RWF_RATE;
}

/**
 * Format currency based on selected currency
 * 
 * @param float $amount Amount to format
 * @param string $currency Currency code (USD or RWF)
 * @return string Formatted currency string
 */
function formatCurrency($amount, $currency = 'USD') {
    if ($currency === 'RWF') {
        return 'RWF ' . number_format($amount, 0);
    } else {
        return '$' . number_format($amount, 2);
    }
}

/**
 * Convert and format amount based on source and target currencies
 * 
 * @param float $amount Original amount
 * @param string $fromCurrency Source currency code
 * @param string $toCurrency Target currency code
 * @return string Formatted amount in target currency
 */
function convertAndFormat($amount, $fromCurrency = 'USD', $toCurrency = 'USD') {
    if ($fromCurrency === $toCurrency) {
        return formatCurrency($amount, $toCurrency);
    }
    
    if ($fromCurrency === 'USD' && $toCurrency === 'RWF') {
        return formatCurrency(usdToRwf($amount), 'RWF');
    }
    
    if ($fromCurrency === 'RWF' && $toCurrency === 'USD') {
        return formatCurrency(rwfToUsd($amount), 'USD');
    }
    
    // Default fallback
    return formatCurrency($amount, 'USD');
}

/**
 * Get current exchange rate from API (can be implemented with real API)
 * 
 * @return float Current exchange rate
 */
function getExchangeRate() {
    // In a real implementation, you would fetch the current rate from an API
    // For example:
    // $response = file_get_contents('https://api.exchangerate-api.com/v4/latest/USD');
    // $data = json_decode($response, true);
    // return $data['rates']['RWF'];
    
    // For now, return the default rate
    return USD_TO_RWF_RATE;
}

/**
 * Get currency symbol
 * 
 * @param string $currency Currency code
 * @return string Currency symbol
 */
function getCurrencySymbol($currency = 'USD') {
    switch ($currency) {
        case 'RWF':
            return 'RWF';
        case 'USD':
        default:
            return '$';
    }
}