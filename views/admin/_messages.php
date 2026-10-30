<?php
// SPDX-License-Identifier: AGPL-3.0-only
use yii\helpers\Html;
?>
<?php if (Yii::$app->session->hasFlash('gmm-error')): ?>
<div class="alert alert-danger" role="alert"><?= Html::encode(Yii::$app->session->getFlash('gmm-error')) ?></div>
<?php endif; ?>
