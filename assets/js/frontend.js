/**
 * Art Print Calculator Frontend JavaScript
 * Handles all interactive functionality for the product configurator
 */

jQuery(document).ready(function($) {
    'use strict';
    
    const ArtPrintCalculator = {
        // Current selections
        selectedSize: null,
        selectedProductType: 'print',
        selectedShippingType: 'rolled',
        selectedFrame: 1,
        currentUnit: 'in',
        
        // Price data
        prices: {},
        frames: {},
        
        // DOM elements
        $calculator: null,
        $sizeButtons: null,
        $productTypeButtons: null,
        $shippingTypeButtons: null,
        $frameOptions: null,
        $unitToggles: null,
        
        init: function() {
            this.$calculator = $('#art-print-calculator');
            
            if (this.$calculator.length === 0) {
                console.log('Art Print Calculator: Calculator element not found');
                return;
            }
            
            console.log('Art Print Calculator: Initializing...');
            this.cacheElements();
            this.bindEvents();
            this.loadFrameData();
            this.initializeDefaults();
        },
        
        cacheElements: function() {
            this.$sizeButtons = $('.size-option');
            this.$productTypeButtons = $('.product-type-option');
            this.$shippingTypeButtons = $('.shipping-type-option');
            this.$frameOptions = $('.frame-option');
            this.$unitToggles = $('.unit-toggle');
            
            console.log('Art Print Calculator: Found elements', {
                sizes: this.$sizeButtons.length,
                productTypes: this.$productTypeButtons.length,
                shippingTypes: this.$shippingTypeButtons.length,
                frames: this.$frameOptions.length,
                toggles: this.$unitToggles.length
            });
        },
        
        bindEvents: function() {
            // Size selection
            this.$sizeButtons.on('click', this.handleSizeSelection.bind(this));
            
            // Product type selection
            this.$productTypeButtons.on('click', this.handleProductTypeSelection.bind(this));
            
            // Shipping type UI removed; shipping is derived from frame selection
            
            // Frame selection
            this.$frameOptions.on('click', this.handleFrameSelection.bind(this));
            
            // Unit toggle
            this.$unitToggles.on('click', this.handleUnitToggle.bind(this));
            
            // Form submission
            $('form.cart').on('submit', this.handleFormSubmit.bind(this));

            // Quantity changes
            $(document).on('change input', 'form.cart input.qty', () => {
                this.updatePriceDisplay();
            });
        },
        
        loadFrameData: function() {
            const self = this;
            
            this.$frameOptions.each(function() {
                const $frame = $(this);
                const frameId = $frame.data('frame-id');
                const framePrice = $frame.data('frame-price');
                const shippingType = $frame.data('shipping-type') || (parseInt(frameId, 10) <= 1 ? 'rolled' : 'framed');
                
                self.frames[frameId] = {
                    id: frameId,
                    price: parseFloat(framePrice) || 0,
                    shippingType: String(shippingType),
                    element: $frame
                };
            });
            
            console.log('Art Print Calculator: Loaded frame data', this.frames);
        },
        
        initializeDefaults: function() {
            // Set default size (first available)
            const $firstSize = this.$sizeButtons.first();
            if ($firstSize.length) {
                this.selectSize($firstSize);
            }
            
            // Set default product type
            this.selectedProductType = 'print';
            $('#art_print_product_type').val('print');
            
            // Set default shipping type
            this.selectedShippingType = 'rolled';
            $('#art_print_shipping_type').val('rolled');
            
            // Set default frame
            this.selectedFrame = 1;
            
            // Calculate initial price
            this.updatePriceDisplay();
        },
        
        handleSizeSelection: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            this.selectSize($button);
        },
        
        selectSize: function($button) {
            // Update UI
            this.$sizeButtons.removeClass('active');
            $button.addClass('active');
            
            // Store selection
            this.selectedSize = $button.data('size');
            
            // Store price data
            this.prices = {
                print: parseFloat($button.data('price-print')),
                painted: parseFloat($button.data('price-painted')),
                shipping_rolled: parseFloat($button.data('shipping-rolled')),
                shipping_stretcher: parseFloat($button.data('shipping-stretcher')),
                shipping_framed: parseFloat($button.data('shipping-framed')),
                dimensionsIn: $button.data('dimensions-in'),
                dimensionsCm: $button.data('dimensions-cm')
            };
            
            console.log('Art Print Calculator: Size selected', this.selectedSize, this.prices);
            
            // Update form inputs
            $('#art_print_size').val(this.selectedSize);
            $('#art_print_dimensions').val(this.getCurrentDimensions());
            
            // Update price display
            this.updatePriceDisplay();
        },
        
        handleProductTypeSelection: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            
            // Update UI
            this.$productTypeButtons.removeClass('active');
            $button.addClass('active');
            
            // Store selection
            this.selectedProductType = $button.data('type');
            
            console.log('Art Print Calculator: Product type selected', this.selectedProductType);
            
            // Update form input
            $('#art_print_product_type').val(this.selectedProductType);
            
            // Update price display
            this.updatePriceDisplay();
        },
        
        // Shipping type selection removed
        
        handleFrameSelection: function(e) {
            e.preventDefault();
            const $option = $(e.currentTarget);
            
            // Update UI
            this.$frameOptions.removeClass('active');
            $option.addClass('active');
            
            // Store selection
            this.selectedFrame = $option.data('frame-id');
            
            console.log('Art Print Calculator: Frame selected', this.selectedFrame);
            
            // Update form input
            $('#art_print_frame').val(this.selectedFrame);
            // Derive shipping from selected frame's shippingType
            const frameData = this.frames[this.selectedFrame];
            this.selectedShippingType = frameData && frameData.shippingType ? frameData.shippingType : ((parseInt(this.selectedFrame, 10) <= 1) ? 'rolled' : 'framed');
            $('#art_print_shipping_type').val(this.selectedShippingType);
            
            // Update price display
            this.updatePriceDisplay();
        },
        
        handleUnitToggle: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const unit = $button.data('unit');
            
            // Update UI
            this.$unitToggles.removeClass('active');
            $button.addClass('active');
            
            // Store selection
            this.currentUnit = unit;
            
            // Toggle dimension display
            if (unit === 'cm') {
                $('.size-inches').hide();
                $('.size-cm').show();
            } else {
                $('.size-cm').hide();
                $('.size-inches').show();
            }
        },
        
        getCurrentDimensions: function() {
            if (!this.prices) return '';
            
            return this.currentUnit === 'cm' 
                ? this.prices.dimensionsCm 
                : this.prices.dimensionsIn;
        },
        
        updatePriceDisplay: function() {
            if (!this.selectedSize) {
                console.log('Art Print Calculator: Cannot update price - missing size');
                return;
            }
            
            // Use AJAX to get dynamic pricing
            this.calculatePriceAjax();
        },
        
        getFramePrice: function() {
            if (!this.frames[this.selectedFrame]) {
                return 0;
            }
            
            return this.frames[this.selectedFrame].price;
        },
        
        formatPrice: function(amount) {
            if (typeof app_ajax !== 'undefined' && app_ajax.currency_symbol) {
                return app_ajax.currency_symbol + parseFloat(amount).toFixed(2);
            }
            return '$' + parseFloat(amount).toFixed(2);
        },
        
        updateAddToCartButton: function(totalPrice) {
            const $button = $('.single_add_to_cart_button');
            const originalText = $button.data('original-text') || $button.text();
            
            if (!$button.data('original-text')) {
                $button.data('original-text', originalText);
            }
            
            const newText = originalText + ' - ' + this.formatPrice(totalPrice);
            $button.text(newText);
        },
        
        handleFormSubmit: function(e) {
            console.log('[ArtPrint] Add to cart button clicked');
            console.log('[ArtPrint] Current values:', {
                selectedSize: this.selectedSize,
                selectedProductType: this.selectedProductType,
                selectedShippingType: this.selectedShippingType,
                selectedFrame: this.selectedFrame,
                art_print_size: $('#art_print_size').val(),
                art_print_product_type: $('#art_print_product_type').val(),
                art_print_shipping_type: $('#art_print_shipping_type').val(),
                art_print_frame: $('#art_print_frame').val()
            });
            if (this.$calculator.length === 0) {
                return true;
            }
            if (this.$sizeButtons.length === 0) {
                return true;
            }
            // Defensive: If no size is selected, auto-select the first one
            if (!this.selectedSize && this.$sizeButtons.length > 0) {
                this.selectSize(this.$sizeButtons.first());
            }
            if (!this.selectedSize) {
                e.preventDefault();
                this.showError('Please select a size before adding to cart.');
                return false;
            }
            
            // Show loading state
            this.showLoading();
            
            // Let the form submit normally
            return true;
        },
        
        showError: function(message) {
            // Remove existing errors
            $('.art-print-error').remove();
            
            // Add new error
            const $error = $('<div class="woocommerce-error art-print-error">' + message + '</div>');
            this.$calculator.before($error);
            
            // Scroll to error
            $('html, body').animate({
                scrollTop: $error.offset().top - 100
            }, 500);
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                $error.fadeOut(500, function() {
                    $error.remove();
                });
            }, 5000);
        },
        
        showLoading: function() {
            this.$calculator.addClass('loading');
            $('.single_add_to_cart_button').prop('disabled', true);
        },
        
        hideLoading: function() {
            this.$calculator.removeClass('loading');
            $('.single_add_to_cart_button').prop('disabled', false);
        },
        
        // AJAX methods for dynamic price calculation
        calculatePriceAjax: function() {
            const self = this;
            
            if (!this.selectedSize) {
                return;
            }
            
            const data = {
                action: 'calculate_art_price',
                nonce: app_ajax.nonce,
                product_id: this.getProductId(),
                size: this.selectedSize,
                frame_id: this.selectedFrame,
                product_type: this.selectedProductType,
                quantity: parseInt($('form.cart').find('input.qty').val(), 10) || 1
            };
            
            $.ajax({
                url: app_ajax.ajax_url,
                type: 'POST',
                data: data,
                beforeSend: function() {
                    self.showLoading();
                },
                success: function(response) {
                    if (response.success) {
                        self.updatePriceFromAjax(response.data);
                    } else {
                        self.showError(response.data.message || 'Error calculating price');
                    }
                },
                error: function() {
                    self.showError('Error communicating with server');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },
        
        updatePriceFromAjax: function(data) {
            const qty = parseInt($('form.cart').find('input.qty').val(), 10) || 1;
            const baseTotal = parseFloat(data.base_price) * qty;
            const frameTotal = parseFloat(data.frame_cost) * qty;
            const shippingTotal = parseFloat(data.shipping_cost); // already line-level
            const grandTotal = parseFloat(data.total_price); // already line-level

            $('#base-price').html(this.formatPrice(baseTotal));
            $('#frame-price').html(this.formatPrice(frameTotal));
            $('#shipping-cost').html(this.formatPrice(shippingTotal));
            $('#total-price').html(this.formatPrice(grandTotal));
            
            // Show/hide frame price line
            if (data.frame_cost > 0) {
                $('.frame-price-line').show();
            } else {
                $('.frame-price-line').hide();
            }
            
            // Update form inputs
            var subtotalWithoutShipping = (parseFloat(data.base_price) + parseFloat(data.frame_cost)) * qty;
            $('#art_print_price').val(subtotalWithoutShipping);
            // pass shipping separately for cart/checkout fee (line-level)
            if ($('#art_print_shipping').length) {
                $('#art_print_shipping').val(data.shipping_cost);
            }
            $('#art_print_dimensions').val(data.dimensions);
            if ($('#art_print_unit_weight').length && typeof data.weight_kg !== 'undefined') {
                $('#art_print_unit_weight').val(data.weight_kg);
            }
            
            // Update add to cart button
            this.updateAddToCartButton(data.total_price);
        },
        
        getProductId: function() {
            // Try to get product ID from various sources
            const $productForm = $('form.cart');
            let productId = $productForm.find('input[name="add-to-cart"]').val();
            
            if (!productId) {
                productId = $productForm.find('button[name="add-to-cart"]').val();
            }
            
            if (!productId && window.wc_single_product_params) {
                productId = window.wc_single_product_params.post_id;
            }
            
            return productId || 0;
        }
    };
    
    // Initialize the calculator
    ArtPrintCalculator.init();
    
    // Handle dynamic content loading (for themes that load content via AJAX)
    $(document).on('wc_fragments_refreshed wc_fragments_loaded', function() {
        ArtPrintCalculator.init();
    });
    
    // Handle variation changes (if used with variable products)
    $(document).on('show_variation hide_variation', function() {
        setTimeout(function() {
            ArtPrintCalculator.updatePriceDisplay();
        }, 100);
    });
    
    // Export for external access
    window.ArtPrintCalculator = ArtPrintCalculator;
});