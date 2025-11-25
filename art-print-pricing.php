<?php
/**
 * Plugin Name: Art Print Pricing Calculator
 * Plugin URI: https://yoursite.com
 * Description: Advanced pricing calculator for art prints with automatic dimension detection, frame options, and shipping calculation
 * Version: 1.0.0
 * Author: Talha Munawar
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('APP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('APP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('APP_PLUGIN_VERSION', '1.1.0');

class ArtPrintPricingPlugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load plugin components
        $this->load_dependencies();
        // Ensure DB schema is up to date
        $this->maybe_upgrade_frames_table();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once APP_PLUGIN_PATH . 'includes/class-admin-settings.php';
        require_once APP_PLUGIN_PATH . 'includes/class-product-calculator.php';
        require_once APP_PLUGIN_PATH . 'includes/class-frontend-display.php';
        require_once APP_PLUGIN_PATH . 'includes/class-image-processor.php';
        require_once APP_PLUGIN_PATH . 'includes/class-frame-manager.php';
        require_once APP_PLUGIN_PATH . 'includes/class-shipping-zone-manager.php';
        require_once APP_PLUGIN_PATH . 'includes/class-frame-pricing-manager.php';
    }
    
    private function init_hooks() {
        // Admin hooks
        new APP_Admin_Settings();
        
        // Product hooks
        new APP_Product_Calculator();
        
        // Frontend hooks  
        new APP_Frontend_Display();
        
        // Image processing hooks
        new APP_Image_Processor();
        
        // Frame management hooks
        new APP_Frame_Manager();
        
        // Shipping zone management hooks
        new APP_Shipping_Zone_Manager();
        
        // Frame pricing management hooks
        new APP_Frame_Pricing_Manager();
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX hooks
        add_action('wp_ajax_calculate_art_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_nopriv_calculate_art_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_get_image_dimensions', array($this, 'ajax_get_image_dimensions'));
        add_action('wp_ajax_nopriv_get_image_dimensions', array($this, 'ajax_get_image_dimensions'));
        // Admin batch operations
        add_action('wp_ajax_recalculate_all_prices', array($this, 'ajax_recalculate_all_prices'));
        add_action('wp_ajax_extract_all_dimensions', array($this, 'ajax_extract_all_dimensions'));
        // Admin metabox refresh
        add_action('wp_ajax_refresh_calculator_metabox', array($this, 'ajax_refresh_calculator_metabox'));
        
        // Debug hook for troubleshooting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_footer', array($this, 'debug_info'));
        }
    }
    
    public function debug_info() {
        if (is_product()) {
            global $product;
            $product_id = $product->get_id();
            $calculated_prices = get_post_meta($product_id, '_calculated_prices', true);
            $has_thumbnail = has_post_thumbnail($product_id);
            
            echo '<script>console.log("Art Print Debug - Product ID: ' . $product_id . '");</script>';
            echo '<script>console.log("Art Print Debug - Has thumbnail: ' . ($has_thumbnail ? 'Yes' : 'No') . '");</script>';
            echo '<script>console.log("Art Print Debug - Has calculated prices: ' . (!empty($calculated_prices) ? 'Yes' : 'No') . '");</script>';
            
            if (current_user_can('manage_options')) {
                echo '<div style="position:fixed;bottom:10px;right:10px;background:#333;color:#fff;padding:10px;font-size:12px;z-index:9999;">';
                echo 'Art Print Debug:<br>';
                echo 'Product ID: ' . $product_id . '<br>';
                echo 'Has Image: ' . ($has_thumbnail ? 'Yes' : 'No') . '<br>';
                echo 'Has Prices: ' . (!empty($calculated_prices) ? 'Yes' : 'No') . '<br>';
                echo '</div>';
            }
        }
    }
    
    public function enqueue_frontend_scripts() {
        if (is_product()) {
            wp_enqueue_script('app-frontend', APP_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), APP_PLUGIN_VERSION, true);
            wp_enqueue_style('app-frontend', APP_PLUGIN_URL . 'assets/css/frontend.css', array(), APP_PLUGIN_VERSION);
            
            wp_localize_script('app-frontend', 'app_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('app_nonce'),
                'currency_symbol' => get_woocommerce_currency_symbol()
            ));
        }
    }
    
    public function enqueue_admin_scripts($hook) {
        $enqueue_on_frames = strpos($hook, 'woocommerce_page_art-print-frames') === 0;
        $enqueue_on_frame_pricing = strpos($hook, 'woocommerce_page_art-print-frame-pricing') === 0;
        $enqueue_on_shipping = strpos($hook, 'woocommerce_page_art-print-shipping-zones') === 0;
        $enqueue_on_settings = strpos($hook, 'woocommerce_page_art-print-settings') === 0;

        if ('post.php' === $hook || 'post-new.php' === $hook || $enqueue_on_frames || $enqueue_on_frame_pricing || $enqueue_on_shipping || $enqueue_on_settings) {
            // Ensure media uploader is available for frame image upload
            if ($enqueue_on_frames || $enqueue_on_frame_pricing) {
                if (function_exists('wp_enqueue_media')) {
                    wp_enqueue_media();
                }
            }

            wp_enqueue_script('app-admin', APP_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), APP_PLUGIN_VERSION, true);
            wp_enqueue_style('app-admin', APP_PLUGIN_URL . 'assets/css/admin.css', array(), APP_PLUGIN_VERSION);

            wp_localize_script('app-admin', 'app_admin_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('app_admin_nonce')
            ));
        }
    }

    public function ajax_recalculate_all_prices() {
        check_ajax_referer('app_admin_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        @set_time_limit(0);
        $offset = isset($_POST['offset']) ? max(0, intval($_POST['offset'])) : 0;
        $batch_size = isset($_POST['batch_size']) ? max(1, min(200, intval($_POST['batch_size']))) : 20;

        // Get total count once per run (approximation to all products with a thumbnail)
        $total_query = new WP_Query(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'ID',
            'order' => 'ASC',
            'ignore_sticky_posts' => true,
            'suppress_filters' => true,
            'meta_query' => array(
                array('key' => '_thumbnail_id', 'compare' => 'EXISTS')
            )
        ));
        $total = is_array($total_query->posts) ? count($total_query->posts) : 0;

        // Fetch a batch
        $query = new WP_Query(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'ID',
            'order' => 'ASC',
            'ignore_sticky_posts' => true,
            'suppress_filters' => true,
            'meta_query' => array(
                array('key' => '_thumbnail_id', 'compare' => 'EXISTS')
            )
        ));

        $processed = 0;
        if (!empty($query->posts)) {
            $calculator = new APP_Product_Calculator();
            foreach ($query->posts as $product_id) {
                $calculator->update_product_prices($product_id);
                $processed++;
            }
        }

        $completed = min($offset + $processed, $total);
        $next_offset = ($processed > 0) ? ($offset + $processed) : ($offset + $batch_size);
        $next_offset = min($next_offset, $total);
        wp_send_json_success(array('completed' => $completed, 'total' => $total, 'next_offset' => $next_offset));
    }

    public function ajax_extract_all_dimensions() {
        check_ajax_referer('app_admin_nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        @set_time_limit(0);
        $offset = isset($_POST['offset']) ? max(0, intval($_POST['offset'])) : 0;
        $batch_size = isset($_POST['batch_size']) ? max(1, min(200, intval($_POST['batch_size']))) : 20;

        // Get total count of products with images
        $total_query = new WP_Query(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'ID',
            'order' => 'ASC',
            'ignore_sticky_posts' => true,
            'suppress_filters' => true,
            'meta_query' => array(
                array('key' => '_thumbnail_id', 'compare' => 'EXISTS')
            )
        ));
        $total = is_array($total_query->posts) ? count($total_query->posts) : 0;

        // Fetch a batch
        $query = new WP_Query(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'ID',
            'order' => 'ASC',
            'ignore_sticky_posts' => true,
            'suppress_filters' => true,
            'meta_query' => array(
                array('key' => '_thumbnail_id', 'compare' => 'EXISTS')
            )
        ));

        $processed = 0;
        if (!empty($query->posts)) {
            $processor = new APP_Image_Processor();
            $calculator = new APP_Product_Calculator();
            foreach ($query->posts as $product_id) {
                // Process image and recalc
                $processor->process_product_images($product_id);
                $calculator->update_product_prices($product_id);
                $processed++;
            }
        }

        $completed = min($offset + $processed, $total);
        $next_offset = ($processed > 0) ? ($offset + $processed) : ($offset + $batch_size);
        $next_offset = min($next_offset, $total);
        wp_send_json_success(array('completed' => $completed, 'total' => $total, 'next_offset' => $next_offset));
    }
    
    public function ajax_calculate_price() {
        check_ajax_referer('app_nonce', 'nonce');
        
        $calculator = new APP_Product_Calculator();
        $result = $calculator->calculate_price_ajax();
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_image_dimensions() {
        check_ajax_referer('app_nonce', 'nonce');
        
        $processor = new APP_Image_Processor();
        $result = $processor->get_image_dimensions_ajax();
        
        wp_send_json($result);
    }
    
    public function ajax_refresh_calculator_metabox() {
        check_ajax_referer('app_admin_nonce', 'nonce');
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(array('message' => 'Invalid product ID'));
        }
        $calculator = new APP_Product_Calculator();
        $prices = $calculator->get_calculated_prices($post_id);
        ob_start();
        echo '<h4>Calculated Prices:</h4>';
        if (!empty($prices)) {
            foreach ($prices as $size => $data) {
                echo '<div class="price-row">';
                echo '<strong>' . esc_html($size) . '":</strong><br>';
                echo 'Print: $' . number_format((float) $data['print_price'], 2) . '<br>';
                echo 'Painted: $' . number_format((float) $data['painted_price'], 2) . '<br>';
                echo 'Shipping: $' . number_format((float) ($data['shipping_rolled'] ?? 0), 2) . '<br>';
                echo 'Dimensions: ' . esc_html($data['dimensions_cm']) . '<br>';
                echo '</div><hr>';
            }
        } else {
            echo '<p>Upload an image to see calculated prices.</p>';
        }
        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }
    
    public function activate() {
    // Make sure the classes are loaded
    require_once APP_PLUGIN_PATH . 'includes/class-shipping-zone-manager.php';
    require_once APP_PLUGIN_PATH . 'includes/class-frame-pricing-manager.php';

    // Create frames table
    $this->create_frames_table();

    // Insert default frame options
    $this->insert_default_frames();

    // Set default options
    $this->set_default_options();

    // Create shipping zones table
    $shipping_zone_manager = new APP_Shipping_Zone_Manager();
    $shipping_zone_manager->create_zones_table();

    // Create frame pricing table
    $frame_pricing_manager = new APP_Frame_Pricing_Manager();
    $frame_pricing_manager->create_frame_pricing_table();

    // Flush rewrite rules
    flush_rewrite_rules();
}
    
    private function set_default_options() {
        $default_options = array(
            'enable_auto_calculation' => true,
            'base_coefficient' => 0.009,
            'available_sizes' => '20,24,32,40,48',
            'painted_multiplier' => 3.5,
            'default_dpi' => 96,
            'min_print_dpi' => 240,
            'auto_process_images' => true,
            'supported_categories' => array() // Empty means all categories
        );
        
        // Only set if option doesn't exist
        if (!get_option('art_print_options')) {
            add_option('art_print_options', $default_options);
        }
    }
    
    private function process_existing_products() {
        // Get products with featured images but no calculated prices
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 50, // Process first 50 products
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_thumbnail_id',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => '_calculated_prices',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        $products = get_posts($args);
        
        if (!empty($products)) {
            foreach ($products as $product_post) {
                $calculator = new APP_Product_Calculator();
                $calculator->update_product_prices($product_post->ID);
            }
        }
    }
    
    public function deactivate() {
        // Cleanup if needed
        flush_rewrite_rules();
    }
    
    private function create_frames_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'art_print_frames';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            description text,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            image_url varchar(255),
            shipping_type varchar(50) NOT NULL DEFAULT 'rolled',
            active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function insert_default_frames() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'art_print_frames';
        
        // Check if frames already exist
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if ($existing > 0) {
            return;
        }
        
        $default_frames = array(
            array(
                'name' => 'No Frame',
                'description' => 'Rolled canvas without frame',
                'price' => 0.00,
                'shipping_type' => 'rolled',
                'sort_order' => 0
            ),
            array(
                'name' => 'Basic Black Frame',
                'description' => 'Simple black wooden frame',
                'price' => 100.00,
                'shipping_type' => 'framed',
                'sort_order' => 1
            ),
            array(
                'name' => 'Premium Black Frame',
                'description' => 'High-quality black frame with premium finish',
                'price' => 150.00,
                'shipping_type' => 'framed',
                'sort_order' => 2
            ),
            array(
                'name' => 'Luxury Gold Frame',
                'description' => 'Elegant gold frame for premium presentation',
                'price' => 200.00,
                'shipping_type' => 'framed',
                'sort_order' => 3
            )
        );
        
        foreach ($default_frames as $frame) {
            $wpdb->insert($table_name, $frame);
        }
    }

    private function maybe_upgrade_frames_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'art_print_frames';
        // Bail if table doesn't exist yet
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $table_name
        ));
        if (intval($table_exists) === 0) {
            return;
        }
        // Add shipping_type column if missing
        $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $table_name LIKE %s", 'shipping_type'));
        if (!$col) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN shipping_type varchar(50) NOT NULL DEFAULT 'rolled' AFTER image_url");
            // Backfill shipping_type: id 1 => rolled; others => framed
            $wpdb->query("UPDATE $table_name SET shipping_type = 'rolled' WHERE id = 1");
            $wpdb->query("UPDATE $table_name SET shipping_type = 'framed' WHERE id <> 1");
        }
    }
    
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p><strong>Art Print Pricing Calculator</strong> requires WooCommerce to be installed and active.</p></div>';
    }
}

// Initialize the plugin
new ArtPrintPricingPlugin();