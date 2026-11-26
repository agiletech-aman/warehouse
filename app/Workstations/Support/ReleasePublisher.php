<?php

namespace App\Workstations\Support;

use App\Workstations\Models\Release;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** Shared by the web upload and the `workstations:release` command. */
class ReleasePublisher
{
    /**
     * @throws InvalidArgumentException with a message safe to show to the admin
     */
    public static function publish(string $version, string $file, ?string $signature, string $type = 'binary',
                                   ?string $postUpdate = null, ?string $notes = null, bool $latest = false): Release
    {
        if (!preg_match('/^\d+\.\d+\.\d+(-[A-Za-z0-9.]+)?$/', $version)) {
            throw new InvalidArgumentException('Version 1.2.3 jaisa hona chahiye.');
        }
        if (Release::where('version', $version)->exists()) {
            throw new InvalidArgumentException("Version $version pehle se maujood hai.");
        }
        if (!in_array($type, AgentProtocol::RELEASE_TYPES, true)) {
            throw new InvalidArgumentException('Type "binary" ya "package" hona chahiye.');
        }
        if ($postUpdate !== null && $postUpdate !== '') {
            if ($type !== 'package') {
                throw new InvalidArgumentException('Post-update action sirf package release ke liye hai.');
            }
            if (!array_key_exists($postUpdate, (array)config('workstations.post_update_actions'))) {
                throw new InvalidArgumentException('Ye post-update action allowed list mein nahi hai.');
            }
        } else {
            $postUpdate = null;
        }
        if (!is_file($file)) {
            throw new InvalidArgumentException('File nahi mili.');
        }
        if ($type === 'package' && !self::looksLikeGzip($file)) {
            throw new InvalidArgumentException('Package release .tar.gz file honi chahiye.');
        }

        $pk = (string)config('workstations.public_key');
        if (!AgentProtocol::validPublicKey($pk)) {
            throw new InvalidArgumentException('WSM_PUBLIC_KEY set nahi hai (ya galat hai). Bina signing key ke release upload nahi ho sakta.');
        }
        $sha = hash_file('sha256', $file);
        $signature = trim((string)$signature);
        if (!AgentProtocol::verifySignature($pk, $version, $sha, $signature)) {
            throw new InvalidArgumentException('Signature match nahi hua. Isi file aur isi version ke liye dobara sign karein.');
        }

        $path = 'workstations/releases/' . $version . '-' . substr($sha, 0, 12) . ($type === 'package' ? '.tar.gz' : '');
        $in = fopen($file, 'rb');
        Storage::disk(config('workstations.disk'))->put($path, $in);
        if (is_resource($in)) {
            fclose($in);
        }

        $rel = Release::create(['version' => $version, 'file_path' => $path, 'sha256' => $sha, 'signature' => $signature,
            'size' => filesize($file), 'notes' => $notes ?: null, 'type' => $type, 'post_update' => $postUpdate]);
        if ($latest) {
            self::makeLatest($rel);
        }
        return $rel;
    }

    public static function makeLatest(Release $rel): void
    {
        if (!AgentProtocol::verifySignature((string)config('workstations.public_key'), $rel->version, $rel->sha256, $rel->signature)) {
            throw new InvalidArgumentException("v{$rel->version} ka signature current WSM_PUBLIC_KEY se match nahi karta — latest nahi bana sakte.");
        }
        Release::where('id', '!=', $rel->id)->update(['is_latest' => false]);
        $rel->update(['is_latest' => true, 'yanked' => false]);
    }

    public static function yank(Release $rel, bool $yank = true): void
    {
        $rel->update($yank ? ['yanked' => true, 'is_latest' => false] : ['yanked' => false]);
    }

    private static function looksLikeGzip(string $file): bool
    {
        $h = fopen($file, 'rb');
        $magic = fread($h, 2);
        fclose($h);
        return $magic === "\x1f\x8b";
    }
}
