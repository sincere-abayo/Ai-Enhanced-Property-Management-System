<?php
require_once 'auth.php';
$currentCurrency = getUserCurrency();
?>

<div class="currency-switcher">
    <div class="relative inline-block">
        <button id="currencyDropdownButton" class="flex items-center text-gray-700 hover:text-gray-900 focus:outline-none">
            <span id="currentCurrencySymbol"><?php echo $currentCurrency === 'USD' ? '$' : 'RWF'; ?></span>
            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
        <div id="currencyDropdown" class="hidden absolute right-0 mt-2 w-24 bg-white rounded-md shadow-lg z-10">
            <a href="#" class="currency-option block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-currency="USD">USD ($)</a>
            <a href="#" class="currency-option block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-currency="RWF">RWF</a>
        </div>
    </div>
</div>

<!-- Update the JavaScript part of the currency switcher -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const currencyDropdownButton = document.getElementById('currencyDropdownButton');
        const currencyDropdown = document.getElementById('currencyDropdown');
        const currencyOptions = document.querySelectorAll('.currency-option');
        const currentCurrencySymbol = document.getElementById('currentCurrencySymbol');
        
        // Toggle dropdown
        currencyDropdownButton.addEventListener('click', function(e) {
            e.preventDefault();
            currencyDropdown.classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!currencyDropdownButton.contains(e.target) && !currencyDropdown.contains(e.target)) {
                currencyDropdown.classList.add('hidden');
            }
        });
        
        // Handle currency selection
        currencyOptions.forEach(option => {
            option.addEventListener('click', function(e) {
                e.preventDefault();
                const currency = this.getAttribute('data-currency');
                
                // Update UI immediately
                currentCurrencySymbol.textContent = currency === 'USD' ? '$' : 'RWF';
                currencyDropdown.classList.add('hidden');
                
                // Send request to update currency preference
                fetch('../api/toggle_currency.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `currency=${currency}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Dispatch event for real-time currency update
                        document.dispatchEvent(new CustomEvent('currencyChanged', {
                            detail: { currency: currency }
                        }));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
                
            });
        });
    });
</script>
