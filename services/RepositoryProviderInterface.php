<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\services;
interface RepositoryProviderInterface
{
    public function repository(array $repository, bool $refresh = false): array;
    public function branches(array $repository, bool $refresh = false): array;
    public function commit(array $repository, string $branch, bool $refresh = false): string;
    public function download(array $repository, string $sha, string $destination): void;
}
