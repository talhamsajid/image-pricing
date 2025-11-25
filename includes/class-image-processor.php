<?php
/**
 * Image Processor Class
 * Handles automatic image dimension extraction and processing
 */

if (!defined('ABSPATH')) {
    exit;
}

class APP_Image_Processor {
    
    public function __construct() {
        // Hook into image upload/update processes
        add_action('add_attachment', array($this, 'process_new_attachment'));
        add_action('edit_attachment', array($this, 'process_updated_attachment'));
        
        // Hook into product image changes
        add_action('woocommerce_process_product_meta', array($this, 'process_product_images'), 20);
        add_action('woocommerce_ajax_save_product_variations', array($this, 'process_variation_images'), 20);
        
        // AJAX handlers
        add_action('wp_ajax_extract_image_dimensions', array($this, 'ajax_extract_dimensions'));
        add_action('wp_ajax_nopriv_extract_image_dimensions', array($this, 'ajax_extract_dimensions'));
    }
    
    /**
     * Process newly uploaded attachments
     */
    public function process_new_attachment($attachment_id) {
        $this->extract_and_store_dimensions($attachment_id);
    }
    
    /**
     * Process updated attachments
     */
    public function process_updated_attachment($attachment_id) {
        $this->extract_and_store_dimensions($attachment_id);
    }
    
    /**
     * Process product images when product is saved
     */
    public function process_product_images($product_id) {
        // Only process if this is an art print product (you can add custom logic here)
        if (!$this->is_art_print_product($product_id)) {
            return;
        }
        
        // Get all product images
        $image_ids = $this->get_product_image_ids($product_id);
        
        if (empty($image_ids)) {
            return;
        }
        
        // Process the first (featured) image for dimensions
        $primary_image_id = $image_ids[0];
        $dimensions = $this->extract_image_dimensions($primary_image_id);
        
        if ($dimensions) {
            // Update product meta with extracted dimensions
            update_post_meta($product_id, '_auto_width_cm', $dimensions['width_cm']);
            update_post_meta($product_id, '_auto_height_cm', $dimensions['height_cm']);
            update_post_meta($product_id, '_auto_width_px', $dimensions['width_px']);
            update_post_meta($product_id, '_auto_height_px', $dimensions['height_px']);
            update_post_meta($product_id, '_last_processed_image_id', $primary_image_id);
            
            // Trigger price recalculation
            $calculator = new APP_Product_Calculator();
            $calculator->update_product_prices($product_id);
        }
    }
    
    /**
     * Check if product should be processed as art print
     */
    private function is_art_print_product($product_id) {
        // Add logic to determine if this is an art print product
        // For now, we'll process all products, but you can add custom logic here
        
        // Example: Check for specific category
        $terms = wp_get_post_terms($product_id, 'product_cat');
        foreach ($terms as $term) {
            if (in_array($term->slug, array('art-prints', 'paintings', 'canvas-prints'))) {
                return true;
            }
        }
        
        // Example: Check for custom field
        $is_art_print = get_post_meta($product_id, '_is_art_print', true);
        if ($is_art_print === 'yes') {
            return true;
        }
        
        // Default to true for now - you can customize this logic
        return true;
    }
    
    /**
     * Get all image IDs associated with a product
     */
    private function get_product_image_ids($product_id) {
        $image_ids = array();
        
        // Featured image
        $featured_image = get_post_thumbnail_id($product_id);
        if ($featured_image) {
            $image_ids[] = $featured_image;
        }
        
        // Gallery images
        $gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
        if ($gallery_ids) {
            $gallery_array = explode(',', $gallery_ids);
            $image_ids = array_merge($image_ids, $gallery_array);
        }
        
        return array_filter($image_ids);
    }
    
