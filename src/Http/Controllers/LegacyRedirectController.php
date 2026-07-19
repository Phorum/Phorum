<?php
declare(strict_types=1);

namespace Phorum\Http\Controllers;

use Phorum\Core\Config;
use Phorum\Core\Url;
use Phorum\Http\Controller;
use Phorum\Http\Request;
use Phorum\Http\Response;
use Phorum\Mapper\ForumMapper;
use Phorum\Mapper\MessageMapper;
use Twig\Environment;

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
    private readonly ForumMapper   $forums;
    private readonly MessageMapper $messages;

    public function __construct(
        Config          $config,
        Environment     $twig,
        ?ForumMapper    $forums   = null,
        ?MessageMapper  $messages = null,
    ) {
        parent::__construct($config, $twig);
        $this->forums   = $forums   ?? new ForumMapper();
        $this->messages = $messages ?? new MessageMapper();
    }

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
            return $this->redirect(Url::forum($forum_id), 301);
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
            $url = Url::forum($forum_id);
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
     *   →  /forum/{forum_id}/thread/{thread_id}[?page={n}][#msg-{message_id}]
     *
     * When a message_id is given, its containing page is resolved (for
     * forums in flat reading mode) so the deep link still lands on the right
     * page now that thread pages are paginated. Resolution failures degrade
     * gracefully to the un-paged link — never blocks the base redirect.
     */
    public function read(Request $request): Response
    {
        ['positional' => $pos] = $this->parseArgs($request->server['QUERY_STRING'] ?? '');
        $forum_id  = (int) ($pos[0] ?? 0);
        $thread_id = (int) ($pos[1] ?? 0);
        $msg_id    = (int) ($pos[2] ?? 0);

        if ($forum_id > 0 && $thread_id > 0) {
            $targetMsg = ($msg_id > 0 && $msg_id !== $thread_id) ? $msg_id : null;
            $page      = null;

            if ($targetMsg !== null) {
                $forum = $this->forums->load($forum_id);
                if ($forum !== null && !$forum->threaded_read) {
                    $perPage  = $forum->read_length ?: 25;
                    $position = $this->messages->findMessagePosition($thread_id, $targetMsg);
                    if ($position !== null) {
                        $page = max(1, (int) ceil($position / $perPage));
                    }
                }
            }

            $url = Url::thread($forum_id, $thread_id, $targetMsg, $page);
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
