<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Pure service regression suite: PHP 8.2+, zip, curl. No HumHub or network needed.
$root=dirname(__DIR__);
spl_autoload_register(static function($class) use ($root) {
    $prefix='humhub\\modules\\githubmodulemanager\\';
    if (str_starts_with($class,$prefix)) require $root.'/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
});
use humhub\modules\githubmodulemanager\services\{RepositoryUrl,GitHubClient,ArchiveValidator,ModuleValidator,ModuleInstaller,Files,Failure,Lock};
$count=0;
function check($ok,$message) { global $count; if (!$ok) throw new RuntimeException($message); ++$count; }
function reject(callable $f,$message) { try { $f(); } catch (Throwable $e) { check(true,$message); return; } check(false,$message); }
$tmp=sys_get_temp_dir().'/github-manager-tests-'.bin2hex(random_bytes(5)); Files::directory($tmp);
function zipFixture($path,array $files): void { $z=new ZipArchive(); $z->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE); foreach($files as $name=>$body) $z->addFromString($name,$body); $z->close(); }
function moduleFiles($version='1.0.0', $id='example-module'): array { return ['wrapper/module.json'=>json_encode(['id'=>$id,'name'=>'Example','version'=>$version,'humhub'=>['minVersion'=>'1.18.0','maxVersion'=>'1.18.*']]),'wrapper/Module.php'=>'<?php namespace Example; class Module {}','wrapper/config.php'=>"<?php return ['id'=>'$id','class'=>'Example\\\\Module'];"]; }
try {
    foreach(['https://github.com/owner/repo','https://github.com/owner/repo.git','https://github.com/owner/repo/'] as $url) check(RepositoryUrl::parse($url)['name']==='repo','Valid URL');
    foreach(['http://github.com/a/b','https://github.com.evil/a/b','https://github.com/a/b?x=y','https://github.com/a/b#x','https://user@github.com/a/b','https://github.com/a/../b','file:///tmp/a','https://127.0.0.1/a/b','git@github.com:a/b','https://github.com/a/%2e%2e','https://github.com/a/..'] as $url) reject(fn()=>RepositoryUrl::parse($url),'Bad URL');
    foreach(['main','feature/private-follower-sharing'] as $branch) check(RepositoryUrl::branch($branch)===$branch,'Valid branch');
    foreach(['../main','a..b','a b','a\\b','x?y','a~b','/main','main/','x.lock','x//y'] as $branch) reject(fn()=>RepositoryUrl::branch($branch),'Bad branch');
    foreach(['https://localhost/x','file:///a','https://api.github.com.evil/x','https://api.github.com:444/x','https://x@y.github.com/x'] as $url) reject(fn()=>GitHubClient::validateEndpoint($url),'SSRF endpoint rejected');
    check(GitHubClient::validateEndpoint('https://api.github.com/repos/o/r')==='api.github.com','Allowed API');
    zipFixture($tmp.'/good.zip',moduleFiles()); $validator=new ArchiveValidator();
    $path=$validator->extract($tmp.'/good.zip',$tmp.'/good');
    $info=(new ModuleValidator())->inspect($path); check($info['id']==='example-module','Metadata ID');
    check((new ModuleValidator())->compatibility($info,$path,'1.18.5')===[],'HumHub compatibility');
    reject(fn()=>(new ModuleValidator())->compatibility($info,$path,'1.19.0'),'Incompatible HumHub');
    foreach(['../outside','/absolute','wrapper/../../outside','wrapper/..\\outside','C:/outside','wrapper/file:stream','wrapper/CON','wrapper/a.','wrapper//bad','other/file'] as $name) {
        zipFixture($tmp.'/bad.zip',moduleFiles()+[$name=>'bad']); reject(fn()=>$validator->extract($tmp.'/bad.zip',$tmp.'/bad'),'Unsafe ZIP'); check(!file_exists($tmp.'/bad'),'Reject before extraction');
    }
    zipFixture($tmp.'/link.zip',moduleFiles()+['wrapper/link'=>'../../outside']); $z=new ZipArchive(); $z->open($tmp.'/link.zip'); $z->setExternalAttributesName('wrapper/link',ZipArchive::OPSYS_UNIX,0120777<<16); $z->close();
    reject(fn()=>$validator->extract($tmp.'/link.zip',$tmp.'/link'),'Symlink ZIP');
    reject(fn()=>(new ArchiveValidator(1))->extract($tmp.'/good.zip',$tmp.'/size'),'Download size');
    reject(fn()=>(new ArchiveValidator(999999,1))->extract($tmp.'/good.zip',$tmp.'/size'),'Unpacked size');
    reject(fn()=>(new ArchiveValidator(999999,999999,1))->extract($tmp.'/good.zip',$tmp.'/size'),'Entry count');
    file_put_contents($tmp.'/broken.zip','not zip'); reject(fn()=>$validator->extract($tmp.'/broken.zip',$tmp.'/broken'),'Broken ZIP');
    zipFixture($tmp.'/missing.zip',['wrapper/Module.php'=>'<?php']); $missing=$validator->extract($tmp.'/missing.zip',$tmp.'/missing'); reject(fn()=>(new ModuleValidator())->inspect($missing),'Missing metadata');
    file_put_contents($path.'/config.php',"<?php file_put_contents('".$tmp."/EXECUTED','bad'); return ['id'=>'example-module','class'=>'Example'];");
    (new ModuleValidator())->inspect($path); check(!file_exists($tmp.'/EXECUTED'),'Inspection never executes PHP');
    file_put_contents($path.'/config.php',"<?php return ['id'=>'different','class'=>'Example'];"); reject(fn()=>(new ModuleValidator())->inspect($path),'ID mismatch');
    file_put_contents($path.'/config.php',"<?php return ['id'=>'example-module','class'=>'Example'];");
    file_put_contents($path.'/composer.json','{"require":{"some/package":"*"}}'); reject(fn()=>(new ModuleValidator())->compatibility($info,$path,'1.18.5'),'Composer requirements rejected'); unlink($path.'/composer.json');
    $runtime=$tmp.'/runtime'; Files::directory($runtime); Files::directory($tmp.'/modules');
    $installer=new ModuleInstaller($runtime,1); $target=$tmp.'/modules/example-module';
    $installer->install('example-module',$path,$target,null,fn()=>null); check(is_file($target.'/module.json'),'New installation');
    $hash=Files::hash($target); reject(fn()=>$installer->install('example-module',$tmp.'/missing',$target,null,fn()=>null),'Cannot overwrite as new');
    zipFixture($tmp.'/update.zip',moduleFiles('1.1.0')); $stage=$validator->extract($tmp.'/update.zip',$tmp.'/update');
    $installer->install('example-module',$stage,$target,$hash,fn()=>null); check(json_decode(file_get_contents($target.'/module.json'),true)['version']==='1.1.0','Update');
    check(count(glob($runtime.'/backups/example-module/*'))===1,'Backup retained');
    $hash=Files::hash($target); $stage=$validator->extract($tmp.'/good.zip',$tmp.'/failure');
    reject(fn()=>$installer->install('example-module',$stage,$target,$hash,fn()=>throw new RuntimeException('Migration failure')),'Migration failure');
    check(Files::hash($target)===$hash,'Old files restored'); check(is_file($runtime.'/operations/example-module.json'),'Recovery journal remains');
    reject(fn()=>$installer->install('example-module',$stage,$target,$hash,fn()=>null),'Unresolved recovery blocks update'); unlink($runtime.'/operations/example-module.json');
    $faulty=new class($runtime) extends ModuleInstaller { protected function move(string $from,string $to): void { if (str_contains($from,'failure/wrapper')) throw new Failure('swap failed'); parent::move($from,$to); } };
    reject(fn()=>$faulty->install('example-module',$stage,$target,$hash,fn()=>null),'Swap error'); check(Files::hash($target)===$hash,'Swap rollback');
    file_put_contents($target.'/local.txt','changed'); reject(fn()=>$installer->install('example-module',$stage,$target,$hash,fn()=>null),'Local modification protection'); unlink($target.'/local.txt');
    $lock=new Lock($runtime,'example-module'); reject(fn()=>$installer->install('example-module',$stage,$target,$hash,fn()=>null),'Concurrent update blocked'); unset($lock);
    $before=Files::hash($target); reject(fn()=>$installer->install('example-module',$stage,$target,$hash,fn()=>null,fn()=>throw new Failure('requirements')),'Requirements failure'); check(Files::hash($target)===$before,'Requirements do not exchange files');
    foreach (['app','runtime','web','humhub','github-module-manager'] as $id) reject(fn()=>ModuleValidator::id($id),'Reserved module ID');
    zipFixture($tmp.'/manager.zip',moduleFiles('1.0.0','github-module-manager')); $manager=$validator->extract($tmp.'/manager.zip',$tmp.'/manager');
    reject(fn()=>(new ModuleValidator())->inspect($manager),'Manager remains protected for ordinary imports');
    check((new ModuleValidator())->inspect($manager,true)['id']==='github-module-manager','Manager requires explicit self-update mode');
    $faultyBackup=new class($runtime) extends ModuleInstaller { protected function move(string $from,string $to): void { if (str_contains($to,'/backups/')) throw new Failure('backup failed'); parent::move($from,$to); } };
    reject(fn()=>$faultyBackup->install('example-module',$stage,$target,$hash,fn()=>null),'Backup failure'); check(Files::hash($target)===$hash,'Backup failure keeps active files');
    chmod(dirname($target),0555);
    try { reject(fn()=>$installer->install('example-module',$stage,$target,$hash,fn()=>null),'Missing write permissions'); }
    finally { chmod(dirname($target),0700); }
    echo "$count checks passed\n";
} finally { Files::remove($tmp); }
