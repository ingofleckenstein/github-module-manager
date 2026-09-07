<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\githubmodulemanager\services;
use Yii;

class GitHubClient implements RepositoryProviderInterface
{
    public function __construct(private int $maxDownload = 52428800) {}

    public static function validateEndpoint(string $url): string
    {
        $p = parse_url($url);
        if (!$p || ($p['scheme'] ?? '') !== 'https' || !in_array($p['host'] ?? '', ['api.github.com', 'codeload.github.com', 'github.com'], true)
            || isset($p['user']) || isset($p['pass']) || isset($p['fragment']) || (isset($p['port']) && $p['port'] !== 443)) {
            throw new Failure('Download address is not allowed.');
        }
        return $p['host'];
    }

    protected function transfer(string $url, int $limit, ?string $destination = null): string
    {
        $host = self::validateEndpoint($url);
        $ips = gethostbynamel($host);
        if (!$ips) throw new Failure('GitHub could not be reached.');
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new Failure('Download address is not allowed.');
            }
        }
        $body = ''; $bytes = 0;
        $output = $destination === null ? null : fopen($destination, 'xb');
        if ($destination !== null && !$output) throw new Failure('Cannot create temporary download.');
        $curl = curl_init($url);
        try {
            curl_setopt_array($curl, [
                CURLOPT_FOLLOWLOCATION => false, // Construct codeload URL directly; reject all redirects.
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_RESOLVE => [$host . ':443:' . $ips[0]],
                CURLOPT_PROXY => '',
                CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 120,
                CURLOPT_USERAGENT => 'HumHub-GitHub-Module-Manager/0.1.0',
                CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'],
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$bytes, $limit, $output): int {
                    $bytes += strlen($chunk);
                    if ($bytes > $limit) return 0;
                    if ($output) return fwrite($output, $chunk);
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($bytes > $limit) throw new Failure('Download exceeds the configured size limit.');
            if ($status === 403 || $status === 429) throw new Failure('GitHub rate limit or access restriction reached. Try again later.');
            if ($status === 404) throw new Failure('Public repository or branch was not found.');
            if ($ok === false || $status !== 200) throw new Failure('GitHub download failed or timed out.');
            return $body;
        } finally {
            curl_close($curl);
            if ($output) fclose($output);
        }
    }

    private function api(string $path, bool $refresh): array
    {
        $key = ['github-module-manager.api', $path];
        if (!$refresh && is_array($cached = Yii::$app->cache->get($key))) return $cached;
        try { $data = json_decode($this->transfer('https://api.github.com' . $path, 2097152), true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new Failure('GitHub returned invalid metadata.'); }
        if (!is_array($data)) throw new Failure('GitHub returned invalid metadata.');
        Yii::$app->cache->set($key, $data, 900);
        return $data;
    }
    private function path(array $r): string { return '/repos/' . rawurlencode($r['owner']) . '/' . rawurlencode($r['name']); }
    public function repository(array $repository, bool $refresh = false): array
    {
        $data = $this->api($this->path($repository), $refresh);
        if (($data['private'] ?? true) || empty($data['default_branch'])) throw new Failure('Only public repositories are supported.');
        return $data;
    }
    public function branches(array $repository, bool $refresh = false): array
    {
        $names = [];
        for ($page = 1; $page <= 10; $page++) {
            $data = $this->api($this->path($repository) . '/branches?per_page=100&page=' . $page, $refresh);
            foreach ($data as $row) if (isset($row['name'])) $names[] = $row['name'];
            if (count($data) < 100) break;
        }
        return $names;
    }
    public function commit(array $repository, string $branch, bool $refresh = false): string
    {
        RepositoryUrl::branch($branch);
        $data = $this->api($this->path($repository) . '/commits/' . rawurlencode($branch), $refresh);
        if (!preg_match('/^[a-f0-9]{40}$/D', $data['sha'] ?? '')) throw new Failure('GitHub returned invalid metadata.');
        return $data['sha'];
    }
    public function download(array $repository, string $sha, string $destination): void
    {
        if (!preg_match('/^[a-f0-9]{40}$/D', $sha)) throw new Failure('Invalid commit.');
        $this->transfer('https://codeload.github.com/' . rawurlencode($repository['owner']) . '/' . rawurlencode($repository['name']) . '/zip/' . $sha, $this->maxDownload, $destination);
    }
}
