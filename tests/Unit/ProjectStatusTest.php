<?php

use App\Enums\ProjectStatus;

it('mengikuti alur utama tanpa lompat', function () {
    $flow = [
        ProjectStatus::Lead, ProjectStatus::Quoted, ProjectStatus::Deal, ProjectStatus::AwaitingDp,
        ProjectStatus::Active, ProjectStatus::AwaitingFinal, ProjectStatus::Handover,
        ProjectStatus::Warranty, ProjectStatus::Closed,
    ];

    foreach ($flow as $i => $status) {
        if (isset($flow[$i + 1])) {
            expect($status->canTransitionTo($flow[$i + 1]))->toBeTrue();
        }
    }
});

it('mengizinkan negosiasi sebagai jalur alternatif', function () {
    expect(ProjectStatus::Quoted->canTransitionTo(ProjectStatus::Negotiating))->toBeTrue()
        ->and(ProjectStatus::Negotiating->canTransitionTo(ProjectStatus::Deal))->toBeTrue();
});

it('menolak lompatan status', function () {
    expect(ProjectStatus::Lead->canTransitionTo(ProjectStatus::Active))->toBeFalse()
        ->and(ProjectStatus::Deal->canTransitionTo(ProjectStatus::Active))->toBeFalse()
        ->and(ProjectStatus::Active->canTransitionTo(ProjectStatus::Lead))->toBeFalse();

    ProjectStatus::Lead->transitionTo(ProjectStatus::Handover);
})->throws(InvalidArgumentException::class);

it('bisa dibatalkan dari tahap mana pun kecuali status akhir', function () {
    foreach (ProjectStatus::cases() as $status) {
        $expected = ! in_array($status, [ProjectStatus::Closed, ProjectStatus::Cancelled], true);
        expect($status->canTransitionTo(ProjectStatus::Cancelled))->toBe($expected);
    }
});

it('status akhir tidak punya transisi', function () {
    expect(ProjectStatus::Closed->allowedTransitions())->toBe([])
        ->and(ProjectStatus::Cancelled->allowedTransitions())->toBe([]);
});
