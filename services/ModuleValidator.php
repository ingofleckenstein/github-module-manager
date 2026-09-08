<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\services;

class ModuleValidator
{
    /**
     * The manager ID is accepted only by Workflow after it has verified that
     * the target is this installed manager.  It must remain protected for all
     * ordinary repository imports.
     */
    public function inspect(string $path, bool $allowManager = false): array
    {
        foreach (['module.json', 'Module.php', 'config.php'] as $file) {
            if (!is_file($path . '/' . $file) || is_link($path . '/' . $file)) throw new Failure('Required module file is missing: {file}', ['file'=>$file]);
        }
        if (filesize($path . '/module.json') > 65536) throw new Failure('Invalid module metadata.');
        try { $info = json_decode(file_get_contents($path . '/module.json'), true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new Failure('Invalid module metadata.'); }
        if (!is_array($info)) throw new Failure('Invalid module metadata.');
        foreach (['id','name','version'] as $field) if (!isset($info[$field]) || !is_string($info[$field]) || $info[$field] === '' || strlen($info[$field]) > 255) throw new Failure('Invalid module metadata.');
        self::id($info['id'], $allowManager);
        if (!preg_match('/^[0-9]+(?:\.[0-9]+)*(?:[-+][a-zA-Z0-9.-]+)?$/D', $info['version'])) throw new Failure('Invalid module version.');
        // Tokenize only. Never include/evaluate a downloaded config during inspection.
        $tokens = token_get_all(file_get_contents($path . '/config.php'), TOKEN_PARSE);
        $id = null; $class = false;
        $tokens = array_values(array_filter($tokens, static fn($t)=>!is_array($t) || !in_array($t[0], [T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)));
        for ($i=0; $i<count($tokens)-2; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_CONSTANT_ENCAPSED_STRING && ($tokens[$i+1][0] ?? null) === T_DOUBLE_ARROW) {
                $key = trim($tokens[$i][1], "'\"");
                if ($key === 'id') {
                    if ($id !== null || !is_array($tokens[$i+2]) || $tokens[$i+2][0] !== T_CONSTANT_ENCAPSED_STRING) throw new Failure('Module configuration needs one literal module ID.');
                    $id = trim($tokens[$i+2][1], "'\"");
                }
                if ($key === 'class') $class = true;
                if (in_array($key, ['isCoreModule','isInstallerModule'],true)) throw new Failure('Core and installer modules are not supported.');
            }
        }
        if ($id !== $info['id'] || !$class) throw new Failure('Module ID and config.php do not match.');
        token_get_all(file_get_contents($path . '/Module.php'), TOKEN_PARSE);
        return $info;
    }
    public static function id(string $id, bool $allowManager = false): void
    {
        $protected = ['github-module-manager','app','web','webroot','runtime','vendor','humhub','yii','bower','npm','web-static','webroot-static'];
        if (!preg_match('/^[a-z][a-z0-9-]{0,99}$/D', $id) || (in_array($id, $protected, true) && !($allowManager && $id === 'github-module-manager'))) throw new Failure('Invalid or protected module ID.');
    }
    public function compatibility(array $info, string $path, string $humhubVersion): array
    {
        $warnings = [];
        foreach (['minVersion','maxVersion'] as $field) {
            $limit = $info['humhub'][$field] ?? null;
            if ($limit === null) continue;
            if (!is_string($limit) || !preg_match('/^\d+\.\d+(?:\.(?:\d+|\*))?$/D', $limit)) throw new Failure('Unsupported compatibility constraint.');
            $current = $humhubVersion;
            if ($field === 'maxVersion' && (str_ends_with($limit, '.*') || substr_count($limit, '.') === 1)) {
                $limit = preg_replace('/\.\*$/', '', $limit);
                $current = implode('.', array_slice(explode('.', $current), 0, 2));
            }
            if (version_compare($current, $limit, $field === 'minVersion' ? '<' : '>')) throw new Failure('This module is not compatible with the HumHub version.');
        }
        // Core 1.18.5 uses executable requirements.php, not a public generic dependency resolver.
        // Unknown declarative dependencies cannot silently be assumed satisfied.
        foreach (['dependencies','requirements','require','requires','php'] as $field) {
            if (!empty($info[$field])) throw new Failure('This module declares requirements that need manual installation.');
        }
        if (is_file($path . '/composer.json')) {
            try { $composer = json_decode(file_get_contents($path . '/composer.json'), true, 32, JSON_THROW_ON_ERROR); }
            catch (\Throwable $e) { throw new Failure('Invalid Composer metadata.'); }
            if (!is_array($composer)) throw new Failure('Invalid Composer metadata.');
            if (!empty($composer['require'])) throw new Failure('Composer requirements need manual installation; Composer is never executed.');
            $warnings[] = 'Composer is not executed. Development dependencies are not installed.';
        }
        if (is_file($path . '/requirements.php')) $warnings[] = 'Module requirements will execute only after your confirmation.';
        return $warnings;
    }
}
