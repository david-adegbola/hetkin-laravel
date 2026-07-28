<?php

use App\Domain\Billing\Actions\ApproveLiveAction;
use App\Domain\Billing\Actions\ConfirmShadowReviewAction;
use App\Domain\Billing\Actions\DisputeRecordAction;
use App\Domain\Billing\Actions\ReopenDisputedAction;
use App\Domain\Billing\Enums\VerificationReviewStatus;
use App\Domain\Billing\Models\VerificationRecord;
use App\Domain\Organizations\Models\Organization;
use App\Models\AdminUser;

test('confirmShadowReview moves a pending record to shadow_confirmed', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $record = VerificationRecord::factory()->for($organization, 'organization')->create();

    $result = app(ConfirmShadowReviewAction::class)->execute($record->id, $admin);

    expect($result->review_status)->toBe(VerificationReviewStatus::ShadowConfirmed);
    expect($result->reviewed_by_admin_user_id)->toBe($admin->id);
});

test('confirmShadowReview is a no-op on a record not pending review', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $record = VerificationRecord::factory()->liveApproved()->for($organization, 'organization')->create();

    $result = app(ConfirmShadowReviewAction::class)->execute($record->id, $admin);

    expect($result->review_status)->toBe(VerificationReviewStatus::LiveApproved);
});

test('approveLive only accepts a shadow_confirmed record', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $pending = VerificationRecord::factory()->for($organization, 'organization')->create();
    $confirmed = VerificationRecord::factory()->shadowConfirmed()->for($organization, 'organization')->create();

    $noop = app(ApproveLiveAction::class)->execute($pending->id, $admin);
    $approved = app(ApproveLiveAction::class)->execute($confirmed->id, $admin);

    expect($noop->review_status)->toBe(VerificationReviewStatus::ShadowPendingReview);
    expect($approved->review_status)->toBe(VerificationReviewStatus::LiveApproved);
    expect($approved->reviewed_by_admin_user_id)->toBe($admin->id);
});

test('disputeRecord accepts anything except live_approved or already-disputed', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $pending = VerificationRecord::factory()->for($organization, 'organization')->create();
    $live = VerificationRecord::factory()->liveApproved()->for($organization, 'organization')->create();

    $disputed = app(DisputeRecordAction::class)->execute($pending->id, $admin);
    $stillLive = app(DisputeRecordAction::class)->execute($live->id, $admin);

    expect($disputed->review_status)->toBe(VerificationReviewStatus::Disputed);
    expect($stillLive->review_status)->toBe(VerificationReviewStatus::LiveApproved);
});

test('reopenDisputed sends a disputed record back to shadow_pending_review, clearing the reviewer', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $disputed = VerificationRecord::factory()->disputed()->for($organization, 'organization')->create([
        'reviewed_by_admin_user_id' => $admin->id,
    ]);

    $reopened = app(ReopenDisputedAction::class)->execute($disputed->id, $admin);

    expect($reopened->review_status)->toBe(VerificationReviewStatus::ShadowPendingReview);
    expect($reopened->reviewed_by_admin_user_id)->toBeNull();
    expect($reopened->reviewed_at)->toBeNull();
});

test('reopenDisputed is a no-op on a record that is not disputed', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $pending = VerificationRecord::factory()->for($organization, 'organization')->create();

    $result = app(ReopenDisputedAction::class)->execute($pending->id, $admin);

    expect($result->review_status)->toBe(VerificationReviewStatus::ShadowPendingReview);
});
