<?php
/**
 * Shipping Zone Manager Class
 * Handles tiered shipping pricing based on zones and product types
 */

if (!defined('ABSPATH')) {
    exit;
}

class APP_Shipping_Zone_Manager {
    
    private $zones_table;
    
    public function __construct() {
        global $wpdb;
        $this->zones_table = $wpdb->prefix . 'art_print_shipping_zones';
        
        // Create table on activation
        add_action('init', array($this, 'create_zones_table'));
        
        // Add admin hooks
        add_action('admin_menu', array($this, 'add_shipping_menu'));
        add_action('admin_init', array($this, 'handle_shipping_zone_save'));
        
        // Add AJAX handlers
        add_action('wp_ajax_save_shipping_zones', array($this, 'ajax_save_shipping_zones'));
        add_action('wp_ajax_get_shipping_zones', array($this, 'ajax_get_shipping_zones'));
    }
    
    public function create_zones_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->zones_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            zone_name varchar(255) NOT NULL,
            zone_type varchar(50) NOT NULL DEFAULT 'rolled',
            taxable tinyint(1) NOT NULL DEFAULT 0,
            tier_1_units int(11) NOT NULL DEFAULT 1,
            tier_1_cost decimal(10,2) NOT NULL DEFAULT 0.00,
            tier_2_units int(11) NOT NULL DEFAULT 0,
            tier_2_cost decimal(10,2) NOT NULL DEFAULT 0.00,
            tier_3_units int(11) NOT NULL DEFAULT 0,
            tier_3_cost decimal(10,2) NOT NULL DEFAULT 0.00,
            tier_4_units int(11) NOT NULL DEFAULT 0,
            tier_4_cost decimal(10,2) NOT NULL DEFAULT 0.00,
            tier_rest_cost decimal(10,2) NOT NULL DEFAULT 0.00,
            active tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Insert default zones if table is empty
        $this->insert_default_zones();
    }
    
    private function insert_default_zones() {
        global $wpdb;
        
        // Check if zones already exist
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM {$this->zones_table}");
        if ($existing > 0) {
            return;
        }
        
        $default_zones = array(
            array(
                'zone_name' => 'Rouleau - Rolled - Gerollt',
                'zone_type' => 'rolled',
                'taxable' => 0,
                'tier_1_units' => 1,
                'tier_1_cost' => 49.00,
                'tier_2_units' => 100,
                'tier_2_cost' => 2.00,
                'tier_3_units' => 0,
                'tier_3_cost' => 0.00,
                'tier_4_units' => 0,
                'tier_4_cost' => 0.00,
                'tier_rest_cost' => 0.00,
                'sort_order' => 1
            ),
            array(
                'zone_name' => 'Chassis - Stretcher - Rahmen',
                'zone_type' => 'stretcher',
                'taxable' => 0,
                'tier_1_units' => 1,
                'tier_1_cost' => 49.00,
                'tier_2_units' => 5,
                'tier_2_cost' => 18.00,
                'tier_3_units' => 5,
                'tier_3_cost' => 18.00,
                'tier_4_units' => 10,
                'tier_4_cost' => 16.00,
                'tier_rest_cost' => 15.00,
                'sort_order' => 2
            ),
            array(
                'zone_name' => 'Cadre & caisse - Frame & crate',
                'zone_type' => 'framed',
                'taxable' => 0,
                'tier_1_units' => 1,
                'tier_1_cost' => 250.00,
                'tier_2_units' => 5,
                'tier_2_cost' => 35.00,
                'tier_3_units' => 5,
                'tier_3_cost' => 30.00,
                'tier_4_units' => 0,
                'tier_4_cost' => 0.00,
                'tier_rest_cost' => 28.00,
                'sort_order' => 3
            )
        );
        
        foreach ($default_zones as $zone) {
            $wpdb->insert($this->zones_table, $zone);
        }
    }
    
    public function add_shipping_menu() {
        add_submenu_page(
            'woocommerce',
            __('Shipping Zone Tables', 'art-print-pricing'),
            __('Shipping Zones', 'art-print-pricing'),
            'manage_woocommerce',
            'art-print-shipping-zones',
            array($this, 'shipping_zones_page')
        );
    }
    
    public function shipping_zones_page() {
        $zones = $this->get_all_zones();
        ?>
        <div class="wrap">
            <h1><?php _e('SHIPPING ZONE TABLES', 'art-print-pricing'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php _e('These tables are used for the shipping methods; 4, 5, 6 or 7. The <strong>taxable</strong> check box is used by one of the sales tax options.', 'art-print-pricing'); ?></p>
            </div>
            
            <form method="post" action="" id="shipping-zones-form">
                <?php wp_nonce_field('save_shipping_zones', 'shipping_zones_nonce'); ?>
                
                <div class="shipping-zones-header">
                    <label for="num_zones"><?php _e('Number of zones you need to use:', 'art-print-pricing'); ?></label>
                    <select name="num_zones" id="num_zones">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php selected(count($zones), $i); ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="shipping-zones-container">
                    <?php foreach ($zones as $index => $zone): ?>
                        <div class="shipping-zone" data-zone-id="<?php echo $zone->id; ?>">
                            <h3><?php _e('Zone', 'art-print-pricing'); ?> <?php echo $index + 1; ?></h3>
                            
                            <div class="zone-header">
                                <div class="zone-name">
                                    <label><?php _e('NAME OF ZONE', 'art-print-pricing'); ?></label>
                                    <input type="text" name="zones[<?php echo $zone->id; ?>][name]" value="<?php echo esc_attr($zone->zone_name); ?>" class="regular-text">
                                </div>
                                <div class="zone-type">
                                    <label><?php _e('Type', 'art-print-pricing'); ?></label>
                                    <select name="zones[<?php echo $zone->id; ?>][type]">
                                        <option value="rolled" <?php selected($zone->zone_type, 'rolled'); ?>><?php _e('Rolled', 'art-print-pricing'); ?></option>
                                        <option value="stretcher" <?php selected($zone->zone_type, 'stretcher'); ?>><?php _e('Stretcher', 'art-print-pricing'); ?></option>
                                        <option value="framed" <?php selected($zone->zone_type, 'framed'); ?>><?php _e('Framed', 'art-print-pricing'); ?></option>
                                    </select>
                                </div>
                                <div class="zone-taxable">
                                    <label>
                                        <input type="checkbox" name="zones[<?php echo $zone->id; ?>][taxable]" value="1" <?php checked($zone->taxable, 1); ?>>
                                        <?php _e('taxable', 'art-print-pricing'); ?>
                                    </label>
                                </div>
                            </div>
                            
                            <table class="zone-pricing-table">
                                <thead>
                                    <tr>
                                        <th><?php _e('UNITS', 'art-print-pricing'); ?></th>
                                        <th><?php _e('COST', 'art-print-pricing'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?php _e('Up to', 'art-print-pricing'); ?> <input type="number" name="zones[<?php echo $zone->id; ?>][tier_1_units]" value="<?php echo $zone->tier_1_units; ?>" min="0"></td>
                                        <td><input type="number" name="zones[<?php echo $zone->id; ?>][tier_1_cost]" value="<?php echo $zone->tier_1_cost; ?>" step="0.01" min="0"></td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('The next', 'art-print-pricing'); ?> <input type="number" name="zones[<?php echo $zone->id; ?>][tier_2_units]" value="<?php echo $zone->tier_2_units; ?>" min="0"></td>
                                        <td><input type="number" name="zones[<?php echo $zone->id; ?>][tier_2_cost]" value="<?php echo $zone->tier_2_cost; ?>" step="0.01" min="0"></td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('The next', 'art-print-pricing'); ?> <input type="number" name="zones[<?php echo $zone->id; ?>][tier_3_units]" value="<?php echo $zone->tier_3_units; ?>" min="0"></td>
                                        <td><input type="number" name="zones[<?php echo $zone->id; ?>][tier_3_cost]" value="<?php echo $zone->tier_3_cost; ?>" step="0.01" min="0"></td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('The next', 'art-print-pricing'); ?> <input type="number" name="zones[<?php echo $zone->id; ?>][tier_4_units]" value="<?php echo $zone->tier_4_units; ?>" min="0"></td>
                                        <td><input type="number" name="zones[<?php echo $zone->id; ?>][tier_4_cost]" value="<?php echo $zone->tier_4_cost; ?>" step="0.01" min="0"></td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('The rest', 'art-print-pricing'); ?></td>
                                        <td><input type="number" name="zones[<?php echo $zone->id; ?>][tier_rest_cost]" value="<?php echo $zone->tier_rest_cost; ?>" step="0.01" min="0"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <p class="submit">
                    <input type="submit" name="update_shipping" class="button-primary" value="<?php _e('Update Shipping', 'art-print-pricing'); ?>">
                </p>
            </form>
        </div>
        
        <style>
        .shipping-zones-header {
            margin: 20px 0;
            padding: 15px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .shipping-zone {
            background: #fff;
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .zone-header {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .zone-name input,
        .zone-type select {
            width: 100%;
        }
        
        .zone-pricing-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .zone-pricing-table th,
        .zone-pricing-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .zone-pricing-table th {
            background: #f9f9f9;
            font-weight: bold;
        }
        
        .zone-pricing-table input {
            width: 80px;
        }
        
        .submit {
            margin-top: 20px;
        }
        
        .button-primary {
            background: #f39c12 !important;
            border-color: #e67e22 !important;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#num_zones').on('change', function() {
                var numZones = parseInt($(this).val());
                var currentZones = $('.shipping-zone').length;
                
                if (numZones > currentZones) {
                    // Add zones
                    for (var i = currentZones; i < numZones; i++) {
                        addNewZone(i + 1);
                    }
                } else if (numZones < currentZones) {
                    // Remove zones
                    $('.shipping-zone').slice(numZones).remove();
                }
            });
            
            function addNewZone(zoneNumber) {
                var newZone = $('<div class="shipping-zone" data-zone-id="new_' + zoneNumber + '">' +
                    '<h3>Zone ' + zoneNumber + '</h3>' +
                    '<div class="zone-header">' +
                        '<div class="zone-name">' +
                            '<label>NAME OF ZONE</label>' +
                            '<input type="text" name="zones[new_' + zoneNumber + '][name]" class="regular-text">' +
                        '</div>' +
                        '<div class="zone-type">' +
                            '<label>Type</label>' +
                            '<select name="zones[new_' + zoneNumber + '][type]">' +
                                '<option value="rolled">Rolled</option>' +
                                '<option value="stretcher">Stretcher</option>' +
                                '<option value="framed">Framed</option>' +
                            '</select>' +
                        '</div>' +
                        '<div class="zone-taxable">' +
                            '<label><input type="checkbox" name="zones[new_' + zoneNumber + '][taxable]" value="1"> taxable</label>' +
                        '</div>' +
                    '</div>' +
                    '<table class="zone-pricing-table">' +
                        '<thead><tr><th>UNITS</th><th>COST</th></tr></thead>' +
                        '<tbody>' +
                            '<tr><td>Up to <input type="number" name="zones[new_' + zoneNumber + '][tier_1_units]" value="1" min="0"></td><td><input type="number" name="zones[new_' + zoneNumber + '][tier_1_cost]" value="0.00" step="0.01" min="0"></td></tr>' +
                            '<tr><td>The next <input type="number" name="zones[new_' + zoneNumber + '][tier_2_units]" value="0" min="0"></td><td><input type="number" name="zones[new_' + zoneNumber + '][tier_2_cost]" value="0.00" step="0.01" min="0"></td></tr>' +
                            '<tr><td>The next <input type="number" name="zones[new_' + zoneNumber + '][tier_3_units]" value="0" min="0"></td><td><input type="number" name="zones[new_' + zoneNumber + '][tier_3_cost]" value="0.00" step="0.01" min="0"></td></tr>' +
                            '<tr><td>The next <input type="number" name="zones[new_' + zoneNumber + '][tier_4_units]" value="0" min="0"></td><td><input type="number" name="zones[new_' + zoneNumber + '][tier_4_cost]" value="0.00" step="0.01" min="0"></td></tr>' +
                            '<tr><td>The rest</td><td><input type="number" name="zones[new_' + zoneNumber + '][tier_rest_cost]" value="0.00" step="0.01" min="0"></td></tr>' +
                        '</tbody>' +
                    '</table>' +
                '</div>');
                
                $('.shipping-zones-container').append(newZone);
            }
        });
        </script>
        <?php
    }
    
    public function handle_shipping_zone_save() {
        if (!isset($_POST['update_shipping']) || !wp_verify_nonce($_POST['shipping_zones_nonce'], 'save_shipping_zones')) {
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $zones = $_POST['zones'] ?? array();
        
        foreach ($zones as $zone_id => $zone_data) {
            $zone_data = array_map('sanitize_text_field', $zone_data);
            
            $zone_update = array(
                'zone_name' => $zone_data['name'],
                'zone_type' => $zone_data['type'],
                'taxable' => isset($zone_data['taxable']) ? 1 : 0,
                'tier_1_units' => intval($zone_data['tier_1_units']),
                'tier_1_cost' => floatval($zone_data['tier_1_cost']),
                'tier_2_units' => intval($zone_data['tier_2_units']),
                'tier_2_cost' => floatval($zone_data['tier_2_cost']),
                'tier_3_units' => intval($zone_data['tier_3_units']),
                'tier_3_cost' => floatval($zone_data['tier_3_cost']),
                'tier_4_units' => intval($zone_data['tier_4_units']),
                'tier_4_cost' => floatval($zone_data['tier_4_cost']),
                'tier_rest_cost' => floatval($zone_data['tier_rest_cost'])
            );
            
            if (strpos($zone_id, 'new_') === 0) {
                // New zone
                $zone_update['sort_order'] = $this->get_next_sort_order();
                global $wpdb;
                $wpdb->insert($this->zones_table, $zone_update);
            } else {
                // Update existing zone
                global $wpdb;
                $wpdb->update($this->zones_table, $zone_update, array('id' => intval($zone_id)));
            }
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . __('Shipping zones updated successfully!', 'art-print-pricing') . '</p></div>';
        });
    }
    
    private function get_next_sort_order() {
        global $wpdb;
        $max_order = $wpdb->get_var("SELECT MAX(sort_order) FROM {$this->zones_table}");
        return intval($max_order) + 1;
    }
    
    public function get_all_zones() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->zones_table} WHERE active = 1 ORDER BY sort_order ASC");
    }
    
    public function get_zone_by_type($zone_type) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->zones_table} WHERE zone_type = %s AND active = 1 ORDER BY sort_order ASC LIMIT 1",
            $zone_type
        ));
    }
    
    public function calculate_shipping_cost($zone_type, $weight_kg) {
        $zone = $this->get_zone_by_type($zone_type);
        
        if (!$zone) {
            return 0;
        }
        
        $weight_kg = floatval($weight_kg);
        $cost = 0;
        $remaining_weight = $weight_kg;
        
        // Tier 1: Up to tier_1_units
        if ($remaining_weight > 0 && $zone->tier_1_units > 0) {
            $tier_weight = min($remaining_weight, $zone->tier_1_units);
            $cost += $tier_weight * $zone->tier_1_cost;
            $remaining_weight -= $tier_weight;
        }
        
        // Tier 2: The next tier_2_units
        if ($remaining_weight > 0 && $zone->tier_2_units > 0) {
            $tier_weight = min($remaining_weight, $zone->tier_2_units);
            $cost += $tier_weight * $zone->tier_2_cost;
            $remaining_weight -= $tier_weight;
        }
        
        // Tier 3: The next tier_3_units
        if ($remaining_weight > 0 && $zone->tier_3_units > 0) {
            $tier_weight = min($remaining_weight, $zone->tier_3_units);
            $cost += $tier_weight * $zone->tier_3_cost;
            $remaining_weight -= $tier_weight;
        }
        
        // Tier 4: The next tier_4_units
        if ($remaining_weight > 0 && $zone->tier_4_units > 0) {
            $tier_weight = min($remaining_weight, $zone->tier_4_units);
            $cost += $tier_weight * $zone->tier_4_cost;
            $remaining_weight -= $tier_weight;
        }
        
        // The rest
        if ($remaining_weight > 0) {
            $cost += $remaining_weight * $zone->tier_rest_cost;
        }
        
        return round($cost, 2);
    }
    
    /**
     * Calculate shipping cost based on product quantity using tiered ranges.
     * Interprets tier_X_units as the maximum additional units in that tier window.
     * Applies a single cost for the tier in which the total quantity falls (not cumulative).
     */
    public function calculate_shipping_cost_by_quantity($zone_type, $quantity) {
        $zone = $this->get_zone_by_type($zone_type);
        if (!$zone) {
            return 0;
        }
        $q = max(1, intval($quantity));
        $tier1Max = intval($zone->tier_1_units);
        $tier2Max = $tier1Max + intval($zone->tier_2_units);
        $tier3Max = $tier2Max + intval($zone->tier_3_units);
        $tier4Max = $tier3Max + intval($zone->tier_4_units);
        if ($tier1Max > 0 && $q <= $tier1Max) {
            return round(floatval($zone->tier_1_cost), 2);
        }
        if ($zone->tier_2_units > 0 && $q <= $tier2Max) {
            return round(floatval($zone->tier_2_cost), 2);
        }
        if ($zone->tier_3_units > 0 && $q <= $tier3Max) {
            return round(floatval($zone->tier_3_cost), 2);
        }
        if ($zone->tier_4_units > 0 && $q <= $tier4Max) {
            return round(floatval($zone->tier_4_cost), 2);
        }
        return round(floatval($zone->tier_rest_cost), 2);
    }
    
    public function ajax_save_shipping_zones() {
        check_ajax_referer('app_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $zones = $_POST['zones'] ?? array();
        $success = true;
        
        foreach ($zones as $zone_data) {
            $zone_data = array_map('sanitize_text_field', $zone_data);
            
            $zone_update = array(
                'zone_name' => $zone_data['name'],
                'zone_type' => $zone_data['type'],
                'taxable' => isset($zone_data['taxable']) ? 1 : 0,
                'tier_1_units' => intval($zone_data['tier_1_units']),
                'tier_1_cost' => floatval($zone_data['tier_1_cost']),
                'tier_2_units' => intval($zone_data['tier_2_units']),
                'tier_2_cost' => floatval($zone_data['tier_2_cost']),
                'tier_3_units' => intval($zone_data['tier_3_units']),
                'tier_3_cost' => floatval($zone_data['tier_3_cost']),
                'tier_4_units' => intval($zone_data['tier_4_units']),
                'tier_4_cost' => floatval($zone_data['tier_4_cost']),
                'tier_rest_cost' => floatval($zone_data['tier_rest_cost'])
            );
            
            global $wpdb;
            $result = $wpdb->update($this->zones_table, $zone_update, array('id' => intval($zone_data['id'])));
            
            if ($result === false) {
                $success = false;
            }
        }
        
        if ($success) {
            wp_send_json_success('Shipping zones updated successfully');
        } else {
            wp_send_json_error('Error updating shipping zones');
        }
    }
    
    public function ajax_get_shipping_zones() {
        check_ajax_referer('app_nonce', 'nonce');
        
        $zones = $this->get_all_zones();
        wp_send_json_success($zones);
    }
} 