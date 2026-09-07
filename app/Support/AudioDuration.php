<?php

namespace App\Support;

use App\Models\ArchiveRecording;

class AudioDuration
{
    public static function formatFromRecording(ArchiveRecording $recording): ?string
    {
        $candidates = [];
        $stored = trim((string) $recording->storage_path);
        if ($stored !== '') {
            $candidates[] = $stored;
            $candidates[] = storage_path('app/'.$stored);
            $candidates[] = storage_path('app/private/'.$stored);
            $candidates[] = public_path($stored);
        }
        $fileName = trim((string) $recording->file_name);
        if ($fileName !== '') {
            $candidates[] = storage_path('app/archive-recordings/'.$fileName);
            $candidates[] = storage_path('app/private/archive-recordings/'.$fileName);
        }

        foreach ($candidates as $path) {
            $formatted = self::formatFromFile($path);
            if ($formatted !== null) {
                return $formatted;
            }
        }

        return null;
    }

    public static function formatFromFile(?string $path): ?string
    {
        $seconds = self::secondsFromFile($path);
        if ($seconds === null || $seconds <= 0) {
            return null;
        }

        $total = (int) round($seconds);

        return sprintf('%02d:%02d:%02d', intdiv($total, 3600), intdiv($total % 3600, 60), $total % 60);
    }

