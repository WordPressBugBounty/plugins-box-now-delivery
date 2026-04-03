/**
 * BOX NOW Delivery integration with WooCommerce Blocks checkout.
 *
 * This script handles the integration of BOX NOW Delivery with the WooCommerce Blocks checkout.
 * It adds a button to pick a locker from the map, validates that a locker is selected before
 * placing an order, and stores the selected locker ID in the order data.
 */

/* TODO: WooCommerce Blocks checkout does NOT use <input> radio buttons for shipping methods.*/

// Global variable to store the locker ID value
let popupIframe = null;
let iframeObserver = null;
let embeddedIframe = null;
const { select, subscribe } = wp.data;
let lastRateId = null;
let lastCountry = null;
let lastIsBoxNow = null;
let codTitleUpdateScheduled = false;
let codTitleObserver = null;
let codTitleObserverIsBoxNow = null;
let codOriginalTitle = null;
let codOriginalDescription = null;
const codTitle = (typeof boxNowDeliverySettings !== 'undefined' && boxNowDeliverySettings.codTitle)
    ? boxNowDeliverySettings.codTitle
    : 'BOX NOW PAY ON THE GO!';
const codDescription = (typeof boxNowDeliverySettings !== 'undefined' && boxNowDeliverySettings.codDescription)
    ? boxNowDeliverySettings.codDescription
    : '';

wp.data.subscribe(() => {
    const selectedRates = getSelectedShippingRates();
    if (!selectedRates.length) {
        scheduleCodTitleUpdate(false);
        return;
    }

    const rate = selectedRates[0];
    const isEmbedded = boxNowDeliverySettings.displayMode === 'embedded';
    const isBoxNow = isBoxNowDeliverySelected(selectedRates);
    let countryChanged = false;

    if (isEmbedded) {
        const currentCountry = getUserCountry();
        if (currentCountry && currentCountry !== lastCountry) {
            lastCountry = currentCountry;
            countryChanged = true;
        }
    }

    const embeddedMapMissing = isEmbedded && isBoxNow && !document.querySelector('#box_now_delivery_embedded_map_blocks');
    const buttonMissing = !isEmbedded && isBoxNow && !document.querySelector('#box_now_delivery_button_blocks');

    if (rate.rate_id === lastRateId && !countryChanged && !embeddedMapMissing && !buttonMissing) {
        return;
    }

    lastRateId = rate.rate_id;

    if (isBoxNow !== lastIsBoxNow || countryChanged) {
        scheduleCodTitleUpdate(isBoxNow);
        lastIsBoxNow = isBoxNow;
    }

    if (isBoxNow && (countryChanged || embeddedMapMissing || buttonMissing)) {
        addBoxNowButton();
    }

    if (isEmbedded && isBoxNow && (countryChanged || embeddedMapMissing)) {
        refreshEmbeddedMapBlocks();
    }

    const lockerId = getSelectedBoxNowLockerIDFromLocalStorage();
    toggleBoxNowPickLockerUI(isBoxNow && !!lockerId);
    if (isBoxNow) {
        showSelectedLockerDetailsFromLocalStorage();
    }
});

const boxNowTrustedOriginRegex = /^https:\/\/.*\.boxnow\..*$/;

document.addEventListener('DOMContentLoaded', () => {
    initBoxNowBlocksIntegration();

    // Optional: initial add of button in case checkout is already rendered
    refreshBoxNowCheckoutUI();

    // Initialize Blocks registry filters
    waitForWooCommerceBlocksRegistry(() => {
        const { registerCheckoutFilters } = window.wc.blocksCheckout;

        registerCheckoutFilters('box-now-delivery', {
            validateCheckoutResponse: (checkoutResponse) => {
                const shippingMethod = checkoutResponse.shipping_method;
                const lockerId = getSelectedBoxNowLockerIDFromLocalStorage();

                if (shippingMethod && shippingMethod.includes('box_now_delivery') && !lockerId) {
                    throw {
                        code: 'box-now-delivery-locker-not-selected',
                        message: boxNowDeliverySettings.lockerNotSelectedMessage || 'Please select a locker first!',
                        messageContext: 'wc/checkout'
                    };
                }
                return checkoutResponse;
            },
            beforeProcessCheckoutResponse: (checkoutData) => {
                const lockerId = getSelectedBoxNowLockerIDFromLocalStorage();
                if (lockerId) {
                    // Top-level property for PHP
                    checkoutData._boxnow_locker_id = lockerId;

                    // Legacy extensions for backward compatibility
                    checkoutData.extensions = checkoutData.extensions || {};
                    checkoutData.extensions['box-now-delivery'] = checkoutData.extensions['box-now-delivery'] || {};
                    checkoutData.extensions['box-now-delivery']['_boxnow_locker_id'] = lockerId;
                }
                return checkoutData;
            }
        });

        setTimeout(() => {
            refreshBoxNowCheckoutUI();
        }, 800);
    });

    patchCheckoutFetchForLockerId();
});

