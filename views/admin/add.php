<?php
// SPDX-License-Identifier: AGPL-3.0-only
use yii\helpers\Html;
$t=static fn($s)=>Yii::t('GithubModuleManagerModule.base',$s);
?>
<div class="panel panel-default"><div class="panel-heading"><?= Html::encode($t('Add or change repository')) ?></div><div class="panel-body">
<p><?= Html::encode($t('Only public repositories are supported. Your private repositories must be published or installed manually.')) ?></p>
<?= Html::beginForm(['discover'],'post') ?>
<?= Html::hiddenInput('id',$repository ? $repository->id : '') ?>
<div class="form-group"><?= Html::label(Html::encode($t('GitHub repository URL')),'repository-url') ?><?= Html::input('url','url',$discovery['source']['url'] ?? ($repository->repository_url ?? ''),['id'=>'repository-url','class'=>'form-control','required'=>true,'placeholder'=>'https://github.com/owner/repository']) ?></div>
<?= Html::submitButton(Html::encode($t('Read repository')),['class'=>'btn btn-default']) ?><?= Html::endForm() ?>
<?php if ($discovery): ?>
<hr><p><?= Html::encode($t('Default branch').': '.$discovery['defaultBranch']) ?></p>
<?= Html::beginForm(['inspect'],'post') ?><?= Html::hiddenInput('id',$repository ? $repository->id : '') ?><?= Html::hiddenInput('url',$discovery['source']['url']) ?>
<div class="form-group"><?= Html::label(Html::encode($t('Branch')),'branch') ?><?= Html::input('text','branch',$repository->channel_value ?? $discovery['defaultBranch'],['id'=>'branch','class'=>'form-control','required'=>true,'list'=>'branches']) ?>
<datalist id="branches"><?php foreach ($discovery['branches'] as $branch): ?><option value="<?= Html::encode($branch) ?>"></option><?php endforeach ?></datalist></div>
<?= Html::submitButton(Html::encode($t('Inspect module without installing')),['class'=>'btn btn-primary']) ?><?= Html::endForm() ?>
<?php endif ?>
</div></div>
