<?php
declare(strict_types=1);

/**
 * European Portuguese (pt-PT) regional overrides for Phorum.
 * Only keys that differ from lang/pt.php are listed here.
 * All other strings fall back to lang/pt.php, then lang/en.php.
 *
 * Principal differences from Brazilian Portuguese (pt.php):
 *   - "senha"     → "palavra-passe"
 *   - "usuário"   → "utilizador"
 *   - "salvar"    → "guardar"
 *   - "buscar"    → "pesquisar"
 *   - "registrar" → "registar"
 *   - "gerenciar" → "gerir"
 *   - "você/suas" → "tu/tuas" / impersonal constructions
 *   - "Configurações" (settings menu/page) → "Definições"
 *   - "exibir/exibição" → "mostrar/nome a mostrar"
 */
return [
    '_name' => 'Português (Portugal)',

    // -------------------------------------------------------------------------
    // Navigation
    // -------------------------------------------------------------------------
    'nav.search'    => 'Pesquisar',
    'nav.settings'  => 'Definições',
    'nav.register'  => 'Registar',

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------
    // "usuário" → "utilizador"; "senha" → "palavra-passe"
    'auth.username'          => 'Nome de utilizador',
    'auth.password'          => 'Palavra-passe',
    'auth.password_hint'     => 'pelo menos 6 caracteres',
    'auth.confirm_password'  => 'Confirmar palavra-passe',
    'auth.register_title'    => 'Criar uma conta',
    'auth.register_submit'   => 'Registar',

    // -------------------------------------------------------------------------
    // User profile
    // -------------------------------------------------------------------------
    'profile.username'      => 'Nome de utilizador',
    'profile.edit_settings' => 'Editar definições',

    // -------------------------------------------------------------------------
    // User settings
    // -------------------------------------------------------------------------
    // "Configurações" → "Definições"; "salvar" → "guardar"; "senha" → "palavra-passe"
    // "Suas … foram salvas" → "As suas … foram guardadas"
    // "exibição/exibir" → "nome a mostrar / mostrar"
    'settings.title'            => 'Definições da conta',
    'settings.saved'            => 'As suas definições foram guardadas.',
    'settings.display_name'     => 'Nome a mostrar',
    'settings.hide_email'       => 'Ocultar o meu endereço de e-mail no perfil',
    'settings.password_section' => 'Palavra-passe',
    'settings.password_hint'    => 'Deixe em branco para manter a sua palavra-passe atual.',
    'settings.new_password'     => 'Nova palavra-passe',
    'settings.confirm_password' => 'Confirmar nova palavra-passe',
    'settings.show_signature'   => 'Mostrar assinatura nas minhas mensagens',
    'settings.save'             => 'Guardar definições',

    // -------------------------------------------------------------------------
    // Private messages
    // -------------------------------------------------------------------------
    // "usuário" → "utilizador"; "gerenciar" → "gerir"
    'pm.to_label'        => 'Para (nome de utilizador)',
    'pm.manage_folders'  => 'Gerir pastas',

    // -------------------------------------------------------------------------
    // Thread subscriptions
    // -------------------------------------------------------------------------
    // "Você" → impersonal / "tu" constructions natural in PT-PT
    'sub.following_email' => 'Estás a seguir este tópico e receberás notificações por e-mail sobre novas respostas.',
    'sub.bookmarked'      => 'Marcaste este tópico como favorito (sem notificações por e-mail).',
    'sub.not_following'   => 'Não estás a seguir este tópico.',

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------
    // "buscar" → "pesquisar"
    'search.title'          => 'Pesquisar',
    'search.messages_label' => 'Pesquisar mensagens',
    'search.submit'         => 'Pesquisar',

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------
    // Remove "você" from error messages — use impersonal constructions
    'error.404_message'  => 'A página que solicitou não existe.',
    'error.403_message'  => 'Não tem permissão para aceder a este fórum.',
    'error.403_login'    => 'Inicie sessão',
    'error.403_login_hint' => 'para aceder a fóruns que exigem registo.',

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
