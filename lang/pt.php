<?php
declare(strict_types=1);

/**
 * Portuguese translations for Phorum.
 * Machine-translated — please review with a native speaker.
 */
return [

    // Metadata (not rendered as UI text)
    '_name' => 'Português',

    // -------------------------------------------------------------------------
    // Navigation
    // -------------------------------------------------------------------------
    'nav.forum_index'   => 'Índice do fórum',
    'nav.search'        => 'Buscar',
    'nav.messages'      => 'Mensagens',
    'nav.settings'      => 'Configurações',
    'nav.log_out'       => 'Sair',
    'nav.log_in'        => 'Entrar',
    'nav.register'      => 'Registrar',
    'nav.powered_by'    => 'Desenvolvido por Phorum',
    'nav.skip_to_content' => 'Pular para o conteúdo principal',
    'nav.breadcrumb'    => 'Trilha de navegação',
    'nav.primary'       => 'Principal',
    'nav.menu'          => 'Menu',
    'pagination.nav_label' => 'Paginação',

    // -------------------------------------------------------------------------
    // Forum list (index page)
    // -------------------------------------------------------------------------
    'forum_list.no_forums'   => 'Nenhum fórum foi criado ainda.',
    'forum_list.col_forum'   => 'Fórum',
    'forum_list.col_posts'   => 'Mensagens',
    'forum_list.col_threads' => 'Tópicos',
    'forum_list.col_last'    => 'Última mensagem',

    // -------------------------------------------------------------------------
    // Forum (thread listing)
    // -------------------------------------------------------------------------
    'forum.new_thread'       => 'Novo tópico',
    'forum.no_threads'       => 'Nenhum tópico ainda.',
    'forum.start_one'        => 'Crie um.',
    'forum.col_subject'      => 'Assunto',
    'forum.col_author'       => 'Autor',
    'forum.col_replies'      => 'Respostas',
    'forum.col_posts'        => 'Mensagens',
    'forum.col_last_post'    => 'Última mensagem',
    'forum.sticky'           => 'Fixado',
    'forum.closed'           => 'Fechado',
    'forum.by'               => 'por',
    'forum.new'              => 'novo',
    'forum.mark_read'        => 'Marcar tudo como lido',
    'forum.feed_link'        => 'RSS',

    // -------------------------------------------------------------------------
    // Thread view
    // -------------------------------------------------------------------------
    'thread.reply'           => 'Responder',
    'thread.follow'          => 'Seguir',
    'thread.following'       => 'Seguindo',
    'thread.reopen'          => 'Reabrir',
    'thread.close'           => 'Fechar',
    'thread.move'            => 'Mover',
    'thread.merge'           => 'Mesclar',
    'thread.delete'          => 'Excluir tópico',

    // -------------------------------------------------------------------------
    // Individual message
    // -------------------------------------------------------------------------
    'message.awaiting_approval' => 'Aguardando aprovação',
    'message.reply'             => 'Responder',
    'message.edit'              => 'Editar',
    'message.edit_title'        => 'Editar mensagem',
    'message.save_edit'         => 'Guardar alterações',
    'message.edited_note'       => 'Editado',
    'message.changes'           => 'Changes',
    'message.changes_title'     => 'Edit History',
    'message.changes_edit_n'    => 'Edit #{n}',
    'message.changes_by'        => 'by',
    'message.changes_subject'   => 'Subject',
    'message.changes_body'      => 'Message',
    'message.changes_back'      => 'Back to thread',
    'message.approve'           => 'Aprovar',
    'message.delete'            => 'Excluir',
    'message.report'            => 'Denunciar',
    'message.registered'        => 'Registrado',
    'message.posts'             => 'Mensagens',

    // -------------------------------------------------------------------------
    // Post / reply form
    // -------------------------------------------------------------------------
    'post.new_thread'        => 'Novo tópico',
    'post.reply_to'          => 'Responder a {subject}',
    'post.reply'             => 'Responder',
    'post.subject'           => 'Assunto',
    'post.body'              => 'Mensagem',
    'post.submit_thread'     => 'Publicar tópico',
    'post.submit_reply'      => 'Publicar resposta',
    'post.cancel'            => 'Cancelar',
    'post.preview'           => 'Pré-visualização',
    'post.error_subject_required' => 'O assunto é obrigatório.',
    'post.error_subject_length'   => 'O assunto deve ter no máximo 255 caracteres.',
    'post.error_body_required'    => 'O corpo da mensagem é obrigatório.',
    'post.error_flood_wait'       => 'Aguarde mais {seconds} segundo(s) antes de postar novamente.',
    'post.error_posting_blocked'  => 'Publicar não é permitido para a sua conta.',

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------
    'auth.login_title'       => 'Entrar',
    'auth.username'          => 'Nome de usuário',
    'auth.password'          => 'Senha',
    'auth.remember_me'       => 'Lembrar de mim',
    'auth.login_submit'      => 'Entrar',
    'auth.create_account'    => 'Criar uma conta',
    'auth.register_title'    => 'Criar uma conta',
    'auth.email'             => 'Endereço de e-mail',
    'auth.password_hint'     => 'pelo menos 6 caracteres',
    'auth.confirm_password'  => 'Confirmar senha',
    'auth.register_submit'   => 'Criar conta',
    'auth.have_account'      => 'Já tem uma conta?',
    'auth.login_link'        => 'Entre.',
    'auth.forgot_password'   => 'Esqueceu sua senha?',
    'auth.forgot_title'      => 'Redefinir sua senha',
    'auth.forgot_email_label' => 'Endereço de e-mail',
    'auth.forgot_submit'     => 'Enviar link de redefinição',
    'auth.forgot_sent'       => 'Se esse endereço de e-mail estiver registrado, um link de redefinição foi enviado. Verifique sua caixa de entrada.',
    'auth.reset_title'       => 'Escolha uma nova senha',
    'auth.reset_new_password' => 'Nova senha',
    'auth.reset_confirm'     => 'Confirmar nova senha',
    'auth.reset_submit'      => 'Definir nova senha',
    'auth.reset_invalid'     => 'Este link de redefinição de senha é inválido ou expirou. Por favor, solicite um novo.',
    'auth.reset_success'     => 'Sua senha foi atualizada. Você está conectado agora.',
    'auth.confirm_pending_title'  => 'Verifique seu e-mail',
    'auth.confirm_pending_body'   => 'Enviamos um link de confirmação para {email}. Clique nele para ativar sua conta.',
    'auth.confirm_pending_resend' => 'Reenviar e-mail de confirmação',
    'auth.confirm_invalid'        => 'Este link de confirmação é inválido ou expirou.',
    'auth.resend_title'           => 'Reenviar e-mail de confirmação',
    'auth.resend_email_label'     => 'Endereço de e-mail',
    'auth.resend_submit'          => 'Reenviar',
    'auth.resend_sent'            => 'Se esse endereço tiver uma confirmação pendente, um novo link foi enviado. Verifique sua caixa de entrada.',
    'auth.error_missing_credentials'  => 'Por favor, insira seu nome de usuário e senha.',
    'auth.error_invalid_credentials'  => 'Nome de usuário ou senha inválidos.',
    'auth.error_registration_blocked' => 'O registro não é permitido para a sua conta.',
    'auth.error_invalid_email'        => 'Por favor, insira um endereço de e-mail válido.',
    'auth.error_password_min_length'  => 'A senha deve ter pelo menos 6 caracteres.',
    'auth.error_passwords_mismatch'   => 'As senhas não coincidem.',
    'auth.error_username_required'    => 'O nome de usuário é obrigatório.',
    'auth.error_username_length'      => 'O nome de usuário deve ter entre 2 e 50 caracteres.',
    'auth.error_email_required'       => 'É necessário um endereço de e-mail válido.',
    'auth.error_username_taken'       => 'Esse nome de usuário já está em uso.',

    // -------------------------------------------------------------------------
    // OAuth login
    // -------------------------------------------------------------------------
    'oauth.button_google' => 'Continuar com o Google',
    'oauth.button_github' => 'Continuar com o GitHub',
    'oauth.error_provider_error'        => 'O login foi cancelado ou o provedor retornou um erro. Tente novamente.',
    'oauth.error_state_mismatch'        => 'Sua sessão de login expirou ou é inválida. Tente novamente.',
    'oauth.error_token_exchange_failed' => 'Não foi possível concluir o login com esse provedor. Tente novamente.',
    'oauth.error_email_not_verified'    => 'Seu endereço de e-mail não está verificado com esse provedor, então não podemos fazer seu login. Verifique seu e-mail com o provedor e tente novamente.',
    'oauth.error_login_failed'          => 'Algo deu errado ao fazer login. Tente novamente.',
    'oauth.error_account_inactive'      => 'Sua conta ainda não está ativa. Verifique seu e-mail para um link de confirmação.',
    'oauth.error_not_configured'        => 'Essa opção de login não está disponível no momento.',

    // -------------------------------------------------------------------------
    // User profile
    // -------------------------------------------------------------------------
    'profile.username'       => 'Nome de usuário',
    'profile.name'           => 'Nome',
    'profile.email'          => 'E-mail',
    'profile.joined'         => 'Membro desde',
    'profile.posts'          => 'Mensagens',
    'profile.last_active'    => 'Última atividade',
    'profile.signature'      => 'Assinatura',
    'profile.recent_posts'   => 'Mensagens recentes',
    'profile.col_subject'    => 'Assunto',
    'profile.col_date'       => 'Data',
    'profile.edit_settings'  => 'Editar configurações',

    // -------------------------------------------------------------------------
    // User settings
    // -------------------------------------------------------------------------
    'settings.title'             => 'Configurações da conta',
    'settings.saved'             => 'Suas configurações foram salvas.',
    'settings.identity'          => 'Identidade',
    'settings.display_name'      => 'Nome de exibição',
    'settings.email'             => 'Endereço de e-mail',
    'settings.hide_email'        => 'Ocultar meu endereço de e-mail no perfil',
    'settings.password_section'  => 'Senha',
    'settings.password_hint'     => 'Deixe em branco para manter sua senha atual.',
    'settings.new_password'      => 'Nova senha',
    'settings.confirm_password'  => 'Confirmar nova senha',
    'settings.signature_section' => 'Assinatura',
    'settings.signature_text'    => 'Texto da assinatura',
    'settings.show_signature'    => 'Exibir assinatura nas minhas mensagens',
    'settings.preferences'       => 'Preferências',
    'settings.threaded_read'     => 'Usar visualização em árvore ao ler tópicos',
    'settings.email_notify'      => 'Enviar e-mail quando novas mensagens forem postadas nos fóruns que sigo',
    'settings.pm_email_notify'   => 'Enviar e-mail quando eu receber uma mensagem privada',
    'settings.tz_offset'         => 'Fuso horário (horas, -12 a +14; -99 = horário do servidor)',
    'settings.save'              => 'Salvar configurações',
    'settings.cancel'            => 'Cancelar',
    'settings.avatar_section'    => 'Avatar',
    'settings.avatar_current'    => 'Avatar atual',
    'settings.avatar_upload'     => 'Enviar novo avatar',
    'settings.avatar_hint'       => 'JPG, PNG, GIF ou WebP. Máximo de 100 KB.',
    'settings.avatar_delete'     => 'Remover avatar atual',
    'settings.error_display_name_required' => 'O nome de exibição é obrigatório.',
    'settings.error_display_name_length'   => 'O nome de exibição deve ter no máximo 50 caracteres.',
    'settings.error_email_required'        => 'É necessário um endereço de e-mail válido.',
    'settings.error_email_taken'           => 'Esse endereço de e-mail já está em uso por outra conta.',
    'settings.error_password_min_length'   => 'A nova senha deve ter pelo menos 6 caracteres.',
    'settings.error_passwords_mismatch'    => 'As senhas não coincidem.',
    'settings.error_tz_offset'             => 'O fuso horário deve estar entre -12 e +14, ou -99 para o horário do servidor.',

    // -------------------------------------------------------------------------
    // Forced password change
    // -------------------------------------------------------------------------
    'force_password_change.title'     => 'Altere sua senha',
    'force_password_change.message'   => 'Um administrador exige que você defina uma nova senha antes de continuar.',
    'force_password_change.new_password'     => 'Nova senha',
    'force_password_change.confirm_password' => 'Confirmar nova senha',
    'force_password_change.save'      => 'Definir senha',
    'force_password_change.error_password_min_length' => 'A nova senha deve ter pelo menos 6 caracteres.',
    'force_password_change.error_passwords_mismatch'  => 'As senhas não coincidem.',

    // -------------------------------------------------------------------------
    // Private messages
    // -------------------------------------------------------------------------
    'pm.private_messages'    => 'Mensagens privadas',
    'pm.folders'             => 'Pastas',
    'pm.inbox'               => 'Caixa de entrada',
    'pm.outbox'              => 'Caixa de saída',
    'pm.compose'             => 'Escrever',
    'pm.manage_folders'      => 'Gerenciar pastas',
    'pm.no_messages'         => 'Nenhuma mensagem.',
    'pm.col_subject'         => 'Assunto',
    'pm.col_from'            => 'De',
    'pm.col_to'              => 'Para',
    'pm.col_date'            => 'Data',
    'pm.delete'              => 'Excluir',
    'pm.compose_title'       => 'Escrever mensagem',
    'pm.to_label'            => 'Para (nome de usuário)',
    'pm.subject'             => 'Assunto',
    'pm.body'                => 'Mensagem',
    'pm.send'                => 'Enviar',
    'pm.cancel'              => 'Cancelar',
    'pm.reply'               => 'Responder',
    'pm.back_to_inbox'       => 'Voltar à caixa de entrada',
    'pm.move_to_folder'      => 'Mover para pasta…',
    'pm.move'                => 'Mover',
    'pm.delete_title'        => 'Excluir mensagem privada',
    'pm.delete_confirm'      => 'Excluir "{subject}" de {author}?',
    'pm.create_folder_title' => 'Criar pasta',
    'pm.folder_name'         => 'Nome da pasta',
    'pm.create'              => 'Criar',
    'pm.buddy_list'          => 'Buddy List',
    'pm.no_buddies'          => 'You have no buddies yet.',
    'pm.add_buddy'           => 'Add Buddy',
    'pm.remove_buddy'        => 'Remove Buddy',
    'pm.mutual'              => 'Mutual',
    'pm.col_buddy'           => 'User',
    'pm.col_mutual'          => 'Mutual',
    'pm.col_last_active'     => 'Last Active',
    'pm.error_recipient_required'   => 'O destinatário é obrigatório.',
    'pm.error_user_not_found'       => 'Usuário "{username}" não encontrado.',
    'pm.error_subject_required'     => 'O assunto é obrigatório.',
    'pm.error_body_required'        => 'O corpo da mensagem é obrigatório.',
    'pm.error_folder_name_required' => 'O nome da pasta é obrigatório.',
    'pm.error_folder_name_length'   => 'O nome da pasta deve ter no máximo 60 caracteres.',

    // -------------------------------------------------------------------------
    // Thread subscriptions
    // -------------------------------------------------------------------------
    'sub.title'              => 'Seguir tópico',
    'sub.following_email'    => 'Você está seguindo este tópico e receberá notificações por e-mail sobre novas respostas.',
    'sub.bookmarked'         => 'Você marcou este tópico como favorito (sem notificações por e-mail).',
    'sub.not_following'      => 'Você não está seguindo este tópico.',
    'sub.follow_email'       => 'Seguir e receber e-mail sobre novas respostas',
    'sub.bookmark'           => 'Favoritar (sem e-mails)',
    'sub.unfollow'           => 'Deixar de seguir',
    'sub.back_to_thread'     => 'Voltar ao tópico',
    'sub.follow'             => 'Seguir',
    'sub.confirm_title'      => 'Confirmar ação',
    'sub.confirm_remove'     => 'Tem certeza de que deseja deixar de seguir este tópico?',
    'sub.confirm_bookmark'   => 'Mudar sua inscrição para favorito (sem notificações por e-mail)?',
    'sub.confirm_yes'        => 'Sim, confirmar',
    'sub.confirm_cancel'     => 'Cancelar',

    // -------------------------------------------------------------------------
    // Moderation
    // -------------------------------------------------------------------------
    'mod.delete_thread'            => 'Excluir tópico',
    'mod.delete_message'           => 'Excluir mensagem',
    'mod.approve_message'          => 'Aprovar mensagem',
    'mod.close_thread'             => 'Fechar tópico',
    'mod.reopen_thread'            => 'Reabrir tópico',
    'mod.delete_thread_confirm'    => 'Tem certeza de que deseja excluir permanentemente o tópico "{subject}" e todas as suas respostas? Esta ação não pode ser desfeita.',
    'mod.delete_message_confirm'   => 'Tem certeza de que deseja excluir esta mensagem de {author}? As respostas serão vinculadas à mensagem anterior no tópico.',
    'mod.approve_confirm'          => 'Aprovar a seguinte mensagem de {author} para que fique visível a todos os leitores?',
    'mod.close_confirm'            => 'Fechar o tópico "{subject}"? Nenhuma nova resposta será permitida após o fechamento.',
    'mod.open_confirm'             => 'Reabrir o tópico "{subject}" para que os membros possam publicar novas respostas?',
    'mod.yes_delete'               => 'Sim, excluir',
    'mod.approve'                  => 'Aprovar',
    'mod.close'                    => 'Fechar tópico',
    'mod.reopen'                   => 'Reabrir tópico',
    'mod.cancel'                   => 'Cancelar',
    'mod.move_title'               => 'Mover tópico',
    'mod.move_prompt'              => 'Mover "{subject}" para outro fórum:',
    'mod.destination'              => 'Fórum de destino',
    'mod.choose_forum'             => '— escolha um fórum —',
    'mod.move_submit'              => 'Mover tópico',
    'mod.merge_title'               => 'Mesclar tópico',
    'mod.merge_prompt'              => 'Mesclar "{subject}" em outro tópico. As mensagens do tópico mesclado serão anexadas ao tópico de destino, e as inscrições deste tópico não serão preservadas.',
    'mod.merge_target'              => 'ID do tópico de destino',
    'mod.merge_target_hint'         => 'O ID numérico do tópico no qual mesclar (visível em sua URL).',
    'mod.merge_submit'              => 'Mesclar tópico',
    'mod.merge_error_not_found'     => 'Esse ID de tópico não foi encontrado.',
    'mod.merge_error_same_thread'   => 'Escolha um tópico diferente para mesclar.',
    'mod.merge_error_failed'        => 'Não foi possível mesclar nesse tópico.',
    'mod.moderate'                 => 'Moderar',
    'mod.queue'                    => 'Fila de revisão',
    'mod.queue_title'              => 'Fila de mensagens pendentes',
    'mod.queue_empty'              => 'Nenhuma mensagem aguardando aprovação.',
    'mod.queue_forum'              => 'Fórum',
    'mod.queue_posted'             => 'Publicado',
    'mod.reports_title'            => 'Conteúdo denunciado',
    'mod.reports_empty'            => 'Nenhuma denúncia em aberto.',
    'mod.reports_message_missing'  => '(mensagem denunciada não está mais disponível)',
    'mod.reports_reported'         => 'denunciado',
    'mod.reports_resolve'          => 'Resolver',
    'mod.reports_dismiss'          => 'Descartar',
    'mod.reports_view'             => 'Ver no tópico',
    'report.title'                 => 'Denunciar mensagem',
    'report.intro'                 => 'Denunciar esta mensagem de {author} aos moderadores?',
    'report.reason_label'          => 'Motivo (opcional)',
    'report.submit'                => 'Enviar denúncia',

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------
    'search.title'           => 'Buscar',
    'search.messages_label'  => 'Buscar mensagens',
    'search.author'          => 'Autor',
    'search.match_type'      => 'Tipo de correspondência',
    'search.all_words'       => 'Todas as palavras',
    'search.any_word'        => 'Qualquer palavra',
    'search.exact_phrase'    => 'Frase exata',
    'search.posted_within'   => 'Publicado nos últimos',
    'search.last_30'         => 'Últimos 30 dias',
    'search.last_90'         => 'Últimos 90 dias',
    'search.last_year'       => 'Último ano',
    'search.any_time'        => 'Qualquer data',
    'search.threads_only'    => 'Somente primeiras mensagens do tópico',
    'search.forums_label'    => 'Fóruns',
    'search.all_forums'      => 'Todos os fóruns',
    'search.submit'          => 'Buscar',
    'search.no_results'      => 'Nenhum resultado encontrado.',
    'search.showing'         => 'Exibindo',
    'search.of'              => 'de',
    'search.result'          => 'resultado',
    'search.results'         => 'resultados',
    'search.col_subject'     => 'Assunto',
    'search.col_author'      => 'Autor',
    'search.col_forum'       => 'Fórum',
    'search.col_date'        => 'Data',

    // -------------------------------------------------------------------------
    // Install
    // -------------------------------------------------------------------------
    'install.page_title'               => 'Instalador do Phorum',
    'install.requirements_heading'     => 'Requisitos',
    'install.requirement_failed'       => 'falhou',
    'install.fix_requirements'         => 'Corrija os requisitos acima antes de continuar.',
    'install.fix_requirements_hint_1'  => 'Certifique-se de que',
    'install.fix_requirements_hint_and' => 'e',
    'install.fix_requirements_hint_2'  => 'existam (copiados dos arquivos .example) e que as credenciais do banco de dados estejam corretas.',
    'install.errors_heading'           => 'Por favor, corrija o seguinte',
    'install.setup_heading'            => 'Configuração do site e do administrador',
    'install.site_name_label'          => 'Nome do site',
    'install.admin_account_heading'    => 'Conta de administrador',
    'install.username_label'           => 'Nome de usuário',
    'install.email_label'              => 'E-mail',
    'install.password_label'           => 'Senha (mín. 8 caracteres)',
    'install.confirm_password_label'   => 'Confirmar senha',
    'install.submit'                   => 'Instalar Phorum',
    'install.complete_page_title'      => 'Instalação concluída — Phorum',
    'install.complete_heading'         => 'Instalação concluída',
    'install.complete_message'         => 'O esquema do banco de dados foi criado e sua conta de administrador está pronta.',
    'install.go_to_forum'              => 'Ir para o fórum',
    'install.admin_panel'              => 'Painel de administração',
    'install.error_site_name_required'  => 'O nome do site é obrigatório.',
    'install.error_username_required'   => 'O nome de usuário do administrador é obrigatório.',
    'install.error_username_format'     => 'O nome de usuário deve ter de 3 a 50 caracteres (apenas letras, números, _ . -).',
    'install.error_email_required'      => 'É necessário um endereço de e-mail de administrador válido.',
    'install.error_password_min_length' => 'A senha do administrador deve ter pelo menos 8 caracteres.',
    'install.error_passwords_mismatch'  => 'As senhas não coincidem.',
    'install.error_failed'              => 'Falha na instalação: {message}',

    // -------------------------------------------------------------------------
    // Upgrade (existing Phorum 6 database → Phorum 10)
    // -------------------------------------------------------------------------
    'upgrade.page_title'          => 'Atualização do Phorum',
    'upgrade.detected_heading'    => 'Banco de dados existente do Phorum 6 detectado',
    'upgrade.detected_message'    => 'Este banco de dados foi criado pelo Phorum 6. O Phorum 10 é compatível com o esquema do Phorum 6 — nenhum dado existente será alterado, excluído ou convertido.',
    'upgrade.up_to_date'          => 'Nenhuma alteração de esquema é necessária — este banco de dados já está atualizado.',
    'upgrade.new_tables_heading'  => 'As seguintes novas tabelas serão adicionadas:',
    'upgrade.new_patches_heading' => 'As seguintes atualizações de esquema serão aplicadas:',
    'upgrade.submit'              => 'Continuar',
    'upgrade.complete_page_title' => 'Atualização concluída — Phorum',
    'upgrade.complete_heading'    => 'Atualização concluída',
    'upgrade.complete_message'    => 'Seu banco de dados do Phorum 6 agora está pronto para rodar no Phorum 10.',
    'upgrade.go_to_forum'         => 'Ir para o fórum',
    'upgrade.admin_panel'         => 'Painel de administração',

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------
    'error.404_title'        => 'Página não encontrada',
    'error.404_message'      => 'A página que você solicitou não existe.',
    'error.404_return'       => 'Voltar ao índice do fórum.',
    'error.403_title'        => 'Acesso negado',
    'error.403_message'      => 'Você não tem permissão para acessar este fórum.',
    'error.403_login'        => 'Entre',
    'error.403_login_hint'   => 'para acessar fóruns que exigem registro.',
    'error.403_return'       => 'Voltar ao índice do fórum',
    'error.disabled_title'    => 'Site indisponível',
    'error.disabled_message'  => 'Este site está temporariamente desativado. Por favor, volte mais tarde.',
    'error.admin_only_title'   => 'Site indisponível',
    'error.admin_only_message' => 'Este site está temporariamente fechado para manutenção. Por favor, volte mais tarde.',
    'error.read_only_title'    => 'Somente leitura',
    'error.read_only_message'  => 'Este site está atualmente em modo somente leitura. Publicar e entrar estão temporariamente desativados.',
    'banner.read_only'         => 'Este site está atualmente em modo somente leitura — publicar e entrar estão temporariamente desativados.',

    // -------------------------------------------------------------------------
    // Announcements
    // -------------------------------------------------------------------------
    'announcements.heading' => 'Anúncios',

    // -------------------------------------------------------------------------
    // Attachments
    // -------------------------------------------------------------------------
    'attachment.label'      => 'Attachments',
    'attachment.add'        => 'Add files',
    'attachment.existing'   => 'Existing attachments',
    'attachment.remove'     => 'Remove',
    'attachment.hint_count' => 'Up to {n} file(s).',
    'attachment.hint_size'  => 'Max {size} per file.',
    'attachment.error_uploads_disabled' => 'O envio de arquivos está desativado no momento.',
];
