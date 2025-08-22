<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Container\Attributes\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthenticationController extends Controller
{

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
            return response()->json(['error' => 'Employee Number not found. Please coordinate with HR.', 'validation' => '1'], 200);
        }
                
        if ($user->account_status == 2) {
            if ($user->isLog == 0) {
                if(Hash::check($request->password, $user->password)){
                    return $user;
                } else {
                    return response()->json(['error' => 'Incorrect username or password', 'validation' => '1'], 200);
                }
            } else {
                return response()->json(['error' => 'To comply with cybersecurity policies, please update your password immediately. You will be redirected to the Employee Registration Module to log in and update your password.' , 'validation' => '2'], 200);
            }
        } else {
            return response()->json(['error' => 'Please Coordinate with HR Regarding Your ERM Account' , 'validation' => '3', 'accountStatus' => $user->account_status_name], 200);
        }
    }

    public function login(Request $request){
        try {
            $request->validate([
                'username' => 'required',
                'password' => 'required'
            ]);
    
            $user = $this->validateUser($request);
            if(!$user){
                return response()->json(['error' => 'Incorrect username or password'], 401);
            }

            if ($user instanceof \Illuminate\Http\JsonResponse) {
                return $user;
            }
            return response()->json([
                'status' => true,
                'message' => 'User Logged In Successfully',
                'token' => $user->createToken("API TOKEN")->plainTextToken,
                'UserInformation' => $user,
            ], 200);
    
        } catch(\Exception $e){
            return response()->json([
                'message' => 'An error occurred.',
                'error' => $e->getMessage(),
                'status' => 500
            ]);
        }
    }
    
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'User signed out successfully',
        ], 200);
    }

}
