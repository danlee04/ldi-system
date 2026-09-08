<?php

test('the hris connection is configured and read only by convention', function () {
    $config = config('database.connections.hris');

    expect($config)->not->toBeNull()
        ->and($config['driver'])->toBe('mysql')
        ->and($config['database'])->toBe(env('HRIS_DB_DATABASE', 'hris_db'));
});
