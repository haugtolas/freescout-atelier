<?php

namespace Modules\Atelier\Services;

/**
 * Quiet announcements for the administrators who use Atelier (news, a request for feedback).
 *
 * - Source: the module's own news.json (shipped with each version) and, once a day, news.json from the module's
 *   GitHub repository. The only request made is that file download: nothing about the helpdesk is sent.
 * - Shown to administrators only, one small card at a time, once per item and per administrator, never more than
 *   one every few days; never on a conversation, never on a phone. The text is plain text, never HTML.
 * - Turn it off in Settings > Atelier > Announcements (nothing is downloaded and nothing is shown).
 */
class News
{
    const FEED_KEY = 'atelier.news.feed';
    const FETCH_EVERY = 86400;     // one download a day
    const RETRY_AFTER = 21600;     // 6 hours after a failure
    const MIN_GAP = 259200;        // at most one card every 3 days per administrator
    const MAX_BYTES = 65536;

    /** Where the feed comes from; only changed by tests. */
    public static $base = 'https://raw.githubusercontent.com';
    /** Tests only: accept plain http. */
    public static $allowHttp = false;

    public static function enabled()
    {
        return \Modules\Atelier\Providers\AtelierServiceProvider::opt('news', '1') === '1';
    }

    protected static function repo()
    {
        return Updater::repo();
    }

    /** Option::get() already turns a stored JSON string into an array */
    protected static function read($key)
    {
        $v = \Option::get($key, null);
        if (!is_array($v)) {
            $v = json_decode((string)$v, true);
        }
        return is_array($v) ? $v : [];
    }

