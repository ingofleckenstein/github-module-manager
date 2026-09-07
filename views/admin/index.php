<?php
// SPDX-License-Identifier: AGPL-3.0-only
use yii\helpers\Html;
$t=static fn($s,$p=[])=>Yii::t('GithubModuleManagerModule.base',$s,$p);
?>
<div class="panel panel-default"><div class="panel-heading"><?= Html::encode($t('GitHub Module Manager')) ?></div><div class="panel-body">
<p><?= Html::encode($t('Manage trusted public GitHub branches. Installation does not activate modules.')) ?></p>
<?= Html::a(Html::encode($t('Add repository')),['add'],['class'=>'btn btn-primary']) ?>
<?= Html::beginForm(['check-all'],'post',['style'=>'display:inline']) ?><?= Html::submitButton(Html::encode($t('Check all for updates')),['class'=>'btn btn-default']) ?><?= Html::endForm() ?>
<div class="table-responsive"><table class="table"><thead><tr>
<?php foreach (['Module','Installed','Repository','Branch','Remote','Status','Action'] as $label): ?><th scope="col"><?= Html::encode($t($label)) ?></th><?php endforeach ?>
</tr></thead><tbody>
<?php foreach ($repositories as $r): ?><tr>
<td><?= Html::a(Html::encode($r->module_id),['view','id'=>$r->id]) ?></td><td><?= Html::encode($r->installed_version) ?></td><td><?= Html::encode($r->repository_owner.'/'.$r->repository_name) ?></td><td><?= Html::encode($r->channel_value) ?></td><td><?= Html::encode($r->last_remote_version ?: $t('Not checked')) ?></td><td><?= Html::encode($t($r->status)) ?></td><td><?= Html::a(Html::encode($t('Details')),['view','id'=>$r->id]) ?></td>
</tr><?php endforeach ?>
</tbody></table></div>
<h2><?= Html::encode($t('Custom modules without a repository mapping')) ?></h2>
<?php foreach ($unmapped as $info): ?><p><?= Html::encode($info['name'].' ('.$info['id'].') '.$info['version']) ?> — <?= Html::a(Html::encode($t('Map repository')),['add']) ?></p><?php endforeach ?>
<details><summary><?= Html::encode($t('Download and backup settings')) ?></summary>
<?= Html::beginForm(['settings'],'post') ?>
<?php foreach (['maxDownloadMb'=>['Maximum download (MB)',50,200],'maxUnpackedMb'=>['Maximum unpacked size (MB)',200,1000],'maxFiles'=>['Maximum archive entries',10000,50000],'backupRetention'=>['Backups to keep',3,20]] as $key=>$setting): ?>
<div class="form-group"><?= Html::label(Html::encode($t($setting[0])),$key) ?><?= Html::input('number',$key,$this->context->module->settings->get($key,$setting[1]),['id'=>$key,'class'=>'form-control','min'=>1,'max'=>$setting[2],'required'=>true]) ?></div>
<?php endforeach ?>
<?= Html::submitButton(Html::encode($t('Save settings')),['class'=>'btn btn-primary']) ?><?= Html::endForm() ?></details>
</div></div>
