<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\services;
use ZipArchive;

class ArchiveValidator
{
    public function __construct(private int $maxDownload = 52428800, private int $maxUnpacked = 209715200, private int $maxFiles = 10000) {}
    public function extract(string $archive, string $destination): string
    {
        if (!is_file($archive) || filesize($archive) > $this->maxDownload) throw new Failure('Download exceeds the configured size limit.');
        if (file_exists($destination) || is_link($destination)) throw new Failure('Staging directory already exists.');
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CHECKCONS) !== true) throw new Failure('Invalid ZIP archive.');
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > $this->maxFiles) throw new Failure('Archive contains too many files.');
            $entries = []; $seen = []; $total = 0; $root = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!$stat) throw new Failure('Invalid ZIP archive.');
                $name = $stat['name'];
                if ($name === '' || strlen($name) > 512 || preg_match('~[\x00-\x1f\x7f\\\\:]~', $name) || str_starts_with($name, '/')) throw new Failure('Unsafe archive path.');
                $parts = explode('/', rtrim($name, '/'));
                foreach ($parts as $part) {
                    if ($part === '' || $part === '.' || $part === '..' || preg_match('/[. ]$/', $part)
                        || preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i', $part)) throw new Failure('Unsafe archive path.');
                }
                $root ??= $parts[0];
                if ($parts[0] !== $root) throw new Failure('Archive must contain one repository wrapper directory.');
                $key = strtolower(rtrim($name, '/'));
                if (isset($seen[$key])) throw new Failure('Duplicate archive path.');
                $seen[$key] = true;
                $zip->getExternalAttributesIndex($i, $os, $attr);
                $type = ($attr >> 16) & 0170000;
                if ($type !== 0 && $type !== 0100000 && $type !== 0040000) throw new Failure('Symbolic links and special files are not supported.');
                if (($stat['encryption_method'] ?? 0) !== 0) throw new Failure('Encrypted archives are not supported.');
                $total += $stat['size'];
                if ($total > $this->maxUnpacked) throw new Failure('Unpacked archive exceeds the configured size limit.');
                $entries[] = $stat;
            }
            // Validation above completes before any archive file is written.
            Files::directory($destination);
            $written = 0;
            foreach ($entries as $stat) {
                $target = $destination . '/' . $stat['name'];
                if (str_ends_with($stat['name'], '/')) { Files::directory($target); continue; }
                Files::directory(dirname($target));
                $input = $zip->getStream($stat['name']); $output = fopen($target, 'xb');
                if (!$input || !$output) throw new Failure('Archive could not be extracted.');
                $size = 0;
                try {
                    while (!feof($input)) {
                        $chunk = fread($input, 65536);
                        if ($chunk === false) throw new Failure('Archive could not be extracted.');
                        $size += strlen($chunk); $written += strlen($chunk);
                        if ($size > $stat['size'] || $written > $this->maxUnpacked || fwrite($output, $chunk) !== strlen($chunk)) throw new Failure('Archive size or write error.');
                    }
                } finally { fclose($input); fclose($output); }
                if ($size !== $stat['size']) throw new Failure('Archive size or write error.');
                chmod($target, 0644);
            }
            if (!is_dir($destination . '/' . $root)) throw new Failure('Archive must contain one repository wrapper directory.');
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($destination, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $entry) {
                if ($entry->isDir()) chmod($entry->getPathname(), 0755);
            }
            return $destination . '/' . $root;
        } catch (\Throwable $e) {
            if (is_dir($destination)) Files::remove($destination);
            throw $e;
        } finally { $zip->close(); }
    }
}
