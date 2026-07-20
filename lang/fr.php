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
    'nav.skip_to_content' => 'Passer au contenu principal',
    'nav.breadcrumb'    => 'Fil d\'Ariane',
    'nav.primary'       => 'Principal',
    'nav.menu'          => 'Menu',
    'pagination.nav_label' => 'Pagination',

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
    'forum.col_posts'        => 'Messages',
    'forum.col_last_post'    => 'Dernier message',
    'forum.sticky'           => 'Épinglé',
    'forum.closed'           => 'Fermé',
    'forum.by'               => 'par',
    'forum.new'              => 'nouveau',
    'forum.mark_read'        => 'Tout marquer comme lu',
    'forum.feed_link'        => 'RSS',

    // -------------------------------------------------------------------------
    // Thread view
    // -------------------------------------------------------------------------
    'thread.reply'           => 'Répondre',
    'thread.follow'          => 'Suivre',
    'thread.following'       => 'Suivi',
    'thread.reopen'          => 'Rouvrir',
    'thread.close'           => 'Fermer',
    'thread.move'            => 'Déplacer',
    'thread.merge'           => 'Fusionner',
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
    'message.report'            => 'Signaler',
    'message.registered'        => 'Inscrit',
    'message.posts'             => 'Messages',

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
    'post.preview'           => 'Aperçu',
    'post.error_subject_required' => 'Le sujet est obligatoire.',
    'post.error_subject_length'   => 'Le sujet doit comporter au maximum 255 caractères.',
    'post.error_body_required'    => 'Le corps du message est obligatoire.',
    'post.error_flood_wait'       => 'Veuillez attendre encore {seconds} seconde(s) avant de publier à nouveau.',
    'post.error_posting_blocked'  => 'La publication n\'est pas autorisée depuis votre compte.',

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
    'auth.error_missing_credentials'  => 'Veuillez saisir votre nom d\'utilisateur et votre mot de passe.',
    'auth.error_invalid_credentials'  => 'Nom d\'utilisateur ou mot de passe invalide.',
    'auth.error_registration_blocked' => 'L\'inscription n\'est pas autorisée depuis votre compte.',
    'auth.error_invalid_email'        => 'Veuillez saisir une adresse e-mail valide.',
    'auth.error_password_min_length'  => 'Le mot de passe doit comporter au moins 6 caractères.',
    'auth.error_passwords_mismatch'   => 'Les mots de passe ne correspondent pas.',
    'auth.error_username_required'    => 'Le nom d\'utilisateur est obligatoire.',
    'auth.error_username_length'      => 'Le nom d\'utilisateur doit comporter entre 2 et 50 caractères.',
    'auth.error_email_required'       => 'Une adresse e-mail valide est requise.',
    'auth.error_username_taken'       => 'Ce nom d\'utilisateur est déjà pris.',

    // -------------------------------------------------------------------------
    // OAuth login
    // -------------------------------------------------------------------------
    'oauth.button_google' => 'Continuer avec Google',
    'oauth.button_github' => 'Continuer avec GitHub',
    'oauth.error_provider_error'        => 'La connexion a été annulée ou le fournisseur a renvoyé une erreur. Veuillez réessayer.',
    'oauth.error_state_mismatch'        => 'Votre session de connexion a expiré ou n\'est pas valide. Veuillez réessayer.',
    'oauth.error_token_exchange_failed' => 'Nous n\'avons pas pu terminer la connexion avec ce fournisseur. Veuillez réessayer.',
    'oauth.error_email_not_verified'    => 'Votre adresse e-mail n\'est pas vérifiée auprès de ce fournisseur, nous ne pouvons donc pas vous connecter. Veuillez vérifier votre e-mail auprès du fournisseur et réessayer.',
    'oauth.error_login_failed'          => 'Une erreur s\'est produite lors de la connexion. Veuillez réessayer.',
    'oauth.error_account_inactive'      => 'Votre compte n\'est pas encore actif. Consultez votre e-mail pour un lien de confirmation.',
    'oauth.error_not_configured'        => 'Cette option de connexion n\'est pas disponible actuellement.',

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
    'settings.threaded_read'     => 'Utiliser la vue par fils lors de la lecture des fils',
    'settings.email_notify'      => 'M\'envoyer un e-mail lorsque de nouveaux messages sont publiés dans les forums auxquels je suis abonné',
    'settings.pm_email_notify'   => 'M\'envoyer un e-mail lorsque je reçois un message privé',
    'settings.tz_offset'         => 'Décalage horaire (heures, -12 à +14 ; -99 = heure du serveur)',
    'settings.save'              => 'Enregistrer les paramètres',
    'settings.cancel'            => 'Annuler',
    'settings.avatar_section'    => 'Avatar',
    'settings.avatar_current'    => 'Avatar actuel',
    'settings.avatar_upload'     => 'Téléverser un nouvel avatar',
    'settings.avatar_hint'       => 'JPG, PNG, GIF ou WebP. Maximum 100 Ko.',
    'settings.avatar_delete'     => 'Supprimer l\'avatar actuel',
    'settings.error_display_name_required' => 'Le nom affiché est obligatoire.',
    'settings.error_display_name_length'   => 'Le nom affiché doit comporter au maximum 50 caractères.',
    'settings.error_email_required'        => 'Une adresse e-mail valide est requise.',
    'settings.error_email_taken'           => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
    'settings.error_password_min_length'   => 'Le nouveau mot de passe doit comporter au moins 6 caractères.',
    'settings.error_passwords_mismatch'    => 'Les mots de passe ne correspondent pas.',
    'settings.error_tz_offset'             => 'Le décalage horaire doit être compris entre -12 et +14, ou -99 pour l\'heure du serveur.',

    // -------------------------------------------------------------------------
    // Forced password change
    // -------------------------------------------------------------------------
    'force_password_change.title'     => 'Changer votre mot de passe',
    'force_password_change.message'   => 'Un administrateur vous demande de définir un nouveau mot de passe avant de continuer.',
    'force_password_change.new_password'     => 'Nouveau mot de passe',
    'force_password_change.confirm_password' => 'Confirmer le nouveau mot de passe',
    'force_password_change.save'      => 'Définir le mot de passe',
    'force_password_change.error_password_min_length' => 'Le nouveau mot de passe doit comporter au moins 6 caractères.',
    'force_password_change.error_passwords_mismatch'  => 'Les mots de passe ne correspondent pas.',

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
    'pm.error_recipient_required'   => 'Le destinataire est obligatoire.',
    'pm.error_user_not_found'       => 'Utilisateur « {username} » introuvable.',
    'pm.error_subject_required'     => 'Le sujet est obligatoire.',
    'pm.error_body_required'        => 'Le corps du message est obligatoire.',
    'pm.error_folder_name_required' => 'Le nom du dossier est obligatoire.',
    'pm.error_folder_name_length'   => 'Le nom du dossier doit comporter au maximum 60 caractères.',

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
    'sub.confirm_title'      => 'Confirmer l\'action',
    'sub.confirm_remove'     => 'Êtes-vous sûr de vouloir vous désabonner de ce fil ?',
    'sub.confirm_bookmark'   => 'Remplacer votre abonnement par un favori (sans notifications par e-mail) ?',
    'sub.confirm_yes'        => 'Oui, confirmer',
    'sub.confirm_cancel'     => 'Annuler',

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
    'mod.merge_title'               => 'Fusionner le fil',
    'mod.merge_prompt'              => 'Fusionner « {subject} » dans un autre fil. Les messages du fil fusionné seront ajoutés au fil cible, et les abonnements de ce fil ne seront pas conservés.',
    'mod.merge_target'              => 'ID du fil cible',
    'mod.merge_target_hint'         => 'L\'identifiant numérique du fil dans lequel fusionner (visible dans son URL).',
    'mod.merge_submit'              => 'Fusionner le fil',
    'mod.merge_error_not_found'      => 'Cet ID de fil est introuvable.',
    'mod.merge_error_same_thread'    => 'Choisissez un autre fil dans lequel fusionner.',
    'mod.merge_error_failed'         => 'Impossible de fusionner dans ce fil.',
    'mod.moderate'                 => 'Modérer',
    'mod.queue'                    => 'File de modération',
    'mod.queue_title'              => 'File des messages en attente',
    'mod.queue_empty'              => 'Aucun message en attente d\'approbation.',
    'mod.queue_forum'              => 'Forum',
    'mod.queue_posted'             => 'Publié',
    'mod.reports_title'            => 'Contenu signalé',
    'mod.reports_empty'            => 'Aucun signalement ouvert.',
    'mod.reports_message_missing'  => '(le message signalé n\'est plus disponible)',
    'mod.reports_reported'         => 'signalé',
    'mod.reports_resolve'          => 'Résoudre',
    'mod.reports_dismiss'          => 'Rejeter',
    'mod.reports_view'             => 'Voir dans le fil',
    'report.title'                 => 'Signaler le message',
    'report.intro'                 => 'Signaler ce message de {author} aux modérateurs ?',
    'report.reason_label'          => 'Motif (facultatif)',
    'report.submit'                => 'Envoyer le signalement',

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
    // Install
    // -------------------------------------------------------------------------
    'install.page_title'               => 'Installateur Phorum',
    'install.requirements_heading'     => 'Prérequis',
    'install.requirement_failed'       => 'échoué',
    'install.fix_requirements'         => 'Corrigez les prérequis ci-dessus avant de continuer.',
    'install.fix_requirements_hint_1'  => 'Assurez-vous que',
    'install.fix_requirements_hint_and' => 'et',
    'install.fix_requirements_hint_2'  => 'existent (copiés à partir des fichiers .example) et que les identifiants de la base de données sont corrects.',
    'install.errors_heading'           => 'Veuillez corriger les éléments suivants',
    'install.setup_heading'            => 'Configuration du site et de l\'administrateur',
    'install.site_name_label'          => 'Nom du site',
    'install.admin_account_heading'    => 'Compte administrateur',
    'install.username_label'           => 'Nom d\'utilisateur',
    'install.email_label'              => 'E-mail',
    'install.password_label'           => 'Mot de passe (8 caractères min.)',
    'install.confirm_password_label'   => 'Confirmer le mot de passe',
    'install.submit'                   => 'Installer Phorum',
    'install.complete_page_title'      => 'Installation terminée — Phorum',
    'install.complete_heading'         => 'Installation terminée',
    'install.complete_message'         => 'Le schéma de la base de données a été créé et votre compte administrateur est prêt.',
    'install.go_to_forum'              => 'Aller au forum',
    'install.admin_panel'              => 'Panneau d\'administration',
    'install.error_site_name_required'  => 'Le nom du site est obligatoire.',
    'install.error_username_required'   => 'Le nom d\'utilisateur administrateur est obligatoire.',
    'install.error_username_format'     => 'Le nom d\'utilisateur doit comporter entre 3 et 50 caractères (lettres, chiffres, _ . - uniquement).',
    'install.error_email_required'      => 'Une adresse e-mail administrateur valide est requise.',
    'install.error_password_min_length' => 'Le mot de passe administrateur doit comporter au moins 8 caractères.',
    'install.error_passwords_mismatch'  => 'Les mots de passe ne correspondent pas.',
    'install.error_failed'              => 'Échec de l\'installation : {message}',

    // -------------------------------------------------------------------------
    // Upgrade (existing Phorum 6 database → Phorum 10)
    // -------------------------------------------------------------------------
    'upgrade.page_title'          => 'Mise à niveau Phorum',
    'upgrade.detected_heading'    => 'Base de données Phorum 6 existante détectée',
    'upgrade.detected_message'    => 'Cette base de données a été créée par Phorum 6. Phorum 10 est compatible avec le schéma de Phorum 6 — aucune donnée existante ne sera modifiée, supprimée ou convertie.',
    'upgrade.up_to_date'          => 'Aucune modification de schéma n\'est nécessaire — cette base de données est déjà à jour.',
    'upgrade.new_tables_heading'  => 'Les nouvelles tables suivantes seront ajoutées :',
    'upgrade.new_patches_heading' => 'Les mises à jour de schéma suivantes seront appliquées :',
    'upgrade.submit'              => 'Continuer',
    'upgrade.complete_page_title' => 'Mise à niveau terminée — Phorum',
    'upgrade.complete_heading'    => 'Mise à niveau terminée',
    'upgrade.complete_message'    => 'Votre base de données Phorum 6 est maintenant prête à fonctionner sur Phorum 10.',
    'upgrade.go_to_forum'         => 'Aller au forum',
    'upgrade.admin_panel'         => 'Panneau d\'administration',

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
    'error.disabled_title'    => 'Site indisponible',
    'error.disabled_message'  => 'Ce site est temporairement désactivé. Veuillez revenir plus tard.',
    'error.admin_only_title'   => 'Site indisponible',
    'error.admin_only_message' => 'Ce site est temporairement fermé pour maintenance. Veuillez revenir plus tard.',
    'error.read_only_title'    => 'Lecture seule',
    'error.read_only_message'  => 'Ce site est actuellement en lecture seule. La publication et la connexion sont temporairement désactivées.',
    'banner.read_only'         => 'Ce site est actuellement en lecture seule — la publication et la connexion sont temporairement désactivées.',

    // -------------------------------------------------------------------------
    // Announcements
    // -------------------------------------------------------------------------
    'announcements.heading' => 'Annonces',

    // -------------------------------------------------------------------------
    // Attachments
    // -------------------------------------------------------------------------
    'attachment.label'      => 'Attachments',
    'attachment.add'        => 'Add files',
    'attachment.existing'   => 'Existing attachments',
    'attachment.remove'     => 'Remove',
    'attachment.hint_count' => 'Up to {n} file(s).',
    'attachment.hint_size'  => 'Max {size} per file.',
    'attachment.error_uploads_disabled' => 'Les téléversements de fichiers sont actuellement désactivés.',
    'attachment.lightbox_close' => 'Fermer',
    'attachment.play_video' => 'Lire la vidéo',
];
