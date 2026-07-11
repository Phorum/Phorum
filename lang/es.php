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
    'nav.menu'          => 'Menú',

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
    'forum.col_last_post'    => 'Último mensaje',
    'forum.sticky'           => 'Fijado',
    'forum.closed'           => 'Cerrado',
    'forum.by'               => 'por',
    'forum.new'              => 'nuevo',
    'forum.mark_read'        => 'Marcar todo como leído',

    // -------------------------------------------------------------------------
    // Thread view
    // -------------------------------------------------------------------------
    'thread.reply'           => 'Responder',
    'thread.follow'          => 'Seguir',
    'thread.following'       => 'Siguiendo',
    'thread.reopen'          => 'Reabrir',
    'thread.close'           => 'Cerrar',
    'thread.move'            => 'Mover',
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
    'settings.threaded_list'     => 'Usar vista en árbol para las listas del foro',
    'settings.threaded_read'     => 'Usar vista en árbol al leer hilos',
    'settings.email_notify'      => 'Enviarme un correo cuando se publiquen mensajes nuevos en los foros a los que estoy suscrito',
    'settings.pm_email_notify'   => 'Enviarme un correo cuando reciba un mensaje privado',
    'settings.tz_offset'         => 'Desfase horario (horas, -12 a +14; -99 = hora del servidor)',
    'settings.save'              => 'Guardar configuración',
    'settings.cancel'            => 'Cancelar',

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
