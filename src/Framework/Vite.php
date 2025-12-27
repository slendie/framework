<?php

declare(strict_types=1);

namespace Slendie\Framework;

use function env;

final class Vite
{
    private static bool|null $devServerAvailable = null;

    /**
     * Get the asset URL from Vite manifest
     *
     * Checks if Vite dev server is running. If yes, returns the dev server URL.
     * Otherwise, reads from the manifest.json file.
     *
     * @param string $entry The entry point file (e.g., 'js/main.js')
     * @return string The asset URL
     */
    public static function asset(string $entry): string
    {
        if (self::isDevServerAvailable()) {
            // Development mode: use Vite dev server
            $vitePort = env('VITE_PORT', '5173');
            // Normalize entry path for dev server (remove views/assets/ prefix if present)
            $normalizedEntry = preg_replace('/^views\/assets\//', '', mb_ltrim($entry, '/'));
            return "http://localhost:{$vitePort}/{$normalizedEntry}";
        }

        // Production mode: read from manifest
        $manifestPath = BASE_PATH . '/public/assets/.vite/manifest.json';

        if (!file_exists($manifestPath)) {
            // Fallback if manifest doesn't exist
            return "/assets/{$entry}";
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);

        // Normalize entry path (remove leading slash if present)
        $entry = mb_ltrim($entry, '/');

        // Remove 'views/assets/' prefix if present to match manifest keys
        $entry = preg_replace('/^views\/assets\//', '', $entry);

        // Find the entry in manifest
        if (isset($manifest[$entry])) {
            $file = $manifest[$entry]['file'];
            // Remove 'assets/' prefix if present to avoid double assets folder
            $file = preg_replace('/^assets\//', '', $file);
            return "/assets/{$file}";
        }

        // Fallback if entry not found
        return "/assets/{$entry}";
    }

    /**
     * Get CSS files for an entry point
     *
     * @param string $entry The entry point file (e.g., 'js/main.js')
     * @return array Array of CSS file URLs
     */
    public static function css(string $entry): array
    {
        if (self::isDevServerAvailable()) {
            // In dev mode, CSS is injected by Vite
            return [];
        }

        $manifestPath = BASE_PATH . '/public/assets/.vite/manifest.json';

        if (!file_exists($manifestPath)) {
            return [];
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $entry = mb_ltrim($entry, '/');

        // Remove 'views/assets/' prefix if present to match manifest keys
        $entry = preg_replace('/^views\/assets\//', '', $entry);

        if (isset($manifest[$entry]) && isset($manifest[$entry]['css'])) {
            $cssFiles = [];
            foreach ($manifest[$entry]['css'] as $cssFile) {
                // Remove 'assets/' prefix if present to avoid double assets folder
                $cssFile = preg_replace('/^assets\//', '', $cssFile);
                $cssFiles[] = "/assets/{$cssFile}";
            }
            return $cssFiles;
        }

        return [];
    }

    /**
     * Generate the Vite client script tag for development
     *
     * @return string HTML script tag or empty string
     */
    public static function client(): string
    {
        if (!self::isDevServerAvailable()) {
            return '';
        }

        $vitePort = env('VITE_PORT', '5173');
        return '<script type="module" src="http://localhost:' . $vitePort . '/@vite/client"></script>';
    }

    /**
     * Generate CSS link tags for an entry point
     *
     * @param string $entry The entry point file (e.g., 'js/main.js')
     * @return string HTML link tags for CSS files
     */
    public static function cssTags(string $entry): string
    {
        $cssFiles = self::css($entry);
        $html = '';
        foreach ($cssFiles as $cssFile) {
            $html .= '<link rel="stylesheet" href="' . htmlspecialchars($cssFile, ENT_QUOTES, 'UTF-8') . '">' . "\n  ";
        }
        return $html;
    }

    /**
     * Generate script tag for an entry point
     *
     * @param string $entry The entry point file (e.g., 'js/main.js')
     * @return string HTML script tag for the JavaScript entry point
     */
    public static function scriptTag(string $entry): string
    {
        $assetUrl = self::asset($entry);
        return '<script type="module" src="' . htmlspecialchars($assetUrl, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n  ";
    }

    /**
     * Check if Vite dev server is running
     *
     * @return bool True if dev server is accessible
     */
    private static function isDevServerAvailable(): bool
    {
        // Cache the result to avoid checking on every request
        if (self::$devServerAvailable !== null) {
            return self::$devServerAvailable;
        }

        $vitePort = (int) env('VITE_PORT', '5173');
        $host = 'localhost';

        // Try to connect to the dev server port
        $connection = @fsockopen($host, $vitePort, $errno, $errstr, 0.1);

        if ($connection) {
            fclose($connection);
            self::$devServerAvailable = true;
        } else {
            self::$devServerAvailable = false;
        }

        return self::$devServerAvailable;
    }
}
