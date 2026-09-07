<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager;
use Yii;
use yii\helpers\Url;
class Events
{
    public static function onAdminMenu($event): void
    {
        if (Yii::$app->user->isGuest || !Yii::$app->user->identity->isSystemAdmin()) return;
        $event->sender->addItem([
            'label'=>Yii::t('GithubModuleManagerModule.base','GitHub Module Manager'),
            'url'=>Url::to(['/github-module-manager/admin/index']), 'icon'=>'github',
            'isActive'=>Yii::$app->controller && Yii::$app->controller->module->id === 'github-module-manager',
            'sortOrder'=>650,
        ]);
    }
}
