<?php
declare(strict_types=1);

/**
 * Canadian French (fr-CA) regional overrides for Phorum.
 * Only keys that differ from lang/fr.php are listed here.
 * All other strings fall back to lang/fr.php, then lang/en.php.
 *
 * Primary difference: Quebec French uses "courriel" (the Office québécois de
 * la langue française recommended term) everywhere European French uses
 * "e-mail" or "mail".
 */
return [
    '_name' => 'Français (Canada)',

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------

    // fr.php: 'Adresse e-mail'
    'auth.email' => 'Adresse courriel',

    // -------------------------------------------------------------------------
    // User profile
    // -------------------------------------------------------------------------

    // fr.php: 'E-mail'
    'profile.email' => 'Courriel',

    // -------------------------------------------------------------------------
    // User settings
    // -------------------------------------------------------------------------

    // fr.php: 'Adresse e-mail'
    'settings.email' => 'Adresse courriel',

    // fr.php: 'Masquer mon adresse e-mail sur mon profil'
    'settings.hide_email' => 'Masquer mon adresse courriel sur mon profil',

    // fr.php: 'M\'envoyer un e-mail lorsque de nouveaux messages sont publiés dans les forums auxquels je suis abonné'
    'settings.email_notify' => 'M\'envoyer un courriel lorsque de nouveaux messages sont publiés dans les forums auxquels je suis abonné',

    // fr.php: 'M\'envoyer un e-mail lorsque je reçois un message privé'
    'settings.pm_email_notify' => 'M\'envoyer un courriel lorsque je reçois un message privé',

    // -------------------------------------------------------------------------
    // Thread subscriptions
    // -------------------------------------------------------------------------

    // fr.php: '…recevrez des notifications par e-mail pour les nouvelles réponses.'
    'sub.following_email' => 'Vous suivez actuellement ce fil et recevrez des notifications par courriel pour les nouvelles réponses.',

    // fr.php: '…(sans notifications par e-mail).'
    'sub.bookmarked' => 'Vous avez mis ce fil en favori (sans notifications par courriel).',

    // fr.php: 'Suivre et recevoir des e-mails pour les nouvelles réponses'
    'sub.follow_email' => 'Suivre et recevoir des courriels pour les nouvelles réponses',

    // fr.php: 'Mettre en favori (sans e-mails)'
    'sub.bookmark' => 'Mettre en favori (sans courriels)',

    // -------------------------------------------------------------------------
    // Attachments
    // -------------------------------------------------------------------------
    'attachment.label'      => 'Attachments',
    'attachment.add'        => 'Add files',
    'attachment.existing'   => 'Existing attachments',
    'attachment.remove'     => 'Remove',
    'attachment.hint_count' => 'Up to {n} file(s).',
    'attachment.hint_size'  => 'Max {size} per file.',
];
