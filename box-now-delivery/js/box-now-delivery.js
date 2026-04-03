(function ($) {
    let lockerSelected = false;
    let iframeObserver = null;
    let popupIframe = null;

    const boxNowTrustedOriginRegex = /^https:\/\/.*\.boxnow\..*$/;

    function handleBoxNowMessage(event) {
        // 1. Must be from our iframe
        if (boxNowDeliverySettings.displayMode === "popup" && (!popupIframe || event.source !== popupIframe.contentWindow)) {
            return;
        }

        const data = event.data;
        const origin = event.origin;
        const isAllowed = boxNowTrustedOriginRegex.test(origin);

        // Ignore untrusted origins. Only listen for BOX NOW events
        if(!isAllowed){
            // TODO 3.1.1: console error logs untrusted origins from other plugins that may try to postMessage. This may lead to console flooding. 
            //console.error('BOX NOW Delivery SHORTCODE: untrusted origin, ignoring message', { origin });
            closeBoxNowPopup()
            return
        }
        
        // Safety check: ignore non-object messages unless it's "closeIframe"
        if (typeof data !== "object" && data !== "closeIframe") {
            return;
        }

        // Now it's safe to process the message
        // Handle close message
        if (data === "closeIframe" || (typeof data === "object" && data !== null && data.boxnowClose !== undefined)) {
            // Remove popup overlays/iframes
            if (boxNowDeliverySettings.displayMode === "popup") {
                closeBoxNowPopup(); 
            }
            return;
        }

        // Handle locker data selection
        updateLockerDetailsContainer(data);
        showSelectedLockerDetailsFromLocalStorage();
        lockerSelected = true;
    }

    function closeBoxNowPopup() {
        popupIframe = null;

        if (iframeObserver) {
            iframeObserver.disconnect();
            iframeObserver = null;
        }

        $("#box_now_delivery_iframe_popup").remove();
        $("#box_now_delivery_overlay").remove();
    }


    /**
     * Add the BOX NOW Delivery button or embedded map.
     */
    function addButton() {
        var useEmbedded = boxNowDeliverySettings.displayMode === "embedded" && boxNowDeliverySettings.page === "checkout";
        if (
            $("#box_now_delivery_button").length === 0 &&
            !useEmbedded
        ) {
            var buttonText = boxNowDeliverySettings.buttonText || "Pick a locker";

            $('label[for="shipping_method_0_box_now_delivery"]').after(
                '<button type="button" id="box_now_delivery_button" style="display:none;">' +
                buttonText +
                "</button>"
            );

            attachButtonClickListener();
        } else if (useEmbedded) {
            $("#box_now_delivery_button").remove();
            $("#box_now_delivery_iframe_popup").remove();
            $("#box_now_delivery_overlay").remove();
            popupIframe = null;

            if (iframeObserver) {
                iframeObserver.disconnect();
                iframeObserver = null;
            }

            if ($("#box_now_delivery_embedded_map").length === 0) {
                $('label[for="shipping_method_0_box_now_delivery"]').after(
                    '<div id="box_now_delivery_embedded_map" style="display:none;"></div>'
                );
            }
            embedMap();
        }
        applyButtonStyles();
    }

    /**
     * Apply the custom styles for the BOX NOW Delivery button.
     */
    function applyButtonStyles() {
        var buttonColor = boxNowDeliverySettings.buttonColor || "#6CD04E";

        var styleBlock = `
      <style id="box-now-delivery-button-styles">
        #box_now_delivery_button {
          background-color: ${buttonColor} !important;
          color: #fff !important;
        }
      </style>
    `;

        $("head").append(styleBlock);
    }

    /**
     * Attach click event listener to the BOX NOW Delivery button.
     */
    function attachButtonClickListener() {
        $("#box_now_delivery_button")
        .off('click') // safety - remove any old ones
        .on("click", function (event) {
            event.preventDefault();
            createPopupMap();
        });
    }

    function GetUserCountry() {
        let selectedCountry;

        // Modified if clause that mitigates for shipping, billing address and cases where only one service country is selected.
        if ($('#ship-to-different-address-checkbox').is(":checked")) {
            // Check if the shipping country field is a select or hidden input
            if ($('select[name="shipping_country"]').length) {
                // If it's a select, get the selected value
                selectedCountry = $('select[name="shipping_country"]').val();
            } else if ($('input[name="shipping_country"]').length) {
                // If it's a hidden input, get the value directly
                selectedCountry = $('input[name="shipping_country"]').val();
            }
        } else {
            // Check if the billing country field is a select or hidden input
            if ($('select[name="billing_country"]').length) {
                // If it's a select, get the selected value
                selectedCountry = $('select[name="billing_country"]').val();
            } else if ($('input[name="billing_country"]').length) {
                // If it's a hidden input, get the value directly
                selectedCountry = $('input[name="billing_country"]').val();
            }
        }

        return selectedCountry;
    }
    /**
     * Embed the map to the page.
     */
    function embedMap() {
        var iframe = $("#box_now_delivery_embedded_map iframe");

        if (iframe.length === 0) {
            iframe = createEmbeddedIframe();

            var lockerDetailsContainer = $("<div>", {
                id: "box_now_selected_locker_details",
                css: {
                    display: "none",
                    marginTop: "10px",
                },
            });

            // Create a new div to hold the locker information
            var lockerInfoContainer = $("<div>", {
                id: "locker_info_container",
            });

            $("#box_now_delivery_embedded_map")
                .css({
                    position: "relative",
                    width: "100%",
                    height: "80vh", // Set the height to 100%
                    overflow: "auto"
                })
                .append(iframe)
                .append(lockerInfoContainer.append(lockerDetailsContainer));

           
        }

        $("#box_now_delivery_button").hide();
        var selected = $('input[name^="shipping_method"]:checked, input[name^="shipping_method"][type="hidden"]');

        if (selected.length && selected.val().includes('box_now_delivery')) {
            $("#box_now_delivery_embedded_map").show();
        } else {
            $("#box_now_delivery_embedded_map").hide();
        }
    }

    // Overlay for the popup iframe
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

    /**
     * Create an iframe for the popup map.
     */
    function createPopupMap() {
        // Prevent duplicate popups
        if ($("#box_now_delivery_iframe_popup").length) {
            return;
        }

        let src;
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
        } else {
            src = "https://widget-v5.boxnow.gr/popup.html";
        }

        if (partnerId) {
            src += "?partnerId=" + partnerId + "&";
        } else {
            src += "?";
        }

        if (gpsOption === "off") {
            src +=
                "gps=no&zip=" +
                encodeURIComponent(postalCode) +
                "&autoclose=yes&autoselect=no";
        } else {
            src += "gps=yes&autoclose=yes&autoselect=no";
        }

        let iframe = $("<iframe>", {
            id: "box_now_delivery_iframe_popup",
            src: src,
            allow: "geolocation",
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
                    if (node === iframe[0]) {
                        // iframe disappeared for ANY reason
                        $("#box_now_delivery_overlay").remove();
                        $(".boxnow-popup").remove();
                        iframeObserver.disconnect();
                        iframeObserver = null;

                        return;
                    }
                }
            }
        });

        iframeObserver.observe(document.body, { childList: true });
    }

    /**
     * Create an iframe for the embedded map.
     */
    function createEmbeddedIframe() {
        let src;
        let gpsOption = boxNowDeliverySettings.gps_option;
        let partnerId = boxNowDeliverySettings.partnerId;
        let postalCode = $('input[name="billing_postcode"]').val();
        let country = GetUserCountry();

        if (country === "CY") {
            src = "https://widget-v5.boxnow.cy"; // TODO 3.1.1: update when available
        } else if (country === "BG") {
            src = "https://widget-v5.boxnow.bg";
        } else if (country === "HR") {
            src = "https://widget-v5.boxnow.hr";
        } else {
            src = "https://widget-v5.boxnow.gr";
        }

        if (partnerId) {
            src += "?partnerId=" + partnerId + "&";
        } else {
            src += "?";
        }

        if (gpsOption === "off") {
            src += "gps=no&zip=" + encodeURIComponent(postalCode);
        } else {
            src += "gps=yes";
        }

        return $("<iframe>", {
            src: src,
            css: {
                width: "100%",
                height: "70%",
                border: 0,
            },
        });
    }

    function sendLockerToServer(lockerId) {
        if (!lockerId) {
            return;
        }

        $.ajax({
            url: boxNowDeliverySettings.ajaxUrl,
            type: 'POST',
            data: {
                action: 'boxnow_set_locker',
                locker_id: lockerId,
                nonce: boxNowDeliverySettings.nonce
            },
            success: function(response) {
            },
            error: function(xhr, status, error) {
            }
        });
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
        var locker_address = lockerData.boxnowLockerAddressLine1;
        var locker_postal_code = lockerData.boxnowLockerPostalCode;
        var locker_name = lockerData.boxnowLockerName;
        // Add more fields as needed

        localStorage.setItem("box_now_selected_locker", JSON.stringify(lockerData));

        // Ensure the locker details container is added after the BOX NOW Delivery button
        if ($("#box_now_selected_locker_details").length === 0) {
            $("#box_now_delivery_button").after(
                '<div id="box_now_selected_locker_details" style="display:none;"></div>'
            );
        }

        // Add a hidden input field to store locker_id
        if ($("#_boxnow_locker_id").length === 0) {
            $("<input>")
                .attr({
                    type: "hidden",
                    id: "_boxnow_locker_id",
                    name: "_boxnow_locker_id",
                    value: locker_id,
                })
                .appendTo("#box_now_selected_locker_details");
        } else {
            $("#_boxnow_locker_id").val(locker_id);
        }

        // Update the locker details container
        // Get the language of the webpage.
        // If the language is not defined, default to Greek.
        var language = document.documentElement.lang || "el";

        // Define the content for English.
        var englishContent = `
<div id="locker-info">
  <p class="locker-title"><b>Selected Locker</b></p>
  <p class="locker-detail">${locker_name}</p>
  <p class="locker-detail">${locker_address}</p>
  <p class="locker-detail">${locker_postal_code}</p>
</div>`;

        // Define the content for Greek.
        var greekContent = `
<div id="locker-info">
  <p class="locker-title"><b>Επιλεγμένο Locker</b></p>
  <p class="locker-detail">${locker_name}</p>
  <p class="locker-detail">${locker_address}</p>
  <p class="locker-detail">${locker_postal_code}</p>
</div>`;

        // Choose the correct content based on the language.
        var content = language === "el" ? greekContent : englishContent;

        // Update the locker details container.
        $("#box_now_selected_locker_details").html(content).show();

        // Add a hidden input field to store locker information
        if ($("#box_now_selected_locker_input").length === 0) {
            $("<input>")
                .attr({
                    type: "hidden",
                    id: "box_now_selected_locker_input",
                    name: "box_now_selected_locker",
                    value: JSON.stringify(lockerData),
                })
                .appendTo("#box_now_selected_locker_details");
        } else {
            $("#box_now_selected_locker_input").val(JSON.stringify(lockerData));
        }

        sendLockerToServer(locker_id);

        if (boxNowDeliverySettings.displayMode === "popup") {
            closeBoxNowPopup()
        }
    }

    /**
     * Show the selected locker details from local storage.
     */
    function showSelectedLockerDetailsFromLocalStorage() {
        var lockerData = localStorage.getItem("box_now_selected_locker");

        if (lockerData) {
            updateLockerDetailsContainer(JSON.parse(lockerData));
        }
    }

    /**
     * Toggle the BOX NOW Delivery button or embedded map based on the selected shipping method.
     */
    function toggleBoxNowDelivery() {
        if (boxNowDeliverySettings.displayMode === "popup" || boxNowDeliverySettings.page !== "checkout") {
            toggleBoxNowDeliveryButton();
        } else if (boxNowDeliverySettings.displayMode === "embedded") {
            embedMap();
        }
    }

    /**
     * Toggle the BOX NOW Delivery button visibility based on the selected shipping method.
     */
    function toggleBoxNowDeliveryButton() {
        var boxButton = $("#box_now_delivery_button");

        // Set the background color once since it's common for all conditions
        var buttonColor = boxNowDeliverySettings.buttonColor || "#6CD04E";
        boxButton.css("background-color", buttonColor);

        var isSelected = false;
        if(boxNowDeliverySettings.page === "thankyou_page"){
            isSelected = true;
        }else if (boxNowDeliverySettings.page === "checkout"){
            // Radio-based selection
            var radio = $('input[type="radio"][name="shipping_method[0]"]:checked');
            if (radio.length) {
                isSelected = radio.val() === 'box_now_delivery';
            }
            // Hidden input fallback (some themes/flows)
            if (!isSelected) {
                var hiddenVal = $('input[type="hidden"][name="shipping_method[0]"]').val();
                if (hiddenVal) {
                    isSelected = hiddenVal === 'box_now_delivery';
                }
            }
        }

        if (isSelected) {
            boxButton.show();
        } else {
            boxButton.hide();
        }
    }

    /**
     * Initialize the script.
     */
    function init() {
        addButton();
        toggleBoxNowDelivery();

        if ($("#shipping_method_0_box_now_delivery").is(":checked")) {
            showSelectedLockerDetailsFromLocalStorage();
        }
    }

    /**
     * Remove the selected locker details from Session.
     */
    function removeLockerFromSession() {
        try {
            $.ajax({
                url: boxNowDeliverySettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bndp_clear_boxnow_locker',
                    nonce: boxNowDeliverySettings.nonce
                },
                success: function(response) {
                },
                error: function(xhr, status, error) {
                    console.warn("removeLockerFromSession response error: ", error);
                }
            });

        } catch (e) {
            console.error("removeLockerFromSession error: ", e);
        }
    }

    // Document ready event
    $(document).ready(function () {
        /**
         * Add validation for order placement to ensure locker selection.
         */
        function addOrderValidation() {
            $(document.body).on("click", "#place_order", function (event) {
                var lockerData = localStorage.getItem("box_now_selected_locker");

                if (
                    !lockerData &&
                    ($('input[type="radio"][name="shipping_method[0]"]:checked').val() ===
                        "box_now_delivery" ||
                        $('input[type="hidden"][name="shipping_method[0]"]').val() ===
                        "box_now_delivery")
                ) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    alert(
                        boxNowDeliverySettings.lockerNotSelectedMessage ||
                        "Please select a locker first!"
                    );
                    return false;
                }
            });
        }

        init();
        window.removeEventListener("message", handleBoxNowMessage); // Avoid multiple listeners via AJAX in some themes
        window.addEventListener("message", handleBoxNowMessage, false);

        // Show the selected locker details from localStorage
        showSelectedLockerDetailsFromLocalStorage();

        // Call init() function when the shipping method list is updated
        $(document.body).on("updated_checkout", function () {
            init();
        });

        // Call the toggle function when the shipping method changes
        $(document.body).on(
            "change",
            'input[type="radio"][name="shipping_method[0]"]',
            toggleBoxNowDelivery
        );

        addOrderValidation();
        
        // When shipping country changes clear selected locker from local storage and session
        $(document.body).on("change", "#shipping_country", function () {
            localStorage.removeItem("box_now_selected_locker");
            $("#box_now_selected_locker_details").hide().empty();
            removeLockerFromSession();
        });

        // When billing_country country changes and the user selected the option 
        // to ship to same address as billing, then procceed to clear selected 
        // locker from local storage and session
        $(document.body).on("change", "#billing_country", function () {
            if (!$('#ship-to-different-address-checkbox').is(":checked")) {
                localStorage.removeItem("box_now_selected_locker");
                $("#box_now_selected_locker_details").hide().empty();
                removeLockerFromSession();
            }
        });

        // When the user toggles the option to ship to different address,
        // procceed to clear selected locker from local storage and session
        $(document.body).on("change", "#ship-to-different-address-checkbox", function () {
            localStorage.removeItem("box_now_selected_locker");
            $("#box_now_selected_locker_details").hide().empty();
            removeLockerFromSession();
        });
    });
})(jQuery);