document.addEventListener('wc-blocks-checkout-render', () => {
    refreshBoxNowCheckoutUI();
});

document.addEventListener('wc-blocks-checkout-payment-methods-render', () => {
    refreshBoxNowCheckoutUI();
});

document.addEventListener('change', event => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    if (!target.matches('input[type="radio"], input.wc-block-components-radio-control__input')) return;

    const option = target.closest('.wc-block-components-radio-control__option');
    if (!option) return;

    if (getPaymentMethodIdFromOption(option) === 'cod') {
        scheduleCodTitleUpdate(isBoxNowDeliverySelected());
    }
});

function getSelectedBoxNowLockerDataFromLocalStorage() {
    const lockerDataStr = localStorage.getItem('box_now_selected_locker');

    if (!lockerDataStr) {
        return null;
    }
    try {
        const lockerData = JSON.parse(lockerDataStr);
        return lockerData;
    } catch (e) {
        return null;
    }
}


function getSelectedBoxNowLockerIDFromLocalStorage() {
    const lockerData = getSelectedBoxNowLockerDataFromLocalStorage();
    const lockerId = lockerData ? lockerData.boxnowLockerId : null;
    return lockerId;
}

function refreshBoxNowCheckoutUI() {
    requestAnimationFrame(() => {
        addBoxNowButton();

        const selectedRates = getSelectedShippingRates();
        const isBoxNowSelected = isBoxNowDeliverySelected(selectedRates);
        const lockerId = getSelectedBoxNowLockerIDFromLocalStorage();

        toggleBoxNowPickLockerUI(isBoxNowSelected && !!lockerId);
        scheduleCodTitleUpdate(isBoxNowSelected);

        if (isBoxNowSelected) {
            showSelectedLockerDetailsFromLocalStorage();
        }
    });
}

function isBoxNowDeliverySelected(selectedRates) {
    const rates = selectedRates || getSelectedShippingRates();
    if (!rates.length) return false;

    return rates.some(rate => {
        if (rate.method_id === 'box_now_delivery') return true;
        const rateId = rate.rate_id || '';
        return typeof rateId === 'string' && rateId.indexOf('box_now_delivery') === 0;
    });
}

function scheduleCodTitleUpdate(isBoxNow) {
    if (codTitleUpdateScheduled && lastIsBoxNow === isBoxNow) {
        return;
    }
    codTitleUpdateScheduled = true;
    requestAnimationFrame(() => {
        codTitleUpdateScheduled = false;
        const titleUpdated = updateCodTitleForBlocks(isBoxNow);
        const descriptionUpdated = updateCodDescriptionForBlocks(isBoxNow);
        if (titleUpdated && descriptionUpdated) {
            if (codTitleObserver) {
                codTitleObserver.disconnect();
                codTitleObserver = null;
            }
        } else {
            ensureCodTitleObserver(isBoxNow);
        }
    });
}

function ensureCodTitleObserver(isBoxNow) {
    if (typeof MutationObserver === 'undefined') return;
    if (codTitleObserver && codTitleObserverIsBoxNow === isBoxNow) return;
    if (codTitleObserver) codTitleObserver.disconnect();

    codTitleObserverIsBoxNow = isBoxNow;
    const root =
        document.querySelector('.wc-block-components-payment-methods') ||
        document.querySelector('.wc-block-components-checkout') ||
        document.querySelector('.wc-block-checkout') ||
        document.body;

    const observer = new MutationObserver(() => {
        const titleUpdated = updateCodTitleForBlocks(isBoxNow);
        const descriptionUpdated = updateCodDescriptionForBlocks(isBoxNow);
        if (titleUpdated && descriptionUpdated) {
            observer.disconnect();
            if (codTitleObserver === observer) {
                codTitleObserver = null;
            }
        }
    });
    codTitleObserver = observer;

    codTitleObserver.observe(root, { childList: true, subtree: true });
}

