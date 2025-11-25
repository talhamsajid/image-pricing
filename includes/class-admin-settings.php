<?php
/**
 * Admin Settings Class
 * Handles plugin settings and configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class APP_Admin_Settings {
    
    public function __construct() {
        // Add settings page
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'init_settings'));
        
        // Add settings link to plugin page
        add_filter('plugin_action_links_' . plugin_basename(APP_PLUGIN_PATH . 'art-print-pricing.php'), array($this, 'add_settings_link'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Art Print Settings', 'art-print-pricing'),
            __('Art Print Settings', 'art-print-pricing'),
            'manage_woocommerce',
            'art-print-settings',
            array($this, 'settings_page')
        );
    }
    
    public function init_settings() {
        register_setting('art_print_settings', 'art_print_options');
        
        // General Settings Section
        add_settings_section(
            'art_print_general',
            __('General Settings', 'art-print-pricing'),
            array($this, 'general_section_callback'),
            'art_print_settings'
        );
        
        // Pricing Settings Section
        add_settings_section(
            'art_print_pricing',
            __('Pricing Configuration', 'art-print-pricing'),
            array($this, 'pricing_section_callback'),
            'art_print_settings'
        );
        
        // Image Processing Section
        add_settings_section(
            'art_print_image',
            __('Image Processing', 'art-print-pricing'),
            array($this, 'image_section_callback'),
            'art_print_settings'
        );
        
        // General Settings Fields
        add_settings_field(
            'enable_auto_calculation',
            __('Enable Automatic Calculation', 'art-print-pricing'),
            array($this, 'checkbox_field'),
            'art_print_settings',
            'art_print_general',
            array(
                'name' => 'enable_auto_calculation',
                'description' => __('Automatically calculate prices and dimensions when images are uploaded', 'art-print-pricing'),
                'default' => true
            )
        );
        
        add_settings_field(
            'supported_categories',
            __('Supported Product Categories', 'art-print-pricing'),
            array($this, 'category_multiselect_field'),
            'art_print_settings',
            'art_print_general',
            array(
                'name' => 'supported_categories',
                'description' => __('Select product categories that should use art print pricing', 'art-print-pricing')
            )
        );
        
        // Pricing Settings Fields
        add_settings_field(
            'base_coefficient',
            __('Base Pricing Coefficient', 'art-print-pricing'),
            array($this, 'number_field'),
            'art_print_settings',
            'art_print_pricing',
            array(
                'name' => 'base_coefficient',
                'description' => __('Base coefficient for price calculation (default: 0.009)', 'art-print-pricing'),
                'default' => 0.009,
                'step' => 0.001,
                'min' => 0.001
            )
        );
        
        add_settings_field(
            'available_sizes',
            __('Available Print Sizes (inches)', 'art-print-pricing'),
            array($this, 'text_field'),
            'art_print_settings',
            'art_print_pricing',
            array(
                'name' => 'available_sizes',
                'description' => __('Comma-separated list of available sizes in inches (e.g., 20,24,32,40,48)', 'art-print-pricing'),
                'default' => '20,24,32,40,48'
            )
        );
        
        add_settings_field(
            'painted_multiplier',
            __('Painted Price Multiplier', 'art-print-pricing'),
            array($this, 'number_field'),
            'art_print_settings',
            'art_print_pricing',
            array(
                'name' => 'painted_multiplier',
                'description' => __('Multiplier for hand-painted prices (default: 3.5)', 'art-print-pricing'),
                'default' => 3.5,
                'step' => 0.1,
                'min' => 1
            )
        );
        
        // Image Processing Fields
        add_settings_field(
            'default_dpi',
            __('Default DPI', 'art-print-pricing'),
            array($this, 'number_field'),
            'art_print_settings',
            'art_print_image',
            array(
                'name' => 'default_dpi',
                'description' => __('Default DPI for dimension calculations when EXIF data is unavailable (default: 96)', 'art-print-pricing'),
                'default' => 96,
                'min' => 72,
                'max' => 600
            )
        );
        
        add_settings_field(
            'min_print_dpi',
            __('Minimum Print DPI', 'art-print-pricing'),
            array($this, 'number_field'),
            'art_print_settings',
            'art_print_image',
            array(
                'name' => 'min_print_dpi',
                'description' => __('Minimum DPI required for quality printing (default: 240)', 'art-print-pricing'),
                'default' => 240,
                'min' => 150,
                'max' => 600
            )
        );
        
        add_settings_field(
            'auto_process_images',
            __('Auto-process Images', 'art-print-pricing'),
            array($this, 'checkbox_field'),
            'art_print_settings',
            'art_print_image',
            array(
                'name' => 'auto_process_images',
                'description' => __('Automatically process images when uploaded to extract dimensions', 'art-print-pricing'),
                'default' => true
            )
        );
    }
    
    public function settings_page() {
        if (isset($_GET['tab'])) {
            $active_tab = $_GET['tab'];
        } else {
            $active_tab = 'general';
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Art Print Pricing Settings', 'art-print-pricing'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=art-print-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('General', 'art-print-pricing'); ?>
                </a>
                <a href="?page=art-print-settings&tab=pricing" class="nav-tab <?php echo $active_tab == 'pricing' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Pricing', 'art-print-pricing'); ?>
                </a>
                <a href="?page=art-print-settings&tab=advanced" class="nav-tab <?php echo $active_tab == 'advanced' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Advanced', 'art-print-pricing'); ?>
                </a>
                <a href="?page=art-print-settings&tab=help" class="nav-tab <?php echo $active_tab == 'help' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Help', 'art-print-pricing'); ?>
                </a>
            </h2>
            
            <?php if ($active_tab == 'general'): ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('art_print_settings');
                    do_settings_sections('art_print_settings');
                    submit_button();
                    ?>
                </form>
                
            <?php elseif ($active_tab == 'pricing'): ?>
                <div class="pricing-calculator-demo">
                    <h2><?php _e('Pricing Calculator Demo', 'art-print-pricing'); ?></h2>
                    <div class="demo-container">
                        <div class="demo-inputs">
                            <h3><?php _e('Test Pricing Calculation', 'art-print-pricing'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Width (cm)', 'art-print-pricing'); ?></th>
                                    <td><input type="number" id="test-width" value="80" step="0.1" min="1"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Height (cm)', 'art-print-pricing'); ?></th>
                                    <td><input type="number" id="test-height" value="60" step="0.1" min="1"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Difficulty Level', 'art-print-pricing'); ?></th>
                                    <td>
                                        <select id="test-difficulty">
                                            <option value="1">Easy (2x)</option>
                                            <option value="2">Medium (3x)</option>
                                            <option value="3">Hard (4x)</option>
                                            <option value="4">Very Hard (9x)</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            <button type="button" id="calculate-demo" class="button button-primary">
                                <?php _e('Calculate Prices', 'art-print-pricing'); ?>
                            </button>
                        </div>
                        <div class="demo-results">
                            <h3><?php _e('Calculated Prices', 'art-print-pricing'); ?></h3>
                            <div id="demo-results-content">
                                <p><?php _e('Enter dimensions and click Calculate to see pricing.', 'art-print-pricing'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="post" action="options.php">
                    <?php
                    settings_fields('art_print_settings');
                    do_settings_sections('art_print_settings');
                    submit_button();
                    ?>
                </form>
                
            <?php elseif ($active_tab == 'advanced'): ?>
                <div class="advanced-settings">
                    <h2><?php _e('Advanced Configuration', 'art-print-pricing'); ?></h2>
                    
                    <div class="settings-section">
                        <h3><?php _e('Database Status', 'art-print-pricing'); ?></h3>
                        <?php $this->display_database_status(); ?>
                    </div>
                    
                    <div class="settings-section">
                        <h3><?php _e('System Information', 'art-print-pricing'); ?></h3>
                        <?php $this->display_system_info(); ?>
                    </div>
                    
                    <div class="settings-section">
                        <h3><?php _e('Bulk Operations', 'art-print-pricing'); ?></h3>
                        <p>
                            <button type="button" id="bulk-recalculate" class="button button-secondary">
                                <?php _e('Recalculate All Product Prices', 'art-print-pricing'); ?>
                            </button>
                            <span class="description">
                                <?php _e('This will recalculate prices for all art print products. Use with caution.', 'art-print-pricing'); ?>
                            </span>
                        </p>
                        
                        <p>
                            <button type="button" id="bulk-extract-dimensions" class="button button-secondary">
                                <?php _e('Extract Dimensions from All Images', 'art-print-pricing'); ?>
                            </button>
                            <span class="description">
                                <?php _e('This will extract dimensions from all product images that haven\'t been processed.', 'art-print-pricing'); ?>
                            </span>
                        </p>
                    </div>
                </div>
                
            <?php elseif ($active_tab == 'help'): ?>
                <div class="help-content">
                    <h2><?php _e('Help & Documentation', 'art-print-pricing'); ?></h2>
                    
                    <div class="help-sections">
                        <div class="help-section">
                            <h3><?php _e('Getting Started', 'art-print-pricing'); ?></h3>
                            <ol>
                                <li><?php _e('Upload images to your products', 'art-print-pricing'); ?></li>
                                <li><?php _e('The plugin will automatically extract dimensions', 'art-print-pricing'); ?></li>
                                <li><?php _e('Prices will be calculated based on the dimensions', 'art-print-pricing'); ?></li>
                                <li><?php _e('Customers can select sizes and frame options', 'art-print-pricing'); ?></li>
                            </ol>
                        </div>
                        
                        <div class="help-section">
                            <h3><?php _e('Troubleshooting', 'art-print-pricing'); ?></h3>
                            <ul>
                                <li><strong><?php _e('Prices not calculating:', 'art-print-pricing'); ?></strong> <?php _e('Make sure images are uploaded and the product is in a supported category.', 'art-print-pricing'); ?></li>
                                <li><strong><?php _e('Dimensions incorrect:', 'art-print-pricing'); ?></strong> <?php _e('You can manually override dimensions in the product edit page.', 'art-print-pricing'); ?></li>
                                <li><strong><?php _e('Frontend not displaying:', 'art-print-pricing'); ?></strong> <?php _e('Check that your theme supports WooCommerce hooks.', 'art-print-pricing'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="help-section">
                            <h3><?php _e('Pricing Formula', 'art-print-pricing'); ?></h3>
                            <p><?php _e('The pricing calculation follows this formula:', 'art-print-pricing'); ?></p>
                            <code>Price = (Width_cm × Height_cm × Coefficient × Difficulty_Multiplier) + Shipping</code>
                            <p><?php _e('Where:', 'art-print-pricing'); ?></p>
                            <ul>
                                <li><?php _e('Coefficient: Base pricing coefficient (default 0.009)', 'art-print-pricing'); ?></li>
                                <li><?php _e('Difficulty Multiplier: 2x (easy), 3x (medium), 4x (hard), 9x (very hard)', 'art-print-pricing'); ?></li>
                                <li><?php _e('Shipping: Calculated based on size and weight', 'art-print-pricing'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .demo-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 20px 0;
        }
        
        .demo-inputs,
        .demo-results {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .settings-section {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .help-sections {
            display: grid;
            gap: 20px;
        }
        
        .help-section {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#calculate-demo').on('click', function() {
                var width = parseFloat($('#test-width').val());
                var height = parseFloat($('#test-height').val());
                var difficulty = parseInt($('#test-difficulty').val());
                
                // Simple calculation demo
                var ls = Math.max(width, height);
                var ss = Math.min(width, height);
                var difmulti = difficulty === 2 ? 3 : (difficulty === 3 ? 4 : (difficulty === 4 ? 9 : 2));
                var coef = 0.009;
                
                var sizes = [20, 24, 32, 40, 48];
                var results = '<table class="wp-list-table widefat"><thead><tr><th>Size</th><th>Print Price</th><th>Painted Price</th><th>Shipping</th></tr></thead><tbody>';
                
                sizes.forEach(function(size) {
                    var var_ratio = ss / ls;
                    var res = Math.round(size * var_ratio);
                    var cost = Math.round((size * 2.54) * (res * 2.54) * coef * difmulti);
                    var painted = Math.round(cost * 3.5);
                    var shipping = Math.round((size * 2.54) * ((res + 5) * 2.54) * 0.0016);
                    
                    results += '<tr><td>' + size + ' x ' + res + ' in</td><td>' + cost + '</td><td>' + painted + '</td><td>' + shipping + '</td></tr>';
                });
                
                results += '</tbody></table>';
                $('#demo-results-content').html(results);
            });
        });
        </script>
        <?php
    }
    
    public function general_section_callback() {
        echo '<p>' . __('Configure general plugin settings.', 'art-print-pricing') . '</p>';
    }
    
    public function pricing_section_callback() {
        echo '<p>' . __('Configure pricing calculation parameters.', 'art-print-pricing') . '</p>';
    }
    
    public function image_section_callback() {
        echo '<p>' . __('Configure image processing settings.', 'art-print-pricing') . '</p>';
    }
    
    public function checkbox_field($args) {
        $options = get_option('art_print_options');
        $value = isset($options[$args['name']]) ? $options[$args['name']] : $args['default'];
        
        echo '<input type="checkbox" name="art_print_options[' . $args['name'] . ']" value="1" ' . checked(1, $value, false) . '>';
        if (isset($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }
    
    public function text_field($args) {
        $options = get_option('art_print_options');
        $value = isset($options[$args['name']]) ? $options[$args['name']] : $args['default'];
        
        echo '<input type="text" name="art_print_options[' . $args['name'] . ']" value="' . esc_attr($value) . '" class="regular-text">';
        if (isset($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }
    
    public function number_field($args) {
        $options = get_option('art_print_options');
        $value = isset($options[$args['name']]) ? $options[$args['name']] : $args['default'];
        
        $step = isset($args['step']) ? $args['step'] : 1;
        $min = isset($args['min']) ? $args['min'] : '';
        $max = isset($args['max']) ? $args['max'] : '';
        
        echo '<input type="number" name="art_print_options[' . $args['name'] . ']" value="' . esc_attr($value) . '" step="' . $step . '" min="' . $min . '" max="' . $max . '" class="small-text">';
        if (isset($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }
    
    public function category_multiselect_field($args) {
        $options = get_option('art_print_options');
        $selected = isset($options[$args['name']]) ? $options[$args['name']] : array();
        
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false
        ));
        
        echo '<select name="art_print_options[' . $args['name'] . '][]" multiple="multiple" class="regular-text">';
        foreach ($categories as $category) {
            $selected_attr = in_array($category->term_id, $selected) ? 'selected="selected"' : '';
            echo '<option value="' . $category->term_id . '" ' . $selected_attr . '>' . $category->name . '</option>';
        }
        echo '</select>';
        if (isset($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }
    
    public function display_database_status() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'art_print_frames';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Component</th><th>Status</th></tr></thead>';
        echo '<tbody>';
        
        echo '<tr><td>Frames Table</td><td>';
        if ($table_exists) {
            $frame_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            echo '<span style="color: green;">✓ Exists (' . $frame_count . ' frames)</span>';
        } else {
            echo '<span style="color: red;">✗ Missing</span>';
        }
        echo '</td></tr>';
        
        echo '<tr><td>WooCommerce</td><td>';
        if (class_exists('WooCommerce')) {
            echo '<span style="color: green;">✓ Active (v' . WC()->version . ')</span>';
        } else {
            echo '<span style="color: red;">✗ Not Active</span>';
        }
        echo '</td></tr>';
        
        echo '</tbody></table>';
    }
    
    public function display_system_info() {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Setting</th><th>Value</th></tr></thead>';
        echo '<tbody>';
        
        echo '<tr><td>PHP Version</td><td>' . PHP_VERSION . '</td></tr>';
        echo '<tr><td>WordPress Version</td><td>' . get_bloginfo('version') . '</td></tr>';
        echo '<tr><td>Plugin Version</td><td>' . APP_PLUGIN_VERSION . '</td></tr>';
        echo '<tr><td>GD Extension</td><td>' . (extension_loaded('gd') ? '✓ Enabled' : '✗ Disabled') . '</td></tr>';
        echo '<tr><td>EXIF Extension</td><td>' . (extension_loaded('exif') ? '✓ Enabled' : '✗ Disabled') . '</td></tr>';
        echo '<tr><td>Memory Limit</td><td>' . ini_get('memory_limit') . '</td></tr>';
        echo '<tr><td>Upload Max Size</td><td>' . ini_get('upload_max_filesize') . '</td></tr>';
        
        echo '</tbody></table>';
    }
    
    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=art-print-settings">' . __('Settings', 'art-print-pricing') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function admin_notices() {
        // Check for common configuration issues
        if (!class_exists('WooCommerce')) {
            echo '<div class="notice notice-error"><p><strong>Art Print Pricing:</strong> ' . __('WooCommerce is required for this plugin to work.', 'art-print-pricing') . '</p></div>';
        }
        
        if (!extension_loaded('gd')) {
            echo '<div class="notice notice-warning"><p><strong>Art Print Pricing:</strong> ' . __('GD extension is recommended for better image processing.', 'art-print-pricing') . '</p></div>';
        }
        
        // Check if any products need processing
        $unprocessed_count = $this->get_unprocessed_products_count();
        if ($unprocessed_count > 0) {
            echo '<div class="notice notice-info is-dismissible"><p><strong>Art Print Pricing:</strong> ' . 
                 sprintf(__('You have %d products that may need price recalculation. <a href="%s">Review settings</a>', 'art-print-pricing'), 
                 $unprocessed_count, admin_url('admin.php?page=art-print-settings&tab=advanced')) . '</p></div>';
        }
    }
    
    private function get_unprocessed_products_count() {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
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
            ),
            'fields' => 'ids'
        );
        
        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    public static function get_option($option_name, $default = null) {
        $options = get_option('art_print_options', array());
        return isset($options[$option_name]) ? $options[$option_name] : $default;
    }
}