    /**
     * Extract dimensions from an image attachment
     */
    public function extract_image_dimensions($attachment_id) {
        $image_path = get_attached_file($attachment_id);
        
        if (!$image_path || !file_exists($image_path)) {
            return false;
        }
        
        // Get basic image info
        $image_info = getimagesize($image_path);
        
        if (!$image_info) {
            return false;
        }
        
        $width_px = $image_info[0];
        $height_px = $image_info[1];
        
        // Convert pixels to centimeters
        // Default assumption: 96 DPI (standard web resolution)
        $dpi = $this->get_image_dpi($image_path) ?: 96;
        
        $width_cm = $this->pixels_to_cm($width_px, $dpi);
        $height_cm = $this->pixels_to_cm($height_px, $dpi);
        
        // Also calculate inches
        $width_in = $width_cm / 2.54;
        $height_in = $height_cm / 2.54;
        
        $dimensions = array(
            'width_px' => $width_px,
            'height_px' => $height_px,
            'width_cm' => round($width_cm, 2),
            'height_cm' => round($height_cm, 2),
            'width_in' => round($width_in, 2),
            'height_in' => round($height_in, 2),
            'dpi' => $dpi,
            'aspect_ratio' => round($width_px / $height_px, 4)
        );
        
        return $dimensions;
    }
    
    /**
     * Extract and store dimensions for an attachment
     */
    public function extract_and_store_dimensions($attachment_id) {
        // Only process images
        if (!wp_attachment_is_image($attachment_id)) {
            return false;
        }
        
        $dimensions = $this->extract_image_dimensions($attachment_id);
        
        if ($dimensions) {
            // Store dimensions as attachment meta
            update_post_meta($attachment_id, '_image_dimensions', $dimensions);
            update_post_meta($attachment_id, '_dimensions_extracted', current_time('mysql'));
            
            return $dimensions;
        }
        
        return false;
    }
    
    /**
     * Get DPI from image EXIF data
     */
    private function get_image_dpi($image_path) {
        if (!function_exists('exif_read_data')) {
            return false;
        }
        
        $exif = @exif_read_data($image_path);
        
        if (!$exif) {
            return false;
        }
        
        // Check for resolution in EXIF
        if (isset($exif['XResolution']) && isset($exif['ResolutionUnit'])) {
            $resolution = $exif['XResolution'];
            $unit = $exif['ResolutionUnit'];
            
            // Convert fraction to decimal
            if (strpos($resolution, '/') !== false) {
                $parts = explode('/', $resolution);
                $resolution = $parts[0] / $parts[1];
            }
            
            // Convert to DPI if needed
            if ($unit == 3) { // Centimeters
                $resolution = $resolution * 2.54;
            }
            
            return round($resolution);
        }
        
        return false;
    }
    
    /**
     * Convert pixels to centimeters
     */
    private function pixels_to_cm($pixels, $dpi = 96) {
        // 1 inch = 2.54 cm
        return ($pixels / $dpi) * 2.54;
    }
    
    /**
     * Convert centimeters to pixels
     */
    private function cm_to_pixels($cm, $dpi = 96) {
        // 1 inch = 2.54 cm
        return round(($cm / 2.54) * $dpi);
    }
    
    /**
     * AJAX handler for dimension extraction
     */
    public function ajax_extract_dimensions() {
        check_ajax_referer('app_nonce', 'nonce');
        
        $attachment_id = intval($_POST['attachment_id']);
        
        if (!$attachment_id) {
            wp_send_json_error('Invalid attachment ID');
        }
        
        $dimensions = $this->extract_and_store_dimensions($attachment_id);
        
        if ($dimensions) {
            wp_send_json_success(array(
                'dimensions' => $dimensions,
                'message' => 'Dimensions extracted successfully'
            ));
        } else {
            wp_send_json_error('Could not extract image dimensions');
        }
    }
    
