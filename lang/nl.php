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
    'nav.skip_to_content' => 'Naar de hoofdinhoud',
    'nav.breadcrumb'    => 'Kruimelpad',
    'nav.primary'       => 'Hoofdnavigatie',
    'nav.menu'          => 'Menu',
    'pagination.nav_label' => 'Paginering',

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
    'forum.col_posts'        => 'Berichten',
    'forum.col_last_post'    => 'Laatste bericht',
    'forum.sticky'           => 'Vastgezet',
    'forum.closed'           => 'Gesloten',
    'forum.by'               => 'door',
    'forum.new'              => 'nieuw',
    'forum.mark_read'        => 'Alles markeren als gelezen',
    'forum.feed_link'        => 'RSS',

    // -------------------------------------------------------------------------
    // Thread view
    // -------------------------------------------------------------------------
    'thread.reply'           => 'Reageren',
    'thread.follow'          => 'Volgen',
    'thread.following'       => 'Gevolgd',
    'thread.reopen'          => 'Heropenen',
    'thread.close'           => 'Sluiten',
    'thread.move'            => 'Verplaatsen',
    'thread.merge'           => 'Samenvoegen',
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
    'message.report'            => 'Melden',
    'message.registered'        => 'Geregistreerd',
    'message.posts'             => 'Berichten',

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
    'post.preview'           => 'Voorbeeld',
    'post.error_subject_required' => 'Onderwerp is verplicht.',
    'post.error_subject_length'   => 'Onderwerp mag maximaal 255 tekens bevatten.',
    'post.error_body_required'    => 'Berichttekst is verplicht.',
    'post.error_flood_wait'       => 'Wacht nog {seconds} seconde(n) voordat u opnieuw kunt plaatsen.',
    'post.error_posting_blocked'  => 'Plaatsen is niet toegestaan vanaf uw account.',

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
    'auth.error_missing_credentials'  => 'Voer uw gebruikersnaam en wachtwoord in.',
    'auth.error_invalid_credentials'  => 'Ongeldige gebruikersnaam of wachtwoord.',
    'auth.error_registration_blocked' => 'Registreren is niet toegestaan vanaf uw account.',
    'auth.error_invalid_email'        => 'Voer een geldig e-mailadres in.',
    'auth.error_password_min_length'  => 'Wachtwoord moet minimaal 6 tekens bevatten.',
    'auth.error_passwords_mismatch'   => 'Wachtwoorden komen niet overeen.',
    'auth.error_username_required'    => 'Gebruikersnaam is verplicht.',
    'auth.error_username_length'      => 'Gebruikersnaam moet tussen 2 en 50 tekens lang zijn.',
    'auth.error_email_required'       => 'Een geldig e-mailadres is verplicht.',
    'auth.error_username_taken'       => 'Deze gebruikersnaam is al in gebruik.',

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
    'settings.threaded_read'     => 'Gebruik de discussieweergave bij het lezen van threads',
    'settings.email_notify'      => 'Stuur mij een e-mail bij nieuwe berichten in forums waarop ik ben geabonneerd',
    'settings.pm_email_notify'   => 'Stuur mij een e-mail wanneer ik een privébericht ontvang',
    'settings.tz_offset'         => 'Tijdzoneverschuiving (uren, -12 t/m +14; -99 = servertijd)',
    'settings.save'              => 'Instellingen opslaan',
    'settings.cancel'            => 'Annuleren',
    'settings.avatar_section'    => 'Avatar',
    'settings.avatar_current'    => 'Huidige avatar',
    'settings.avatar_upload'     => 'Nieuwe avatar uploaden',
    'settings.avatar_hint'       => 'JPG, PNG, GIF of WebP. Maximaal 100 KB.',
    'settings.avatar_delete'     => 'Huidige avatar verwijderen',
    'settings.error_display_name_required' => 'Weergavenaam is verplicht.',
    'settings.error_display_name_length'   => 'Weergavenaam mag maximaal 50 tekens bevatten.',
    'settings.error_email_required'        => 'Een geldig e-mailadres is verplicht.',
    'settings.error_email_taken'           => 'Dit e-mailadres is al in gebruik bij een ander account.',
    'settings.error_password_min_length'   => 'Nieuw wachtwoord moet minimaal 6 tekens bevatten.',
    'settings.error_passwords_mismatch'    => 'Wachtwoorden komen niet overeen.',
    'settings.error_tz_offset'             => 'Tijdzoneverschuiving moet tussen -12 en +14 liggen, of -99 voor servertijd.',

    // -------------------------------------------------------------------------
    // Forced password change
    // -------------------------------------------------------------------------
    'force_password_change.title'     => 'Wijzig uw wachtwoord',
    'force_password_change.message'   => 'Een beheerder vereist dat u een nieuw wachtwoord instelt voordat u verdergaat.',
    'force_password_change.new_password'     => 'Nieuw wachtwoord',
    'force_password_change.confirm_password' => 'Nieuw wachtwoord bevestigen',
    'force_password_change.save'      => 'Wachtwoord instellen',
    'force_password_change.error_password_min_length' => 'Nieuw wachtwoord moet minimaal 6 tekens bevatten.',
    'force_password_change.error_passwords_mismatch'  => 'Wachtwoorden komen niet overeen.',

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
    'pm.error_recipient_required'   => 'Ontvanger is verplicht.',
    'pm.error_user_not_found'       => 'Gebruiker "{username}" niet gevonden.',
    'pm.error_subject_required'     => 'Onderwerp is verplicht.',
    'pm.error_body_required'        => 'Berichttekst is verplicht.',
    'pm.error_folder_name_required' => 'Mapnaam is verplicht.',
    'pm.error_folder_name_length'   => 'Mapnaam mag maximaal 60 tekens bevatten.',

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
    'sub.confirm_title'      => 'Actie bevestigen',
    'sub.confirm_remove'     => 'Weet u zeker dat u zich wilt uitschrijven voor deze discussie?',
    'sub.confirm_bookmark'   => 'Uw abonnement omzetten naar een bladwijzer (geen e-mailmeldingen)?',
    'sub.confirm_yes'        => 'Ja, bevestigen',
    'sub.confirm_cancel'     => 'Annuleren',

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
    'mod.merge_title'               => 'Discussie samenvoegen',
    'mod.merge_prompt'              => '"{subject}" samenvoegen met een andere discussie. De berichten van de samengevoegde discussie worden toegevoegd aan de doeldiscussie, en de abonnementen van deze discussie blijven niet behouden.',
    'mod.merge_target'              => 'ID van doeldiscussie',
    'mod.merge_target_hint'         => 'Het numerieke ID van de discussie waarmee moet worden samengevoegd (zichtbaar in de URL).',
    'mod.merge_submit'              => 'Discussie samenvoegen',
    'mod.merge_error_not_found'      => 'Dat discussie-ID is niet gevonden.',
    'mod.merge_error_same_thread'    => 'Kies een andere discussie om mee samen te voegen.',
    'mod.merge_error_failed'         => 'Samenvoegen met die discussie is niet gelukt.',
    'mod.moderate'                 => 'Modereren',
    'mod.queue'                    => 'Wachtrij',
    'mod.queue_title'              => 'Wachtrij voor berichten',
    'mod.queue_empty'              => 'Er zijn geen berichten die wachten op goedkeuring.',
    'mod.queue_forum'              => 'Forum',
    'mod.queue_posted'             => 'Geplaatst',
    'mod.reports_title'            => 'Meldingen',
    'mod.reports_empty'            => 'Geen openstaande meldingen.',
    'mod.reports_message_missing'  => '(gemeld bericht niet meer beschikbaar)',
    'mod.reports_reported'         => 'gemeld',
    'mod.reports_resolve'          => 'Afhandelen',
    'mod.reports_dismiss'          => 'Afwijzen',
    'mod.reports_view'             => 'Bekijken in discussie',
    'report.title'                 => 'Bericht melden',
    'report.intro'                 => 'Dit bericht van {author} melden bij de moderators?',
    'report.reason_label'          => 'Reden (optioneel)',
    'report.submit'                => 'Melding versturen',

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
    // Install
    // -------------------------------------------------------------------------
    'install.page_title'               => 'Phorum-installatie',
    'install.requirements_heading'     => 'Vereisten',
    'install.requirement_failed'       => 'mislukt',
    'install.fix_requirements'         => 'Los de bovenstaande vereisten op voordat u verdergaat.',
    'install.fix_requirements_hint_1'  => 'Zorg ervoor dat',
    'install.fix_requirements_hint_and' => 'en',
    'install.fix_requirements_hint_2'  => 'bestaan (gekopieerd van de .example-bestanden) en dat de databasegegevens correct zijn.',
    'install.errors_heading'           => 'Los het volgende op',
    'install.setup_heading'            => 'Site- en beheerdersinstellingen',
    'install.site_name_label'          => 'Sitenaam',
    'install.admin_account_heading'    => 'Beheerdersaccount',
    'install.username_label'           => 'Gebruikersnaam',
    'install.email_label'              => 'E-mail',
    'install.password_label'           => 'Wachtwoord (min. 8 tekens)',
    'install.confirm_password_label'   => 'Wachtwoord bevestigen',
    'install.submit'                   => 'Phorum installeren',
    'install.complete_page_title'      => 'Installatie voltooid — Phorum',
    'install.complete_heading'         => 'Installatie voltooid',
    'install.complete_message'         => 'Het databaseschema is aangemaakt en uw beheerdersaccount is klaar voor gebruik.',
    'install.go_to_forum'              => 'Naar het forum',
    'install.admin_panel'              => 'Beheerderspaneel',
    'install.error_site_name_required'  => 'Sitenaam is verplicht.',
    'install.error_username_required'   => 'Gebruikersnaam van de beheerder is verplicht.',
    'install.error_username_format'     => 'Gebruikersnaam moet 3–50 tekens lang zijn (alleen letters, cijfers, _ . -).',
    'install.error_email_required'      => 'Een geldig e-mailadres van de beheerder is verplicht.',
    'install.error_password_min_length' => 'Wachtwoord van de beheerder moet minimaal 8 tekens bevatten.',
    'install.error_passwords_mismatch'  => 'Wachtwoorden komen niet overeen.',
    'install.error_failed'              => 'Installatie mislukt: {message}',

    // -------------------------------------------------------------------------
    // Upgrade (existing Phorum 6 database → Phorum 10)
    // -------------------------------------------------------------------------
    'upgrade.page_title'          => 'Phorum-upgrade',
    'upgrade.detected_heading'    => 'Bestaande Phorum 6-database gedetecteerd',
    'upgrade.detected_message'    => 'Deze database is aangemaakt door Phorum 6. Phorum 10 is schema-compatibel met Phorum 6 — er worden geen bestaande gegevens gewijzigd, verwijderd of geconverteerd.',
    'upgrade.up_to_date'          => 'Er zijn geen schemawijzigingen nodig — deze database is al up-to-date.',
    'upgrade.new_tables_heading'  => 'De volgende nieuwe tabellen worden toegevoegd:',
    'upgrade.new_patches_heading' => 'De volgende schema-updates worden toegepast:',
    'upgrade.submit'              => 'Doorgaan',
    'upgrade.complete_page_title' => 'Upgrade voltooid — Phorum',
    'upgrade.complete_heading'    => 'Upgrade voltooid',
    'upgrade.complete_message'    => 'Uw Phorum 6-database is nu klaar om te draaien op Phorum 10.',
    'upgrade.go_to_forum'         => 'Naar het forum',
    'upgrade.admin_panel'         => 'Beheerderspaneel',

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
    'error.disabled_title'    => 'Site niet beschikbaar',
    'error.disabled_message'  => 'Deze site is tijdelijk uitgeschakeld. Kom later nog eens terug.',
    'error.admin_only_title'   => 'Site niet beschikbaar',
    'error.admin_only_message' => 'Deze site is tijdelijk gesloten voor onderhoud. Kom later nog eens terug.',
    'error.read_only_title'    => 'Alleen-lezen',
    'error.read_only_message'  => 'Deze site staat momenteel op alleen-lezen. Plaatsen en inloggen zijn tijdelijk uitgeschakeld.',
    'banner.read_only'         => 'Deze site staat momenteel op alleen-lezen — plaatsen en inloggen zijn tijdelijk uitgeschakeld.',

    // -------------------------------------------------------------------------
    // Announcements
    // -------------------------------------------------------------------------
    'announcements.heading' => 'Aankondigingen',

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
