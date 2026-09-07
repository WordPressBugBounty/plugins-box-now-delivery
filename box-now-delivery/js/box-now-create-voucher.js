document.addEventListener("DOMContentLoaded", function () {
    const createVouchersEnabled = document.getElementById(
        "create_vouchers_enabled"
    );

    const createVouchersBtnSmall = document.getElementById(
        "box_now_create_voucher_small"
    );
    const createVouchersBtnMedium = document.getElementById(
        "box_now_create_voucher_medium"
    );
    const createVouchersBtnLarge = document.getElementById(
        "box_now_create_voucher_large"
    );

    const buttons = [
        createVouchersBtnSmall,
        createVouchersBtnMedium,
        createVouchersBtnLarge,
    ];
    const sizes = ["small", "medium", "large"];

    // Create the sizeMapping object
    const sizeMapping = {
        small: 1,
        medium: 2,
        large: 3,
    };

    for (let i = 0; i < buttons.length; i++) {
        let button = buttons[i];
        let size = sizes[i];

        // Check if button exists and enabled
	        if (!button || !createVouchersEnabled || createVouchersEnabled.value !== "true") {
            if (button) button.disabled = true;
            continue;
        }

        button.disabled = false;

        button.addEventListener("click", function () {
            const order_id = document.getElementById("box_now_order_id").value;
            const voucher_quantity = document.getElementById(
                "box_now_voucher_code"
            ).value;
            const max_vouchers = parseInt(
                document.getElementById("max_vouchers").value,
                10
            );

            if (!order_id || !voucher_quantity) {
                alert("Please provide the required data.");
                return;
            }

            if (voucher_quantity > max_vouchers) {
                alert(
                    "The number of vouchers you want to create is larger than the max vouchers number."
                );
                return;
            }

            // Disable the button immediately after it is clicked
            button.disabled = true;

            // Make an AJAX call to your existing function
            const data = {
                action: "create_box_now_vouchers",
                order_id: order_id,
                voucher_quantity: voucher_quantity,
                compartment_size: sizeMapping[size], // Send the selected compartment size
                security: boxNowDeliveryAjax.nonce,
            };

            jQuery.post(
                boxNowDeliveryAjax.ajaxurl,
                data,
                function (response) {
                    button.disabled = false;

                    if (response.success) {
                        if (response.data && response.data.new_parcel_ids) {
                            const parcelIds = response.data.new_parcel_ids;
                            const boxNowParcelIdsEl =
                                document.getElementById("box_now_parcel_ids");
                            if (boxNowParcelIdsEl) {
                                boxNowParcelIdsEl.value = JSON.stringify(parcelIds);
                            }

                            // Display the parcel ID links
                            displayParcelIdLinks(parcelIds);

                            // Disable all buttons after creating the vouchers
                            buttons.forEach((btn) => btn && (btn.disabled = true));
                        } else {
                            alert(
                                "Error: New parcel IDs are not available in the response data."
                            );
                            button.disabled = false; // Re-enable the button if there is an error
                        }
                    } else {
                        alert("Error: " + response.data);
                        button.disabled = false; // Re-enable the button if there is an error
                    }
                },
                "json"
            );
        });
    }

	    const boxNowParcelIdsEl = document.getElementById("box_now_parcel_ids");
	    if (boxNowParcelIdsEl) {
	        const parcelIds = getStoredParcelIds();
	        displayParcelIdLinks(parcelIds);

	        // Enable or disable the button based on whether there are vouchers created for the order
	        setCreateVoucherButtonsDisabled(parcelIds.length > 0);
	    }

	    const cancelAllVouchersButton = document.getElementById("box_now_cancel_all_vouchers");
	    if (cancelAllVouchersButton) {
	        cancelAllVouchersButton.addEventListener("click", handleCancelAllVouchersClick);
	        updateCancelAllVouchersButton(getStoredParcelIds());
	    }
	});

	function getCreateVoucherButtons() {
	    return [
	        document.getElementById("box_now_create_voucher_small"),
	        document.getElementById("box_now_create_voucher_medium"),
	        document.getElementById("box_now_create_voucher_large"),
	    ];
	}

	function setCreateVoucherButtonsDisabled(disabled) {
	    getCreateVoucherButtons().forEach((btn) => {
	        if (btn) {
	            btn.disabled = disabled;
	        }
	    });
	}

	function getStoredParcelIds() {
	    const boxNowParcelIdsEl = document.getElementById("box_now_parcel_ids");
	    if (!boxNowParcelIdsEl) {
	        return [];
	    }

	    try {
	        const parcelIds = JSON.parse(boxNowParcelIdsEl.value || "[]");
	        return Array.isArray(parcelIds) ? parcelIds.filter(Boolean) : [];
	    } catch (error) {
	        console.warn("Invalid BOX NOW parcel ID list:", error);
	        return [];
	    }
	}

	function setStoredParcelIds(parcelIds) {
	    const boxNowParcelIdsEl = document.getElementById("box_now_parcel_ids");
	    if (boxNowParcelIdsEl) {
	        boxNowParcelIdsEl.value = JSON.stringify(parcelIds || []);
	    }
	}

	function updateCancelAllVouchersButton(parcelIds) {
	    const cancelAllVouchersButton = document.getElementById("box_now_cancel_all_vouchers");
	    if (!cancelAllVouchersButton) {
	        return;
	    }

	    const hasParcelIds = Array.isArray(parcelIds) && parcelIds.length > 0;
	    cancelAllVouchersButton.style.display = hasParcelIds ? "" : "none";
	    cancelAllVouchersButton.disabled = !hasParcelIds;
	}

	function escapeHtml(value) {
	    const div = document.createElement("div");
	    div.textContent = value;
	    return div.innerHTML;
	}

	function escapeAttribute(value) {
	    return escapeHtml(value).replace(/"/g, "&quot;");
	}

	function displayParcelIdLinks(parcelIds) {
	    const pdfLinkContainer = document.getElementById("box_now_voucher_link");
	    parcelIds = Array.isArray(parcelIds) ? parcelIds.filter(Boolean) : [];
	    updateCancelAllVouchersButton(parcelIds);

	    if (!pdfLinkContainer) {
	        return;
	    }

	    pdfLinkContainer.innerHTML = ""; // Clear the container

	    parcelIds.forEach((parcelId) => {
	        const orderId = document.getElementById("box_now_order_id").value;
	        const trackingUrl = `https://t.boxnow.gr/?track=${encodeURIComponent(parcelId)}`;
	        const escapedParcelId = escapeHtml(parcelId);
	        const escapedParcelIdAttribute = escapeAttribute(parcelId);

	        const newLinkHtml = `
	      <div class="box-now-voucher-actions">
	        <button class="button parcel-id-link box-now-link box-now-admin-action box-now-admin-action--voucher dashicons-before dashicons-printer" data-parcel-id="${escapedParcelIdAttribute}" type="button">Print ${escapedParcelId}</button>
	        <button type="button" class="button cancel-voucher-btn box-now-admin-action box-now-admin-action--danger dashicons-before dashicons-no-alt" data-order-id="${orderId}">Cancel</button>
	        <a class="button box-now-track-btn box-now-admin-action box-now-admin-action--track dashicons-before dashicons-location-alt" href="${trackingUrl}" target="_blank" rel="noopener noreferrer">Track</a>
	      </div>`;

        pdfLinkContainer.innerHTML += newLinkHtml;
    });

    if (pdfLinkContainer.dataset.boxNowVoucherHandlersBound === "true") {
        return;
    }

    pdfLinkContainer.dataset.boxNowVoucherHandlersBound = "true";

    // Event delegation for parcelIdLinks and cancelButtons
    pdfLinkContainer.addEventListener("click", function (event) {
        if (event.target.matches(".parcel-id-link")) {
            event.preventDefault();
            const parcelId = event.target.getAttribute("data-parcel-id");
            const orderId = document.getElementById("box_now_order_id").value;
            window.open(
                boxNowDeliveryAjax.ajaxurl + "?action=print_box_now_voucher&parcel_id=" + parcelId + "&order_id=" + orderId + "&security=" + boxNowDeliveryAjax.nonce,
                "_blank",
                "noopener,noreferrer"
            );
        }

        if (event.target.matches(".cancel-voucher-btn")) {
            handleCancelVoucherClick(event);
        }
    });
}

function handleCancelVoucherClick(event) {
    event.preventDefault();

    const orderId = event.target.getAttribute("data-order-id");
    const parcelId =
        event.target.previousElementSibling.getAttribute("data-parcel-id");

    const nonce = boxNowDeliveryAjax.nonce;

    const data = {
        action: "cancel_voucher",
        order_id: orderId,
        parcel_id: parcelId,
        nonce: nonce,
    };

    jQuery.post(
        boxNowDeliveryAjax.ajaxurl,
        data,
        function (response) {
	            if (response.success) {
	                let canceledParcelId = response.data;
	                const parcelIds = getStoredParcelIds();
	                const index = parcelIds.indexOf(canceledParcelId);
	                if (index !== -1) {
	                    parcelIds.splice(index, 1);
	                }
	                setStoredParcelIds(parcelIds);
	                updateCancelAllVouchersButton(parcelIds);
	                setCreateVoucherButtonsDisabled(parcelIds.length > 0);
	                location.reload();
	            } else {
	                console.error("Error canceling voucher:", response.data);
                alert("Error canceling voucher: " + response.data);
            }
        },
	        "json"
	    );
	}

	function handleCancelAllVouchersClick(event) {
	    event.preventDefault();

	    const button = event.currentTarget;
	    const orderId = button.getAttribute("data-order-id");
	    const parcelIds = getStoredParcelIds();

	    if (!orderId || parcelIds.length === 0) {
	        alert("No BOX NOW vouchers were found for this order.");
	        return;
	    }

	    if (
	        !window.confirm(
	            `Cancel all ${parcelIds.length} BOX NOW voucher(s) for this order? This cannot be undone.`
	        )
	    ) {
	        return;
	    }

	    button.disabled = true;

	    const data = {
	        action: "cancel_all_vouchers",
	        order_id: orderId,
	        nonce: boxNowDeliveryAjax.nonce,
	    };

	    jQuery.post(
	        boxNowDeliveryAjax.ajaxurl,
	        data,
	        function (response) {
	            if (response.success) {
	                const responseData = response.data || {};
	                const remainingParcelIds = responseData.remaining_parcel_ids || [];
	                const failedCancellations = responseData.failed_cancellations || [];

	                setStoredParcelIds(remainingParcelIds);
	                displayParcelIdLinks(remainingParcelIds);
	                setCreateVoucherButtonsDisabled(remainingParcelIds.length > 0);

	                if (failedCancellations.length > 0) {
	                    alert(
	                        "Some BOX NOW vouchers could not be cancelled: " +
	                        failedCancellations.join(", ")
	                    );
	                }

	                location.reload();
	            } else {
	                console.error("Error canceling all vouchers:", response.data);
	                alert("Error canceling all vouchers: " + response.data);
	                button.disabled = false;
	            }
	        },
	        "json"
	    );
	}