    /**
     * AJAX handler for getting image dimensions (frontend use)
     */
    public function get_image_dimensions_ajax() {
        $product_id = intval($_POST['product_id']);
        
        if (!$product_id) {
            return array('success' => false, 'message' => 'Invalid product ID');
        }
        
        $image_ids = $this->get_product_image_ids($product_id);
        
        if (empty($image_ids)) {
            return array('success' => false, 'message' => 'No images found for this product');
        }
        
        $primary_image_id = $image_ids[0];
        $dimensions = $this->extract_image_dimensions($primary_image_id);
        
        if ($dimensions) {
            return array(
                'success' => true,
                'dimensions' => $dimensions,
                'image_id' => $primary_image_id
            );
        } else {
            return array('success' => false, 'message' => 'Could not extract dimensions from image');
        }
    }
    
    /**
     * Get cached dimensions for an image
     */
    public function get_cached_dimensions($attachment_id) {
        return get_post_meta($attachment_id, '_image_dimensions', true);
    }
    
    /**
     * Get optimal print sizes based on image dimensions and aspect ratio
     */
    public function get_optimal_print_sizes($width_cm, $height_cm) {
        $aspect_ratio = $width_cm / $height_cm;
        $optimal_sizes = array();
        
        // Standard print sizes in inches
        $standard_sizes = array(20, 24, 32, 40, 48);
        
        foreach ($standard_sizes as $size) {
            // Calculate the corresponding size for the shorter side
            if ($width_cm >= $height_cm) {
                // Landscape orientation
                $longer_side = $size;
                $shorter_side = round($size / $aspect_ratio);
            } else {
                // Portrait orientation
                $shorter_side = $size;
                $longer_side = round($size * $aspect_ratio);
            }
            
            $optimal_sizes[$size] = array(
                'size_inches' => $size,
                'width_inches' => $width_cm >= $height_cm ? $longer_side : $shorter_side,
                'height_inches' => $width_cm >= $height_cm ? $shorter_side : $longer_side,
                'width_cm' => round(($width_cm >= $height_cm ? $longer_side : $shorter_side) * 2.54, 2),
                'height_cm' => round(($width_cm >= $height_cm ? $shorter_side : $longer_side) * 2.54, 2)
            );
        }
        
        return $optimal_sizes;
    }
    
    /**
     * Check if image has sufficient resolution for print
     */
    public function check_print_quality($width_px, $height_px, $target_width_cm, $target_height_cm) {
        // Calculate required pixels for 300 DPI print
        $required_width_px = $this->cm_to_pixels($target_width_cm, 300);
        $required_height_px = $this->cm_to_pixels($target_height_cm, 300);
        
        $width_quality = ($width_px / $required_width_px) * 100;
        $height_quality = ($height_px / $required_height_px) * 100;
        
        $overall_quality = min($width_quality, $height_quality);
        
        return array(
            'quality_percentage' => round($overall_quality, 1),
            'is_print_ready' => $overall_quality >= 80, // 80% of 300 DPI = 240 DPI minimum
            'recommendation' => $this->get_quality_recommendation($overall_quality)
        );
    }
    
    /**
     * Get quality recommendation based on percentage
     */
    private function get_quality_recommendation($quality_percentage) {
        if ($quality_percentage >= 100) {
            return 'Excellent quality for professional printing';
        } elseif ($quality_percentage >= 80) {
            return 'Good quality for standard printing';
        } elseif ($quality_percentage >= 60) {
            return 'Acceptable quality, may show some pixelation';
        } else {
            return 'Poor quality, consider using a higher resolution image';
        }
    }
    
    /**
     * Process variation images (for variable products)
     */
    public function process_variation_images($product_id) {
        // Get all variations
        $product = wc_get_product($product_id);
        
        if (!$product || !$product->is_type('variable')) {
            return;
        }
        
        $variations = $product->get_children();
        
        foreach ($variations as $variation_id) {
            $variation_image_id = get_post_meta($variation_id, '_thumbnail_id', true);
            
            if ($variation_image_id) {
                $dimensions = $this->extract_image_dimensions($variation_image_id);
                
                if ($dimensions) {
                    update_post_meta($variation_id, '_auto_width_cm', $dimensions['width_cm']);
                    update_post_meta($variation_id, '_auto_height_cm', $dimensions['height_cm']);
                }
            }
        }
    }
}