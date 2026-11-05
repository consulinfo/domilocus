<?php
/** Shared guest security primitives; bundled in each consumer plugin. */
// phpcs:disable WordPress.WP.AlternativeFunctions -- Private storage needs native local paths, exclusive creation, exact hashes and checked deletion. Remote filesystem transports cannot provide these guarantees.
defined('ABSPATH') || exit;
if (!class_exists('Domilocus_Guest_Security', false)) {
final class Domilocus_Guest_Security {
    public static function ip() {
        $normalize = static function ($ip) { return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) ? inet_ntop(inet_pton($ip)) : ''; };
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Strict FILTER_VALIDATE_IP validation and inet_pton canonicalization reject malformed input without modifying it.
        $remote = $normalize($_SERVER['REMOTE_ADDR'] ?? '');
        $proxies = defined('DOMILOCUS_TRUSTED_PROXIES') && is_array(DOMILOCUS_TRUSTED_PROXIES) ? array_filter(array_map($normalize, DOMILOCUS_TRUSTED_PROXIES)) : array();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Only trusted peers are considered; each raw chain element is strictly validated below, without changing its meaning.
        $header = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (!$remote || !in_array($remote, $proxies, true) || !is_string($header) || !$header) { return $remote; }
        $parts = explode(',', $header);
        if (count($parts) > 20) { return $remote; }
        $chain = array();
        foreach ($parts as $part) { $ip = $normalize(trim($part)); if (!$ip) { return $remote; } $chain[] = $ip; }
        $chain[] = $remote;
        while (count($chain) > 1 && in_array(end($chain), $proxies, true)) { array_pop($chain); }
        return end($chain);
    }

    /** Atomic SQL budget; common to REST/AJAX and plugins, including local sites. */
    public static function rate($scope, $key, $max = 20, $window = 3600) {
        global $wpdb;
        $window = max(1, (int) $window);
        $end = ((int) floor(time() / $window) + 1) * $window;
        $name = 'dlsec_rl_' . $end . '_' . hash_hmac('sha256', $scope . ':' . $key, wp_salt('auth'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic security budget must use live SQL rather than a race-prone cached read/write.
        $ok = $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'no') ON DUPLICATE KEY UPDATE option_value = CAST(option_value AS UNSIGNED) + 1", $name));
        if ($ok === false) { return false; }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Always read the current count; caching can bypass the limit across concurrent requests.
        $count = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name));
        return $count !== null && (int) $count <= $max;
    }

    public static function cleanup() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Remove only expired, non-autoloaded security counters that are never read through the option cache.
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(SUBSTRING(option_name, 10, 10) AS UNSIGNED) < %d LIMIT 1000", $wpdb->esc_like('dlsec_rl_') . '%', time()));
    }

    public static function inside($path, $root) {
        $path = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if (DIRECTORY_SEPARATOR === '\\') { $path = strtolower($path); $root = strtolower($root); }
        return $path === $root || strpos($path, $root . '/') === 0;
    }

    /** Refuse public paths, including symlinks and custom document roots. */
    public static function private_dir($scope) {
        $base = defined('DOMILOCUS_PRIVATE_DIR') ? DOMILOCUS_PRIVATE_DIR : dirname(rtrim(ABSPATH, '/\\')) . '/domilocus-private';
        if (!is_string($base) || !preg_match('~^(?:[A-Za-z]:[/\\\\]|/)~', $base)) { return false; }
        $roots = array(realpath(ABSPATH));
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Server filesystem path, not an HTTP header; realpath resolves it without corrupting Windows path separators.
        if (!empty($_SERVER['DOCUMENT_ROOT'])) { $roots[] = realpath($_SERVER['DOCUMENT_ROOT']); }
        $dir = rtrim($base, '/\\') . '/' . substr(hash('sha256', ABSPATH), 0, 16) . '/' . preg_replace('/[^a-z0-9-]/', '', $scope);
        // Resolve the nearest existing ancestor before creating any directories.
        $ancestor = $dir;
        while (!file_exists($ancestor) && dirname($ancestor) !== $ancestor) { $ancestor = dirname($ancestor); }
        foreach ($roots as $root) { if ($root && self::inside(realpath($ancestor), $root)) { return false; } }
        if (!is_dir($dir) && !wp_mkdir_p($dir)) { return false; }
        $resolved = realpath($dir);
        if (!$resolved) { return false; }
        foreach ($roots as $root) { if ($root && self::inside($resolved, $root)) { return false; } }
        @chmod($resolved, 0700);
        // Windows directory attributes can make is_writable() report false
        // even when file creation works. Verify the actual operation instead.
        $probe = $resolved . DIRECTORY_SEPARATOR . '.probe-' . bin2hex(random_bytes(8));
        $handle = @fopen($probe, 'x');
        if (!$handle) { return false; }
        fclose($handle);
        if (!unlink($probe)) { return false; }
        return rtrim($resolved, '/\\') . DIRECTORY_SEPARATOR;
    }

    /** Move legacy files without deleting the only valid copy; no DB rewrite required. */
    public static function relocate($path, $scope) {
        $dir = self::private_dir($scope);
        if (!$dir || !is_string($path) || !$path) { return false; }
        $legacy_name = $scope === 'domicheck' ? 'domicheck-documents' : 'simple-esign';
        $legacy = WP_CONTENT_DIR . '/uploads/' . $legacy_name;
        $target = $dir . basename(str_replace('\\', '/', $path));
        if (is_file($path)) {
            $real = realpath($path);
            if (self::inside($real, $dir)) { return $real; }
            if (!realpath($legacy) || !self::inside($real, realpath($legacy)) || is_link($path)) { return false; }
            if (is_file($target) && !self::inside(realpath($target), $dir)) { return false; }
            if (!is_file($target)) {
                $temporary = $target . '.' . bin2hex(random_bytes(8)) . '.tmp';
                if (!copy($path, $temporary) || hash_file('sha256', $path) !== hash_file('sha256', $temporary)) { @unlink($temporary); return false; }
                @chmod($temporary, 0600);
                if (!rename($temporary, $target)) { @unlink($temporary); return false; }
            }
            if (hash_file('sha256', $path) !== hash_file('sha256', $target) || !unlink($path)) { return false; }
        } elseif (!self::inside(str_replace('\\', '/', $path), str_replace('\\', '/', $legacy)) && !self::inside($path, $dir)) { return false; }
        return is_file($target) && self::inside(realpath($target), $dir) ? $target : false;
    }

    public static function migrate_legacy($scope) {
        $name = $scope === 'domicheck' ? 'domicheck-documents' : 'simple-esign';
        $legacy = WP_CONTENT_DIR . '/uploads/' . $name;
        if (!is_dir($legacy)) { return true; }
        $count = 0;
        foreach (new DirectoryIterator($legacy) as $file) {
            if (!$file->isFile() || $file->isLink() || in_array($file->getFilename(), array('.htaccess', 'index.php'), true)) { continue; }
            if (!self::relocate($file->getPathname(), $scope)) { return false; }
            if (++$count >= 25) { break; }
        }
        return true;
    }
}
add_action('domilocus_guest_security_cleanup', array('Domilocus_Guest_Security', 'cleanup'));
add_action('init', static function () {
    if (!wp_next_scheduled('domilocus_guest_security_cleanup')) { wp_schedule_event(time(), 'daily', 'domilocus_guest_security_cleanup'); }
});
}
