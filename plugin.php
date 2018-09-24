<?php

/**
 * Plugin Name: CasAdapter
 * Plugin URI: https://github.com/paudebau/wp-casadapter
 * Description: CAS Authentication/Registration Client for Wordpress.
 * Version: 0.2.0
 * Requires PHP: 5.4.45
 * Author: Philippe Audebaud
 * Author URI: http://paudebau.github.io/
 * License: GPLv3
 */

/*  resort, define('USE_CASADAPTER', false) disable this functionality */
if (defined('USE_CASADAPTER') && (USE_CASADAPTER === false)) {
    return;
}

// https://codex.wordpress.org/Writing_a_Plugin
if (!defined('ABSPATH')) {
    header('HTTP/1.1 403 Forbidden');
    exit(0);
}

if (!function_exists('get_plugin_data')) {
    require_once ABSPATH . '/wp-admin/includes/plugin.php';
}

if (!in_array('phpcas4wp/plugin.php', get_option('active_plugins'))) {
    $this_plugin = plugin_basename(__FILE__);
    deactivate_plugins($this_plugin);
    wp_die(__('The "phpcas" plugin must installed and activated.'), $this_plugin);
}

if (!class_exists('CAS')) {
    require_once dirname(__DIR__) . "/phpcas4wp/source/CAS.php";
}

function casAdapter_autoload($class_name) {
    $file = __DIR__ . "/classes/{$class_name}.php";
    if (file_exists($file)) {
        require_once $file;
    }
}

spl_autoload_register(casAdapter_autoload);

CasAdapter::init();

?>