    public static function secondsFromFile(?string $path): ?float
    {
        if ($path === null || $path === '' || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            $header = fread($handle, 12) ?: '';
            if (str_starts_with($header, 'RIFF') && str_ends_with($header, 'WAVE')) {
                return self::wavSeconds($handle);
            }
            if (str_starts_with($header, 'OggS')) {
                return self::oggSeconds($path);
            }
            if (str_starts_with($header, 'fLaC')) {
                return self::flacSeconds($handle);
            }
            rewind($handle);
            $probe = fread($handle, 12) ?: '';
            if (strlen($probe) >= 8 && substr($probe, 4, 4) === 'ftyp') {
                return self::mp4Seconds($path);
            }

            return self::mpegSeconds($path);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     */
    private static function wavSeconds($handle): ?float
    {
        $byteRate = 0;
        $dataSize = 0;
        while (! feof($handle)) {
            $chunk = fread($handle, 8);
            if ($chunk === false || strlen($chunk) < 8) {
                break;
            }
            $id = substr($chunk, 0, 4);
            $size = unpack('V', substr($chunk, 4, 4))[1] ?? 0;
            if ($id === 'fmt ') {
                $fmt = fread($handle, $size) ?: '';
                if (strlen($fmt) >= 12) {
                    $byteRate = unpack('V', substr($fmt, 8, 4))[1] ?? 0;
                }
                if ($size % 2 === 1) {
                    fread($handle, 1);
                }
                continue;
            }
            if ($id === 'data') {
                $dataSize = $size;
                break;
            }
            fseek($handle, $size + ($size % 2), SEEK_CUR);
        }

        if ($byteRate < 1 || $dataSize < 1) {
            return null;
        }

        return $dataSize / $byteRate;
    }

    /**
     * @param  resource  $handle
     */
    private static function flacSeconds($handle): ?float
    {
        $block = fread($handle, 38) ?: '';
        if (strlen($block) < 22) {
            return null;
        }
        $sampleRate = (unpack('N', "\x00".substr($block, 14, 3))[1] ?? 0) >> 4;
        $totalSamples = unpack('J', "\x00\x00".substr($block, 18, 6))[1] ?? 0;
        if ($sampleRate < 1 || $totalSamples < 1) {
            return null;
        }

        return $totalSamples / $sampleRate;
    }

    private static function oggSeconds(string $path): ?float
    {
        $size = filesize($path);
        if (! is_int($size) || $size < 27) {
            return null;
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        try {
            $sampleRate = 0;
            $head = fread($handle, 96) ?: '';
            if (preg_match('/\x01vorbis/s', $head, $match, PREG_OFFSET_CAPTURE) === 1) {
                $offset = $match[0][1] + 11;
                if (strlen($head) >= $offset + 4) {
                    $sampleRate = unpack('V', substr($head, $offset, 4))[1] ?? 0;
                }
            }
            $read = min(65536, $size);
            fseek($handle, $size - $read);
            $tail = fread($handle, $read) ?: '';
            $pos = strrpos($tail, 'OggS');
            if ($pos === false || $sampleRate < 1) {
                return null;
            }
            $granule = unpack('P', substr($tail, $pos + 6, 8))[1] ?? 0;
            if ($granule < 1) {
                return null;
            }

            return $granule / $sampleRate;
        } finally {
            fclose($handle);
        }
    }

    private static function mp4Seconds(string $path): ?float
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        try {
            $limit = filesize($path) ?: 0;
            $offset = 0;
            while ($offset + 8 <= $limit) {
                fseek($handle, $offset);
                $header = fread($handle, 8) ?: '';
                if (strlen($header) < 8) {
                    break;
                }
                $size = unpack('N', substr($header, 0, 4))[1] ?? 0;
                $type = substr($header, 4, 4);
                if ($size < 8) {
                    break;
                }
                if ($type === 'moov' || $type === 'trak' || $type === 'mdia') {
                    $offset += 8;
                    continue;
                }
                if ($type === 'mvhd') {
                    $version = ord(fread($handle, 1) ?: "\x00");
                    fread($handle, 3);
                    if ($version === 1) {
                        fread($handle, 16);
                        $timescale = unpack('N', fread($handle, 4) ?: "\x00\x00\x00\x00")[1] ?? 0;
                        $high = unpack('N', fread($handle, 4) ?: "\x00\x00\x00\x00")[1] ?? 0;
                        $low = unpack('N', fread($handle, 4) ?: "\x00\x00\x00\x00")[1] ?? 0;
                        $duration = ($high * 4294967296) + $low;
                    } else {
                        fread($handle, 8);
                        $timescale = unpack('N', fread($handle, 4) ?: "\x00\x00\x00\x00")[1] ?? 0;
                        $duration = unpack('N', fread($handle, 4) ?: "\x00\x00\x00\x00")[1] ?? 0;
                    }
                    if ($timescale > 0 && $duration > 0) {
                        return $duration / $timescale;
                    }

                    return null;
                }
                $offset += $size;
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    private static function mpegSeconds(string $path): ?float
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        try {
            $buffer = fread($handle, 8192) ?: '';
            $xing = strpos($buffer, 'Xing');
            if ($xing === false) {
                $xing = strpos($buffer, 'Info');
            }
            $frame = self::firstMpegFrame($buffer);
            if ($frame === null) {
                return null;
            }
            [$sampleRate, $samplesPerFrame, $bitrate] = $frame;
            if ($xing !== false && strlen($buffer) >= $xing + 12) {
                $flags = unpack('N', substr($buffer, $xing + 4, 4))[1] ?? 0;
                if (($flags & 1) === 1) {
                    $frames = unpack('N', substr($buffer, $xing + 8, 4))[1] ?? 0;
                    if ($frames > 0 && $sampleRate > 0) {
                        return ($frames * $samplesPerFrame) / $sampleRate;
                    }
                }
            }
            $size = filesize($path);
            if (! is_int($size) || $bitrate < 1) {
                return null;
            }

            return ($size * 8) / $bitrate;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private static function firstMpegFrame(string $buffer): ?array
    {
        $length = strlen($buffer);
        for ($i = 0; $i < $length - 3; $i++) {
            if (ord($buffer[$i]) !== 0xFF || (ord($buffer[$i + 1]) & 0xE0) !== 0xE0) {
                continue;
            }
            $b1 = ord($buffer[$i + 1]);
            $b2 = ord($buffer[$i + 2]);
            $versionBits = ($b1 >> 3) & 3;
            $layerBits = ($b1 >> 1) & 3;
            $bitrateIndex = ($b2 >> 4) & 15;
            $sampleIndex = ($b2 >> 2) & 3;
            if ($versionBits === 1 || $layerBits === 0 || $bitrateIndex === 0 || $bitrateIndex === 15 || $sampleIndex === 3) {
                continue;
            }
            $version = [2.5, 0, 2, 1][$versionBits];
            $layer = [0, 3, 2, 1][$layerBits];
            $sampleRates = [
                1 => [44100, 48000, 32000],
                2 => [22050, 24000, 16000],
                2.5 => [11025, 12000, 8000],
            ];
            $sampleRate = $sampleRates[$version][$sampleIndex] ?? 0;
            $bitrates = [
                1 => [
                    1 => [0, 32, 64, 96, 128, 160, 192, 224, 256, 288, 320, 352, 384, 416, 448],
                    2 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 384],
                    3 => [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320],
                ],
                2 => [
                    1 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224, 256],
                    2 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
                    3 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
                ],
            ];
            $bitrateTable = $bitrates[$version === 1 ? 1 : 2][$layer] ?? [];
            $bitrate = ($bitrateTable[$bitrateIndex] ?? 0) * 1000;
            $samplesPerFrame = $layer === 1 ? 384 : (($layer === 3 && $version !== 1) ? 576 : 1152);
            if ($sampleRate > 0 && $bitrate > 0) {
                return [$sampleRate, $samplesPerFrame, $bitrate];
            }
        }

        return null;
    }
}
