<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Lets admin users inspect and update Terms of Service and Privacy Policy copy. */
class LegalAdminController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => SiteSetting::legalDocuments(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'terms_of_service' => ['sometimes', 'required', 'string', 'max:50000'],
            'privacy_policy' => ['sometimes', 'required', 'string', 'max:50000'],
        ]);

        if (! array_key_exists(SiteSetting::KEY_TERMS, $data) && ! array_key_exists(SiteSetting::KEY_PRIVACY, $data)) {
            throw ValidationException::withMessages([
                'terms_of_service' => 'Provide terms_of_service or privacy_policy to update.',
            ]);
        }

        if (array_key_exists(SiteSetting::KEY_TERMS, $data)) {
            SiteSetting::setValue(SiteSetting::KEY_TERMS, trim($data[SiteSetting::KEY_TERMS]));
        }
        if (array_key_exists(SiteSetting::KEY_PRIVACY, $data)) {
            SiteSetting::setValue(SiteSetting::KEY_PRIVACY, trim($data[SiteSetting::KEY_PRIVACY]));
        }

        return response()->json([
            'message' => 'Legal documents updated.',
            'data' => SiteSetting::legalDocuments(),
        ]);
    }
}

