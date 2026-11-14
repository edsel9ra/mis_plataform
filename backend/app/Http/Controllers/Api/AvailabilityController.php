<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Availability\SaveAvailabilityExceptionRequest;
use App\Http\Requests\Availability\GetAvailabilitySlotsRequest;
use App\Http\Requests\Availability\SaveAvailabilityWindowRequest;
use App\Http\Resources\AvailabilitySlotResource;
use App\Http\Resources\MentorAvailabilityExceptionResource;
use App\Http\Resources\MentorAvailabilityWindowResource;
use App\Models\MentorAvailabilityException;
use App\Models\MentorAvailabilityWindow;
use App\Models\Session;
use App\Models\User;
use App\Enums\AvailabilityMode;
use App\Services\Scheduling\AvailabilitySlotsService;
use App\Services\Scheduling\ExceptionConflictsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AvailabilityController
{
    public function slots(
        GetAvailabilitySlotsRequest $request,
        User $mentor,
        AvailabilitySlotsService $availabilitySlots,
    ): JsonResponse {
        $slots = $availabilitySlots->forMentor(
            $mentor,
            $request->user(),
            $request->validated('from'),
            $request->validated('to'),
            (int) $request->validated('duration_minutes'),
        );

        return AvailabilitySlotResource::collection($slots)->response();
    }

    public function windows(Request $request): JsonResponse
    {
        Gate::authorize('manageAvailability', [Session::class, $request->user()]);

        return MentorAvailabilityWindowResource::collection(
            $request->user()->availabilityWindows()->orderBy('day_of_week')->orderBy('start_time')->get(),
        )->response();
    }

    public function showWindow(MentorAvailabilityWindow $window): JsonResponse
    {
        Gate::authorize('manageAvailability', [Session::class, $window]);

        return response()->json(new MentorAvailabilityWindowResource($window));
    }

    public function storeWindow(SaveAvailabilityWindowRequest $request): JsonResponse
    {
        $window = $request->user()->availabilityWindows()->create($request->validated());

        return response()->json(new MentorAvailabilityWindowResource($window), 201);
    }

    public function updateWindow(SaveAvailabilityWindowRequest $request, MentorAvailabilityWindow $window): JsonResponse
    {
        $window->update($request->validated());

        return response()->json(new MentorAvailabilityWindowResource($window->refresh()));
    }

    public function destroyWindow(MentorAvailabilityWindow $window): JsonResponse
    {
        Gate::authorize('manageAvailability', [Session::class, $window]);
        $window->delete();

        return response()->json(null, 204);
    }

    public function exceptions(Request $request): JsonResponse
    {
        Gate::authorize('manageAvailability', [Session::class, $request->user()]);

        return MentorAvailabilityExceptionResource::collection(
            $request->user()->availabilityExceptions()->orderBy('start_date')->get(),
        )->response();
    }

    public function showException(MentorAvailabilityException $exception): JsonResponse
    {
        Gate::authorize('manageAvailability', [Session::class, $exception]);

        return response()->json(new MentorAvailabilityExceptionResource($exception));
    }

    public function storeException(
        SaveAvailabilityExceptionRequest $request,
        ExceptionConflictsService $exceptionConflicts,
    ): JsonResponse {
        $exception = $request->user()->availabilityExceptions()->create($request->validated());

        $resource = new MentorAvailabilityExceptionResource($exception);

        if ($exception->mode === AvailabilityMode::Block) {
            $resource->withConflictingSessions($exceptionConflicts->forException($exception, now()));
        }

        return response()->json($resource, 201);
    }

    public function updateException(
        SaveAvailabilityExceptionRequest $request,
        MentorAvailabilityException $exception,
        ExceptionConflictsService $exceptionConflicts,
    ): JsonResponse {
        $exception->update($request->validated());

        $exception = $exception->refresh();
        $resource = new MentorAvailabilityExceptionResource($exception);

        if ($exception->mode === AvailabilityMode::Block) {
            $resource->withConflictingSessions($exceptionConflicts->forException($exception, now()));
        }

        return response()->json($resource);
    }

    public function destroyException(MentorAvailabilityException $exception): JsonResponse
    {
        Gate::authorize('manageAvailability', [Session::class, $exception]);
        $exception->delete();

        return response()->json(null, 204);
    }
}
