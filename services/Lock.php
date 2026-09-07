<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\services;
class Lock
{
    private $handle;
    public function __construct(string $runtime, string $id)
    {
        Files::directory($runtime . '/locks');
        $this->handle = fopen($runtime . '/locks/' . hash('sha256', $id) . '.lock', 'c');
        if (!$this->handle || !flock($this->handle, LOCK_EX | LOCK_NB)) throw new Failure('This module is currently being updated.');
    }
    public function __destruct()
    {
        if (is_resource($this->handle)) { flock($this->handle, LOCK_UN); fclose($this->handle); }
    }
}
