<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\services;

/** Filesystem transaction. The callback migrates and persists the successful result. */
class ModuleInstaller
{
    public function __construct(private string $runtime, private int $retention = 3) {}
    protected function move(string $from, string $to): void
    {
        if (!rename($from, $to)) throw new Failure('Module directory could not be exchanged.');
    }
    public function install(string $id, string $staged, string $target, ?string $expectedHash, callable $afterSwap, ?callable $beforeSwap = null): array
    {
        $lock = new Lock($this->runtime, $id);
        Files::noLinks($staged); Files::noLinks($target);
        Files::directory($this->runtime . '/operations');
        $journal = $this->runtime . '/operations/' . $id . '.json';
        if (file_exists($journal)) throw new Failure('A previous operation needs manual recovery. See the operation journal.');
        $exists = is_dir($target);
        if ($exists !== ($expectedHash !== null) || ($exists && !hash_equals($expectedHash, Files::hash($target)))) throw new Failure('Local files changed after confirmation. Inspect the repository again.');
        if (!is_writable(dirname($target)) || !is_writable($this->runtime) || stat(dirname($target))['dev'] !== stat($staged)['dev'] || stat(dirname($target))['dev'] !== stat($this->runtime)['dev']) throw new Failure('Directories are not writable or not on the same filesystem.');
        if ($beforeSwap) $beforeSwap();
        $backupRoot = $this->runtime . '/backups/' . $id;
        Files::directory($backupRoot);
        $backup = $backupRoot . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6));
        $state = ['module'=>$id,'target'=>$target,'backup'=>$exists ? $backup : null,'staged'=>$staged,'phase'=>'prepared','databaseRollback'=>false];
        Files::writeJson($journal, $state);
        $oldMoved = false; $newMoved = false;
        try {
            if ($exists) { $this->move($target, $backup); $oldMoved = true; }
            $state['phase'] = 'backup'; Files::writeJson($journal, $state);
            $this->move($staged, $target); $newMoved = true;
            $state['phase'] = 'migration'; Files::writeJson($journal, $state);
            $afterSwap($target);
        } catch (\Throwable $e) {
            try {
                if ($newMoved) $this->move($target, $staged);
                if ($oldMoved) $this->move($backup, $target);
                $state['filesRestored'] = true;
            } catch (\Throwable $rollbackError) { $state['filesRestored'] = false; }
            $state['failedPhase'] = $state['phase']; $state['phase'] = 'failed';
            Files::writeJson($journal, $state);
            // A migration can have changed schema without a transactional rollback.
            if ($state['failedPhase'] !== 'migration' && $state['filesRestored']) unlink($journal);
            throw new Failure($state['filesRestored']
                ? 'Installation failed. Previous files were restored; database changes may require manual recovery.'
                : 'Installation failed and file recovery failed. Stop and restore from the operation journal.');
        }
        $state['phase'] = 'complete'; Files::writeJson($journal, $state);
        if (!unlink($journal)) throw new Failure('Operation journal could not be finalized.');
        $warnings = [];
        $backups = glob($backupRoot . '/*', GLOB_ONLYDIR) ?: []; rsort($backups, SORT_STRING);
        foreach (array_slice($backups, max(1, $this->retention)) as $old) {
            try { Files::remove($old); } catch (\Throwable $e) { $warnings[] = 'Old backups could not be removed.'; }
        }
        return $warnings;
    }
}
