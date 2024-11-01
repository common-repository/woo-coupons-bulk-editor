<?php

defined( 'ABSPATH' ) || exit;
if ( !function_exists( 'wpsewcc_fs' ) ) {
    // Create a helper function for easy SDK access.
    function wpsewcc_fs() {
        global $wpsewcc_fs;
        if ( !isset( $wpsewcc_fs ) ) {
            if ( !defined( 'WP_FS__PRODUCT_2808_MULTISITE' ) ) {
                define( 'WP_FS__PRODUCT_2808_MULTISITE', true );
            }
            $wpsewcc_fs = fs_dynamic_init( array(
                'id'             => '2808',
                'slug'           => 'woo-coupons-bulk-editor',
                'type'           => 'plugin',
                'public_key'     => 'pk_3c3f2bb6a98019a61f74074759e07',
                'is_premium'     => false,
                'has_addons'     => false,
                'has_paid_plans' => true,
                'menu'           => array(
                    'slug'       => 'wpsewcc_welcome_page',
                    'first-path' => 'admin.php?page=wpsewcc_welcome_page',
                    'support'    => false,
                ),
                'is_live'        => true,
            ) );
        }
        return $wpsewcc_fs;
    }

    // Init Freemius.
    wpsewcc_fs();
    // Signal that SDK was initiated.
    do_action( 'wpsewcc_fs_loaded' );
}