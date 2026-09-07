<?php
// SPDX-License-Identifier: AGPL-3.0-only
use yii\helpers\Html;
$t=static fn($s)=>Yii::t('GithubModuleManagerModule.base',$s);
?>
<div class="panel panel-default"><div class="panel-heading"><?= Html::encode($t('Review module')) ?></div><div class="panel-body">
<dl><?php foreach (['Repository'=>$p['source']['url'],'Owner'=>$p['source']['owner'],'Default branch'=>$p['defaultBranch'],'Module'=>$p['info']['name'],'Module ID'=>$p['info']['id'],'Description'=>$p['info']['description']??'','Installed'=>$p['local']['version']??$t('Not installed'),'Remote'=>$p['info']['version'],'Branch'=>$p['branch'],'Commit'=>$p['sha']] as $label=>$value): ?>
<dt><?= Html::encode($t($label)) ?></dt><dd><?= Html::encode(is_scalar($value) ? $value : '') ?></dd><?php endforeach ?></dl>
<p><?= Html::encode($t('A valid HumHub module was found. No module files have been installed yet.')) ?></p>
<?php foreach ($p['warnings'] as $warning): ?><p class="alert alert-warning"><?= Html::encode($t($warning)) ?></p><?php endforeach ?>
<?php if ($p['localChanges']): ?><p class="alert alert-warning"><?= Html::encode($t('Local changes detected. Updating will replace them.')) ?></p><?php endif ?>
<?php if ($p['local']): ?>
<?= Html::beginForm(['attach'],'post') ?><?= Html::hiddenInput('token',$p['token']) ?><p><?= Html::encode($t('Mapping changes no module files. The installed commit remains unknown until a controlled update.')) ?></p><?= Html::submitButton(Html::encode($t('Save repository mapping only')),['class'=>'btn btn-default']) ?><?= Html::endForm() ?>
<?php endif ?>
<?php if (!$p['local'] || $p['repositoryId']): ?>
<p class="alert alert-warning"><?= Html::encode($t('Install only modules from repositories you trust. HumHub modules contain executable server-side code.')) ?></p>
<p><?= Html::encode($t('A file backup cannot undo database migrations. Create a database backup before continuing.')) ?></p>
<?= Html::beginForm(['install'],'post') ?><?= Html::hiddenInput('token',$p['token']) ?>
<div class="checkbox"><?= Html::checkbox('trust',false,['value'=>'1','required'=>true,'label'=>$t('I trust this repository and have a database backup.')]) ?></div>
<?php if ($p['localChanges']): ?><div class="checkbox"><?= Html::checkbox('localChanges',false,['value'=>'1','required'=>true,'label'=>$t('Replace my local changes with this commit.')]) ?></div><?php endif ?>
<?= Html::submitButton(Html::encode($t($p['local'] ? 'Install update' : 'Install module')),['class'=>'btn btn-primary']) ?><?= Html::endForm() ?>
<?php endif ?>
<?= Html::a(Html::encode($t('Cancel')),['index'],['class'=>'btn btn-default']) ?>
</div></div>