    protected static function pickText($v, $max)
    {
        if (is_array($v)) {
            $loc = strtolower(substr((string)app()->getLocale(), 0, 2));
            $v = isset($v[$loc]) ? $v[$loc] : (isset($v['en']) ? $v['en'] : (count($v) ? reset($v) : ''));
        }
        $v = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$v)));
        return mb_strlen($v) > $max ? rtrim(mb_substr($v, 0, $max - 1)).'…' : $v;
    }

    /** One raw entry -> a clean entry, or null when it is not valid / not for this installation / expired */
    public static function clean($row, $version = null)
    {
        if (!is_array($row) || empty($row['id']) || !preg_match('/^[A-Za-z0-9._-]{1,40}$/', (string)$row['id'])) {
            return null;
        }
        $version = $version ?: Updater::current();
        if (!empty($row['min_version']) && version_compare($version, (string)$row['min_version'], '<')) {
            return null;
        }
        if (!empty($row['max_version']) && version_compare($version, (string)$row['max_version'], '>')) {
            return null;
        }
        $date = (isset($row['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$row['date'])) ? (string)$row['date'] : '';
        if (!empty($row['until']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$row['until']) && (string)$row['until'] < date('Y-m-d')) {
            return null;
        }
        $title = self::pickText(isset($row['title']) ? $row['title'] : '', 80);
        $body = self::pickText(isset($row['body']) ? $row['body'] : '', 400);
        if ($title === '') {
            return null;
        }
        $url = '';
        if (!empty($row['url'])) {
            $u = parse_url((string)$row['url']);
            if (self::allowedLink($u)) {
                $url = (string)$row['url'];
            }
        }
        return [
            'id'    => (string)$row['id'],
            'kind'  => (isset($row['kind']) && $row['kind'] === 'feedback') ? 'feedback' : 'news',
            'date'  => $date,
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
        ];
    }

    /** Hosts an announcement may link to: the project on GitHub and common survey / form services */
    public static $linkHosts = ['github.com', 'tally.so', 'forms.gle', 'framaforms.org', 'framadate.org', 'forms.office.com', 'jotform.com', 'www.jotform.com', 'surveymonkey.com', 'www.surveymonkey.com', 'typeform.com'];
    public static $linkSuffixes = ['.typeform.com', '.notion.site'];

    public static function allowedLink($u)
    {
        if (!is_array($u) || !isset($u['scheme'], $u['host']) || strtolower($u['scheme']) !== 'https' || isset($u['user']) || isset($u['port'])) {
            return false;
        }
        $h = strtolower($u['host']);
        if ($h === 'docs.google.com') {
            return strpos(isset($u['path']) ? $u['path'] : '', '/forms/') === 0;
        }
        if (in_array($h, self::$linkHosts, true)) {
            return true;
        }
        foreach (self::$linkSuffixes as $suf) {
            if (substr($h, -strlen($suf)) === $suf) {
                return true;
            }
        }
        return false;
    }

    protected static function bundled()
    {
        $j = json_decode((string)@file_get_contents(dirname(__DIR__).'/news.json'), true);
        return (is_array($j) && isset($j['items']) && is_array($j['items'])) ? $j['items'] : [];
    }

    /** All valid entries, newest first: shipped with the module + the last downloaded feed */
    public static function items()
    {
        $feed = self::read(self::FEED_KEY);
        $rows = array_merge(self::bundled(), isset($feed['items']) && is_array($feed['items']) ? $feed['items'] : []);
        $out = [];
        foreach ($rows as $row) {
            $c = self::clean($row);
            if ($c && !isset($out[$c['id']])) {
                $out[$c['id']] = $c;
            }
        }
        $out = array_values($out);
        usort($out, function ($a, $b) { return strcmp($b['date'], $a['date']); });
        return $out;
    }

    public static function recent($n = 6)
    {
        return array_slice(self::items(), 0, $n);
    }

    protected static function stateKey($uid)
    {
        return 'atelier.news_seen.'.(int)$uid;
    }

    /** The one entry this administrator should be shown now, or null */
    public static function pending($uid)
    {
        if (!self::enabled() || !$uid) {
            return null;
        }
        $st = self::read(self::stateKey($uid));
        $seen = isset($st['ids']) && is_array($st['ids']) ? $st['ids'] : [];
        $last = isset($st['shown']) ? (int)$st['shown'] : 0;
        if ($last && time() - $last < self::MIN_GAP) {
            return null;
        }
        foreach (self::items() as $it) {
            // an announcement more than 45 days old is no longer pushed (it stays in Settings > Atelier)
            if ($it['date'] !== '' && $it['date'] < date('Y-m-d', time() - 45 * 86400)) {
                continue;
            }
            if (!in_array($it['id'], $seen, true)) {
                return $it;
            }
        }
        return null;
    }

    public static function markSeen($uid, $id)
    {
        if (!$uid || !preg_match('/^[A-Za-z0-9._-]{1,40}$/', (string)$id)) {
            return;
        }
        $st = self::read(self::stateKey($uid));
        $seen = isset($st['ids']) && is_array($st['ids']) ? $st['ids'] : [];
        $add = [(string)$id];
        // showing the newest card also retires the older ones, so nobody gets a backlog of announcements
        $all = self::items();
        foreach ($all as $it) {
            if ($it['id'] === (string)$id) {
                foreach ($all as $o) {
                    if ($o['date'] !== '' && $it['date'] !== '' && strcmp($o['date'], $it['date']) <= 0) {
                        $add[] = $o['id'];
                    }
                }
                break;
            }
        }
        $seen = array_values(array_unique(array_merge($seen, $add)));
        \Option::set(self::stateKey($uid), json_encode(['ids' => array_slice($seen, -60), 'shown' => time()]));
    }

    public static function url()
    {
        $r = self::repo();
        return $r ? rtrim(self::$base, '/').'/'.$r.'/main/news.json' : null;
    }

    /** Download the feed when it is older than a day. Called after the page has been sent, so nobody waits for it. */
    public static function refreshIfStale()
    {
        try {
            if (!self::enabled() || !function_exists('curl_init')) {
                return;
            }
            $url = self::url();
            if (!$url) {
                return;
            }
            $feed = self::read(self::FEED_KEY);
            $at = isset($feed['at']) ? (int)$feed['at'] : 0;
            if ($at && time() - $at < self::FETCH_EVERY) {
                return;
            }
            // a failed try is only repeated after a few hours, a successful one overwrites this
            $keep = isset($feed['items']) && is_array($feed['items']) ? $feed['items'] : [];
            \Option::set(self::FEED_KEY, json_encode(['at' => time() - (self::FETCH_EVERY - self::RETRY_AFTER), 'items' => $keep]));
            $body = self::download($url);
            if ($body === null) {
                return;
            }
            $j = json_decode($body, true);
            if (!is_array($j) || !isset($j['items']) || !is_array($j['items'])) {
                return;
            }
            $items = [];
            foreach (array_slice($j['items'], 0, 30) as $row) {
                if (is_array($row) && self::clean($row, '999.0.0') !== null) {
                    $items[] = $row;
                }
            }
            \Option::set(self::FEED_KEY, json_encode(['at' => time(), 'items' => $items]));
        } catch (\Throwable $e) {
            \Modules\Atelier\Providers\AtelierServiceProvider::fail($e);
        }
    }

    protected static function download($url)
    {
        $u = parse_url($url);
        if (!$u || empty($u['host']) || (!self::$allowHttp && strtolower((string)$u['scheme']) !== 'https')) {
            return null;
        }
        $buf = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT      => 'Atelier-FreeScout',
            CURLOPT_PROTOCOLS      => self::$allowHttp ? (CURLPROTO_HTTPS | CURLPROTO_HTTP) : CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION  => function ($c, $data) use (&$buf) {
                $buf .= $data;
                return strlen($buf) > self::MAX_BYTES ? 0 : strlen($data);
            },
        ]);
        $ok = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($ok !== false && $code === 200 && $buf !== '') ? $buf : null;
    }
}
