<?php

namespace Modules\Atelier\Services;

/**
 * Updates Atelier from its GitHub releases, from Settings > Atelier (admins only).
 *
 * - looks up the latest GitHub release of the repository named in module.json ("repository": "owner/name");
 * - downloads the release zip over HTTPS, from GitHub hosts only;
 * - checks it (module alias, newer version, safe paths, allowed file types, size) before touching anything;
 * - keeps a backup of the current version and puts it back if the copy fails.
 */
class Updater
{
    const CACHE_KEY = 'atelier.update.latest';
    const LOCK_KEY = 'atelier.update.lock';
    const MAX_ZIP = 26214400;        // 25 MB
    const MAX_UNPACKED = 78643200;   // 75 MB
    const MAX_FILES = 3000;

    /** Hosts a download may come from (a redirect to anywhere else is refused). */
    public static $hosts = ['api.github.com', 'github.com', 'codeload.github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com'];
    /** Base URL of the GitHub API; only changed by tests. */
    public static $api = 'https://api.github.com';
    /** Tests only: accept plain http. */
    public static $allowHttp = false;

    protected static $exts = ['php', 'json', 'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'md', 'txt', 'woff', 'woff2', 'html', 'lock'];

    public static function modulePath()
    {
        return dirname(__DIR__);
    }

    public static function workDir()
    {
        return storage_path('app/atelier-update');
    }

    public static function manifest()
    {
        $j = json_decode((string)@file_get_contents(self::modulePath().'/module.json'), true);
        return is_array($j) ? $j : [];
    }

    public static function current()
    {
        $m = self::manifest();
        return isset($m['version']) ? (string)$m['version'] : '0.0.0';
    }

    /** "owner/name" or null when the module is not linked to a repository. */
    public static function repo()
    {
        $m = self::manifest();
        $r = isset($m['repository']) ? trim((string)$m['repository']) : '';
        if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $r) || stripos($r, 'OWNER/') === 0) {
            return null;
        }
        return $r;
    }

    public static function canWrite()
    {
        $p = self::modulePath();
        return class_exists('ZipArchive') && is_dir($p) && is_writable($p) && is_writable($p.'/module.json');
    }

    protected static function okUrl($url)
    {
        $u = parse_url((string)$url);
        if (!$u || empty($u['host'])) {
            return false;
        }
        if (!self::$allowHttp && (!isset($u['scheme']) || strtolower($u['scheme']) !== 'https')) {
            return false;
        }
        return in_array(strtolower($u['host']), self::$hosts, true);
    }

    protected static function client($timeout)
    {
        return new \GuzzleHttp\Client([
            'timeout' => $timeout,
            'connect_timeout' => 6,
            'allow_redirects' => [
                'max' => 5,
                'on_redirect' => function ($req, $res, $uri) {
                    if (!Updater::okUrl((string)$uri)) {
                        throw new \RuntimeException('Redirect to an untrusted host');
                    }
                },
            ],
            'headers' => ['User-Agent' => 'Atelier-FreeScout-Updater', 'Accept' => 'application/vnd.github+json'],
        ]);
    }

    /**
     * Latest release: ['version', 'tag', 'zip', 'url', 'notes', 'checked'] or ['error', 'checked'].
     * Cached for 6 hours (30 minutes after a failure) so GitHub is not asked on every page.
     */
    public static function latest($force = false, $timeout = 5)
    {
        $repo = self::repo();
        if (!$repo) {
            return ['error' => 'no-repo', 'checked' => time()];
        }
        if (!$force) {
            $c = \Cache::get(self::CACHE_KEY);
            if (is_array($c) && isset($c['repo']) && $c['repo'] === $repo) {
                return $c;
            }
        }
        try {
            $url = self::$api.'/repos/'.$repo.'/releases/latest';
            if (!self::okUrl($url)) {
                throw new \RuntimeException('Bad API url');
            }
            $res = self::client($timeout)->get($url);
            $j = json_decode((string)$res->getBody(), true);
            if (!is_array($j) || empty($j['tag_name'])) {
                throw new \RuntimeException('No release found');
            }
            $version = ltrim((string)$j['tag_name'], 'vV');
            if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                throw new \RuntimeException('Unexpected tag '.$j['tag_name']);
            }
            $zip = null;
            foreach ((isset($j['assets']) && is_array($j['assets'])) ? $j['assets'] : [] as $a) {
                if (isset($a['name'], $a['browser_download_url']) && preg_match('/\.zip$/i', $a['name']) && self::okUrl($a['browser_download_url'])) {
                    $zip = $a['browser_download_url'];
                    break;
                }
            }
            if (!$zip && !empty($j['zipball_url']) && self::okUrl($j['zipball_url'])) {
                $zip = $j['zipball_url'];
            }
            $info = [
                'repo' => $repo,
                'version' => $version,
                'tag' => (string)$j['tag_name'],
                'zip' => $zip,
                'url' => (isset($j['html_url']) && self::okUrl($j['html_url'])) ? $j['html_url'] : 'https://github.com/'.$repo.'/releases',
                'notes' => isset($j['body']) ? mb_substr((string)$j['body'], 0, 4000) : '',
                'checked' => time(),
            ];
            \Cache::put(self::CACHE_KEY, $info, 6 * 3600);
            return $info;
        } catch (\Throwable $e) {
            $info = ['repo' => $repo, 'error' => $e->getMessage(), 'checked' => time()];
            \Cache::put(self::CACHE_KEY, $info, 1800);
            return $info;
        }
    }

    public static function hasUpdate($info)
    {
        return is_array($info) && !empty($info['version']) && !empty($info['zip']) && version_compare($info['version'], self::current(), '>');
    }

    /** Downloads the latest release and installs it. Returns ['ok' => bool, 'message' => string, 'version' => string]. */
    public static function apply()
    {
        if (!\Cache::add(self::LOCK_KEY, 1, 300)) {
            return self::fail('An update is already running.');
        }
        $tmp = null;
        try {
            if (!self::repo()) {
                return self::fail('No repository is set in module.json.');
            }
            if (!self::canWrite()) {
                return self::fail('The module folder is not writable by the web server (or the zip extension is missing). Update it by hand.');
            }
            $info = self::latest(true, 10);
            if (!self::hasUpdate($info)) {
                return self::fail(isset($info['error']) ? 'Could not reach GitHub: '.$info['error'] : 'Atelier is already up to date.');
            }
            $dir = self::workDir();
            if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
                return self::fail('Could not create '.$dir);
            }
            $tmp = $dir.'/download-'.bin2hex(random_bytes(4)).'.zip';
            $res = self::client(60)->get($info['zip'], ['sink' => $tmp, 'headers' => ['Accept' => 'application/octet-stream']]);
            if (!is_file($tmp) || filesize($tmp) < 100 || filesize($tmp) > self::MAX_ZIP) {
                return self::fail('The downloaded file is empty or too large.');
            }
            return self::installZip($tmp, $info['version']);
        } catch (\Throwable $e) {
            \Log::error('Atelier update: '.$e->getMessage());
            return self::fail('Update failed: '.$e->getMessage());
        } finally {
            if ($tmp && is_file($tmp)) {
                @unlink($tmp);
            }
            \Cache::forget(self::LOCK_KEY);
        }
    }

    protected static function fail($msg)
    {
        return ['ok' => false, 'message' => $msg, 'version' => ''];
    }

    protected static function safeName($name)
    {
        if ($name === '' || strpos($name, "\0") !== false || strpos($name, '\\') !== false || $name[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $name) || preg_match('#^[A-Za-z]:#', $name)) {
            return false;
        }
        return true;
    }

    /**
     * Checks a release zip and, if it is a newer Atelier, installs it (backup first, rollback on error).
     * $expected: version the release claims to be (the zip must say the same).
     */
    public static function installZip($zipPath, $expected = null)
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return self::fail('The downloaded file is not a valid zip.');
        }
        $work = null;
        $done = false;
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_FILES) {
                return self::fail('Unexpected number of files in the archive.');
            }
            // the shallowest module.json is the module's root
            $manifest = null;
            $depth = 99;
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                $n = $st['name'];
                if (!self::safeName($n)) {
                    return self::fail('Unsafe path in the archive: '.$n);
                }
                $total += $st['size'];
                if (basename($n) === 'module.json' && substr($n, -1) !== '/') {
                    $d = substr_count($n, '/');
                    if ($d < $depth) {
                        $depth = $d;
                        $manifest = $n;
                    }
                }
            }
            if ($total > self::MAX_UNPACKED) {
                return self::fail('The archive is too large once unpacked.');
            }
            if ($manifest === null) {
                return self::fail('No module.json in the archive.');
            }
            $m = json_decode((string)$zip->getFromName($manifest), true);
            if (!is_array($m) || !isset($m['alias']) || $m['alias'] !== 'atelier' || empty($m['version']) || !preg_match('/^\d+\.\d+\.\d+$/', (string)$m['version'])) {
                return self::fail('This archive is not an Atelier release.');
            }
            if ($expected !== null && $m['version'] !== $expected) {
                return self::fail('The archive version ('.$m['version'].') does not match the release ('.$expected.').');
            }
            if (!version_compare($m['version'], self::current(), '>')) {
                return self::fail('This version ('.$m['version'].') is not newer than the installed one ('.self::current().').');
            }
            $prefix = substr($manifest, 0, -strlen('module.json'));

            // unpack to a scratch folder, file by file (never extractTo: paths were checked above, but stay explicit)
            $work = self::workDir().'/new-'.$m['version'].'-'.bin2hex(random_bytes(3));
            $files = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = $zip->getNameIndex($i);
                if ($prefix !== '' && strpos($n, $prefix) !== 0) {
                    continue;
                }
                $rel = substr($n, strlen($prefix));
                if ($rel === '' || substr($n, -1) === '/') {
                    continue;
                }
                $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
                if (!in_array($ext, self::$exts, true)) {
                    return self::fail('File type not allowed in an update: '.$rel);
                }
                $dest = $work.'/'.$rel;
                if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0775, true)) {
                    return self::fail('Could not create a scratch folder.');
                }
                $in = $zip->getStream($n);
                $out = @fopen($dest, 'wb');
                if (!$in || !$out) {
                    return self::fail('Could not unpack '.$rel);
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
                $files[] = $rel;
            }
            foreach (['module.json', 'Providers/AtelierServiceProvider.php', 'Public/js/theme.js', 'Public/css/atelier.css'] as $need) {
                if (!in_array($need, $files, true)) {
                    return self::fail('The archive is incomplete (missing '.$need.').');
                }
            }
            $done = true;
        } finally {
            $zip->close();
            if (!$done && $work !== null) {
                self::rrmdir($work);
            }
        }

        $path = self::modulePath();
        $backup = self::backup($path);
        if ($backup === null) {
            self::rrmdir($work);
            return self::fail('Could not make a backup of the current version; nothing was changed.');
        }
        try {
            foreach ($files as $rel) {
                $to = $path.'/'.$rel;
                if (!is_dir(dirname($to)) && !@mkdir(dirname($to), 0775, true)) {
                    throw new \RuntimeException('Cannot create '.dirname($rel));
                }
                if (!@copy($work.'/'.$rel, $to)) {
                    throw new \RuntimeException('Cannot write '.$rel);
                }
            }
            // drop files that no longer exist in the new version
            foreach (self::listFiles($path) as $rel) {
                if (!in_array($rel, $files, true)) {
                    @unlink($path.'/'.$rel);
                }
            }
            self::rrmdir($work);
        } catch (\Throwable $e) {
            self::restore($backup, $path);
            self::rrmdir($work);
            return self::fail('Update failed, the previous version was restored: '.$e->getMessage());
        }
        self::afterInstall();
        return ['ok' => true, 'message' => 'Atelier '.$m['version'].' installed.', 'version' => $m['version']];
    }

    protected static function afterInstall()
    {
        try {
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            \Option::set('atelier.rev', (int)\Option::get('atelier.rev', 1) + 1);
            \Cache::forget(self::CACHE_KEY);
            if (class_exists('\Artisan')) {
                \Artisan::call('freescout:clear-cache');
            }
        } catch (\Throwable $e) {
            \Log::error('Atelier update (post): '.$e->getMessage());
        }
    }

    /** Relative paths of every file of the module. */
    protected static function listFiles($root)
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && !$f->isLink()) {
                $out[] = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            }
        }
        return $out;
    }

    protected static function backup($path)
    {
        $dir = self::workDir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return null;
        }
        $file = $dir.'/backup-'.self::current().'-'.date('Ymd-His').'.zip';
        $z = new \ZipArchive();
        if ($z->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }
        foreach (self::listFiles($path) as $rel) {
            $z->addFile($path.'/'.$rel, $rel);
        }
        if (!$z->close() || !is_file($file)) {
            return null;
        }
        // keep the three latest backups
        $all = glob($dir.'/backup-*.zip') ?: [];
        rsort($all);
        foreach (array_slice($all, 3) as $old) {
            @unlink($old);
        }
        return $file;
    }

    protected static function restore($backup, $path)
    {
        $z = new \ZipArchive();
        if ($z->open($backup) !== true) {
            return false;
        }
        for ($i = 0; $i < $z->numFiles; $i++) {
            $n = $z->getNameIndex($i);
            if (substr($n, -1) === '/' || !self::safeName($n)) {
                continue;
            }
            $to = $path.'/'.$n;
            if (!is_dir(dirname($to))) {
                @mkdir(dirname($to), 0775, true);
            }
            $in = $z->getStream($n);
            $out = @fopen($to, 'wb');
            if ($in && $out) {
                stream_copy_to_stream($in, $out);
            }
            if ($in) {
                fclose($in);
            }
            if ($out) {
                fclose($out);
            }
        }
        $names = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $names[] = $z->getNameIndex($i);
        }
        $z->close();
        // files created by the failed attempt do not belong to the previous version
        foreach (self::listFiles($path) as $rel) {
            if (!in_array($rel, $names, true)) {
                @unlink($path.'/'.$rel);
            }
        }
        return true;
    }

    protected static function rrmdir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
