(function ($) {
    let popupIframe = null;

    const boxNowTrustedOriginRegex = /^https:\/\/.*\.boxnow\..*$/;

    $(document).ready(function() {
        attachButtonClickListener()

        window.removeEventListener("message", handleBoxNowMessage); // Avoid multiple listeners via AJAX in some themes
        // Add an event listener for the 'message' event
        window.addEventListener("message", handleBoxNowMessage, false);

    });

    // Checks when the button is clicked to make the popup.
    function attachButtonClickListener() {
        $("#box_now_delivery_button")
        .off('click') // safety - remove any old ones
        .on("click", function (event) {
            event.preventDefault();
            createPopupMap();
        });
    }

    function GetUserCountry() {
        const boxNowCountries = ['GR', 'CY', 'BG', 'HR', 'SI'];

        // Helper to validate country
        const isValidCountry = (country) => boxNowCountries.includes(country);

        // Try shipping first
        const shippingSelect = document.querySelector('#_shipping_country');
        const shippingCountry = shippingSelect?.value?.toUpperCase();
        if (shippingCountry && isValidCountry(shippingCountry)) {
            return shippingCountry;
        }

        // Then billing
        const billingSelect = document.querySelector('#_billing_country');
        const billingCountry = billingSelect?.value?.toUpperCase();
        if (billingCountry && isValidCountry(billingCountry)) {
            return billingCountry;
        }

        // Fallback
        return 'GR';
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

        // Add a hidden input field to store locker information
        document.getElementById('_boxnow_locker_id').value = locker_id;

        if (boxNowDeliverySettings.displayMode === "popup") {
            closeBoxNowPopup();
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
            closeBoxNowPopup();
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
            id: "boxnow_widget_admin_page_iframe",
            src: src,
            allow: "geolocation https://*.boxnow.gr https://*.boxnow.cy https://*.boxnow.bg https://*.boxnow.si https://*.boxnow.hr",
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

    function handleBoxNowMessage(event) {
        // 1. Must be from our iframe
        if (!popupIframe || event.source !== popupIframe.contentWindow) {
            return;
        }

        const data = event.data;
        const origin = event.origin;
        const isAllowed = boxNowTrustedOriginRegex.test(origin);
        
        // Ignore untrusted origins. Only listen for BOX NOW events
        if(!isAllowed){
            // TODO console error logs untrusted origins from other plugins that may try to postMessage. This may lead to console flooding. 
            //console.error('BOX NOW Delivery SHORTCODE: untrusted origin, ignoring message', { origin });
            // TODO also log to server for security monitoring. Send ajax request to server with origin info and log wc_get_logger error_log
            closeBoxNowPopup()
            return
        }

        if (
            data === "closeIframe" ||
            data.boxnowClose !== undefined
        ) {
            closeBoxNowPopup();
        } else {
            updateLockerDetailsContainer(data);
        }
    }

    function closeBoxNowPopup() {
        popupIframe = null;
        $("#box_now_delivery_overlay").remove();
        $("#boxnow_widget_admin_page_iframe").remove();
    }

})(jQuery);
