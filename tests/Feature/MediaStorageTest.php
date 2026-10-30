<?php

use Illuminate\Support\Facades\Storage;

test('uploads are stored in their own folder inside the public storage link', function () {
    expect(config('media-library.disk_name'))->toBe('media')
        ->and(Storage::disk('media')->path('1/photo.jpg'))->toBe(storage_path('app/public/media/1/photo.jpg'))
        ->and(Storage::disk('media')->url('1/photo.jpg'))->toBe(rtrim(config('app.url'), '/').'/storage/media/1/photo.jpg');
});
