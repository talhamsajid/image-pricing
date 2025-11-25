<?php
/**
 * Frame Manager Class
 * Handles frame options as custom post type or database table
 */

if (!defined('ABSPATH')) {
    exit;
}

class APP_Frame_Manager {
    
    public function __construct() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX handlers
        add_action('wp_ajax_add_frame', array($this, 'ajax_add_frame'));
        add_action('wp_ajax_update_frame', array($this, 'ajax_update_frame'));
        add_action('wp_ajax_delete_frame', array($this, 'ajax_delete_frame'));
        add_action('wp_ajax_upload_frame_image', array($this, 'ajax_upload_frame_image'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Art Print Frames', 'art-print-pricing'),
            __('Art Print Frames', 'art-print-pricing'),
            'manage_woocommerce',
            'art-print-frames',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        $frames = $this->get_all_frames();
        ?>
        <div class="wrap">
            <h1><?php _e('Art Print Frames Management', 'art-print-pricing'); ?></h1>
            
            <div class="frame-manager-container">
                <!-- Add New Frame Form -->
                <div class="add-frame-section">
                    <h2><?php _e('Add New Frame', 'art-print-pricing'); ?></h2>
                    <form id="add-frame-form" class="frame-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="frame-name"><?php _e('Frame Name', 'art-print-pricing'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="frame-name" name="name" class="regular-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="frame-description"><?php _e('Description', 'art-print-pricing'); ?></label>
                                </th>
                                <td>
                                    <textarea id="frame-description" name="description" class="large-text" rows="3"></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="frame-price"><?php _e('Additional Price', 'art-print-pricing'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="frame-price" name="price" step="0.01" min="0" class="regular-text" required>
                                    <p class="description"><?php _e('Additional cost for this frame option', 'art-print-pricing'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="frame-shipping-type"><?php _e('Shipping Type', 'art-print-pricing'); ?></label>
                                </th>
                                <td>
                                    <select id="frame-shipping-type" name="shipping_type" class="regular-text">
                                        <option value="rolled"><?php _e('Rolled', 'art-print-pricing'); ?></option>
                                        <option value="stretcher"><?php _e('Stretcher', 'art-print-pricing'); ?></option>
                                        <option value="framed"><?php _e('Framed', 'art-print-pricing'); ?></option>
                                    </select>
                                    <p class="description"><?php _e('Determines which shipping zone table applies when this frame is selected.', 'art-print-pricing'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="frame-image"><?php _e('Frame Image', 'art-print-pricing'); ?></label>
                                </th>
                                <td>
                                    <input type="hidden" id="frame-image-url" name="image_url">
                                    <div class="frame-image-preview">
                                        <img id="frame-image-preview" src="" alt="" style="display:none; max-width: 100px; height: auto;">
                                    </div>
                                    <button type="button" id="upload-frame-image" class="button"><?php _e('Upload Image', 'art-print-pricing'); ?></button>
                                    <button type="button" id="remove-frame-image" class="button" style="display:none;"><?php _e('Remove Image', 'art-print-pricing'); ?></button>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="frame-sort-order"><?php _e('Sort Order', 'art-print-pricing'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="frame-sort-order" name="sort_order" min="0" class="small-text" value="0">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="frame-active"><?php _e('Active', 'art-print-pricing'); ?></label>
                                </th>
                                <td>
                                    <input type="checkbox" id="frame-active" name="active" value="1" checked>
                                    <label for="frame-active"><?php _e('Enable this frame option', 'art-print-pricing'); ?></label>
                                </td>
                            </tr>
                        </table>
                        
                        <?php wp_nonce_field('add_frame', 'add_frame_nonce'); ?>
                        <p class="submit">
                            <input type="submit" class="button-primary" value="<?php _e('Add Frame', 'art-print-pricing'); ?>">
                        </p>
                    </form>
                </div>
                
                <!-- Existing Frames List -->
                <div class="frames-list-section">
                    <h2><?php _e('Existing Frames', 'art-print-pricing'); ?></h2>
                    <div id="frames-list">
                        <?php $this->render_frames_list($frames); ?>
                    </div>
                    <input type="hidden" id="delete_frame_nonce" value="<?php echo esc_attr(wp_create_nonce('delete_frame')); ?>">
                </div>
            </div>
        </div>
        
        <!-- Edit Frame Modal -->
        <div id="edit-frame-modal" class="frame-modal" style="display:none;">
            <div class="frame-modal-content">
                <span class="frame-modal-close">&times;</span>
                <h2><?php _e('Edit Frame', 'art-print-pricing'); ?></h2>
                <form id="edit-frame-form" class="frame-form">
                    <input type="hidden" id="edit-frame-id" name="frame_id">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="edit-frame-name"><?php _e('Frame Name', 'art-print-pricing'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="edit-frame-name" name="name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit-frame-description"><?php _e('Description', 'art-print-pricing'); ?></label>
                            </th>
                            <td>
                                <textarea id="edit-frame-description" name="description" class="large-text" rows="3"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit-frame-price"><?php _e('Additional Price', 'art-print-pricing'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="edit-frame-price" name="price" step="0.01" min="0" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit-frame-shipping-type"><?php _e('Shipping Type', 'art-print-pricing'); ?></label>
                            </th>
                            <td>
                                <select id="edit-frame-shipping-type" name="shipping_type" class="regular-text">
                                    <option value="rolled"><?php _e('Rolled', 'art-print-pricing'); ?></option>
                                    <option value="stretcher"><?php _e('Stretcher', 'art-print-pricing'); ?></option>
                                    <option value="framed"><?php _e('Framed', 'art-print-pricing'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit-frame-image"><?php _e('Frame Image', 'art-print-pricing'); ?></label>
                            </th>
                            <td>
                                <input type="hidden" id="edit-frame-image-url" name="image_url">
                                <div class="frame-image-preview">
                                    <img id="edit-frame-image-preview" src="" alt="" style="max-width: 100px; height: auto;">
                                </div>
                                <button type="button" id="edit-upload-frame-image" class="button"><?php _e('Upload Image', 'art-print-pricing'); ?></button>
                                <button type="button" id="edit-remove-frame-image" class="button"><?php _e('Remove Image', 'art-print-pricing'); ?></button>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit-frame-sort-order"><?php _e('Sort Order', 'art-print-pricing'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="edit-frame-sort-order" name="sort_order" min="0" class="small-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="edit-frame-active"><?php _e('Active', 'art-print-pricing'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" id="edit-frame-active" name="active" value="1">
                                <label for="edit-frame-active"><?php _e('Enable this frame option', 'art-print-pricing'); ?></label>
                            </td>
                        </tr>
                    </table>
                    
                    <?php wp_nonce_field('edit_frame', 'edit_frame_nonce'); ?>
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e('Update Frame', 'art-print-pricing'); ?>">
                        <button type="button" class="button frame-modal-close"><?php _e('Cancel', 'art-print-pricing'); ?></button>
                    </p>
                </form>
            </div>
        </div>
        
        <style>
        .frame-manager-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        
        .add-frame-section,
        .frames-list-section {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .frames-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .frames-table th,
        .frames-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .frames-table th {
            background-color: #f1f1f1;
            font-weight: 600;
        }
        
        .frame-image-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .frame-actions {
            display: flex;
            gap: 5px;
        }
        
        .frame-modal {
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .frame-modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: none;
            border-radius: 4px;
            width: 80%;
            max-width: 600px;
            position: relative;
        }
        
        .frame-modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            right: 15px;
            top: 10px;
            cursor: pointer;
        }
        
        .frame-modal-close:hover {
            color: black;
        }
        
        .status-active {
            color: #46b450;
            font-weight: 600;
        }
        
        .status-inactive {
            color: #dc3232;
            font-weight: 600;
        }
        </style>
        <?php
    }
    
    public function render_frames_list($frames) {
        if (empty($frames)) {
            echo '<p>' . __('No frames found. Add your first frame above.', 'art-print-pricing') . '</p>';
            return;
        }
        
        ?>
        <table class="frames-table wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Image', 'art-print-pricing'); ?></th>
                    <th><?php _e('Name', 'art-print-pricing'); ?></th>
                    <th><?php _e('Price', 'art-print-pricing'); ?></th>
                    <th><?php _e('Status', 'art-print-pricing'); ?></th>
                    <th><?php _e('Order', 'art-print-pricing'); ?></th>
                    <th><?php _e('Actions', 'art-print-pricing'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($frames as $frame): ?>
                <tr data-frame-id="<?php echo esc_attr($frame->id); ?>">
                    <td>
                        <?php if ($frame->image_url): ?>
                        <img src="<?php echo esc_url($frame->image_url); ?>" 
                             alt="<?php echo esc_attr($frame->name); ?>" 
                             class="frame-image-thumb">
                        <?php else: ?>
                        <div class="frame-placeholder">—</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo esc_html($frame->name); ?></strong>
                        <?php if ($frame->description): ?>
                        <br><small><?php echo esc_html($frame->description); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo wc_price($frame->price); ?></td>
                    <td>
                        <span class="<?php echo $frame->active ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $frame->active ? __('Active', 'art-print-pricing') : __('Inactive', 'art-print-pricing'); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($frame->sort_order); ?></td>
                    <td>
                        <div class="frame-actions">
                            <button type="button" class="button button-small edit-frame" 
                                    data-frame-id="<?php echo esc_attr($frame->id); ?>">
                                <?php _e('Edit', 'art-print-pricing'); ?>
                            </button>
                            <button type="button" class="button button-small delete-frame" 
                                    data-frame-id="<?php echo esc_attr($frame->id); ?>">
                                <?php _e('Delete', 'art-print-pricing'); ?>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    public function get_all_frames() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'art_print_frames';
        
        return $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY sort_order ASC, name ASC"
        );
    }
    
    public function get_frame($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'art_print_frames';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));
    }
    
    public function get_active_frames() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'art_print_frames';
        
        return $wpdb->get_results(
            "SELECT * FROM $table_name WHERE active = 1 ORDER BY sort_order ASC, name ASC"
        );
    }
    
    public function ajax_add_frame() {
        check_ajax_referer('add_frame', 'add_frame_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);
        $price = floatval($_POST['price']);
        $image_url = esc_url_raw($_POST['image_url']);
        $sort_order = intval($_POST['sort_order']);
        $active = isset($_POST['active']) ? 1 : 0;
        
        if (empty($name)) {
            wp_send_json_error('Frame name is required');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'art_print_frames';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'image_url' => $image_url,
                'shipping_type' => sanitize_text_field($_POST['shipping_type'] ?? 'rolled'),
                'sort_order' => $sort_order,
                'active' => $active
            ),
            array('%s', '%s', '%f', '%s', '%s', '%d', '%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to add frame');
        }
        
        $frames = $this->get_all_frames();
        
        ob_start();
        $this->render_frames_list($frames);
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'message' => 'Frame added successfully',
            'html' => $html
        ));
    }
    
    public function ajax_update_frame() {
        check_ajax_referer('edit_frame', 'edit_frame_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $frame_id = intval($_POST['frame_id']);
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);
        $price = floatval($_POST['price']);
        $image_url = esc_url_raw($_POST['image_url']);
        $sort_order = intval($_POST['sort_order']);
        $active = isset($_POST['active']) ? 1 : 0;
        
        if (empty($name)) {
            wp_send_json_error('Frame name is required');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'art_print_frames';
        
        $result = $wpdb->update(
            $table_name,
            array(
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'image_url' => $image_url,
                'shipping_type' => sanitize_text_field($_POST['shipping_type'] ?? 'rolled'),
                'sort_order' => $sort_order,
                'active' => $active
            ),
            array('id' => $frame_id),
            array('%s', '%s', '%f', '%s', '%s', '%d', '%d'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to update frame');
        }
        
        $frames = $this->get_all_frames();
        
        ob_start();
        $this->render_frames_list($frames);
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'message' => 'Frame updated successfully',
            'html' => $html
        ));
    }
    
    public function ajax_delete_frame() {
        check_ajax_referer('delete_frame', 'delete_frame_nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $frame_id = intval($_POST['frame_id']);
        
        // Don't allow deletion of the default "No Frame" option
        if ($frame_id === 1) {
            wp_send_json_error('Cannot delete the default frame option');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'art_print_frames';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $frame_id),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to delete frame');
        }
        
        $frames = $this->get_all_frames();
        
        ob_start();
        $this->render_frames_list($frames);
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'message' => 'Frame deleted successfully',
            'html' => $html
        ));
    }
    
    public function ajax_upload_frame_image() {
        check_ajax_referer('upload_frame_image', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('No image uploaded or upload error');
        }
        
        $uploaded_file = wp_handle_upload($_FILES['image'], array('test_form' => false));
        
        if (isset($uploaded_file['error'])) {
            wp_send_json_error($uploaded_file['error']);
        }
        
        wp_send_json_success(array(
            'url' => $uploaded_file['url']
        ));
    }
}