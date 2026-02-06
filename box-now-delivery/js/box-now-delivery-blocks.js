/**
 * Box Now Delivery integration with WooCommerce Blocks checkout.
 *
 * This script handles the integration of Box Now Delivery with the WooCommerce Blocks checkout.
 * It adds a button to pick a locker from the map, validates that a locker is selected before
 * placing an order, and stores the selected locker ID in the order data.
 */

// Global variable to store the locker ID value
let boxNowLockerIdValue = '';
// Internal flag to avoid starting multiple observers
let bndpObserverStarted = false;

document.addEventListener('DOMContentLoaded', () => {

    // Listen for shipping method changes and checkout updates
    document.body.addEventListener('change', (event) => {
        const target = event.target;
        if (target.matches('input[name^="radio-control-0"]')) {
            showSelectedLockerDetailsFromLocalStorage();
        }

        if (target.id === 'shipping-country' || target.id === 'shipping_country') {
            removeLockerFromSession();
        }
    });

    document.body.addEventListener('updated_checkout', showSelectedLockerDetailsFromLocalStorage);

    // Initialize Blocks registry filters
    waitForWooCommerceBlocksRegistry(() => {
        const { registerCheckoutFilters } = window.wc.blocksCheckout;

        registerCheckoutFilters('box-now-delivery', {
            validateCheckoutResponse: (checkoutResponse) => {
                const shippingMethod = checkoutResponse.shipping_method;
                if (shippingMethod && shippingMethod.includes('box_now_delivery') && !boxNowLockerIdValue) {
                    throw {
                        code: 'box-now-delivery-locker-not-selected',
                        message: boxNowDeliverySettings.lockerNotSelectedMessage || 'Please select a locker first!',
                        messageContext: 'wc/checkout'
                    };
                }
                return checkoutResponse;
            },
            beforeProcessCheckoutResponse: (checkoutData) => {
                if (boxNowLockerIdValue) {
                    // Top-level property for PHP
                    checkoutData._boxnow_locker_id = boxNowLockerIdValue;

                    // Legacy extensions for backward compatibility
                    checkoutData.extensions = checkoutData.extensions || {};
                    checkoutData.extensions['box-now-delivery'] = checkoutData.extensions['box-now-delivery'] || {};
                    checkoutData.extensions['box-now-delivery']['_boxnow_locker_id'] = boxNowLockerIdValue;
                }
                return checkoutData;
            }
        });

        setTimeout(addBoxNowButton, 800);
        startBndpObserver();
    });

    patchCheckoutFetchForLockerId();
});

// Wait for Blocks registry
function waitForWooCommerceBlocksRegistry(callback) {
    if (window.wc && window.wc.blocksCheckout) callback();
    else setTimeout(() => waitForWooCommerceBlocksRegistry(callback), 100);
}

// Patch fetch to include locker ID
function patchCheckoutFetchForLockerId() {
    if (window._bndpFetchPatched) return;
    window._bndpFetchPatched = true;
    const originalFetch = window.fetch;

    window.fetch = async function(input, init) {
        try {
            const url = (typeof input === 'string') ? input : (input?.url || '');
            if (!url || !url.includes('/store/v1/checkout')) return originalFetch.apply(this, arguments);

            let opts = (typeof input === 'string') ? (init || {}) : { ...input };
            if ((opts.method || 'POST').toUpperCase() === 'POST' && opts.body && opts.headers) {
                const contentType = opts.headers['Content-Type'] || opts.headers['content-type'] || '';
                if (typeof opts.body === 'string' && contentType.includes('application/json')) {
                    try {
                        const bodyObj = JSON.parse(opts.body);
                        if (boxNowLockerIdValue) {
                            // Top-level for PHP
                            bodyObj._boxnow_locker_id = boxNowLockerIdValue;

                            // Legacy extensions
                            bodyObj.extensions = bodyObj.extensions || {};
                            bodyObj.extensions['box-now-delivery'] = bodyObj.extensions['box-now-delivery'] || {};
                            bodyObj.extensions['box-now-delivery']['_boxnow_locker_id'] = boxNowLockerIdValue;
                        }
                        opts.body = JSON.stringify(bodyObj);
                        if (typeof input !== 'string') input = new Request(url, opts);
                        else init = opts;
                    } catch (e) { console.warn("patchCheckoutFetchForLockerId parse error", e); }
                }
            }
        } catch (e) { console.warn("patchCheckoutFetchForLockerId error", e); }

        return originalFetch.apply(this, arguments);
    };

    // Place order click guard
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('button.wc-block-components-checkout-place-order-button, button.wp-element-button, button.button');
        if (!btn) return;
        const selected = document.querySelector('.wc-block-components-radio-control__option input[type="radio"]:checked');
        if (selected?.value.includes('box_now_delivery') && !boxNowLockerIdValue) {
            e.preventDefault();
            e.stopPropagation();
            alert(boxNowDeliverySettings.lockerNotSelectedMessage || 'Please select a locker first!');
            return false;
        }
    }, true);
}

