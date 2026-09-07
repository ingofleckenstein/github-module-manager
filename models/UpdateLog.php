<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\models;
class UpdateLog extends \yii\db\ActiveRecord
{
    public static function tableName(): string { return '{{%github_module_log}}'; }
}
