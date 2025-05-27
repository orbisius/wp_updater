<?php
/**
 * Full WordPress site updater script. Works via browser or command line interface (CLI).
 * Add this script to the root of your WordPress installation where wp-config.php is located.
 *
 * Usage:
 *   Browser: https://yourdemo.com/000_wp_updater.php?go=SomeSmartCode
 *   Browser (Mass Update): https://yourdemo.com/000_wp_updater.php?go=SomeSmartCode&all=1
 *   CLI:     php 000_wp_updater.php
 *   CLI:     php 000_wp_updater.php /path/to/your/wordpress/installation
 *   CLI (Mass Update): php 000_wp_updater.php /path/to/parent/dir 1
 *
 * Always use the latest version: https://github.com/orbisius/wp_updater
 *
 * WARNING:
 * ------------
 * This script runs WordPress core, plugin, and theme updates with no backups.
 * It is meant for demo environments only.
 * Do NOT upload this to your main site or production folder!
 * Always place it inside a folder that contains only staging/demo installs.
 *
 * DISCLAIMER:
 * ------------
 * This script is provided "as-is" with no warranties.
 * Use it at your own risk. The author (Svetoslav Marinov | https://orbisius.com)
 * is not responsible for any damage, data loss, or downtime caused by its use.
 */

$code = 'SomeSmartCode'; // secret code for web requests only.
$WPCliBin = '/usr/local/bin/wp'; // Adjust this path to your WP-CLI binary
$maxDepth = 8; // Maximum directory depth to scan for WordPress installations
$defaultStartDir = __DIR__; // Default directory to start scanning from. Change this if you want to scan a different directory by default.

try {
    $appExitCode = 0;
    $start_time = microtime(true);

    // Error reporting settings
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);

    // Output buffering settings
    ini_set('implicit_flush', 1);
    ini_set('output_buffering', 0);
    ini_set('zlib.output_compression', 0);

    // https://stackoverflow.com/questions/20316338/intermittently-echo-out-data-with-long-running-php-script
    if (function_exists('apache_setenv')) {
        apache_setenv( 'no-gzip', 1 );
    }

    // Disable PHP's output buffering
    ob_implicit_flush(1);

    // WordPress directory can be passed as an argument or defaults to the current directory
    $WPDir = empty($argv[1]) ? $defaultStartDir : realpath($argv[1]);

    // if it's empty that means that realpath() failed
    if (empty($WPDir)) {
        throw new RuntimeException("Invalid directory path. Current directory: " . getcwd());
    }

    if (!is_dir($WPDir)) {
        throw new RuntimeException("Not a directory: [$WPDir]");
    }

    if (!is_readable($WPDir)) {
        throw new RuntimeException("Directory not readable: [$WPDir]");
    }

    $isMassUpdate = (PHP_SAPI === 'cli' && !empty($argv[2])) ||
        (PHP_SAPI !== 'cli' && !empty($_REQUEST['all']));

    $php_open_base_dir = ini_get('open_basedir');

    // If open_basedir is set, we need to ensure WP-CLI can run. this seems to find it.
    if (!empty($php_open_base_dir)) {
        $WPCliBin = 'wp';
    }

    putenv('WP_CLI_CACHE_DIR=/dev/null');
    putenv('WP_CLI_DISABLE_AUTO_CHECK_UPDATE=1');

    if (PHP_SAPI != 'cli') {
        header('Content-Type: text/plain');
        $input = !empty($_REQUEST['go']) ? trim($_REQUEST['go']) : '';

        if (empty($input) || $input != $code) {
            http_response_code(403);
            throw new Exception("Access Denied");
        }
    }

    // Disallow running as root for safety
    if (PHP_SAPI === 'cli' && function_exists('posix_geteuid') && posix_geteuid() == 0) {
        throw new Exception("ERROR: Do not run this script as root. Use a non-privileged user (like www-data).\n");
    }

    if (!function_exists('exec')) {
        throw new RuntimeException("exec() is not available. Please enable it in php.ini.");
    } elseif (!function_exists('shell_exec')) {
        throw new RuntimeException("shell_exec() is not available. Please enable it in php.ini.");
    }

    // if full path check if it exists and is executable
    if (substr($WPCliBin, 0, 1) == '/' && (!file_exists($WPCliBin) || !is_executable($WPCliBin))) {
        throw new RuntimeException("WP-CLI not executable or not found at: {$WPCliBin}");
    } else {
        // check with info
        $output = [];
        $exitCode = 1;
        exec("$WPCliBin --info 2>&1", $output, $exitCode);

        if (!empty($exitCode)) {
            throw new RuntimeException("WP-CLI not executable or not found at: {$WPCliBin}");
        }
    }

    // Find WordPress installations
    $wpPaths = findWPInstalls($WPDir, $isMassUpdate ? 0 : $maxDepth - 1); // run one level scan $maxDepth - 1

    if (empty($wpPaths)) {
        throw new RuntimeException("No WordPress installations found in: [$WPDir]");
    }

    $totalDirs = count($wpPaths);

    foreach ($wpPaths as $idx => $wpPath) {
        $wpPathEsc = escapeshellarg($wpPath);
        $url = shell_exec("$WPCliBin --path=$wpPathEsc option get siteurl 2>/dev/null");
        $url = empty($url) ? '' : trim($url);

        $currentDir = $idx + 1;
        echo "[$currentDir/$totalDirs] Starting updater for [$url] in [$wpPath] ... \n";
        echo str_repeat('-', 50) . "\n";
        appFlush();

        // Check if multisite: used only for plugin/theme updates
        $output = [];
        $exitCode = 1;
        $extraCmdFlags = '';
        exec("$WPCliBin --path=$wpPathEsc core is-installed --network 2>&1", $output, $exitCode);

        if (empty($exitCode)) {
            echo "Multisite detected. Using --network for plugin/theme updates.\n";
            appFlush();
            $extraCmdFlags = '--network';
        }

        $extraCmdFlags .= ' 2>&1';

        echo "Updating all WordPress plugins ...\n";
        appFlush();
        runCommand($wpPath, "plugin update --all $extraCmdFlags");

        // Check if WooCommerce is active
        $output = [];
        $exitCode = 1;
        exec("$WPCliBin --path=$wpPathEsc plugin is-active woocommerce $extraCmdFlags", $output, $exitCode);

        if (empty($exitCode)) {
            echo "WooCommerce is active. Running WC DB update...\n";
            appFlush();
            runCommand($wpPath, "wc update $extraCmdFlags");
        }

        // Check if Elementor is active
        $output = [];
        $exitCode = 1;
        exec("$WPCliBin --path=$wpPathEsc plugin is-active elementor $extraCmdFlags", $output, $exitCode);

        if (empty($exitCode)) {
            echo "Elementor is active. Running Elementor DB update...\n";
            appFlush();
            runCommand($wpPath, "elementor update db $extraCmdFlags");
        }

        echo "Updating all WordPress themes ...\n";
        appFlush();
        runCommand($wpPath, "theme update --all $extraCmdFlags");

        echo "Updating WordPress...\n";
        appFlush();
        runCommand($wpPath, "core update 2>&1");
        runCommand($wpPath, "core update-db 2>&1");

        echo "Done with: $wpPath\n";
        echo str_repeat('-', 50) . "\n\n";
        appFlush();
    }
} catch (Exception $e) {
    $appExitCode = 255;
    echo "ERROR: " . $e->getMessage() . "\n";
} finally {
    $exec_time = round(microtime(true) - $start_time, 4);
    echo "Updater completed in $exec_time ...\n";
    echo str_repeat('-', 50) . "\n";
    appFlush();
}

