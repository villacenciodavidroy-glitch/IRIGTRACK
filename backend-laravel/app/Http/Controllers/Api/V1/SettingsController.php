<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FormLabel;
use App\Support\Logo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Get current logo URL (public).
     */
    public function logo(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'url' => Logo::url(),
            'has_custom' => Logo::hasCustom(),
        ]);
    }

    /**
     * Upload / replace logo (admin only).
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|file|image|mimes:png,jpeg,jpg,gif,webp|max:2048',
        ], [
            'logo.required' => 'Please select an image file.',
            'logo.image' => 'The file must be an image.',
            'logo.mimes' => 'Allowed formats: PNG, JPEG, JPG, GIF, WebP.',
            'logo.max' => 'The image may not be larger than 2 MB.',
        ]);

        try {
            $file = $request->file('logo');
            $path = Logo::STORAGE_PATH;

            $file->storeAs('', $path, 'public');

            Log::info('Logo updated by user ID: ' . ($request->user()?->id ?? 'unknown'));

            return response()->json([
                'success' => true,
                'message' => 'Logo updated successfully.',
                'url' => Logo::url(),
                'has_custom' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Logo upload failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update logo. Please try again.',
            ], 500);
        }
    }

    /**
     * Get all form labels (public).
     */
    public function formLabels(): JsonResponse
    {
        try {
            $labels = FormLabel::all()->keyBy('key');
            return response()->json([
                'success' => true,
                'data' => $labels->values()->all(), // Return as array for frontend
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch form labels: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch form labels',
            ], 500);
        }
    }

    /**
     * Update form labels (admin only).
     */
    public function updateFormLabels(Request $request): JsonResponse
    {
        $request->validate([
            'labels' => 'required|array',
            'labels.*.key' => 'required|string',
            'labels.*.label' => 'nullable|string|max:255',
            'labels.*.placeholder' => 'nullable|string|max:255',
            'labels.*.section_title' => 'nullable|string|max:255',
            'labels.*.section_subtitle' => 'nullable|string|max:500',
            'labels.*.helper_text' => 'nullable|string|max:500',
        ]);

        try {
            $updated = [];
            foreach ($request->input('labels', []) as $labelData) {
                $label = FormLabel::updateOrCreate(
                    ['key' => $labelData['key']],
                    [
                        'label' => $labelData['label'] ?? '',
                        'placeholder' => $labelData['placeholder'] ?? null,
                        'section_title' => $labelData['section_title'] ?? null,
                        'section_subtitle' => $labelData['section_subtitle'] ?? null,
                        'helper_text' => $labelData['helper_text'] ?? null,
                    ]
                );
                $updated[] = $label;
            }

            Log::info('Form labels updated by user ID: ' . ($request->user()?->id ?? 'unknown'));

            return response()->json([
                'success' => true,
                'message' => 'Form labels updated successfully.',
                'data' => FormLabel::all()->keyBy('key'),
            ]);
        } catch (\Exception $e) {
            Log::error('Form labels update failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update form labels. Please try again.',
            ], 500);
        }
    }
}
