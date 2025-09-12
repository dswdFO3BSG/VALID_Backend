<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Content Security Policy Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines the Content Security Policy (CSP) directives
    | that will be applied to your application responses.
    |
    */

    /**
     * Enable CSP Report-Only mode for testing
     * When true, violations are reported but not enforced
     */
    'report_only' => env('CSP_REPORT_ONLY', false),

    /**
     * CSP Report URI (optional)
     * URL where CSP violation reports will be sent
     */
    'report_uri' => env('CSP_REPORT_URI', null),

    /**
     * CSP Directives
     * Define the allowed sources for different types of content
     */
    'directives' => [
        /**
         * Default source for all directives
         */
        'default_src' => [
            "'self'",
        ],

        /**
         * Sources for scripts
         */
        'script_src' => [
            "'self'",
            "'unsafe-inline'", // Required for Vue.js and inline scripts
            "'unsafe-eval'",   // Required for Vue.js development mode
            'https://www.google.com',           // Google reCAPTCHA
            'https://www.gstatic.com',          // Google reCAPTCHA
            'https://www.googletagmanager.com', // Google Analytics (if used)
            'https://cdn.jsdelivr.net',         // CDN for libraries
            'https://unpkg.com',                // CDN for libraries
        ],

        /**
         * Sources for stylesheets
         */
        'style_src' => [
            "'self'",
            "'unsafe-inline'", // Required for inline styles and Vue components
            'https://fonts.googleapis.com',     // Google Fonts
            'https://cdn.jsdelivr.net',         // CDN for CSS libraries
            'https://unpkg.com',                // CDN for CSS libraries
        ],

        /**
         * Sources for images
         */
        'img_src' => [
            "'self'",
            'data:',                            // Data URLs for inline images
            'blob:',                            // Blob URLs for generated images
            'https:',                           // All HTTPS images
            'http://localhost:*',               // Local development
            'http://127.0.0.1:*',               // Local development
        ],

        /**
         * Sources for fonts
         */
        'font_src' => [
            "'self'",
            'data:',                            // Data URLs for inline fonts
            'https://fonts.gstatic.com',        // Google Fonts
            'https://cdn.jsdelivr.net',         // CDN for fonts
        ],

        /**
         * Sources for connections (AJAX, WebSocket, EventSource)
         */
        'connect_src' => [
            "'self'",
            'https://api.psa.gov.ph',           // PSA API
            'https://www.google.com',           // Google services
            'https://www.gstatic.com',          // Google services
            'http://localhost:*',               // Local development
            'http://127.0.0.1:*',               // Local development
            'ws://localhost:*',                 // WebSocket for development
            'wss://localhost:*',                // Secure WebSocket for development
        ],

        /**
         * Sources for media elements (audio, video)
         */
        'media_src' => [
            "'self'",
            'data:',
            'blob:',
        ],

        /**
         * Sources for objects (embed, object, applet)
         */
        'object_src' => [
            "'none'",
        ],

        /**
         * Sources for frames
         */
        'frame_src' => [
            "'self'",
            'https://www.google.com',           // Google reCAPTCHA
        ],

        /**
         * Sources for web workers
         */
        'worker_src' => [
            "'self'",
            'blob:',
        ],

        /**
         * Sources for manifests
         */
        'manifest_src' => [
            "'self'",
        ],

        /**
         * Base URI for relative URLs
         */
        'base_uri' => [
            "'self'",
        ],

        /**
         * Valid ancestors for embedding in frames
         */
        'frame_ancestors' => [
            "'none'",
        ],

        /**
         * Sources for form actions
         */
        'form_action' => [
            "'self'",
        ],

        /**
         * Upgrade insecure requests (HTTP to HTTPS)
         * Uncomment if you want to force HTTPS
         */
        // 'upgrade_insecure_requests' => '',

        /**
         * Block all mixed content
         * Uncomment if you want to block mixed content
         */
        // 'block_all_mixed_content' => '',
    ],

    /**
     * Nonce configuration (for enhanced security)
     * Generate a random nonce for each request to allow specific inline scripts/styles
     */
    'nonce' => [
        'enabled' => env('CSP_NONCE_ENABLED', false),
        'script' => env('CSP_SCRIPT_NONCE', true),
        'style' => env('CSP_STYLE_NONCE', true),
    ],
];
