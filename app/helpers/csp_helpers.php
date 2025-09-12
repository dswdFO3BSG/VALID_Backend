<?php

if (!function_exists('csp_nonce')) {
    /**
     * Get the CSP nonce for scripts or styles
     *
     * @param string $type The type of nonce ('script' or 'style')
     * @return string|null
     */
    function csp_nonce(string $type = 'script'): ?string
    {
        try {
            return app()->bound("csp.{$type}.nonce") ? app("csp.{$type}.nonce") : null;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('csp_script_nonce')) {
    /**
     * Get the CSP nonce for scripts
     *
     * @return string|null
     */
    function csp_script_nonce(): ?string
    {
        return csp_nonce('script');
    }
}

if (!function_exists('csp_style_nonce')) {
    /**
     * Get the CSP nonce for styles
     *
     * @return string|null
     */
    function csp_style_nonce(): ?string
    {
        return csp_nonce('style');
    }
}
