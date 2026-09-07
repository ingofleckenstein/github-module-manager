<?php
// SPDX-License-Identifier: AGPL-3.0-only
use yii\helpers\Html;
$t=static fn($s)=>Yii::t('GithubModuleManagerModule.base',$s); $r=$repository;
?>
<div class="panel panel-default"><div class="panel-heading"><?= Html::encode($r->module_id) ?></div><div class="panel-body">
<dl><?php foreach (['Installed'=>$r->installed_version,'Installed commit'=>$r->installed_commit_sha ?: $t('Unknown'),'Remote'=>$r->last_remote_version ?: $t('Not checked'),'Remote commit'=>$r->last_remote_commit_sha,'Branch'=>$r->channel_value,'Status'=>$t($r->status),'Last checked'=>$r->last_checked_at ? Yii::$app->formatter->asDatetime($r->last_checked_at) : $t('Not checked'),'Last updated'=>$r->last_updated_at ? Yii::$app->formatter->asDatetime($r->last_updated_at) : $t('Unknown')] as $label=>$value): ?><dt><?= Html::encode($t($label)) ?></dt><dd><?= Html::encode($value) ?></dd><?php endforeach ?></dl>
<p><?= Html::a(Html::encode($r->repository_url),$r->repository_url,['rel'=>'noopener noreferrer','target'=>'_blank']) ?></p>
<?= Html::beginForm(['check','id'=>$r->id],'post',['style'=>'display:inline']) ?><?= Html::submitButton(Html::encode($t('Check for updates')),['class'=>'btn btn-default']) ?><?= Html::endForm() ?>
<?= Html::beginForm(['inspect'],'post',['style'=>'display:inline']) ?><?= Html::hiddenInput('id',$r->id) ?><?= Html::hiddenInput('url',$r->repository_url) ?><?= Html::hiddenInput('branch',$r->channel_value) ?><?= Html::submitButton(Html::encode($t('Review update')),['class'=>'btn btn-primary']) ?><?= Html::endForm() ?>
<?= Html::a(Html::encode($t('Change repository')),['add','id'=>$r->id],['class'=>'btn btn-default']) ?>
<?= Html::beginForm(['detach','id'=>$r->id],'post') ?><p><?= Html::encode($t('Removing the mapping keeps the module files and update history.')) ?></p><?= Html::submitButton(Html::encode($t('Remove mapping')),['class'=>'btn btn-default']) ?><?= Html::endForm() ?>
<h2><?= Html::encode($t('Update history')) ?></h2><div class="table-responsive"><table class="table"><thead><tr><?php foreach (['Date','Version','Commit','Admin','Phase','Result'] as $label): ?><th scope="col"><?= Html::encode($t($label)) ?></th><?php endforeach ?></tr></thead><tbody>
<?php foreach ($logs as $log): ?><tr><td><?= Html::encode(Yii::$app->formatter->asDatetime($log->created_at)) ?></td><td><?= Html::encode(($log->from_version ?? '—').' → '.$log->to_version) ?></td><td><?= Html::encode(substr((string)$log->commit_sha,0,12)) ?></td><td><?= Html::encode($log->admin_id) ?></td><td><?= Html::encode($t($log->phase)) ?></td><td><?= Html::encode($t($log->result)) ?> <?= Html::encode($log->message ? $t($log->message) : '') ?></td></tr><?php endforeach ?>
</tbody></table></div></div></div>
