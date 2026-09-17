<?php

namespace Tests\Unit;

use App\Models\ArchiveRecording;
use App\Support\Archive\ArchiveStorage;
use Tests\TestCase;

class ArchiveStorageTest extends TestCase
{
    public function test_contained_file_rejects_paths_outside_the_archive_roots(): void
    {
        $outside = storage_path('app/outside-archive.txt');
        file_put_contents($outside, 'secret');

        $this->assertNull(ArchiveStorage::containedFile($outside, ArchiveStorage::roots()));

        $dir = storage_path('app/archive-recordings');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $inside = $dir.'/safe.txt';
        file_put_contents($inside, 'ok');
        $this->assertSame(realpath($inside), ArchiveStorage::containedFile($inside, ArchiveStorage::roots()));

        @unlink($outside);
        @unlink($inside);
    }

    public function test_resolve_recording_ignores_traversal_and_absolute_escape_paths(): void
    {
        $recording = new ArchiveRecording([
            'storage_path' => '../.env',
            'file_name' => '..\\..\\windows\\win.ini',
        ]);

        $this->assertNull(ArchiveStorage::resolveRecording($recording));
    }
}
