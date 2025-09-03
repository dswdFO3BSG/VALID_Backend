<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMFA;
use App\Services\AuditTrailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use PragmaRX\Google2FA\Exceptions\IncompatibleWithGoogleAuthenticatorException;
use PragmaRX\Google2FA\Exceptions\InvalidCharactersException;
use PragmaRX\Google2FA\Exceptions\SecretKeyTooShortException;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class MFAController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Generate MFA setup data for new user
     */
    public function setupMFA(Request $request)
    {
        try {
            $request->validate([
                'empno' => 'required|string'
            ]);

            // Check if user already has MFA setup
            $userMFA = UserMFA::where('empno', $request->empno)->first();
            if ($userMFA && $userMFA->enabled_mfa) {
                return response()->json([
                    'error' => 'MFA is already enabled for this user',
                    'status' => false
                ], 400);
            }

            // Get user details
            $user = User::where('empno', $request->empno)->first();
            if (!$user) {
                return response()->json([
                    'error' => 'User not found',
                    'status' => false
                ], 404);
            }

            // Generate a new secret key
            $secretKey = $this->google2fa->generateSecretKey(32);

            // Create or update MFA record
            $userMFA = UserMFA::updateOrCreate(
                ['empno' => $request->empno],
                [
                    'mfa_secret' => $secretKey,
                    'enabled_mfa' => 0, // Not enabled until verified
                ]
            );

            // Generate company name for QR code
            $companyName = 'DSWD VALID System';
            $companyEmail = $user->empno . '@dswd.gov.ph';

            // Generate QR code URL
            $qrCodeUrl = $this->google2fa->getQRCodeUrl(
                $companyName,
                $companyEmail,
                $secretKey
            );

            // Generate QR code as SVG
            $qrCodeSvg = $this->generateQRCodeSVG($qrCodeUrl);

            return response()->json([
                'status' => true,
                'message' => 'MFA setup initiated successfully',
                'data' => [
                    'secret_key' => $secretKey,
                    'qr_code_url' => $qrCodeUrl,
                    'qr_code_svg' => $qrCodeSvg,
                    'company_name' => $companyName,
                    'user_email' => $companyEmail,
                    'manual_entry_key' => chunk_split($secretKey, 4, ' ')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('MFA Setup Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to setup MFA: ' . $e->getMessage(),
                'status' => false
            ], 500);
        }
    }

    /**
     * Verify TOTP code and enable MFA
     */
    public function verifyAndEnableMFA(Request $request)
    {
        try {
            $request->validate([
                'empno' => 'required|string',
                'totp_code' => 'required|string|size:6'
            ]);

            $userMFA = UserMFA::where('empno', $request->empno)->first();
            if (!$userMFA) {
                return response()->json([
                    'error' => 'MFA not initialized for this user',
                    'status' => false
                ], 400);
            }

            // Verify the TOTP code
            $isValid = $this->google2fa->verifyKey($userMFA->mfa_secret, $request->totp_code, 2); // 2 windows tolerance

            if (!$isValid) {
                return response()->json([
                    'error' => 'Invalid TOTP code. Please try again.',
                    'status' => false
                ], 400);
            }

            // Enable MFA for the user
            $userMFA->update(['enabled_mfa' => 1]);

            // Get the user information for creating token
            $user = User::selectRaw("
                        userprofile.empno,
                        userprofile.password,
                        userprofile.isLog,
                        userprofile.is_reset,
                        userprofile.account_status,
                        lib_account_status.account_status_name,
                        userprofile.fname,
                        CONCAT(userprofile.fname,' ',userprofile.mname,' ',userprofile.sname, ' ', userprofile.ename ) as name
                    ")
                    ->LeftJoin('lib_account_status', 'userprofile.account_status', '=', 'lib_account_status.account_status_code')
                    ->where('userprofile.empno', '=', $request->empno)
                    ->first();

            if (!$user) {
                return response()->json([
                    'error' => 'User not found',
                    'status' => false
                ], 400);
            }

            return response()->json([
                'status' => true,
                'message' => 'MFA has been successfully enabled and user logged in',
                'token' => $user->createToken("API TOKEN")->plainTextToken,
                'UserInformation' => $user,
            ]);

        } catch (\Exception $e) {
            Log::error('MFA Verification Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to verify MFA: ' . $e->getMessage(),
                'status' => false
            ], 500);
        }
    }

    /**
     * Verify TOTP code for login
     */
    public function verifyMFAForLogin(Request $request)
    {
        try {
            $request->validate([
                'empno' => 'required|string',
                'totp_code' => 'required|string|size:6',
                'remember_mfa' => 'boolean'
            ]);

            $userMFA = UserMFA::where('empno', $request->empno)->where('enabled_mfa', 1)->first();
            if (!$userMFA) {
                return response()->json([
                    'error' => 'MFA not enabled for this user',
                    'status' => false
                ], 400);
            }

            // Verify the TOTP code
            $isValid = $this->google2fa->verifyKey($userMFA->mfa_secret, $request->totp_code, 2);

            if (!$isValid) {
                return response()->json([
                    'error' => 'Invalid TOTP code. Please try again.',
                    'status' => false
                ], 400);
            }

            // Get the user information for creating token
            $user = User::selectRaw("
                        userprofile.empno,
                        userprofile.password,
                        userprofile.isLog,
                        userprofile.is_reset,
                        userprofile.account_status,
                        lib_account_status.account_status_name,
                        userprofile.fname,
                        CONCAT(userprofile.fname,' ',userprofile.mname,' ',userprofile.sname, ' ', userprofile.ename ) as name
                    ")
                    ->LeftJoin('lib_account_status', 'userprofile.account_status', '=', 'lib_account_status.account_status_code')
                    ->where('userprofile.empno', '=', $request->empno)
                    ->first();

            if (!$user) {
                return response()->json([
                    'error' => 'User not found',
                    'status' => false
                ], 400);
            }

            $responseData = [
                'status' => true,
                'message' => 'MFA verification successful and user logged in',
                'token' => $user->createToken("API TOKEN")->plainTextToken,
                'UserInformation' => $user,
            ];

            // Handle "Remember this device" functionality
            if ($request->remember_mfa) {
                $rememberToken = Str::random(64);
                $rememberHash = hash('sha256', $rememberToken);
                
                // Set expiry to 30 days from now
                $expiresAt = now()->addDays(30);
                
                $userMFA->update([
                    'mfa_remember_hash' => $rememberHash,
                    'mfa_remember_expires' => $expiresAt
                ]);

                $responseData['remember_token'] = $rememberToken;
                $responseData['expires_at'] = $expiresAt->toISOString();
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            Log::error('MFA Login Verification Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to verify MFA: ' . $e->getMessage(),
                'status' => false
            ], 500);
        }
    }

    /**
     * Check if user has valid MFA remember token
     */
    public function checkMFARememberToken(Request $request)
    {
        try {
            $request->validate([
                'empno' => 'required|string',
                'remember_token' => 'required|string'
            ]);

            $userMFA = UserMFA::where('empno', $request->empno)->where('enabled_mfa', 1)->first();
            if (!$userMFA) {
                return response()->json([
                    'status' => false,
                    'valid' => false
                ]);
            }

            $tokenHash = hash('sha256', $request->remember_token);
            
            // Check if token matches and hasn't expired
            $isValid = $userMFA->mfa_remember_hash === $tokenHash && 
                      $userMFA->mfa_remember_expires && 
                      $userMFA->mfa_remember_expires->isFuture();

            return response()->json([
                'status' => true,
                'valid' => $isValid,
                'expires_at' => $isValid ? $userMFA->mfa_remember_expires->toISOString() : null
            ]);

        } catch (\Exception $e) {
            Log::error('MFA Remember Token Check Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'valid' => false
            ]);
        }
    }

    /**
     * Disable MFA for user
     */
    public function disableMFA(Request $request)
    {
        try {
            $request->validate([
                'empno' => 'required|string',
                'totp_code' => 'required|string|size:6'
            ]);

            $userMFA = UserMFA::where('empno', $request->empno)->first();
            if (!$userMFA) {
                return response()->json([
                    'error' => 'MFA not found for this user',
                    'status' => false
                ], 400);
            }

            // Verify current TOTP code before disabling
            $isValid = $this->google2fa->verifyKey($userMFA->mfa_secret, $request->totp_code, 2);
            if (!$isValid) {
                return response()->json([
                    'error' => 'Invalid TOTP code. Cannot disable MFA.',
                    'status' => false
                ], 400);
            }

            // Disable MFA
            $userMFA->update([
                'enabled_mfa' => 0,
                'mfa_remember_hash' => null,
                'mfa_remember_expires' => null
            ]);

            return response()->json([
                'status' => true,
                'message' => 'MFA has been disabled for your account'
            ]);

        } catch (\Exception $e) {
            Log::error('MFA Disable Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to disable MFA: ' . $e->getMessage(),
                'status' => false
            ], 500);
        }
    }

    /**
     * Generate QR code as SVG
     */
    private function generateQRCodeSVG($text)
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        return $writer->writeString($text);
    }

    /**
     * Get MFA status for user
     */
    public function getMFAStatus(Request $request)
    {
        try {
            $request->validate([
                'empno' => 'required|string'
            ]);

            $userMFA = UserMFA::where('empno', $request->empno)->first();
            
            return response()->json([
                'status' => true,
                'data' => [
                    'has_mfa' => $userMFA !== null,
                    'mfa_enabled' => $userMFA ? (bool)$userMFA->enabled_mfa : false,
                    'has_remember_token' => $userMFA && $userMFA->mfa_remember_hash !== null,
                    'remember_expires' => $userMFA && $userMFA->mfa_remember_expires ? 
                                        $userMFA->mfa_remember_expires->toISOString() : null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('MFA Status Check Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to check MFA status',
                'status' => false
            ], 500);
        }
    }
}
