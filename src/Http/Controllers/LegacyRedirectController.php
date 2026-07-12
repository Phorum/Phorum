<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;

/**
 * Issues 301 redirects for Phorum 6 legacy URLs.
 *
 * Phorum 6 encodes parameters as comma-separated positional values in the
 * query string (e.g. read.php?1,2,3), NOT standard key=value pairs.
 * The first positional arg is always the forum_id; subsequent args depend
 * on the page type.
 */
class LegacyRedirectController extends Controller
{
    /**
     * index.php[?{forum_id}]  →  /forum/{forum_id}, or / with no forum_id.
     *
     * Phorum 6 used index.php for both the forum index and, given a
     * folder's forum_id, a view scoped to that folder — both now live at
     * the same /forum/{id} route (which dispatches on folder_flag).
     */
    public function index(Request $request): Response
    {
        ['positional' => $pos] = $this->parseArgs($request->server['QUERY_STRING'] ?? '');
        $forum_id = (int) ($pos[0] ?? 0);

        if ($forum_id > 0) {
            return $this->redirect("/forum/{$forum_id}", 301);
        } else {
            return $this->redirect('/', 301);
        }
    }

    /**
     * list.php?{forum_id}[,page={n}]  →  /forum/{forum_id}[?page={n}]
     */
    public function list(Request $request): Response
    {
        ['positional' => $pos, 'named' => $named] = $this->parseArgs($request->server['QUERY_STRING'] ?? '');
        $forum_id = (int) ($pos[0] ?? 0);

        if ($forum_id > 0) {
            $url = "/forum/{$forum_id}";
            if (isset($named['page']) && (int) $named['page'] > 1) {
                $url .= '?page=' . (int) $named['page'];
            }
            return $this->redirect($url, 301);
        } else {
            return $this->redirect('/', 301);
        }
    }

    /**
     * read.php?{forum_id},{thread_id}[,{message_id}]
     *   →  /forum/{forum_id}/thread/{thread_id}[#msg-{message_id}]
     */
    public function read(Request $request): Response
    {
        ['positional' => $pos] = $this->parseArgs($request->server['QUERY_STRING'] ?? '');
        $forum_id  = (int) ($pos[0] ?? 0);
        $thread_id = (int) ($pos[1] ?? 0);
        $msg_id    = (int) ($pos[2] ?? 0);

        if ($forum_id > 0 && $thread_id > 0) {
            $url = "/forum/{$forum_id}/thread/{$thread_id}";
            if ($msg_id > 0 && $msg_id !== $thread_id) {
                $url .= "#msg-{$msg_id}";
            }
            return $this->redirect($url, 301);
        } else {
            return $this->redirect('/', 301);
        }
    }

    /**
     * profile.php?{forum_id},{user_id}  →  /user/{user_id}
     */
    public function profile(Request $request): Response
    {
        ['positional' => $pos] = $this->parseArgs($request->server['QUERY_STRING'] ?? '');
        $user_id = (int) ($pos[1] ?? 0);

        if ($user_id > 0) {
            return $this->redirect("/user/{$user_id}", 301);
        } else {
            return $this->redirect('/', 301);
        }
    }

    /**
     * Split the raw query string on commas into positional (numeric) values
     * and named (key=value) pairs.
     *
     * Example: "5,123,page=2" → positional=[5,123], named=['page'=>'2']
     *
     * @return array{positional: string[], named: array<string, string>}
     */
    private function parseArgs(string $queryString): array
    {
        $qs = $queryString;
        if ($qs === '') {
            return ['positional' => [], 'named' => []];
        }

        $positional = [];
        $named      = [];
        foreach (explode(',', $qs) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (str_contains($part, '=')) {
                [$key, $val] = explode('=', $part, 2);
                $named[trim($key)] = trim($val);
            } elseif (is_numeric($part)) {
                $positional[] = $part;
            }
        }
        return ['positional' => $positional, 'named' => $named];
    }
}
