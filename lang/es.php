<?php
declare(strict_types=1);

/**
 * Spanish translations for Phorum.
 * Machine-translated — please review with a native speaker.
 */
return [

    // Metadata (not rendered as UI text)
    '_name' => 'Español',

    // -------------------------------------------------------------------------
    // Navigation
    // -------------------------------------------------------------------------
    'nav.forum_index'   => 'Índice del foro',
    'nav.search'        => 'Buscar',
    'nav.messages'      => 'Mensajes',
    'nav.settings'      => 'Configuración',
    'nav.log_out'       => 'Cerrar sesión',
    'nav.log_in'        => 'Iniciar sesión',
    'nav.register'      => 'Registrarse',
    'nav.powered_by'    => 'Desarrollado por Phorum',
    'nav.skip_to_content' => 'Saltar al contenido principal',
    'nav.breadcrumb'    => 'Ruta de navegación',
    'nav.primary'       => 'Principal',
    'nav.menu'          => 'Menú',
    'pagination.nav_label' => 'Paginación',

    // -------------------------------------------------------------------------
    // Forum list (index page)
    // -------------------------------------------------------------------------
    'forum_list.no_forums'   => 'Aún no se han creado foros.',
    'forum_list.col_forum'   => 'Foro',
    'forum_list.col_posts'   => 'Mensajes',
    'forum_list.col_threads' => 'Hilos',
    'forum_list.col_last'    => 'Último mensaje',

    // -------------------------------------------------------------------------
    // Forum (thread listing)
    // -------------------------------------------------------------------------
    'forum.new_thread'       => 'Nuevo hilo',
    'forum.no_threads'       => 'Aún no hay hilos.',
    'forum.start_one'        => 'Crea uno.',
    'forum.col_subject'      => 'Asunto',
    'forum.col_author'       => 'Autor',
    'forum.col_replies'      => 'Respuestas',
    'forum.col_posts'        => 'Mensajes',
    'forum.col_last_post'    => 'Último mensaje',
    'forum.sticky'           => 'Fijado',
    'forum.closed'           => 'Cerrado',
    'forum.by'               => 'por',
    'forum.new'              => 'nuevo',
    'forum.mark_read'        => 'Marcar todo como leído',
    'forum.feed_link'        => 'RSS',

    // -------------------------------------------------------------------------
    // Thread view
    // -------------------------------------------------------------------------
    'thread.reply'           => 'Responder',
    'thread.follow'          => 'Seguir',
    'thread.following'       => 'Siguiendo',
    'thread.reopen'          => 'Reabrir',
    'thread.close'           => 'Cerrar',
    'thread.move'            => 'Mover',
    'thread.merge'           => 'Fusionar',
    'thread.delete'          => 'Eliminar hilo',

    // -------------------------------------------------------------------------
    // Individual message
    // -------------------------------------------------------------------------
    'message.awaiting_approval' => 'Pendiente de aprobación',
    'message.reply'             => 'Responder',
    'message.edit'              => 'Editar',
    'message.edit_title'        => 'Editar mensaje',
    'message.save_edit'         => 'Guardar cambios',
    'message.edited_note'       => 'Editado',
    'message.changes'           => 'Changes',
    'message.changes_title'     => 'Edit History',
    'message.changes_edit_n'    => 'Edit #{n}',
    'message.changes_by'        => 'by',
    'message.changes_subject'   => 'Subject',
    'message.changes_body'      => 'Message',
    'message.changes_back'      => 'Back to thread',
    'message.approve'           => 'Aprobar',
    'message.delete'            => 'Eliminar',
    'message.report'            => 'Reportar',
    'message.registered'        => 'Registrado',
    'message.posts'             => 'Mensajes',

    // -------------------------------------------------------------------------
    // Post / reply form
    // -------------------------------------------------------------------------
    'post.new_thread'        => 'Nuevo hilo',
    'post.reply_to'          => 'Responder a {subject}',
    'post.reply'             => 'Responder',
    'post.subject'           => 'Asunto',
    'post.body'              => 'Mensaje',
    'post.submit_thread'     => 'Publicar hilo',
    'post.submit_reply'      => 'Publicar respuesta',
    'post.cancel'            => 'Cancelar',
    'post.preview'           => 'Vista previa',
    'post.error_subject_required' => 'El asunto es obligatorio.',
    'post.error_subject_length'   => 'El asunto debe tener 255 caracteres o menos.',
    'post.error_body_required'    => 'El cuerpo del mensaje es obligatorio.',
    'post.error_flood_wait'       => 'Por favor, espera {seconds} segundo(s) más antes de volver a publicar.',
    'post.error_posting_blocked'  => 'No se permite publicar desde tu cuenta.',

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------
    'auth.login_title'       => 'Iniciar sesión',
    'auth.username'          => 'Nombre de usuario',
    'auth.password'          => 'Contraseña',
    'auth.remember_me'       => 'Recordarme',
    'auth.login_submit'      => 'Iniciar sesión',
    'auth.create_account'    => 'Crear una cuenta',
    'auth.register_title'    => 'Crear una cuenta',
    'auth.email'             => 'Correo electrónico',
    'auth.password_hint'     => 'al menos 6 caracteres',
    'auth.confirm_password'  => 'Confirmar contraseña',
    'auth.register_submit'   => 'Crear cuenta',
    'auth.have_account'      => '¿Ya tienes una cuenta?',
    'auth.login_link'        => 'Inicia sesión.',
    'auth.forgot_password'   => '¿Olvidaste tu contraseña?',
    'auth.forgot_title'      => 'Restablecer tu contraseña',
    'auth.forgot_email_label' => 'Correo electrónico',
    'auth.forgot_submit'     => 'Enviar enlace de restablecimiento',
    'auth.forgot_sent'       => 'Si ese correo electrónico está registrado, se ha enviado un enlace de restablecimiento. Revisa tu bandeja de entrada.',
    'auth.reset_title'       => 'Elige una nueva contraseña',
    'auth.reset_new_password' => 'Nueva contraseña',
    'auth.reset_confirm'     => 'Confirmar nueva contraseña',
    'auth.reset_submit'      => 'Establecer nueva contraseña',
    'auth.reset_invalid'     => 'Este enlace de restablecimiento de contraseña no es válido o ha expirado. Por favor, solicita uno nuevo.',
    'auth.reset_success'     => 'Tu contraseña ha sido actualizada. Ahora has iniciado sesión.',
    'auth.confirm_pending_title'  => 'Revisa tu correo electrónico',
    'auth.confirm_pending_body'   => 'Enviamos un enlace de confirmación a {email}. Haz clic en él para activar tu cuenta.',
    'auth.confirm_pending_resend' => 'Reenviar correo de confirmación',
    'auth.confirm_invalid'        => 'Este enlace de confirmación no es válido o ha expirado.',
    'auth.resend_title'           => 'Reenviar correo de confirmación',
    'auth.resend_email_label'     => 'Correo electrónico',
    'auth.resend_submit'          => 'Reenviar',
    'auth.resend_sent'            => 'Si esa dirección tiene una confirmación pendiente, se ha enviado un nuevo enlace. Revisa tu bandeja de entrada.',
    'auth.error_missing_credentials'  => 'Por favor, introduce tu nombre de usuario y contraseña.',
    'auth.error_invalid_credentials'  => 'Nombre de usuario o contraseña no válidos.',
    'auth.error_registration_blocked' => 'No se permite el registro desde tu cuenta.',
    'auth.error_invalid_email'        => 'Por favor, introduce una dirección de correo electrónico válida.',
    'auth.error_password_min_length'  => 'La contraseña debe tener al menos 6 caracteres.',
    'auth.error_passwords_mismatch'   => 'Las contraseñas no coinciden.',
    'auth.error_username_required'    => 'El nombre de usuario es obligatorio.',
    'auth.error_username_length'      => 'El nombre de usuario debe tener entre 2 y 50 caracteres.',
    'auth.error_email_required'       => 'Se requiere una dirección de correo electrónico válida.',
    'auth.error_username_taken'       => 'Ese nombre de usuario ya está en uso.',

    // -------------------------------------------------------------------------
    // User profile
    // -------------------------------------------------------------------------
    'profile.username'       => 'Nombre de usuario',
    'profile.name'           => 'Nombre',
    'profile.email'          => 'Correo electrónico',
    'profile.joined'         => 'Registrado',
    'profile.posts'          => 'Mensajes',
    'profile.last_active'    => 'Última actividad',
    'profile.signature'      => 'Firma',
    'profile.recent_posts'   => 'Mensajes recientes',
    'profile.col_subject'    => 'Asunto',
    'profile.col_date'       => 'Fecha',
    'profile.edit_settings'  => 'Editar configuración',

    // -------------------------------------------------------------------------
    // User settings
    // -------------------------------------------------------------------------
    'settings.title'             => 'Configuración de la cuenta',
    'settings.saved'             => 'Tu configuración ha sido guardada.',
    'settings.identity'          => 'Identidad',
    'settings.display_name'      => 'Nombre visible',
    'settings.email'             => 'Correo electrónico',
    'settings.hide_email'        => 'Ocultar mi correo electrónico en mi perfil',
    'settings.password_section'  => 'Contraseña',
    'settings.password_hint'     => 'Déjalo en blanco para conservar tu contraseña actual.',
    'settings.new_password'      => 'Nueva contraseña',
    'settings.confirm_password'  => 'Confirmar nueva contraseña',
    'settings.signature_section' => 'Firma',
    'settings.signature_text'    => 'Texto de firma',
    'settings.show_signature'    => 'Mostrar firma en mis mensajes',
    'settings.preferences'       => 'Preferencias',
    'settings.threaded_read'     => 'Usar vista en árbol al leer hilos',
    'settings.email_notify'      => 'Enviarme un correo cuando se publiquen mensajes nuevos en los foros a los que estoy suscrito',
    'settings.pm_email_notify'   => 'Enviarme un correo cuando reciba un mensaje privado',
    'settings.tz_offset'         => 'Desfase horario (horas, -12 a +14; -99 = hora del servidor)',
    'settings.save'              => 'Guardar configuración',
    'settings.cancel'            => 'Cancelar',
    'settings.avatar_section'    => 'Avatar',
    'settings.avatar_current'    => 'Avatar actual',
    'settings.avatar_upload'     => 'Subir nuevo avatar',
    'settings.avatar_hint'       => 'JPG, PNG, GIF o WebP. Máximo 100 KB.',
    'settings.avatar_delete'     => 'Eliminar avatar actual',
    'settings.error_display_name_required' => 'El nombre visible es obligatorio.',
    'settings.error_display_name_length'   => 'El nombre visible debe tener 50 caracteres o menos.',
    'settings.error_email_required'        => 'Se requiere una dirección de correo electrónico válida.',
    'settings.error_email_taken'           => 'Esa dirección de correo electrónico ya está en uso por otra cuenta.',
    'settings.error_password_min_length'   => 'La nueva contraseña debe tener al menos 6 caracteres.',
    'settings.error_passwords_mismatch'    => 'Las contraseñas no coinciden.',
    'settings.error_tz_offset'             => 'El desfase horario debe estar entre -12 y +14, o -99 para la hora del servidor.',

    // -------------------------------------------------------------------------
    // Forced password change
    // -------------------------------------------------------------------------
    'force_password_change.title'     => 'Cambia tu contraseña',
    'force_password_change.message'   => 'Un administrador requiere que establezcas una nueva contraseña antes de continuar.',
    'force_password_change.new_password'     => 'Nueva contraseña',
    'force_password_change.confirm_password' => 'Confirmar nueva contraseña',
    'force_password_change.save'      => 'Establecer contraseña',
    'force_password_change.error_password_min_length' => 'La nueva contraseña debe tener al menos 6 caracteres.',
    'force_password_change.error_passwords_mismatch'  => 'Las contraseñas no coinciden.',

    // -------------------------------------------------------------------------
    // Private messages
    // -------------------------------------------------------------------------
    'pm.private_messages'    => 'Mensajes privados',
    'pm.folders'             => 'Carpetas',
    'pm.inbox'               => 'Bandeja de entrada',
    'pm.outbox'              => 'Bandeja de salida',
    'pm.compose'             => 'Redactar',
    'pm.manage_folders'      => 'Administrar carpetas',
    'pm.no_messages'         => 'No hay mensajes.',
    'pm.col_subject'         => 'Asunto',
    'pm.col_from'            => 'De',
    'pm.col_to'              => 'Para',
    'pm.col_date'            => 'Fecha',
    'pm.delete'              => 'Eliminar',
    'pm.compose_title'       => 'Redactar mensaje',
    'pm.to_label'            => 'Para (nombre de usuario)',
    'pm.subject'             => 'Asunto',
    'pm.body'                => 'Mensaje',
    'pm.send'                => 'Enviar',
    'pm.cancel'              => 'Cancelar',
    'pm.reply'               => 'Responder',
    'pm.back_to_inbox'       => 'Volver a la bandeja de entrada',
    'pm.move_to_folder'      => 'Mover a carpeta…',
    'pm.move'                => 'Mover',
    'pm.delete_title'        => 'Eliminar mensaje privado',
    'pm.delete_confirm'      => '¿Eliminar "{subject}" de {author}?',
    'pm.create_folder_title' => 'Crear carpeta',
    'pm.folder_name'         => 'Nombre de la carpeta',
    'pm.create'              => 'Crear',
    'pm.buddy_list'          => 'Buddy List',
    'pm.no_buddies'          => 'You have no buddies yet.',
    'pm.add_buddy'           => 'Add Buddy',
    'pm.remove_buddy'        => 'Remove Buddy',
    'pm.mutual'              => 'Mutual',
    'pm.col_buddy'           => 'User',
    'pm.col_mutual'          => 'Mutual',
    'pm.col_last_active'     => 'Last Active',
    'pm.error_recipient_required'   => 'El destinatario es obligatorio.',
    'pm.error_user_not_found'       => 'No se encontró el usuario "{username}".',
    'pm.error_subject_required'     => 'El asunto es obligatorio.',
    'pm.error_body_required'        => 'El cuerpo del mensaje es obligatorio.',
    'pm.error_folder_name_required' => 'El nombre de la carpeta es obligatorio.',
    'pm.error_folder_name_length'   => 'El nombre de la carpeta debe tener 60 caracteres o menos.',

    // -------------------------------------------------------------------------
    // Thread subscriptions
    // -------------------------------------------------------------------------
    'sub.title'              => 'Seguir hilo',
    'sub.following_email'    => 'Actualmente estás siguiendo este hilo y recibirás notificaciones por correo electrónico de las nuevas respuestas.',
    'sub.bookmarked'         => 'Has marcado este hilo como favorito (sin notificaciones por correo).',
    'sub.not_following'      => 'No estás siguiendo este hilo.',
    'sub.follow_email'       => 'Seguir y notificarme por correo las nuevas respuestas',
    'sub.bookmark'           => 'Marcar como favorito (sin correos)',
    'sub.unfollow'           => 'Dejar de seguir',
    'sub.back_to_thread'     => 'Volver al hilo',
    'sub.follow'             => 'Seguir',
    'sub.confirm_title'      => 'Confirmar acción',
    'sub.confirm_remove'     => '¿Estás seguro de que quieres dejar de seguir este hilo?',
    'sub.confirm_bookmark'   => '¿Cambiar tu suscripción a favorito (sin notificaciones por correo)?',
    'sub.confirm_yes'        => 'Sí, confirmar',
    'sub.confirm_cancel'     => 'Cancelar',

    // -------------------------------------------------------------------------
    // Moderation
    // -------------------------------------------------------------------------
    'mod.delete_thread'            => 'Eliminar hilo',
    'mod.delete_message'           => 'Eliminar mensaje',
    'mod.approve_message'          => 'Aprobar mensaje',
    'mod.close_thread'             => 'Cerrar hilo',
    'mod.reopen_thread'            => 'Reabrir hilo',
    'mod.delete_thread_confirm'    => '¿Estás seguro de que quieres eliminar permanentemente el hilo "{subject}" y todas sus respuestas? Esta acción no se puede deshacer.',
    'mod.delete_message_confirm'   => '¿Estás seguro de que quieres eliminar este mensaje de {author}? Sus respuestas pasarán a depender del mensaje anterior en el hilo.',
    'mod.approve_confirm'          => '¿Aprobar el siguiente mensaje de {author} para que sea visible para todos los lectores?',
    'mod.close_confirm'            => '¿Cerrar el hilo "{subject}"? No se permitirán nuevas respuestas una vez cerrado.',
    'mod.open_confirm'             => '¿Reabrir el hilo "{subject}" para que los miembros puedan publicar nuevas respuestas?',
    'mod.yes_delete'               => 'Sí, eliminar',
    'mod.approve'                  => 'Aprobar',
    'mod.close'                    => 'Cerrar hilo',
    'mod.reopen'                   => 'Reabrir hilo',
    'mod.cancel'                   => 'Cancelar',
    'mod.move_title'               => 'Mover hilo',
    'mod.move_prompt'              => 'Mover "{subject}" a otro foro:',
    'mod.destination'              => 'Foro de destino',
    'mod.choose_forum'             => '— elige un foro —',
    'mod.move_submit'              => 'Mover hilo',
    'mod.merge_title'               => 'Fusionar hilo',
    'mod.merge_prompt'              => 'Fusiona "{subject}" con otro hilo. Los mensajes del hilo fusionado se añadirán al final del hilo de destino, y las suscripciones de este hilo no se conservarán.',
    'mod.merge_target'              => 'ID del hilo de destino',
    'mod.merge_target_hint'         => 'El ID numérico del hilo con el que fusionar (visible en su URL).',
    'mod.merge_submit'              => 'Fusionar hilo',
    'mod.merge_error_not_found'      => 'No se encontró ese ID de hilo.',
    'mod.merge_error_same_thread'    => 'Elige un hilo diferente con el que fusionar.',
    'mod.merge_error_failed'         => 'No se pudo fusionar con ese hilo.',
    'mod.moderate'                 => 'Moderar',
    'mod.queue'                    => 'Cola de revisión',
    'mod.queue_title'              => 'Cola de mensajes pendientes',
    'mod.queue_empty'              => 'No hay mensajes pendientes de aprobación.',
    'mod.queue_forum'              => 'Foro',
    'mod.queue_posted'             => 'Publicado',
    'mod.reports_title'            => 'Contenido reportado',
    'mod.reports_empty'            => 'No hay reportes abiertos.',
    'mod.reports_message_missing'  => '(el mensaje reportado ya no está disponible)',
    'mod.reports_reported'         => 'reportado',
    'mod.reports_resolve'          => 'Resolver',
    'mod.reports_dismiss'          => 'Descartar',
    'mod.reports_view'             => 'Ver en el hilo',
    'report.title'                 => 'Reportar mensaje',
    'report.intro'                 => '¿Reportar este mensaje de {author} a los moderadores?',
    'report.reason_label'          => 'Motivo (opcional)',
    'report.submit'                => 'Enviar reporte',

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------
    'search.title'           => 'Buscar',
    'search.messages_label'  => 'Buscar mensajes',
    'search.author'          => 'Autor',
    'search.match_type'      => 'Tipo de coincidencia',
    'search.all_words'       => 'Todas las palabras',
    'search.any_word'        => 'Cualquier palabra',
    'search.exact_phrase'    => 'Frase exacta',
    'search.posted_within'   => 'Publicado en los últimos',
    'search.last_30'         => 'Últimos 30 días',
    'search.last_90'         => 'Últimos 90 días',
    'search.last_year'       => 'Último año',
    'search.any_time'        => 'Cualquier fecha',
    'search.threads_only'    => 'Solo primeros mensajes de hilo',
    'search.forums_label'    => 'Foros',
    'search.all_forums'      => 'Todos los foros',
    'search.submit'          => 'Buscar',
    'search.no_results'      => 'No se encontraron resultados.',
    'search.showing'         => 'Mostrando',
    'search.of'              => 'de',
    'search.result'          => 'resultado',
    'search.results'         => 'resultados',
    'search.col_subject'     => 'Asunto',
    'search.col_author'      => 'Autor',
    'search.col_forum'       => 'Foro',
    'search.col_date'        => 'Fecha',

    // -------------------------------------------------------------------------
    // Install
    // -------------------------------------------------------------------------
    'install.page_title'               => 'Instalador de Phorum',
    'install.requirements_heading'     => 'Requisitos',
    'install.requirement_failed'       => 'fallido',
    'install.fix_requirements'         => 'Corrige los requisitos anteriores antes de continuar.',
    'install.fix_requirements_hint_1'  => 'Asegúrate de que',
    'install.fix_requirements_hint_and' => 'y',
    'install.fix_requirements_hint_2'  => 'existan (copiados de los archivos .example) y que las credenciales de la base de datos sean correctas.',
    'install.errors_heading'           => 'Por favor, corrige lo siguiente',
    'install.setup_heading'            => 'Configuración del sitio y del administrador',
    'install.site_name_label'          => 'Nombre del sitio',
    'install.admin_account_heading'    => 'Cuenta de administrador',
    'install.username_label'           => 'Nombre de usuario',
    'install.email_label'              => 'Correo electrónico',
    'install.password_label'           => 'Contraseña (mín. 8 caracteres)',
    'install.confirm_password_label'   => 'Confirmar contraseña',
    'install.submit'                   => 'Instalar Phorum',
    'install.complete_page_title'      => 'Instalación completa — Phorum',
    'install.complete_heading'         => 'Instalación completa',
    'install.complete_message'         => 'El esquema de la base de datos se ha creado y tu cuenta de administrador está lista.',
    'install.go_to_forum'              => 'Ir al foro',
    'install.admin_panel'              => 'Panel de administración',
    'install.error_site_name_required'  => 'El nombre del sitio es obligatorio.',
    'install.error_username_required'   => 'El nombre de usuario del administrador es obligatorio.',
    'install.error_username_format'     => 'El nombre de usuario debe tener entre 3 y 50 caracteres (solo letras, números, _ . -).',
    'install.error_email_required'      => 'Se requiere una dirección de correo electrónico de administrador válida.',
    'install.error_password_min_length' => 'La contraseña del administrador debe tener al menos 8 caracteres.',
    'install.error_passwords_mismatch'  => 'Las contraseñas no coinciden.',
    'install.error_failed'              => 'La instalación falló: {message}',

    // -------------------------------------------------------------------------
    // Upgrade (existing Phorum 6 database → Phorum 10)
    // -------------------------------------------------------------------------
    'upgrade.page_title'          => 'Actualización de Phorum',
    'upgrade.detected_heading'    => 'Se detectó una base de datos existente de Phorum 6',
    'upgrade.detected_message'    => 'Esta base de datos fue creada por Phorum 6. Phorum 10 es compatible con el esquema de Phorum 6 — no se cambiará, eliminará ni convertirá ningún dato existente.',
    'upgrade.up_to_date'          => 'No se necesitan cambios de esquema — esta base de datos ya está actualizada.',
    'upgrade.new_tables_heading'  => 'Se añadirán las siguientes tablas nuevas:',
    'upgrade.new_patches_heading' => 'Se aplicarán las siguientes actualizaciones de esquema:',
    'upgrade.submit'              => 'Continuar',
    'upgrade.complete_page_title' => 'Actualización completa — Phorum',
    'upgrade.complete_heading'    => 'Actualización completa',
    'upgrade.complete_message'    => 'Tu base de datos de Phorum 6 ya está lista para ejecutarse en Phorum 10.',
    'upgrade.go_to_forum'         => 'Ir al foro',
    'upgrade.admin_panel'         => 'Panel de administración',

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------
    'error.404_title'        => 'Página no encontrada',
    'error.404_message'      => 'La página que solicitaste no existe.',
    'error.404_return'       => 'Volver al índice del foro.',
    'error.403_title'        => 'Acceso denegado',
    'error.403_message'      => 'No tienes permiso para acceder a este foro.',
    'error.403_login'        => 'Inicia sesión',
    'error.403_login_hint'   => 'para acceder a foros que requieren registro.',
    'error.403_return'       => 'Volver al índice del foro',
    'error.disabled_title'    => 'Sitio no disponible',
    'error.disabled_message'  => 'Este sitio está temporalmente deshabilitado. Por favor, vuelve más tarde.',
    'error.admin_only_title'   => 'Sitio no disponible',
    'error.admin_only_message' => 'Este sitio está temporalmente cerrado por mantenimiento. Por favor, vuelve más tarde.',
    'error.read_only_title'    => 'Solo lectura',
    'error.read_only_message'  => 'Este sitio está actualmente en modo de solo lectura. Publicar e iniciar sesión están temporalmente deshabilitados.',
    'banner.read_only'         => 'Este sitio está actualmente en modo de solo lectura — publicar e iniciar sesión están temporalmente deshabilitados.',

    // -------------------------------------------------------------------------
    // Announcements
    // -------------------------------------------------------------------------
    'announcements.heading' => 'Anuncios',

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
