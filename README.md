# Image Pricing for WooCommerce

A comprehensive WooCommerce plugin that automatically calculates pricing for art prints based on image dimensions, with frame options and shipping calculations - replicating Paolo's custom PHP logic.

## Features

### 🖼️ Automatic Image Dimension Detection
- Extracts dimensions from uploaded product images automatically
- Supports EXIF data for accurate DPI detection
- Converts pixels to centimeters and inches using proper DPI calculations
- Stores dimensions as product meta for future use

### 💰 Advanced Pricing Calculator
- Implements Paolo's exact pricing formula: `(Width × Height × Coefficient × Difficulty) + Shipping`
- Supports multiple predefined sizes (20", 24", 32", 40", 48")
- **Product type selection**: Photo vs Painting with different pricing
- **Difficulty multipliers**: Easy (2x), Medium (3x), Hard (4x), Very Hard (9x)
- **Separate pricing** for prints vs hand-painted options
- **Real-time price updates** on frontend with AJAX

### 🖼️ Frame Management System
- **Size-based frame pricing** with multiple calculation methods
- **Three pricing types**: Fixed, Per Square Centimeter, and Size Tiered
- **Custom frame options** with individual pricing
- **Frame image gallery support**
- **Easy-to-use admin interface** for managing frame options
- **Dynamic frame cost calculation** based on actual dimensions

### 📦 Intelligent Shipping Calculation
- **Zone-based shipping pricing** with tiered rates for different product types
- **Three shipping methods**: Rolled, On Stretcher, and Framed
- **Configurable shipping zones** with admin interface
- **Weight-based calculations** using Paolo's formula
- **Automatic population** of WooCommerce shipping fields

### 🎨 Modern Frontend Interface
- Elegant size selection buttons (inspired by ArtByMaudsch.com)
- Unit toggle (inches/centimeters)
- Interactive frame selection with visual previews
- Real-time price updates
- Mobile-responsive design

### ⚙️ Manual Override Capabilities
- Manual dimension input to override automatic calculations
- Admin controls for fine-tuning pricing
- Bulk recalculation tools

## Installation

1. Upload the plugin files to `/wp-content/plugins/art-print-pricing/`
2. Activate the plugin through the WordPress admin
3. Ensure WooCommerce is installed and active
4. Configure settings under WooCommerce → Art Print Settings

## New Features (v2.0)

### 🚀 Zone-Based Shipping System
- **Shipping Zone Tables**: Configure tiered pricing for different shipping methods
- **Three Shipping Types**: Rolled, On Stretcher, and Framed with separate pricing
- **Admin Interface**: Easy-to-use shipping zone configuration under WooCommerce → Shipping Zones
- **Dynamic Calculation**: Real-time shipping cost calculation based on weight and zone

### 🖼️ Advanced Frame Pricing
- **Size-Based Pricing**: Frame costs calculated based on actual dimensions
- **Multiple Pricing Methods**: Fixed, Per Square Centimeter, or Size Tiered pricing
- **Admin Configuration**: Configure frame pricing under WooCommerce → Frame Pricing
- **Dynamic Updates**: Frame prices update automatically when size changes

### 🎨 Product Type Selection
- **Photo vs Painting**: Customers can choose between photo prints and hand-painted options
- **Different Pricing**: Each product type has its own pricing structure
- **Clear Interface**: Intuitive selection buttons for product type and shipping method

## Plugin Structure

```
art-print-pricing/
├── art-print-pricing.php          # Main plugin file
├── includes/
│   ├── class-admin-settings.php   # Admin settings and configuration
│   ├── class-product-calculator.php # Core pricing calculation logic
│   ├── class-frontend-display.php # Frontend product display
│   ├── class-image-processor.php  # Image dimension extraction
│   └── class-frame-manager.php    # Frame options management
├── assets/
│   ├── css/
│   │   ├── frontend.css           # Frontend styling
│   │   └── admin.css              # Admin interface styling
│   └── js/
│       ├── frontend.js            # Frontend functionality
│       └── admin.js               # Admin functionality
└── README.md
```

## Configuration

### Basic Setup

1. **Product Categories**: Select which product categories should use art print pricing
2. **Base Coefficient**: Set the base pricing coefficient (default: 0.009)
3. **Available Sizes**: Configure available print sizes (default: 20", 24", 32", 40", 48")
4. **Painted Multiplier**: Set the multiplier for hand-painted options (default: 3.5x)
5. **DPI Settings**: Configure minimum print DPI (default: 240)

### Frame Configuration

Navigate to **WooCommerce → Frames** to:
- Add custom frame options
- Set frame pricing (fixed or size-based)
- Upload frame preview images
- Configure shipping type per frame (rolled/framed)

### Frame Pricing Setup

Navigate to **WooCommerce → Frame Pricing** to:
- Configure size-based frame pricing
- Choose pricing method: Fixed, Per Square Centimeter, or Size Tiered
- Set pricing tiers for different size ranges

### Shipping Zones

Navigate to **WooCommerce → Shipping Zones** to:
- Create shipping zones
- Configure tiered pricing for each shipping method
- Set weight-based rates for rolled, stretched, and framed shipping

## Usage

### For Store Owners

1. **Upload Product Images**: Add high-quality images to your products
2. **Automatic Processing**: The plugin automatically extracts dimensions from images
3. **Review Pricing**: Check the calculated prices in the product edit screen
4. **Manual Override**: Adjust dimensions or pricing manually if needed
5. **Bulk Operations**: Use bulk tools to recalculate prices or extract dimensions for all products

### For Customers

1. **Select Size**: Choose from available print sizes
2. **Choose Type**: Select between photo print or hand-painted
3. **Pick Frame**: Select frame option (or no frame)
4. **View Price**: See real-time price updates including shipping
5. **Add to Cart**: Complete purchase with calculated pricing

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.4 or higher
- GD Library or Imagick for image processing

## Frequently Asked Questions

**Q: How are prices calculated?**  
A: Prices are calculated using the formula: `(Width × Height × Coefficient × Difficulty) + Frame Cost + Shipping`

**Q: Can I override automatic dimension detection?**  
A: Yes, you can manually enter dimensions in the product edit screen.

**Q: Does it work with variable products?**  
A: Currently, the plugin is designed for simple products with image-based pricing.

**Q: Can I customize the available sizes?**  
A: Yes, you can configure available sizes in the plugin settings.

## Changelog

### Version 1.0.0
- Initial release
- Automatic image dimension detection
- Dynamic pricing calculator
- Frame management system
- Zone-based shipping
- Frontend interface with real-time updates

## Support

For support, please open an issue on the GitHub repository.

## Author

**Talha Munawar**

## License

GPL v2 or later