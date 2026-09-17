<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\services;

class Files
{
    public static function isWithinDirectories(string $path, array $directories): bool
    {
        $path = self::normalizePath($path);
        foreach ($directories as $directory) {
            if (!is_string($directory) || $directory === '') continue;
            $directory = self::normalizePath($directory);
            $directory = rtrim($directory, '/\\');
            if ($directory === '') {
                if (str_starts_with($path, DIRECTORY_SEPARATOR)) return true;
                continue;
            }
            if ($path === $directory || str_starts_with($path, $directory . DIRECTORY_SEPARATOR)) return true;
        }
        return false;
    }
    private static function normalizePath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $absolute = str_starts_with($path, DIRECTORY_SEPARATOR);
        $parts = [];
        foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') {
                if ($parts && end($parts) !== '..') array_pop($parts);
                elseif (!$absolute) $parts[] = $part;
                continue;
            }
            $parts[] = $part;
        }
        return ($absolute ? DIRECTORY_SEPARATOR : '') . implode(DIRECTORY_SEPARATOR, $parts);
    }
    public static function directory(string $path): void
    {
        if (is_link($path) || (!is_dir($path) && !mkdir($path, 0700, true))) throw new Failure('Directory cannot be created.');
    }
    public static function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            if (!unlink($path)) throw new Failure('Temporary files could not be removed.');
        } elseif (is_dir($path)) {
            foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $entry) self::remove($entry->getPathname());
            if (!rmdir($path)) throw new Failure('Temporary files could not be removed.');
        }
    }
    public static function noLinks(string $path): void
    {
        $cursor = $path;
        while ($cursor !== dirname($cursor)) {
            if (is_link($cursor)) throw new Failure('Symbolic links are not supported.');
            $cursor = dirname($cursor);
        }
    }
    public static function hash(string $path): string
    {
        self::noLinks($path);
        $rows = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $entry) {
            if ($entry->isLink() || !$entry->isFile()) throw new Failure('Symbolic links are not supported.');
            $relative = substr($entry->getPathname(), strlen($path) + 1);
            // .git is not installed from GitHub archives; do not fingerprint its changing internals.
            if (str_starts_with(str_replace('\\', '/', $relative), '.git/')) continue;
            $hash = hash_file('sha256', $entry->getPathname());
            if ($hash === false) throw new Failure('Module files could not be read.');
            $rows[$relative] = $hash;
        }
        ksort($rows, SORT_STRING);
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }
    public static function writeJson(string $path, array $data): void
    {
        $tmp = $path . '.' . bin2hex(random_bytes(8));
        if (file_put_contents($tmp, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX) === false || !rename($tmp, $path)) throw new Failure('Operation state could not be saved.');
    }
}
