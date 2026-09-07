<?php
// SPDX-License-Identifier: AGPL-3.0-only
use yii\db\Migration;
class m260907_100000_initial extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%github_module_repository}}', [
            'id'=>$this->primaryKey(), 'module_id'=>$this->string(100)->notNull(),
            'provider'=>$this->string(20)->notNull()->defaultValue('github'),
            'repository_owner'=>$this->string(100)->notNull(), 'repository_name'=>$this->string(100)->notNull(),
            'repository_url'=>$this->string(255)->notNull(),
            'channel_type'=>$this->string(20)->notNull()->defaultValue('branch'), 'channel_value'=>$this->string(200)->notNull(),
            'installed_version'=>$this->string(255), 'installed_commit_sha'=>$this->string(40),
            'last_remote_version'=>$this->string(255), 'last_remote_commit_sha'=>$this->string(40),
            'last_checked_at'=>$this->integer(), 'last_updated_at'=>$this->integer(),
            'status'=>$this->string(30)->notNull()->defaultValue('unknown'), 'file_hash'=>$this->string(64),
            'module_path'=>$this->text()->notNull(),
        ]);
        $this->createIndex('github_module_unique', '{{%github_module_repository}}', 'module_id', true);
        $this->createTable('{{%github_module_log}}', [
            'id'=>$this->primaryKey(), 'module_id'=>$this->string(100)->notNull(),
            'repository_url'=>$this->string(255), 'channel_value'=>$this->string(200),
            'from_version'=>$this->string(255), 'to_version'=>$this->string(255), 'commit_sha'=>$this->string(40),
            'admin_id'=>$this->integer(), 'created_at'=>$this->integer()->notNull(),
            'phase'=>$this->string(30)->notNull(), 'result'=>$this->string(30)->notNull(), 'message'=>$this->text(),
        ]);
        $this->createIndex('github_module_log_module', '{{%github_module_log}}', ['module_id','created_at']);
    }
    public function safeDown()
    {
        $this->dropTable('{{%github_module_log}}'); $this->dropTable('{{%github_module_repository}}');
    }
}
