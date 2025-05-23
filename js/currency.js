/**
 * Currency conversion utilities for client-side use
 */

// Exchange rate (should match the PHP constant)
const USD_TO_RWF_RATE = 1200;

/**
 * Convert amount from USD to RWF
 * 
 * @param {number} amount - Amount in USD
 * @returns {number} Amount in RWF
 */
function usdToRwf(amount) {
    return amount * USD_TO_RWF_RATE;
}

/**
 * Convert amount from RWF to USD
 * 
 * @param {number} amount - Amount in RWF
 * @returns {number} Amount in USD
 */
function rwfToUsd(amount) {
    return amount / USD_TO_RWF_RATE;
}

/**
 * Format currency based on selected currency
 * 
 * @param {number} amount - Amount to format
 * @param {string} currency - Currency code (USD or RWF)
 * @returns {string} Formatted currency string
 */
function formatCurrency(amount, currency = 'USD') {
    if (currency === 'RWF') {
        return 'RWF ' + Math.round(amount).toLocaleString();
    } else {
        return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
}

/**
 * Convert and format amount based on source and target currencies
 * 
 * @param {number} amount - Original amount
 * @param {string} fromCurrency - Source currency code
 * @param {string} toCurrency - Target currency code
 * @returns {string} Formatted amount in target currency
 */
function convertAndFormat(amount, fromCurrency = 'USD', toCurrency = 'USD') {
    if (fromCurrency === toCurrency) {
        return formatCurrency(amount, toCurrency);
    }
    
    if (fromCurrency === 'USD' && toCurrency === 'RWF') {
        return formatCurrency(usdToRwf(amount), 'RWF');
    }
    
    if (fromCurrency === 'RWF' && toCurrency === 'USD') {
        return formatCurrency(rwfToUsd(amount), 'USD');
    }
    
    // Default fallback
    return formatCurrency(amount, 'USD');
}

/**
 * Get currency symbol
 * 
 * @param {string} currency - Currency code
 * @returns {string} Currency symbol
 */
function getCurrencySymbol(currency = 'USD') {
    switch (currency) {
        case 'RWF':
            return 'RWF';
        case 'USD':
        default:
            return '$';
    }
}

/**
 * Update all currency elements on the page
 * 
 * @param {string} toCurrency - Target currency to display
 */
function updatePageCurrencies(toCurrency = 'USD') {
    document.querySelectorAll('[data-currency-value]').forEach(element => {
        const amount = parseFloat(element.getAttribute('data-currency-value'));
        const fromCurrency = element.getAttribute('data-currency-original') || 'USD';
        
        if (!isNaN(amount)) {
            element.textContent = convertAndFormat(amount, fromCurrency, toCurrency);
        }
    });
}

// Export functions for module usage if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        usdToRwf,
        rwfToUsd,
        formatCurrency,
        convertAndFormat,
        getCurrencySymbol,
        updatePageCurrencies
    };
}
