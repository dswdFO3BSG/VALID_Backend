<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\VerifiedClients;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function generateReports(Request $request){
        try{
            $lastName = $request->input('lastName', '');
            $firstName = $request->input('firstName', '');
            $middleName = $request->input('middleName', '');
            $dateFrom = $request->input('dateFrom', '');
            $dateTo = $request->input('dateTo', '');
            $verificationType = $request->input('verificationType', '');

            $data = VerifiedClients::select('*')
                ->when($middleName, function($query) use ($middleName){
                    return $query ->where('middle_name','like','%'.$middleName.'%');
                })
                ->when($firstName, function($query) use ($firstName){
                    return $query ->where('first_name','like','%'.$firstName.'%');
                })
                ->when($lastName, function($query) use ($lastName){
                    return $query ->where('last_name','like','%'.$lastName.'%');
                })
                ->when($dateFrom, function($query) use ($dateFrom){
                    return $query ->where('verified_at','>=',$dateFrom);
                })
                ->when($dateTo, function($query) use ($dateTo){
                    return $query ->where('verified_at','<=',$dateTo);
                })
                ->when($verificationType == 1, function($query){
                    return $query ->where('verification_result','=',1);
                })
                ->when($verificationType == 0, function($query){
                    return $query ->where('verification_result','=',0);
                })
        
                ->orderBy('id', 'desc')
                ->get();

            return response()->json([
                'message' => 'success',
                'status' => 200,
                'data' => $data
            ]);

        } catch (\Exception $e){
            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
                'method' => 'generateReports',
                'status' => 500
            ], 500);
        }
    }
}
