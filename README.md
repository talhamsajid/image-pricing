# Art Print Pricing Calculator for WooCommerce

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
2.