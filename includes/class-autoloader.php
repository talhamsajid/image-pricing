<?php
namespace WC_Image_Pricing;

class Autoloader {
    public static function register() {
        spl_autoload_register( [ __CLASS__, 'autoload' ] );
    }

    public static function autoload( $class ) {
        $prefix = __NAMESPACE__ . '\\';
        // only load our namespace
        if ( 0 !== strpos( $class, $prefix ) ) {
            return;
        }

        // Strip namespace, convert underscores to hyphens, lowercase, prefix with 'class-'
        $class_name = substr( $class, strlen( $prefix ) );
        $file       = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

        $path = WC_IMAGE_PRICING_PATH . 'includes/' . $file;
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }
}