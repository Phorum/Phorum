<?php
declare(strict_types=1);

/**
 * Dutch (Netherlands) language strings for Phorum.
 * Machine-translated — please review with a native speaker.
 *
 * Keys use dot-notation namespaces (e.g. 'nav.search').
 * Dynamic values use {placeholder} syntax in the string value;
 * pass an array of replacements as the second argument to trans().
 *
 * Placeholders such as {subject} and {author} may be repositioned
 * within the translated sentence but must remain verbatim.
 */
return [

    // Metadata (not rendered as UI text)
    '_name' => 'Nederlands',

    // -------------------------------------------------------------------------
    // Navigation
    // -------------------------------------------------------------------------
    'nav.forum_index'   => 'Forumoverzicht',
    'nav.search'        => 'Zoeken',
    'nav.messages'      => 'Berichten',
    'nav.settings'      => 'Instellingen',
    'nav.log_out'       => 'Uitloggen',
    'nav.log_in'        => 'Inloggen',
    'nav.register'      => 'Registreren',
    'nav.powered_by'    => 'Mogelijk gemaakt door Phorum',
    'nav.menu'          => 'Menu',

    // -------------------------------------------------------------------------
    // Forum list (index page)
    // -------------------------------------------------------------------------
    'forum_list.no_forums'   => 'Er zijn nog geen forums aangemaakt.',
    'forum_list.col_forum'   => 'Forum',
    'forum_list.col_posts'   => 'Berichten',
    'forum_list.col_threads' => 'Discussies',
    'forum_list.col_last'    => 'Laatste bericht',

    // -------------------------------------------------------------------------
    // Forum (thread listing)
    // -------------------------------------------------------------------------
    'forum.new_thread'       => 'Nieuwe discussie',
    'forum.no_threads'       => 'Nog geen discussies.',
    'forum.start_one'        => 'Start er een.',
    'forum.col_subject'      => 'Onderwerp',
    'forum.col_author'       => 'Auteur',
    'forum.col_replies'      => 'Reacties',
    'forum.col_last_post'    => 'Laatste bericht',
    'forum.sticky'           => 'Vastgezet',
    'forum.closed'           => 'Gesloten',
    'forum.by'               => 'door',
    'forum.new'              => 'nieuw',
    'forum.mark_read'        => 'Alles markeren als gelezen',

    // -------------------------------------------------------------------------
    // Thread view
    // -------------------------------------------------------------------------
    'thread.reply'           => 'Reageren',
    'thread.follow'          => 'Volgen',
    'thread.following'       => 'Gevolgd',
    'thread.reopen'          => 'Heropenen',
    'thread.close'           => 'Sluiten',
    'thread.move'            => 'Verplaatsen',
    'thread.delete'          => 'Discussie verwijderen',

    // -------------------------------------------------------------------------
    // Individual message
    // -------------------------------------------------------------------------
    'message.awaiting_approval' => 'Wacht op goedkeuring',
    'message.reply'             => 'Reageren',
    'message.edit'              => 'Bewerken',
    'message.edit_title'        => 'Bericht bewerken',
    'message.save_edit'         => 'Wijzigingen opslaan',
    'message.edited_note'       => 'Bewerkt',
    'message.changes'           => 'Changes',
    'message.changes_title'     => 'Edit History',
    'message.changes_edit_n'    => 'Edit #{n}',
    'message.changes_by'        => 'by',
    'message.changes_subject'   => 'Subject',
    'message.changes_body'      => 'Message',
    'message.changes_back'      => 'Back to thread',
    'message.approve'           => 'Goedkeuren',
    'message.delete'            => 'Verwijderen',

    // -------------------------------------------------------------------------
    // Post / reply form
    // -------------------------------------------------------------------------
    'post.new_thread'        => 'Nieuwe discussie',
    'post.reply_to'          => 'Reactie op {subject}',
    'post.reply'             => 'Reageren',
    'post.subject'           => 'Onderwerp',
    'post.body'              => 'Bericht',
    'post.submit_thread'     => 'Discussie plaatsen',
    'post.submit_reply'      => 'Reactie plaatsen',
    'post.cancel'            => 'Annuleren',

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------
    'auth.login_title'       => 'Inloggen',
    'auth.username'          => 'Gebruikersnaam',
    'auth.password'          => 'Wachtwoord',
    'auth.remember_me'       => 'Onthoud mij',
    'auth.login_submit'      => 'Inloggen',
    'auth.create_account'    => 'Maak een account aan',
    'auth.register_title'    => 'Account aanmaken',
    'auth.email'             => 'E-mailadres',
    'auth.password_hint'     => 'minimaal 6 tekens',
    'auth.confirm_password'  => 'Wachtwoord bevestigen',
    'auth.register_submit'   => 'Account aanmaken',
    'auth.have_account'      => 'Al een account?',
    'auth.login_link'        => 'Log in.',
    'auth.forgot_password'   => 'Wachtwoord vergeten?',
    'auth.forgot_title'      => 'Wachtwoord opnieuw instellen',
    'auth.forgot_email_label' => 'E-mailadres',
    'auth.forgot_submit'     => 'Resetlink versturen',
    'auth.forgot_sent'       => 'Als dat e-mailadres geregistreerd is, is er een resetlink verstuurd. Controleer uw inbox.',
    'auth.reset_title'       => 'Kies een nieuw wachtwoord',
    'auth.reset_new_password' => 'Nieuw wachtwoord',
    'auth.reset_confirm'     => 'Nieuw wachtwoord bevestigen',
    'auth.reset_submit'      => 'Nieuw wachtwoord instellen',
    'auth.reset_invalid'     => 'Deze resetlink voor het wachtwoord is ongeldig of verlopen. Vraag een nieuwe aan.',
    'auth.reset_success'     => 'Uw wachtwoord is bijgewerkt. U bent nu ingelogd.',
    'auth.confirm_pending_title'  => 'Controleer uw e-mail',
    'auth.confirm_pending_body'   => 'We hebben een bevestigingslink gestuurd naar {email}. Klik erop om uw account te activeren.',
    'auth.confirm_pending_resend' => 'Bevestigings-e-mail opnieuw versturen',
    'auth.confirm_invalid'        => 'Deze bevestigingslink is ongeldig of verlopen.',
    'auth.resend_title'           => 'Bevestigings-e-mail opnieuw versturen',
    'auth.resend_email_label'     => 'E-mailadres',
    'auth.resend_submit'          => 'Opnieuw versturen',
    'auth.resend_sent'            => 'Als dat adres een openstaande bevestiging heeft, is er een nieuwe link verstuurd. Controleer uw inbox.',

    // -------------------------------------------------------------------------
    // User profile
    // -------------------------------------------------------------------------
    'profile.username'       => 'Gebruikersnaam',
    'profile.name'           => 'Naam',
    'profile.email'          => 'E-mail',
    'profile.joined'         => 'Lid sinds',
    'profile.posts'          => 'Berichten',
    'profile.last_active'    => 'Laatst actief',
    'profile.signature'      => 'Handtekening',
    'profile.recent_posts'   => 'Recente berichten',
    'profile.col_subject'    => 'Onderwerp',
    'profile.col_date'       => 'Datum',
    'profile.edit_settings'  => 'Instellingen bewerken',

    // -------------------------------------------------------------------------
    // User settings
    // -------------------------------------------------------------------------
    'settings.title'             => 'Accountinstellingen',
    'settings.saved'             => 'Uw instellingen zijn opgeslagen.',
    'settings.identity'          => 'Identiteit',
    'settings.display_name'      => 'Weergavenaam',
    'settings.email'             => 'E-mailadres',
    'settings.hide_email'        => 'Verberg mijn e-mailadres in mijn profiel',
    'settings.password_section'  => 'Wachtwoord',
    'settings.password_hint'     => 'Laat leeg om uw huidige wachtwoord te behouden.',
    'settings.new_password'      => 'Nieuw wachtwoord',
    'settings.confirm_password'  => 'Nieuw wachtwoord bevestigen',
    'settings.signature_section' => 'Handtekening',
    'settings.signature_text'    => 'Handtekeningtekst',
    'settings.show_signature'    => 'Handtekening weergeven onder mijn berichten',
    'settings.preferences'       => 'Voorkeuren',
    'settings.threaded_list'     => 'Gebruik de discussieweergave voor forumlijsten',
    'settings.threaded_read'     => 'Gebruik de discussieweergave bij het lezen van threads',
    'settings.email_notify'      => 'Stuur mij een e-mail bij nieuwe berichten in forums waarop ik ben geabonneerd',
    'settings.pm_email_notify'   => 'Stuur mij een e-mail wanneer ik een privébericht ontvang',
    'settings.tz_offset'         => 'Tijdzoneverschuiving (uren, -12 t/m +14; -99 = servertijd)',
    'settings.save'              => 'Instellingen opslaan',
    'settings.cancel'            => 'Annuleren',

    // -------------------------------------------------------------------------
    // Private messages
    // -------------------------------------------------------------------------
    'pm.private_messages'    => 'Privéberichten',
    'pm.folders'             => 'Mappen',
    'pm.inbox'               => 'Postvak IN',
    'pm.outbox'              => 'Postvak UIT',
    'pm.compose'             => 'Nieuw bericht',
    'pm.manage_folders'      => 'Mappen beheren',
    'pm.no_messages'         => 'Geen berichten.',
    'pm.col_subject'         => 'Onderwerp',
    'pm.col_from'            => 'Van',
    'pm.col_to'              => 'Aan',
    'pm.col_date'            => 'Datum',
    'pm.delete'              => 'Verwijderen',
    'pm.compose_title'       => 'Bericht opstellen',
    'pm.to_label'            => 'Aan (gebruikersnaam)',
    'pm.subject'             => 'Onderwerp',
    'pm.body'                => 'Bericht',
    'pm.send'                => 'Verzenden',
    'pm.cancel'              => 'Annuleren',
    'pm.reply'               => 'Reageren',
    'pm.back_to_inbox'       => 'Terug naar postvak IN',
    'pm.move_to_folder'      => 'Verplaatsen naar map\u{2026}',
    'pm.move'                => 'Verplaatsen',
    'pm.delete_title'        => 'Privébericht verwijderen',
    'pm.delete_confirm'      => '"{subject}" van {author} verwijderen?',
    'pm.create_folder_title' => 'Map aanmaken',
    'pm.folder_name'         => 'Mapnaam',
    'pm.create'              => 'Aanmaken',
    'pm.buddy_list'          => 'Buddy List',
    'pm.no_buddies'          => 'You have no buddies yet.',
    'pm.add_buddy'           => 'Add Buddy',
    'pm.remove_buddy'        => 'Remove Buddy',
    'pm.mutual'              => 'Mutual',
    'pm.col_buddy'           => 'User',
    'pm.col_mutual'          => 'Mutual',
    'pm.col_last_active'     => 'Last Active',

    // -------------------------------------------------------------------------
    // Thread subscriptions
    // -------------------------------------------------------------------------
    'sub.title'              => 'Discussie volgen',
    'sub.following_email'    => 'U volgt deze discussie momenteel en ontvangt e-mailmeldingen voor nieuwe reacties.',
    'sub.bookmarked'         => 'U heeft deze discussie als bladwijzer opgeslagen (geen e-mailmeldingen).',
    'sub.not_following'      => 'U volgt deze discussie niet.',
    'sub.follow_email'       => 'Volgen en e-mail ontvangen bij nieuwe reacties',
    'sub.bookmark'           => 'Bladwijzer (geen e-mails)',
    'sub.unfollow'           => 'Ontvolgen',
    'sub.back_to_thread'     => 'Terug naar discussie',
    'sub.follow'             => 'Volgen',

    // -------------------------------------------------------------------------
    // Moderation
    // -------------------------------------------------------------------------
    'mod.delete_thread'            => 'Discussie verwijderen',
    'mod.delete_message'           => 'Bericht verwijderen',
    'mod.approve_message'          => 'Bericht goedkeuren',
    'mod.close_thread'             => 'Discussie sluiten',
    'mod.reopen_thread'            => 'Discussie heropenen',
    'mod.delete_thread_confirm'    => 'Weet u zeker dat u de discussie "{subject}" en alle reacties definitief wilt verwijderen? Dit kan niet ongedaan worden gemaakt.',
    'mod.delete_message_confirm'   => 'Weet u zeker dat u dit bericht van {author} wilt verwijderen? De reacties worden gekoppeld aan het vorige bericht in de discussie.',
    'mod.approve_confirm'          => 'Het volgende bericht van {author} goedkeuren zodat het zichtbaar wordt voor alle lezers?',
    'mod.close_confirm'            => 'De discussie "{subject}" sluiten? Nieuwe reacties zijn niet meer mogelijk als de discussie gesloten is.',
    'mod.open_confirm'             => 'De discussie "{subject}" heropenen zodat leden weer nieuwe reacties kunnen plaatsen?',
    'mod.yes_delete'               => 'Ja, verwijderen',
    'mod.approve'                  => 'Goedkeuren',
    'mod.close'                    => 'Discussie sluiten',
    'mod.reopen'                   => 'Discussie heropenen',
    'mod.cancel'                   => 'Annuleren',
    'mod.move_title'               => 'Discussie verplaatsen',
    'mod.move_prompt'              => '"{subject}" verplaatsen naar een ander forum:',
    'mod.destination'              => 'Doelforum',
    'mod.choose_forum'             => '\u{2014} kies een forum \u{2014}',
    'mod.move_submit'              => 'Discussie verplaatsen',

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------
    'search.title'           => 'Zoeken',
    'search.messages_label'  => 'Berichten zoeken',
    'search.author'          => 'Auteur',
    'search.match_type'      => 'Zoektype',
    'search.all_words'       => 'Alle woorden',
    'search.any_word'        => 'Een van de woorden',
    'search.exact_phrase'    => 'Exacte zin',
    'search.posted_within'   => 'Geplaatst binnen',
    'search.last_30'         => 'Afgelopen 30 dagen',
    'search.last_90'         => 'Afgelopen 90 dagen',
    'search.last_year'       => 'Afgelopen jaar',
    'search.any_time'        => 'Altijd',
    'search.threads_only'    => 'Alleen threadstarters',
    'search.forums_label'    => 'Forums',
    'search.all_forums'      => 'Alle forums',
    'search.submit'          => 'Zoeken',
    'search.no_results'      => 'Geen resultaten gevonden.',
    'search.showing'         => 'Weergave',
    'search.of'              => 'van',
    'search.result'          => 'resultaat',
    'search.results'         => 'resultaten',
    'search.col_subject'     => 'Onderwerp',
    'search.col_author'      => 'Auteur',
    'search.col_forum'       => 'Forum',
    'search.col_date'        => 'Datum',

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------
    'error.404_title'        => 'Pagina niet gevonden',
    'error.404_message'      => 'De pagina die u heeft opgevraagd bestaat niet.',
    'error.404_return'       => 'Terug naar het forumoverzicht.',
    'error.403_title'        => 'Toegang geweigerd',
    'error.403_message'      => 'U heeft geen toestemming om dit forum te bekijken.',
    'error.403_login'        => 'Log in',
    'error.403_login_hint'   => 'om forums te bekijken waarvoor registratie vereist is.',
    'error.403_return'       => 'Terug naar forumoverzicht',

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
