<?php
/**
 * Frontend Display Class
 * Handles the frontend product display similar to ArtByMaudsch.com design
 */

if (!defined('ABSPATH')) {
    exit;
}

class APP_Frontend_Display {
    
    private $calculator_already_displayed = false;
    
    public function __construct() {
        // Add custom product options after single product summary
        // add_action('woocommerce_single_product_summary', array($this, 'display_art_print_options'), 25);
        add_action('woocommerce_before_add_to_cart_button', array($this, 'display_art_print_options'), 5);
        
        // Alternative hook if the above doesn't work
        // add_action('woocommerce_after_single_product_summary', array($this, 'display_art_print_options_alternative'), 15);
        
        // Shortcode for manual placement
        // add_shortcode('art_print_calculator', array($this, 'calculator_shortcode'));
        
        // Modify add to cart functionality
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 10, 3);
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_order_item_data'), 10, 4);
        
        // Update product price dynamically
        add_action('woocommerce_before_calculate_totals', array($this, 'update_cart_item_price'));
        // Add shipping fees derived from line meta
        add_action('woocommerce_cart_calculate_fees', array($this, 'add_cart_shipping_fees'), 20, 1);
        
        // Remove default price display and add custom
        add_filter('woocommerce_get_price_html', array($this, 'custom_price_html'), 10, 2);
    }
    
    public function display_art_print_options_alternative() {
        global $product;
        
        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }
        
        $product_id = $product->get_id();
        
        // Only show if main display didn't show
        if (!$this->calculator_already_displayed) {
            $this->display_art_print_options();
            $this->calculator_already_displayed = true;
        }
    }
    
    public function calculator_shortcode($atts) {
        $atts = shortcode_atts(array(
            'product_id' => get_the_ID()
        ), $atts);
        
        if (!$atts['product_id']) {
            return '<p>Error: Product ID required for art print calculator.</p>';
        }
        
        $product = wc_get_product($atts['product_id']);
        if (!$product) {
            return '<p>Error: Product not found.</p>';
        }
        
        // Temporarily set global product
        global $product;
        $old_product = $product;
        $GLOBALS['product'] = wc_get_product($atts['product_id']);
        
        ob_start();
        $this->display_art_print_options();
        $output = ob_get_clean();
        
        // Restore global product
        $GLOBALS['product'] = $old_product;
        
        return $output;
    }
    
    public function display_art_print_options() {
        global $product;
        
        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }
        
        $product_id = $product->get_id();
        
        // Check if this product should show art print options
        if (!$this->should_show_art_print_options($product_id)) {
            return;
        }
        
        $calculated_prices = get_post_meta($product_id, '_calculated_prices', true);
        
        // If no calculated prices, try to calculate them now
        if (empty($calculated_prices)) {
            $calculator = new APP_Product_Calculator();
            $calculator->update_product_prices($product_id);
            $calculated_prices = get_post_meta($product_id, '_calculated_prices', true);
        }
        
        // If still no prices, show a message for admin users
        if (empty($calculated_prices)) {
            if (current_user_can('manage_woocommerce')) {
                echo '<div class="woocommerce-info">Art Print Calculator: No image dimensions found. Please upload a product image and the pricing will be calculated automatically.</div>';
            }
            return;
        }
        
        // Get frames
        global $wpdb;
        $frames = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}art_print_frames WHERE active = 1 ORDER BY sort_order ASC"
        );
        
        ?>
        <div id="art-print-calculator" class="art-print-options">
            <h3><?php _e('Select Options', 'art-print-pricing'); ?></h3>
            
            <!-- Size Selection -->
            <div class="option-group size-selection">
                <label class="option-label"><?php _e('Select Size:', 'art-print-pricing'); ?></label>
                <div class="size-toggle">
                    <button type="button" class="unit-toggle active" data-unit="in"><?php _e('in', 'art-print-pricing'); ?></button>
                    <button type="button" class="unit-toggle" data-unit="cm"><?php _e('cm', 'art-print-pricing'); ?></button>
                </div>
                <div class="size-buttons">
                    <?php 
                    $first = true;
                    // Respect Available Print Sizes setting when displaying buttons
                    $sizes_option = APP_Admin_Settings::get_option('available_sizes', '20,24,32,40,48');
                    $allowed_sizes = array_filter(array_map('intval', array_map('trim', explode(',', (string) $sizes_option))));
                    foreach ($calculated_prices as $size => $data): 
                        if (!empty($allowed_sizes) && !in_array((int) $size, $allowed_sizes, true)) { continue; }
                    ?>
                    <button type="button" 
                            class="size-option <?php echo $first ? 'active' : ''; ?>" 
                            data-size="<?php echo esc_attr($size); ?>"
                            data-price-print="<?php echo esc_attr($data['print_price']); ?>"
                            data-price-painted="<?php echo esc_attr($data['painted_price']); ?>"
                            data-shipping-rolled="<?php echo esc_attr($data['shipping_rolled']); ?>"
                            data-shipping-stretcher="<?php echo esc_attr($data['shipping_stretcher']); ?>"
                            data-shipping-framed="<?php echo esc_attr($data['shipping_framed']); ?>"
                            data-dimensions-in="<?php echo esc_attr($data['dimensions_inches']); ?>"
                            data-dimensions-cm="<?php echo esc_attr($data['dimensions_cm']); ?>">
                        <span class="size-inches"><?php echo esc_html($data['dimensions_inches']); ?> in</span>
                        <span class="size-cm" style="display:none;"><?php echo esc_html($data['dimensions_cm']); ?> cm</span>
                    </button>
                    <?php 
                    $first = false;
                    endforeach; 
                    ?>
                </div>
            </div>
            
            <!-- Product Type Selection -->
            <div class="option-group product-type-selection">
                <label class="option-label"><?php _e('Product Type:', 'art-print-pricing'); ?></label>
                <div class="product-type-buttons">
                    <button type="button" class="product-type-option active" data-type="print">
                        <?php _e('Print', 'art-print-pricing'); ?>
                    </button>
                    <button type="button" class="product-type-option" data-type="painting">
                        <?php _e('Painting', 'art-print-pricing'); ?>
                    </button>
                </div>
            </div>

            <!-- Shipping derived from frame selection; explicit shipping UI removed -->
            
            <!-- Frame Selection -->
            <div class="option-group frame-selection">
                <label class="option-label"><?php _e('Frame:', 'art-print-pricing'); ?></label>
                <div class="frame-options">
                    <?php 
                    $first_frame = true;
                    foreach ($frames as $frame): 
                    ?>
                    <div class="frame-option <?php echo $first_frame ? 'active' : ''; ?>" 
                         data-frame-id="<?php echo esc_attr($frame->id); ?>"
                         data-frame-price="<?php echo esc_attr($frame->price); ?>"
                         data-shipping-type="<?php echo esc_attr(isset($frame->shipping_type) ? $frame->shipping_type : ($frame->id == 1 ? 'rolled' : 'framed')); ?>">
                        <?php if ($frame->image_url): ?>
                        <img src="<?php echo esc_url($frame->image_url); ?>" 
                             alt="<?php echo esc_attr($frame->name); ?>" 
                             class="frame-image">
                        <?php else: ?>
                        <div class="frame-placeholder">
                            <span class="frame-icon"><?php echo $frame->id == 1 ? '📄' : '🖼️'; ?></span>
                        </div>
                        <?php endif; ?>
                        <span class="frame-name"><?php echo esc_html($frame->name); ?></span>
                        <?php if ($frame->price > 0): ?>
                        <span class="frame-price">+<?php echo wc_price($frame->price); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php 
                    $first_frame = false;
                    endforeach; 
                    ?>
                </div>
            </div>
            
            <!-- Price Display -->
            <div class="price-summary">
                <div class="price-breakdown">
                    <div class="price-line base-price-line">
                        <span class="price-label"><?php _e('Base Price:', 'art-print-pricing'); ?></span>
                        <span class="price-value" id="base-price"><?php echo wc_price(0); ?></span>
                    </div>
                    <div class="price-line frame-price-line" style="display:none;">
                        <span class="price-label"><?php _e('Frame:', 'art-print-pricing'); ?></span>
                        <span class="price-value" id="frame-price"><?php echo wc_price(0); ?></span>
                    </div>
                    <div class="price-line shipping-line">
                        <span class="price-label"><?php _e('Shipping Cost:', 'art-print-pricing'); ?></span>
                        <span class="price-value" id="shipping-cost"><?php echo wc_price(0); ?></span>
                    </div>
                </div>
                <div class="total-price">
                    <span class="total-label"><?php _e('Total Price:', 'art-print-pricing'); ?></span>
                    <span class="total-value" id="total-price"><?php echo wc_price(0); ?></span>
                </div>
            </div>
            
            <!-- Hidden inputs for form submission -->
            <input type="hidden" name="art_print_size" id="art_print_size" value="<?php echo esc_attr(array_key_first($calculated_prices)); ?>">
            <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product_id); ?>">
            <input type="hidden" name="art_print_product_type" id="art_print_product_type" value="print">
            <input type="hidden" name="art_print_shipping_type" id="art_print_shipping_type" value="rolled">
            <input type="hidden" name="art_print_frame" id="art_print_frame" value="1">
            <input type="hidden" name="art_print_price" id="art_print_price" value="">
            <input type="hidden" name="art_print_shipping" id="art_print_shipping" value="">
            <input type="hidden" name="art_print_unit_weight" id="art_print_unit_weight" value="">
            <input type="hidden" name="art_print_dimensions" id="art_print_dimensions" value="">
            
            <script type="text/javascript">
            // Auto-select first size if none selected (for testing)
            jQuery(document).ready(function($) {
                setTimeout(function() {
                    if (!$('#art_print_size').val() && $('.size-option').length > 0) {
                        $('.size-option').first().trigger('click');
                    }
                }, 500);
            });
            </script>
            
            <!-- Product Info -->
            <div class="product-info">
                <h4><?php _e('Product Information', 'art-print-pricing'); ?></h4>
                <ul class="info-list">
                    <li><strong><?php _e('Printer Epson SureColor, 12 heads, 1200 DPI | Best color rendering of the market.', 'art-print-pricing'); ?></strong></li>
                    <li><?php _e('Art print on matte paper 230 GSM.', 'art-print-pricing'); ?></li>
                    <li><?php _e('Photo printing on glossy paper 230 GSM.', 'art-print-pricing'); ?></li>
                    <li><?php _e('Canvas print on 320 GSM canvas with an extra UV protection varnish.', 'art-print-pricing'); ?></li>
                    <li><?php _e('Oil painting on canvas 100% handmade with oil paint.', 'art-print-pricing'); ?></li>
                </ul>
            </div>
        </div>
        
        <script type="application/ld+json">
        {
          "@context": "https://schema.org/",
          "@type": "Product",
          "name": "<?php echo esc_js(get_the_title($product_id)); ?>",
          "image": "<?php echo esc_url(wp_get_attachment_url(get_post_thumbnail_id($product_id))); ?>",
          "description": "<?php echo esc_js(get_the_title($product_id)); ?> art print on canvas, high quality",
          "sku": "SKU-<?php echo esc_js($product_id); ?>",
          "mpn": "<?php echo esc_js($product_id); ?>",
          "brand": {
            "@type": "Brand",
            "name": "Art Print Shop"
          },
          "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.6",
            "reviewCount": "1322"
          },
          "offers": {
            "@type": "Offer",
            "url": "<?php echo esc_url(get_permalink($product_id)); ?>",
            "priceCurrency": "<?php echo esc_js(get_woocommerce_currency()); ?>",
            "price": "<?php echo esc_js($product->get_price()); ?>",
            "itemCondition": "https://schema.org/NewCondition",
            "availability": "http://schema.org/InStock"
          }
        }
        </script>
        <?php
    }
    
    public function validate_add_to_cart($passed, $product_id, $quantity) {
        error_log('[ArtPrint] validate_add_to_cart called. POST: ' . print_r($_POST, true));
        // Only validate if this is an art print product with our calculator
        if (!$this->should_show_art_print_options($product_id)) {
            return $passed;
        }
        
        // Check if art print options are submitted
        if (!isset($_POST['art_print_size']) || empty($_POST['art_print_size'])) {
            error_log('[ArtPrint] Validation failed: art_print_size missing or empty.');
        wc_add_notice(__('Please select a size before adding to cart.', 'art-print-pricing'), 'error');
            return false;
        }
        
        if (!isset($_POST['art_print_product_type']) || empty($_POST['art_print_product_type'])) {
            error_log('[ArtPrint] Validation failed: art_print_product_type missing or empty.');
            wc_add_notice(__('Please select a product type before adding to cart.', 'art-print-pricing'), 'error');
            return false;
        }
        
        // Shipping method selection removed; shipping is derived from frame selection
        
        return $passed;
    }
    
    private function should_show_art_print_options($product_id) {
        // Check if product has an image
        $has_image = has_post_thumbnail($product_id);
        if (!$has_image) {
            return false;
        }
        
        // Check settings to see if this category is supported
        $supported_categories = APP_Admin_Settings::get_option('supported_categories', array());
        
        if (!empty($supported_categories)) {
            $product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
            $has_supported_category = array_intersect($product_categories, $supported_categories);
            if (empty($has_supported_category)) {
                return false;
            }
        }
        
        // Check if auto calculation is enabled
        $auto_calc_enabled = APP_Admin_Settings::get_option('enable_auto_calculation', true);
        if (!$auto_calc_enabled) {
            // Check if manual prices have been set
            $manual_prices = get_post_meta($product_id, '_calculated_prices', true);
            if (empty($manual_prices)) {
                return false;
            }
        }
        
        return true;
    }
    
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        // Only add data if this is an art print product and data exists
        if (isset($_POST['art_print_size']) && !empty($_POST['art_print_size'])) {
            $cart_item_data['art_print_size'] = sanitize_text_field($_POST['art_print_size']);
            $cart_item_data['art_print_product_type'] = sanitize_text_field($_POST['art_print_product_type'] ?? 'print');
            // Derive shipping type from selected frame's configured shipping_type
            $frame_val = isset($_POST['art_print_frame']) ? intval($_POST['art_print_frame']) : 1;
            $derived_shipping = 'rolled';
            if ($frame_val > 0) {
                global $wpdb;
                $frame_row = $wpdb->get_row($wpdb->prepare(
                    "SELECT shipping_type FROM {$wpdb->prefix}art_print_frames WHERE id = %d",
                    $frame_val
                ));
                if ($frame_row && !empty($frame_row->shipping_type)) {
                    $derived_shipping = $frame_row->shipping_type;
                } else {
                    $derived_shipping = ($frame_val > 1) ? 'framed' : 'rolled';
                }
            }
            $cart_item_data['art_print_shipping_type'] = $derived_shipping;
            $cart_item_data['art_print_frame'] = (string) $frame_val;
            $cart_item_data['art_print_price'] = floatval($_POST['art_print_price'] ?? 0);
            $cart_item_data['art_print_shipping_cost'] = isset($_POST['art_print_shipping']) ? floatval($_POST['art_print_shipping']) : 0;
            $cart_item_data['art_print_dimensions'] = sanitize_text_field($_POST['art_print_dimensions'] ?? '');
            $cart_item_data['art_print_unit_weight'] = isset($_POST['art_print_unit_weight']) ? (float) $_POST['art_print_unit_weight'] : 0;
            
            // Make each cart item unique
            $cart_item_data['unique_key'] = md5(microtime() . rand());
        }
        
        return $cart_item_data;
    }
    
    public function display_cart_item_data($item_data, $cart_item) {
        if (isset($cart_item['art_print_size'])) {
            $item_data[] = array(
                'key' => __('Size', 'art-print-pricing'),
                'value' => $cart_item['art_print_dimensions']
            );
            
            $item_data[] = array(
                'key' => __('Product Type', 'art-print-pricing'),
                'value' => ucfirst($cart_item['art_print_product_type'])
            );
            
            // Shipping method is implicitly derived from frame; omit separate display
            
            if ($cart_item['art_print_frame'] > 1) {
                global $wpdb;
                $frame = $wpdb->get_row($wpdb->prepare(
                    "SELECT name FROM {$wpdb->prefix}art_print_frames WHERE id = %d",
                    $cart_item['art_print_frame']
                ));
                
                if ($frame) {
                    $item_data[] = array(
                        'key' => __('Frame', 'art-print-pricing'),
                        'value' => $frame->name
                    );
                }
            }
        }
        
        return $item_data;
    }
    
    public function save_order_item_data($item, $cart_item_key, $values, $order) {
        if (isset($values['art_print_size'])) {
            $item->add_meta_data(__('Size', 'art-print-pricing'), $values['art_print_dimensions']);
            $item->add_meta_data(__('Product Type', 'art-print-pricing'), ucfirst($values['art_print_product_type']));
            // Shipping method is derived from frame; do not add separate meta
            
            if ($values['art_print_frame'] > 1) {
                global $wpdb;
                $frame = $wpdb->get_row($wpdb->prepare(
                    "SELECT name FROM {$wpdb->prefix}art_print_frames WHERE id = %d",
                    $values['art_print_frame']
                ));
                
                if ($frame) {
                    $item->add_meta_data(__('Frame', 'art-print-pricing'), $frame->name);
                }
            }
            
            // Save technical data for fulfillment
            $item->add_meta_data('_art_print_size_inches', $values['art_print_size']);
            $item->add_meta_data('_art_print_product_type', $values['art_print_product_type']);
            $item->add_meta_data('_art_print_shipping_type', $values['art_print_shipping_type']);
            $item->add_meta_data('_art_print_frame_id', $values['art_print_frame']);
        }
    }
    
    public function update_cart_item_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['art_print_price']) && $cart_item['art_print_price'] > 0) {
                $cart_item['data']->set_price($cart_item['art_print_price']);
            }
        }
    }

    public function add_cart_shipping_fees($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        if (did_action('woocommerce_cart_calculate_fees') > 1) {
            // Avoid double-adding in some themes/plugins
            return;
        }
        // Aggregate shipping by shipping type, summing total weight per type
        $type_to_total_weight = array();
        foreach ($cart->get_cart() as $cart_item) {
            if (empty($cart_item['art_print_shipping_type'])) { continue; }
            $qty = isset($cart_item['quantity']) ? max(1, (int) $cart_item['quantity']) : 1;
            $unit_weight = isset($cart_item['art_print_unit_weight']) ? (float) $cart_item['art_print_unit_weight'] : 0;
            $type = $cart_item['art_print_shipping_type'];
            if (!isset($type_to_total_weight[$type])) { $type_to_total_weight[$type] = 0; }
            $type_to_total_weight[$type] += ($unit_weight * $qty);
        }

        if (!empty($type_to_total_weight)) {
            $shipping_manager = new APP_Shipping_Zone_Manager();
            $total_shipping = 0;
            foreach ($type_to_total_weight as $type => $total_weight) {
                $total_shipping += (float) $shipping_manager->calculate_shipping_cost($type, $total_weight);
            }
            if ($total_shipping > 0) {
                $cart->add_fee(__('Art Print Shipping', 'art-print-pricing'), $total_shipping, false);
            }
        }
    }
    
    public function custom_price_html($price, $product) {
        if (is_admin()) {
            return $price;
        }
        
        $product_id = $product->get_id();
        $calculated_prices = get_post_meta($product_id, '_calculated_prices', true);
        
        if (empty($calculated_prices) || !is_array($calculated_prices)) {
            return $price;
        }
        
        // Single product page: show range as before
        if (is_product()) {
            $min_price = min(array_column($calculated_prices, 'print_price'));
            $max_price = max(array_column($calculated_prices, 'painted_price'));
            
            if ($min_price == $max_price) {
                return wc_price($min_price);
            }
            
            return wc_format_price_range($min_price, $max_price);
        }
        
        // Archive/list contexts: show 20" print price (fallback to smallest available)
        $target_size = 20;
        $archive_price = null;
        
        if (isset($calculated_prices[$target_size]) && isset($calculated_prices[$target_size]['print_price'])) {
            $archive_price = (float) $calculated_prices[$target_size]['print_price'];
        } else {
            $available_sizes = array_map('intval', array_keys($calculated_prices));
            if (!empty($available_sizes)) {
                sort($available_sizes, SORT_NUMERIC);
                $smallest = $available_sizes[0];
                if (isset($calculated_prices[$smallest]['print_price'])) {
                    $archive_price = (float) $calculated_prices[$smallest]['print_price'];
                }
            }
        }
        
        if ($archive_price !== null) {
            return wc_price($archive_price);
        }
        
        return $price;
    }
}