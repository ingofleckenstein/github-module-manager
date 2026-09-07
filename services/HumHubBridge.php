<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\services;
use humhub\components\Module;
use humhub\services\MigrationService;
use Yii;

class HumHubBridge
{
    public function requirements(string $path, string $id): array
    {
        // Deliberate execution only inside the confirmed installation action.
        $config = require $path . '/config.php';
        if (!is_array($config) || ($config['id'] ?? null) !== $id || empty($config['class']) || !empty($config['isCoreModule']) || !empty($config['isInstallerModule'])) throw new Failure('Module ID and config.php do not match.');
        if (is_file($path . '/requirements.php')) {
            $result = require $path . '/requirements.php';
            if (is_string($result) || $result === false) throw new Failure('Module requirements were not satisfied. Install dependencies manually.');
        }
        return $config;
    }
    public function migrate(string $id, string $path, array $config): void
    {
        Yii::$app->moduleManager->register($path, $config);
        Yii::setAlias('@' . $id, $path);
        // Existing Module classes may already be loaded in this PHP request.
        // MigrationService resolves fresh migration files via the module alias.
        $module = new Module($id, Yii::$app, ['basePath'=>$path]);
        $service = new MigrationService($module);
        if ($service->hasMigrations() && $service->migrateUp() !== true) throw new Failure('Module migrations failed.');
    }
    public function clear(): void
    {
        Yii::$app->moduleManager->flushCache();
        if (Yii::$app->cache->flush() === false) throw new Failure('Application cache could not be cleared.');
        Yii::$app->assetManager->clear();
        if (function_exists('opcache_reset') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN) && PHP_SAPI !== 'cli') {
            if (!opcache_reset()) throw new Failure('OPCache could not be reset.');
        }
    }
}