// MutationObserver to add Box Now button on dynamic checkout refreshes
function startBndpObserver() {
    if (bndpObserverStarted) return;
    const checkoutContainer = document.querySelector('.wp-block-woocommerce-checkout') || document.body;
    if (!checkoutContainer) return;
    bndpObserverStarted = true;
    const observer = new MutationObserver(() => setTimeout(addBoxNowButton, 300));
    observer.observe(checkoutContainer, { childList: true, subtree: true, attributes: true });
}

/**
 * Add the Box Now button to the blocks checkout.
 */
function addBoxNowButton() {
    // Find possible shipping method containers used by various Woo Blocks versions
    const shippingMethodContainers = document.querySelectorAll(
        '.wc-block-components-shipping-rates-control__package, .wc-block-components-shipping-rates-control, .wc-block-components-shipping-methods, .wc-block-components-shipping-methods-list'
    );

    if (shippingMethodContainers.length === 0) {
        return;
    }

    // Check each shipping method container
    shippingMethodContainers.forEach(container => {
        // Find the Box Now Delivery shipping method input/option across variants
        const boxNowMethod = container.querySelector(
            'input[value*="box_now_delivery"], [data-shipping-method-id*="box_now_delivery"] input'
        );
        const rateItem = container.querySelector('[data-rate-id*="box_now_delivery"], [data-shipping-method-rate-id*="box_now_delivery"]');
        const targetEl = boxNowMethod || rateItem;

        if (!targetEl) {
            return;
        }

        // Find the option container to append our UI
        const optionContainer = targetEl.closest('.wc-block-components-radio-control__option') || targetEl.closest('[data-rate-id]') || targetEl.closest('li') || targetEl.closest('label') || targetEl.parentElement || container;

        // Check if we already added the button in this option container
        if (optionContainer.querySelector('#box_now_delivery_button_blocks')) {
            return;
        }

        // Create the button
        const button = document.createElement('button');
        button.type = 'button';
        button.id = 'box_now_delivery_button_blocks';
        button.textContent = boxNowDeliverySettings.buttonText || 'Pick a Locker';
        button.style.backgroundColor = boxNowDeliverySettings.buttonColor || '#6CD04E';
        button.style.color = '#fff';
        button.style.marginTop = '6px';
        button.style.padding = '8px 12px';
        button.style.border = 'none';
        button.style.borderRadius = '4px';
        button.style.cursor = 'pointer';

        // Container for selected locker details
        const detailsDiv = document.createElement('div');
        detailsDiv.id = 'box_now_selected_locker_details_blocks';
        detailsDiv.style.display = 'none';
        detailsDiv.style.marginTop = '8px';

        // Hidden input to hold the value for non-block processes, plus a global var for blocks
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.id = '_boxnow_locker_id';
        hiddenInput.name = '_boxnow_locker_id';

        // Append UI near the shipping option label/description
        optionContainer.appendChild(button);
        optionContainer.appendChild(detailsDiv);
        optionContainer.appendChild(hiddenInput);

        // On click open widget
        button.addEventListener('click', (event) => {
            event.preventDefault();
            openBoxNowWidget();
        });

        showSelectedLockerDetailsFromLocalStorage();
    });
}

