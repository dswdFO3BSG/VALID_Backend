<?php

namespace App\Http\Controllers\ClientVerification;

use App\Http\Controllers\Controller;
use App\Models\VerifiedClients;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ClientVerificationController extends Controller
{
    public function getTotalCount () {
        $total = VerifiedClients::where('status', 1)->count();
        $verified = VerifiedClients::where('verification_result', 1)->where('status', 1)->count();
        $unverified = VerifiedClients::where('verification_result', 0)->where('status', 1)->count();
        $male = VerifiedClients::where('sex', 'Male')->where('status', 1)->count();
        $female = VerifiedClients::where('sex', 'Female')->where('status', 1)->count();
        return response()->json([
            'status' => 'success',
            'total' => $total,
            'verified' => $verified,
            'unverified' => $unverified,
            'male' => $male,
            'female' => $female,
        ]);
    }

    public function getCountAge () {
        $children = VerifiedClients::where('status', 1)->where('verification_result', 1)
            ->whereRaw('TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 0 AND 14')
            ->count();
        $early_working = VerifiedClients::where('status', 1)->where('verification_result', 1)
            ->whereRaw('TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 15 AND 24')
            ->count();
        $prime_working = VerifiedClients::where('status', 1)->where('verification_result', 1)
            ->whereRaw('TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 25 AND 54')
            ->count();
        $mature_working = VerifiedClients::where('status', 1)->where('verification_result', 1)
            ->whereRaw('TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 55 AND 64')
            ->count();
        $elderly = VerifiedClients::where('status', 1)->where('verification_result', 1)
            ->whereRaw('TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) >= 65')
            ->count();

        return response()->json([
            'status' => 'success',
            'children' => $children,
            'early_working' => $early_working,
            'prime_working' => $prime_working,
            'mature_working' => $mature_working,
            'elderly' => $elderly,
        ]);
    }

    public function getCountMunicipality (Request $request) {
        $province = $request->province;

        $clients = VerifiedClients::select('municipality', DB::raw('COUNT(*) as total_clients'))
        ->where('province', $province)
        ->where('verification_result', 1)
        ->groupBy('municipality')
        ->get();

        return response()->json([
            'status' => 'success',
            'municipalities' => $clients,
        ]);
    }
    
    public function getClients(Request $request) {
        $query = VerifiedClients::where('status', 1);

        if ($request->filled('beneficiary_id')) {
            $query->where('beneficiary_id', 'like', '%' . $request->beneficiary_id . '%');
        }
        if ($request->filled('lastname')) {
            $query->where('last_name', 'like', '%' . $request->lastname . '%');
        }
        if ($request->filled('firstName')) {
            $query->where('first_name', 'like', '%' . $request->firstName . '%');
        }
        if ($request->filled('middleName')) {
            $query->where('middle_name', 'like', '%' . $request->middleName . '%');
        }
        if ($request->filled('suffix')) {
            $query->where('suffix_name', 'like', '%' . $request->suffix . '%');
        }
        if ($request->filled('birth_date')) {
            $formattedBirthdate = Carbon::parse($request->birthdate)->setTimezone(config('app.timezone'))->format('Y-m-d');
            $query->whereDate('birth_date', '=',  $formattedBirthdate);
        }

        try {
            $clients = $query
            ->selectRaw('id, psn, CONCAT(COALESCE(last_name, ""), ", ", COALESCE(first_name, ""), " ", COALESCE(middle_name, ""), " ", COALESCE(suffix_name, "")) as fullName, last_name, first_name, middle_name, suffix_name, birth_date, DATE_FORMAT(birth_date, "%M %d, %Y") as formattedBirthdate, DATE_FORMAT(verified_at, "%M %d, %Y") as verified_at, verification_result, bene_id_created, bene_id_creation_date')
            ->orderBy('verified_at', 'desc')->get();

            return response()->json([
                'status' => 'success',
                'data' => $clients,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function saveClients(Request $request) {
        try {
            $psn = $request->psn ?? null;
            $face_url = $request->face_url ?? null;
            $first_name = strtoupper($request->firstName ?? null);
            $middle_name = strtoupper($request->middleName ?? null);
            $last_name = strtoupper($request->lastName ?? null);
            $suffix = strtoupper($request->suffix ?? null);
            $sex = $request->sex ?? null;
            $marital_status = $request->marital_status ?? null;
            $email = $request->email ?? null;
            $mobile_number = $request->mobile_number ?? null;
            $birth_date = $request->birth_date ?? null;
            $address_line_1 = $request->address_line_1 ?? null;
            $address_line_2 = $request->address_line_2 ?? null;
            $barangay = $request->barangay ?? null;
            $municipality = $request->municipality ?? null;
            $province = $request->province ?? null;
            $country = $request->country ?? null;
            $postal_code = $request->postal_code ?? null;
            $present_address_line_1 = $request->present_address_line_1 ?? null;
            $present_address_line_2 = $request->present_address_line_2 ?? null;
            $present_barangay = $request->present_barangay ?? null;
            $present_municipality = $request->present_municipality ?? null;
            $present_province = $request->present_province ?? null;
            $present_country = $request->present_country ?? null;
            $present_postal_code = $request->present_postal_code ?? null;
            $residency_status = $request->residency_status ?? null;
            $place_of_birth = $request->place_of_birth ?? null;
            $pob_municipality = $request->pob_municipality ?? null;
            $pob_province = $request->pob_province ?? null;
            $pob_country = $request->pob_country ?? null;
            $verified_by = $request->verified_by ?? null;
            $verification_result = $request->verificationResult ?? 0;
            $beneficiary_id = '';
                if ($last_name && $birth_date) {
                    $prefix = substr($last_name, 0, 2);
                    $year = date('Y', strtotime($birth_date));
                    $suffix_id = substr($year, -2);
                    $random = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
                    $beneficiary_id = $prefix . $suffix_id . $random;
                }            
            $verifiedClient = VerifiedClients::create([
                'psn'=> $psn,
                'beneficiary_id'=> $beneficiary_id,
                // 'face_url'=> $face_url,
                'first_name'=> $first_name,
                'middle_name'=> $middle_name,
                'last_name'=> $last_name,
                'suffix_name'=> $suffix,
                'sex'=> $sex,
                'marital_status'=> $marital_status,
                'email'=> $email,
                'mobile_number'=> $mobile_number,
                'birth_date'=> $birth_date,
                'address_line_1'=> $address_line_1,
                'address_line_2'=> $address_line_2,
                'barangay'=> $barangay,
                'municipality'=> $municipality,
                'province'=> $province,
                'country'=> $country,
                'postal_code'=> $postal_code,
                'present_address_line_1'=> $present_address_line_1,
                'present_address_line_2'=> $present_address_line_2,
                'present_barangay'=> $present_barangay,
                'present_municipality'=> $present_municipality,
                'present_province'=> $present_province,
                'present_country'=> $present_country,
                'present_postal_code'=> $present_postal_code,
                'residency_status'=> $residency_status,
                'place_of_birth'=> $place_of_birth,
                'pob_municipality'=> $pob_municipality,
                'pob_province'=> $pob_province,
                'pob_country'=> $pob_country,
                'verified_at'=> NOW(),
                'verified_by'=> $verified_by,
                'verification_result'=> $verification_result,
                'status'=> 1
            ]);

            return response()->json([
                'message' => 'success',
                'status' => 200,
                'verifiedClient' => $verifiedClient
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
                'method' => 'saveSalaryGradeHeader',
                'status' => 500
            ], 500);
        }
    }

    public function updateClients(Request $request) {
        try {
            $id = $request->id ?? null;
            $psn = $request->psn ?? null;
            $face_url = $request->face_url ?? null;
            $first_name = strtoupper($request->firstName ?? null);
            $middle_name = strtoupper($request->middleName ?? null);
            $last_name = strtoupper($request->lastName ?? null);
            $suffix = strtoupper($request->suffix ?? null);
            $sex = $request->sex ?? null;
            $marital_status = $request->marital_status ?? null;
            $email = $request->email ?? null;
            $mobile_number = $request->mobile_number ?? null;
            $birth_date = $request->birth_date ?? null;
            $address_line_1 = $request->address_line_1 ?? null;
            $address_line_2 = $request->address_line_2 ?? null;
            $barangay = $request->barangay ?? null;
            $municipality = $request->municipality ?? null;
            $province = $request->province ?? null;
            $country = $request->country ?? null;
            $postal_code = $request->postal_code ?? null;
            $present_address_line_1 = $request->present_address_line_1 ?? null;
            $present_address_line_2 = $request->present_address_line_2 ?? null;
            $present_barangay = $request->present_barangay ?? null;
            $present_municipality = $request->present_municipality ?? null;
            $present_province = $request->present_province ?? null;
            $present_country = $request->present_country ?? null;
            $present_postal_code = $request->present_postal_code ?? null;
            $residency_status = $request->residency_status ?? null;
            $place_of_birth = $request->place_of_birth ?? null;
            $pob_municipality = $request->pob_municipality ?? null;
            $pob_province = $request->pob_province ?? null;
            $pob_country = $request->pob_country ?? null;
            $verified_by = $request->verified_by ?? null;
            $verification_result = $request->verificationResult ?? 0;
            
            $verifiedClient = VerifiedClients::where('id', $id)->update([
                'psn'=> $psn,
                // 'face_url'=> $face_url,
                'first_name'=> $first_name,
                'middle_name'=> $middle_name,
                'last_name'=> $last_name,
                'suffix_name'=> $suffix,
                'sex'=> $sex,
                'marital_status'=> $marital_status,
                'email'=> $email,
                'mobile_number'=> $mobile_number,
                'address_line_1'=> $address_line_1,
                'address_line_2'=> $address_line_2,
                'barangay'=> $barangay,
                'municipality'=> $municipality,
                'province'=> $province,
                'country'=> $country,
                'postal_code'=> $postal_code,
                'present_address_line_1'=> $present_address_line_1,
                'present_address_line_2'=> $present_address_line_2,
                'present_barangay'=> $present_barangay,
                'present_municipality'=> $present_municipality,
                'present_province'=> $present_province,
                'present_country'=> $present_country,
                'present_postal_code'=> $present_postal_code,
                'residency_status'=> $residency_status,
                'place_of_birth'=> $place_of_birth,
                'pob_municipality'=> $pob_municipality,
                'pob_province'=> $pob_province,
                'pob_country'=> $pob_country,
                'verified_at'=> NOW(),
                'verified_by'=> $verified_by,
                'verification_result'=> $verification_result,
                'status'=> 1
            ]);

            return response()->json([
                'message' => 'success',
                'status' => 200,
                'verifiedClient' => $verifiedClient
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
                'method' => 'saveSalaryGradeHeader',
                'status' => 500
            ], 500);
        }
    }
}
