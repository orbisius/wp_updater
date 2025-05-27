<?php
/**
 * Simple updater script for one demo site.
 * Add this script to the root of your demo WordPress installation where wp-config.php is located.
 *
 * Works via browser or command line interface (CLI).
 *
 * Usage:
 *   Browser: https://yourdemo.com/000_wp_updater.php?go=SomeSmartCode
 *   CLI:     php 000_wp_updater.php SomeSmartCode
 *
 * WARNING:
 * ------------
 * This script runs WordPress core, plugin, and theme updates with no backups.
 * It is meant for demo environments only.
 * Do NOT upload this to your main site or production folder!
 * Always place it inside a folder that contains only demo installs.
 *
 * DISCLAIMER:
 * ------------
 * This script is provided "as-is" with no warranties.
 * Use it at your own risk. The author (Svetoslav Marinov | https://orbisius.com)
 * is not responsible for any damage, data loss, or downtime caused by its use.
 */

$code = 'SomeSmartCode';

putenv('WP_CLI_CACHE_DIR=/dev/null');
putenv('WP_CLI_DISABLE_AUTO_CHECK_UPDATE=1');

// Error reporting settings
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Output buffering settings
ini_set('implicit_flush', 1);
ini_set('output_buffering', 0);
ini_set('zlib.output_compression', 0);

// Disable PHP's output buffering
ob_implicit_flush(1);

if (PHP_SAPI === 'cli') {
    $input = !empty($argv[1]) ? $argv[1] : '';
} else {
    $input = !empty($_REQUEST['go']) ? $_REQUEST['go'] : '';
}

$input = trim($input);

if ($input !== $code) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(403);
        header('Content-Type: text/plain');
    }
    exit("Access Denied\n");
}

// Disallow running as root for safety
if (PHP_SAPI === 'cli' && function_exists('posix_geteuid') && posix_geteuid() == 0) {
    exit("ERROR: Do not run this script as root. Use a non-privileged user (like www-data).\n");
}

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain');
}

$wp_config = __DIR__ . '/wp-config.php';

if (!file_exists($wp_config)) {
    exit("ERROR: wp-config.php not found in the current directory.\n");
}

$start_time = microtime(true);

echo "Starting updater ...\n";
echo str_repeat('-', 50) . "\n";
app_flush();

// Check if multisite: used only for plugin/theme updates
$output = [];
$exitCode = 1;
$extraCmdFlags = '';
exec('wp core is-installed --network 2>&1', $output, $exitCode);

if (empty($exitCode)) {
    echo "Multisite detected. Using --network for plugin/theme updates.\n";
    app_flush();
    $extraCmdFlags = '--network';
}

$extraCmdFlags .= ' 2>&1';

echo "Updating all WordPress plugins ...\n";
app_flush();
echo shell_exec("wp plugin update --all $extraCmdFlags");
app_flush();

// Check if WooCommerce is active
$output = [];
$exitCode = 1;

exec("wp plugin is-active woocommerce $extraCmdFlags", $output, $exitCode);

if (empty($exitCode)) {
    echo "WooCommerce is active. Running WC DB update...\n";
    app_flush();
    echo shell_exec("wp wc update $extraCmdFlags");
    app_flush();
}

// Check if Elementor is active
$output = [];
$exitCode = 1;

exec("wp plugin is-active elementor $extraCmdFlags", $output, $exitCode);

if (empty($exitCode)) {
    echo "Elementor is active. Running Elementor DB update...\n";
    app_flush();
    echo shell_exec("wp elementor update db $extraCmdFlags");
    app_flush();
}

echo "Updating all WordPress themes ...\n";
app_flush();

echo shell_exec("wp theme update --all $extraCmdFlags");
app_flush();

echo "Updating WordPress...\n";
app_flush();

echo shell_exec('wp core update 2>&1');
app_flush();

echo shell_exec('wp core update-db 2>&1');
app_flush();

$exec_time = round(microtime(true) - $start_time, 4);
echo "Updater completed in $exec_time ...\n";
echo str_repeat('-', 50) . "\n";
app_flush();

function app_flush()
{   
    if (PHP_SAPI === 'cli') {
        return;
    }

    echo str_repeat("\t", 512) . "\n"; // Add some spacing to ensure the web server sends the output
    @ob_flush();
    flush();
}

