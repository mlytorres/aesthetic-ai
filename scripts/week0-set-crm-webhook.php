<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$t = App\Models\Tenant::query()->first();
if (! $t) {
    fwrite(STDERR, "tenant missing\n");
    exit(1);
}

$t->webhook_url = 'https://miamilife-crm.test/api/webhooks/symetrihealth';
$t->save();

file_put_contents(sys_get_temp_dir().'/week0_symetri.json', json_encode([
    'clinic_id' => (string) $t->id,
    'secret' => (string) $t->webhook_secret,
]));

echo "OK\n";
