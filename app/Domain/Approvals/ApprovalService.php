<?php

namespace App\Domain\Approvals;

use App\Domain\Notifications\Notifier;
use App\Enums\ApprovalStatus;
use App\Enums\LoanStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\LoanApplication;
use App\Models\Member;
use App\Models\MurabahaFinancing;
use App\Models\QardHasanFinancing;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ApprovalService
{
    public function start(string $appliesTo, Model $approvable, int $amountKobo): ApprovalRequest
    {
        $workflow = ApprovalWorkflow::query()
            ->where('applies_to', $appliesTo)
            ->where('is_active', true)
            ->where('min_amount_kobo', '<=', $amountKobo)
            ->where(function ($q) use ($amountKobo) {
                $q->whereNull('max_amount_kobo')->orWhere('max_amount_kobo', '>=', $amountKobo);
            })
            ->orderByDesc('min_amount_kobo')
            ->first();

        if (! $workflow) {
            $this->markApproved($approvable);

            return ApprovalRequest::query()->make([
                'status' => ApprovalStatus::Approved,
            ]);
        }

        $request = ApprovalRequest::query()->create([
            'approval_workflow_id' => $workflow->id,
            'approvable_type' => $approvable->getMorphClass(),
            'approvable_id' => $approvable->getKey(),
            'current_step' => 1,
            'status' => ApprovalStatus::Pending,
            'requested_by' => auth()->id(),
        ]);

        $this->notifyRequested($approvable, $amountKobo);

        return $request;
    }

    public function act(ApprovalRequest $request, User $user, ApprovalStatus $decision, ?string $comment = null): ApprovalRequest
    {
        $request->load('workflow.steps');
        $step = $request->workflow->steps->firstWhere('position', $request->current_step);

        if (! $step) {
            throw new InvalidArgumentException('No current approval step.');
        }

        if (! $user->hasRole($step->role_name) && ! $user->hasRole('Administrator') && ! $user->hasRole('Branch Admin')) {
            throw new InvalidArgumentException("This step requires the {$step->role_name} role.");
        }

        $request->actions()->create([
            'approval_step_id' => $step->id,
            'user_id' => $user->id,
            'decision' => $decision,
            'comment' => $comment,
            'ip_address' => request()->ip(),
        ]);

        activity()
            ->causedBy($user)
            ->performedOn($request->approvable)
            ->withProperties(['decision' => $decision->value, 'step' => $step->name])
            ->log('approval.'.$decision->value);

        if ($decision === ApprovalStatus::Rejected) {
            $request->update(['status' => ApprovalStatus::Rejected, 'completed_at' => now()]);
            $this->markRejected($request->approvable, $comment);
            $this->notifyRejected($request->approvable, $comment);

            return $request->fresh();
        }

        $next = $request->workflow->steps->firstWhere('position', $request->current_step + 1);

        if ($next) {
            $request->update(['current_step' => $next->position]);

            return $request->fresh();
        }

        $request->update(['status' => ApprovalStatus::Approved, 'completed_at' => now()]);
        $this->markApproved($request->approvable);
        $this->notifyApproved($request->approvable);

        return $request->fresh();
    }

    private function markApproved(Model $model): void
    {
        if ($model instanceof LoanApplication) {
            $model->update(['status' => LoanStatus::Approved]);
        }

        if ($model instanceof WithdrawalRequest) {
            $model->update(['status' => 'approved']);
        }

        if (method_exists($model, 'update') && isset($model->status) && ! $model instanceof LoanApplication && ! $model instanceof WithdrawalRequest) {
            $model->update(['status' => LoanStatus::Approved]);
        }
    }

    private function markRejected(Model $model, ?string $reason): void
    {
        if ($model instanceof LoanApplication) {
            $model->update(['status' => LoanStatus::Rejected, 'rejection_reason' => $reason]);
        }

        if ($model instanceof WithdrawalRequest) {
            $model->update(['status' => 'rejected', 'review_notes' => $reason]);
        }

        if ($model instanceof QardHasanFinancing || $model instanceof MurabahaFinancing) {
            $model->update(['status' => LoanStatus::Rejected]);
        }
    }

    private function notifyRequested(Model $approvable, int $amountKobo): void
    {
        [$label] = $this->approvableCopy($approvable);

        app(Notifier::class)->approvalRequested(
            $label,
            $this->memberOf($approvable),
            $amountKobo,
            tenant_path('approvals'),
        );
    }

    private function notifyRejected(Model $model, ?string $reason): void
    {
        [$label] = $this->approvableCopy($model);
        app(Notifier::class)->approvalRejected($this->memberOf($model), $label, $reason);
    }

    private function notifyApproved(Model $model): void
    {
        if ($model instanceof LoanApplication || $model instanceof WithdrawalRequest) {
            return;
        }

        [$label] = $this->approvableCopy($model);
        app(Notifier::class)->financingApproved($this->memberOf($model), $label);
    }

    /**
     * @return array{0: string}
     */
    private function approvableCopy(Model $model): array
    {
        return match (true) {
            $model instanceof LoanApplication => ['loan'],
            $model instanceof WithdrawalRequest => ['withdrawal'],
            $model instanceof QardHasanFinancing => ['Qard Hasan'],
            $model instanceof MurabahaFinancing => ['Murabaha'],
            default => ['request'],
        };
    }

    private function memberOf(Model $model): ?Member
    {
        if (method_exists($model, 'member')) {
            $model->loadMissing('member');

            return $model->member;
        }

        return null;
    }
}
