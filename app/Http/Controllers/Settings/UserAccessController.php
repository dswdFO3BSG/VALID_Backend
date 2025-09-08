<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ClientVerification\UserAccess;
use App\Models\ClientVerification\UserModules;
use App\Models\User;
use Illuminate\Http\Request;

class UserAccessController extends Controller
{
    public function getUserAccess(Request $request){
        try{

            $userAccess = UserAccess::where('empno', $request->empNo)
                ->join('user_modules', 'user_access.module_id', '=', 'user_modules.module_id')
                ->whereNull('user_modules.parent')
                ->where('user_modules.main_menu', 1)
                ->orderBy('user_modules.order', 'asc')
                ->get();

            if($userAccess->isEmpty()){
                return response()->json([
                    'status' => 'error',
                    'message' => 'No access found'
                ]);
            }

            foreach ($userAccess as $key => $data) {
                $userAccessData[$key]['items'][] = [
                    'module_id' => $data->module_id,
                    'label' => $data->label,
                    'icon' => $data->icon,
                    'to' => $data->to
                ];
            }

            $settingsAccess = UserAccess::where('empno', $request->empNo)
                ->join('user_modules', 'user_access.module_id', '=', 'user_modules.module_id')
                ->where('user_modules.parent', 1)
                ->where('user_modules.main_menu', 1)
                ->orderBy('user_modules.order', 'asc')
                ->get();


            if($settingsAccess->isEmpty()){
                $subMenu = [];
                $userAccessData[] = $subMenu;
                
            } else {

                foreach ($settingsAccess as $key => $data) {
                    $settingsAccessData[$key] = [
                        'module_id' => $data->module_id,
                        'label' => $data->label,
                        'icon' => $data->icon,
                        'to' => $data->to
                    ];
                }
    
                $subMenu = [
                    'to' => $settingsAccess[0]['parent_to'],
                    'items' => [
                        [
                            'label' => $settingsAccess[0]['parent_label'],
                            'icon' => $settingsAccess[0]['parent_icon'],
                            'items' => $settingsAccessData
                        ]
                    ]
                ];
    
                $userAccessData[] = $subMenu;
            }

            
            
            return response()->json([
                'status' => 'success',
                'data' => $userAccessData
            ]);

        } catch (\Exception $e){
            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
                'method' => 'getUserAccess',
                'status' => 500
            ], 500);
        }
        
    }

    public function getUserModules() {
        try {

            $userModules = UserModules::where('status', 1)->get();

            return response()->json([
                'status' => 'success',
                'data' => $userModules
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
                'method' => 'getUserModules',
                'status' => 500
            ], 500);
        }
    }

    public function getUsers(Request $request){
        try{
            $filters = $request->only(['sname', 'fname', 'mname']);
            
            $users = User::leftJoin('lib_position as p', 'p.position_id', '=', 'userprofile.position_id')
                ->leftJoin('lib_position_name as pn', 'pn.position_name_id', '=', 'p.position_name_id')

                ->where('userprofile.emp_status', '0')
                ->when($filters['sname'] ?? null, fn($query, $sname) => $query->where('userprofile.sname', 'LIKE', "%$sname%"))
                ->when($filters['fname'] ?? null, fn($query, $fname) => $query->where('userprofile.fname', 'LIKE', "%$fname%"))
                ->when($filters['mname'] ?? null, fn($query, $mname) => $query->where('userprofile.mname', 'LIKE', "%$mname%"))
                ->orderBy('userprofile.sname')
                ->get([
                    'userprofile.empno',
                    'userprofile.fname',
                    'userprofile.mname',
                    'userprofile.sname',
                    'pn.position_initial',
                    'pn.position_name'
                ]);

                return response()->json([
                    'status' => 'success',
                    'data' => $users
                ]);

        } catch (\Exception $e){
            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
                'method' => 'getUsers',
                'status' => 500
            ], 500);
        }
    }
    
    public function getUserCurrentModules(Request $request){
        try{
            $userModules = UserAccess::where('empno', $request->empNo)
            ->get(['module_id', 'empno']);

            return response()->json([
                'status' => 'success',
                'data' => $userModules
            ]);

        } catch (\Exception $e){
            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
                'method' => 'getUserCurrentModules',
                'status' => 500
            ], 500);
        }
    }

    public function saveAccess(Request $request){
        try{
            $empNo = $request->input('empNo', '');
            $addedBy = $request->input('addedBy', '');
            $modules = $request->input('modules', '');

            UserAccess::where('empno', $empNo)->delete();

            foreach ($modules as $module) {
                UserAccess::create([
                    'empno' => $module['empno'],
                    'added_by' => $module['addedBy'],
                    'module_id' => $module['module_id']
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Access successfully saved'
            ]);

        } catch (\Exception $e){
            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
                'method' => 'saveAccess',
                'status' => 500
            ], 500);
        }
    }

    public function checkUserAccessPath(Request $request){
        try{
            $user = $request->input('user', '');
            $path = $request->input('path', '');

            $userAccess = UserAccess::where('empno', $user)
                ->join('user_modules', 'user_access.module_id', '=', 'user_modules.module_id')
                ->where('user_modules.to', $path)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $userAccess
            ]);

        } catch (\Exception $e){
            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
                'method' => 'checkUserAccessPath',
                'status' => 500
            ], 500);
        }
    }
}
