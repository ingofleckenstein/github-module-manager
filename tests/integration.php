<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Run only against a designated local test installation, never production.
if (getenv('GMM_INTEGRATION') !== '1' || !getenv('HUMHUB_ROOT')) {
    fwrite(STDERR,"Set GMM_INTEGRATION=1 and HUMHUB_ROOT to a local test installation.\n"); exit(2);
}
$protected=rtrim(getenv('HUMHUB_ROOT'),'/').'/protected';
define('YII_DEBUG',true); define('YII_ENV','test');
require $protected.'/vendor/autoload.php'; require $protected.'/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@humhub',$protected.'/humhub');
$bootstrap=new humhub\services\BootstrapService(true);
$app=new humhub\components\console\Application($bootstrap->getConfig('console'));
if (!str_contains($app->db->dsn,'test')) throw new RuntimeException('Only a test database is allowed');
$app->set('user',['class'=>yii\web\User::class,'identityClass'=>humhub\modules\user\models\User::class,'enableSession'=>false]);
$admin=humhub\modules\user\models\User::findOne(['username'=>'admin']);
if (!$admin || !$admin->isSystemAdmin()) throw new RuntimeException('Local test admin required');
$app->user->setIdentity($admin);
$app->set('assetManager',['class'=>humhub\components\AssetManager::class,'basePath'=>$protected.'/../assets','baseUrl'=>'/assets']);
use humhub\modules\githubmodulemanager\services\{Workflow,RepositoryProviderInterface,Files,Failure,Paths,ModuleValidator};
use humhub\modules\githubmodulemanager\models\{Repository,UpdateLog};
class FixtureProvider implements RepositoryProviderInterface
{
    public int $generation=1; public bool $failDownload=false; public string $moduleId='gmm-fixture'; public ?string $version=null;
    public function repository(array $r,bool $refresh=false): array { return ['private'=>false,'default_branch'=>'main']; }
    public function branches(array $r,bool $refresh=false): array { return ['main','feature/test']; }
    public function commit(array $r,string $branch,bool $refresh=false): string { return str_repeat((string)$this->generation,40); }
    public function download(array $r,string $sha,string $destination): void
    {
        if ($this->failDownload) throw new Failure('GitHub download failed or timed out.');
        $id=$this->moduleId; $version=$this->version ?? ($this->generation===1 ? '0.1.2' : '0.1.3');
        $namespace=$id === Workflow::MANAGER_ID ? 'humhub\\modules\\githubmodulemanager' : 'humhub\\modules\\gmmfixture';
        $zip=new ZipArchive(); $zip->open($destination,ZipArchive::CREATE);
        $zip->addFromString('wrapper/module.json',json_encode(['id'=>$id,'name'=>'GMM test fixture','version'=>$version,'humhub'=>['minVersion'=>'1.18.5']]));
        $zip->addFromString('wrapper/config.php',"<?php return ['id'=>'$id','class'=>'$namespace\\\\Module','namespace'=>'$namespace'];");
        $zip->addFromString('wrapper/Module.php',"<?php namespace $namespace; class Module extends \\humhub\\components\\Module {}\n");
        $zip->addFromString('wrapper/revision.txt',(string)$this->generation);
        $migration1 = <<<'CODE'
<?php class m260907_120001_fixture extends \yii\db\Migration {
 public function safeUp() { $this->createTable('{{%gmm_fixture_probe}}',['id'=>$this->primaryKey()]); }
 public function safeDown() { $this->dropTable('{{%gmm_fixture_probe}}'); }
}
CODE;
        $migration2 = <<<'CODE'
<?php class m260907_120002_fixture extends \yii\db\Migration {
 public function safeUp() { $this->addColumn('{{%gmm_fixture_probe}}','note',$this->string()); }
 public function safeDown() { $this->dropColumn('{{%gmm_fixture_probe}}','note'); }
}
CODE;
        $zip->addFromString('wrapper/migrations/m260907_120001_fixture.php',$migration1);
        if ($this->generation>=2) $zip->addFromString('wrapper/migrations/m260907_120002_fixture.php',$migration2);
        if ($this->generation===4) $zip->addFromString('wrapper/migrations/m260907_120004_failure.php','<?php class m260907_120004_failure extends \\yii\\db\\Migration { public function safeUp() { return false; } }');
        $zip->close();
    }
}
$count=0;
function ensure($condition,$message) { global $count; if (!$condition) throw new RuntimeException($message); ++$count; }
$provider=new FixtureProvider(); $workflow=new Workflow($provider); $url='https://github.com/example/gmm-fixture';
$target=(new Paths())->target('gmm-fixture');
if (file_exists($target) || Repository::findOne(['module_id'=>'gmm-fixture'])) throw new RuntimeException('Remove previous gmm-fixture test artifacts before running');
$runtime=(new Paths())->runtime();
$selfFixtureCreated=false;
try {
    $managerTarget=(new Paths())->managerTarget();
    ensure($managerTarget===realpath($app->getModule('github-module-manager')->getBasePath()),'Manager target is the loaded module path');
    ensure((new ModuleValidator())->inspect($managerTarget,true)['id']==='github-module-manager','Manager metadata is accepted only in self-update mode');
    $p=$workflow->inspect($url,'main'); ensure(!file_exists($target),'Inspection must not install files');
    $r=$workflow->install($p['token'],false);
    ensure($app->moduleManager->hasModule('gmm-fixture'),'HumHub discovers installed module');
    ensure(!$app->moduleManager->getModule('gmm-fixture')->isEnabled,'New module stays disabled');
    ensure($app->db->schema->getTableSchema('{{%gmm_fixture_probe}}',true)!==null,'Initial migration applied');
    ensure($r->installed_commit_sha===str_repeat('1',40),'SHA persisted');
    $provider->generation=2; $workflow->check($r); $r->refresh(); ensure($r->status==='update_available','Update detected');
    $p=$workflow->inspect($url,'main',$r); $r=$workflow->install($p['token'],false);
    ensure($app->db->schema->getTableSchema('{{%gmm_fixture_probe}}',true)->getColumn('note')!==null,'Update migration applied');
    ensure(count(glob($runtime.'/backups/gmm-fixture/*',GLOB_ONLYDIR))===1,'Backup exists');
    $provider->generation=3; $workflow->check($r); $r->refresh(); ensure($r->status==='different_commit','Same version with changed SHA is distinguished');
    $p=$workflow->inspect($url,'main',$r); $r=$workflow->install($p['token'],false);
    $before=Files::hash($target); $provider->failDownload=true;
    try { $workflow->inspect($url,'main',$r); throw new RuntimeException('Expected failed download'); } catch (Failure $e) { ensure(Files::hash($target)===$before,'Download failure leaves files intact'); }
    $provider->failDownload=false; $provider->generation=4;
    $p=$workflow->inspect($url,'main',$r);
    try { $workflow->install($p['token'],false); throw new RuntimeException('Expected migration failure'); } catch (Failure $e) { ensure(Files::hash($target)===$before,'Migration failure restores files'); }
    $r->refresh(); ensure($r->installed_commit_sha===str_repeat('3',40) && $r->status==='error','Failed migration does not record new version');
    ensure(is_file($runtime.'/operations/gmm-fixture.json'),'Recovery state persists');
    ensure(UpdateLog::find()->where(['module_id'=>'gmm-fixture','result'=>'success'])->count()>=3,'Audit history stored');
    ensure(UpdateLog::find()->where(['module_id'=>'gmm-fixture','result'=>'error'])->exists(),'Failure audit stored');
    ensure(Yii::t('GithubModuleManagerModule.base','Add repository',[],'de')==='Repository hinzufügen','German translation works');
    // Mapping preserves existing files and never invents a verified SHA.
    $provider->generation=3;
    $p=$workflow->inspect($url,'main',$r);
    try { $workflow->attach($p['token']); throw new RuntimeException('Expected recovery guard'); }
    catch (Failure $e) { ensure($r->status==='error','Recovery blocks mapping changes'); }
    unlink($runtime.'/operations/gmm-fixture.json');
    $workflow->detach($r); ensure(is_dir($target),'Detach preserves module files');
    $p=$workflow->inspect($url,'main');
    $r=$workflow->attach($p['token']);
    ensure($r->installed_commit_sha===null && Files::hash($target)===$before,'Existing module mapping preserves files and unknown SHA');
    $workflow->check($r); $r->refresh(); ensure($r->status==='current','Unknown SHA with equal module versions is current');
    $metadata=json_decode(file_get_contents($target.'/module.json'),true); $metadata['version']='0.1.4'; file_put_contents($target.'/module.json',json_encode($metadata));
    $workflow->rescan($r); $r->refresh(); ensure($r->installed_version==='0.1.4','Local rescan reads a changed module.json');
    if (Repository::findOne(['module_id'=>Workflow::MANAGER_ID])) throw new RuntimeException('Manager self-update mapping already exists; preserve it and run this test in a clean test installation.');
    $provider->moduleId=Workflow::MANAGER_ID; $provider->version='0.1.5';
    $self=$workflow->configureSelf('https://github.com/example/github-module-manager','main'); $selfFixtureCreated=true;
    $workflow->check($self); $self->refresh(); ensure($self->status==='update_available','Manager self-repository is checked through the protected path');
    $controller=new \humhub\modules\githubmodulemanager\controllers\AdminController('admin',$app->getModule('github-module-manager'));
    $access=$controller->behaviors()['access']['rules'][0]['matchCallback'];
    ensure($access(),'System administrator allowed');
    $normal=new \humhub\modules\user\models\User(['id'=>-1,'username'=>'unsaved-access-fixture']);
    $app->user->setIdentity($normal); ensure(!$access(),'Non-administrator denied'); $app->user->setIdentity($admin);
    echo "$count HumHub/MariaDB integration checks passed\n";
} finally {
    // The fixtures belong exclusively to this test; no other module/table is touched.
    if (is_dir($target)) Files::remove($target);
    foreach (['backups/gmm-fixture','operations/gmm-fixture.json'] as $relative) Files::remove($runtime.'/'.$relative);
    Repository::deleteAll(['module_id'=>'gmm-fixture']); UpdateLog::deleteAll(['module_id'=>'gmm-fixture']);
    if ($selfFixtureCreated) { Repository::deleteAll(['module_id'=>Workflow::MANAGER_ID]); UpdateLog::deleteAll(['module_id'=>Workflow::MANAGER_ID]); }
    $app->db->createCommand('DROP TABLE IF EXISTS {{%gmm_fixture_probe}}')->execute();
    $app->db->createCommand()->delete('{{%migration}}',['like','version','%m260907_12000%',false])->execute();
    $app->cache->flush();
}
