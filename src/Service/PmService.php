<?php
declare(strict_types=1);

namespace Phorum\Service;

use Phorum\Core\Config;
use Phorum\Core\SiteSettings;
use Phorum\Mapper\PmFolderMapper;
use Phorum\Mapper\PmMessageMapper;
use Phorum\Mapper\PmXrefMapper;
use Phorum\Mapper\UserMapper;
use Phorum\Model\MessageMeta;
use Phorum\Model\PmFolder;
use Phorum\Model\PmMessage;
use Phorum\Model\PmXref;

class PmService
{
    public function __construct(
        private readonly PmMessageMapper $messages,
        private readonly PmXrefMapper    $xrefs,
        private readonly PmFolderMapper  $folders,
        private readonly UserMapper      $users,
        private readonly MailService     $mailer,
        private readonly Config          $config,
    ) {
    }

    /**
     * Send a PM from one user to one or more recipients.
     * Creates one pm_messages row, one inbox xref per recipient (plus outbox
     * xref for the sender), and increments pm_new_count on each recipient.
     */
    public function send(
        int    $fromUserId,
        string $author,
        array  $toUserIds,
        string $subject,
        string $body,
    ): PmMessage {
        $hookData = phorum_api_hook('pm_before_send', [
            'from_user_id' => $fromUserId,
            'author'       => $author,
            'to_user_ids'  => $toUserIds,
            'subject'      => $subject,
            'body'         => $body,
        ]);
        if (is_array($hookData)) {
            $subject    = $hookData['subject']      ?? $subject;
            $body       = $hookData['body']         ?? $body;
            $toUserIds  = $hookData['to_user_ids']  ?? $toUserIds;
        }

        $msg                = new PmMessage();
        $msg->user_id       = $fromUserId;
        $msg->author        = $author;
        $msg->subject       = $subject;
        $msg->message       = $body;
        $msg->datestamp     = time();

        // Load all recipients up front — skip IDs that don't exist
        $recipients    = [];
        $recipientMeta = [];
        foreach ($toUserIds as $uid) {
            $recipient = $this->users->load($uid);
            if ($recipient !== null) {
                $recipients[$uid] = $recipient;
                $recipientMeta[]  = [
                    'user_id'  => $recipient->user_id,
                    'username' => $recipient->display_name !== ''
                        ? $recipient->display_name
                        : $recipient->username,
                ];
            }
        }
        $msg->meta = MessageMeta::fromArray([
            'recipients' => $recipientMeta,
            'format'     => 'markdown',
        ])->encode();

        $msg = $this->messages->save($msg);

        // Inbox xref and notification for each verified recipient
        foreach ($recipients as $recipient) {
            $xref                 = new PmXref();
            $xref->user_id        = $recipient->user_id;
            $xref->pm_message_id  = $msg->pm_message_id;
            $xref->pm_folder_id   = 0;
            $xref->special_folder = 'inbox';
            $xref->read_flag      = 0;
            $xref->reply_flag     = 0;
            $this->xrefs->save($xref);

            $this->users->incrementNewPmCount($recipient->user_id);

            if ($recipient->pm_email_notify) {
                $siteName = SiteSettings::name();
                $this->mailer->send(
                    toAddress: $recipient->email,
                    toName:    $recipient->display_name !== ''
                        ? $recipient->display_name : $recipient->username,
                    subject: "[{$siteName}] New private message from {$author}",
                    body:    "You have a new private message from {$author}.\n\n"
                           . "Subject: {$subject}\n\n"
                           . "Log in to read it.",
                );
            }
        }

        // Outbox xref for sender
        $outbox                 = new PmXref();
        $outbox->user_id        = $fromUserId;
        $outbox->pm_message_id  = $msg->pm_message_id;
        $outbox->pm_folder_id   = 0;
        $outbox->special_folder = 'outbox';
        $outbox->read_flag      = 1; // sender has "read" their own sent message
        $outbox->reply_flag     = 0;
        $this->xrefs->save($outbox);

        phorum_api_hook('pm_sent', $msg);

        return $msg;
    }

    /**
     * Return messages in inbox or outbox as an array of raw rows
     * (merged xref + message fields).
     */
    public function listFolder(int $userId, string $specialFolder = 'inbox'): array
    {
        $rows = $this->xrefs->listBySpecialFolder($userId, $specialFolder);
        return phorum_api_hook('pm_list', $rows) ?? $rows;
    }

    /**
     * Return messages in a custom folder as an array of raw rows.
     */
    public function listCustomFolder(int $userId, int $pmFolderId): array
    {
        $rows = $this->xrefs->listByCustomFolder($userId, $pmFolderId);
        return phorum_api_hook('pm_list', $rows) ?? $rows;
    }

    /**
     * Load a message for reading. Verifies the xref belongs to $userId,
     * marks it read, and decrements pm_new_count if it was unread.
     * Returns ['xref' => PmXref, 'message' => PmMessage] or null on failure.
     */
    public function getMessage(int $pmXrefId, int $userId): ?array
    {
        $xref = $this->xrefs->findForUser($pmXrefId, $userId);
        if ($xref === null) {
            return null;
        }

        $message = $this->messages->load($xref->pm_message_id);
        if ($message === null) {
            return null;
        }

        if ($xref->read_flag === 0) {
            $this->xrefs->markRead($pmXrefId);
            $this->users->decrementNewPmCount($userId);
        }

        $message = phorum_api_hook('pm_read', $message);

        return ['xref' => $xref, 'message' => $message];
    }

    /**
     * Delete a user's copy of a PM (removes the xref row only).
     */
    public function delete(int $pmXrefId, int $userId): bool
    {
        $xref = $this->xrefs->findForUser($pmXrefId, $userId);
        if ($xref === null) {
            return false;
        }
        phorum_api_hook('pm_delete', $xref);
        // Decrement unread count if the message was never read
        if ($xref->read_flag === 0) {
            $this->users->decrementNewPmCount($userId);
        }
        return $this->xrefs->delete($pmXrefId);
    }

    /**
     * Move a PM to a custom folder.
     */
    public function move(int $pmXrefId, int $userId, int $pmFolderId): bool
    {
        $xref = $this->xrefs->findForUser($pmXrefId, $userId);
        if ($xref === null) {
            return false;
        }
        // Verify folder ownership
        $folder = $this->folders->load($pmFolderId);
        if ($folder === null || $folder->user_id !== $userId) {
            return false;
        }
        $this->xrefs->moveToFolder($pmXrefId, $pmFolderId);
        return true;
    }

    /** Return all custom folders for a user. */
    public function listFolders(int $userId): array
    {
        return $this->folders->findByUser($userId);
    }

    /** Create a new custom folder for a user. */
    public function createFolder(int $userId, string $name): PmFolder
    {
        $folder             = new PmFolder();
        $folder->user_id    = $userId;
        $folder->foldername = $name;
        return $this->folders->save($folder);
    }

    /**
     * Delete a custom folder. Messages in the folder are moved to inbox first.
     */
    public function deleteFolder(int $pmFolderId, int $userId): bool
    {
        $folder = $this->folders->load($pmFolderId);
        if ($folder === null || $folder->user_id !== $userId) {
            return false;
        }
        $this->xrefs->moveAllToInbox($userId, $pmFolderId);
        return $this->folders->delete($pmFolderId);
    }
}
