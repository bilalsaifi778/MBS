<?php
/**
 * PHP Compatibility Functions
 * This file provides compatibility functions for different PHP versions
 */

// Check PHP version and provide polyfills if needed
if (!function_exists('str_contains')) {
    /**
     * Polyfill for str_contains() function (PHP 8.0+)
     */
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    /**
     * Polyfill for str_starts_with() function (PHP 8.0+)
     */
    function str_starts_with($haystack, $needle) {
        return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    /**
     * Polyfill for str_ends_with() function (PHP 8.0+)
     */
    function str_ends_with($haystack, $needle) {
        return $needle !== '' && substr($haystack, -strlen($needle)) === $needle;
    }
}

// Set error reporting based on environment
$host = $_SERVER['HTTP_HOST'] ?? '';
if (str_contains($host, 'infinityfree.net') || str_contains($host, 'epizy.com')) {
    // Production environment - hide errors
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
} else {
    // Development environment - show errors
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

// Set default timezone
date_default_timezone_set('UTC');

// Increase memory limit if possible
@ini_set('memory_limit', '128M');

// Increase max execution time
@ini_set('max_execution_time', 300);