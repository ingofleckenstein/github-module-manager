<?php
// SPDX-License-Identifier: AGPL-3.0-only
if (PHP_VERSION_ID < 80200 || !extension_loaded('curl') || !class_exists(ZipArchive::class)) {
    return Yii::t('GithubModuleManagerModule.base', 'PHP 8.2 or newer with cURL and ZIP is required.');
}
return true;
