<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\controllers;
use humhub\modules\githubmodulemanager\models\Repository;
use humhub\modules\githubmodulemanager\models\UpdateLog;
use humhub\modules\githubmodulemanager\services\{Workflow,Failure,Paths,ModuleValidator};
use Yii;
use yii\filters\{AccessControl,VerbFilter};
use yii\web\{ForbiddenHttpException,NotFoundHttpException};
class AdminController extends \humhub\modules\admin\components\Controller
{
    public function behaviors()
    {
        return [
            'access'=>['class'=>AccessControl::class,'rules'=>[['allow'=>true,'roles'=>['@'],'matchCallback'=>static fn()=>Yii::$app->user->identity->isSystemAdmin()]]],
            'verbs'=>['class'=>VerbFilter::class,'actions'=>[
                'discover'=>['POST'],'inspect'=>['POST'],'install'=>['POST'],'attach'=>['POST'],
                'check'=>['POST'],'check-all'=>['POST'],'detach'=>['POST'],'settings'=>['POST'],
                'index'=>['GET'],'add'=>['GET'],'view'=>['GET'],'confirm'=>['GET'],
            ]],
        ];
    }
    public function actionIndex()
    {
        $repositories=Repository::find()->orderBy(['module_id'=>SORT_ASC])->all(); $unmapped=[];
        $mapped=array_map(static fn($r)=>$r->module_id,$repositories);
        foreach ((new Paths())->roots() as $root) foreach (glob($root.'/*',GLOB_ONLYDIR) ?: [] as $path) {
            try {
                $info=(new ModuleValidator())->inspect($path);
                if (!in_array($info['id'],$mapped,true)) $unmapped[]=$info;
            } catch (\Throwable $e) { /* Only valid custom modules belong in the mapping list. */ }
        }
        return $this->render('index',['repositories'=>$repositories,'unmapped'=>$unmapped]);
    }
    public function actionAdd($id=null)
    {
        return $this->render('add',['repository'=>$id ? $this->repository($id) : null,'discovery'=>null]);
    }
    public function actionDiscover()
    {
        try {
            $repository=Yii::$app->request->post('id') ? $this->repository(Yii::$app->request->post('id')) : null;
            $discovery=(new Workflow())->discover((string)Yii::$app->request->post('url'));
            return $this->render('add',['repository'=>$repository,'discovery'=>$discovery]);
        } catch (\Throwable $e) { return $this->error($e,['add']); }
    }
    public function actionInspect()
    {
        try {
            $repository=Yii::$app->request->post('id') ? $this->repository(Yii::$app->request->post('id')) : null;
            $p=(new Workflow())->inspect((string)Yii::$app->request->post('url'),(string)Yii::$app->request->post('branch'),$repository,true);
            return $this->redirect(['confirm','token'=>$p['token']]);
        } catch (\Throwable $e) { return $this->error($e,['index']); }
    }
    public function actionConfirm(string $token)
    {
        try { return $this->render('confirm',['p'=>(new Workflow())->preview($token)]); }
        catch (\Throwable $e) { return $this->error($e,['index']); }
    }
    public function actionInstall()
    {
        try {
            if (Yii::$app->request->post('trust') !== '1') throw new Failure('Confirm that you trust this repository and have a database backup.');
            $r=(new Workflow())->install((string)Yii::$app->request->post('token'),Yii::$app->request->post('localChanges') === '1');
            Yii::$app->session->setFlash('success',Yii::t('GithubModuleManagerModule.base','Module files installed and migrations completed. Activation remains unchanged; new modules can be enabled under Administration → Modules.'));
            return $this->redirect(['view','id'=>$r->id]);
        } catch (\Throwable $e) { return $this->error($e,['index']); }
    }
    public function actionAttach()
    {
        try { $r=(new Workflow())->attach((string)Yii::$app->request->post('token')); return $this->redirect(['view','id'=>$r->id]); }
        catch (\Throwable $e) { return $this->error($e,['index']); }
    }
    public function actionView(int $id)
    {
        $r=$this->repository($id);
        $logs=UpdateLog::find()->where(['module_id'=>$r->module_id])->orderBy(['id'=>SORT_DESC])->limit(100)->all();
        return $this->render('view',['repository'=>$r,'logs'=>$logs]);
    }
    public function actionCheck(int $id)
    {
        try { (new Workflow())->check($this->repository($id)); return $this->redirect(['view','id'=>$id]); }
        catch (\Throwable $e) { return $this->error($e,['view','id'=>$id]); }
    }
    public function actionCheckAll()
    {
        $workflow=new Workflow(); $failures=0;
        foreach (Repository::find()->all() as $r) {
            try { $workflow->check($r); } catch (\Throwable $e) { ++$failures; }
        }
        Yii::$app->session->setFlash($failures ? 'warning' : 'success',Yii::t('GithubModuleManagerModule.base','Update checks finished. Failed checks: {count}',['count'=>$failures]));
        return $this->redirect(['index']);
    }
    public function actionDetach(int $id)
    {
        try { (new Workflow())->detach($this->repository($id)); return $this->redirect(['index']); }
        catch (\Throwable $e) { return $this->error($e,['view','id'=>$id]); }
    }
    public function actionSettings()
    {
        try {
            $values=[];
            foreach (['maxDownloadMb'=>[1,200], 'maxUnpackedMb'=>[1,1000], 'maxFiles'=>[1,50000], 'backupRetention'=>[1,20]] as $key=>$range) {
                $value=filter_var(Yii::$app->request->post($key),FILTER_VALIDATE_INT);
                if ($value === false || $value<$range[0] || $value>$range[1]) throw new Failure('Invalid size or retention setting.');
                $values[$key]=$value;
            }
            $transaction=Yii::$app->db->beginTransaction();
            try {
                foreach ($values as $key=>$value) $this->module->settings->set($key,$value);
                $transaction->commit();
            } catch (\Throwable $error) { $transaction->rollBack(); throw $error; }
            return $this->redirect(['index']);
        } catch (\Throwable $e) { return $this->error($e,['index']); }
    }
    private function repository($id): Repository
    {
        $r=Repository::findOne((int)$id); if (!$r) throw new NotFoundHttpException(); return $r;
    }
    private function error(\Throwable $e,array $route)
    {
        $key=$e instanceof Failure ? $e->key : 'Unexpected operation error. Review the operation journal and server log.';
        // Never record arbitrary remote PHP exception bodies or HTTP response headers.
        Yii::error(['phase'=>$this->action->id,'result'=>'error','type'=>get_class($e),'message'=>$key],'github-module-manager');
        Yii::$app->session->setFlash('error',Yii::t('GithubModuleManagerModule.base',$key,$e instanceof Failure ? $e->parameters : []));
        return $this->redirect($route);
    }
}
