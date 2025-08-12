<?php

namespace App\Http\Controllers\ClientVerification;

use App\Http\Controllers\Controller;
use App\Models\VerifiedClients;
use Illuminate\Http\Request;

class uploadPhotoController extends Controller
{
    public function uploadPhoto(Request $request)
{
    $request->validate([
        'beneficiary_id' => 'required',
        'photo' => 'required|string', // base64 string
    ]);

    $beneficiaryId = $request->input('beneficiary_id');
    $base64Image = $request->input('photo');

    // Extract base64 data
    if (preg_match('/^data:image\\/(\\w+);base64,/', $base64Image, $type)) {
        $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
        $type = strtolower($type[1]); // jpg, png, gif

        $base64Image = base64_decode($base64Image);
        if ($base64Image === false) {
            return response()->json(['error' => 'base64_decode failed'], 400);
        }
    } else {
        return response()->json(['error' => 'Invalid image data'], 400);
    }

    $publicPath = public_path("beneficiary_id_photos/{$beneficiaryId}");
    if (!file_exists($publicPath)) {
        mkdir($publicPath, 0777, true);
    }
    $filename = uniqid() . '.' . $type;
    $filepath = $publicPath . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($filepath, $base64Image);

    // Save the relative path in DB
    $faceUrl = "beneficiary_id_photos/{$beneficiaryId}/{$filename}";
    // Update the beneficiary record
    $beneficiary = VerifiedClients::where('beneficiary_id', $beneficiaryId)->first();
    if ($beneficiary) {
        $beneficiary->face_url = $faceUrl;
        $beneficiary->bene_id_created = 1;
        $beneficiary->bene_id_creation_date = now();
        $beneficiary->save();
    }

    return response()->json(['face_url' => asset($faceUrl)]);
}
}
