<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\models;
class Repository extends \yii\db\ActiveRecord
{
    public static function tableName(): string { return '{{%github_module_repository}}'; }
    public function rules(): array
    {
        return [
            [['module_id','repository_url','repository_owner','repository_name','channel_value'], 'required'],
            [['module_id'], 'unique'],
            [['installed_version','installed_commit_sha','last_remote_version','last_remote_commit_sha','last_checked_at','last_updated_at','status','file_hash','module_path','provider','channel_type'], 'safe'],
        ];
    }
    public function source(): array { return ['owner'=>$this->repository_owner,'name'=>$this->repository_name,'url'=>$this->repository_url]; }
}
