# Content Security Policy (CSP) Implementation

## Overview

This implementation adds comprehensive Content Security Policy (CSP) headers to your Laravel backend to enhance security by preventing various types of attacks including Cross-Site Scripting (XSS), data injection attacks, and clickjacking.

## Files Created/Modified

### 1. ContentSecurityPolicyMiddleware.php (NEW)

**Location**: `app/Http/Middleware/ContentSecurityPolicyMiddleware.php`

**Purpose**:

-   Handles CSP header generation and application
-   Supports nonce generation for enhanced security
-   Adds additional security headers (X-Frame-Options, X-Content-Type-Options, etc.)

### 2. csp.php (NEW)

**Location**: `config/csp.php`

**Purpose**:

-   Centralized configuration for CSP policies
-   Defines allowed sources for different content types
-   Configurable for different environments

### 3. csp_helpers.php (NEW)

**Location**: `app/helpers/csp_helpers.php`

**Purpose**:

-   Helper functions to access CSP nonces in views
-   Functions: `csp_nonce()`, `csp_script_nonce()`, `csp_style_nonce()`

### 4. bootstrap/app.php (MODIFIED)

**Changes**:

-   Registered CSP middleware globally for web and API routes
-   Added middleware alias for optional use

### 5. composer.json (MODIFIED)

**Changes**:

-   Added autoload configuration for helper functions

## Security Headers Added

### Content Security Policy (CSP)

-   **script-src**: Controls allowed script sources
-   **style-src**: Controls allowed stylesheet sources
-   **img-src**: Controls allowed image sources
-   **connect-src**: Controls allowed connection sources (AJAX, WebSocket)
-   **font-src**: Controls allowed font sources
-   **media-src**: Controls allowed media sources
-   **object-src**: Controls allowed object/embed sources
-   **frame-src**: Controls allowed frame sources
-   **base-uri**: Controls base URI for relative URLs
-   **form-action**: Controls allowed form action URLs

### Additional Security Headers

-   **X-Content-Type-Options**: `nosniff` - Prevents MIME type sniffing
-   **X-Frame-Options**: `DENY` - Prevents clickjacking
-   **X-XSS-Protection**: `1; mode=block` - Enables XSS protection
-   **Referrer-Policy**: `strict-origin-when-cross-origin` - Controls referrer information
-   **Permissions-Policy**: Restricts access to browser features
-   **Strict-Transport-Security**: Forces HTTPS (when applicable)
-   **Cross-Origin-Embedder-Policy**: `require-corp` - Controls cross-origin embedding
-   **Cross-Origin-Opener-Policy**: `same-origin` - Controls window opening
-   **Cross-Origin-Resource-Policy**: `same-origin` - Controls resource access

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# CSP Configuration
CSP_REPORT_ONLY=false
CSP_REPORT_URI=
CSP_NONCE_ENABLED=false
CSP_SCRIPT_NONCE=true
CSP_STYLE_NONCE=true
```

### CSP Directives Configuration

The CSP policies are configured in `config/csp.php`. Key sections include:

#### Script Sources

```php
'script_src' => [
    "'self'",
    "'unsafe-inline'", // For Vue.js and inline scripts
    "'unsafe-eval'",   // For Vue.js development mode
    'https://www.google.com',           // Google reCAPTCHA
    'https://www.gstatic.com',          // Google reCAPTCHA
    // Add other trusted script sources
],
```

#### Style Sources

```php
'style_src' => [
    "'self'",
    "'unsafe-inline'", // For inline styles and Vue components
    'https://fonts.googleapis.com',     // Google Fonts
    // Add other trusted style sources
],
```

#### Connection Sources

```php
'connect_src' => [
    "'self'",
    'https://api.psa.gov.ph',           // PSA API
    'http://localhost:*',               // Local development
    // Add other API endpoints
],
```

## Usage

### Basic Usage

The CSP middleware is automatically applied to all web and API routes. No additional configuration is needed for basic functionality.

### Using Nonces (Advanced)

For enhanced security, you can enable nonces:

1. Set `CSP_NONCE_ENABLED=true` in your `.env` file
2. Use nonces in your views:

```php
<!-- In Blade templates -->
<script nonce="{{ csp_script_nonce() }}">
    // Your inline script
</script>

<style nonce="{{ csp_style_nonce() }}">
    /* Your inline styles */
</style>
```

### Applying to Specific Routes

You can also apply CSP middleware to specific routes:

```php
// In routes/web.php or routes/api.php
Route::middleware(['csp'])->group(function () {
    // Your routes
});
```

## Testing and Debugging

### Report-Only Mode

For testing, enable report-only mode:

```env
CSP_REPORT_ONLY=true
```

This will add `Content-Security-Policy-Report-Only` headers instead of enforcing policies.

### CSP Reporting

Set up a reporting endpoint to monitor violations:

```env
CSP_REPORT_URI=https://yourdomain.com/csp-report
```

### Browser Developer Tools

Monitor CSP violations in the browser console. Violations will show detailed information about blocked resources.

## Customization

### Adding New Domains

To allow new domains, add them to the appropriate directive in `config/csp.php`:

```php
'script_src' => [
    "'self'",
    'https://newdomain.com',
    // existing sources...
],
```

### Development vs Production

You can use environment-specific configurations:

```php
'script_src' => [
    "'self'",
    env('APP_ENV') === 'local' ? "'unsafe-eval'" : '',
    // other sources...
],
```

### Disabling CSP for Specific Routes

Create a middleware to disable CSP for specific routes:

```php
public function handle($request, Closure $next)
{
    $response = $next($request);
    $response->headers->remove('Content-Security-Policy');
    return $response;
}
```

## Common Issues and Solutions

### Vue.js Applications

-   Include `'unsafe-inline'` and `'unsafe-eval'` for development
-   Consider using nonces for production
-   Allow CDN sources for external libraries

### Third-party Integrations

-   Add domains for external APIs (PSA, Google services)
-   Include font and style sources for external libraries
-   Allow frame sources for embedded content

### Image and Media Content

-   Use `data:` and `blob:` for dynamically generated content
-   Include `https:` for all HTTPS images if needed
-   Add specific domains for trusted image sources

## Installation Steps

1. The middleware and configuration files are already created
2. Run composer autoload to register helper functions:
    ```bash
    composer dump-autoload
    ```
3. Test the implementation by checking response headers in browser developer tools
4. Adjust CSP policies in `config/csp.php` as needed for your application

## Security Benefits

-   **XSS Prevention**: Blocks execution of malicious scripts
-   **Data Injection Protection**: Prevents unauthorized resource loading
-   **Clickjacking Protection**: Prevents embedding in malicious frames
-   **MITM Attack Prevention**: Forces HTTPS connections
-   **Content Sniffing Protection**: Prevents MIME type confusion attacks
-   **Feature Access Control**: Restricts browser feature access

The CSP implementation provides a robust security layer for your Laravel application while maintaining compatibility with modern frontend frameworks like Vue.js.
