<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'personalityAssessment',
            'skills.skillTag',
            'company',
            'employee',
        ]);

        return response()->json(new UserProfileResource($user));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json(new UserProfileResource($user->fresh()));
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => Storage::url($path)]);

        return response()->json(['avatar' => $user->avatar]);
    }

    public function updateLocale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:es,en,pt'],
        ]);

        $request->user()->update($validated);

        app()->setLocale($validated['locale']);

        return response()->json(['locale' => $validated['locale']]);
    }
}
