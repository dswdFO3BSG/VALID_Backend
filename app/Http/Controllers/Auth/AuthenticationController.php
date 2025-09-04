<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMFA;
use App\Services\PasswordDecryptionService;
use App\Services\AuditTrailService;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthenticationController extends Controller
{

    private function verifyRecaptcha($token)
    {
        // Use v3 secret key (uncommented)
        $secretKey = env('RECAPTCHA_SECRET_KEY');
        
        if (!$secretKey) {
            return ['success' => false, 'error' => 'reCAPTCHA secret key not configured'];
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secretKey,
                'response' => $token
            ]);

            $result = $response->json();
            
            if (!$result['success']) {
                return ['success' => false, 'error' => 'reCAPTCHA verification failed'];
            }

            // Uncomment v3 score check for v3 implementation
            if (isset($result['score']) && $result['score'] < 0.5) {
                return ['success' => false, 'error' => 'reCAPTCHA score too low'];
            }


            return ['success' => true];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'reCAPTCHA verification error: ' . $e->getMessage()];
        }
    }

    private function validateUser($request){
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
                ->where('userprofile.empno', '=', $request->username)
                ->first();
                
        // Check if user exists
        if (!$user) {
            return response()->json(['error' => 'Incorrect Username or Password.', 'validation' => '1'], 200);
        }
                
        if ($user->account_status == 2) {
            if ($user->isLog == 0) {
                try {
                    // Decrypt the password received from frontend
                    $decryptedPassword = PasswordDecryptionService::decryptPasswordBase64($request->password);
                    
                    // Use Laravel's Hash::check with the decrypted password
                    if(Hash::check($decryptedPassword, $user->password)){
                        // Password is correct, return user for further processing
                        return $user;
                    } else {
                        return response()->json(['error' => 'Incorrect Username or Password', 'validation' => '1'], 200);
                    }
                } catch (\Exception $e) {
                    // Log decryption error and return generic error message
                    Log::error('Password decryption failed: ' . $e->getMessage());
                    return response()->json(['error' => 'Authentication failed. Please try again.', 'validation' => '1'], 200);
                }
            } else {
                return response()->json(['error' => 'To comply with cybersecurity policies, please update your password immediately. You will be redirected to the Employee Registration Module to log in and update your password.' , 'validation' => '2'], 200);
            }
        } else {
            return response()->json(['error' => 'Incorrect Username or Password' , 'validation' => '3', 'accountStatus' => $user->account_status_name], 200);
        }
    }

    /**
     * Check if user requires MFA and handle remember token
     */
    private function checkMFARequirement($empno, $rememberToken = null, $verificationToken = null)
    {
        try {
            // Check for any MFA record first
            $anyMFARecord = UserMFA::where('empno', $empno)->first();
            $userMFA = UserMFA::where('empno', $empno)->where('enabled_mfa', 1)->first();
            
            Log::info("MFA Requirement Check", [
                'empno' => $empno,
                'has_any_mfa_record' => !!$anyMFARecord,
                'any_record_enabled_mfa' => $anyMFARecord ? $anyMFARecord->enabled_mfa : 'no_record',
                'has_enabled_mfa_record' => !!$userMFA,
                'remember_token_provided' => !empty($rememberToken),
                'verification_token_provided' => !empty($verificationToken)
            ]);
            
            // If no MFA record exists at all, require setup
            if (!$anyMFARecord) {
                Log::info("No MFA record for user, requiring MFA setup", ['empno' => $empno]);
                return [
                    'requires_mfa' => true,
                    'setup_required' => true,
                    'message' => 'Multi-Factor Authentication setup is required for your account'
                ];
            }
            
            // If MFA record exists but not enabled, require setup completion
            if ($anyMFARecord && !$userMFA) {
                Log::info("MFA setup not completed for user, requiring completion", ['empno' => $empno]);
                return [
                    'requires_mfa' => true,
                    'setup_required' => true,
                    'message' => 'Please complete your Multi-Factor Authentication setup'
                ];
            }

            // Check if MFA was already verified with a verification token
            if ($verificationToken) {
                $verificationHash = hash('sha256', $verificationToken);
                $sessionKey = "mfa_verified_{$empno}";
                $storedHash = session($sessionKey);
                
                if ($storedHash && hash_equals($storedHash, $verificationHash)) {
                    // Clear the verification token as it's single-use
                    session()->forget($sessionKey);
                    return ['requires_mfa' => false];
                }
            }

            // Check if remember token is valid and not expired
            if ($rememberToken) {
                $rememberHash = hash('sha256', $rememberToken);
                
                Log::info("MFA Remember Token Check", [
                    'empno' => $empno,
                    'remember_token_provided' => !empty($rememberToken),
                    'user_has_remember_hash' => !empty($userMFA->mfa_remember_hash),
                    'remember_expires' => $userMFA->mfa_remember_expires,
                    'is_expired' => $userMFA->mfa_remember_expires ? now()->gte($userMFA->mfa_remember_expires) : 'no_expiry_set',
                    'hash_matches' => $userMFA->mfa_remember_hash ? hash_equals($userMFA->mfa_remember_hash, $rememberHash) : false
                ]);
                
                if ($userMFA->mfa_remember_hash && 
                    hash_equals($userMFA->mfa_remember_hash, $rememberHash) &&
                    $userMFA->mfa_remember_expires && 
                    now()->lt($userMFA->mfa_remember_expires)) {
                    
                    Log::info("MFA Remember Token Valid - Bypassing MFA", ['empno' => $empno]);
                    return ['requires_mfa' => false];
                } else {
                    Log::info("MFA Remember Token Invalid or Expired", ['empno' => $empno]);
                }
            }

            return [
                'requires_mfa' => true,
                'setup_required' => false, // User already has MFA enabled
                'message' => 'Multi-Factor Authentication required',
                'qr_code' => null // QR code only needed during initial setup
            ];

        } catch (\Exception $e) {
            Log::error('MFA Check Error: ' . $e->getMessage());
            return ['requires_mfa' => false]; // Fail open for availability
        }
    }

    public function login(Request $request){
        try {
            $request->validate([
                'username' => 'required',
                'password' => 'required',
                'recaptcha_token' => 'required'
            ]);

            // Verify reCAPTCHA token
            $recaptchaResult = $this->verifyRecaptcha($request->recaptcha_token);
            if (!$recaptchaResult['success']) {
                $errorMessage = $recaptchaResult['error'] ?? 'Security verification failed. Please try again.';
                return response()->json([
                    'error' => $errorMessage,
                    'validation' => '1'
                ], 200);
            }
    
            $user = $this->validateUser($request);
            if(!$user){
                return response()->json(['error' => 'Incorrect username or password'], 401);
            }

            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }

            // Check MFA requirement
            $mfaCheck = $this->checkMFARequirement(
                $request->username, 
                $request->remember_token,
                $request->verification_token
            );
            
            if ($mfaCheck['requires_mfa']) {
                // Include all MFA check results in the response
                $response = array_merge($mfaCheck, [
                    'empno' => $request->username
                ]);
                return response()->json($response, 200);
            }

            // Log successful login
            AuditTrailService::logLogin($request->username, true, 'User logged in successfully');

            return response()->json([
                'status' => true,
                'message' => 'User Logged In Successfully',
                'token' => $user->createToken("API TOKEN")->plainTextToken,
                'UserInformation' => $user,
            ], 200);
    
        } catch(\Exception $e){
            // Log failed login attempt
            if (isset($request->username)) {
                AuditTrailService::logLogin($request->username, false, 'Login failed: ' . $e->getMessage());
            }
            
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $e->getMessage(),
                'status' => 500
            ]);
        }
    }
    
    public function logout(Request $request)
    {
        $user = $request->user();
        $empno = $user->empno ?? 'UNKNOWN';
        
        // Log logout action
        AuditTrailService::logLogout($empno, 'User logged out successfully');
        
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'User signed out successfully',
        ], 200);
    }

}
