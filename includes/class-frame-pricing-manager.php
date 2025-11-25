<?php
/**
 * Frame Pricing Manager Class
 * Handles size-based frame pricing calculations
 */

if (!defined('ABSPATH')) {
    exit;
}

class APP_Frame_Pricing_Manager {
    
    private $frame_pricing_table;
    
    public function __construct() {
        global $wpdb;
        $this->frame_pricing_table = $wpdb->prefix . 'art_print_frame_pricing';
        
        // Create table on activation
        add_action('init', array($this, 'create_frame_pricing_table'));
        
        // Add admin hooks
        add_action('admin_menu', array($this, 'add_frame_pricing_menu'));
        add_action('admin_init', array($this, 'handle_frame_pricing_save'));
        
        // Add AJAX handlers
        add_action('wp_ajax_save_frame_pricing', array($this, 'ajax_save_frame_pricing'));
        add_action('wp_ajax_get_frame_pricing', array($this, 'ajax_get_frame_pricing'));
    }
    
    public function create_frame_pricing_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->frame_pricing_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            frame_id mediumint(9) NOT NULL,
            pricing_type varchar(50) NOT NULL DEFAULT 'fixed',
            base_price decimal(10,2) NOT NULL DEFAULT 0.00,
            price_per_cm2 decimal(10,4) NOT NULL DEFAULT 0.0000,
            min_price decimal(10,2) NOT NULL DEFAULT 0.00,
            max_price decimal(10,2) NOT NULL DEFAULT 0.00,
            size_tiers text,
            active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY frame_id (frame_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Insert default pricing if table is empty
        $this->insert_default_frame_pricing();
    }
    
    private function insert_default_frame_pricing() {
        global $wpdb;
        
        // Check if pricing already exists
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM {$this->frame_pricing_table}");
        if ($existing > 0) {
            return;
        }
        
        // Get existing frames
        $frames_table = $wpdb->prefix . 'art_print_frames';
        $frames = $wpdb->get_results("SELECT * FROM {$frames_table} WHERE active = 1 ORDER BY sort_order ASC");
        
        foreach ($frames as $frame) {
            $pricing_data = array(
                'frame_id' => $frame->id,
                'pricing_type' => 'size_tier',
                'base_price' => 0.00,
                'price_per_cm2' => 0.0000,
                'min_price' => 0.00,
                'max_price' => 0.00,
                'size_tiers' => json_encode(array(
                    '20' => array('base' => 50.00, 'per_cm2' => 0.15),
                    '24' => array('base' => 75.00, 'per_cm2' => 0.12),
                    '32' => array('base' => 100.00, 'per_cm2' => 0.10),
                    '40' => array('base' => 150.00, 'per_cm2' => 0.08),
                    '48' => array('base' => 200.00, 'per_cm2' => 0.06)
                ))
            );
            
            $wpdb->insert($this->frame_pricing_table, $pricing_data);
        }
    }
    
    public function add_frame_pricing_menu() {
        add_submenu_page(
            'woocommerce',
            __('Frame Pricing', 'art-print-pricing'),
            __('Frame Pricing', 'art-print-pricing'),
            'manage_woocommerce',
            'art-print-frame-pricing',
            array($this, 'frame_pricing_page')
        );
    }
    