function getPaymentMethodIdFromOption(option) {
    const input = option.querySelector(
        'input[type="radio"], input.wc-block-components-radio-control__input'
    );
    let paymentId = input?.value;

    if (!paymentId) {
        paymentId =
            option.dataset.paymentMethodId ||
            option.getAttribute('data-payment-method-id') ||
            option.getAttribute('data-method-id');
    }

    if (!paymentId) {
        const dataEl = option.querySelector('[data-payment-method-id], [data-method-id]');
        paymentId = dataEl?.getAttribute('data-payment-method-id') || dataEl?.getAttribute('data-method-id');
    }

    return paymentId;
}

function getCodDescriptionElement(option) {
    const directDescription =
        option.querySelector('.wc-block-components-payment-method-description') ||
        option.querySelector('.wc-block-components-radio-control__description');

    if (directDescription) {
        return directDescription;
    }

    const input = option.querySelector(
        'input[type="radio"], input.wc-block-components-radio-control__input'
    );
    const describedBy = input?.getAttribute('aria-describedby');
    if (describedBy) {
        const describedByElement = document.getElementById(describedBy);
        if (describedByElement) {
            return describedByElement;
        }
    }

    const container =
        option.closest('.wc-block-components-payment-method') ||
        option.parentElement ||
        option;

    return (
        container.querySelector('.wc-block-components-payment-method-description') ||
        container.querySelector('.wc-block-components-radio-control__description') ||
        null
    );
}

function updateCodTitleForBlocks(isBoxNow) {
    const options = document.querySelectorAll('.wc-block-components-radio-control__option');
    if (!options.length) {
        return false;
    }

    let updated = false;

    options.forEach(option => {
        const paymentId = getPaymentMethodIdFromOption(option);
        if (paymentId !== 'cod') return;

        const label =
            option.querySelector('.wc-block-components-radio-control__label') ||
            option.querySelector('label') ||
            option.querySelector('.wc-block-components-radio-control__option-label') ||
            option.querySelector('span');

        if (!label) return;

        const labelText = label.textContent.trim();
        if (!codOriginalTitle && (!isBoxNow || labelText !== codTitle)) {
            codOriginalTitle = labelText;
        }

        if (!option.dataset.bndpCodOriginalTitle) {
            option.dataset.bndpCodOriginalTitle = labelText;
        }

        const originalTitle = option.dataset.bndpCodOriginalTitle || codOriginalTitle || labelText;
        const nextTitle = isBoxNow ? codTitle : originalTitle;
        if (labelText !== nextTitle) {
            label.textContent = nextTitle;
        }
        updated = true;
    });
    return updated;
}

function updateCodDescriptionForBlocks(isBoxNow) {
    const options = document.querySelectorAll('.wc-block-components-radio-control__option');
    if (!options.length) {
        return false;
    }

    let hasCodOption = false;
    let updated = false;

    options.forEach(option => {
        const paymentId = getPaymentMethodIdFromOption(option);
        if (paymentId !== 'cod') return;

        hasCodOption = true;

        const description = getCodDescriptionElement(option);

        if (!description) {
            return;
        }

        const descriptionHtml = description.innerHTML.trim();
        if (!codOriginalDescription && (!isBoxNow || !codDescription || descriptionHtml !== codDescription)) {
            codOriginalDescription = descriptionHtml;
        }

        if (!option.dataset.bndpCodOriginalDescription) {
            option.dataset.bndpCodOriginalDescription = descriptionHtml;
        }

        const originalDescription =
            option.dataset.bndpCodOriginalDescription || codOriginalDescription || descriptionHtml;
        const nextDescription = isBoxNow && codDescription ? codDescription : originalDescription;

        if (description.innerHTML.trim() !== nextDescription) {
            description.innerHTML = nextDescription;
        }

        updated = true;
    });

    return hasCodOption && (updated || (!isBoxNow || !codDescription));
}
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
                        const lockerId = getSelectedBoxNowLockerIDFromLocalStorage();
                        if (lockerId) {
                            // Top-level for PHP
                            bodyObj._boxnow_locker_id = lockerId;

                            // Legacy extensions
                            bodyObj.extensions = bodyObj.extensions || {};
                            bodyObj.extensions['box-now-delivery'] = bodyObj.extensions['box-now-delivery'] || {};
                            bodyObj.extensions['box-now-delivery']['_boxnow_locker_id'] = lockerId;
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
        const lockerId = getSelectedBoxNowLockerIDFromLocalStorage();
        if (selected?.value.includes('box_now_delivery') && !lockerId) {
            e.preventDefault();
            e.stopPropagation();
            alert(boxNowDeliverySettings.lockerNotSelectedMessage || 'Please select a locker first!');
            return false;
        }
    }, true);
}

