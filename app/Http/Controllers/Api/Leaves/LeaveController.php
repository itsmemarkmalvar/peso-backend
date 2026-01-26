<?php

namespace App\Http\Controllers\Api\Leaves;

use App\Http\Controllers\Api\BaseController;
use App\Models\Intern;
use App\Models\Leave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends BaseController
{
    private function formatLeave(Leave $leave): array
    {
        $intern = $leave->intern;

        return [
            'id' => $leave->id,
            'intern_id' => $leave->intern_id,
            'intern_name' => $intern?->full_name ?? 'Unknown',
            'intern_student_id' => $intern?->student_id ?? '',
            'type' => ucfirst($leave->type),
            'reason_title' => $leave->reason_title,
            'status' => ucfirst($leave->status),
            'start_date' => optional($leave->start_date)->toDateString(),
            'end_date' => optional($leave->end_date)->toDateString(),
            'notes' => $leave->notes,
            'rejection_reason' => $leave->rejection_reason,
            'approved_by' => $leave->approved_by,
            'approved_at' => optional($leave->approved_at)->toISOString(),
            'created_at' => optional($leave->created_at)->toISOString(),
            'updated_at' => optional($leave->updated_at)->toISOString(),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $leaves = Leave::with('intern')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Leave $leave) => $this->formatLeave($leave))
            ->values();

        return $this->success($leaves, 'Leave requests list');
    }

    public function pending(Request $request): JsonResponse
    {
        $leaves = Leave::with('intern')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Leave $leave) => $this->formatLeave($leave))
            ->values();

        return $this->success($leaves, 'Pending leave requests');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['Leave', 'Holiday', 'leave', 'holiday'])],
            'reason_title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $intern = Intern::where('user_id', $user->id)->first();

        if (!$intern) {
            return $this->error('Intern profile not found.', 404);
        }

        $leave = Leave::create([
            'intern_id' => $intern->id,
            'type' => strtolower($validated['type']),
            'reason_title' => $validated['reason_title'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        $leave->load('intern');

        return $this->success($this->formatLeave($leave), 'Leave request submitted.', 201);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'comments' => 'nullable|string|max:1000',
        ]);

        $leave = Leave::with('intern')->find($id);

        if (!$leave) {
            return $this->notFound('Leave request not found.');
        }

        if ($leave->status !== 'pending') {
            return $this->error('This leave request has already been processed.', 422);
        }

        $leave->status = 'approved';
        $leave->approved_by = $request->user()->id;
        $leave->approved_at = now();
        $leave->rejection_reason = null;

        if (!empty($validated['comments'])) {
            $leave->notes = $validated['comments'];
        }

        $leave->save();

        return $this->success($this->formatLeave($leave), 'Leave request approved.');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
            'comments' => 'nullable|string|max:1000',
        ]);

        $leave = Leave::with('intern')->find($id);

        if (!$leave) {
            return $this->notFound('Leave request not found.');
        }

        if ($leave->status !== 'pending') {
            return $this->error('This leave request has already been processed.', 422);
        }

        $leave->status = 'rejected';
        $leave->approved_by = $request->user()->id;
        $leave->approved_at = now();
        $leave->rejection_reason = $validated['reason'];

        if (!empty($validated['comments'])) {
            $leave->notes = $validated['comments'];
        }

        $leave->save();

        return $this->success($this->formatLeave($leave), 'Leave request rejected.');
    }
}
