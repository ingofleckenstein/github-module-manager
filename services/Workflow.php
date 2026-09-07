<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\services;
use humhub\modules\githubmodulemanager\models\Repository;
use humhub\modules\githubmodulemanager\models\UpdateLog;
use Yii;

class Workflow
{
    public function __construct(private ?RepositoryProviderInterface $provider = null, private ?Paths $paths = null, private ?HumHubBridge $bridge = null)
    {
        $this->paths ??= new Paths(); $this->bridge ??= new HumHubBridge();
        $this->provider ??= new GitHubClient($this->limit('maxDownloadMb', 50) * 1048576);
    }
    private function limit(string $key, int $default): int { return max(1, (int)Yii::$app->getModule('github-module-manager')->settings->get($key, $default)); }
    public function discover(string $url): array
    {
        $source = RepositoryUrl::parse($url);
        $remote = $this->provider->repository($source);
        return ['source'=>$source,'defaultBranch'=>$remote['default_branch'],'branches'=>$this->provider->branches($source)];
    }
    public function inspect(string $url, string $branch, ?Repository $existing = null, bool $refresh = false): array
    {
        $this->cleanup();
        $source = RepositoryUrl::parse($url); RepositoryUrl::branch($branch);
        $remote = $this->provider->repository($source, $refresh);
        $sha = $this->provider->commit($source, $branch, $refresh);
        $token = bin2hex(random_bytes(24)); $dir = $this->paths->runtime() . '/previews/' . $token;
        Files::directory($dir);
        try {
            $this->provider->download($source, $sha, $dir . '/source.zip');
            $module = (new ArchiveValidator($this->limit('maxDownloadMb',50)*1048576, $this->limit('maxUnpackedMb',200)*1048576, $this->limit('maxFiles',10000)))->extract($dir . '/source.zip', $dir . '/unpacked');
            unlink($dir . '/source.zip');
            $validator = new ModuleValidator(); $info = $validator->inspect($module);
            $warnings = $validator->compatibility($info, $module, Yii::$app->version);
            if ($existing && $existing->module_id !== $info['id']) throw new Failure('Repository module ID does not match the local module.');
            $target = $this->paths->target($info['id'], $existing ? $existing->module_path : null);
            $local = is_dir($target) ? $validator->inspect($target) : null;
            $localHash = $local ? Files::hash($target) : null;
            if (!$existing && Repository::findOne(['module_id'=>$info['id']])) throw new Failure('This module already has a repository mapping.');
            $preview = ['token'=>$token,'admin'=>(int)Yii::$app->user->id,'expires'=>time()+1800,'source'=>$source,
                'branch'=>$branch,'defaultBranch'=>$remote['default_branch'],'sha'=>$sha,'info'=>$info,'warnings'=>$warnings,
                'module'=>$module,'hash'=>Files::hash($module),'target'=>$target,'local'=>$local,'localHash'=>$localHash,
                'repositoryId'=>$existing ? $existing->id : null,
                'mappingHash'=>$existing ? hash('sha256',json_encode($existing->attributes)) : null,
                'localChanges'=>$existing && $existing->file_hash && $localHash !== $existing->file_hash];
            Files::writeJson($dir . '/preview.json', $preview);
            return $preview;
        } catch (\Throwable $e) { Files::remove($dir); throw $e; }
    }
    public function preview(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{48}$/D',$token)) throw new Failure('Preview expired. Inspect the repository again.');
        $file=$this->paths->runtime().'/previews/'.$token.'/preview.json';
        $data=is_file($file) ? json_decode(file_get_contents($file),true) : null;
        if (!$data || $data['admin'] !== (int)Yii::$app->user->id || $data['expires'] < time()) throw new Failure('Preview expired. Inspect the repository again.');
        return $data;
    }
    private function record(array $p): Repository
    {
        if ($p['repositoryId']) {
            $r=Repository::findOne($p['repositoryId']);
            if (!$r || !hash_equals($p['mappingHash'], hash('sha256',json_encode($r->attributes)))) throw new Failure('Repository mapping changed. Inspect the repository again.');
            return $r;
        }
        if (Repository::findOne(['module_id'=>$p['info']['id']])) throw new Failure('This module already has a repository mapping.');
        $r=new Repository(); $r->module_id=$p['info']['id']; return $r;
    }
    private function source(Repository $r, array $p): void
    {
        $r->provider='github'; $r->repository_url=$p['source']['url']; $r->repository_owner=$p['source']['owner']; $r->repository_name=$p['source']['name'];
        $r->channel_type='branch'; $r->channel_value=$p['branch']; $r->module_path=$p['target'];
        $r->last_remote_version=$p['info']['version']; $r->last_remote_commit_sha=$p['sha']; $r->last_checked_at=time();
    }
    public function attach(string $token): Repository
    {
        $p=$this->preview($token); $lock=new Lock($this->paths->runtime(),$p['info']['id']);
        if (file_exists($this->paths->runtime().'/operations/'.$p['info']['id'].'.json')) throw new Failure('A previous operation needs manual recovery. See the operation journal.');
        $r=$this->record($p);
        $target=$this->paths->target($p['info']['id'],$p['target']);
        if (!$p['local'] || !is_dir($target) || Files::hash($target) !== $p['localHash']) throw new Failure('Local files changed after confirmation. Inspect the repository again.');
        $this->source($r,$p); $r->installed_version=$p['local']['version']; $r->installed_commit_sha=null;
        $r->file_hash=$p['localHash']; $r->status='unknown';
        $tx=Yii::$app->db->beginTransaction();
        try { if (!$r->save()) throw new Failure('Repository mapping could not be saved.'); $this->log($p,'mapping','success'); $tx->commit(); }
        catch (\Throwable $e) { $tx->rollBack(); throw $e; }
        Files::remove(dirname($p['module'],2));
        return $r;
    }
    public function install(string $token, bool $acknowledgeLocalChanges): Repository
    {
        $p=$this->preview($token);
        $previewLock=new Lock($this->paths->runtime(),'preview-'.$token);
        if (!hash_equals($p['hash'],Files::hash($p['module']))) throw new Failure('Staged files changed. Inspect the repository again.');
        $r=$this->record($p);
        if ($p['local'] && !$p['repositoryId']) throw new Failure('Map the existing module before updating it.');
        if ($p['localChanges'] && !$acknowledgeLocalChanges) throw new Failure('Confirm that local changes may be replaced.');
        $this->paths->target($p['info']['id'],$p['target']);
        $dir=dirname($p['module'],2); $id=$p['info']['id']; $config=[];
        $this->log($p,'start','installing');
        try {
            $installer=new ModuleInstaller($this->paths->runtime(),$this->limit('backupRetention',3));
            $warnings=$installer->install($id,$p['module'],$p['target'],$p['localHash'],function($target) use ($p,$r,$id,&$config) {
                $this->bridge->migrate($id,$target,$config);
                $this->bridge->clear();
                $this->source($r,$p); $r->installed_version=$p['info']['version']; $r->installed_commit_sha=$p['sha'];
                $r->file_hash=Files::hash($target); $r->last_updated_at=time(); $r->status='current';
                $tx=Yii::$app->db->beginTransaction();
                try { if (!$r->save()) throw new Failure('Repository mapping could not be saved.'); $this->log($p,'complete','success'); $tx->commit(); }
                catch (\Throwable $e) { $tx->rollBack(); throw $e; }
            },function() use ($p,&$config) { $this->record($p); $config=$this->bridge->requirements($p['module'],$p['info']['id']); });
            foreach ($warnings as $warning) $this->log($p,'cleanup','warning',$warning);
            return $r;
        } catch (\Throwable $e) {
            if (!$r->isNewRecord) { $r->refresh(); $r->status='error'; $r->save(false,['status']); }
            $journal=$this->paths->runtime().'/operations/'.$id.'.json';
            $state=is_file($journal) ? json_decode(file_get_contents($journal),true) : [];
            $this->log($p,$state['failedPhase'] ?? 'install','error',$e instanceof Failure ? $e->key : 'Unexpected installation error.');
            // Refresh discovery after restoration; do not suppress a second failure.
            try { $this->bridge->clear(); } catch (\Throwable $cacheError) { Yii::error(['module'=>$id,'phase'=>'recovery-cache','result'=>'error'],'github-module-manager'); }
            throw $e;
        } finally {
            if (is_dir($dir)) Files::remove($dir);
        }
    }
    public function check(Repository $r): void
    {
        $lock=new Lock($this->paths->runtime(),$r->module_id);
        if (file_exists($this->paths->runtime().'/operations/'.$r->module_id.'.json')) throw new Failure('A previous operation needs manual recovery. See the operation journal.');
        $r->status='checking'; $r->save(false,['status']);
        try {
            $p=$this->inspect($r->repository_url,$r->channel_value,$r,true);
            $r->last_remote_version=$p['info']['version']; $r->last_remote_commit_sha=$p['sha']; $r->last_checked_at=time();
            $r->status=$p['localChanges'] ? 'local_changes' : ($r->installed_commit_sha === $p['sha'] ? 'current' : 'update_available');
            $r->save(false); $this->log($p,'check','success'); Files::remove(dirname($p['module'],2));
        } catch (\Throwable $e) {
            $r->status='error'; $r->last_checked_at=time(); $r->save(false);
            $failure=['info'=>['id'=>$r->module_id,'version'=>$r->last_remote_version], 'source'=>$r->source(), 'branch'=>$r->channel_value, 'sha'=>$r->last_remote_commit_sha, 'local'=>['version'=>$r->installed_version]];
            $this->log($failure,'check','error',$e instanceof Failure ? $e->key : 'Unexpected operation error. Review the operation journal and server log.');
            throw $e;
        }
    }
    public function detach(Repository $r): void
    {
        $lock=new Lock($this->paths->runtime(),$r->module_id);
        if (file_exists($this->paths->runtime().'/operations/'.$r->module_id.'.json')) throw new Failure('A previous operation needs manual recovery. See the operation journal.');
        $p=['info'=>['id'=>$r->module_id,'version'=>$r->installed_version],'source'=>$r->source(),'branch'=>$r->channel_value,'sha'=>$r->installed_commit_sha,'local'=>['version'=>$r->installed_version]];
        $this->log($p,'detach','success'); if (!$r->delete()) throw new Failure('Repository mapping could not be removed.');
    }
    private function log(array $p,string $phase,string $result,?string $message=null): void
    {
        $log=new UpdateLog(); $log->setAttributes(['module_id'=>$p['info']['id'],'repository_url'=>$p['source']['url'],'channel_value'=>$p['branch'],
            'from_version'=>$p['local']['version']??null,'to_version'=>$p['info']['version'],'commit_sha'=>$p['sha'],
            'admin_id'=>Yii::$app->user->id,'created_at'=>time(),'phase'=>$phase,'result'=>$result,'message'=>$message],false);
        if (!$log->save(false)) throw new Failure('Update history could not be saved.');
        Yii::info(['module'=>$p['info']['id'],'commit'=>$p['sha'],'phase'=>$phase,'result'=>$result],'github-module-manager');
    }
    private function cleanup(): void
    {
        $path=$this->paths->runtime().'/previews'; Files::directory($path);
        foreach (glob($path.'/*',GLOB_ONLYDIR) ?: [] as $dir) {
            if (filemtime($dir) < time()-7200) {
                try { $lock=new Lock($this->paths->runtime(),'preview-'.basename($dir)); Files::remove($dir); unset($lock); }
                catch (Failure $e) { /* Active preview: clean on a later inspection. */ }
            }
        }
    }
}
