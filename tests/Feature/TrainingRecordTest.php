<?php

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalLevel;
use App\Enums\LdType;
use App\Models\TrainingRecord;

test('a record keeps the PDS fields and the costs', function () {
    $record = TrainingRecord::factory()->create([
        'title' => 'Records Management Seminar',
        'date_start' => '2026-03-02',
        'date_end' => '2026-03-04',
        'hours' => 24,
        'ld_type' => LdType::Technical,
        'conducted_by' => 'Civil Service Commission',
        'registration_fee' => 1500.00,
        'tev' => 2350.50,
    ]);

    expect($record->ld_type)->toBe(LdType::Technical)
        ->and($record->hours)->toBe(24)
        ->and((float) $record->registration_fee)->toBe(1500.00)
        ->and((float) $record->tev)->toBe(2350.50);
});

test('the label of a standard type is the PDS name', function () {
    $record = TrainingRecord::factory()->create(['ld_type' => LdType::Technical, 'ld_type_other' => null]);

    expect($record->ld_type_label)->toBe('Technical');
});

test('the label of an other type is its own text', function () {
    $record = TrainingRecord::factory()->create([
        'ld_type' => LdType::Other,
        'ld_type_other' => 'Convention',
    ]);

    expect($record->ld_type_label)->toBe('Convention');
});

test('scopes separate pending, approved and awaiting a level', function () {
    TrainingRecord::factory()->create();
    TrainingRecord::factory()->awaitingDivisionHead()->create();
    TrainingRecord::factory()->approved()->create();
    TrainingRecord::factory()->rejected()->create();

    expect(TrainingRecord::pending()->count())->toBe(2)
        ->and(TrainingRecord::approved()->count())->toBe(1)
        ->and(TrainingRecord::awaiting(ApprovalLevel::DivisionHead)->count())->toBe(1)
        ->and(TrainingRecord::awaiting(ApprovalLevel::SectionHead)->count())->toBe(1);
});

test('a pending record with no level is unroutable', function () {
    TrainingRecord::factory()->create();
    TrainingRecord::factory()->create(['current_level' => null]);
    TrainingRecord::factory()->approved()->create();

    expect(TrainingRecord::unroutable()->count())->toBe(1);
});

test('an approval belongs to its record', function () {
    $record = TrainingRecord::factory()->create();
    $approval = $record->approvals()->create([
        'level' => ApprovalLevel::SectionHead,
        'approver_user_id' => $record->submitted_by,
        'decision' => ApprovalDecision::Approved,
        'remarks' => null,
        'decided_at' => now(),
    ]);

    expect($record->approvals)->toHaveCount(1)
        ->and($approval->level)->toBe(ApprovalLevel::SectionHead);
});
