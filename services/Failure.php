<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\services;

class Failure extends \RuntimeException
{
    public function __construct(public string $key, public array $parameters = [])
    {
        parent::__construct($key);
    }
}
