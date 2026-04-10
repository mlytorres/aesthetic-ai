<?php

use App\Models\Evaluation;
use App\Services\ClinicalBriefService;
use App\Services\SecureFileService;

test('filename returns kebab-case pdf name with short id', function () {
    $evaluation = new Evaluation([
        'procedure_slug' => 'rhinoplasty',
    ]);
    $evaluation->id = '12345678-abcd-efgh-ijkl-000000000000';

    $service = new ClinicalBriefService($this->createMock(SecureFileService::class));
    $filename = $service->filename($evaluation);

    expect($filename)->toBe('clinical-brief-rhinoplasty-12345678.pdf');
});

test('filename uses full procedure slug for multi-word procedures', function () {
    $evaluation = new Evaluation([
        'procedure_slug' => 'breast-augmentation',
    ]);
    $evaluation->id = 'aabbccdd-0000-0000-0000-000000000000';

    $service = new ClinicalBriefService($this->createMock(SecureFileService::class));
    $filename = $service->filename($evaluation);

    expect($filename)->toBe('clinical-brief-breast-augmentation-aabbccdd.pdf');
});