function getUserCountry() {
    // 1) Try WooCommerce Blocks data stores first (supporting multiple versions)
    try {
        if (window.wp && wp.data && typeof wp.data.select === 'function') {
            const tryStore = (key) => {
                try {
                    const sel = wp.data.select(key);
                    if (!sel) return '';
                    if (typeof sel.getShippingAddress === 'function') {
                        const ship = sel.getShippingAddress();
                        if (ship && ship.country) return ship.country;
                    }
                    if (typeof sel.getBillingAddress === 'function') {
                        const bill = sel.getBillingAddress();
                        if (bill && bill.country) return bill.country;
                    }
                    if (typeof sel.getCustomerData === 'function') {
                        const cust = sel.getCustomerData();
                        if (cust && cust.shippingAddress && cust.shippingAddress.country) return cust.shippingAddress.country;
                        if (cust && cust.billingAddress && cust.billingAddress.country) return cust.billingAddress.country;
                    }
                } catch (e) {
                    console.warn("getUserCountry 1: ", e);
                }
                return '';
            };
            let country = tryStore('wc/store/checkout') || tryStore('wc/store');
            if (country) return String(country).toUpperCase().slice(0, 2);
        }
    } catch (e) {
        console.warn("getUserCountry 2: ", e);
    }

    // 2) Try WooCommerce settings object (Blocks env)
    try {
        const s = (window.wc && (window.wc.wcSettings || window.wc.settings)) || window.wcSettings || {};
        if (s && s.shippingAddress && s.shippingAddress.country) {
            return String(s.shippingAddress.country).toUpperCase().slice(0, 2);
        }
        if (s && s.billingAddress && s.billingAddress.country) {
            return String(s.billingAddress.country).toUpperCase().slice(0, 2);
        }
        if (s && s.defaultCountry) {
            const dc = String(s.defaultCountry).split(':')[0];
            if (dc) return String(dc).toUpperCase().slice(0, 2);
        }
    } catch (e) {
        console.warn("getUserCountry 3: ", e);
    }

    // 3) Fallback for Blocks DOM (country select inside address forms)
    const blocksCountryInput = document.querySelector(
        '.wc-block-components-address-form select[name="country"], .wc-block-components-country-input select[name="country"], .wc-block-components-country-input select'
    );
    if (blocksCountryInput && blocksCountryInput.value) {
        return String(blocksCountryInput.value).toUpperCase().slice(0, 2);
    }

    // 4) Fallback for classic checkout DOM"
    const shipToDiff = document.querySelector('#ship-to-different-address-checkbox');
    let selector;
    if (shipToDiff && shipToDiff.checked) {
        selector = 'select[name="shipping_country"], input[name="shipping_country"]';
    } else {
        selector = 'select[name="billing_country"], input[name="billing_country"]';
    }
    const countryInput = document.querySelector(selector) || document.querySelector('select[name="billing_country"], input[name="billing_country"], select[name="shipping_country"], input[name="shipping_country"], select[name="country"]');
    
    return countryInput && countryInput.value ? String(countryInput.value).toUpperCase().slice(0, 2) : '';
}

function openBoxNowWidget() {
    const gpsOption = boxNowDeliverySettings.gps_option;
    const partnerId = boxNowDeliverySettings.partnerId;
    const postalCodeEl = document.querySelector('input[name="shipping_postcode"]');
    const postalCode = postalCodeEl ? postalCodeEl.value : '';
    const country = getUserCountry();
    let src;
    if (country === 'CY') {
        src = 'https://widget-v5.boxnow.cy/popup.html';
    } else if (country === 'BG') {
        src = 'https://widget-v5.boxnow.bg/popup.html';
    } else if (country === 'HR') {
        src = 'https://widget-v5.boxnow.hr/popup.html';
    } else {
        src = 'https://widget-v5.boxnow.gr/popup.html';
    }

    src += partnerId ? `?partnerId=${partnerId}&` : '?';

    if (gpsOption === 'off') {
        src += `gps=no&zip=${encodeURIComponent(postalCode)}&autoclose=yes&autoselect=no`;
    } else {
        src += 'gps=yes&autoclose=yes&autoselect=no';
    }

    const overlay = document.createElement('div');
    overlay.id = 'box_now_delivery_overlay';
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100%';
    overlay.style.height = '100%';
    overlay.style.backgroundColor = 'rgba(0,0,0,0)';
    overlay.style.zIndex = '9998';
    overlay.addEventListener('click', () => {
        const existing = document.getElementById('box_now_delivery_iframe');
        if (existing) existing.remove();
        overlay.remove();
    });
    document.body.appendChild(overlay);

    const iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.id = 'box_now_delivery_iframe';
    iframe.style.position = 'fixed';
    iframe.style.top = '50%';
    iframe.style.left = '50%';
    iframe.style.width = '80%';
    iframe.style.height = '80%';
    iframe.style.border = '0';
    iframe.style.borderRadius = '20px';
    iframe.style.transform = 'translate(-50%, -50%)';
    iframe.style.zIndex = '9999';

    window.addEventListener('message', function(event) {
        if (event.data === 'closeIframe' || typeof event.data.boxnowClose !== 'undefined') {
            const existing = document.getElementById('box_now_delivery_iframe');
            if (existing) existing.remove();
            const ov = document.getElementById('box_now_delivery_overlay');
            if (ov) ov.remove();
        } else {
            updateLockerDetails(event.data);
        }
    });

    document.body.appendChild(iframe);
}

function showSelectedLockerDetailsFromLocalStorage() {
    const lockerDataStr = localStorage.getItem('box_now_selected_locker');
    if (!lockerDataStr) {
        toggleBoxNowPickLockerUI(false);
        return;
    }
    try {
        const lockerData = JSON.parse(lockerDataStr);
        updateLockerDetails(lockerData);
    } catch (e) {
        console.warn("showSelectedLockerDetailsFromLocalStorage: ", e);
    }
}

