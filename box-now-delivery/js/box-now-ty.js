(function ($) {
    let carrierName = document.getElementById('carrier_name');
    let shippingCountry = document.getElementById('shipping_country');
    let billingCountry = document.getElementById('billing_country');
    let popupIframe = null;

    const boxNowTrustedOriginRegex = /^https:\/\/.*\.boxnow\..*$/;
    const boxNowGeolocationAllowlist = [
        "https://widget-v5.boxnow.gr",
        "https://widget-v5.boxnow.cy",
        "https://widget-v5.boxnow.bg",
        "https://widget-v5.boxnow.si",
        "https://widget-v5.boxnow.hr",
        "https://widget-v4.boxnow.gr",
        "https://widget-v4.boxnow.cy",
        "https://widget-v4.boxnow.bg",
        "https://widget-v4.boxnow.si",
        "https://widget-v4.boxnow.hr",
        "https://map.boxnow.gr",
        "https://map.boxnow.cy",
        "https://map.boxnow.bg",
        "https://map.boxnow.si",
        "https://map.boxnow.hr",
    ].join(" ");

    $(document).ready(function() {
        attachButtonClickListener()
    });

    // Checks when the button is clicked to make the popup.
    function attachButtonClickListener() {
        $("#box_now_delivery_button")
        .off('click') // safety: remove any old ones
        .on("click", function (event) {
            event.preventDefault();
            createPopupMap();
        });

        // Add an event listener for the 'message' event
        window.addEventListener("message", function (event) {
            // 1️. Must be from our iframe
            if (!popupIframe || event.source !== popupIframe.contentWindow) {
                return;
            }

            const data = event.data;
            const origin = event.origin;
            const isAllowed = boxNowTrustedOriginRegex.test(origin);

            // Ignore untrusted origins. Only listen for BOX NOW events
            if(!isAllowed){
                // TODO 3.1.1: console error logs untrusted origins from other plugins that may try to postMessage. This may lead to console flooding. 
                //console.error('BOX NOW Delivery Thank You page: untrusted origin, ignoring message', { origin });
                closeBoxNowPopup()
                return
            }

            // Now it's safe to process the message
            if (
                data === "closeIframe" ||
                (data && data.boxnowClose !== undefined)
            ) {
                closeBoxNowPopup()
            } else {
                updateLockerDetailsContainer(data);
            }
        });

    }

    function closeBoxNowPopup() {
        popupIframe = null;
        $("#box_now_delivery_overlay").remove();
        $("#boxnow_widget_thank_you_page_iframe").remove();
    }

    
    function GetUserCountry() {
        let selectedCountry = 'GR'; // Default fallback

        // Try to get SHIPPING country from WooCommerce block
        const shippingAddressEl = shippingCountry.value;

        // Try to get BILLING country from WooCommerce block (if exists)
        const billingAddressEl = billingCountry.value;

        // Extract the last line (country) from shipping address first
        if (shippingAddressEl) {

            selectedCountry = shippingAddressEl;
        }
        // If shipping not found, try billing
        else if (billingAddressEl) {
            selectedCountry = billingAddressEl;
        } else {
            selectedCountry = 'GR'; // Hard fallback
        }

        return selectedCountry;
    }

    /**
     * Update the locker details container with selected locker data.
     *
     * @param {object} lockerData Locker data object.
     */
    function updateLockerDetailsContainer(lockerData) {
        // Check if locker data is not undefined
        if (
            !lockerData || typeof lockerData !== "object" ||
            lockerData.boxnowLockerId === undefined ||
            lockerData.boxnowLockerAddressLine1 === undefined ||
            lockerData.boxnowLockerPostalCode === undefined ||
            lockerData.boxnowLockerName === undefined
        ) {
            return;
        }

        // Get the selected locker details
        var locker_id = lockerData.boxnowLockerId;

        // Fetch order id and secret
        var order_id = (typeof thankyou_boxnow !== 'undefined' && thankyou_boxnow.order_id) ?
            thankyou_boxnow.order_id :
            window.location.pathname.split('/').filter(segment => /^\d+$/.test(segment))[0];
        var order_key = new URLSearchParams(window.location.search).get('key');

        // Fire the AJAX request to save locker
        jQuery.ajax({
            url: (typeof thankyou_boxnow !== 'undefined' && thankyou_boxnow.ajax_url) ? thankyou_boxnow.ajax_url : (window.ajaxurl || '/wp-admin/admin-ajax.php'),
            method: 'POST',
            data: {
                action: 'thankyou_php_boxnow',
                order_id: order_id,
                _boxnow_locker_id: locker_id,
                box_now_selected_locker: JSON.stringify(lockerData),
                order_key: order_key,
                nonce: (typeof thankyou_boxnow !== 'undefined' && thankyou_boxnow.nonce) ? thankyou_boxnow.nonce : ''
            },
            success: function (response) {
                if (response.success) {
                    updateThankYouPage(lockerData);
                    localStorage.removeItem("box_now_selected_locker");
                } else {
                    showSaveError();
                    console.error('Error saving locker ID:', response.data);
                }
            }
            ,
            error: function () {
                showSaveError();
                console.error('AJAX request thankyou_php_boxnow failed.');
            }
        });
        if (boxNowDeliverySettings.displayMode === "popup") {
            closeBoxNowPopup();
        }
    }

    function updateThankYouPage(lockerData) {
        const lockerIdRow = document.querySelector('.boxnow-thankyou__locker-id');
        const lockerIdValue = document.querySelector('.boxnow-thankyou__locker-id span');
        const title = document.querySelector('.boxnow-thankyou__title');
        const description = document.querySelector('.boxnow-thankyou__description');
        const statusMessage = document.querySelector('.boxnow-thankyou__status');

        if (lockerIdValue) {
            lockerIdValue.textContent = lockerData.boxnowLockerId;
        }
        if (lockerIdRow) {
            lockerIdRow.hidden = false;
        }
        if (title && thankyou_boxnow.selected_title) {
            title.textContent = thankyou_boxnow.selected_title;
        }
        if (description && thankyou_boxnow.selected_description) {
            description.textContent = thankyou_boxnow.selected_description;
        }

        let details = document.getElementById('box_now_selected_locker_details');
        const button = document.getElementById('box_now_delivery_button');
        if (!details && button) {
            details = document.createElement('div');
            details.id = 'box_now_selected_locker_details';
            button.insertAdjacentElement('afterend', details);
        }
        if (details) {
            const info = document.createElement('div');
            info.className = 'locker-info';
            const heading = document.createElement('p');
            heading.className = 'locker-title';
            heading.textContent = thankyou_boxnow.selected_locker_label || 'Selected Locker';
            info.appendChild(heading);

            [lockerData.boxnowLockerName, lockerData.boxnowLockerAddressLine1, lockerData.boxnowLockerPostalCode].forEach(value => {
                if (value === undefined || value === null || value === '') return;
                const line = document.createElement('p');
                line.className = 'locker-detail';
                line.textContent = value;
                info.appendChild(line);
            });

            details.replaceChildren(info);
            details.hidden = false;
            details.style.display = 'block';
        }
        if (statusMessage) {
            statusMessage.classList.remove('boxnow-thankyou__status--error');
            statusMessage.textContent = thankyou_boxnow.changed_message || 'Locker changed successfully.';
        }
    }

    function showSaveError() {
        const statusMessage = document.querySelector('.boxnow-thankyou__status');

        if (statusMessage) {
            statusMessage.textContent = thankyou_boxnow.save_error_message || 'The locker could not be saved. Please try again.';
            statusMessage.classList.add('boxnow-thankyou__status--error');
        }
    }

    function createOverlay() {
        var overlay = $("<div>", {
            id: "box_now_delivery_overlay",
            css: {
                position: "fixed",
                top: 0,
                left: 0,
                width: "100%",
                height: "100%",
                backgroundColor: "rgba(0, 0, 0, 0)",
                zIndex: 9998,
            },
        });

        overlay.on("click", function () {
            closeBoxNowPopup()
        });

        $("body").append(overlay);
    }

    function createPopupMap() {
        let gpsOption = boxNowDeliverySettings.gps_option;
        let partnerId = boxNowDeliverySettings.partnerId;
        let postalCode = $('input[name="billing_postcode"]').val();
        let country = GetUserCountry();

        if (country === "CY") {
            src = "https://widget-v5.boxnow.cy/popup.html"; // TODO 3.1.1: update when available
        } else if (country === "BG") {
            src = "https://widget-v5.boxnow.bg/popup.html";
        } else if (country === "HR") {
            src = "https://widget-v5.boxnow.hr/popup.html";
        } else if (country === "SI") {
            src = "https://widget-v5.boxnow.si/popup.html";
        } else {
            src = "https://widget-v5.boxnow.gr/popup.html";
        }

        partnerId ? src += "?partnerId=" + partnerId + "&" : "?";

        if (gpsOption === "off") {
            src +=
                "gps=no&zip=" +
                encodeURIComponent(postalCode) +
                "&autoclose=yes&autoselect=no";
        } else {
            src += "gps=yes&autoclose=yes&autoselect=no";
        }

        let iframe = $("<iframe>", {
            id: "boxnow_widget_thank_you_page_iframe",
            src: src,
            allow: "geolocation " + boxNowGeolocationAllowlist,
            css: {
                position: "fixed",
                top: "50%",
                left: "50%",
                width: "80%",
                height: "80%",
                border: 0,
                borderRadius: "20px",
                transform: "translate(-50%, -50%)",
                zIndex: 9999,
            },
        });
        popupIframe = iframe[0];

        createOverlay();
        $("body").append(iframe);
    }
})(jQuery); 
