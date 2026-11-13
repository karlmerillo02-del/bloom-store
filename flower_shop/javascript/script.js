document.addEventListener('DOMContentLoaded', function () {
    const dropdowns = document.querySelectorAll('.dropdown');

    function closeDropdown(dropdown) {
        dropdown.classList.remove('active');
        const button = dropdown.querySelector('.dropbtn');
        if (button) {
            button.setAttribute('aria-expanded', 'false');
        }
    }

    dropdowns.forEach(function (dropdown) {
        const button = dropdown.querySelector('.dropbtn');
        if (!button) {
            return;
        }

        button.addEventListener('click', function () {
            const shouldOpen = !dropdown.classList.contains('active');
            dropdowns.forEach(closeDropdown);
            if (shouldOpen) {
                dropdown.classList.add('active');
                button.setAttribute('aria-expanded', 'true');
            }
        });

        dropdown.querySelectorAll('.dropdown-content a').forEach(function (link) {
            link.addEventListener('click', function () {
                closeDropdown(dropdown);
            });
        });
    });

    document.addEventListener('click', function (event) {
        dropdowns.forEach(function (dropdown) {
            if (!dropdown.contains(event.target)) {
                closeDropdown(dropdown);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            dropdowns.forEach(function (dropdown) {
                const button = dropdown.querySelector('.dropbtn');
                const wasOpen = dropdown.classList.contains('active');
                closeDropdown(dropdown);
                if (wasOpen && button) {
                    button.focus();
                }
            });
        }
    });

    document.querySelectorAll('[data-confirm]').forEach(function (element) {
        element.addEventListener('click', function (event) {
            const message = element.getAttribute('data-confirm');
            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('.qty-input').forEach(function (input) {
        const form = input.closest('form');
        const control = input.closest('.quantity-control');
        if (!form || !control) {
            return;
        }

        const decrementButton = control.querySelector('.qty-decrement');
        const incrementButton = control.querySelector('.qty-increment');

        function normalizeQuantity(value) {
            const minimum = Number(input.min) || 1;
            const maximumValue = Number(input.max);
            const maximum = input.max !== '' && Number.isFinite(maximumValue)
                ? maximumValue
                : Infinity;
            return Math.min(maximum, Math.max(minimum, value));
        }

        function adjustQuantity(change) {
            const current = Number.parseInt(input.value, 10);
            const quantity = Number.isFinite(current) ? current : Number(input.min) || 1;
            input.value = String(normalizeQuantity(quantity + change));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (decrementButton) {
            decrementButton.addEventListener('click', function () {
                adjustQuantity(-1);
            });
        }

        if (incrementButton) {
            incrementButton.addEventListener('click', function () {
                adjustQuantity(1);
            });
        }

        input.addEventListener('change', function () {
            const quantity = Number.parseInt(input.value, 10);
            input.value = String(normalizeQuantity(Number.isFinite(quantity) ? quantity : Number(input.min) || 1));
        });
    });

    const customizationPanels = document.querySelectorAll('.cart-customization-panel');
    if (customizationPanels.length > 0) {
        const currency = function (amount) {
            return '₱' + amount.toFixed(2);
        };
        const cartTotalOutput = document.querySelector('[data-cart-total]');

        function updateCartEstimate() {
            let cartTotal = 0;
            customizationPanels.forEach(function (panel) {
                const line = panel.closest('[data-cart-line]');
                const quantityInput = line ? line.querySelector('.qty-input') : null;
                const stemsInput = panel.querySelector('input[name$="[extra_stems]"]');
                if (!line || !quantityInput || !stemsInput) {
                    return;
                }

                const stock = Number(panel.dataset.stock) || 0;
                const stems = Math.min(30, Math.max(0, Number.parseInt(stemsInput.value, 10) || 0));
                const maximumQuantity = Math.floor(stock / (1 + stems));
                quantityInput.max = String(Math.max(1, maximumQuantity));
                quantityInput.value = String(Math.min(
                    Math.max(1, Number.parseInt(quantityInput.value, 10) || 1),
                    Math.max(1, maximumQuantity)
                ));

                const basePrice = Number(panel.dataset.basePrice) || 0;
                const wrapPrice = Number(panel.dataset.wrapPrice) || 0;
                const cardPrice = Number(panel.dataset.cardPrice) || 0;
                const stemPrice = Number(panel.dataset.stemPrice) || 0;
                const unitPrice = basePrice
                    + (panel.querySelector('input[name$="[bouquet_wrap]"]').checked ? wrapPrice : 0)
                    + (panel.querySelector('input[name$="[greeting_card]"]').checked ? cardPrice : 0)
                    + (stems * stemPrice);
                const lineTotal = unitPrice * Number(quantityInput.value);
                const unitOutput = line.querySelector('[data-unit-price]');
                const lineOutput = line.querySelector('[data-line-total]');
                if (unitOutput) {
                    unitOutput.textContent = currency(unitPrice);
                }
                if (lineOutput) {
                    lineOutput.textContent = currency(lineTotal);
                }
                cartTotal += lineTotal;
            });

            if (cartTotalOutput) {
                cartTotalOutput.textContent = currency(cartTotal);
            }
        }

        customizationPanels.forEach(function (panel) {
            panel.addEventListener('input', updateCartEstimate);
            panel.addEventListener('change', updateCartEstimate);
        });
        document.querySelectorAll('.store-cart-line .qty-input').forEach(function (input) {
            input.addEventListener('input', updateCartEstimate);
            input.addEventListener('change', updateCartEstimate);
        });
        updateCartEstimate();
    }

    document.querySelectorAll('input[name="image_file"]').forEach(function (input) {
        const form = input.closest('form');
        const preview = form ? form.querySelector('[data-image-preview]') : null;
        const message = form ? form.querySelector('[data-image-preview-message]') : null;
        if (!preview) {
            return;
        }
        const currentImage = preview.dataset.currentSrc || '';
        let previewUrl = '';

        function restoreCurrentImage() {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = '';
            }
            if (!currentImage) {
                preview.hidden = true;
                return;
            }

            preview.onload = null;
            preview.onerror = null;
            if (preview.getAttribute('src') !== currentImage) {
                preview.src = currentImage;
            }
            preview.hidden = false;
        }

        input.addEventListener('change', function () {
            const file = input.files && input.files[0];
            if (!file) {
                restoreCurrentImage();
                if (message) {
                    message.textContent = '';
                }
                return;
            }

            if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type)) {
                input.value = '';
                restoreCurrentImage();
                if (message) {
                    message.textContent = 'Choose a JPG, PNG, WEBP, or GIF image.';
                }
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                input.value = '';
                restoreCurrentImage();
                if (message) {
                    message.textContent = 'Choose an image smaller than 5 MB.';
                }
                return;
            }

            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
            previewUrl = URL.createObjectURL(file);
            preview.hidden = false;
            preview.onload = function () {
                if (preview.src === previewUrl && message) {
                    message.textContent = 'Photo preview ready. It will be saved with the product.';
                }
            };
            preview.onerror = function () {
                if (preview.src !== previewUrl) {
                    return;
                }
                input.value = '';
                restoreCurrentImage();
                if (message) {
                    message.textContent = 'This photo could not be previewed. Choose a different image.';
                }
            };
            preview.src = previewUrl;
            if (message) {
                message.textContent = 'Loading photo preview…';
            }
        });
    });

    document.querySelectorAll('.alert-success').forEach(function (alert) {
        window.setTimeout(function () {
            if (alert.matches(':hover') || alert.contains(document.activeElement)) {
                return;
            }

            alert.classList.add('is-dismissing');
            window.setTimeout(function () {
                alert.remove();
            }, 250);
        }, 6000);
    });
});
