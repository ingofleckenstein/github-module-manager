<?php
// SPDX-License-Identifier: AGPL-3.0-only
return [
    'id'=>'github-module-manager',
    'class'=>humhub\modules\githubmodulemanager\Module::class,
    'namespace'=>'humhub\modules\githubmodulemanager',
    'events'=>[
        [humhub\modules\admin\widgets\AdminMenu::class, humhub\modules\admin\widgets\AdminMenu::EVENT_INIT, [humhub\modules\githubmodulemanager\Events::class,'onAdminMenu']],
    ],
];
