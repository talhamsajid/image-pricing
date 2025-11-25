<?php
/**
 * Product Calculator Class
 * Handles all price calculations based on Paolo's PHP logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class APP_Product_Calculator {
    
    private $coef = 0.009;
    private $predefined_sizes = array(20, 24, 32, 40, 48);
    
    public function __construct() {
        // Add custom fields to product admin
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_custom_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_custom_fields'));
        
        // Add custom meta boxes
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Calculate prices on image upload
        add_action('wp_ajax_calculate_from_image', array($this, 'calculate_from_image_ajax'));

        // Load available sizes from settings
        $sizes_option = method_exists('APP_Admin_Settings', 'get_option')
            ? APP_Admin_Settings::get_option('available_sizes', '20,24,32,40,48')
            : '20,24,32,40,48';
        $sizes = array_filter(array_map('intval', array_map('trim', explode(',', (string) $sizes_option))));
        if (!empty($sizes)) {
            sort($sizes, SORT_NUMERIC);
            $this->predefined_sizes = $sizes;
        }
    }
    
    public function add_custom_fields() {
        global $post;
        
        echo '<div class="options_group">';
        echo '<h3>' . __('Art Print Settings', 'art-print-pricing') . '</h3>';
        
        // Manual dimensions override
        woocommerce_wp_text_input(array(
            'id' => '_manual_width_cm',
            'label' => __('Manual Width (cm)', 'art-print-pricing'),
            'placeholder' => 'Leave empty for auto-calculation',
            'desc_tip' => true,
            'description' => __('Override automatic width calculation', 'art-print-pricing'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '0.01',
                'min' => '0'
            )
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_manual_height_cm',
            'label' => __('Manual Height (cm)', 'art-print-pricing'),
            'placeholder' => 'Leave empty for auto-calculation',
            'desc_tip' => true,
            'description' => __('Override automatic height calculation', 'art-print-pricing'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => '0.01',
                'min' => '0'
            )
        ));
        
        // Difficulty multiplier
        woocommerce_wp_select(array(
            'id' => '_difficulty_level',
            'label' => __('Difficulty Level', 'art-print-pricing'),
            'desc_tip' => true,
            'description' => __('Affects pricing calculation', 'art-print-pricing'),
            'options' => array(
                '1' => __('Easy (2x multiplier)', 'art-print-pricing'),
                '2' => __('Medium (3x multiplier)', 'art-print-pricing'),
                '3' => __('Hard (4x multiplier)', 'art-print-pricing'),
                '4' => __('Very Hard (9x multiplier)', 'art-print-pricing')
            )
        ));
        
        // Auto-calculated dimensions (read-only)
        $auto_width = get_post_meta($post->ID, '_auto_width_cm', true);
        $auto_height = get_post_meta($post->ID, '_auto_height_cm', true);
        
        woocommerce_wp_text_input(array(
            'id' => '_auto_width_cm',
            'label' => __('Auto Width (cm)', 'art-print-pricing'),
            'value' => $auto_width,
            'desc_tip' => true,
            'description' => __('Automatically calculated from image', 'art-print-pricing'),
            'custom_attributes' => array('readonly' => 'readonly')
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_auto_height_cm',
            'label' => __('Auto Height (cm)', 'art-print-pricing'),
            'value' => $auto_height,
            'desc_tip' => true,
            'description' => __('Automatically calculated from image', 'art-print-pricing'),
            'custom_attributes' => array('readonly' => 'readonly')
        ));
        
        echo '<p><button type="button" id="recalculate-dimensions" class="button">Recalculate from Image</button></p>';
        
        echo '</div>';
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'art-print-calculator',
            __('Art Print Calculator', 'art-print-pricing'),
            array($this, 'calculator_meta_box'),
            'product',
            'side',
            'high'
        );
    }
    
    public function calculator_meta_box($post) {
        $prices = $this->get_calculated_prices($post->ID);
        
        echo '<div id="art-print-prices">';
        echo '<h4>Calculated Prices:</h4>';
        
        if (!empty($prices)) {
            foreach ($prices as $size => $data) {
                echo '<div class="price-row">';
                echo '<strong>' . $size . '":</strong><br>';
                echo 'Print: $' . number_format($data['print_price'], 2) . '<br>';
                echo 'Painted: $' . number_format($data['painted_price'], 2) . '<br>';
                $shipping_display = isset($data['shipping_rolled']) ? (float) $data['shipping_rolled'] : 0;
                echo 'Shipping: $' . number_format($shipping_display, 2) . '<br>';
                echo 'Dimensions: ' . $data['dimensions_cm'] . '<br>';
                echo '</div><hr>';
            }
        } else {
            echo '<p>Upload an image to see calculated prices.</p>';
        }
        
        echo '</div>';
    }
    
    public function save_custom_fields($post_id) {
        $fields = array(
            '_manual_width_cm',
            '_manual_height_cm', 
            '_difficulty_level',
            '_auto_width_cm',
            '_auto_height_cm'
        );
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Recalculate prices when fields are saved
        $this->update_product_prices($post_id);
    }
    
    public function get_image_dimensions($post_id) {
        // Get product images
        $attachment_ids = array();
        
        // Featured image
        $featured_image = get_post_thumbnail_id($post_id);
        if ($featured_image) {
            $attachment_ids[] = $featured_image;
        }
        
        // Gallery images
        $gallery_ids = get_post_meta($post_id, '_product_image_gallery', true);
        if ($gallery_ids) {
            $gallery_array = explode(',', $gallery_ids);
            $attachment_ids = array_merge($attachment_ids, $gallery_array);
        }
        
        if (empty($attachment_ids)) {
            return false;
        }
        
        // Use first available image
        $attachment_id = $attachment_ids[0];
        $image_path = get_attached_file($attachment_id);
        
        if (!$image_path || !file_exists($image_path)) {
            return false;
        }
        
        $image_info = getimagesize($image_path);
        
        if (!$image_info) {
            return false;
        }
        
        return array(
            'width_px' => $image_info[0],
            'height_px' => $image_info[1],
            'width_cm' => round($image_info[0] * 0.026458333, 2), // Convert px to cm (96 DPI)
            'height_cm' => round($image_info[1] * 0.026458333, 2)
        );
    }
    
    public function get_effective_dimensions($post_id) {
        // Check for manual override first
        $manual_width = get_post_meta($post_id, '_manual_width_cm', true);
        $manual_height = get_post_meta($post_id, '_manual_height_cm', true);
        
        if (!empty($manual_width) && !empty($manual_height)) {
            return array(
                'width_cm' => floatval($manual_width),
                'height_cm' => floatval($manual_height),
                'source' => 'manual'
            );
        }
        
        // Use auto-calculated dimensions
        $auto_dims = $this->get_image_dimensions($post_id);
        if ($auto_dims) {
            // Update auto dimensions in meta
            update_post_meta($post_id, '_auto_width_cm', $auto_dims['width_cm']);
            update_post_meta($post_id, '_auto_height_cm', $auto_dims['height_cm']);
            
            return array(
                'width_cm' => $auto_dims['width_cm'],
                'height_cm' => $auto_dims['height_cm'],
                'source' => 'auto'
            );
        }
        
        return false;
    }
    
    public function calculate_prices($width_cm, $height_cm, $difficulty = 1) {
        // Determine larger and smaller sides
        $ls = max($width_cm, $height_cm);
        $ss = min($width_cm, $height_cm);
        
        // Calculate difficulty multiplier based on Paolo's logic
        $difmulti = 2; // default
        switch (intval($difficulty)) {
            case 2:
                $difmulti = 3;
                break;
            case 3:
                $difmulti = 4;
                break;
            case 4:
                $difmulti = 9;
                break;
        }
        
        $var = $ss / $ls;
        $prices = array();
        
        // Initialize shipping zone manager
        $shipping_manager = new APP_Shipping_Zone_Manager();
        
        foreach ($this->predefined_sizes as $size_inches) {
            $res = round($size_inches * $var);
            $rescm = round($res * 2.54, 2);
            
            // Calculate base cost
            $cost = round((($size_inches * 2.54) * ($res * 2.54) * $this->coef * $difmulti));
            
            // Adjust cost based on size (following Paolo's logic)
            if ($size_inches == 24) {
                $cost = round($cost * 0.9);
            } elseif ($size_inches == 20) {
                $cost = round($cost * 0.81); // 0.9 * 0.9
            }
            
            // Calculate shipping costs for different zones
            $weight_kg = ($size_inches * 2.54) * (($res + 5) * 2.54) * 0.0016;
            if ($size_inches == 48) {
                $weight_kg += 8; // Extra weight for 48"
            }
            
            $shipping_rolled = $shipping_manager->calculate_shipping_cost('rolled', $weight_kg);
            $shipping_stretcher = $shipping_manager->calculate_shipping_cost('stretcher', $weight_kg);
            $shipping_framed = $shipping_manager->calculate_shipping_cost('framed', $weight_kg);
            
            // Calculate painted price (3.5x for larger sizes, adjusted for smaller)
            $painted_multiplier = ($size_inches >= 32) ? 3.5 : 3.15; // Slightly less for smaller sizes
            $painted_cost = round($cost * $painted_multiplier);
            
            $prices[$size_inches] = array(
                'print_price' => $cost,
                'painted_price' => $painted_cost,
                'shipping_rolled' => $shipping_rolled,
                'shipping_stretcher' => $shipping_stretcher,
                'shipping_framed' => $shipping_framed,
                'weight_kg' => $weight_kg,
                'dimensions_inches' => $size_inches . ' x ' . $res,
                'dimensions_cm' => round($size_inches * 2.54) . ' x ' . $rescm,
                'res_inches' => $res,
                'res_cm' => $rescm
            );
        }
        
        return $prices;
    }
    
    public function get_calculated_prices($post_id) {
        $dimensions = $this->get_effective_dimensions($post_id);
        if (!$dimensions) {
            return array();
        }
        
        $difficulty = get_post_meta($post_id, '_difficulty_level', true) ?: 1;
        
        return $this->calculate_prices(
            $dimensions['width_cm'],
            $dimensions['height_cm'],
            $difficulty
        );
    }
    
    public function update_product_prices($post_id) {
        error_log('[ArtPrint] update_product_prices called for product_id: ' . $post_id);
        $prices = $this->get_calculated_prices($post_id);
        error_log('[ArtPrint] Calculated prices: ' . print_r($prices, true));
        
        if (empty($prices)) {
            error_log('[ArtPrint] No prices calculated for product_id: ' . $post_id . '. Check image and meta.');
            return;
        }
        
        // Set the base price to the smallest available size
        $base_size = min(array_keys($prices));
        
        $base_price = $prices[$base_size]['print_price'];
        
        // Update WooCommerce price
        update_post_meta($post_id, '_price', $base_price);
        update_post_meta($post_id, '_regular_price', $base_price);
        
        // Update dimensions (use base size)
        $dimensions = $this->get_effective_dimensions($post_id);
        if ($dimensions) {
            update_post_meta($post_id, '_length', $dimensions['width_cm']);
            update_post_meta($post_id, '_width', $dimensions['height_cm']);
            update_post_meta($post_id, '_height', '0.5'); // Canvas thickness
        }
        
        // Update weight (use calculated weight_kg for the base size)
        $base_weight = isset($prices[$base_size]['weight_kg']) ? $prices[$base_size]['weight_kg'] : 0;
        update_post_meta($post_id, '_weight', $base_weight);
        
        // Store calculated prices for frontend use
        update_post_meta($post_id, '_calculated_prices', $prices);
    }
    
    public function calculate_price_ajax() {
        $product_id = intval($_POST['product_id']);
        $size = intval($_POST['size']);
        $frame_id = intval($_POST['frame_id']);
        $print_type = sanitize_text_field($_POST['print_type']);
        $product_type = sanitize_text_field($_POST['product_type'] ?? 'print');
        $quantity = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;
        
        $prices = get_post_meta($product_id, '_calculated_prices', true);
        
        if (empty($prices) || !isset($prices[$size])) {
            return array('success' => false, 'message' => 'Invalid size selected');
        }
        
        $price_data = $prices[$size];
        
        // Determine base price based on product type
        if ($product_type === 'painting') {
            $base_price = $price_data['painted_price'];
        } else {
            $base_price = $price_data['print_price'];
        }
        
        // Calculate frame cost: use simple frame price from frames table (admin-configured)
        $frame_cost = 0;
        if ($frame_id > 0) {
            global $wpdb;
            $frame = $wpdb->get_row($wpdb->prepare(
                "SELECT price FROM {$wpdb->prefix}art_print_frames WHERE id = %d",
                $frame_id
            ));
            if ($frame) {
                $frame_cost = floatval($frame->price);
            }
        }
        
        // Derive shipping type from selected frame's configured shipping_type
        $derived_shipping_type = 'rolled';
        if ($frame_id > 0) {
            global $wpdb;
            $frame_row = $wpdb->get_row($wpdb->prepare(
                "SELECT shipping_type FROM {$wpdb->prefix}art_print_frames WHERE id = %d",
                $frame_id
            ));
            if ($frame_row && !empty($frame_row->shipping_type)) {
                $derived_shipping_type = $frame_row->shipping_type;
            } else {
                // Fallback to legacy rule: id 1 => rolled, others => framed
                $derived_shipping_type = ($frame_id <= 1) ? 'rolled' : 'framed';
            }
        }
        // Shipping cost by weight tiers using total weight = per-unit weight × quantity
        $shipping_manager = new APP_Shipping_Zone_Manager();
        $total_weight_kg = floatval($price_data['weight_kg']) * $quantity;
        $shipping_cost = $shipping_manager->calculate_shipping_cost($derived_shipping_type, $total_weight_kg);

        // Total price includes frame + base (per unit) times qty, plus line-level shipping
        $line_subtotal = ($base_price + $frame_cost) * $quantity;
        $total_price = $line_subtotal + $shipping_cost;
        
        return array(
            'success' => true,
            'base_price' => $base_price,
            'frame_cost' => $frame_cost,
            'total_price' => $total_price,
            'shipping_cost' => $shipping_cost,
            'quantity' => $quantity,
            'dimensions' => $price_data['dimensions_cm'],
            'dimensions_inches' => $price_data['dimensions_inches'],
            'weight_kg' => $price_data['weight_kg']
        );
    }
    
    public function calculate_from_image_ajax() {
        check_ajax_referer('app_admin_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        
        if (!$post_id) {
            wp_send_json_error('Invalid product ID');
        }
        
        $dimensions = $this->get_image_dimensions($post_id);
        
        if (!$dimensions) {
            wp_send_json_error('Could not extract image dimensions');
        }
        
        // Update auto dimensions
        update_post_meta($post_id, '_auto_width_cm', $dimensions['width_cm']);
        update_post_meta($post_id, '_auto_height_cm', $dimensions['height_cm']);
        
        // Recalculate prices
        $this->update_product_prices($post_id);
        
        wp_send_json_success(array(
            'dimensions' => $dimensions,
            'message' => 'Dimensions and prices updated successfully'
        ));
    }
}