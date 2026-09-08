<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\services;
use humhub\components\Module;
use humhub\services\MigrationService;
use Yii;
use yii\db\Query;

class HumHubBridge
{
    public function requirements(string $path, string $id): array
    {
        $this->assertApplicationSchemaIsCurrent();
        // Deliberate execution only inside the confirmed installation action.
        $config = require $path . '/config.php';
        if (!is_array($config) || ($config['id'] ?? null) !== $id || empty($config['class']) || !empty($config['isCoreModule']) || !empty($config['isInstallerModule'])) throw new Failure('Module ID and config.php do not match.');
        if (is_file($path . '/requirements.php')) {
            $result = require $path . '/requirements.php';
            if (is_string($result) || $result === false) throw new Failure('Module requirements were not satisfied. Install dependencies manually.');
        }
        return $config;
    }

    private function assertApplicationSchemaIsCurrent(): void
    {
        // Module migrations run in the same database as HumHub. Do not let a
        // module update race or mask an outstanding core migration.
        $path = Yii::getAlias('@humhub/migrations');
        $files = is_dir($path) ? glob($path . '/m*.php') : false;
        if ($files === false) {
            throw new Failure('Application database migration status could not be checked. Update HumHub database before installing modules.');
        }
        $versions = array_map(static fn(string $file): string => basename($file, '.php'), $files);
        if (!$versions) {
            return;
        }
        try {
            $applied = (new Query())
                ->select('version')
                ->from('{{%migration}}')
                ->where(['version' => $versions])
                ->column();
        } catch (\Throwable $exception) {
            Yii::warning(['phase' => 'core-migration-preflight', 'result' => 'unavailable'], 'github-module-manager');
            throw new Failure('Application database migration status could not be checked. Update HumHub database before installing modules.');
        }
        if (array_diff($versions, $applied)) {
            throw new Failure('Application database migrations are pending. Update HumHub database before installing modules.');
        }
    }
    public function migrate(string $id, string $path, array $config): void
    {
        if (($config['id'] ?? null) !== $id || empty($config['class'])) {
            throw new Failure('Module ID and config.php do not match.');
        }

        /*
         * An existing module was already registered when this request
         * bootstrapped. Registering its replacement config again adds event
         * callbacks to the same request a second time. This is unsafe for
         * namespace changes because Yii's autoloader includes class files
         * directly. A newly installed module still needs registration so it
         * becomes discoverable immediately after its initial migration.
         */
        $alreadyRegistered = Yii::$app->moduleManager->hasModule($id);
        if (!empty($config['namespace']) && is_string($config['namespace'])) {
            Yii::setAlias('@' . str_replace('\\', '/', $config['namespace']), $path);
        }
        Yii::setAlias('@' . $id, $path);

        if (!$alreadyRegistered) {
            Yii::$app->moduleManager->register($path, $config);
        }

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
