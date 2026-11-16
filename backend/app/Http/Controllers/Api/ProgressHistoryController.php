<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Progress\AdminProgressHistoryRequest;
use App\Http\Requests\Progress\ProgressHistoryRequest;
use App\Http\Resources\ProgressHistoryResource;
use App\Models\User;
use App\Services\Progress\History\ProgressHistoryService;
use App\Services\Progress\ProgressAccessService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Unified progress history (RF-015.1/.7/.8): the same ProgressAccessService
 * matrix as the panel and the learning map — self, assigned mentor with
 * workspace entitlement, support — 404 for unassigned mentees.
 */
class ProgressHistoryController
{
    public function __construct(
        private ProgressHistoryService $history,
        private ProgressAccessService $access,
    ) {}

    public function index(ProgressHistoryRequest $request): ProgressHistoryResource
    {
        $subject = $request->user();
        $this->access->assertUserPanel($subject, $subject);

        return $this->page($request, $subject);
    }

    public function show(ProgressHistoryRequest $request, User $user): ProgressHistoryResource
    {
        $this->access->assertUserPanel($request->user(), $user);

        return $this->page($request, $user);
    }

    public function support(AdminProgressHistoryRequest $request): ProgressHistoryResource
    {
        $subject = User::findOrFail($request->validated('user_id'));
        $this->access->assertUserPanel($request->user(), $subject);

        return $this->page($request, $subject);
    }

    private function page(ProgressHistoryRequest $request, User $subject): ProgressHistoryResource
    {
        try {
            $page = $this->history->page($subject, $request->cursor(), $request->limit());
        } catch (Throwable $e) {
            Log::error('progress.history_failed', [
                'viewer_id' => $request->user()->id,
                'subject_id' => $subject->id,
                'exception' => $e::class,
            ]);

            throw $e;
        }

        return new ProgressHistoryResource($page);
    }
}
