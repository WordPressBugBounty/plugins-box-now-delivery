(function ($) {
    $(document).ready(function() {
        attachButtonClickListener()
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
        const boxNowCountries = ['GR', 'CY', 'BG', 'HR'];

        // Helper to validate country
        const isValidCountry = (country) => boxNowCountries.includes(country);

        // Try shipping first
        const shippingSelect = document.querySelector('#_shipping_country');
        if (shippingSelect?.value && isValidCountry(shippingSelect.value)) {
            return shippingSelect.value;
        }

        // Then billing
        const billingSelect = document.querySelector('#_billing_country');
        if (billingSelect?.value && isValidCountry(billingSelect.value)) {
            return billingSelect.value;
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

        // Add an event listener for the 'message' event
        window.addEventListener("message", function (event) {
            if (
                event.data === "closeIframe" ||
                event.data.boxnowClose !== undefined
            ) {
                $("#box_now_delivery_overlay").remove(); // Add this line
                iframe.remove();
            } else {
                updateLockerDetailsContainer(event.data);
            }
        });

        createOverlay();
        $("body").append(iframe);
    }

})(jQuery);