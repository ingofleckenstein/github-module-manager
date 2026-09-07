<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\services;

final class RepositoryUrl
{
    public static function parse(string $url): array
    {
        $url = trim($url);
        if (strlen($url) > 250 || !preg_match('~^https://github\.com/([A-Za-z0-9](?:[A-Za-z0-9-]{0,38}))/([A-Za-z0-9_.-]{1,100})/?$~D', $url, $m)) {
            throw new Failure('Enter a public HTTPS GitHub repository URL without query or fragment.');
        }
        $name = preg_replace('/\.git$/', '', $m[2]);
        if ($name === '' || $name === '.' || $name === '..' || str_contains($name, '..')) {
            throw new Failure('Invalid repository name.');
        }
        return ['owner' => $m[1], 'name' => $name, 'url' => 'https://github.com/' . $m[1] . '/' . $name];
    }

    public static function branch(string $branch): string
    {
        if ($branch === '' || strlen($branch) > 200 || preg_match('~[\x00-\x20\x7f\\\\\~^:?*\[\]]~', $branch)
            || str_contains($branch, '..') || str_contains($branch, '@{') || str_contains($branch, '//')
            || str_starts_with($branch, '/') || str_ends_with($branch, '/') || str_ends_with($branch, '.')
            || preg_match('~(^|/)\.|\.lock($|/)~', $branch)) {
            throw new Failure('Invalid branch name.');
        }
        return $branch;
    }
}