/**
 * Add the BOX NOW button to the blocks checkout.
 */
function addBoxNowButton() {
    const useEmbedded = boxNowDeliverySettings.displayMode === 'embedded';
    // Find possible shipping method containers used by various Woo Blocks versions
    const shippingMethodContainers = document.querySelectorAll(
        '.wc-block-components-shipping-rates-control__package, .wc-block-components-shipping-rates-control, .wc-block-components-shipping-methods, .wc-block-components-shipping-methods-list'
    );

    if (shippingMethodContainers.length === 0) {
        return;
    }

    // Check each shipping method container
    shippingMethodContainers.forEach(container => {
        // Find the BOX NOW Delivery shipping method input/option across variants
        const boxNowMethod = container.querySelector(
            'input[value*="box_now_delivery"], [data-shipping-method-id*="box_now_delivery"] input'
        );
        const rateItem = container.querySelector('[data-rate-id*="box_now_delivery"], [data-shipping-method-rate-id*="box_now_delivery"]');
        const targetEl = boxNowMethod || rateItem;

        if (!targetEl) {
            return;
        }

        /* TODO: WooCommerce Blocks checkout does NOT use <input> radio buttons for shipping methods.*/
        // Find the option container to append our UI
        const optionContainer = targetEl.closest('.wc-block-components-radio-control__option') || targetEl.closest('[data-rate-id]') || targetEl.closest('li') || targetEl.closest('label') || targetEl.parentElement || container;

        // Check if we already added the button in this option container
        if (optionContainer.querySelector('#box_now_delivery_button_blocks') || optionContainer.querySelector('#box_now_delivery_embedded_map_blocks')) {
            return;
        }

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

        if (useEmbedded) {
            closeBoxNowPopup();
            const embeddedMap = document.createElement('div');
            embeddedMap.id = 'box_now_delivery_embedded_map_blocks';
            embeddedMap.style.display = 'none';
            embeddedMap.style.position = 'relative';
            embeddedMap.style.width = '100%';
            embeddedMap.style.height = '55vh';
            embeddedMap.style.overflow = 'auto';

            const iframe = createEmbeddedIframeBlocks();
            embeddedIframe = iframe;
            embeddedMap.appendChild(iframe);
            embeddedMap.appendChild(detailsDiv);

            optionContainer.appendChild(embeddedMap);
            optionContainer.appendChild(hiddenInput);
        } else {
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

            // Append UI near the shipping option label/description
            optionContainer.appendChild(button);
            optionContainer.appendChild(detailsDiv);
            optionContainer.appendChild(hiddenInput);

            // On click open widget
            button.addEventListener('click', (event) => {
                event.preventDefault();
                openBoxNowWidget();
            });
        }

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
    if (boxNowDeliverySettings.displayMode === 'embedded') {
        return;
    }
    // Prevent multiple popups
    if (document.getElementById('box_now_delivery_iframe_blocks') 
        || document.getElementById('box_now_delivery_overlay_blocks')) {
        return;
    }

    const gpsOption = boxNowDeliverySettings.gps_option;
    const partnerId = boxNowDeliverySettings.partnerId;
    const postalCodeEl = document.querySelector('input[name="shipping_postcode"]');
    const postalCode = postalCodeEl ? postalCodeEl.value : '';
    const country = getUserCountry();
    let src;
    if (country === 'CY') {
        src = 'https://widget-v5.boxnow.cy/popup.html'; // TODO 3.1.1: update when available
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
    overlay.id = 'box_now_delivery_overlay_blocks';
    overlay.style.position = 'fixed';
    overlay.style.top = '0';
    overlay.style.left = '0';
    overlay.style.width = '100%';
    overlay.style.height = '100%';
    overlay.style.backgroundColor = 'rgba(0,0,0,0)';
    overlay.style.zIndex = '9998';
    overlay.addEventListener('click', closeBoxNowPopup);
    document.body.appendChild(overlay);

    const iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.allow = "geolocation",
    iframe.id = 'box_now_delivery_iframe_blocks';
    iframe.style.position = 'fixed';
    iframe.style.top = '50%';
    iframe.style.left = '50%';
    iframe.style.width = '80%';
    iframe.style.height = '80%';
    iframe.style.border = '0';
    iframe.style.borderRadius = '20px';
    iframe.style.transform = 'translate(-50%, -50%)';
    iframe.style.zIndex = '9999';

    popupIframe = iframe;

    document.body.appendChild(iframe);
    observeIframeRemoval(iframe);

}

function observeIframeRemoval(iframe) {
    if (!iframe || typeof MutationObserver === "undefined") return;

    // Prevent multiple observers
    if (iframeObserver) {
        iframeObserver.disconnect();
    }
        
    iframeObserver= new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.removedNodes) {
                if (node === iframe) {
                    closeBoxNowPopup();
                    return;
                }
            }
        }
    });

    iframeObserver.observe(document.body, { childList: true });
}

