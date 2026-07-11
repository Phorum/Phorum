<?php
declare(strict_types=1);

/**
 * French translations for Phorum.
 * Machine-translated — please review with a native speaker.
 */
return [

    // Metadata (not rendered as UI text)
    '_name' => 'Français',

    // -------------------------------------------------------------------------
    // Navigation
    // -------------------------------------------------------------------------
    'nav.forum_index'   => 'Index du forum',
    'nav.search'        => 'Rechercher',
    'nav.messages'      => 'Messages',
    'nav.settings'      => 'Paramètres',
    'nav.log_out'       => 'Se déconnecter',
    'nav.log_in'        => 'Se connecter',
    'nav.register'      => 'S\'inscrire',
    'nav.powered_by'    => 'Propulsé par Phorum',
    'nav.menu'          => 'Menu',

    // -------------------------------------------------------------------------
    // Forum list (index page)
    // -------------------------------------------------------------------------
    'forum_list.no_forums'   => 'Aucun forum n\'a encore été créé.',
    'forum_list.col_forum'   => 'Forum',
    'forum_list.col_posts'   => 'Messages',
    'forum_list.col_threads' => 'Fils',
    'forum_list.col_last'    => 'Dernier message',

    // -------------------------------------------------------------------------
    // Forum (thread listing)
    // -------------------------------------------------------------------------
    'forum.new_thread'       => 'Nouveau fil',
    'forum.no_threads'       => 'Aucun fil pour l\'instant.',
    'forum.start_one'        => 'Créez-en un.',
    'forum.col_subject'      => 'Sujet',
    'forum.col_author'       => 'Auteur',
    'forum.col_replies'      => 'Réponses',
    'forum.col_last_post'    => 'Dernier message',
    'forum.sticky'           => 'Épinglé',
    'forum.closed'           => 'Fermé',
    'forum.by'               => 'par',
    'forum.new'              => 'nouveau',
    'forum.mark_read'        => 'Tout marquer comme lu',

    // -------------------------------------------------------------------------
    // Thread view
    // -------------------------------------------------------------------------
    'thread.reply'           => 'Répondre',
    'thread.follow'          => 'Suivre',
    'thread.following'       => 'Suivi',
    'thread.reopen'          => 'Rouvrir',
    'thread.close'           => 'Fermer',
    'thread.move'            => 'Déplacer',
    'thread.delete'          => 'Supprimer le fil',

    // -------------------------------------------------------------------------
    // Individual message
    // -------------------------------------------------------------------------
    'message.awaiting_approval' => 'En attente d\'approbation',
    'message.reply'             => 'Répondre',
    'message.edit'              => 'Modifier',
    'message.edit_title'        => 'Modifier le message',
    'message.save_edit'         => 'Enregistrer',
    'message.edited_note'       => 'Modifié',
    'message.changes'           => 'Changes',
    'message.changes_title'     => 'Edit History',
    'message.changes_edit_n'    => 'Edit #{n}',
    'message.changes_by'        => 'by',
    'message.changes_subject'   => 'Subject',
    'message.changes_body'      => 'Message',
    'message.changes_back'      => 'Back to thread',
    'message.approve'           => 'Approuver',
    'message.delete'            => 'Supprimer',

    // -------------------------------------------------------------------------
    // Post / reply form
    // -------------------------------------------------------------------------
    'post.new_thread'        => 'Nouveau fil',
    'post.reply_to'          => 'Répondre à {subject}',
    'post.reply'             => 'Répondre',
    'post.subject'           => 'Sujet',
    'post.body'              => 'Message',
    'post.submit_thread'     => 'Publier le fil',
    'post.submit_reply'      => 'Publier la réponse',
    'post.cancel'            => 'Annuler',

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------
    'auth.login_title'       => 'Se connecter',
    'auth.username'          => 'Nom d\'utilisateur',
    'auth.password'          => 'Mot de passe',
    'auth.remember_me'       => 'Se souvenir de moi',
    'auth.login_submit'      => 'Se connecter',
    'auth.create_account'    => 'Créer un compte',
    'auth.register_title'    => 'Créer un compte',
    'auth.email'             => 'Adresse e-mail',
    'auth.password_hint'     => 'au moins 6 caractères',
    'auth.confirm_password'  => 'Confirmer le mot de passe',
    'auth.register_submit'   => 'Créer le compte',
    'auth.have_account'      => 'Vous avez déjà un compte ?',
    'auth.login_link'        => 'Se connecter.',
    'auth.forgot_password'   => 'Mot de passe oublié ?',
    'auth.forgot_title'      => 'Réinitialiser votre mot de passe',
    'auth.forgot_email_label' => 'Adresse e-mail',
    'auth.forgot_submit'     => 'Envoyer le lien de réinitialisation',
    'auth.forgot_sent'       => 'Si cette adresse e-mail est enregistrée, un lien de réinitialisation a été envoyé. Vérifiez votre boîte de réception.',
    'auth.reset_title'       => 'Choisir un nouveau mot de passe',
    'auth.reset_new_password' => 'Nouveau mot de passe',
    'auth.reset_confirm'     => 'Confirmer le nouveau mot de passe',
    'auth.reset_submit'      => 'Définir le nouveau mot de passe',
    'auth.reset_invalid'     => 'Ce lien de réinitialisation est invalide ou a expiré. Veuillez en demander un nouveau.',
    'auth.reset_success'     => 'Votre mot de passe a été mis à jour. Vous êtes maintenant connecté.',
    'auth.confirm_pending_title'  => 'Vérifiez votre e-mail',
    'auth.confirm_pending_body'   => 'Nous avons envoyé un lien de confirmation à {email}. Cliquez dessus pour activer votre compte.',
    'auth.confirm_pending_resend' => 'Renvoyer l\'e-mail de confirmation',
    'auth.confirm_invalid'        => 'Ce lien de confirmation est invalide ou a expiré.',
    'auth.resend_title'           => 'Renvoyer l\'e-mail de confirmation',
    'auth.resend_email_label'     => 'Adresse e-mail',
    'auth.resend_submit'          => 'Renvoyer',
    'auth.resend_sent'            => 'Si cette adresse a une confirmation en attente, un nouveau lien a été envoyé. Vérifiez votre boîte de réception.',

    // -------------------------------------------------------------------------
    // User profile
    // -------------------------------------------------------------------------
    'profile.username'       => 'Nom d\'utilisateur',
    'profile.name'           => 'Nom',
    'profile.email'          => 'E-mail',
    'profile.joined'         => 'Inscrit le',
    'profile.posts'          => 'Messages',
    'profile.last_active'    => 'Dernière activité',
    'profile.signature'      => 'Signature',
    'profile.recent_posts'   => 'Messages récents',
    'profile.col_subject'    => 'Sujet',
    'profile.col_date'       => 'Date',
    'profile.edit_settings'  => 'Modifier les paramètres',

    // -------------------------------------------------------------------------
    // User settings
    // -------------------------------------------------------------------------
    'settings.title'             => 'Paramètres du compte',
    'settings.saved'             => 'Vos paramètres ont été enregistrés.',
    'settings.identity'          => 'Identité',
    'settings.display_name'      => 'Nom affiché',
    'settings.email'             => 'Adresse e-mail',
    'settings.hide_email'        => 'Masquer mon adresse e-mail sur mon profil',
    'settings.password_section'  => 'Mot de passe',
    'settings.password_hint'     => 'Laisser vide pour conserver votre mot de passe actuel.',
    'settings.new_password'      => 'Nouveau mot de passe',
    'settings.confirm_password'  => 'Confirmer le nouveau mot de passe',
    'settings.signature_section' => 'Signature',
    'settings.signature_text'    => 'Texte de la signature',
    'settings.show_signature'    => 'Afficher la signature sur mes messages',
    'settings.preferences'       => 'Préférences',
    'settings.threaded_list'     => 'Utiliser la vue par fils pour les listes de forums',
    'settings.threaded_read'     => 'Utiliser la vue par fils lors de la lecture des fils',
    'settings.email_notify'      => 'M\'envoyer un e-mail lorsque de nouveaux messages sont publiés dans les forums auxquels je suis abonné',
    'settings.pm_email_notify'   => 'M\'envoyer un e-mail lorsque je reçois un message privé',
    'settings.tz_offset'         => 'Décalage horaire (heures, -12 à +14 ; -99 = heure du serveur)',
    'settings.save'              => 'Enregistrer les paramètres',
    'settings.cancel'            => 'Annuler',

    // -------------------------------------------------------------------------
    // Private messages
    // -------------------------------------------------------------------------
    'pm.private_messages'    => 'Messages privés',
    'pm.folders'             => 'Dossiers',
    'pm.inbox'               => 'Boîte de réception',
    'pm.outbox'              => 'Boîte d\'envoi',
    'pm.compose'             => 'Rédiger',
    'pm.manage_folders'      => 'Gérer les dossiers',
    'pm.no_messages'         => 'Aucun message.',
    'pm.col_subject'         => 'Sujet',
    'pm.col_from'            => 'De',
    'pm.col_to'              => 'À',
    'pm.col_date'            => 'Date',
    'pm.delete'              => 'Supprimer',
    'pm.compose_title'       => 'Rédiger un message',
    'pm.to_label'            => 'À (nom d\'utilisateur)',
    'pm.subject'             => 'Sujet',
    'pm.body'                => 'Message',
    'pm.send'                => 'Envoyer',
    'pm.cancel'              => 'Annuler',
    'pm.reply'               => 'Répondre',
    'pm.back_to_inbox'       => 'Retour à la boîte de réception',
    'pm.move_to_folder'      => 'Déplacer vers le dossier…',
    'pm.move'                => 'Déplacer',
    'pm.delete_title'        => 'Supprimer le message privé',
    'pm.delete_confirm'      => 'Supprimer « {subject} » de {author} ?',
    'pm.create_folder_title' => 'Créer un dossier',
    'pm.folder_name'         => 'Nom du dossier',
    'pm.create'              => 'Créer',
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
    'sub.title'              => 'Suivre le fil',
    'sub.following_email'    => 'Vous suivez actuellement ce fil et recevrez des notifications par e-mail pour les nouvelles réponses.',
    'sub.bookmarked'         => 'Vous avez mis ce fil en favori (sans notifications par e-mail).',
    'sub.not_following'      => 'Vous ne suivez pas ce fil.',
    'sub.follow_email'       => 'Suivre et recevoir des e-mails pour les nouvelles réponses',
    'sub.bookmark'           => 'Mettre en favori (sans e-mails)',
    'sub.unfollow'           => 'Ne plus suivre',
    'sub.back_to_thread'     => 'Retour au fil',
    'sub.follow'             => 'Suivre',

    // -------------------------------------------------------------------------
    // Moderation
    // -------------------------------------------------------------------------
    'mod.delete_thread'            => 'Supprimer le fil',
    'mod.delete_message'           => 'Supprimer le message',
    'mod.approve_message'          => 'Approuver le message',
    'mod.close_thread'             => 'Fermer le fil',
    'mod.reopen_thread'            => 'Rouvrir le fil',
    'mod.delete_thread_confirm'    => 'Êtes-vous sûr de vouloir supprimer définitivement le fil « {subject} » et toutes ses réponses ? Cette action est irréversible.',
    'mod.delete_message_confirm'   => 'Êtes-vous sûr de vouloir supprimer ce message de {author} ? Ses réponses seront rattachées au message précédent dans le fil.',
    'mod.approve_confirm'          => 'Approuver le message suivant de {author} pour le rendre visible à tous les lecteurs ?',
    'mod.close_confirm'            => 'Fermer le fil « {subject} » ? Aucune nouvelle réponse ne sera autorisée une fois le fil fermé.',
    'mod.open_confirm'             => 'Rouvrir le fil « {subject} » pour permettre aux membres de publier de nouvelles réponses ?',
    'mod.yes_delete'               => 'Oui, supprimer',
    'mod.approve'                  => 'Approuver',
    'mod.close'                    => 'Fermer le fil',
    'mod.reopen'                   => 'Rouvrir le fil',
    'mod.cancel'                   => 'Annuler',
    'mod.move_title'               => 'Déplacer le fil',
    'mod.move_prompt'              => 'Déplacer « {subject} » vers un autre forum :',
    'mod.destination'              => 'Forum de destination',
    'mod.choose_forum'             => '— choisir un forum —',
    'mod.move_submit'              => 'Déplacer le fil',

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------
    'search.title'           => 'Rechercher',
    'search.messages_label'  => 'Rechercher des messages',
    'search.author'          => 'Auteur',
    'search.match_type'      => 'Type de correspondance',
    'search.all_words'       => 'Tous les mots',
    'search.any_word'        => 'N\'importe quel mot',
    'search.exact_phrase'    => 'Expression exacte',
    'search.posted_within'   => 'Publié dans les',
    'search.last_30'         => '30 derniers jours',
    'search.last_90'         => '90 derniers jours',
    'search.last_year'       => 'Dernière année',
    'search.any_time'        => 'N\'importe quand',
    'search.threads_only'    => 'Premiers messages des fils uniquement',
    'search.forums_label'    => 'Forums',
    'search.all_forums'      => 'Tous les forums',
    'search.submit'          => 'Rechercher',
    'search.no_results'      => 'Aucun résultat trouvé.',
    'search.showing'         => 'Affichage',
    'search.of'              => 'sur',
    'search.result'          => 'résultat',
    'search.results'         => 'résultats',
    'search.col_subject'     => 'Sujet',
    'search.col_author'      => 'Auteur',
    'search.col_forum'       => 'Forum',
    'search.col_date'        => 'Date',

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------
    'error.404_title'        => 'Page introuvable',
    'error.404_message'      => 'La page que vous avez demandée n\'existe pas.',
    'error.404_return'       => 'Retour à l\'index du forum.',
    'error.403_title'        => 'Accès refusé',
    'error.403_message'      => 'Vous n\'avez pas la permission d\'accéder à ce forum.',
    'error.403_login'        => 'Se connecter',
    'error.403_login_hint'   => 'pour accéder aux forums qui nécessitent une inscription.',
    'error.403_return'       => 'Retour à l\'index du forum',

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
