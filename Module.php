<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager;
use Yii;
use yii\helpers\Url;
class Module extends \humhub\components\Module
{
    public function getConfigUrl() { return Url::to(['/github-module-manager/admin/index']); }
    public function disable()
    {
        // Preserve provenance and audit history when disabled.
        Yii::$app->moduleManager->disable($this);
        return true;
    }
}
