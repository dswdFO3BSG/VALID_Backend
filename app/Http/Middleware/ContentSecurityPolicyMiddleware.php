<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicyMiddleware
{
    /**
     * The generated nonces for this request
     */
    private array $nonces = [];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate nonces if enabled
        $this->generateNonces();

        $response = $next($request);

        // Get CSP configuration
        $cspConfig = config('csp', []);
        
        // Build CSP header
        $cspHeader = $this->buildCspHeader($cspConfig);
        
        // Add CSP headers
        if ($cspHeader) {
            if (config('csp.report_only', false)) {
                $response->headers->set('Content-Security-Policy-Report-Only', $cspHeader);
            } else {
                $response->headers->set('Content-Security-Policy', $cspHeader);
            }
        }

        // Add other security headers
        $this->addSecurityHeaders($response);

        return $response;
    }

    /**
     * Generate nonces for scripts and styles
     */
    private function generateNonces(): void
    {
        if (config('csp.nonce.enabled', false)) {
            if (config('csp.nonce.script', true)) {
                $this->nonces['script'] = Str::random(32);
                app()->instance('csp.script.nonce', $this->nonces['script']);
            }
            
            if (config('csp.nonce.style', true)) {
                $this->nonces['style'] = Str::random(32);
                app()->instance('csp.style.nonce', $this->nonces['style']);
            }
        }
    }

    /**
     * Build the CSP header string from configuration
     */
    private function buildCspHeader(array $config): string
    {
        if (empty($config['directives'])) {
            return '';
        }

        $directives = [];

        foreach ($config['directives'] as $directive => $sources) {
            if (empty($sources)) {
                continue;
            }

            $directiveName = str_replace('_', '-', $directive);
            
            if (is_array($sources)) {
                // Add nonces to script-src and style-src if enabled
                if (config('csp.nonce.enabled', false)) {
                    if ($directive === 'script_src' && isset($this->nonces['script'])) {
                        $sources[] = "'nonce-{$this->nonces['script']}'";
                    }
                    if ($directive === 'style_src' && isset($this->nonces['style'])) {
                        $sources[] = "'nonce-{$this->nonces['style']}'";
                    }
                }
                
                $sourceList = implode(' ', $sources);
            } else {
                $sourceList = $sources;
            }

            $directives[] = "{$directiveName} {$sourceList}";
        }

        // Add report-uri if configured
        if ($reportUri = config('csp.report_uri')) {
            $directives[] = "report-uri {$reportUri}";
        }

        return implode('; ', $directives);
    }

    /**
     * Add additional security headers
     */
    private function addSecurityHeaders(Response $response): void
    {
        // X-Content-Type-Options
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // X-Frame-Options
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // X-XSS-Protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Referrer-Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Permissions-Policy (formerly Feature-Policy)
        $response->headers->set('Permissions-Policy', 
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()'
        );

        // Strict-Transport-Security (HSTS) - only for HTTPS
        if ($this->isHttps()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Cross-Origin-Embedder-Policy
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');
        
        // Cross-Origin-Opener-Policy
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        
        // Cross-Origin-Resource-Policy
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
    }

    /**
     * Check if the request is HTTPS
     */
    private function isHttps(): bool
    {
        return request()->isSecure() || 
               request()->header('X-Forwarded-Proto') === 'https' ||
               request()->header('X-Forwarded-SSL') === 'on';
    }
}