exit($appExitCode);

function appFlush()
{
    if (PHP_SAPI != 'cli') {
        echo str_repeat("\t", 512); // Add some spacing to ensure the web server sends the output
    }

    @ob_flush();
    flush();
}

function runCommand($path, $command)
{
    global $WPCliBin;
    $pathEsc = escapeshellarg($path);
    $fullCommand = "$WPCliBin --path=$pathEsc $command";

    $outputLines = [];
    $exitCode = 0;
    $startTime = microtime(true);

    exec($fullCommand, $outputLines, $exitCode);

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 3);

    $output = implode("\n", $outputLines) . "\n";
    echo $output;
    echo "⏱ Duration: {$duration}s\n";

    appFlush();

    if (!empty($exitCode)) {
        echo "Command failed with exit code $exitCode\n";
    }

    return $output;
}

function findWPInstalls($dir, $depth = 0)
{
    $foundWPDirs = [];

    if ($depth > $GLOBALS['maxDepth'] || !is_dir($dir)) {
        return $foundWPDirs;
    }

    $items = scandir($dir);

    $quick_skip_names = [
        '.', '..', 'tmp', 'cache', 'log', 'logs',
        'node_modules', 'vendor', 'bower_components',
        '.git', '.svn', '.hg', '.idea', '.vscode',
    ];

    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if ($item == 'wp-config.php') {
            $foundWPDirs[] = $dir;
            continue;
        }

        // Skip quick skip names
        if (in_array($item, $quick_skip_names)) {
            continue;
        }

        // Skip hidden files and wp-* directories
        if ((strpos($item, '.') === 0) || (strpos($item, 'wp-') === 0)) {
            continue;
        }

        if (is_dir($path)) {
            $subFound = findWPInstalls($path, $depth + 1);

            if (!empty($subFound)) {
                $foundWPDirs = array_merge($foundWPDirs, $subFound);
            }
        }
    }

    return $foundWPDirs;
}