function handleBoxNowCloseIframeMessage(event) {
    // 1. Must be from our iframe
    const isPopupMessage = popupIframe && event.source === popupIframe.contentWindow;
    const isEmbeddedMessage = embeddedIframe && event.source === embeddedIframe.contentWindow;
    if (!isPopupMessage && !isEmbeddedMessage) {
        return;
    }

    const data = event.data;
    const origin = event.origin;
    const isAllowed = boxNowTrustedOriginRegex.test(origin);

    // Ignore untrusted origins. Only listen for BOX NOW events
    if(!isAllowed){ 
        // TODO 3.1.1: console error logs untrusted origins from other plugins that may try to postMessage. This may lead to console flooding. 
        console.error('BOX NOW Delivery BLOCKS: untrusted origin, ignoring message', { origin });
        // TODO also log to server for security monitoring. Send ajax request to server with origin info and log wc_get_logger error_log
        closeBoxNowPopup();
        return
    }

    // Safety check: ignore non-object messages unless it's "closeIframe"
    if (typeof data !== "object" && data !== "closeIframe") {
        return;
    }

    // Now it's safe to process the message
    // Handle close message
    if (data === 'closeIframe' || (typeof data === "object" && data !== null && data.boxnowClose !== undefined)) {
        // Remove popup overlays/iframes
        if (boxNowDeliverySettings.displayMode === "popup" && isPopupMessage) {
            closeBoxNowPopup(); 
        }
        return;
    }

    if (typeof data === 'object') {
        updateLockerDetails(data);
    }
}

function closeBoxNowPopup() {
    if (popupIframe) {
        popupIframe.remove();
        popupIframe = null;
    }

    if (iframeObserver) {
        iframeObserver.disconnect();
        iframeObserver = null;
    }
    // Use querySelectorAll to remove all matching iframes and overlays
    document.querySelectorAll('#box_now_delivery_iframe_blocks').forEach(el => el.remove());
    document.querySelectorAll('#box_now_delivery_overlay_blocks').forEach(el => el.remove());
    document.getElementById('box_now_delivery_iframe_blocks')?.remove();
    document.getElementById('box_now_delivery_overlay_blocks')?.remove();
}

