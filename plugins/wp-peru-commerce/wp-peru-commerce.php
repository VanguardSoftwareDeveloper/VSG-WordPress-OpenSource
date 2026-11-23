<?php
/**
 * Plugin Name: PeruCommerce
 * Description: Comercio peruano de productos, archivos digitales y servicios presenciales.
 * Version: 0.1.0-dev
 * Requires at least: 7.1.4
 * Requires PHP: 8.3
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: perucommerce
 * Update URI: https://github.com/VanguardSoftwareDeveloper/wp-peru-commerce
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PERUCOMMERCE_VERSION')) {
    define('PERUCOMMERCE_VERSION', '0.1.0-dev');
}
// Contrato estructural independiente de la versión del paquete PHP.
if (!defined('PERUCOMMERCE_SCHEMA_VERSION')) {
    define('PERUCOMMERCE_SCHEMA_VERSION', '1.0.2');
}
if (!defined('PERUCOMMERCE_PLUGIN_FILE')) {
    define('PERUCOMMERCE_PLUGIN_FILE', __FILE__);
}
if (!defined('PERUCOMMERCE_PLUGIN_DIR')) {
    define('PERUCOMMERCE_PLUGIN_DIR', __DIR__ . '/');
}

require_once PERUCOMMERCE_PLUGIN_DIR . 'includes/bootstrap.php';
\PeruCommerce\Bootstrap::register();