    public function frame_pricing_page() {
        global $wpdb;
        $frames_table = $wpdb->prefix . 'art_print_frames';
        $frames = $wpdb->get_results("SELECT * FROM {$frames_table} WHERE active = 1 ORDER BY sort_order ASC");
        
        $pricing_data = array();
        foreach ($frames as $frame) {
            $pricing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->frame_pricing_table} WHERE frame_id = %d AND active = 1",
                $frame->id
            ));
            $pricing_data[$frame->id] = $pricing;
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Frame Pricing Configuration', 'art-print-pricing'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php _e('Configure frame pricing based on size. You can use fixed pricing, per square centimeter pricing, or size-tiered pricing.', 'art-print-pricing'); ?></p>
            </div>
            
            <form method="post" action="" id="frame-pricing-form">
                <?php wp_nonce_field('save_frame_pricing', 'frame_pricing_nonce'); ?>
                
                <?php foreach ($frames as $frame): ?>
                    <?php $pricing = $pricing_data[$frame->id] ?? null; ?>
                    <div class="frame-pricing-section">
                        <h3><?php echo esc_html($frame->name); ?></h3>
                        <p class="description"><?php echo esc_html($frame->description); ?></p>
                        
                        <input type="hidden" name="frame_pricing[<?php echo $frame->id; ?>][frame_id]" value="<?php echo $frame->id; ?>">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Pricing Type', 'art-print-pricing'); ?></th>
                                <td>
                                    <select name="frame_pricing[<?php echo $frame->id; ?>][pricing_type]" class="pricing-type-select">
                                        <option value="fixed" <?php selected($pricing->pricing_type ?? '', 'fixed'); ?>><?php _e('Fixed Price', 'art-print-pricing'); ?></option>
                                        <option value="per_cm2" <?php selected($pricing->pricing_type ?? '', 'per_cm2'); ?>><?php _e('Per Square Centimeter', 'art-print-pricing'); ?></option>
                                        <option value="size_tier" <?php selected($pricing->pricing_type ?? '', 'size_tier'); ?>><?php _e('Size Tiered', 'art-print-pricing'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr class="fixed-pricing-row" style="<?php echo ($pricing->pricing_type ?? '') === 'fixed' ? '' : 'display:none;'; ?>">
                                <th scope="row"><?php _e('Fixed Price', 'art-print-pricing'); ?></th>
                                <td>
                                    <input type="number" name="frame_pricing[<?php echo $frame->id; ?>][base_price]" 
                                           value="<?php echo esc_attr($pricing->base_price ?? 0); ?>" 
                                           step="0.01" min="0" class="regular-text">
                                </td>
                            </tr>
                            
                            <tr class="per-cm2-pricing-row" style="<?php echo ($pricing->pricing_type ?? '') === 'per_cm2' ? '' : 'display:none;'; ?>">
                                <th scope="row"><?php _e('Price per cm²', 'art-print-pricing'); ?></th>
                                <td>
                                    <input type="number" name="frame_pricing[<?php echo $frame->id; ?>][price_per_cm2]" 
                                           value="<?php echo esc_attr($pricing->price_per_cm2 ?? 0); ?>" 
                                           step="0.0001" min="0" class="regular-text">
                                </td>
                            </tr>
                            
                            <tr class="per-cm2-pricing-row" style="<?php echo ($pricing->pricing_type ?? '') === 'per_cm2' ? '' : 'display:none;'; ?>">
                                <th scope="row"><?php _e('Minimum Price', 'art-print-pricing'); ?></th>
                                <td>
                                    <input type="number" name="frame_pricing[<?php echo $frame->id; ?>][min_price]" 
                                           value="<?php echo esc_attr($pricing->min_price ?? 0); ?>" 
                                           step="0.01" min="0" class="regular-text">
                                </td>
                            </tr>
                            
                            <tr class="per-cm2-pricing-row" style="<?php echo ($pricing->pricing_type ?? '') === 'per_cm2' ? '' : 'display:none;'; ?>">
                                <th scope="row"><?php _e('Maximum Price', 'art-print-pricing'); ?></th>
                                <td>
                                    <input type="number" name="frame_pricing[<?php echo $frame->id; ?>][max_price]" 
                                           value="<?php echo esc_attr($pricing->max_price ?? 0); ?>" 
                                           step="0.01" min="0" class="regular-text">
                                </td>
                            </tr>
                            
                            <tr class="size-tier-pricing-row" style="<?php echo ($pricing->pricing_type ?? '') === 'size_tier' ? '' : 'display:none;'; ?>">
                                <th scope="row"><?php _e('Size Tier Pricing', 'art-print-pricing'); ?></th>
                                <td>
                                    <div class="size-tiers-container">
                                        <?php 
                                        $size_tiers = json_decode($pricing->size_tiers ?? '{}', true);
                                        $sizes = array(20, 24, 32, 40, 48);
                                        foreach ($sizes as $size):
                                            $tier = $size_tiers[$size] ?? array('base' => 0, 'per_cm2' => 0);
                                        ?>
                                        <div class="size-tier">
                                            <label><?php echo $size; ?>" Base Price:</label>
                                            <input type="number" name="frame_pricing[<?php echo $frame->id; ?>][size_tiers][<?php echo $size; ?>][base]" 
                                                   value="<?php echo esc_attr($tier['base']); ?>" step="0.01" min="0">
                                            
                                            <label>Per cm²:</label>
                                            <input type="number" name="frame_pricing[<?php echo $frame->id; ?>][size_tiers][<?php echo $size; ?>][per_cm2]" 
                                                   value="<?php echo esc_attr($tier['per_cm2']); ?>" step="0.0001" min="0">
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endforeach; ?>
                
                <p class="submit">
                    <input type="submit" name="update_frame_pricing" class="button-primary" value="<?php _e('Update Frame Pricing', 'art-print-pricing'); ?>">
                </p>
            </form>
        </div>
        
        <style>
        .frame-pricing-section {
            background: #fff;
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .size-tiers-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .size-tier {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .size-tier label {
            font-weight: bold;
            font-size: 12px;
        }
        
        .size-tier input {
            width: 100%;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.pricing-type-select').on('change', function() {
                var pricingType = $(this).val();
                var section = $(this).closest('.frame-pricing-section');
                
                // Hide all pricing rows
                section.find('.fixed-pricing-row, .per-cm2-pricing-row, .size-tier-pricing-row').hide();
                
                // Show relevant pricing row
                if (pricingType === 'fixed') {
                    section.find('.fixed-pricing-row').show();
                } else if (pricingType === 'per_cm2') {
                    section.find('.per-cm2-pricing-row').show();
                } else if (pricingType === 'size_tier') {
                    section.find('.size-tier-pricing-row').show();
                }
            });
        });
        </script>
        <?php
    }
    
    public function handle_frame_pricing_save() {
        if (!isset($_POST['update_frame_pricing']) || !wp_verify_nonce($_POST['frame_pricing_nonce'], 'save_frame_pricing')) {
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $frame_pricing = $_POST['frame_pricing'] ?? array();
        
        foreach ($frame_pricing as $frame_id => $pricing_data) {
            $frame_id = intval($frame_id);
            $pricing_type = sanitize_text_field($pricing_data['pricing_type']);
            
            $update_data = array(
                'frame_id' => $frame_id,
                'pricing_type' => $pricing_type,
                'base_price' => floatval($pricing_data['base_price'] ?? 0),
                'price_per_cm2' => floatval($pricing_data['price_per_cm2'] ?? 0),
                'min_price' => floatval($pricing_data['min_price'] ?? 0),
                'max_price' => floatval($pricing_data['max_price'] ?? 0)
            );
            
            // Handle size tiers
            if ($pricing_type === 'size_tier' && isset($pricing_data['size_tiers'])) {
                $size_tiers = array();
                foreach ($pricing_data['size_tiers'] as $size => $tier_data) {
                    $size_tiers[$size] = array(
                        'base' => floatval($tier_data['base']),
                        'per_cm2' => floatval($tier_data['per_cm2'])
                    );
                }
                $update_data['size_tiers'] = json_encode($size_tiers);
            }
            
            global $wpdb;
            
            // Check if pricing exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->frame_pricing_table} WHERE frame_id = %d",
                $frame_id
            ));
            
            if ($existing) {
                $wpdb->update($this->frame_pricing_table, $update_data, array('frame_id' => $frame_id));
            } else {
                $wpdb->insert($this->frame_pricing_table, $update_data);
            }
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __('Frame pricing updated successfully!', 'art-print-pricing') . '</p></div>';
        });
    }
    
    public function calculate_frame_price($frame_id, $width_cm, $height_cm) {
        global $wpdb;
        
        $pricing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->frame_pricing_table} WHERE frame_id = %d AND active = 1",
            $frame_id
        ));
        
        if (!$pricing) {
            return 0;
        }
        
        $area_cm2 = $width_cm * $height_cm;
        $price = 0;
        
        switch ($pricing->pricing_type) {
            case 'fixed':
                $price = $pricing->base_price;
                break;
                
            case 'per_cm2':
                $price = $area_cm2 * $pricing->price_per_cm2;
                
                // Apply min/max constraints
                if ($pricing->min_price > 0 && $price < $pricing->min_price) {
                    $price = $pricing->min_price;
                }
                if ($pricing->max_price > 0 && $price > $pricing->max_price) {
                    $price = $pricing->max_price;
                }
                break;
                
            case 'size_tier':
                $size_tiers = json_decode($pricing->size_tiers, true);
                if (!$size_tiers) {
                    return 0;
                }
                
                // Find the appropriate size tier based on the larger dimension
                $larger_dimension = max($width_cm, $height_cm);
                $larger_inches = $larger_dimension / 2.54;
                
                $selected_tier = null;
                foreach ($size_tiers as $size => $tier) {
                    if ($larger_inches <= $size) {
                        $selected_tier = $tier;
                        break;
                    }
                }
                
                // If no tier found, use the largest tier
                if (!$selected_tier) {
                    $largest_size = max(array_keys($size_tiers));
                    $selected_tier = $size_tiers[$largest_size];
                }
                
                $price = $selected_tier['base'] + ($area_cm2 * $selected_tier['per_cm2']);
                break;
        }
        
        return round($price, 2);
    }
    
    public function ajax_save_frame_pricing() {
        check_ajax_referer('app_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $frame_pricing = $_POST['frame_pricing'] ?? array();
        $success = true;
        
        foreach ($frame_pricing as $frame_id => $pricing_data) {
            $frame_id = intval($frame_id);
            $pricing_type = sanitize_text_field($pricing_data['pricing_type']);
            
            $update_data = array(
                'frame_id' => $frame_id,
                'pricing_type' => $pricing_type,
                'base_price' => floatval($pricing_data['base_price'] ?? 0),
                'price_per_cm2' => floatval($pricing_data['price_per_cm2'] ?? 0),
                'min_price' => floatval($pricing_data['min_price'] ?? 0),
                'max_price' => floatval($pricing_data['max_price'] ?? 0)
            );
            
            global $wpdb;
            $result = $wpdb->update($this->frame_pricing_table, $update_data, array('frame_id' => $frame_id));
            
            if ($result === false) {
                $success = false;
            }
        }
        
        if ($success) {
            wp_send_json_success('Frame pricing updated successfully');
        } else {
            wp_send_json_error('Error updating frame pricing');
        }
    }
    
    public function ajax_get_frame_pricing() {
        check_ajax_referer('app_nonce', 'nonce');
        
        $frame_id = intval($_POST['frame_id']);
        $width_cm = floatval($_POST['width_cm']);
        $height_cm = floatval($_POST['height_cm']);
        
        $price = $this->calculate_frame_price($frame_id, $width_cm, $height_cm);
        
        wp_send_json_success(array('price' => $price));
    }
} 