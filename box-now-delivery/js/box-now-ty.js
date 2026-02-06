(function ($) {
    let carrierName = document.getElementById('carrier_name');
    let shippingCountry = document.getElementById('shipping_country');
    let billingCountry = document.getElementById('billing_country');

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
            if (
                event.data === "closeIframe" ||
                event.data.boxnowClose !== undefined
            ) {
                $("#box_now_delivery_overlay").remove();
                $("#boxnow_widget_thank_you_page_iframe").remove();
            } else {
                updateLockerDetailsContainer(event.data);
            }
        });

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
            lockerData.boxnowLockerId === undefined ||
            lockerData.boxnowLockerAddressLine1 === undefined ||
            lockerData.boxnowLockerPostalCode === undefined ||
            lockerData.boxnowLockerName === undefined
        ) {
            return;
        }

        // Get the selected locker details
        var locker_id = lockerData.boxnowLockerId;

        // Add more fields as needed
        localStorage.setItem("box_now_selected_locker", JSON.stringify(lockerData));

        // Fetch order id and secret
        var order_id = window.location.pathname.split('/').filter(segment => /^\d+$/.test(segment))[0];
        var order_key = new URLSearchParams(window.location.search).get('key');

        // Try to select elements and apply styles
        const strongTag = document.querySelector('.boxnow-thankyou p strong');
        const element = document.getElementById('box_now_selected_locker_details');

        if (element) {
            element.style.margin = '1% auto';  // red background
            element.style.maxWidth = '20vw';            // white text
        }
        if (strongTag && strongTag.nextSibling) {
            const lockerId = strongTag.nextSibling.textContent.trim();
            // Set new locker ID
            strongTag.nextSibling.textContent = ' ' + locker_id + ' Locker Changed Sucessfuly!';
        }

        // Fire the AJAX request to save locker
        jQuery.ajax({
            url: window.ajaxurl || '/wp-admin/admin-ajax.php',
            method: 'POST',
            data: {
                action: 'thankyou_php_boxnow',
                order_id: order_id,
                _boxnow_locker_id: locker_id,
                order_key: order_key
            },
            success: function (response) {
                if (response.success) {
                    localStorage.removeItem("box_now_selected_locker");
                } else {
                    console.error('Error saving locker ID:', response.data.message);
                }
            }
            ,
            error: function () {
                console.error('AJAX request thankyou_php_boxnow failed.');
            }
        });
        if (boxNowDeliverySettings.displayMode === "popup") {
            $("#box_now_delivery_overlay").remove();
            $("iframe[src^='https://widget-v5.boxnow.gr/popup.html']").remove();
            $("iframe[src^='https://widget-v5.boxnow.cy/popup.html']").remove();
            $("iframe[src^='https://widget-v5.boxnow.bg/popup.html']").remove();
            $("iframe[src^='https://widget-v5.boxnow.hr/popup.html']").remove();
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
            $("#box_now_delivery_overlay").remove();
            $("iframe[src^='https://widget-v5.boxnow.gr/popup.html']").remove();
            $("iframe[src^='https://widget-v5.boxnow.cy/popup.html']").remove();
            $("iframe[src^='https://widget-v5.boxnow.bg/popup.html']").remove();
            $("iframe[src^='https://widget-v5.boxnow.hr/popup.html']").remove();
        });

        $("body").append(overlay);
    }

    function createPopupMap() {
        let gpsOption = boxNowDeliverySettings.gps_option;
        let partnerId = boxNowDeliverySettings.partnerId;
        let postalCode = $('input[name="billing_postcode"]').val();
        let country = GetUserCountry();

        if (country === "CY") {
            src = "https://widget-v5.boxnow.cy/popup.html";
        } else if (country === "BG") {
            src = "https://widget-v5.boxnow.bg/popup.html";
        } else if (country === "HR") {
            src = "https://widget-v5.boxnow.hr/popup.html";
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

        createOverlay();
        $("body").append(iframe);
    }
})(jQuery); 