// prevent multiple event listeners attachemnt
if (!window._boxNowMessageListenerAttached) {
    window.addEventListener('message', handleBoxNowCloseIframeMessage, false);
    window._boxNowMessageListenerAttached = true;
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
        closeBoxNowPopup();
        return;
    }

    localStorage.setItem('box_now_selected_locker', JSON.stringify(lockerData));

    // Persist to Woo session via AJAX as a fallback path for Blocks
    try {
        if (boxNowDeliverySettings && boxNowDeliverySettings.ajaxUrl && lockerData.boxnowLockerId) {
            fetch(boxNowDeliverySettings.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: `action=bndp_set_boxnow_locker&locker_id=${encodeURIComponent(lockerData.boxnowLockerId)}&nonce=${encodeURIComponent(boxNowDeliverySettings.nonce || '')}`
            }).catch(() => {});
        }
    } catch (e) {
        console.warn("updateLockerDetails error: ", e);
    }

    // Update all instances on the page if multiple packages blocks
    document.querySelectorAll('#box_now_selected_locker_details_blocks').forEach(detailsDiv => {
        const hiddenInput = detailsDiv.parentElement.querySelector('#_boxnow_locker_id');
        if (hiddenInput) hiddenInput.value = lockerData.boxnowLockerId;

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
    if (boxNowDeliverySettings.displayMode === "popup") {
        closeBoxNowPopup();
    }
}

function getSelectedShippingRates() {
    const store = select('wc/store/cart');
    if (!store) return [];

    const shippingRates = store.getShippingRates();
    if (!shippingRates?.length) return [];

    return shippingRates.flatMap(pkg =>
        pkg.shipping_rates.filter(rate => rate.selected)
    );
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
    const shippingOptions = document.querySelectorAll(
        '.wc-block-components-radio-control__option'
    );

    shippingOptions.forEach(option => {
        const radio = option.querySelector(
            'input[type="radio"], input.wc-block-components-radio-control__input'
        );
        if (!radio) return;

        const isBoxNow = radio.value && radio.value.includes('box_now_delivery');
        const isSelected = radio.checked;

        const button = option.querySelector('#box_now_delivery_button_blocks');
        const detailsDiv = option.querySelector('#box_now_selected_locker_details_blocks');
        const embeddedMap = option.querySelector('#box_now_delivery_embedded_map_blocks');

        // Default: hide everything
        if (button) button.style.display = 'none';
        if (detailsDiv) detailsDiv.style.display = 'none';
        if (embeddedMap) embeddedMap.style.display = 'none';

        // Only act on Box Now option
        if (!isBoxNow) return;

        if (boxNowDeliverySettings.displayMode === 'embedded') {
            if (embeddedMap) {
                embeddedMap.style.display = isSelected ? 'block' : 'none';
            }
        } else {
            // Box Now selected → show button
            if (isSelected && button) {
                button.style.display = 'inline-block';
            }
        }

        // Box Now selected + locker → show details
        if (detailsDiv) {
            detailsDiv.style.display = isSelected && isLockerSelected ? 'block' : 'none';
        }

        if (!isSelected && embeddedMap) {
            embeddedMap.remove();
        }
    });
}

function refreshEmbeddedMapBlocks() {
    if (boxNowDeliverySettings.displayMode !== 'embedded') {
        return;
    }

    const embeddedMaps = document.querySelectorAll('#box_now_delivery_embedded_map_blocks');
    if (!embeddedMaps.length) {
        addBoxNowButton();
        return;
    }

    embeddedMaps.forEach(map => {
        const existingIframe = map.querySelector('iframe');
        const newIframe = createEmbeddedIframeBlocks();
        embeddedIframe = newIframe;

        if (existingIframe) {
            existingIframe.replaceWith(newIframe);
        } else {
            map.insertBefore(newIframe, map.firstChild);
        }
    });
}

function createEmbeddedIframeBlocks() {
    const gpsOption = boxNowDeliverySettings.gps_option;
    const partnerId = boxNowDeliverySettings.partnerId;
    const postalCodeEl = document.querySelector('input[name="shipping_postcode"]');
    const postalCode = postalCodeEl ? postalCodeEl.value : '';
    const country = getUserCountry();
    let src;
    if (country === 'CY') {
        src = 'https://widget-v5.boxnow.cy'; // TODO 3.1.1: update when available
    } else if (country === 'BG') {
        src = 'https://widget-v5.boxnow.bg';
    } else if (country === 'HR') {
        src = 'https://widget-v5.boxnow.hr';
    } else {
        src = 'https://widget-v5.boxnow.gr';
    }

    src += partnerId ? `?partnerId=${partnerId}&` : '?';

    if (gpsOption === 'off') {
        src += `gps=no&zip=${encodeURIComponent(postalCode)}`;
    } else {
        src += 'gps=yes';
    }

    const iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.style.width = '100%';
    iframe.style.height = '80%';
    iframe.style.border = '0';
    return iframe;
}

function initBoxNowBlocksIntegration() {
    // Listen for postal code or country changes to update UI
    document.body.addEventListener('change', (event) => {
        const target = event.target;

        if (
            target.id === 'shipping-postcode' ||
            target.name === 'shipping-postcode' ||
            target.id === 'shipping-country' ||
            target.id === 'shipping_country'
        ) {
            const selected = document.querySelector(
                '.wc-block-components-radio-control__option input[type="radio"]:checked'
            );
            const isBoxNowSelected = selected?.value?.includes('box_now_delivery');
            const lockerId = getSelectedBoxNowLockerIDFromLocalStorage();

            toggleBoxNowPickLockerUI(isBoxNowSelected && !!lockerId);

            // Clear locker if country changes
            if (target.id === 'shipping-country' || target.id === 'shipping_country') {
                removeLockerFromSession();
            }
        }
    }); 
}
