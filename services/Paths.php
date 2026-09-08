<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\services;
use Yii;

class Paths
{
    public function runtime(): string
    {
        $path = Yii::getAlias('@runtime/github-module-manager');
        Files::noLinks($path); Files::directory($path);
        if (!is_writable($path)) throw new Failure('Runtime directory is not writable.');
        return $path;
    }
    public function roots(): array
    {
        $core = realpath(Yii::getAlias('@humhub/modules'));
        $roots = [];
        foreach (Yii::$app->params['moduleAutoloadPaths'] ?? [] as $alias) {
            $path = Yii::getAlias($alias); Files::noLinks($path);
            $real = realpath($path);
            if ($real === false || $real === $core || ($core && str_starts_with($real, $core . DIRECTORY_SEPARATOR))) continue;
            $roots[] = $real;
        }
        return array_values(array_unique($roots));
    }
    public function managerTarget(): string
    {
        $module = Yii::$app->getModule('github-module-manager');
        $path = $module ? $module->getBasePath() : null;
        if (!is_string($path) || !is_dir($path)) throw new Failure('GitHub Module Manager path could not be determined.');
        $path = realpath($path);
        if ($path === false) throw new Failure('GitHub Module Manager path could not be determined.');
        Files::noLinks($path);
        $this->assertWritableAtomicTarget($path);
        return $path;
    }
    public function target(string $id, ?string $expected = null, bool $allowManager = false): string
    {
        ModuleValidator::id($id, $allowManager);
        if ($id === 'github-module-manager') {
            if (!$allowManager) throw new Failure('Invalid or protected module ID.');
            $target = $this->managerTarget();
            if ($expected !== null && $target !== $expected) throw new Failure('Module path changed. Inspect the repository again.');
            return $target;
        }
        $core = Yii::getAlias('@humhub/modules') . '/' . $id;
        if (file_exists($core) || is_link($core)) throw new Failure('Core modules cannot be overwritten.');
        $candidates = [];
        foreach ($this->roots() as $root) {
            $path = $root . '/' . $id;
            if (file_exists($path) || is_link($path)) $candidates[] = $path;
        }
        if (count($candidates) > 1) throw new Failure('The module exists in multiple autoload paths.');
        $target = $candidates[0] ?? null;
        if ($target === null) {
            foreach ($this->roots() as $root) if (is_writable($root)) { $target = $root . '/' . $id; break; }
        }
        if ($target === null) throw new Failure('No writable custom module path was found.');
        $alias = Yii::getAlias('@' . $id, false);
        if ($alias !== false && realpath($alias) !== realpath($target) && $alias !== $target) {
            throw new Failure('Module ID conflicts with an existing module or application alias.');
        }
        Files::noLinks($target);
        if ($expected !== null && $target !== $expected) throw new Failure('Module path changed. Inspect the repository again.');
        $this->assertWritableAtomicTarget($target);
        return $target;
    }
    private function assertWritableAtomicTarget(string $target): void
    {
        if (!is_writable(dirname($target))) throw new Failure('Custom module directory is not writable.');
        if (stat(dirname($target))['dev'] !== stat($this->runtime())['dev']) throw new Failure('Runtime and custom modules must be on the same filesystem for atomic updates.');
    }
}
