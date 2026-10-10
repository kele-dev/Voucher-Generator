<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Queue stall detection
    |--------------------------------------------------------------------------
    |
    | Number of seconds without the queue heartbeat before the dashboard warns
    | that the worker is not running.
    |
    */

    'queue_stall_seconds' => (int) env('VOUCHER_QUEUE_STALL_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | Voucher code patterns
    |--------------------------------------------------------------------------
    |
    | Tokens: {PREFIX} brand prefix, {DDMM} day+month, {DATE:Ymd} arbitrary
    | PHP date format, {SEQ:n} zero-padded sequence, {RAND:n} random from the
    | charset, {ALPHA:n} random uppercase letters. A pattern must contain one
    | {SEQ:n} or at least one {RAND:n}/{ALPHA:n}.
    |
    */

    'codes' => [
        'charset' => 'ABCDEFGHJKMNPQRSTUVWXYZ23456789',
        'alpha_charset' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'max_length' => 40,

        'presets' => [
            'example_random' => [
                'label' => 'Mirip contoh + acak',
                'pattern' => '{PREFIX}{DDMM}{SEQ:4}{RAND:3}',
            ],
            'example_exact' => [
                'label' => 'Persis contoh (mudah ditebak)',
                'pattern' => '{PREFIX}{DDMM}{SEQ:3}',
            ],
            'random' => [
                'label' => 'Random murni',
                'pattern' => '{PREFIX}-{RAND:8}',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Template uploads
    |--------------------------------------------------------------------------
    */

    'templates' => [
        'disk' => env('VOUCHER_TEMPLATE_DISK', 'local'),
        'max_upload_kb' => (int) env('VOUCHER_TEMPLATE_MAX_KB', 5120),
        'overlay_max_width' => (int) env('VOUCHER_OVERLAY_MAX_WIDTH', 1200),
    ],

    /*
    |--------------------------------------------------------------------------
    | ZIP exports
    |--------------------------------------------------------------------------
    */

    'exports' => [
        'formats' => ['jpg', 'png'],
        'scales' => [100, 50],
    ],

    /*
    |--------------------------------------------------------------------------
    | Generation batches
    |--------------------------------------------------------------------------
    */

    'batches' => [
        'max_count' => (int) env('VOUCHER_BATCH_MAX_COUNT', 2000),
        'quantities' => [1, 5, 10, 25, 50, 100, 250, 500, 1000, 2000],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backups
    |--------------------------------------------------------------------------
    */

    'backups' => [
        'path' => env('VOUCHER_BACKUP_PATH', storage_path('backups')),
        'keep_daily' => (int) env('VOUCHER_BACKUP_KEEP_DAILY', 7),
        'keep_weekly' => (int) env('VOUCHER_BACKUP_KEEP_WEEKLY', 4),
    ],

    'export_retention_days' => (int) env('VOUCHER_EXPORT_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Health endpoint token
    |--------------------------------------------------------------------------
    |
    | Only sent via the X-Health-Token header. Without it /health answers a
    | bare status so infrastructure monitors cannot read internals.
    |
    */

    'health_token' => env('HEALTH_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Template fields
    |--------------------------------------------------------------------------
    |
    | Every slot the editor can place. "type" tells the renderer how to fill
    | it and the UI which controls to show.
    |
    */

    'fields' => [
        'product_image' => ['label' => 'Foto produk', 'type' => 'image', 'group' => 'main'],
        'voucher_code' => ['label' => 'Kode voucher', 'type' => 'text', 'group' => 'main'],
        'benefit_title' => ['label' => 'Judul benefit', 'type' => 'text', 'group' => 'main'],
        'benefit_description' => ['label' => 'Deskripsi benefit', 'type' => 'text', 'group' => 'main'],
        'expires_at' => ['label' => 'Berlaku hingga', 'type' => 'date', 'group' => 'main'],
        'menu_items' => ['label' => 'Daftar menu', 'type' => 'text', 'group' => 'content'],
        'valid_schedule' => ['label' => 'Jadwal berlaku', 'type' => 'text', 'group' => 'content'],
        'outlet_scope' => ['label' => 'Cakupan outlet', 'type' => 'text', 'group' => 'content'],
        'campaign_title' => ['label' => 'Nama campaign', 'type' => 'text', 'group' => 'extra'],
        'min_purchase' => ['label' => 'Minimal belanja', 'type' => 'text', 'group' => 'extra'],
        'normal_value' => ['label' => 'Nilai normal', 'type' => 'text', 'group' => 'extra'],
        'terms' => ['label' => 'Syarat & ketentuan', 'type' => 'text', 'group' => 'extra'],
    ],

    'field_groups' => [
        'main' => 'Utama',
        'content' => 'Isi voucher',
        'extra' => 'Tambahan',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fonts bundled with the renderer
    |--------------------------------------------------------------------------
    */

    'fonts' => [
        'poppins' => [
            'label' => 'Poppins',
            'regular' => resource_path('fonts/Poppins-Regular.ttf'),
            'semibold' => resource_path('fonts/Poppins-SemiBold.ttf'),
            'bold' => resource_path('fonts/Poppins-Bold.ttf'),
            'extrabold' => resource_path('fonts/Poppins-ExtraBold.ttf'),
            'weights' => ['regular', 'semibold', 'bold', 'extrabold'],
        ],
    ],

];