function updateLockerDetails(lockerData) {
    if (
        typeof lockerData.boxnowLockerId === 'undefined' ||
        typeof lockerData.boxnowLockerAddressLine1 === 'undefined' ||
        typeof lockerData.boxnowLockerPostalCode === 'undefined' ||
        typeof lockerData.boxnowLockerName === 'undefined'
    ) {
        return;
    }

    localStorage.setItem('box_now_selected_locker', JSON.stringify(lockerData));
    boxNowLockerIdValue = lockerData.boxnowLockerId || '';

    // Persist to Woo session via AJAX as a fallback path for Blocks
    try {
        if (boxNowDeliverySettings && boxNowDeliverySettings.ajaxUrl && boxNowLockerIdValue) {
            fetch(boxNowDeliverySettings.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: `action=bndp_set_boxnow_locker&locker_id=${encodeURIComponent(boxNowLockerIdValue)}`
            }).catch(() => {});
        }
    } catch (e) {
        console.warn("updateLockerDetails error: ", e);
    }

    // Update all instances on the page if multiple packages blocks
    document.querySelectorAll('#box_now_selected_locker_details_blocks').forEach(detailsDiv => {
        const hiddenInput = detailsDiv.parentElement.querySelector('#_boxnow_locker_id');
        if (hiddenInput) hiddenInput.value = boxNowLockerIdValue;

        const language = document.documentElement.lang || 'el';
        const englishContent = `
<div style="font-family: Verdana , Arial, sans-serif;font-weight:300;margin-top: -7px;">
  <p style="margin: 1px 0px; color: #61bb46;font-weight: 400;height: 25px;"><b>Selected Locker</b></p>
  <p style="margin: 1px 0px; font-size: 13px;line-height:20px;height: 20px;">${lockerData.boxnowLockerName}</p>
  <p style="margin: 1px 0px; font-size: 13px;line-height:20px;height: 20px;">${lockerData.boxnowLockerAddressLine1}</p>
  <p style="margin: 1px 0px; font-size: 13px;line-height:20px;height: 20px;">${lockerData.boxnowLockerPostalCode}</p>
</div>`;
        const greekContent = `
<div style="font-family: Verdana , Arial, sans-serif;font-weight:300;margin-top: -7px;">
  <p style="margin: 1px 0px; color: #61bb46;font-weight: 400;height: 25px;"><b>Επιλεγμένο Locker</b></p>
  <p style="margin: 1px 0px; font-size: 13px;line-height:20px;height: 20px;">${lockerData.boxnowLockerName}</p>
  <p style="margin: 1px 0px; font-size: 13px;line-height:20px;height: 20px;">${lockerData.boxnowLockerAddressLine1}</p>
  <p style="margin: 1px 0px; font-size: 13px;line-height:20px;height: 20px;">${lockerData.boxnowLockerPostalCode}</p>
</div>`;
        const content = language === 'el' ? greekContent : englishContent;
        detailsDiv.innerHTML = content;
    });
    toggleBoxNowPickLockerUI(true);
}

// Remove selected locker from WooCommerce session via AJAX
function removeLockerFromSession() {
    try {
        if (boxNowDeliverySettings && boxNowDeliverySettings.ajaxUrl) {
            fetch(boxNowDeliverySettings.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: `action=bndp_clear_boxnow_locker`
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Remove selected locker from local storage as well if present
                    localStorage.removeItem("box_now_selected_locker");
                    updateSelectedLockerUI();
                } else {
                    console.warn('Action failed:', result);
                }
            })
            .catch(error => console.error('removeLockerFromSession - Fetch error:', error));
        }
    } catch (e) {
        console.warn("removeLockerFromSession error: ", e);
    }
}

function updateSelectedLockerUI() {
    const box = document.querySelector("#box_now_selected_locker_details_blocks");
    if (box) {
        box.innerHTML = "";
    }
    toggleBoxNowPickLockerUI(false);
}

function toggleBoxNowPickLockerUI(isLockerSelected) {
    const selectedShippingMethod = document.querySelector('input[name^="radio-control-0"]:checked');
    const isBoxNowSelected = selectedShippingMethod && selectedShippingMethod.value && selectedShippingMethod.value === "box_now_delivery";
    document.getElementById('box_now_delivery_button_blocks').style.display = (isBoxNowSelected ? 'inline-block' : 'none');
    document.getElementById('box_now_selected_locker_details_blocks').style.display = (isBoxNowSelected && isLockerSelected ? 'block' : 'none');
}