<?php

/*

Plugin Name: csf-tree-term
Plugin URI:
Description: Add customized tree term to custom field suite types
Version: 1.0
Author: anonymous
License: GPLv2 or later
Text Domain: csf-tree-term

*/

define( 'CFS_CUSTOM_DIR', dirname( __FILE__ ) );

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter('cfs_field_types', 'add_custom_term_field', 10, 1);
// add_action( 'admin_enqueue_scripts', 'custom_style' );
add_action( 'plugins_loaded', 'init' );
// add_action( 'wp_enqueue_scripts', 'custom_style' );

// add_action( 'admin_enqueue_scripts', 'custom_style' );
function add_custom_term_field($field_array) {
    $field_array['treeterm'] = CFS_CUSTOM_DIR . '/treeterm/treeterm.php';

    return $field_array;
}

function init() {
    add_action( 'admin_enqueue_scripts', 'custom_style' );
}

function custom_style() {
    wp_enqueue_style( 'custom_css_for_treeterm', plugin_dir_url( __FILE__ ) . 'treeterm/custom.css' );
}