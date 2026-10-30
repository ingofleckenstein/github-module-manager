<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\services;

class Failure extends \RuntimeException
{
    public function __construct(public string $key, public array $parameters = [], ?\Throwable $previous = null)
    {
        parent::__construct($key, 0, $previous);
    }

    /** Only known, static messages may leave an exception raised by module PHP. */
    public static function diagnostic(\Throwable $error): array
    {
        $messages = require dirname(__DIR__) . '/messages/de/base.php';
        $cause = $error;
        $reason = 'Unexpected operation error. Review the operation journal and server log.';
        do {
            if ($cause instanceof self && isset($messages[$cause->key])) $reason = $cause->key;
            if ($cause->getPrevious() === null) break;
            $cause = $cause->getPrevious();
        } while (true);
        return [
            'type' => get_class($cause),
            'source' => basename($cause->getFile()),
            'line' => $cause->getLine(),
            'reason' => $reason,
        ];
    }
}
