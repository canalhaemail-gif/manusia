<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/customer_message_scene.php';
require_once __DIR__ . '/../includes/customer_messages.php';
require_once __DIR__ . '/../includes/admin_message_queue.php';
require_once __DIR__ . '/../includes/admin_whatsapp_queue.php';
require_once __DIR__ . '/../includes/admin_message_send_log.php';
require_once __DIR__ . '/../includes/whatsapp_evolution.php';

require_admin_auth();

if (isset($_GET['whatsapp_connection_status'])) {
    $refreshQr = isset($_GET['refresh']) && (string) $_GET['refresh'] === '1';
    whatsapp_evolution_debug_log('admin_mensagens.whatsapp_connection_status_requested', [
        'refresh_qr' => $refreshQr,
        'query' => $_GET,
    ]);
    $payload = whatsapp_evolution_connection_payload($refreshQr);
    whatsapp_evolution_debug_log('admin_mensagens.whatsapp_connection_status_completed', [
        'refresh_qr' => $refreshQr,
        'payload' => $payload,
    ]);

    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_message_target_customers(string $recipientMode, int $customerId = 0): array
{
    ensure_customer_email_marketing_columns();

    if ($recipientMode === 'inactive_45') {
        if (table_exists('pedidos')) {
            return db()->query(
                'SELECT c.id, c.nome, c.email, c.telefone, c.email_marketing_opt_out_at, c.email_marketing_unsubscribe_token
                 FROM clientes c
                 LEFT JOIN pedidos p ON p.cliente_id = c.id
                 WHERE c.ativo = 1
                 GROUP BY c.id, c.nome, c.email, c.telefone, c.email_marketing_opt_out_at, c.email_marketing_unsubscribe_token, c.criado_em
                 HAVING COALESCE(MAX(p.criado_em), c.criado_em) < DATE_SUB(NOW(), INTERVAL 45 DAY)
                 ORDER BY c.nome ASC'
            )->fetchAll();
        }

        return db()->query(
            'SELECT id, nome, email, telefone, email_marketing_opt_out_at, email_marketing_unsubscribe_token
             FROM clientes
             WHERE ativo = 1
               AND criado_em < DATE_SUB(NOW(), INTERVAL 45 DAY)
             ORDER BY nome ASC'
        )->fetchAll();
    }

    if ($recipientMode === 'customer' && $customerId > 0) {
        $statement = db()->prepare(
            'SELECT id, nome, email, telefone, email_marketing_opt_out_at, email_marketing_unsubscribe_token
             FROM clientes
             WHERE ativo = 1
               AND id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $customerId]);

        return $statement->fetchAll();
    }

    return db()->query(
        'SELECT id, nome, email, telefone, email_marketing_opt_out_at, email_marketing_unsubscribe_token
         FROM clientes
         WHERE ativo = 1
         ORDER BY nome ASC'
    )->fetchAll();
}

function admin_message_normalize_link(?string $value): ?string
{
    return customer_message_normalize_link($value);
}

function admin_message_layers_json_value(string $value): string
{
    $layers = customer_message_editor_layers(['email_editor_layers' => $value]);

    return json_encode($layers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function admin_message_scene_json_value(?string $value, array $draft = []): string
{
    $value = trim((string) $value);

    if ($value !== '') {
        $decoded = json_decode($value, true);
        $scene = is_array($decoded)
            ? customer_message_scene_normalize($decoded)
            : customer_message_scene_from_context(['scene_json' => $value] + $draft);
        $heroImagePath = trim((string) ($draft['hero_image_path'] ?? ''));
        if ($heroImagePath !== '') {
            $scene['canvas']['backgroundImage'] = $heroImagePath;
        }
        $scene = admin_message_scene_apply_title_override($scene, $draft);

        return json_encode(customer_message_scene_normalize($scene), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ?: customer_message_scene_json($draft);
    }

    return customer_message_scene_json(admin_message_scene_apply_title_draft_defaults($draft));
}

function admin_message_scene_apply_title_override(array $scene, array $draft = []): array
{
    $title = customer_message_scene_normalize_text_content((string) ($draft['title'] ?? ''), false);
    if ($title === '') {
        return $scene;
    }

    $updated = false;
    foreach ((array) ($scene['layers'] ?? []) as $index => $layer) {
        if (!is_array($layer)) {
            continue;
        }

        if (($layer['type'] ?? '') !== 'text' || ($layer['role'] ?? '') !== 'title') {
            continue;
        }

        $scene['layers'][$index]['textRaw'] = $title;
        $updated = true;
        break;
    }

    if ($updated) {
        return $scene;
    }

    $draftWithDefaults = admin_message_scene_apply_title_draft_defaults($draft);
    $fallbackScene = customer_message_scene_from_context($draftWithDefaults);
    $fallbackLayers = customer_message_scene_layers_by_role($fallbackScene);
    $fallbackTitle = is_array($fallbackLayers['title'] ?? null) ? $fallbackLayers['title'] : null;

    if ($fallbackTitle !== null) {
        $fallbackTitle['textRaw'] = $title;
        $scene['layers'][] = $fallbackTitle;
    }

    return $scene;
}

function admin_message_scene_apply_title_draft_defaults(array $draft): array
{
    $title = customer_message_scene_normalize_text_content((string) ($draft['title'] ?? ''), false);
    if ($title === '') {
        return $draft;
    }

    $draft['show_title'] = '1';
    $draft['title'] = $title;

    return $draft;
}

function admin_message_editor_layers_json_from_scene_value(?string $value, array $draft = []): string
{
    $raw = trim((string) $value);

    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        $scene = is_array($decoded)
            ? customer_message_scene_normalize($decoded)
            : customer_message_scene_from_context(['scene_json' => $raw] + $draft);
        $heroImagePath = trim((string) ($draft['hero_image_path'] ?? ''));
        if ($heroImagePath !== '') {
            $scene['canvas']['backgroundImage'] = $heroImagePath;
        }
        return json_encode(
            customer_message_scene_to_editor_layers($scene),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '[]';
    }

    return admin_message_layers_json_value((string) ($draft['editor_layers_json'] ?? '[]'));
}

function admin_message_whatsapp_button_enabled(array $draft): bool
{
    return (($draft['whatsapp_button_enabled'] ?? '0') === '1');
}

function admin_message_whatsapp_link_preview_enabled(array $draft): bool
{
    return (($draft['whatsapp_link_preview_enabled'] ?? '0') === '1');
}

function admin_message_whatsapp_mode(array $draft): string
{
    $url = trim((string) ($draft['whatsapp_button_url'] ?? ''));

    if (admin_message_whatsapp_button_enabled($draft) && $url !== '') {
        return 'button';
    }

    if (admin_message_whatsapp_link_preview_enabled($draft) && $url !== '') {
        return 'link_preview';
    }

    return 'media';
}

function admin_message_default_draft(): array
{
    return [
        'project_id' => '',
        'project_name' => '',
        'recipient_mode' => 'all',
        'customer_id' => '',
        'message_kind' => 'manual',
        'title' => '',
        'message' => '',
        'link_url' => '',
        'image_link_url' => '',
        'button_label' => '',
        'button_font_size' => '24',
        'hero_image_path' => '',
        'whatsapp_message' => '',
        'whatsapp_image_path' => '',
        'whatsapp_mode' => 'media',
        'whatsapp_button_enabled' => '0',
        'whatsapp_link_preview_enabled' => '0',
        'whatsapp_button_title' => '',
        'whatsapp_button_label' => '',
        'whatsapp_button_url' => '',
        'whatsapp_button_footer' => 'Moda Tropical',
        'editor_layers_json' => '[]',
        'scene_json' => '',
        'fabric_scene_json' => '',
        'editor_engine' => 'fabric_v2',
        'show_title' => '0',
        'show_body' => '0',
        'show_button' => '0',
        'show_image_hotspot' => '0',
        'title_x' => '8',
        'title_y' => '10',
        'title_width' => '72',
        'body_x' => '8',
        'body_y' => '30',
        'body_width' => '72',
        'title_size' => '78',
        'body_size' => '24',
        'title_line_height' => '104',
        'body_line_height' => '175',
        'title_align' => 'left',
        'body_align' => 'left',
        'title_bold' => '1',
        'body_bold' => '0',
        'title_italic' => '0',
        'body_italic' => '0',
        'title_uppercase' => '0',
        'body_uppercase' => '0',
        'title_shadow' => 'strong',
        'body_shadow' => 'soft',
        'title_color' => '#fff7f0',
        'body_color' => '#2c1917',
        'button_x' => '24',
        'button_y' => '82',
        'button_width' => '26',
        'button_height' => '11',
        'image_hotspot_x' => '33',
        'image_hotspot_y' => '47',
        'image_hotspot_width' => '34',
        'image_hotspot_height' => '32',
        'send_notification' => '1',
        'send_email' => '1',
        'send_whatsapp' => '0',
    ];
}

function admin_message_whatsapp_payload_empty(array $draft): bool
{
    return trim((string) ($draft['whatsapp_message'] ?? '')) === ''
        && trim((string) ($draft['whatsapp_image_path'] ?? '')) === ''
        && trim((string) ($draft['whatsapp_button_title'] ?? '')) === ''
        && trim((string) ($draft['whatsapp_button_label'] ?? '')) === ''
        && trim((string) ($draft['whatsapp_button_url'] ?? '')) === ''
        && (string) ($draft['whatsapp_button_enabled'] ?? '0') !== '1'
        && (string) ($draft['whatsapp_link_preview_enabled'] ?? '0') !== '1';
}

function admin_message_email_payload_available(array $draft): bool
{
    $sceneLayerCount = admin_message_debug_json_count(
        (string) (($draft['fabric_scene_json'] ?? '') !== '' ? ($draft['fabric_scene_json'] ?? '') : ($draft['scene_json'] ?? '')),
        'scene'
    );

    return trim((string) ($draft['title'] ?? '')) !== ''
        || trim((string) ($draft['message'] ?? '')) !== ''
        || trim((string) ($draft['hero_image_path'] ?? '')) !== ''
        || trim((string) ($draft['image_link_url'] ?? '')) !== ''
        || trim((string) ($draft['link_url'] ?? '')) !== ''
        || $sceneLayerCount > 0;
}

function admin_message_apply_whatsapp_boot_fallback(array $draft, string $requestedChannel): array
{
    $meta = [
        'applied' => false,
        'reason' => 'channel_not_whatsapp',
        'fields' => [],
    ];

    if ($requestedChannel !== 'whatsapp') {
        return [
            'draft' => $draft,
            'meta' => $meta,
        ];
    }

    if (!admin_message_whatsapp_payload_empty($draft)) {
        $meta['reason'] = 'whatsapp_has_saved_payload';

        return [
            'draft' => $draft,
            'meta' => $meta,
        ];
    }

    if (!admin_message_email_payload_available($draft)) {
        $meta['reason'] = 'email_has_no_fallback_content';

        return [
            'draft' => $draft,
            'meta' => $meta,
        ];
    }

    $title = trim((string) ($draft['title'] ?? ''));
    $fallbackMessage = trim((string) ($draft['message'] ?? ''));
    if ($fallbackMessage === '') {
        $fallbackScene = customer_message_scene_from_context($draft);
        $fallbackMessage = trim(customer_message_scene_body_text($fallbackScene, $title));
    }

    $fallbackImagePath = trim((string) ($draft['hero_image_path'] ?? ''));
    $fallbackUrl = trim((string) ($draft['image_link_url'] ?? ''));
    if ($fallbackUrl === '') {
        $fallbackUrl = trim((string) ($draft['link_url'] ?? ''));
    }
    $fallbackUrl = admin_message_normalize_link($fallbackUrl) ?? '';

    if ($fallbackMessage !== '') {
        $draft['whatsapp_message'] = $fallbackMessage;
        $meta['fields'][] = 'whatsapp_message';
    }

    if ($fallbackImagePath !== '') {
        $draft['whatsapp_image_path'] = $fallbackImagePath;
        $meta['fields'][] = 'whatsapp_image_path';
    }

    if (trim((string) ($draft['whatsapp_button_url'] ?? '')) === '' && $fallbackUrl !== '') {
        $draft['whatsapp_button_url'] = $fallbackUrl;
        $meta['fields'][] = 'whatsapp_button_url';
    }

    if (trim((string) ($draft['whatsapp_button_footer'] ?? '')) === '') {
        $draft['whatsapp_button_footer'] = 'Moda Tropical';
    }

    if ($meta['fields'] === []) {
        $meta['reason'] = 'email_has_no_reusable_fields';

        return [
            'draft' => $draft,
            'meta' => $meta,
        ];
    }

    $meta['applied'] = true;
    $meta['reason'] = 'email_payload_bootstrap';

    return [
        'draft' => $draft,
        'meta' => $meta,
    ];
}

function admin_message_projects_file(): string
{
    return BASE_PATH . '/storage/messages/projects.json';
}

function admin_message_normalize_www_data_permissions(string $path, int $mode = 0664): void
{
    if (!file_exists($path)) {
        return;
    }

    @chmod($path, $mode);

    if (!function_exists('posix_geteuid') || (int) posix_geteuid() !== 0) {
        return;
    }

    $user = function_exists('posix_getpwnam') ? @posix_getpwnam('www-data') : false;
    $group = function_exists('posix_getgrnam') ? @posix_getgrnam('www-data') : false;

    if (is_array($user) && isset($user['uid'])) {
        @chown($path, (int) $user['uid']);
    }

    if (is_array($group) && isset($group['gid'])) {
        @chgrp($path, (int) $group['gid']);
    }
}

function admin_message_projects_normalize_permissions(string $file): void
{
    if (!is_file($file)) {
        return;
    }

    admin_message_normalize_www_data_permissions($file, 0664);
}

function admin_message_projects_load(): array
{
    $file = admin_message_projects_file();

    if (!is_file($file)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($file), true);

    return is_array($decoded) ? $decoded : [];
}

function admin_message_projects_save(array $projects): void
{
    $file = admin_message_projects_file();
    $directory = dirname($file);

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Nao foi possivel criar a pasta de projetos salvos.');
    }

    if (file_put_contents($file, json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
        throw new RuntimeException('Nao foi possivel salvar o projeto.');
    }

    admin_message_projects_normalize_permissions($file);
}

function admin_message_project_slug(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = preg_replace('/[^a-z0-9]+/i', '-', $normalized) ?: '';
    $normalized = trim((string) $normalized, '-');

    return $normalized !== '' ? $normalized : 'projeto';
}

function admin_message_project_find(string $projectId, array $projects): ?array
{
    foreach ($projects as $project) {
        if ((string) ($project['id'] ?? '') === $projectId) {
            return is_array($project) ? $project : null;
        }
    }

    return null;
}

function admin_message_project_assets_relative_dir(): string
{
    return 'uploads/store/message-assets';
}

function admin_message_whatsapp_assets_relative_dir(): string
{
    return 'uploads/store/whatsapp-assets';
}

function admin_message_project_assets_directory(): string
{
    return BASE_PATH . '/' . admin_message_project_assets_relative_dir();
}

function admin_message_whatsapp_assets_directory(): string
{
    return BASE_PATH . '/' . admin_message_whatsapp_assets_relative_dir();
}

function admin_message_project_assets_prepare_directory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Nao foi possivel criar a biblioteca de imagens da loja.');
    }

    admin_message_normalize_www_data_permissions($directory, 0775);

    if (!is_writable($directory)) {
        throw new RuntimeException('A biblioteca de imagens da loja nao esta gravavel.');
    }
}

function admin_message_project_persist_asset(
    string $projectId,
    string $assetPath,
    string $relativeDirectory,
    string $suffix,
    string $errorMessage
): string {
    $assetPath = trim($assetPath);
    if ($assetPath === '' || !str_starts_with($assetPath, 'uploads/')) {
        return $assetPath;
    }

    $sourcePath = BASE_PATH . '/' . ltrim($assetPath, '/');
    if (!is_file($sourcePath)) {
        return $assetPath;
    }

    if (str_starts_with($assetPath, $relativeDirectory . '/')) {
        return $assetPath;
    }

    $targetDirectory = BASE_PATH . '/' . $relativeDirectory;
    admin_message_project_assets_prepare_directory($targetDirectory);

    $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    if ($extension === '') {
        $extension = 'png';
    }

    $safeProjectId = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($projectId)) ?: 'projeto';
    $sourceHash = hash_file('sha256', $sourcePath) ?: sha1($assetPath);
    $targetFileName = $safeProjectId . '-' . $suffix . '-' . substr($sourceHash, 0, 16) . '.' . $extension;
    $targetRelativePath = $relativeDirectory . '/' . $targetFileName;
    $targetPath = BASE_PATH . '/' . $targetRelativePath;

    if (!is_file($targetPath)) {
        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException($errorMessage);
        }
        admin_message_normalize_www_data_permissions($targetPath, 0664);
    }

    return $targetRelativePath;
}

function admin_message_project_persist_hero_image(string $projectId, string $heroImagePath): string
{
    return admin_message_project_persist_asset(
        $projectId,
        $heroImagePath,
        admin_message_project_assets_relative_dir(),
        'hero',
        'Nao foi possivel copiar a imagem do projeto.'
    );
}

function admin_message_project_persist_whatsapp_image(string $projectId, string $imagePath): string
{
    return admin_message_project_persist_asset(
        $projectId,
        $imagePath,
        admin_message_whatsapp_assets_relative_dir(),
        'whatsapp',
        'Nao foi possivel copiar a imagem do WhatsApp.'
    );
}

function admin_message_project_store(array $draft, string $projectName, array $projects): string
{
    $projectId = trim((string) ($draft['project_id'] ?? ''));
    if ($projectId === '') {
        $projectId = admin_message_project_slug($projectName) . '-' . date('YmdHis');
    }

    $draft['hero_image_path'] = admin_message_project_persist_hero_image(
        $projectId,
        trim((string) ($draft['hero_image_path'] ?? ''))
    );
    $draft['whatsapp_image_path'] = admin_message_project_persist_whatsapp_image(
        $projectId,
        trim((string) ($draft['whatsapp_image_path'] ?? ''))
    );

    $payload = admin_message_default_draft();
    foreach (array_keys($payload) as $key) {
        if (array_key_exists($key, $draft)) {
            $payload[$key] = (string) $draft[$key];
        }
    }

    $canonicalSceneJson = admin_message_scene_json_value(
        (string) (($draft['fabric_scene_json'] ?? '') !== '' ? $draft['fabric_scene_json'] : ($draft['scene_json'] ?? '')),
        $draft
    );

    $payload['project_id'] = $projectId;
    $payload['project_name'] = $projectName;
    $payload['scene_json'] = $canonicalSceneJson;
    $payload['fabric_scene_json'] = $canonicalSceneJson;
    $payload['editor_layers_json'] = admin_message_editor_layers_json_from_scene_value($canonicalSceneJson, $draft);

    $record = [
        'id' => $projectId,
        'name' => $projectName,
        'updated_at' => date('c'),
        'payload' => $payload,
    ];

    $updated = false;
    foreach ($projects as $index => $project) {
        if ((string) ($project['id'] ?? '') === $projectId) {
            $record['created_at'] = (string) ($project['created_at'] ?? date('c'));
            $projects[$index] = $record;
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        $record['created_at'] = date('c');
        array_unshift($projects, $record);
    }

    admin_message_projects_save($projects);

    return $projectId;
}

function admin_message_post_draft(): array
{
    $draft = admin_message_default_draft();

    foreach (array_keys($draft) as $key) {
        $draft[$key] = (string) posted_value($key, $draft[$key]);
    }

    $draft['message_kind'] = (string) posted_value('message_kind', 'manual');
    $postedEditorLayersJson = (string) posted_value('editor_layers_json', '[]');
    $draft['fabric_scene_json'] = admin_message_scene_json_value((string) posted_value('fabric_scene_json', ''), $draft);
    $draft['editor_engine'] = 'fabric_v2';
    $draft['scene_json'] = admin_message_scene_json_value(
        (string) posted_value('scene_json', $draft['fabric_scene_json']),
        $draft
    );
    $draft['editor_layers_json'] = admin_message_editor_layers_json_from_scene_value(
        (string) ($draft['fabric_scene_json'] !== '' ? $draft['fabric_scene_json'] : $draft['scene_json']),
        $draft + ['editor_layers_json' => $postedEditorLayersJson]
    );
    $draft['send_notification'] = posted_value('send_notification') ? '1' : '0';
    $draft['send_email'] = posted_value('send_email') ? '1' : '0';
    $draft['send_whatsapp'] = posted_value('send_whatsapp') ? '1' : '0';
    $draft['whatsapp_button_enabled'] = posted_value('whatsapp_button_enabled') ? '1' : '0';
    $draft['whatsapp_link_preview_enabled'] = posted_value('whatsapp_link_preview_enabled') ? '1' : '0';
    $draft['show_title'] = posted_value('show_title') ? '1' : '0';
    $draft['show_body'] = posted_value('show_body') ? '1' : '0';
    $draft['show_button'] = posted_value('show_button') ? '1' : '0';
    $draft['show_image_hotspot'] = posted_value('show_image_hotspot') ? '1' : '0';

    return $draft;
}

function admin_message_debug_decode_entries(string $raw): array
{
    $decoded = json_decode(trim($raw), true);

    if (!is_array($decoded)) {
        return [];
    }

    $entries = [];

    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }

        $entries[] = [
            'ts' => trim((string) ($row['ts'] ?? '')),
            'step' => trim((string) ($row['step'] ?? '')),
            'detail' => is_array($row['detail'] ?? null) ? $row['detail'] : [],
        ];
    }

    return $entries;
}

function admin_message_debug_json_count(string $raw, string $mode = 'scene'): int
{
    $decoded = json_decode(trim($raw), true);

    if (!is_array($decoded)) {
        return 0;
    }

    if ($mode === 'layers') {
        return count($decoded);
    }

    return isset($decoded['layers']) && is_array($decoded['layers'])
        ? count($decoded['layers'])
        : 0;
}

function admin_message_debug_form_snapshot(array $draft): array
{
    return [
        'form_action' => trim((string) ($draft['form_action'] ?? '')),
        'project_id' => trim((string) ($draft['project_id'] ?? '')),
        'project_name' => trim((string) ($draft['project_name'] ?? '')),
        'recipient_mode' => trim((string) ($draft['recipient_mode'] ?? 'all')),
        'customer_id' => (int) ($draft['customer_id'] ?? 0),
        'message_kind' => trim((string) ($draft['message_kind'] ?? 'manual')),
        'title_bytes' => strlen((string) ($draft['title'] ?? '')),
        'message_bytes' => strlen((string) ($draft['message'] ?? '')),
        'whatsapp_message_bytes' => strlen((string) ($draft['whatsapp_message'] ?? '')),
        'whatsapp_mode' => trim((string) ($draft['whatsapp_mode'] ?? 'media')),
        'whatsapp_button_enabled' => admin_message_whatsapp_button_enabled($draft),
        'whatsapp_link_preview_enabled' => admin_message_whatsapp_link_preview_enabled($draft),
        'whatsapp_button_title_bytes' => strlen((string) ($draft['whatsapp_button_title'] ?? '')),
        'whatsapp_button_label' => trim((string) ($draft['whatsapp_button_label'] ?? '')),
        'whatsapp_button_url' => trim((string) ($draft['whatsapp_button_url'] ?? '')),
        'whatsapp_button_footer' => trim((string) ($draft['whatsapp_button_footer'] ?? '')),
        'link_url' => trim((string) ($draft['link_url'] ?? '')),
        'button_label' => trim((string) ($draft['button_label'] ?? '')),
        'button_font_size' => (int) ($draft['button_font_size'] ?? 24),
        'image_link_url' => trim((string) ($draft['image_link_url'] ?? '')),
        'hero_image_path' => trim((string) ($draft['hero_image_path'] ?? '')),
        'whatsapp_image_path' => trim((string) ($draft['whatsapp_image_path'] ?? '')),
        'send_notification' => ($draft['send_notification'] ?? '0') === '1',
        'send_email' => ($draft['send_email'] ?? '0') === '1',
        'send_whatsapp' => ($draft['send_whatsapp'] ?? '0') === '1',
        'editor_engine' => trim((string) ($draft['editor_engine'] ?? 'fabric_v2')),
        'scene_json_bytes' => strlen((string) ($draft['scene_json'] ?? '')),
        'fabric_scene_json_bytes' => strlen((string) ($draft['fabric_scene_json'] ?? '')),
        'editor_layers_json_bytes' => strlen((string) ($draft['editor_layers_json'] ?? '')),
        'scene_layer_count' => admin_message_debug_json_count((string) ($draft['scene_json'] ?? ''), 'scene'),
        'fabric_scene_layer_count' => admin_message_debug_json_count((string) ($draft['fabric_scene_json'] ?? ''), 'scene'),
        'editor_layers_count' => admin_message_debug_json_count((string) ($draft['editor_layers_json'] ?? ''), 'layers'),
    ];
}

function admin_message_debug_scene_snapshot(string $raw): array
{
    $decoded = json_decode(trim($raw), true);
    if (!is_array($decoded)) {
        return [
            'bytes' => strlen($raw),
            'parse_ok' => false,
            'canvas' => null,
            'layers' => [],
        ];
    }

    $layers = [];
    foreach ((array) ($decoded['layers'] ?? []) as $index => $layer) {
        if (!is_array($layer)) {
            continue;
        }
        $layers[] = [
            'index' => $index,
            'id' => trim((string) ($layer['id'] ?? '')),
            'type' => trim((string) ($layer['type'] ?? '')),
            'role' => trim((string) ($layer['role'] ?? '')),
            'x' => (int) ($layer['x'] ?? 0),
            'y' => (int) ($layer['y'] ?? 0),
            'width' => (int) ($layer['width'] ?? 0),
            'height' => (int) ($layer['height'] ?? 0),
            'fontSize' => (int) ($layer['fontSize'] ?? 0),
            'lineHeight' => (float) ($layer['lineHeight'] ?? 0),
            'textBytes' => strlen((string) ($layer['textRaw'] ?? '')),
            'href' => trim((string) ($layer['hrefRaw'] ?? '')),
        ];
    }

    return [
        'bytes' => strlen($raw),
        'parse_ok' => true,
        'canvas' => [
            'width' => (int) (($decoded['canvas']['width'] ?? 0)),
            'height' => (int) (($decoded['canvas']['height'] ?? 0)),
            'backgroundImage' => trim((string) ($decoded['canvas']['backgroundImage'] ?? '')),
        ],
        'layers' => $layers,
    ];
}

function admin_message_debug_layers_snapshot(string $raw): array
{
    $decoded = json_decode(trim($raw), true);
    if (!is_array($decoded)) {
        return [
            'bytes' => strlen($raw),
            'parse_ok' => false,
            'layers' => [],
        ];
    }

    $layers = [];
    foreach ($decoded as $index => $layer) {
        if (!is_array($layer)) {
            continue;
        }
        $layers[] = [
            'index' => $index,
            'id' => trim((string) ($layer['id'] ?? '')),
            'type' => trim((string) ($layer['type'] ?? '')),
            'x' => (int) ($layer['x'] ?? 0),
            'y' => (int) ($layer['y'] ?? 0),
            'width' => (int) ($layer['width'] ?? 0),
            'height' => (int) ($layer['height'] ?? 0),
            'font_size' => (int) ($layer['font_size'] ?? 0),
            'line_height' => (int) ($layer['line_height'] ?? 0),
            'content_bytes' => strlen((string) ($layer['content'] ?? '')),
            'link_url' => trim((string) ($layer['link_url'] ?? '')),
        ];
    }

    return [
        'bytes' => strlen($raw),
        'parse_ok' => true,
        'layers' => $layers,
    ];
}

function admin_message_debug_init(string $requestId, array $draft, array $clientEntries = []): array
{
    $requestId = trim($requestId) !== '' ? trim($requestId) : 'msgdbg_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));

    return [
        'request_id' => $requestId,
        'captured_at' => date('c'),
        'server' => [
            'environment' => [
                'request_method' => strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
                'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ],
            'form' => admin_message_debug_form_snapshot($draft),
            'events' => [],
            'targets' => [],
            'summary' => [],
        ],
        'client' => [
            'submitted_entries_count' => count($clientEntries),
            'submitted_entries' => $clientEntries,
        ],
    ];
}

function admin_message_debug_event(array &$debug, string $step, array $detail = []): void
{
    if (!isset($debug['server']['events']) || !is_array($debug['server']['events'])) {
        $debug['server']['events'] = [];
    }

    $debug['server']['events'][] = [
        'ts' => date('c'),
        'step' => $step,
        'detail' => $detail,
    ];
}

function admin_message_debug_store(?array $debug): void
{
    if ($debug === null) {
        return;
    }

    $_SESSION['admin_message_send_debug'] = $debug;
}

function admin_message_debug_beacon_log_file(): string
{
    return BASE_PATH . '/storage/messages/editor-submit-debug.ndjson';
}

function admin_message_debug_write_beacon(array $payload): void
{
    $directory = BASE_PATH . '/storage/messages';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return;
    }

    $file = admin_message_debug_beacon_log_file();
    $entry = [
        'captured_at' => date('c'),
        'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        'content_type' => (string) ($_SERVER['CONTENT_TYPE'] ?? ''),
        'content_length' => (int) ($_SERVER['CONTENT_LENGTH'] ?? 0),
        'payload' => $payload,
    ];

    @file_put_contents(
        $file,
        json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
    admin_message_normalize_www_data_permissions($file, 0664);
}

function admin_message_debug_project_payload_snapshot(array $payload): array
{
    return [
        'project_id' => trim((string) ($payload['project_id'] ?? '')),
        'project_name' => trim((string) ($payload['project_name'] ?? '')),
        'hero_image_path' => trim((string) ($payload['hero_image_path'] ?? '')),
        'whatsapp_image_path' => trim((string) ($payload['whatsapp_image_path'] ?? '')),
        'title_bytes' => strlen((string) ($payload['title'] ?? '')),
        'message_bytes' => strlen((string) ($payload['message'] ?? '')),
        'whatsapp_message_bytes' => strlen((string) ($payload['whatsapp_message'] ?? '')),
        'whatsapp_mode' => trim((string) ($payload['whatsapp_mode'] ?? 'media')),
        'whatsapp_button_title_bytes' => strlen((string) ($payload['whatsapp_button_title'] ?? '')),
        'whatsapp_button_label' => trim((string) ($payload['whatsapp_button_label'] ?? '')),
        'whatsapp_button_url' => trim((string) ($payload['whatsapp_button_url'] ?? '')),
        'whatsapp_button_footer' => trim((string) ($payload['whatsapp_button_footer'] ?? '')),
        'link_url' => trim((string) ($payload['link_url'] ?? '')),
        'button_label' => trim((string) ($payload['button_label'] ?? '')),
        'image_link_url' => trim((string) ($payload['image_link_url'] ?? '')),
        'scene' => admin_message_debug_scene_snapshot((string) ($payload['scene_json'] ?? '')),
        'fabric_scene' => admin_message_debug_scene_snapshot((string) ($payload['fabric_scene_json'] ?? '')),
        'editor_layers' => admin_message_debug_layers_snapshot((string) ($payload['editor_layers_json'] ?? '')),
    ];
}

function admin_message_debug_pretty_json($value): string
{
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($json) ? $json : 'null';
}

function admin_message_debug_read_beacon(?string $requestId, int $scanLimit = 250): ?array
{
    $requestId = trim((string) $requestId);
    if ($requestId === '') {
        return null;
    }

    $file = admin_message_debug_beacon_log_file();
    if (!is_file($file)) {
        return null;
    }

    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines) || $lines === []) {
        return null;
    }

    $scanned = 0;
    for ($index = count($lines) - 1; $index >= 0 && $scanned < $scanLimit; $index--, $scanned++) {
        $decoded = json_decode((string) $lines[$index], true);
        if (!is_array($decoded)) {
            continue;
        }

        $payload = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];
        if (trim((string) ($payload['requestId'] ?? '')) !== $requestId) {
            continue;
        }

        return [
            'captured_at' => (string) ($decoded['captured_at'] ?? ''),
            'remote_addr' => (string) ($decoded['remote_addr'] ?? ''),
            'user_agent' => (string) ($decoded['user_agent'] ?? ''),
            'content_type' => (string) ($decoded['content_type'] ?? ''),
            'content_length' => (int) ($decoded['content_length'] ?? 0),
            'payload' => $payload,
        ];
    }

    return null;
}

function admin_message_debug_batch_snapshot(string $batchPublicId): ?array
{
    $batchPublicId = trim($batchPublicId);
    if ($batchPublicId === '') {
        return null;
    }

    if (!function_exists('message_queue_tables_ready') || !message_queue_tables_ready()) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, public_id, status, project_id, project_name, recipient_mode, message_kind,
                total_jobs, pending_jobs, reserved_jobs, processing_jobs, retry_jobs,
                sent_jobs, failed_jobs, cancelled_jobs, queued_at, started_at, finished_at,
                created_at, updated_at
           FROM message_batches
          WHERE public_id = :public_id
          LIMIT 1'
    );
    $statement->execute(['public_id' => $batchPublicId]);
    $batch = $statement->fetch();

    if (!is_array($batch)) {
        return null;
    }

    $jobsStatement = db()->prepare(
        'SELECT id, public_id, customer_id, customer_email, send_notification, send_email,
                notification_status, email_status, status, attempts, worker_id,
                subject_snapshot, render_cache_key, render_path, last_stage, last_error_excerpt,
                notification_sent_at, email_sent_at, finished_at, updated_at,
                customer_snapshot_json, token_snapshot_json
           FROM message_batch_jobs
          WHERE batch_id = :batch_id
          ORDER BY id ASC'
    );
    $jobsStatement->execute(['batch_id' => (int) $batch['id']]);
    $jobs = [];

    while ($row = $jobsStatement->fetch()) {
        $customerSnapshot = json_decode((string) ($row['customer_snapshot_json'] ?? ''), true);
        $tokenSnapshot = json_decode((string) ($row['token_snapshot_json'] ?? ''), true);
        $renderPath = trim((string) ($row['render_path'] ?? ''));
        $renderAbsolutePath = $renderPath !== ''
            ? BASE_PATH . '/' . ltrim(str_replace('\\', '/', $renderPath), '/')
            : '';

        $jobs[] = [
            'id' => (int) ($row['id'] ?? 0),
            'public_id' => trim((string) ($row['public_id'] ?? '')),
            'customer_id' => (int) ($row['customer_id'] ?? 0),
            'customer_name' => trim((string) ($customerSnapshot['nome'] ?? '')),
            'customer_email' => trim((string) ($row['customer_email'] ?? ($customerSnapshot['email'] ?? ''))),
            'primeiro_nome' => trim((string) ($tokenSnapshot['{{primeiro_nome}}'] ?? '')),
            'status' => trim((string) ($row['status'] ?? '')),
            'notification_status' => trim((string) ($row['notification_status'] ?? '')),
            'email_status' => trim((string) ($row['email_status'] ?? '')),
            'send_notification' => !empty($row['send_notification']),
            'send_email' => !empty($row['send_email']),
            'attempts' => (int) ($row['attempts'] ?? 0),
            'worker_id' => trim((string) ($row['worker_id'] ?? '')),
            'subject_snapshot' => trim((string) ($row['subject_snapshot'] ?? '')),
            'render_cache_key' => trim((string) ($row['render_cache_key'] ?? '')),
            'render_path' => $renderPath,
            'render_file_exists' => $renderAbsolutePath !== '' && is_file($renderAbsolutePath),
            'render_file_size' => $renderAbsolutePath !== '' && is_file($renderAbsolutePath)
                ? (int) @filesize($renderAbsolutePath)
                : 0,
            'last_stage' => trim((string) ($row['last_stage'] ?? '')),
            'last_error_excerpt' => trim((string) ($row['last_error_excerpt'] ?? '')),
            'notification_sent_at' => (string) ($row['notification_sent_at'] ?? ''),
            'email_sent_at' => (string) ($row['email_sent_at'] ?? ''),
            'finished_at' => (string) ($row['finished_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    $logsStatement = db()->prepare(
        'SELECT id, job_id, stage, status, summary, created_at, details_json
           FROM message_send_log
          WHERE batch_id = :batch_id
          ORDER BY id ASC'
    );
    $logsStatement->execute(['batch_id' => (int) $batch['id']]);
    $logs = [];

    while ($row = $logsStatement->fetch()) {
        $details = json_decode((string) ($row['details_json'] ?? ''), true);
        $details = is_array($details) ? $details : [];

        $logs[] = [
            'id' => (int) ($row['id'] ?? 0),
            'job_id' => isset($row['job_id']) ? (int) $row['job_id'] : null,
            'stage' => trim((string) ($row['stage'] ?? '')),
            'status' => trim((string) ($row['status'] ?? '')),
            'summary' => trim((string) ($row['summary'] ?? '')),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'details' => [
                'render_path' => trim((string) ($details['render_path'] ?? ($details['email_html_summary']['image_src'] ?? ''))),
                'render_cache_key' => trim((string) ($details['render_cache_key'] ?? ($details['render_debug']['cache_key'] ?? ''))),
                'cache_status' => trim((string) ($details['cache_status'] ?? ($details['render_debug']['cache_status'] ?? ''))),
                'mail_success' => $details['mail_result']['success'] ?? null,
                'mail_delivered' => $details['mail_result']['delivered'] ?? null,
                'last_recipient' => trim((string) ($details['mail_result']['transport']['last_recipient'] ?? '')),
                'message_id' => trim((string) ($details['mail_result']['message_id'] ?? '')),
                'error' => trim((string) ($details['error'] ?? '')),
            ],
        ];
    }

    $projectParts = customer_message_render_cache_project_parts([
        'project_id' => (string) ($batch['project_id'] ?? ''),
        'project_name' => (string) ($batch['project_name'] ?? ''),
    ]);
    $projectKey = trim((string) ($projectParts['project_key'] ?? ''));
    $projectPublicDir = $projectKey !== ''
        ? 'uploads/messages/render-cache/' . $projectKey
        : '';
    $projectAbsoluteDir = $projectPublicDir !== ''
        ? BASE_PATH . '/' . $projectPublicDir
        : '';
    $projectFiles = [];

    if ($projectAbsoluteDir !== '' && is_dir($projectAbsoluteDir)) {
        $entries = glob($projectAbsoluteDir . '/*') ?: [];
        foreach ($entries as $entry) {
            if (!is_file($entry)) {
                continue;
            }

            $projectFiles[] = [
                'name' => basename($entry),
                'size' => (int) @filesize($entry),
                'modified_at' => date('c', (int) @filemtime($entry)),
            ];
        }

        usort($projectFiles, static function (array $left, array $right): int {
            return strcmp((string) ($right['modified_at'] ?? ''), (string) ($left['modified_at'] ?? ''));
        });
    }

    return [
        'batch' => [
            'id' => (int) ($batch['id'] ?? 0),
            'public_id' => trim((string) ($batch['public_id'] ?? '')),
            'status' => trim((string) ($batch['status'] ?? '')),
            'project_id' => trim((string) ($batch['project_id'] ?? '')),
            'project_name' => trim((string) ($batch['project_name'] ?? '')),
            'recipient_mode' => trim((string) ($batch['recipient_mode'] ?? '')),
            'message_kind' => trim((string) ($batch['message_kind'] ?? '')),
            'total_jobs' => (int) ($batch['total_jobs'] ?? 0),
            'pending_jobs' => (int) ($batch['pending_jobs'] ?? 0),
            'reserved_jobs' => (int) ($batch['reserved_jobs'] ?? 0),
            'processing_jobs' => (int) ($batch['processing_jobs'] ?? 0),
            'retry_jobs' => (int) ($batch['retry_jobs'] ?? 0),
            'sent_jobs' => (int) ($batch['sent_jobs'] ?? 0),
            'failed_jobs' => (int) ($batch['failed_jobs'] ?? 0),
            'cancelled_jobs' => (int) ($batch['cancelled_jobs'] ?? 0),
            'queued_at' => (string) ($batch['queued_at'] ?? ''),
            'started_at' => (string) ($batch['started_at'] ?? ''),
            'finished_at' => (string) ($batch['finished_at'] ?? ''),
            'created_at' => (string) ($batch['created_at'] ?? ''),
            'updated_at' => (string) ($batch['updated_at'] ?? ''),
        ],
        'jobs' => $jobs,
        'logs' => $logs,
        'runtime_log' => [
            'directory' => function_exists('whatsapp_evolution_debug_log_dir')
                ? whatsapp_evolution_debug_log_dir()
                : '',
            'file' => function_exists('whatsapp_evolution_debug_log_file_path')
                ? whatsapp_evolution_debug_log_file_path()
                : '',
            'file_exists' => function_exists('whatsapp_evolution_debug_log_file_path')
                ? is_file(whatsapp_evolution_debug_log_file_path())
                : false,
            'file_size' => function_exists('whatsapp_evolution_debug_log_file_path') && is_file(whatsapp_evolution_debug_log_file_path())
                ? (int) (@filesize(whatsapp_evolution_debug_log_file_path()) ?: 0)
                : 0,
        ],
        'project_cache' => [
            'project_key' => $projectKey,
            'public_dir' => $projectPublicDir,
            'absolute_dir' => $projectAbsoluteDir,
            'files' => $projectFiles,
        ],
    ];
}

function admin_message_debug_whatsapp_batch_snapshot(string $batchPublicId): ?array
{
    $batchPublicId = trim($batchPublicId);
    if ($batchPublicId === '') {
        return null;
    }

    if (!function_exists('whatsapp_queue_tables_ready') || !whatsapp_queue_tables_ready()) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, public_id, status, project_id, project_name, recipient_mode, message_kind,
                total_jobs, pending_jobs, reserved_jobs, processing_jobs, retry_jobs,
                accepted_jobs, transport_ack_jobs, delivered_jobs, read_jobs, render_suspect_jobs, fallback_jobs,
                sent_jobs, failed_jobs, cancelled_jobs, queued_at, started_at, finished_at,
                created_at, updated_at
           FROM whatsapp_batches
          WHERE public_id = :public_id
          LIMIT 1'
    );
    $statement->execute(['public_id' => $batchPublicId]);
    $batch = $statement->fetch();

    if (!is_array($batch)) {
        return null;
    }

    $jobsStatement = db()->prepare(
        'SELECT id, public_id, customer_id, customer_phone, status, attempts, worker_id,
                caption_snapshot, message_id, media_path, last_stage, last_error_excerpt,
                sent_at, finished_at, updated_at, customer_snapshot_json, token_snapshot_json,
                send_variant, button_type, has_thumbnail, provider_message_id, provider_remote_jid,
                provider_from_me, accepted_http_status, accepted_at, last_provider_event,
                last_provider_status, last_provider_event_at, transport_ack_at, delivered_at,
                read_at, render_suspect_at, render_suspect_reason, fallback_trigger_at,
                fallback_trigger_reason, fallback_attempt_no
           FROM whatsapp_batch_jobs
          WHERE batch_id = :batch_id
          ORDER BY id ASC'
    );
    $jobsStatement->execute(['batch_id' => (int) $batch['id']]);
    $jobs = [];

    while ($row = $jobsStatement->fetch()) {
        $customerSnapshot = json_decode((string) ($row['customer_snapshot_json'] ?? ''), true);
        $tokenSnapshot = json_decode((string) ($row['token_snapshot_json'] ?? ''), true);
        $mediaPath = trim((string) ($row['media_path'] ?? ''));
        $mediaAbsolutePath = $mediaPath !== ''
            ? BASE_PATH . '/' . ltrim(str_replace('\\', '/', $mediaPath), '/')
            : '';

        $jobs[] = [
            'id' => (int) ($row['id'] ?? 0),
            'public_id' => trim((string) ($row['public_id'] ?? '')),
            'customer_id' => (int) ($row['customer_id'] ?? 0),
            'customer_name' => trim((string) ($customerSnapshot['nome'] ?? '')),
            'customer_phone' => trim((string) ($row['customer_phone'] ?? ($customerSnapshot['telefone_normalized'] ?? ''))),
            'primeiro_nome' => trim((string) ($tokenSnapshot['{{primeiro_nome}}'] ?? ($tokenSnapshot['{primeiro_nome}'] ?? ''))),
            'status' => trim((string) ($row['status'] ?? '')),
            'attempts' => (int) ($row['attempts'] ?? 0),
            'worker_id' => trim((string) ($row['worker_id'] ?? '')),
            'caption_snapshot' => admin_message_send_log_preview((string) ($row['caption_snapshot'] ?? ''), 220),
            'message_id' => trim((string) ($row['message_id'] ?? '')),
            'media_path' => $mediaPath,
            'send_variant' => trim((string) ($row['send_variant'] ?? '')),
            'button_type' => trim((string) ($row['button_type'] ?? '')),
            'has_thumbnail' => !empty($row['has_thumbnail']),
            'provider_message_id' => trim((string) ($row['provider_message_id'] ?? '')),
            'provider_remote_jid' => trim((string) ($row['provider_remote_jid'] ?? '')),
            'provider_from_me' => isset($row['provider_from_me']) ? (bool) $row['provider_from_me'] : null,
            'accepted_http_status' => isset($row['accepted_http_status']) ? (int) $row['accepted_http_status'] : 0,
            'media_file_exists' => $mediaAbsolutePath !== '' && is_file($mediaAbsolutePath),
            'media_file_size' => $mediaAbsolutePath !== '' && is_file($mediaAbsolutePath)
                ? (int) @filesize($mediaAbsolutePath)
                : 0,
            'last_stage' => trim((string) ($row['last_stage'] ?? '')),
            'last_error_excerpt' => trim((string) ($row['last_error_excerpt'] ?? '')),
            'accepted_at' => (string) ($row['accepted_at'] ?? ($row['sent_at'] ?? '')),
            'sent_at' => (string) ($row['sent_at'] ?? ''),
            'last_provider_event' => trim((string) ($row['last_provider_event'] ?? '')),
            'last_provider_status' => trim((string) ($row['last_provider_status'] ?? '')),
            'last_provider_event_at' => (string) ($row['last_provider_event_at'] ?? ''),
            'transport_ack_at' => (string) ($row['transport_ack_at'] ?? ''),
            'delivered_at' => (string) ($row['delivered_at'] ?? ''),
            'read_at' => (string) ($row['read_at'] ?? ''),
            'render_suspect_at' => (string) ($row['render_suspect_at'] ?? ''),
            'render_suspect_reason' => trim((string) ($row['render_suspect_reason'] ?? '')),
            'fallback_trigger_at' => (string) ($row['fallback_trigger_at'] ?? ''),
            'fallback_trigger_reason' => trim((string) ($row['fallback_trigger_reason'] ?? '')),
            'fallback_attempt_no' => (int) ($row['fallback_attempt_no'] ?? 0),
            'finished_at' => (string) ($row['finished_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    $logsStatement = db()->prepare(
        'SELECT id, job_id, stage, status, summary, created_at, details_json
           FROM whatsapp_send_log
          WHERE batch_id = :batch_id
          ORDER BY id ASC'
    );
    $logsStatement->execute(['batch_id' => (int) $batch['id']]);
    $logs = [];

    while ($row = $logsStatement->fetch()) {
        $details = json_decode((string) ($row['details_json'] ?? ''), true);
        $details = is_array($details) ? $details : [];

        $logs[] = [
            'id' => (int) ($row['id'] ?? 0),
            'job_id' => isset($row['job_id']) ? (int) $row['job_id'] : null,
            'stage' => trim((string) ($row['stage'] ?? '')),
            'status' => trim((string) ($row['status'] ?? '')),
            'summary' => trim((string) ($row['summary'] ?? '')),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'details' => [
                'customer_phone' => trim((string) ($details['customer_phone'] ?? '')),
                'message_id' => trim((string) ($details['message_id'] ?? '')),
                'provider_message_id' => trim((string) ($details['provider_message_id'] ?? '')),
                'provider_status' => trim((string) ($details['provider_status'] ?? '')),
                'send_variant' => trim((string) ($details['send_variant'] ?? '')),
                'media_path' => trim((string) ($details['media_path'] ?? '')),
                'error' => trim((string) ($details['error'] ?? '')),
            ],
        ];
    }

    $projectParts = customer_message_render_cache_project_parts([
        'project_id' => (string) ($batch['project_id'] ?? ''),
        'project_name' => (string) ($batch['project_name'] ?? ''),
    ]);
    $projectKey = trim((string) ($projectParts['project_key'] ?? ''));
    $projectPublicDir = $projectKey !== ''
        ? 'uploads/messages/render-cache/' . $projectKey
        : '';
    $projectAbsoluteDir = $projectPublicDir !== ''
        ? BASE_PATH . '/' . $projectPublicDir
        : '';
    $projectFiles = [];

    if ($projectAbsoluteDir !== '' && is_dir($projectAbsoluteDir)) {
        $entries = glob($projectAbsoluteDir . '/*') ?: [];
        foreach ($entries as $entry) {
            if (!is_file($entry)) {
                continue;
            }

            $projectFiles[] = [
                'name' => basename($entry),
                'size' => (int) @filesize($entry),
                'modified_at' => date('c', (int) @filemtime($entry)),
            ];
        }

        usort($projectFiles, static function (array $left, array $right): int {
            return strcmp((string) ($right['modified_at'] ?? ''), (string) ($left['modified_at'] ?? ''));
        });
    }

    return [
        'batch' => [
            'id' => (int) ($batch['id'] ?? 0),
            'public_id' => trim((string) ($batch['public_id'] ?? '')),
            'status' => trim((string) ($batch['status'] ?? '')),
            'project_id' => trim((string) ($batch['project_id'] ?? '')),
            'project_name' => trim((string) ($batch['project_name'] ?? '')),
            'recipient_mode' => trim((string) ($batch['recipient_mode'] ?? '')),
            'message_kind' => trim((string) ($batch['message_kind'] ?? '')),
            'total_jobs' => (int) ($batch['total_jobs'] ?? 0),
            'pending_jobs' => (int) ($batch['pending_jobs'] ?? 0),
            'reserved_jobs' => (int) ($batch['reserved_jobs'] ?? 0),
            'processing_jobs' => (int) ($batch['processing_jobs'] ?? 0),
            'retry_jobs' => (int) ($batch['retry_jobs'] ?? 0),
            'accepted_jobs' => (int) ($batch['accepted_jobs'] ?? $batch['sent_jobs'] ?? 0),
            'transport_ack_jobs' => (int) ($batch['transport_ack_jobs'] ?? 0),
            'delivered_jobs' => (int) ($batch['delivered_jobs'] ?? 0),
            'read_jobs' => (int) ($batch['read_jobs'] ?? 0),
            'render_suspect_jobs' => (int) ($batch['render_suspect_jobs'] ?? 0),
            'fallback_jobs' => (int) ($batch['fallback_jobs'] ?? 0),
            'sent_jobs' => (int) ($batch['sent_jobs'] ?? 0),
            'failed_jobs' => (int) ($batch['failed_jobs'] ?? 0),
            'cancelled_jobs' => (int) ($batch['cancelled_jobs'] ?? 0),
            'queued_at' => (string) ($batch['queued_at'] ?? ''),
            'started_at' => (string) ($batch['started_at'] ?? ''),
            'finished_at' => (string) ($batch['finished_at'] ?? ''),
            'created_at' => (string) ($batch['created_at'] ?? ''),
            'updated_at' => (string) ($batch['updated_at'] ?? ''),
        ],
        'jobs' => $jobs,
        'logs' => $logs,
        'project_cache' => [
            'project_key' => $projectKey,
            'public_dir' => $projectPublicDir,
            'absolute_dir' => $projectAbsoluteDir,
            'files' => $projectFiles,
        ],
    ];
}

function admin_message_channel_value(?string $value, string $fallback = 'email'): string
{
    $normalized = strtolower(trim((string) $value));

    if (in_array($normalized, ['email', 'whatsapp'], true)) {
        return $normalized;
    }

    return $fallback;
}

function admin_message_redirect_url(?string $projectId = null, ?string $channel = null): string
{
    $projectId = trim((string) $projectId);
    $resolvedChannel = admin_message_channel_value($channel, '');

    $query = [];

    if ($projectId !== '') {
        $query['project'] = $projectId;
    }

    if ($resolvedChannel !== '') {
        $query['channel'] = $resolvedChannel;
    }

    return 'admin/mensagens.php' . ($query !== [] ? '?' . http_build_query($query) : '');
}

$presets = customer_message_presets();
$activeCustomers = admin_message_target_customers('all');
$inactiveCustomers = admin_message_target_customers('inactive_45');
$savedProjects = admin_message_projects_load();
$smtpReady = trim((string) MAIL_SMTP_USERNAME) !== '' && trim((string) MAIL_SMTP_PASSWORD) !== '';
$mailLogWritable = is_dir(MAIL_LOG_DIRECTORY)
    ? is_writable(MAIL_LOG_DIRECTORY)
    : is_writable(dirname(MAIL_LOG_DIRECTORY));

if (is_post()) {
    $postedProjectId = trim((string) posted_value('project_id', ''));
    $formAction = trim((string) posted_value('form_action', 'send_message'));
    $postedActiveChannel = admin_message_channel_value((string) posted_value('active_channel', ''), 'email');

    if (!verify_csrf_token(posted_value('csrf_token'))) {
        if ($formAction === 'send_message') {
            $csrfDraft = admin_message_post_draft();
            $csrfDraft['form_action'] = $formAction;
            $csrfDraft['message_enqueue_token'] = (string) posted_value('message_enqueue_token', '');
            $csrfDraft['message_debug_request_id'] = (string) posted_value('message_debug_request_id', '');
            admin_message_send_log_write_attempt(
                'csrf_error',
                'Token invalido para enviar a mensagem.',
                $csrfDraft,
                current_admin(),
                [
                    'error_reason' => 'invalid_csrf',
                ]
            );
        }
        set_flash('error', 'Token invalido para enviar a mensagem.');
        redirect(admin_message_redirect_url($postedProjectId, $postedActiveChannel));
    }

    $draftFromPost = admin_message_post_draft();
    $draftFromPost['form_action'] = $formAction;
    $draftFromPost['active_channel'] = $postedActiveChannel;
    $draftFromPost['message_enqueue_token'] = (string) posted_value('message_enqueue_token', '');
    $draftFromPost['message_debug_request_id'] = (string) posted_value('message_debug_request_id', '');
    $adminActor = current_admin();
    if ($formAction === 'send_message') {
        admin_message_send_log_write_state(null, [
            'reason' => 'new_send_click_started',
            'request_started_at' => date('c'),
        ]);
    }
    $writeSendAttemptLog = static function (string $status, string $message, array $extra = []) use (&$draftFromPost, $formAction, $adminActor): void {
        if ($formAction !== 'send_message') {
            return;
        }

        admin_message_send_log_write_attempt(
            $status,
            $message,
            $draftFromPost,
            $adminActor,
            $extra + [
                'form_action' => $formAction,
                'message_enqueue_token' => (string) ($draftFromPost['message_enqueue_token'] ?? ''),
                'message_debug_request_id' => (string) ($draftFromPost['message_debug_request_id'] ?? ''),
            ]
        );
    };
    $writeSendAttemptLog('started', 'Clique em Enviar mensagem recebido.');
    $debugCaptureRequested = in_array($formAction, ['send_message', 'save_project'], true)
        && posted_value('message_debug_capture') === '1';
    $sendDebug = $debugCaptureRequested
        ? admin_message_debug_init(
            (string) posted_value('message_debug_request_id', ''),
            $draftFromPost,
            admin_message_debug_decode_entries((string) posted_value('message_debug_client_trace', ''))
        )
        : null;
    if ($sendDebug !== null) {
        $sendDebug['server']['action'] = $formAction;
    }
    $recipientMode = (string) ($draftFromPost['recipient_mode'] ?? 'all');
    $recipientMode = in_array($recipientMode, ['all', 'inactive_45', 'customer'], true) ? $recipientMode : 'all';
    $customerId = (int) ($draftFromPost['customer_id'] ?? 0);
    $messageKind = trim((string) ($draftFromPost['message_kind'] ?? 'manual'));
    $whatsAppButtonEnabled = ($draftFromPost['whatsapp_button_enabled'] ?? '0') === '1';
    $whatsAppLinkPreviewEnabled = ($draftFromPost['whatsapp_link_preview_enabled'] ?? '0') === '1';
    $whatsAppButtonTitle = trim((string) ($draftFromPost['whatsapp_button_title'] ?? ''));
    $whatsAppButtonLabel = trim((string) ($draftFromPost['whatsapp_button_label'] ?? ''));
    $whatsAppButtonFooter = trim((string) ($draftFromPost['whatsapp_button_footer'] ?? ''));
    $whatsAppButtonUrl = admin_message_normalize_link((string) ($draftFromPost['whatsapp_button_url'] ?? ''));
    $draftFromPost['whatsapp_button_enabled'] = $whatsAppButtonEnabled ? '1' : '0';
    $draftFromPost['whatsapp_link_preview_enabled'] = $whatsAppLinkPreviewEnabled ? '1' : '0';
    $whatsAppMode = admin_message_whatsapp_mode($draftFromPost);
    $draftFromPost['whatsapp_mode'] = $whatsAppMode;
    $draftFromPost['whatsapp_button_title'] = $whatsAppButtonTitle;
    $draftFromPost['whatsapp_button_label'] = $whatsAppButtonLabel;
    $draftFromPost['whatsapp_button_url'] = $whatsAppButtonUrl ?? '';
    $draftFromPost['whatsapp_button_footer'] = $whatsAppButtonFooter;
    $title = trim((string) ($draftFromPost['title'] ?? ''));
    $editorLayersJson = admin_message_layers_json_value((string) ($draftFromPost['editor_layers_json'] ?? '[]'));
    $sendNotification = ($draftFromPost['send_notification'] ?? '0') === '1' ? 1 : 0;
    $sendEmail = ($draftFromPost['send_email'] ?? '0') === '1' ? 1 : 0;
    $sendWhatsApp = ($draftFromPost['send_whatsapp'] ?? '0') === '1' ? 1 : 0;
    $messageKind = isset($presets[$messageKind]) ? $messageKind : 'manual';

    $heroImagePath = trim((string) ($draftFromPost['hero_image_path'] ?? ''));
    $whatsAppImagePath = trim((string) ($draftFromPost['whatsapp_image_path'] ?? ''));

    if ($sendDebug !== null) {
        admin_message_debug_event($sendDebug, 'post_received', [
            'form_action' => $formAction,
            'content_type' => (string) ($_SERVER['CONTENT_TYPE'] ?? ''),
            'content_length' => (int) ($_SERVER['CONTENT_LENGTH'] ?? 0),
            'post_keys' => array_keys($_POST),
            'files_keys' => array_keys($_FILES),
            'client_entries_count' => count((array) ($sendDebug['client']['submitted_entries'] ?? [])),
            'scene_post_snapshot' => admin_message_debug_scene_snapshot((string) posted_value('scene_json', '')),
            'fabric_scene_post_snapshot' => admin_message_debug_scene_snapshot((string) posted_value('fabric_scene_json', '')),
            'editor_layers_post_snapshot' => admin_message_debug_layers_snapshot((string) posted_value('editor_layers_json', '')),
        ]);
    }

    if (
        isset($_FILES['hero_image'])
        && (int) ($_FILES['hero_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
    ) {
        try {
            $heroImagePath = handle_image_upload('hero_image', 'messages', null) ?? $heroImagePath;
            $draftFromPost['hero_image_path'] = $heroImagePath;
            if ($sendDebug !== null) {
                admin_message_debug_event($sendDebug, 'hero_upload_ok', [
                    'hero_image_path' => $heroImagePath,
                ]);
            }
        } catch (RuntimeException $exception) {
            $writeSendAttemptLog('hero_upload_error', $exception->getMessage(), [
                'error_reason' => 'hero_upload_error',
            ]);
            if ($sendDebug !== null) {
                admin_message_debug_event($sendDebug, 'hero_upload_error', [
                    'error' => $exception->getMessage(),
                ]);
                admin_message_debug_store($sendDebug);
            }
            set_flash('error', $exception->getMessage());
            redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId), $postedActiveChannel));
        }
    }

    if (
        isset($_FILES['whatsapp_image'])
        && (int) ($_FILES['whatsapp_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
    ) {
        try {
            $whatsAppImagePath = handle_image_upload('whatsapp_image', 'messages/whatsapp-assets', null) ?? $whatsAppImagePath;
            $draftFromPost['whatsapp_image_path'] = $whatsAppImagePath;
            if ($sendDebug !== null) {
                admin_message_debug_event($sendDebug, 'whatsapp_image_upload_ok', [
                    'whatsapp_image_path' => $whatsAppImagePath,
                ]);
            }
        } catch (RuntimeException $exception) {
            if ($sendDebug !== null) {
                admin_message_debug_event($sendDebug, 'whatsapp_image_upload_error', [
                    'error' => $exception->getMessage(),
                ]);
                admin_message_debug_store($sendDebug);
            }
            set_flash('error', $exception->getMessage());
            redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId), $postedActiveChannel));
        }
    }

    $draftFromPost['hero_image_path'] = $heroImagePath;
    $draftFromPost['whatsapp_image_path'] = $whatsAppImagePath;
    $draftFromPost['fabric_scene_json'] = admin_message_scene_json_value((string) ($draftFromPost['fabric_scene_json'] ?? ''), $draftFromPost);
    $draftFromPost['editor_engine'] = 'fabric_v2';
    if (trim((string) ($draftFromPost['fabric_scene_json'] ?? '')) !== '') {
        $draftFromPost['scene_json'] = admin_message_scene_json_value((string) ($draftFromPost['fabric_scene_json'] ?? ''), $draftFromPost);
    }
    $sceneJson = admin_message_scene_json_value((string) ($draftFromPost['scene_json'] ?? ''), $draftFromPost);
    $draftFromPost['scene_json'] = $sceneJson;
    $draftFromPost['fabric_scene_json'] = $sceneJson;
    $draftFromPost['editor_layers_json'] = admin_message_editor_layers_json_from_scene_value($sceneJson, $draftFromPost);
    $normalizedScene = customer_message_scene_from_context($draftFromPost);
    $derivedMessage = customer_message_scene_body_text($normalizedScene, $title);
    $derivedLinkUrl = customer_message_scene_primary_link(
        $normalizedScene,
        trim((string) ($draftFromPost['link_url'] ?? ''))
    );
    $derivedButtonLabel = customer_message_scene_button_label(
        $normalizedScene,
        trim((string) ($draftFromPost['button_label'] ?? ''))
    );
    $draftFromPost['message'] = $derivedMessage;
    $draftFromPost['link_url'] = $derivedLinkUrl !== null ? $derivedLinkUrl : '';
    $draftFromPost['button_label'] = $derivedButtonLabel;
    $message = $derivedMessage;
    $linkUrl = admin_message_normalize_link($draftFromPost['link_url']);
    $buttonLabel = trim((string) $draftFromPost['button_label']);
    if ($sendDebug !== null) {
        $sendDebug['server']['form'] = admin_message_debug_form_snapshot($draftFromPost);
        admin_message_debug_event($sendDebug, 'payload_normalized', [
            'scene_json_bytes' => strlen($sceneJson),
            'scene_layer_count' => admin_message_debug_json_count($sceneJson, 'scene'),
            'fabric_scene_layer_count' => admin_message_debug_json_count((string) ($draftFromPost['fabric_scene_json'] ?? ''), 'scene'),
            'scene_snapshot' => admin_message_debug_scene_snapshot((string) ($draftFromPost['scene_json'] ?? '')),
            'fabric_scene_snapshot' => admin_message_debug_scene_snapshot((string) ($draftFromPost['fabric_scene_json'] ?? '')),
            'editor_layers_snapshot' => admin_message_debug_layers_snapshot((string) ($draftFromPost['editor_layers_json'] ?? '')),
        ]);
    }

    if ($formAction === 'save_project') {
        $projectName = trim((string) ($draftFromPost['project_name'] ?? ''));

        if ($projectName === '') {
            if ($sendDebug !== null) {
                admin_message_debug_event($sendDebug, 'validation_error', [
                    'reason' => 'project_name_empty',
                ]);
                admin_message_debug_store($sendDebug);
            }
            set_flash('error', 'Informe um nome para salvar o projeto.');
            redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId), $postedActiveChannel));
        }

        $draftFromPost['scene_json'] = admin_message_scene_json_value((string) ($draftFromPost['scene_json'] ?? ''), $draftFromPost);
        if ($sendDebug !== null) {
            admin_message_debug_event($sendDebug, 'save_project_before_store', [
                'project_name' => $projectName,
                'draft_payload' => admin_message_debug_project_payload_snapshot($draftFromPost),
            ]);
        }
        try {
            $projectId = admin_message_project_store($draftFromPost, $projectName, $savedProjects);
        } catch (RuntimeException $exception) {
            if ($sendDebug !== null) {
                admin_message_debug_event($sendDebug, 'save_project_error', [
                    'project_name' => $projectName,
                    'error' => $exception->getMessage(),
                ]);
                admin_message_debug_store($sendDebug);
            }
            set_flash('error', $exception->getMessage());
            redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId), $postedActiveChannel));
        }
        if ($sendDebug !== null) {
            $savedProjectsAfter = admin_message_projects_load();
            $savedProject = admin_message_project_find($projectId, $savedProjectsAfter);
            admin_message_debug_event($sendDebug, 'save_project_after_store', [
                'project_id' => $projectId,
                'projects_file' => admin_message_projects_file(),
                'projects_file_mtime' => is_file(admin_message_projects_file()) ? date('c', (int) filemtime(admin_message_projects_file())) : null,
                'project_found_after_reload' => $savedProject !== null,
                'saved_project_record' => $savedProject ? [
                    'id' => (string) ($savedProject['id'] ?? ''),
                    'name' => (string) ($savedProject['name'] ?? ''),
                    'updated_at' => (string) ($savedProject['updated_at'] ?? ''),
                    'payload' => admin_message_debug_project_payload_snapshot((array) ($savedProject['payload'] ?? [])),
                ] : null,
            ]);
            $sendDebug['server']['summary'] = [
                'action' => 'save_project',
                'project_id' => $projectId,
                'project_name' => $projectName,
                'status' => 'saved',
            ];
            admin_message_debug_store($sendDebug);
        }
        set_flash('success', 'Projeto salvo com sucesso.');
        redirect(admin_message_redirect_url($projectId, $postedActiveChannel));
    }

    if ($title === '' && ($sendNotification === 1 || $sendEmail === 1)) {
        $writeSendAttemptLog('validation_error', 'Preencha o titulo antes de enviar.', [
            'error_reason' => 'title_empty',
            'title_bytes' => strlen($title),
            'message_bytes' => strlen($message),
        ]);
        if ($sendDebug !== null) {
            admin_message_debug_event($sendDebug, 'validation_error', [
                'reason' => 'title_empty',
                'title_bytes' => strlen($title),
                'message_bytes' => strlen($message),
            ]);
            admin_message_debug_store($sendDebug);
        }
        set_flash('error', 'Preencha o titulo antes de enviar.');
        redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId), $postedActiveChannel));
    }

    if ($sendNotification !== 1 && $sendEmail !== 1 && $sendWhatsApp !== 1) {
        $writeSendAttemptLog('validation_error', 'Escolha pelo menos um canal: notificacao, email ou WhatsApp.', [
            'error_reason' => 'no_channel_selected',
            'send_notification' => $sendNotification === 1,
            'send_email' => $sendEmail === 1,
            'send_whatsapp' => $sendWhatsApp === 1,
        ]);
        if ($sendDebug !== null) {
            admin_message_debug_event($sendDebug, 'validation_error', [
                'reason' => 'no_channel_selected',
                'send_notification' => $sendNotification,
                'send_email' => $sendEmail,
                'send_whatsapp' => $sendWhatsApp,
            ]);
            admin_message_debug_store($sendDebug);
        }
        set_flash('error', 'Escolha pelo menos um canal: notificacao, email ou WhatsApp.');
        redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId), $postedActiveChannel));
    }

    if (
        $sendWhatsApp === 1
        && $whatsAppMode === 'media'
        && trim((string) ($draftFromPost['whatsapp_message'] ?? '')) === ''
        && trim((string) ($draftFromPost['whatsapp_image_path'] ?? '')) === ''
    ) {
        if ($sendDebug !== null) {
            admin_message_debug_event($sendDebug, 'validation_error', [
                'reason' => 'whatsapp_content_empty',
            ]);
            admin_message_debug_store($sendDebug);
        }
        set_flash('error', 'Preencha a mensagem do WhatsApp ou escolha uma imagem antes de enviar.');
        redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId), $postedActiveChannel));
    }

    if ($sendWhatsApp === 1 && $whatsAppMode === 'button') {
        if (trim((string) ($draftFromPost['whatsapp_button_label'] ?? '')) === '') {
            $draftFromPost['whatsapp_button_label'] = 'Abrir link';
        }

        if (trim((string) ($draftFromPost['whatsapp_button_footer'] ?? '')) === '') {
            $draftFromPost['whatsapp_button_footer'] = 'Moda Tropical';
        }
    }

    if ($recipientMode === 'customer' && $customerId <= 0) {
        $writeSendAttemptLog('validation_error', 'Escolha o cliente que vai receber a mensagem.', [
            'error_reason' => 'customer_missing',
            'recipient_mode' => $recipientMode,
            'customer_id' => $customerId,
        ]);
        if ($sendDebug !== null) {
            admin_message_debug_event($sendDebug, 'validation_error', [
                'reason' => 'customer_missing',
                'recipient_mode' => $recipientMode,
                'customer_id' => $customerId,
            ]);
            admin_message_debug_store($sendDebug);
        }
        set_flash('error', 'Escolha o cliente que vai receber a mensagem.');
        redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId), $postedActiveChannel));
    }

    $targets = admin_message_target_customers($recipientMode, $customerId);

    if ($targets === []) {
        $writeSendAttemptLog('validation_error', 'Nenhum cliente ativo encontrado para esse envio.', [
            'error_reason' => 'no_targets',
            'recipient_mode' => $recipientMode,
        ]);
        if ($sendDebug !== null) {
            admin_message_debug_event($sendDebug, 'validation_error', [
                'reason' => 'no_targets',
                'recipient_mode' => $recipientMode,
            ]);
            admin_message_debug_store($sendDebug);
        }
        set_flash('error', 'Nenhum cliente ativo encontrado para esse envio.');
        redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId), $postedActiveChannel));
    }

    if ($sendDebug !== null) {
        admin_message_debug_event($sendDebug, 'targets_resolved', [
            'recipient_mode' => $recipientMode,
            'targets_count' => count($targets),
            'send_notification' => $sendNotification === 1,
            'send_email' => $sendEmail === 1,
            'send_whatsapp' => $sendWhatsApp === 1,
            'targets' => admin_message_send_log_targets_snapshot($targets),
        ]);
    }

    $messageQueueResult = null;
    $whatsAppQueueResult = null;

    try {
        if ($sendNotification === 1 || $sendEmail === 1) {
            $messageQueueResult = admin_message_queue_enqueue_campaign(
                $draftFromPost,
                $targets,
                $adminActor,
                $sendDebug
            );
        }

        if ($sendWhatsApp === 1) {
            $whatsAppQueueResult = admin_whatsapp_queue_enqueue_campaign(
                $draftFromPost,
                $targets,
                $adminActor,
                $sendDebug
            );
        }
    } catch (Throwable $exception) {
        $writeSendAttemptLog('enqueue_error', $exception->getMessage(), [
            'error_reason' => 'queue_enqueue_error',
            'targets_total' => count($targets),
            'targets_preview' => admin_message_send_log_targets_snapshot($targets),
            'email_batch_created' => !empty($messageQueueResult['created']),
            'whatsapp_batch_created' => !empty($whatsAppQueueResult['created']),
        ]);
        if ($sendDebug !== null) {
            admin_message_debug_event($sendDebug, 'queue_enqueue_error', [
                'error' => $exception->getMessage(),
                'email_batch_created' => !empty($messageQueueResult['created']),
                'whatsapp_batch_created' => !empty($whatsAppQueueResult['created']),
            ]);
            admin_message_debug_store($sendDebug);
        }
        set_flash('error', $exception->getMessage());
        redirect(admin_message_redirect_url((string) ($draftFromPost['project_id'] ?? $postedProjectId), $postedActiveChannel));
    }

    admin_message_queue_rotate_form_token();
    $batchPublicId = trim((string) (($messageQueueResult['batch']['public_id'] ?? '') ?: ''));
    $whatsappBatchPublicId = trim((string) (($whatsAppQueueResult['batch']['public_id'] ?? '') ?: ''));
    $totalJobsCount = (int) ($messageQueueResult['jobs_count'] ?? 0) + (int) ($whatsAppQueueResult['jobs_count'] ?? 0);
    $flashMessage = 'Lotes criados com sucesso para processamento em background.';
    $flashMessage .= ' Destinatarios resolvidos: ' . count($targets) . '.';
    $flashMessage .= ' Jobs totais: ' . $totalJobsCount . '.';

    if (($messageQueueResult['notification_jobs'] ?? 0) > 0) {
        $flashMessage .= ' Jobs com notificacao: ' . (int) $messageQueueResult['notification_jobs'] . '.';
    }

    if (($messageQueueResult['email_jobs'] ?? 0) > 0) {
        $flashMessage .= ' Jobs com email: ' . (int) $messageQueueResult['email_jobs'] . '.';
    }

    if (($messageQueueResult['email_skipped_count'] ?? 0) > 0) {
        $flashMessage .= ' Sem email e nao enfileirados para esse canal: ' . (int) $messageQueueResult['email_skipped_count'] . '.';
    }

    if (($whatsAppQueueResult['whatsapp_jobs'] ?? 0) > 0) {
        $flashMessage .= ' Jobs com WhatsApp: ' . (int) $whatsAppQueueResult['whatsapp_jobs'] . '.';
    }

    if (($whatsAppQueueResult['whatsapp_skipped_no_phone'] ?? 0) > 0) {
        $flashMessage .= ' Sem telefone valido no WhatsApp: ' . (int) $whatsAppQueueResult['whatsapp_skipped_no_phone'] . '.';
    }

    if ($batchPublicId !== '') {
        $flashMessage .= ' Lote email/notificacao: ' . $batchPublicId . '.';
    }

    if ($whatsappBatchPublicId !== '') {
        $flashMessage .= ' Lote WhatsApp: ' . $whatsappBatchPublicId . '.';
    }

    if ($batchPublicId !== '') {
        admin_message_send_log_write_state($batchPublicId, [
            'reason' => 'batch_created',
            'request_completed_at' => date('c'),
        ]);
    }

    admin_message_send_log_write_batch_audit('queued', $flashMessage, $batchPublicId, [
        'admin' => $adminActor,
        'draft' => $draftFromPost,
        'batch_public_id' => $batchPublicId,
        'whatsapp_batch_public_id' => $whatsappBatchPublicId,
        'batch_created' => !empty($messageQueueResult['created']),
        'batch_duplicate' => !empty($messageQueueResult['duplicate']),
        'whatsapp_batch_created' => !empty($whatsAppQueueResult['created']),
        'whatsapp_batch_duplicate' => !empty($whatsAppQueueResult['duplicate']),
        'targets_total' => count($targets),
        'targets' => admin_message_send_log_targets_snapshot($targets),
        'jobs_count' => $totalJobsCount,
        'notification_jobs' => (int) ($messageQueueResult['notification_jobs'] ?? 0),
        'email_jobs' => (int) ($messageQueueResult['email_jobs'] ?? 0),
        'email_skipped_count' => (int) ($messageQueueResult['email_skipped_count'] ?? 0),
        'whatsapp_jobs' => (int) ($whatsAppQueueResult['whatsapp_jobs'] ?? 0),
        'whatsapp_skipped_no_phone' => (int) ($whatsAppQueueResult['whatsapp_skipped_no_phone'] ?? 0),
    ]);

    if ($sendDebug !== null) {
        $batchSnapshot = $batchPublicId !== ''
            ? admin_message_debug_batch_snapshot($batchPublicId)
            : null;
        $sendDebug['server']['summary'] = [
            'batch_public_id' => $batchPublicId,
            'whatsapp_batch_public_id' => $whatsappBatchPublicId,
            'batch_created' => !empty($messageQueueResult['created']),
            'batch_duplicate' => !empty($messageQueueResult['duplicate']),
            'whatsapp_batch_created' => !empty($whatsAppQueueResult['created']),
            'whatsapp_batch_duplicate' => !empty($whatsAppQueueResult['duplicate']),
            'targets_total' => count($targets),
            'jobs_count' => $totalJobsCount,
            'notification_jobs' => (int) ($messageQueueResult['notification_jobs'] ?? 0),
            'email_jobs' => (int) ($messageQueueResult['email_jobs'] ?? 0),
            'email_skipped_count' => (int) ($messageQueueResult['email_skipped_count'] ?? 0),
            'whatsapp_jobs' => (int) ($whatsAppQueueResult['whatsapp_jobs'] ?? 0),
            'whatsapp_skipped_no_phone' => (int) ($whatsAppQueueResult['whatsapp_skipped_no_phone'] ?? 0),
            'flash_message' => $flashMessage,
        ];
        if ($batchSnapshot !== null) {
            $sendDebug['server']['batch_snapshot'] = $batchSnapshot;
        }
        admin_message_debug_event($sendDebug, 'enqueue_completed', $sendDebug['server']['summary']);
        admin_message_debug_store($sendDebug);
    }

    set_flash('success', $flashMessage);
    redirect(admin_message_redirect_url(null, $postedActiveChannel));
}

$currentAdminPage = 'mensagens';
$pageTitle = 'Mensagens';

$draft = admin_message_default_draft();
$requestedProjectId = isset($_GET['project']) ? trim((string) $_GET['project']) : '';
$messageChannelRequestedTab = admin_message_channel_value((string) ($_GET['channel'] ?? ''), '');
$whatsAppBootFallback = [
    'applied' => false,
    'reason' => $messageChannelRequestedTab === 'whatsapp' ? 'project_not_loaded' : 'channel_not_whatsapp',
    'fields' => [],
];
$shouldBootEmptyEditor = !is_post() && $requestedProjectId === '';

if (is_post()) {
    $draft = admin_message_post_draft();
} elseif ($requestedProjectId !== '') {
    $loadedProject = admin_message_project_find($requestedProjectId, $savedProjects);
    if ($loadedProject && !empty($loadedProject['payload']) && is_array($loadedProject['payload'])) {
        foreach ($draft as $key => $defaultValue) {
            if (array_key_exists($key, $loadedProject['payload'])) {
                $draft[$key] = (string) $loadedProject['payload'][$key];
            }
        }

        $whatsAppBootFallbackResult = admin_message_apply_whatsapp_boot_fallback($draft, $messageChannelRequestedTab);
        $draft = (array) ($whatsAppBootFallbackResult['draft'] ?? $draft);
        $whatsAppBootFallback = (array) ($whatsAppBootFallbackResult['meta'] ?? $whatsAppBootFallback);
    } elseif ($messageChannelRequestedTab === 'whatsapp') {
        $whatsAppBootFallback['reason'] = 'project_not_found';
    }
}

$draft['editor_engine'] = 'fabric_v2';

$draft['scene_json'] = admin_message_scene_json_value((string) ($draft['scene_json'] ?? ''), $draft);
$draft['fabric_scene_json'] = admin_message_scene_json_value(
    (string) (($draft['fabric_scene_json'] ?? '') !== '' ? $draft['fabric_scene_json'] : $draft['scene_json']),
    $draft
);

if ($shouldBootEmptyEditor) {
    $emptySceneJson = json_encode(
        customer_message_scene_defaults(),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?: '{"schemaVersion":1,"canvas":{"width":1080,"height":1620,"backgroundImage":""},"actions":{"primaryHrefRaw":"","imageHrefRaw":""},"layers":[]}';

    $draft['project_id'] = '';
    $draft['project_name'] = '';
    $draft['title'] = '';
    $draft['message'] = '';
    $draft['link_url'] = '';
    $draft['image_link_url'] = '';
    $draft['button_label'] = '';
    $draft['button_font_size'] = '24';
    $draft['hero_image_path'] = '';
    $draft['whatsapp_message'] = '';
    $draft['whatsapp_image_path'] = '';
    $draft['whatsapp_button_enabled'] = '0';
    $draft['whatsapp_link_preview_enabled'] = '0';
    $draft['whatsapp_button_title'] = '';
    $draft['whatsapp_button_label'] = '';
    $draft['whatsapp_button_url'] = '';
    $draft['whatsapp_button_footer'] = 'Moda Tropical';
    $draft['scene_json'] = $emptySceneJson;
    $draft['fabric_scene_json'] = $emptySceneJson;
    $draft['editor_layers_json'] = '[]';
    $draft['show_title'] = '0';
    $draft['show_body'] = '0';
    $draft['show_button'] = '0';
    $draft['show_image_hotspot'] = '0';
    $draft['send_whatsapp'] = '0';
}

$draft['editor_layers'] = customer_message_editor_layers([
    'email_editor_layers' => $draft['editor_layers_json'],
    'editor_layers_json' => $draft['editor_layers_json'],
]);
$draft['message_kind'] = isset($presets[$draft['message_kind']]) ? $draft['message_kind'] : 'manual';
$draft['whatsapp_mode'] = admin_message_whatsapp_mode($draft);

$messageEditorBootScene = json_decode((string) ($draft['fabric_scene_json'] ?? ''), true);
if (!is_array($messageEditorBootScene)) {
    $messageEditorBootScene = customer_message_scene_defaults();
}
$messageEditorBootSceneJson = json_encode(
    $messageEditorBootScene,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS
) ?: '{}';

$messageEditorBootLayers = json_decode((string) ($draft['editor_layers_json'] ?? ''), true);
if (!is_array($messageEditorBootLayers)) {
    $messageEditorBootLayers = [];
}
$messageEditorBootLayersJson = json_encode(
    $messageEditorBootLayers,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS
) ?: '[]';
$debugProjectQuery = isset($_GET['project']) ? trim((string) $_GET['project']) : '';
$debugProjectRecord = $debugProjectQuery !== ''
    ? admin_message_project_find($debugProjectQuery, $savedProjects)
    : null;
$debugBootLayerCount = isset($messageEditorBootScene['layers']) && is_array($messageEditorBootScene['layers'])
    ? count($messageEditorBootScene['layers'])
    : 0;
$debugEditorLayersCount = count($messageEditorBootLayers);
$draft['button_label'] = trim($draft['button_label']);
$draft['button_font_size'] = (string) customer_message_clamp_int($draft['button_font_size'] ?? null, 12, 96, 24);
$draft['project_name'] = trim((string) ($draft['project_name'] ?? ''));
$currentHeroImageUrl = $draft['hero_image_path'] !== '' ? customer_message_absolute_asset_url($draft['hero_image_path']) : null;
$currentWhatsAppImageUrl = $draft['whatsapp_image_path'] !== '' ? customer_message_absolute_asset_url($draft['whatsapp_image_path']) : null;
$currentWhatsAppPreviewUrl = trim((string) ($draft['whatsapp_button_url'] ?? ''));
$currentWhatsAppPreviewDomain = '';
if ($currentWhatsAppPreviewUrl !== '') {
    $currentWhatsAppPreviewDomain = trim((string) (parse_url($currentWhatsAppPreviewUrl, PHP_URL_HOST) ?? ''));
}
if ($currentWhatsAppPreviewDomain === '') {
    $currentWhatsAppPreviewDomain = 'modatropical.store';
}
$currentWhatsAppButtonEnabled = admin_message_whatsapp_button_enabled($draft);
$currentWhatsAppLinkPreviewEnabled = admin_message_whatsapp_link_preview_enabled($draft);
$currentWhatsAppMode = admin_message_whatsapp_mode($draft);
$whatsAppConnection = whatsapp_evolution_connection_payload(false);
$whatsAppConnectionInstance = is_array($whatsAppConnection['instance'] ?? null) ? (array) $whatsAppConnection['instance'] : [];
$whatsAppConnectionNumber = '';
foreach ([
    (string) ($whatsAppConnection['number'] ?? ''),
    (string) ($whatsAppConnectionInstance['number'] ?? ''),
    (string) ($whatsAppConnectionInstance['ownerJid'] ?? ''),
    (string) ($whatsAppConnectionInstance['owner'] ?? ''),
] as $candidate) {
    $candidate = trim($candidate);
    if ($candidate === '') {
        continue;
    }

    $candidate = preg_replace('/@.+$/', '', $candidate) ?: $candidate;
    $whatsAppConnectionNumber = $candidate;
    break;
}
$whatsAppConnectionStatus = 'disconnected';
$whatsAppConnectionStatusLabel = 'Desconectado';
$whatsAppConnectionMeta = 'Escaneie o QR para conectar o WhatsApp que vai fazer os disparos.';
$whatsAppConnectionError = trim((string) ($whatsAppConnection['error'] ?? ''));
$whatsAppConnectionQrDataUri = trim((string) ($whatsAppConnection['qr_data_uri'] ?? ''));
if (!($whatsAppConnection['enabled'] ?? false)) {
    $whatsAppConnectionStatus = 'disabled';
    $whatsAppConnectionStatusLabel = 'Desabilitado';
    $whatsAppConnectionMeta = 'A integracao com o Evolution Go nao esta habilitada nesta instalacao.';
} elseif ($whatsAppConnectionError !== '') {
    $whatsAppConnectionStatus = 'error';
    $whatsAppConnectionStatusLabel = 'Erro';
    $whatsAppConnectionMeta = 'Nao foi possivel consultar a conexao agora.';
} elseif (($whatsAppConnection['connected'] ?? false) === true) {
    $whatsAppConnectionStatus = 'connected';
    $whatsAppConnectionStatusLabel = 'Conectado';
    $whatsAppConnectionMeta = $whatsAppConnectionNumber !== ''
        ? $whatsAppConnectionNumber
        : 'WhatsApp conectado na instancia ' . (string) ($whatsAppConnection['instance_name'] ?? 'modatropical') . '.';
}
$whatsAppConnectionRefreshLabel = $whatsAppConnectionStatus === 'connected' ? 'Atualizar status' : 'Atualizar QR';
$messageChannelDefaultTab = in_array($messageChannelRequestedTab, ['email', 'whatsapp'], true) ? $messageChannelRequestedTab : 'email';
if ($messageChannelRequestedTab === '' && ($draft['send_whatsapp'] ?? '0') === '1' && ($draft['send_email'] ?? '0') !== '1') {
    $messageChannelDefaultTab = 'whatsapp';
}
$messageSendDebug = pull_session_value('admin_message_send_debug', null);
if (is_array($messageSendDebug)) {
    $messageSendDebug['server']['boot_after_redirect'] = [
        'query_project' => isset($_GET['project']) ? trim((string) $_GET['project']) : '',
        'draft_payload' => admin_message_debug_project_payload_snapshot($draft),
    ];
    $messageSendDebug['client']['beacon_after_redirect'] = admin_message_debug_read_beacon(
        (string) ($messageSendDebug['request_id'] ?? '')
    );
    $batchPublicId = trim((string) ($messageSendDebug['server']['summary']['batch_public_id'] ?? ''));
    if ($batchPublicId !== '') {
        $messageSendDebug['server']['batch_after_redirect'] = admin_message_debug_batch_snapshot($batchPublicId);
    }
    $whatsAppBatchPublicId = trim((string) ($messageSendDebug['server']['summary']['whatsapp_batch_public_id'] ?? ''));
    if ($whatsAppBatchPublicId !== '') {
        $messageSendDebug['server']['whatsapp_batch_after_redirect'] = admin_message_debug_whatsapp_batch_snapshot($whatsAppBatchPublicId);
    }
}
$messageDebugAction = is_array($messageSendDebug)
    ? trim((string) ($messageSendDebug['server']['action'] ?? ''))
    : '';
$messageDebugActionLabel = match ($messageDebugAction) {
    'save_project' => 'Salvar projeto',
    'send_message' => 'Enviar mensagem',
    default => 'Acao no formulario',
};
$messageSendDebugVisible = $messageDebugAction === 'send_message' && is_array($messageSendDebug);
$messageSendDebugSummary = $messageSendDebugVisible ? [
    'request_id' => (string) ($messageSendDebug['request_id'] ?? ''),
    'captured_at' => (string) ($messageSendDebug['captured_at'] ?? ''),
    'action' => $messageDebugActionLabel,
    'batch_public_id' => (string) ($messageSendDebug['server']['summary']['batch_public_id'] ?? ''),
    'batch_created' => (bool) ($messageSendDebug['server']['summary']['batch_created'] ?? false),
    'whatsapp_batch_public_id' => (string) ($messageSendDebug['server']['summary']['whatsapp_batch_public_id'] ?? ''),
    'whatsapp_batch_created' => (bool) ($messageSendDebug['server']['summary']['whatsapp_batch_created'] ?? false),
    'targets_total' => (int) ($messageSendDebug['server']['summary']['targets_total'] ?? 0),
    'jobs_count' => (int) ($messageSendDebug['server']['summary']['jobs_count'] ?? 0),
    'email_jobs' => (int) ($messageSendDebug['server']['summary']['email_jobs'] ?? 0),
    'notification_jobs' => (int) ($messageSendDebug['server']['summary']['notification_jobs'] ?? 0),
    'whatsapp_jobs' => (int) ($messageSendDebug['server']['summary']['whatsapp_jobs'] ?? 0),
    'whatsapp_skipped_no_phone' => (int) ($messageSendDebug['server']['summary']['whatsapp_skipped_no_phone'] ?? 0),
    'flash_message' => (string) ($messageSendDebug['server']['summary']['flash_message'] ?? ''),
] : null;
$messageSendDebugCombinedLog = '';
if ($messageSendDebugVisible) {
    $messageSendDebugSections = [
        admin_message_debug_pretty_json($messageSendDebugSummary),
        admin_message_debug_pretty_json((array) ($messageSendDebug['client']['submitted_entries'] ?? [])),
        admin_message_debug_pretty_json((array) ($messageSendDebug['client']['beacon_after_redirect'] ?? [])),
        admin_message_debug_pretty_json((array) ($messageSendDebug['server']['form'] ?? [])),
        admin_message_debug_pretty_json((array) ($messageSendDebug['server']['targets'] ?? [])),
        admin_message_debug_pretty_json((array) ($messageSendDebug['server']['events'] ?? [])),
        admin_message_debug_pretty_json((array) ($messageSendDebug['server']['batch_after_redirect'] ?? ($messageSendDebug['server']['batch_snapshot'] ?? []))),
    ];

    if (!empty($messageSendDebug['server']['whatsapp_batch_after_redirect'])) {
        $messageSendDebugSections[] = admin_message_debug_pretty_json((array) ($messageSendDebug['server']['whatsapp_batch_after_redirect'] ?? []));
    }

    $messageSendDebugSections[] = admin_message_debug_pretty_json((array) ($messageSendDebug['server']['boot_after_redirect'] ?? []));
    $messageSendDebugCombinedLog = implode(PHP_EOL . str_repeat('-', 35) . PHP_EOL, $messageSendDebugSections);
}
$messageProjectOpenDebugServerBoot = [
    'captured_at' => date('c'),
    'query' => [
        'project' => $requestedProjectId,
        'channel' => $messageChannelRequestedTab,
    ],
    'default_channel' => $messageChannelDefaultTab,
    'whatsapp_boot_fallback' => $whatsAppBootFallback,
    'draft_payload' => admin_message_debug_project_payload_snapshot($draft),
    'loaded_project_record' => $debugProjectRecord ? [
        'id' => (string) ($debugProjectRecord['id'] ?? ''),
        'name' => (string) ($debugProjectRecord['name'] ?? ''),
        'updated_at' => (string) ($debugProjectRecord['updated_at'] ?? ''),
        'payload' => admin_message_debug_project_payload_snapshot((array) ($debugProjectRecord['payload'] ?? [])),
    ] : null,
    'saved_projects_count' => count($savedProjects),
    'whatsapp_connection' => [
        'enabled' => (bool) ($whatsAppConnection['enabled'] ?? false),
        'connected' => (bool) ($whatsAppConnection['connected'] ?? false),
        'state' => (string) ($whatsAppConnection['state'] ?? ''),
        'number' => (string) ($whatsAppConnection['number'] ?? ''),
        'error' => $whatsAppConnectionError,
        'qr_len' => strlen((string) $whatsAppConnectionQrDataUri),
    ],
];
$messageProjectOpenDebugInitialLog = admin_message_debug_pretty_json($messageProjectOpenDebugServerBoot);
$messageEnqueueToken = admin_message_queue_form_token();

require BASE_PATH . '/includes/admin_header.php';
?>

<section class="panel-card panel-card--form panel-card--messages message-channel-switcher" data-message-channel-root data-default-tab="<?= e($messageChannelDefaultTab); ?>">
    <div class="message-channel-switcher__copy">
        <p class="panel-card__eyebrow">canais</p>
        <h2>Escolha o painel do disparo</h2>
        <p class="form-hint">Use <strong>EMAIL</strong> para editar projeto e arte. Use <strong>WHATSAPP</strong> para disparo simples com imagem e legenda.</p>
    </div>
    <div class="message-channel-tabs" role="tablist" aria-label="Canais de disparo">
        <button class="message-channel-tab is-active" type="button" role="tab" aria-selected="true" data-message-channel-tab="email">EMAIL</button>
        <button class="message-channel-tab" type="button" role="tab" aria-selected="false" data-message-channel-tab="whatsapp">WHATSAPP</button>
    </div>
    <p class="message-channel-switcher__summary form-hint" data-message-channel-summary>Envio marcado: Email</p>
</section>

<section class="panel-card panel-card--form panel-card--messages">
    <div class="panel-card__header">
        <div>
            <h2>Projetos salvos</h2>
        </div>
    </div>

    <?php if ($savedProjects !== []): ?>
        <div class="message-projects-grid">
            <?php foreach ($savedProjects as $project): ?>
                <?php $projectId = (string) ($project['id'] ?? ''); ?>
                <article class="message-project-card<?= $draft['project_id'] === $projectId ? ' is-active' : ''; ?>">
                    <div>
                        <strong><?= e((string) ($project['name'] ?? 'Projeto salvo')); ?></strong>
                        <p>Atualizado em <?= e(date('d/m/Y H:i', strtotime((string) ($project['updated_at'] ?? 'now')))); ?></p>
                    </div>
                    <a
                        class="button button--ghost button--small button--fit"
                        href="mensagens.php?project=<?= e(rawurlencode($projectId)); ?>&channel=<?= e($messageChannelDefaultTab); ?>"
                        data-open-project
                        data-open-base="mensagens.php?project=<?= e(rawurlencode($projectId)); ?>"
                        data-project-id="<?= e($projectId); ?>"
                        data-project-name="<?= e((string) ($project['name'] ?? 'Projeto salvo')); ?>"
                    >Abrir</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="form-hint">Quando voce salvar um layout, ele aparece aqui para abrir depois.</p>
    <?php endif; ?>
</section>

<section class="panel-card panel-card--form panel-card--messages">
    <div class="panel-card__header">
        <div>
            <h2>Disparar mensagens</h2>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" class="admin-form" data-message-form autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <input type="hidden" name="active_channel" id="active_channel" value="<?= e($messageChannelDefaultTab); ?>">
        <input type="hidden" name="message_kind" id="message_kind" value="<?= e($draft['message_kind']); ?>">
        <input type="hidden" name="project_id" value="<?= e($draft['project_id']); ?>">
        <input type="hidden" name="hero_image_path" id="current_hero_image_path" value="<?= e($draft['hero_image_path']); ?>">
        <input type="hidden" name="whatsapp_image_path" id="current_whatsapp_image_path" value="<?= e($draft['whatsapp_image_path']); ?>">
        <input type="hidden" name="whatsapp_button_enabled" id="whatsapp_button_enabled" value="<?= e($currentWhatsAppButtonEnabled ? '1' : '0'); ?>">
        <input type="hidden" name="whatsapp_link_preview_enabled" id="whatsapp_link_preview_enabled" value="<?= e($currentWhatsAppLinkPreviewEnabled ? '1' : '0'); ?>">
        <input type="hidden" name="whatsapp_mode" id="whatsapp_mode" value="<?= e($currentWhatsAppMode); ?>">
        <input type="hidden" name="scene_json" id="scene_json" value="<?= e($draft['scene_json']); ?>">
        <input type="hidden" name="fabric_scene_json" id="fabric_scene_json" value="<?= e($draft['fabric_scene_json']); ?>">
        <input type="hidden" name="editor_engine" id="editor_engine" value="<?= e($draft['editor_engine']); ?>">
        <input type="hidden" name="message_enqueue_token" value="<?= e($messageEnqueueToken); ?>">
        <input type="hidden" name="message_debug_capture" id="message_debug_capture" value="0">
        <input type="hidden" name="message_debug_request_id" id="message_debug_request_id" value="">
        <input type="hidden" name="message_debug_client_trace" id="message_debug_client_trace" value="[]">

        <div class="form-grid">
            <div class="form-row">
                <label for="recipient_mode">Destinatario</label>
                <select id="recipient_mode" name="recipient_mode" data-recipient-mode>
                    <option value="all" <?= selected($draft['recipient_mode'], 'all'); ?>>Todos os clientes ativos</option>
                    <option value="inactive_45" <?= selected($draft['recipient_mode'], 'inactive_45'); ?>>Clientes sem pedido ha 45 dias</option>
                    <option value="customer" <?= selected($draft['recipient_mode'], 'customer'); ?>>Cliente especifico</option>
                </select>
            </div>

            <div class="form-row" data-customer-row>
                <label for="customer_id">Cliente</label>
                <select id="customer_id" name="customer_id">
                    <option value="">Selecione um cliente</option>
                    <?php foreach ($activeCustomers as $customer): ?>
                        <option value="<?= e((string) ((int) ($customer['id'] ?? 0))); ?>" <?= selected($draft['customer_id'], (string) ((int) ($customer['id'] ?? 0))); ?>>
                            <?= e((string) ($customer['nome'] ?? 'Cliente')); ?><?= !empty($customer['email']) ? ' - ' . e((string) $customer['email']) : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-hint">Esse campo so e usado quando o destinatario for um cliente especifico.</p>
            </div>

            <div class="form-row form-row--wide">
                <label>Tags disponiveis</label>
                <div class="message-token-list">
                    <span>{{primeiro_nome}}</span>
                    <span>{{nome}}</span>
                    <span>{{saudacao_sumido}}</span>
                    <span>{{bem_vindo_ou_vinda}}</span>
                    <span>{{loja}}</span>
                </div>
                <p class="form-hint">Use essas tags para personalizar campanhas como boas-vindas e reengajamento.</p>
            </div>

            <div class="form-row form-row--wide" data-message-channel-panel="email">
                <div class="form-row__header">
                    <label for="title">Titulo</label>
                    <button class="button button--primary button--small button--fit" type="button" data-add-layer="title">Adicionar</button>
                </div>
                <input id="title" name="title" type="text" maxlength="140" value="<?= e($draft['title']); ?>" placeholder="Ex.: Seu pedido chegou, novidade da semana, cupom liberado...">
            </div>

            <div class="form-row form-row--wide" data-message-channel-panel="email">
                <div class="form-row__header">
                    <label for="button_label">Botao real do email</label>
                    <button class="button button--primary button--small button--fit" type="button" data-add-layer="button">Adicionar</button>
                </div>
                <input id="button_label" name="button_label" type="text" maxlength="60" value="<?= e((string) ($draft['button_label'] ?? '')); ?>" placeholder="Ex.: Ver promocoes, Usar cupom, Finalizar pedido">
            </div>

            <div class="form-row form-row--wide" data-message-channel-panel="email">
                <label for="link_url">URL do botao</label>
                <input id="link_url" name="link_url" type="url" maxlength="500" value="<?= e((string) ($draft['link_url'] ?? '')); ?>" placeholder="https://modatropical.store/promocoes">
            </div>

            <div class="form-row form-row--wide" data-message-channel-panel="email">
                <label for="button_font_size">Tamanho do texto do botao</label>
                <input id="button_font_size" name="button_font_size" type="number" min="12" max="96" step="1" value="<?= e((string) ($draft['button_font_size'] ?? '24')); ?>">
            </div>

            <div class="form-row form-row--wide" data-message-channel-panel="email">
                <input id="show_title" name="show_title" type="hidden" value="<?= e($draft['show_title']); ?>">
                <input id="show_body" name="show_body" type="hidden" value="<?= e($draft['show_body']); ?>">
                <input id="show_button" name="show_button" type="hidden" value="<?= e($draft['show_button']); ?>">
                <input id="show_image_hotspot" name="show_image_hotspot" type="hidden" value="<?= e($draft['show_image_hotspot']); ?>">
                <input id="image_hotspot_width" name="image_hotspot_width" type="hidden" value="<?= e($draft['image_hotspot_width']); ?>">
                <input id="image_hotspot_height" name="image_hotspot_height" type="hidden" value="<?= e($draft['image_hotspot_height']); ?>">
                <input id="message_style_align" type="hidden" value="">
                <input id="message_style_shadow" type="hidden" value="">
                <input id="message_style_bold" type="hidden" value="0">
                <input id="message_style_italic" type="hidden" value="0">
                <input id="message_style_uppercase" type="hidden" value="0">
                <input id="title_size" name="title_size" type="hidden" value="<?= e($draft['title_size']); ?>">
                <input id="body_size" name="body_size" type="hidden" value="<?= e($draft['body_size']); ?>">
                <input id="title_line_height" name="title_line_height" type="hidden" value="<?= e($draft['title_line_height']); ?>">
                <input id="body_line_height" name="body_line_height" type="hidden" value="<?= e($draft['body_line_height']); ?>">
                <input id="title_align" name="title_align" type="hidden" value="<?= e($draft['title_align']); ?>">
                <input id="body_align" name="body_align" type="hidden" value="<?= e($draft['body_align']); ?>">
                <input id="title_bold" name="title_bold" type="hidden" value="<?= e($draft['title_bold']); ?>">
                <input id="body_bold" name="body_bold" type="hidden" value="<?= e($draft['body_bold']); ?>">
                <input id="title_italic" name="title_italic" type="hidden" value="<?= e($draft['title_italic']); ?>">
                <input id="body_italic" name="body_italic" type="hidden" value="<?= e($draft['body_italic']); ?>">
                <input id="title_uppercase" name="title_uppercase" type="hidden" value="<?= e($draft['title_uppercase']); ?>">
                <input id="body_uppercase" name="body_uppercase" type="hidden" value="<?= e($draft['body_uppercase']); ?>">
                <input id="title_shadow" name="title_shadow" type="hidden" value="<?= e($draft['title_shadow']); ?>">
                <input id="body_shadow" name="body_shadow" type="hidden" value="<?= e($draft['body_shadow']); ?>">
                <input id="title_color" name="title_color" type="hidden" value="<?= e($draft['title_color']); ?>">
                <input id="body_color" name="body_color" type="hidden" value="<?= e($draft['body_color']); ?>">
                <input type="hidden" name="title_x" value="<?= e($draft['title_x']); ?>" data-layout-input="title_x">
                <input type="hidden" name="title_y" value="<?= e($draft['title_y']); ?>" data-layout-input="title_y">
                <input type="hidden" name="title_width" value="<?= e($draft['title_width']); ?>" data-layout-input="title_width">
                <input type="hidden" name="body_x" value="<?= e($draft['body_x']); ?>" data-layout-input="body_x">
                <input type="hidden" name="body_y" value="<?= e($draft['body_y']); ?>" data-layout-input="body_y">
                <input type="hidden" name="body_width" value="<?= e($draft['body_width']); ?>" data-layout-input="body_width">
                <input type="hidden" name="button_x" value="<?= e($draft['button_x']); ?>" data-layout-input="button_x">
                <input type="hidden" name="button_y" value="<?= e($draft['button_y']); ?>" data-layout-input="button_y">
                <input type="hidden" name="button_width" value="<?= e($draft['button_width']); ?>" data-layout-input="button_width">
                <input type="hidden" name="button_height" value="<?= e($draft['button_height']); ?>" data-layout-input="button_height">
                <input type="hidden" name="image_hotspot_x" value="<?= e($draft['image_hotspot_x']); ?>" data-layout-input="image_hotspot_x">
                <input type="hidden" name="image_hotspot_y" value="<?= e($draft['image_hotspot_y']); ?>" data-layout-input="image_hotspot_y">
                <input id="editor_layers_json" name="editor_layers_json" type="hidden" value="<?= e($draft['editor_layers_json']); ?>">
            </div>

            <div class="form-row form-row--wide" data-message-channel-panel="email">
                <div class="message-fabric-editor" data-message-fabric-root>
                    <div class="message-fabric-editor__toolbar">
                        <div class="message-fabric-editor__tools">
                            <button class="button button--ghost button--small" type="button" data-fabric-add="title">Novo titulo</button>
                            <button class="button button--ghost button--small" type="button" data-fabric-add="body">Novo texto</button>
                            <button class="button button--ghost button--small" type="button" data-fabric-add="button">Novo botao</button>
                        </div>
                    </div>
                    <div class="message-fabric-editor__workspace">
                        <div class="message-fabric-editor__canvas-area">
                            <div
                                class="message-fabric-editor__canvas-shell"
                                data-empty-label="Adicione ou crie um projeto"
                            >
                                <canvas
                                    id="message_editor_v2_canvas"
                                    data-message-fabric-canvas
                                    width="320"
                                    height="180"
                                ></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-row form-row--wide" data-message-channel-panel="whatsapp">
                <label>WhatsApp</label>
                <div class="message-channel-card">
                    <div class="message-channel-card__header message-channel-card__header--whatsapp">
                        <div
                            class="message-whatsapp-connection"
                            data-whatsapp-connection
                            data-status="<?= e($whatsAppConnectionStatus); ?>"
                            data-url="mensagens.php?whatsapp_connection_status=1"
                        >
                            <div class="message-whatsapp-connection__status">
                                <span class="message-whatsapp-connection__badge" data-whatsapp-connection-badge><?= e($whatsAppConnectionStatusLabel); ?></span>
                                <div class="message-whatsapp-connection__copy">
                                    <strong data-whatsapp-connection-title><?= e($whatsAppConnectionStatusLabel); ?></strong>
                                    <p data-whatsapp-connection-meta><?= e($whatsAppConnectionMeta); ?></p>
                                    <p class="message-whatsapp-connection__error" data-whatsapp-connection-error <?= $whatsAppConnectionError === '' ? 'hidden' : ''; ?>><?= e($whatsAppConnectionError); ?></p>
                                </div>
                            </div>
                            <div class="message-whatsapp-connection__actions">
                                <button class="button button--ghost button--small" type="button" data-whatsapp-connection-refresh><?= e($whatsAppConnectionRefreshLabel); ?></button>
                            </div>
                        </div>
                        <strong class="message-channel-card__title">Disparo por WhatsApp</strong>
                    </div>

                    <input name="send_whatsapp" type="hidden" value="<?= ($draft['send_whatsapp'] ?? '0') === '1' ? '1' : '0'; ?>" data-send-whatsapp-hidden>
                    <input type="hidden" id="whatsapp_boot_fallback_applied" value="<?= (($whatsAppBootFallback['applied'] ?? false) === true) ? '1' : '0'; ?>">
                    <input type="hidden" id="whatsapp_boot_fallback_fields" value="<?= e(implode(',', (array) ($whatsAppBootFallback['fields'] ?? []))); ?>">

                    <?php if (($whatsAppBootFallback['applied'] ?? false) === true): ?>
                        <p class="form-hint" data-whatsapp-fallback-notice>
                            Este projeto nao tinha conteudo proprio de WhatsApp salvo. A tela carregou a arte e a legenda do email como ponto de partida nesta abertura. Se quiser manter isso no projeto, salve novamente nesta aba.
                        </p>
                    <?php endif; ?>

                    <div class="message-whatsapp-connection__qr" data-whatsapp-connection-qr <?= (($whatsAppConnection['connected'] ?? false) === false && $whatsAppConnectionQrDataUri !== '') ? '' : 'hidden'; ?>>
                        <img
                            src="<?= e($whatsAppConnectionQrDataUri); ?>"
                            alt="QR Code do WhatsApp"
                            data-whatsapp-connection-qr-image
                        >
                    </div>

                    <div class="message-whatsapp-preview" data-whatsapp-preview-root data-whatsapp-mode="<?= e($currentWhatsAppMode); ?>">
                        <div class="message-whatsapp-stage" data-whatsapp-stage data-has-image="<?= $currentWhatsAppImageUrl !== null ? '1' : '0'; ?>">
                            <div class="message-whatsapp-stage__media" data-whatsapp-image-preview-wrap <?= $currentWhatsAppImageUrl === null ? 'hidden' : ''; ?>>
                                <img
                                    src="<?= e($currentWhatsAppImageUrl ?? ''); ?>"
                                    alt="Imagem atual do WhatsApp"
                                    data-whatsapp-image-preview-main
                                    data-persisted-src="<?= e($currentWhatsAppImageUrl ?? ''); ?>"
                                >
                            </div>
                            <span class="message-whatsapp-stage__placeholder">Escolha a imagem 1x1 do WhatsApp</span>
                        </div>
                        <div class="message-whatsapp-caption" data-whatsapp-caption data-has-content="<?= trim((string) ($draft['whatsapp_message'] ?? '')) !== '' ? '1' : '0'; ?>">
                            <textarea
                                id="whatsapp_message"
                                name="whatsapp_message"
                                class="message-whatsapp-caption__editor"
                                data-whatsapp-caption-input
                                rows="4"
                                placeholder="Digite aqui a legenda que vai junto com a imagem. As mesmas tags do email funcionam aqui, como {{primeiro_nome}}, {{nome}}, {{pedido}} ou {primeiro_nome}."
                            ><?= e($draft['whatsapp_message']); ?></textarea>
                            <div class="message-whatsapp-caption__meta">agora</div>
                        </div>
                        <div class="message-whatsapp-button-preview" data-whatsapp-button-preview <?= $currentWhatsAppButtonEnabled ? '' : 'hidden'; ?>>
                            <div class="message-whatsapp-button-preview__title" data-whatsapp-button-preview-title><?= e(trim((string) ($draft['whatsapp_button_title'] ?? '')) !== '' ? (string) ($draft['whatsapp_button_title'] ?? '') : ((trim((string) ($draft['title'] ?? '')) !== '') ? (string) ($draft['title'] ?? '') : (string) ($draft['project_name'] ?? 'Moda Tropical'))); ?></div>
                            <div class="message-whatsapp-button-preview__body" data-whatsapp-button-preview-body><?= nl2br(e((string) ($draft['whatsapp_message'] ?? ''))); ?></div>
                            <div class="message-whatsapp-button-preview__footer" data-whatsapp-button-preview-footer><?= e(trim((string) ($draft['whatsapp_button_footer'] ?? '')) !== '' ? (string) ($draft['whatsapp_button_footer'] ?? '') : 'Moda Tropical'); ?></div>
                            <div class="message-whatsapp-button-preview__cta" data-whatsapp-button-preview-cta><?= e(trim((string) ($draft['whatsapp_button_label'] ?? '')) !== '' ? (string) ($draft['whatsapp_button_label'] ?? '') : 'Abrir link'); ?></div>
                        </div>
                        <div class="message-whatsapp-link-preview" data-whatsapp-link-preview <?= $currentWhatsAppLinkPreviewEnabled ? '' : 'hidden'; ?>>
                            <div class="message-whatsapp-link-preview__thumb" data-whatsapp-link-preview-thumb><?= e(strtoupper(substr($currentWhatsAppPreviewDomain, 0, 2))); ?></div>
                            <div class="message-whatsapp-link-preview__content">
                                <div class="message-whatsapp-link-preview__title" data-whatsapp-link-preview-title>Preview do link</div>
                                <div class="message-whatsapp-link-preview__description" data-whatsapp-link-preview-description>O WhatsApp gera esse card automaticamente a partir da pagina do link.</div>
                                <div class="message-whatsapp-link-preview__url" data-whatsapp-link-preview-url><?= e($currentWhatsAppPreviewUrl !== '' ? $currentWhatsAppPreviewUrl : 'https://modatropical.store/promocoes'); ?></div>
                                <div class="message-whatsapp-link-preview__domain" data-whatsapp-link-preview-domain><?= e($currentWhatsAppPreviewDomain); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-row form-row--wide">
                        <label>CTA / Preview do WhatsApp</label>
                        <p class="form-hint">Opcional. Voce pode ativar button ou preview de link. Sem ativar nada, a mensagem segue no modo normal.</p>
                        <div class="message-action-row">
                            <button class="button button--ghost button--small button--fit" type="button" data-whatsapp-button-toggle><?= $currentWhatsAppButtonEnabled ? 'Remover Button' : 'Adicionar Button'; ?></button>
                            <button class="button button--ghost button--small button--fit" type="button" data-whatsapp-preview-toggle><?= $currentWhatsAppLinkPreviewEnabled ? 'Remover Preview' : 'Adicionar Preview'; ?></button>
                        </div>
                    </div>
                    <div class="message-whatsapp-button-fields" data-whatsapp-button-fields <?= ($currentWhatsAppButtonEnabled || $currentWhatsAppLinkPreviewEnabled) ? '' : 'hidden'; ?>>
                        <div class="form-row form-row--wide" data-whatsapp-cta-intro-row>
                            <label for="whatsapp_button_url" data-whatsapp-cta-intro-label><?= $currentWhatsAppLinkPreviewEnabled ? 'Preview do link do WhatsApp' : 'CTA opcional do WhatsApp'; ?></label>
                            <p class="form-hint" data-whatsapp-cta-intro-hint><?= $currentWhatsAppLinkPreviewEnabled ? 'No modo Preview, o sistema envia a imagem primeiro e depois uma segunda mensagem so com o link para o WhatsApp gerar o preview.' : 'No modo Button, o sistema tenta enviar a peca com CTA interativo.'; ?></p>
                        </div>
                        <div class="form-row form-row--wide" data-whatsapp-button-title-row <?= $currentWhatsAppButtonEnabled ? '' : 'hidden'; ?>>
                            <label for="whatsapp_button_title">Titulo do CTA</label>
                            <input id="whatsapp_button_title" name="whatsapp_button_title" type="text" maxlength="120" value="<?= e((string) ($draft['whatsapp_button_title'] ?? '')); ?>" placeholder="Promocoes | MODA TROPICAL">
                        </div>
                        <div class="form-row form-row--wide" data-whatsapp-button-label-row <?= $currentWhatsAppButtonEnabled ? '' : 'hidden'; ?>>
                            <label for="whatsapp_button_label">Texto do CTA</label>
                            <input id="whatsapp_button_label" name="whatsapp_button_label" type="text" maxlength="40" value="<?= e((string) ($draft['whatsapp_button_label'] ?? '')); ?>" placeholder="Abrir link">
                        </div>
                        <div class="form-row form-row--wide" data-whatsapp-url-row>
                            <label for="whatsapp_button_url" data-whatsapp-url-label><?= $currentWhatsAppLinkPreviewEnabled ? 'URL do Preview' : 'URL do CTA'; ?></label>
                            <input id="whatsapp_button_url" name="whatsapp_button_url" type="url" maxlength="500" value="<?= e((string) ($draft['whatsapp_button_url'] ?? '')); ?>" placeholder="https://modatropical.store/promocoes">
                        </div>
                        <div class="form-row form-row--wide" data-whatsapp-button-footer-row <?= $currentWhatsAppButtonEnabled ? '' : 'hidden'; ?>>
                            <label for="whatsapp_button_footer">Rodape do CTA</label>
                            <input id="whatsapp_button_footer" name="whatsapp_button_footer" type="text" maxlength="80" value="<?= e((string) ($draft['whatsapp_button_footer'] ?? '')); ?>" placeholder="Moda Tropical">
                        </div>
                    </div>
                    <div class="form-row form-row--wide" data-whatsapp-media-fields>
                        <label>Imagem do WhatsApp</label>
                        <p class="form-hint" data-whatsapp-media-hint>Se o button estiver ativo, esta imagem vira a peca principal enviada com a legenda e o link.</p>
                        <div class="message-whatsapp-upload-row">
                            <div class="admin-file-field admin-file-field--compact">
                                <input
                                    id="whatsapp_image"
                                    name="whatsapp_image"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    data-whatsapp-image-input
                                >
                                <label class="admin-file-field__button" for="whatsapp_image">Escolher imagem</label>
                                <span class="admin-file-field__name" data-whatsapp-image-name><?= e($draft['whatsapp_image_path'] !== '' ? basename($draft['whatsapp_image_path']) : 'Nenhuma imagem selecionada'); ?></span>
                            </div>
                            <button class="button button--ghost button--small" type="button" <?= $draft['whatsapp_image_path'] === '' ? 'hidden' : ''; ?> data-whatsapp-clear-image>Limpar imagem</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-row form-row--toggle">
                <label class="checkbox-row">
                    <input name="send_notification" type="checkbox" value="1" <?= checked($draft['send_notification'], 1); ?>>
                    <span>Enviar notificacao no site</span>
                </label>
            </div>

            <div class="form-row form-row--toggle" data-message-channel-panel="email">
                <label class="checkbox-row">
                    <input name="send_email" type="checkbox" value="1" <?= checked($draft['send_email'], 1); ?>>
                    <span>Enviar email</span>
                </label>
            </div>
        </div>

        <div class="form-actions form-actions--message-builder">
            <div class="message-form-actions__left">
                <div class="message-form-actions__email-tools" data-message-channel-panel="email">
                    <div class="admin-file-field admin-file-field--compact">
                        <input
                            id="hero_image"
                            name="hero_image"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp"
                            data-message-hero-input
                        >
                        <label class="admin-file-field__button" for="hero_image">Escolher imagem</label>
                        <span class="admin-file-field__name" data-message-hero-name><?= e($draft['hero_image_path'] !== '' ? basename($draft['hero_image_path']) : 'Nenhuma imagem selecionada'); ?></span>
                    </div>
                    <button class="button button--ghost button--small" type="button" <?= $draft['hero_image_path'] === '' ? 'hidden' : ''; ?> data-message-clear-image>Limpar imagem</button>
                </div>
                <input class="message-project-name" id="project_name" name="project_name" type="text" maxlength="120" value="<?= e($draft['project_name']); ?>" placeholder="Nome do projeto">
            </div>
            <div class="message-form-actions__right">
                <button class="button button--ghost" type="submit" name="form_action" value="save_project">Salvar projeto</button>
                <button class="button button--primary" type="submit" name="form_action" value="send_message">Enviar mensagem</button>
            </div>
        </div>
    </form>
</section>

<script>
window.messageEditorConfig = <?= json_encode([
    'sceneJson' => $draft['scene_json'],
    'fabricSceneJson' => $draft['fabric_scene_json'],
    'editorEngine' => $draft['editor_engine'],
    'currentHeroImageUrl' => $currentHeroImageUrl,
    'bootProjectId' => $draft['project_id'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS); ?>;
window.messageSendDebugConfig = <?= json_encode([
    'beaconUrl' => 'message_debug_ingest.php',
    'enabled' => true,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS); ?>;
window.messageProjectOpenDebugConfig = <?= json_encode([
    'enabled' => true,
    'storageKey' => 'mt_message_project_open_debug',
    'serverBoot' => $messageProjectOpenDebugServerBoot,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS); ?>;
</script>
<script type="application/json" id="message-editor-initial-scene"><?= $messageEditorBootSceneJson ?></script>
<script type="application/json" id="message-editor-initial-layers"><?= $messageEditorBootLayersJson ?></script>
<script src="<?= e(asset_url('assets/vendor/fabric.min.js')); ?>"></script>
<script src="<?= e(asset_url('assets/js/message-scene-renderer.js')); ?>"></script>
<script src="<?= e(asset_url('assets/js/admin-message-editor-v2.js')); ?>"></script>
<script>
(function (window, document) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('[data-message-channel-root]');
        if (!root) {
            return;
        }

        var storageKey = 'mt_messages_active_channel_tab';
        var buttons = Array.prototype.slice.call(root.querySelectorAll('[data-message-channel-tab]'));
        var panels = Array.prototype.slice.call(document.querySelectorAll('[data-message-channel-panel]'));
        var summary = root.querySelector('[data-message-channel-summary]');
        var form = document.querySelector('[data-message-form]');
        var activeChannelInput = form ? form.querySelector('input[name="active_channel"]') : null;
        var sendNotificationInput = form ? form.querySelector('input[name="send_notification"]') : null;
        var sendEmailInput = form ? form.querySelector('input[name="send_email"]') : null;
        var sendWhatsAppInput = form ? form.querySelector('[data-send-whatsapp-hidden]') : null;
        var projectOpenLinks = Array.prototype.slice.call(document.querySelectorAll('[data-open-project]'));

        var safeReadStoredTab = function () {
            try {
                return window.sessionStorage ? String(window.sessionStorage.getItem(storageKey) || '') : '';
            } catch (error) {
                return '';
            }
        };

        var safeStoreTab = function (value) {
            try {
                if (window.sessionStorage) {
                    window.sessionStorage.setItem(storageKey, value);
                }
            } catch (error) {
            }
        };

        var updateSummary = function () {
            if (!summary) {
                return;
            }

            var enabled = [];
            if (sendNotificationInput && sendNotificationInput.checked) {
                enabled.push('Notificacao');
            }
            if (sendEmailInput && sendEmailInput.checked) {
                enabled.push('Email');
            }
            if (sendWhatsAppInput && String(sendWhatsAppInput.value || '') === '1') {
                enabled.push('WhatsApp');
            }

            summary.textContent = enabled.length
                ? 'Envio marcado: ' + enabled.join(' + ')
                : 'Envio marcado: nenhum canal selecionado';
        };

        var syncChannelsForTab = function (nextTab) {
            if (nextTab === 'whatsapp') {
                if (sendWhatsAppInput) {
                    sendWhatsAppInput.value = '1';
                }
                if (sendEmailInput) {
                    sendEmailInput.checked = false;
                }
                return;
            }

            if (sendEmailInput) {
                sendEmailInput.checked = true;
            }
            if (sendWhatsAppInput) {
                sendWhatsAppInput.value = '0';
            }
        };

        var updateProjectOpenLinks = function (nextTab) {
            if (activeChannelInput) {
                activeChannelInput.value = nextTab;
            }

            projectOpenLinks.forEach(function (link) {
                var base = String(link.getAttribute('data-open-base') || '').trim();
                if (base === '') {
                    return;
                }

                var separator = base.indexOf('?') === -1 ? '?' : '&';
                link.href = base + separator + 'channel=' + encodeURIComponent(nextTab);
            });
        };

        var setActiveTab = function (value) {
            var nextTab = value === 'whatsapp' ? 'whatsapp' : 'email';
            syncChannelsForTab(nextTab);
            updateProjectOpenLinks(nextTab);

            root.setAttribute('data-active-tab', nextTab);
            buttons.forEach(function (button) {
                var active = String(button.getAttribute('data-message-channel-tab') || '') === nextTab;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            panels.forEach(function (panel) {
                panel.hidden = String(panel.getAttribute('data-message-channel-panel') || '') !== nextTab;
            });

            safeStoreTab(nextTab);
            updateSummary();
        };

        var query = null;
        try {
            query = new window.URLSearchParams(window.location.search || '');
        } catch (error) {
            query = null;
        }

        var shouldHonorQueryTab = !!(query && (query.has('project') || query.has('channel')));
        var defaultTab = String(root.getAttribute('data-default-tab') || 'email');
        var initialTab = shouldHonorQueryTab
            ? defaultTab
            : (safeReadStoredTab() || defaultTab);
        setActiveTab(initialTab);

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                setActiveTab(String(button.getAttribute('data-message-channel-tab') || 'email'));
            });
        });

        if (sendNotificationInput) {
            sendNotificationInput.addEventListener('change', updateSummary);
        }
        if (sendEmailInput) {
            sendEmailInput.addEventListener('change', updateSummary);
        }
        updateSummary();
    });
}(window, document));
</script>
<script>
(function (window, document) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('[data-whatsapp-connection]');
        if (!root) {
            return;
        }

        var refreshButton = root.querySelector('[data-whatsapp-connection-refresh]');
        var badge = root.querySelector('[data-whatsapp-connection-badge]');
        var title = root.querySelector('[data-whatsapp-connection-title]');
        var meta = root.querySelector('[data-whatsapp-connection-meta]');
        var errorNode = root.querySelector('[data-whatsapp-connection-error]');
        var qrWrap = document.querySelector('[data-whatsapp-connection-qr]');
        var qrImage = document.querySelector('[data-whatsapp-connection-qr-image]');
        var baseUrl = String(root.getAttribute('data-url') || '').trim();

        if (!refreshButton || !baseUrl || typeof window.fetch !== 'function') {
            return;
        }

        var extractNumber = function (payload) {
            var instance = payload && typeof payload.instance === 'object' && payload.instance ? payload.instance : {};
            var candidates = [
                payload && payload.number ? payload.number : '',
                instance.number || '',
                instance.ownerJid || '',
                instance.owner || ''
            ];

            for (var index = 0; index < candidates.length; index += 1) {
                var candidate = String(candidates[index] || '').trim();
                if (!candidate) {
                    continue;
                }

                return candidate.replace(/@.+$/, '');
            }

            return '';
        };

        var render = function (payload) {
            var enabled = !!(payload && payload.enabled);
            var connected = !!(payload && payload.connected);
            var errorMessage = String(payload && payload.error ? payload.error : '').trim();
            var instanceName = String(payload && payload.instance_name ? payload.instance_name : 'modatropical').trim() || 'modatropical';
            var qrDataUri = String(payload && payload.qr_data_uri ? payload.qr_data_uri : '').trim();
            var number = extractNumber(payload);
            var status = 'disconnected';
            var label = 'Desconectado';
            var metaText = 'Escaneie o QR para conectar o WhatsApp que vai fazer os disparos.';

            if (!enabled) {
                status = 'disabled';
                label = 'Desabilitado';
                metaText = 'A integracao com o Evolution Go nao esta habilitada nesta instalacao.';
            } else if (errorMessage !== '') {
                status = 'error';
                label = 'Erro';
                metaText = 'Nao foi possivel consultar a conexao agora.';
            } else if (connected) {
                status = 'connected';
                label = 'Conectado';
                metaText = number
                    ? number
                    : 'WhatsApp conectado na instancia ' + instanceName + '.';
            }

            root.setAttribute('data-status', status);

            if (badge) {
                badge.textContent = label;
            }
            if (title) {
                title.textContent = label;
            }
            if (meta) {
                meta.textContent = metaText;
            }
            if (errorNode) {
                errorNode.textContent = errorMessage;
                errorNode.hidden = errorMessage === '';
            }
            if (qrWrap) {
                qrWrap.hidden = !(status !== 'connected' && qrDataUri !== '');
            }
            if (qrImage && qrDataUri !== '') {
                qrImage.src = qrDataUri;
            }

            refreshButton.textContent = status === 'connected' ? 'Atualizar status' : 'Atualizar QR';
        };

        refreshButton.addEventListener('click', function () {
            refreshButton.disabled = true;
            refreshButton.textContent = 'Atualizando...';

            var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
            var requestUrl = baseUrl + separator + 'refresh=1&_=' + Date.now();

            window.fetch(requestUrl, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Falha ao atualizar a conexao do WhatsApp.');
                }

                return response.json();
            }).then(function (payload) {
                render(payload || {});
            }).catch(function (error) {
                render({
                    enabled: true,
                    connected: false,
                    state: 'error',
                    qr_data_uri: '',
                    error: String(error && error.message ? error.message : error)
                });
            }).finally(function () {
                refreshButton.disabled = false;
            });
        });
    });
}(window, document));
</script>
<script>
(function (window, document) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var copyButtons = Array.prototype.slice.call(document.querySelectorAll('[data-copy-log-button]'));
        if (copyButtons.length === 0) {
            return;
        }

        var copyText = function (value) {
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                return navigator.clipboard.writeText(value);
            }

            return new Promise(function (resolve, reject) {
                var helper = document.createElement('textarea');
                helper.value = value;
                helper.setAttribute('readonly', 'readonly');
                helper.style.position = 'absolute';
                helper.style.left = '-9999px';
                document.body.appendChild(helper);
                helper.select();

                try {
                    document.execCommand('copy');
                    document.body.removeChild(helper);
                    resolve();
                } catch (error) {
                    document.body.removeChild(helper);
                    reject(error);
                }
            });
        };

        copyButtons.forEach(function (copyButton) {
            var targetId = String(copyButton.getAttribute('data-copy-log-target') || '').trim();
            var target = targetId !== '' ? document.getElementById(targetId) : null;
            if (!target) {
                return;
            }

            copyButton.addEventListener('click', function () {
                var originalLabel = copyButton.textContent;
                copyText(target.textContent || '').then(function () {
                    copyButton.textContent = 'Log copiado';
                    window.setTimeout(function () {
                        copyButton.textContent = originalLabel;
                    }, 1800);
                }).catch(function () {
                    copyButton.textContent = 'Falha ao copiar';
                    window.setTimeout(function () {
                        copyButton.textContent = originalLabel;
                    }, 1800);
                });
            });
        });
    });
}(window, document));
</script>
<script>
(function (window, document) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var whatsAppImageInput = document.querySelector('[data-whatsapp-image-input]');
        var whatsAppImageName = document.querySelector('[data-whatsapp-image-name]');
        var whatsAppClearButton = document.querySelector('[data-whatsapp-clear-image]');
        var whatsAppPathInput = document.getElementById('current_whatsapp_image_path');
        var whatsAppMessageInput = document.getElementById('whatsapp_message');
        var whatsAppButtonEnabledInput = document.getElementById('whatsapp_button_enabled');
        var whatsAppLinkPreviewEnabledInput = document.getElementById('whatsapp_link_preview_enabled');
        var whatsAppModeInput = document.getElementById('whatsapp_mode');
        var whatsAppButtonToggle = document.querySelector('[data-whatsapp-button-toggle]');
        var whatsAppPreviewToggle = document.querySelector('[data-whatsapp-preview-toggle]');
        var whatsAppButtonTitleInput = document.getElementById('whatsapp_button_title');
        var whatsAppButtonLabelInput = document.getElementById('whatsapp_button_label');
        var whatsAppButtonUrlInput = document.getElementById('whatsapp_button_url');
        var whatsAppButtonFooterInput = document.getElementById('whatsapp_button_footer');
        var whatsAppButtonFields = document.querySelector('[data-whatsapp-button-fields]');
        var whatsAppCtaIntroLabel = document.querySelector('[data-whatsapp-cta-intro-label]');
        var whatsAppCtaIntroHint = document.querySelector('[data-whatsapp-cta-intro-hint]');
        var whatsAppButtonTitleRow = document.querySelector('[data-whatsapp-button-title-row]');
        var whatsAppButtonLabelRow = document.querySelector('[data-whatsapp-button-label-row]');
        var whatsAppButtonFooterRow = document.querySelector('[data-whatsapp-button-footer-row]');
        var whatsAppUrlLabel = document.querySelector('[data-whatsapp-url-label]');
        var whatsAppMediaHint = document.querySelector('[data-whatsapp-media-hint]');
        var titleInput = document.getElementById('title');
        var projectNameInput = document.getElementById('project_name');
        var whatsAppMediaFields = document.querySelector('[data-whatsapp-media-fields]');
        var whatsAppPreviewRoot = document.querySelector('[data-whatsapp-preview-root]');
        var stageRoot = document.querySelector('[data-whatsapp-stage]');
        var previewWrap = document.querySelector('[data-whatsapp-image-preview-wrap]');
        var previewImage = document.querySelector('[data-whatsapp-image-preview-main]');
        var captionRoot = document.querySelector('[data-whatsapp-caption]');
        var buttonPreviewRoot = document.querySelector('[data-whatsapp-button-preview]');
        var buttonPreviewTitle = document.querySelector('[data-whatsapp-button-preview-title]');
        var buttonPreviewBody = document.querySelector('[data-whatsapp-button-preview-body]');
        var buttonPreviewFooter = document.querySelector('[data-whatsapp-button-preview-footer]');
        var buttonPreviewCta = document.querySelector('[data-whatsapp-button-preview-cta]');
        var linkPreviewRoot = document.querySelector('[data-whatsapp-link-preview]');
        var linkPreviewThumb = document.querySelector('[data-whatsapp-link-preview-thumb]');
        var linkPreviewUrl = document.querySelector('[data-whatsapp-link-preview-url]');
        var linkPreviewDomain = document.querySelector('[data-whatsapp-link-preview-domain]');
        var objectUrl = '';

        var basename = function (value) {
            var normalized = String(value || '').replace(/\\/g, '/');
            var parts = normalized.split('/');
            return parts.length ? String(parts[parts.length - 1] || '') : '';
        };

        var revokeObjectUrl = function () {
            if (objectUrl) {
                try {
                    window.URL.revokeObjectURL(objectUrl);
                } catch (error) {
                }
                objectUrl = '';
            }
        };

        var escapeHtml = function (value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        var normalizeNewlines = function (value) {
            return String(value || '').replace(/\r\n?/g, '\n');
        };

        var formatPreviewText = function (value) {
            return escapeHtml(normalizeNewlines(value)).replace(/\n/g, '<br>');
        };

        var isWhatsAppButtonEnabled = function () {
            return whatsAppButtonEnabledInput && String(whatsAppButtonEnabledInput.value || '') === '1';
        };

        var isWhatsAppLinkPreviewEnabled = function () {
            return whatsAppLinkPreviewEnabledInput && String(whatsAppLinkPreviewEnabledInput.value || '') === '1';
        };

        var currentWhatsAppMode = function () {
            var hasUrl = whatsAppButtonUrlInput && String(whatsAppButtonUrlInput.value || '').trim() !== '';

            if (isWhatsAppButtonEnabled() && hasUrl) {
                return 'button';
            }

            if (isWhatsAppLinkPreviewEnabled() && hasUrl) {
                return 'link_preview';
            }

            return 'media';
        };

        var extractPreviewDomain = function (value) {
            var normalized = String(value || '').trim();
            if (normalized === '') {
                return 'modatropical.store';
            }

            try {
                return String(new window.URL(normalized).hostname || '').trim() || 'modatropical.store';
            } catch (error) {
                var fallback = normalized.replace(/^https?:\/\//i, '').split('/')[0] || '';
                return fallback !== '' ? fallback : 'modatropical.store';
            }
        };

        var resizeWhatsAppCaptionInput = function () {
            if (!whatsAppMessageInput) {
                return;
            }

            whatsAppMessageInput.style.height = '0px';
            whatsAppMessageInput.style.height = Math.max(96, whatsAppMessageInput.scrollHeight) + 'px';
        };

        var scheduleWhatsAppCaptionResize = function () {
            resizeWhatsAppCaptionInput();

            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(resizeWhatsAppCaptionInput);
            }
        };

        var syncWhatsAppStage = function () {
            if (!stageRoot || !previewWrap || !previewImage) {
                return;
            }

            var currentFile = whatsAppImageInput && whatsAppImageInput.files && whatsAppImageInput.files[0]
                ? whatsAppImageInput.files[0]
                : null;
            var currentPath = whatsAppPathInput ? String(whatsAppPathInput.value || '').trim() : '';
            var persistedSrc = previewImage ? String(previewImage.getAttribute('data-persisted-src') || '').trim() : '';
            var previewSrc = '';

            if (currentFile) {
                revokeObjectUrl();
                objectUrl = window.URL && typeof window.URL.createObjectURL === 'function'
                    ? window.URL.createObjectURL(currentFile)
                    : '';
                previewSrc = objectUrl;
            } else {
                revokeObjectUrl();
                previewSrc = currentPath !== '' ? persistedSrc : '';
            }

            if (previewSrc !== '') {
                previewImage.src = previewSrc;
                previewWrap.hidden = false;
                stageRoot.setAttribute('data-has-image', '1');
                return;
            }

            previewWrap.hidden = true;
            stageRoot.setAttribute('data-has-image', '0');
        };

        var syncWhatsAppCaption = function () {
            if (!captionRoot || !whatsAppMessageInput) {
                return;
            }

            var value = whatsAppMessageInput ? String(whatsAppMessageInput.value || '') : '';
            captionRoot.setAttribute('data-has-content', value === '' ? '0' : '1');
            scheduleWhatsAppCaptionResize();
        };

        var syncWhatsAppButtonPreview = function () {
            if (!buttonPreviewRoot) {
                return;
            }

            var titleValue = '';
            if (whatsAppButtonTitleInput && String(whatsAppButtonTitleInput.value || '').trim() !== '') {
                titleValue = String(whatsAppButtonTitleInput.value || '').trim();
            } else if (titleInput && String(titleInput.value || '').trim() !== '') {
                titleValue = String(titleInput.value || '').trim();
            } else if (projectNameInput && String(projectNameInput.value || '').trim() !== '') {
                titleValue = String(projectNameInput.value || '').trim();
            } else {
                titleValue = 'Moda Tropical';
            }

            var bodyValue = whatsAppMessageInput ? String(whatsAppMessageInput.value || '') : '';
            var footerValue = whatsAppButtonFooterInput && String(whatsAppButtonFooterInput.value || '').trim() !== ''
                ? String(whatsAppButtonFooterInput.value || '').trim()
                : 'Moda Tropical';
            var ctaValue = whatsAppButtonLabelInput && String(whatsAppButtonLabelInput.value || '').trim() !== ''
                ? String(whatsAppButtonLabelInput.value || '').trim()
                : 'Abrir link';

            if (buttonPreviewTitle) {
                buttonPreviewTitle.textContent = titleValue;
            }
            if (buttonPreviewBody) {
                buttonPreviewBody.innerHTML = formatPreviewText(bodyValue);
            }
            if (buttonPreviewFooter) {
                buttonPreviewFooter.textContent = footerValue;
            }
            if (buttonPreviewCta) {
                buttonPreviewCta.textContent = ctaValue;
            }
        };

        var syncWhatsAppLinkPreviewCard = function () {
            if (!linkPreviewRoot) {
                return;
            }

            var urlValue = whatsAppButtonUrlInput && String(whatsAppButtonUrlInput.value || '').trim() !== ''
                ? String(whatsAppButtonUrlInput.value || '').trim()
                : 'https://modatropical.store/promocoes';
            var domainValue = extractPreviewDomain(urlValue).replace(/^www\./i, '');
            var thumbValue = domainValue.slice(0, 2).toUpperCase();

            if (linkPreviewUrl) {
                linkPreviewUrl.textContent = urlValue;
            }
            if (linkPreviewDomain) {
                linkPreviewDomain.textContent = domainValue;
            }
            if (linkPreviewThumb) {
                linkPreviewThumb.textContent = thumbValue !== '' ? thumbValue : 'MT';
            }
        };

        var syncWhatsAppMode = function () {
            var mode = currentWhatsAppMode();
            var buttonEnabled = isWhatsAppButtonEnabled();
            var linkPreviewEnabled = isWhatsAppLinkPreviewEnabled();
            var ctaFieldsVisible = buttonEnabled || linkPreviewEnabled;

            if (whatsAppModeInput) {
                whatsAppModeInput.value = mode;
            }
            if (whatsAppButtonToggle) {
                whatsAppButtonToggle.textContent = buttonEnabled ? 'Remover Button' : 'Adicionar Button';
            }
            if (whatsAppPreviewToggle) {
                whatsAppPreviewToggle.textContent = linkPreviewEnabled ? 'Remover Preview' : 'Adicionar Preview';
            }
            if (whatsAppButtonFields) {
                whatsAppButtonFields.hidden = !ctaFieldsVisible;
            }
            if (whatsAppCtaIntroLabel) {
                whatsAppCtaIntroLabel.textContent = linkPreviewEnabled ? 'Preview do link do WhatsApp' : 'CTA opcional do WhatsApp';
            }
            if (whatsAppCtaIntroHint) {
                whatsAppCtaIntroHint.textContent = linkPreviewEnabled
                    ? 'No modo Preview, o sistema envia a imagem primeiro e depois uma segunda mensagem so com o link para o WhatsApp gerar o preview.'
                    : 'No modo Button, o sistema tenta enviar a peca com CTA interativo.';
            }
            if (whatsAppButtonTitleRow) {
                whatsAppButtonTitleRow.hidden = !buttonEnabled;
            }
            if (whatsAppButtonLabelRow) {
                whatsAppButtonLabelRow.hidden = !buttonEnabled;
            }
            if (whatsAppButtonFooterRow) {
                whatsAppButtonFooterRow.hidden = !buttonEnabled;
            }
            if (whatsAppUrlLabel) {
                whatsAppUrlLabel.textContent = linkPreviewEnabled ? 'URL do Preview' : 'URL do CTA';
            }
            if (whatsAppMediaHint) {
                whatsAppMediaHint.textContent = linkPreviewEnabled
                    ? 'No preview, a imagem sai primeiro com a legenda e o link vai numa segunda mensagem para gerar o card.'
                    : 'Se o button estiver ativo, esta imagem vira a peca principal enviada com a legenda e o link.';
            }
            if (whatsAppPreviewRoot) {
                whatsAppPreviewRoot.setAttribute('data-whatsapp-mode', mode);
            }
            if (stageRoot) {
                stageRoot.hidden = false;
            }
            if (whatsAppMediaFields) {
                whatsAppMediaFields.hidden = false;
            }
            if (buttonPreviewRoot) {
                buttonPreviewRoot.hidden = !buttonEnabled;
            }
            if (linkPreviewRoot) {
                linkPreviewRoot.hidden = !linkPreviewEnabled;
            }

            syncWhatsAppButtonPreview();
            syncWhatsAppLinkPreviewCard();
        };

        var updateWhatsAppImageField = function () {
            var currentFile = whatsAppImageInput && whatsAppImageInput.files && whatsAppImageInput.files[0]
                ? whatsAppImageInput.files[0]
                : null;
            var currentPath = whatsAppPathInput ? String(whatsAppPathInput.value || '') : '';

            if (whatsAppImageName) {
                if (currentFile) {
                    whatsAppImageName.textContent = currentFile.name;
                } else if (currentPath) {
                    whatsAppImageName.textContent = basename(currentPath);
                } else {
                    whatsAppImageName.textContent = 'Nenhuma imagem selecionada';
                }
            }

            if (whatsAppClearButton) {
                whatsAppClearButton.hidden = !currentFile && !currentPath;
            }

            syncWhatsAppStage();
        };

        if (whatsAppImageInput) {
            whatsAppImageInput.addEventListener('change', updateWhatsAppImageField);
        }

        if (whatsAppMessageInput) {
            whatsAppMessageInput.addEventListener('input', syncWhatsAppCaption);
            whatsAppMessageInput.addEventListener('change', scheduleWhatsAppCaptionResize);
            whatsAppMessageInput.addEventListener('keyup', scheduleWhatsAppCaptionResize);
            whatsAppMessageInput.addEventListener('paste', scheduleWhatsAppCaptionResize);
            whatsAppMessageInput.addEventListener('cut', scheduleWhatsAppCaptionResize);
            whatsAppMessageInput.addEventListener('input', syncWhatsAppButtonPreview);
        }

        ;[
            whatsAppButtonTitleInput,
            whatsAppButtonLabelInput,
            whatsAppButtonUrlInput,
            whatsAppButtonFooterInput,
            titleInput,
            projectNameInput
        ].forEach(function (input) {
            if (!input) {
                return;
            }

            input.addEventListener('input', syncWhatsAppMode);
            input.addEventListener('change', syncWhatsAppMode);
        });

        if (whatsAppClearButton) {
            whatsAppClearButton.addEventListener('click', function () {
                if (whatsAppImageInput) {
                    whatsAppImageInput.value = '';
                }
                if (whatsAppPathInput) {
                    whatsAppPathInput.value = '';
                }
                updateWhatsAppImageField();
            });
        }

        if (whatsAppButtonToggle) {
            whatsAppButtonToggle.addEventListener('click', function () {
                var enabled = !isWhatsAppButtonEnabled();

                if (whatsAppButtonEnabledInput) {
                    whatsAppButtonEnabledInput.value = enabled ? '1' : '0';
                }
                if (enabled && whatsAppLinkPreviewEnabledInput) {
                    whatsAppLinkPreviewEnabledInput.value = '0';
                }

                syncWhatsAppMode();

                if (enabled && whatsAppButtonUrlInput) {
                    whatsAppButtonUrlInput.focus();
                }
            });
        }

        if (whatsAppPreviewToggle) {
            whatsAppPreviewToggle.addEventListener('click', function () {
                var enabled = !isWhatsAppLinkPreviewEnabled();

                if (whatsAppLinkPreviewEnabledInput) {
                    whatsAppLinkPreviewEnabledInput.value = enabled ? '1' : '0';
                }
                if (enabled && whatsAppButtonEnabledInput) {
                    whatsAppButtonEnabledInput.value = '0';
                }

                syncWhatsAppMode();

                if (enabled && whatsAppButtonUrlInput) {
                    whatsAppButtonUrlInput.focus();
                }
            });
        }

        updateWhatsAppImageField();
        syncWhatsAppCaption();
        syncWhatsAppMode();

        if (window.ResizeObserver && captionRoot) {
            var whatsAppCaptionObserver = new window.ResizeObserver(function () {
                scheduleWhatsAppCaptionResize();
            });
            whatsAppCaptionObserver.observe(captionRoot);
        }

        window.addEventListener('load', scheduleWhatsAppCaptionResize);
    });
}(window, document));
</script>
<script>
(function (window, document) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var config = window.messageSendDebugConfig || {};
        var form = document.querySelector('[data-message-form]');
        if (!form || !config.enabled) {
            return;
        }

        var sendButton = form.querySelector('button[name="form_action"][value="send_message"]');
        var captureInput = document.getElementById('message_debug_capture');
        var requestIdInput = document.getElementById('message_debug_request_id');
        var traceInput = document.getElementById('message_debug_client_trace');
        var titleInput = document.getElementById('title');
        var projectIdInput = form.querySelector('input[name="project_id"]');
        var projectNameInput = document.getElementById('project_name');
        var recipientModeInput = document.getElementById('recipient_mode');
        var customerIdInput = document.getElementById('customer_id');
        var imageLinkInput = document.getElementById('image_link_url');
        var whatsappMessageInput = document.getElementById('whatsapp_message');
        var whatsappModeInput = document.getElementById('whatsapp_mode');
        var whatsappButtonTitleInput = document.getElementById('whatsapp_button_title');
        var whatsappButtonLabelInput = document.getElementById('whatsapp_button_label');
        var whatsappButtonUrlInput = document.getElementById('whatsapp_button_url');
        var whatsappButtonFooterInput = document.getElementById('whatsapp_button_footer');
        var sceneInput = document.getElementById('scene_json');
        var fabricSceneInput = document.getElementById('fabric_scene_json');
        var editorLayersInput = document.getElementById('editor_layers_json');
        var heroPathInput = document.getElementById('current_hero_image_path');
        var heroFileInput = document.getElementById('hero_image');
        var whatsappPathInput = document.getElementById('current_whatsapp_image_path');
        var whatsappFileInput = document.getElementById('whatsapp_image');
        var sendEmailInput = form.querySelector('input[name="send_email"]');
        var sendNotificationInput = form.querySelector('input[name="send_notification"]');
        var sendWhatsAppInput = form.querySelector('input[name="send_whatsapp"]');
        var entries = [];
        var pendingRequestId = '';
        var pendingAction = '';

        var textBytes = function (value) {
            var raw = String(value || '');
            if (window.TextEncoder) {
                return new window.TextEncoder().encode(raw).length;
            }
            return raw.length;
        };

        var truncate = function (value, max) {
            var raw = String(value || '');
            return raw.length > max ? raw.slice(0, max) + '...' : raw;
        };

        var safeParse = function (raw) {
            try {
                return JSON.parse(String(raw || '').trim() || 'null');
            } catch (error) {
                return null;
            }
        };

        var summarizeScene = function (raw, mode) {
            var text = String(raw || '');
            var parsed = safeParse(text);
            if (!parsed || typeof parsed !== 'object') {
                return {
                    bytes: text.length,
                    parseOk: false
                };
            }

            if (mode === 'layers') {
                return {
                    bytes: text.length,
                    parseOk: Array.isArray(parsed),
                    count: Array.isArray(parsed) ? parsed.length : 0
                };
            }

            return {
                bytes: text.length,
                parseOk: Array.isArray(parsed.layers),
                count: Array.isArray(parsed.layers) ? parsed.layers.length : 0,
                canvas: {
                    width: parsed.canvas && parsed.canvas.width ? Number(parsed.canvas.width) : 0,
                    height: parsed.canvas && parsed.canvas.height ? Number(parsed.canvas.height) : 0,
                    backgroundImage: parsed.canvas && parsed.canvas.backgroundImage ? String(parsed.canvas.backgroundImage) : ''
                }
            };
        };

        var describeElement = function (element) {
            if (!element) {
                return {};
            }

            return {
                tag: String((element.tagName || '')).toLowerCase(),
                id: String(element.id || ''),
                name: String(element.name || ''),
                value: String(element.value || ''),
                href: String(element.getAttribute('href') || ''),
                text: truncate(element.textContent || '', 120),
                type: String(element.type || ''),
                classes: String(element.className || '')
            };
        };

        var buildSnapshot = function () {
            var heroFile = heroFileInput && heroFileInput.files && heroFileInput.files[0] ? heroFileInput.files[0] : null;
            var whatsappFile = whatsappFileInput && whatsappFileInput.files && whatsappFileInput.files[0] ? whatsappFileInput.files[0] : null;
            return {
                locationHref: window.location.href,
                projectId: projectIdInput ? String(projectIdInput.value || '') : '',
                projectName: projectNameInput ? String(projectNameInput.value || '') : '',
                recipientMode: recipientModeInput ? String(recipientModeInput.value || '') : '',
                customerId: customerIdInput ? String(customerIdInput.value || '') : '',
                titleBytes: titleInput ? textBytes(titleInput.value) : 0,
                titlePreview: titleInput ? truncate(titleInput.value, 180) : '',
                whatsappMessageBytes: whatsappMessageInput ? textBytes(whatsappMessageInput.value) : 0,
                whatsappMessagePreview: whatsappMessageInput ? truncate(whatsappMessageInput.value, 180) : '',
                whatsappMode: whatsappModeInput ? String(whatsappModeInput.value || '') : '',
                whatsappButtonTitlePreview: whatsappButtonTitleInput ? truncate(whatsappButtonTitleInput.value, 120) : '',
                whatsappButtonLabel: whatsappButtonLabelInput ? String(whatsappButtonLabelInput.value || '') : '',
                whatsappButtonUrl: whatsappButtonUrlInput ? String(whatsappButtonUrlInput.value || '') : '',
                whatsappButtonFooter: whatsappButtonFooterInput ? String(whatsappButtonFooterInput.value || '') : '',
                imageLinkUrl: imageLinkInput ? String(imageLinkInput.value || '') : '',
                heroImagePath: heroPathInput ? String(heroPathInput.value || '') : '',
                heroFileName: heroFile ? String(heroFile.name || '') : '',
                heroFileSize: heroFile ? Number(heroFile.size || 0) : 0,
                heroFileType: heroFile ? String(heroFile.type || '') : '',
                whatsappImagePath: whatsappPathInput ? String(whatsappPathInput.value || '') : '',
                whatsappFileName: whatsappFile ? String(whatsappFile.name || '') : '',
                whatsappFileSize: whatsappFile ? Number(whatsappFile.size || 0) : 0,
                whatsappFileType: whatsappFile ? String(whatsappFile.type || '') : '',
                sendEmailChecked: !!(sendEmailInput && sendEmailInput.checked),
                sendNotificationChecked: !!(sendNotificationInput && sendNotificationInput.checked),
                sendWhatsAppChecked: !!(sendWhatsAppInput && String(sendWhatsAppInput.value || '') === '1'),
                sceneSummary: summarizeScene(sceneInput ? sceneInput.value : '', 'scene'),
                fabricSceneSummary: summarizeScene(fabricSceneInput ? fabricSceneInput.value : '', 'scene'),
                editorLayersSummary: summarizeScene(editorLayersInput ? editorLayersInput.value : '', 'layers')
            };
        };

        var pushEntry = function (step, detail) {
            entries.push({
                ts: new Date().toISOString(),
                step: step,
                detail: detail || {}
            });
            if (entries.length > 80) {
                entries = entries.slice(-80);
            }
        };

        var generateRequestId = function () {
            return 'msgdbg_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
        };

        var prepareHiddenFields = function () {
            if (!captureInput || !requestIdInput || !traceInput) {
                return;
            }

            if (!pendingRequestId) {
                pendingRequestId = generateRequestId();
            }

            captureInput.value = '1';
            requestIdInput.value = pendingRequestId;
            traceInput.value = JSON.stringify(entries);
        };

        var sendBeacon = function (reason) {
            if (!config.beaconUrl || !window.navigator || typeof window.navigator.sendBeacon !== 'function') {
                return;
            }

            var payload = {
                requestId: pendingRequestId,
                reason: reason,
                action: pendingAction || 'send_message',
                capturedAt: new Date().toISOString(),
                snapshot: buildSnapshot(),
                entries: entries.slice(-20)
            };
            var body = JSON.stringify(payload);
            pushEntry('cliente: beacon_preparado', {
                reason: reason,
                bodyBytes: body.length,
                traceEntriesCount: entries.length,
                heroFileSize: payload.snapshot.heroFileSize || 0
            });

            try {
                var ok = window.navigator.sendBeacon(config.beaconUrl, body);
                pushEntry('cliente: beacon_sendBeacon_resultado', {
                    reason: reason,
                    ok: !!ok,
                    bodyBytes: body.length
                });
            } catch (error) {
                pushEntry('cliente: beacon_sendBeacon_resultado', {
                    reason: reason,
                    ok: false,
                    bodyBytes: body.length,
                    error: String(error && error.message ? error.message : error)
                });
            }
        };

        if (sendButton) {
            sendButton.addEventListener('click', function () {
                pendingAction = 'send_message';
                pendingRequestId = generateRequestId();
                pushEntry('cliente: clique_enviar_mensagem', {
                    action: 'send_message',
                    actionLabel: 'Enviar mensagem',
                    button: describeElement(sendButton),
                    formSnapshot: buildSnapshot()
                });
                prepareHiddenFields();
                sendBeacon('click_send_message');
                prepareHiddenFields();
            });
        }

        form.addEventListener('submit', function (event) {
            var submitter = event.submitter || document.activeElement || null;
            if (!submitter || String(submitter.name || '') !== 'form_action' || String(submitter.value || '') !== 'send_message') {
                pendingAction = '';
                pendingRequestId = '';
                if (captureInput) {
                    captureInput.value = '0';
                }
                if (requestIdInput) {
                    requestIdInput.value = '';
                }
                if (traceInput) {
                    traceInput.value = '[]';
                }
                return;
            }

            pendingAction = 'send_message';
            if (!pendingRequestId) {
                pendingRequestId = generateRequestId();
            }

            pushEntry('cliente: submit_enviar_mensagem', {
                requestId: pendingRequestId,
                action: 'send_message',
                actionLabel: 'Enviar mensagem',
                button: describeElement(submitter),
                formSnapshot: buildSnapshot()
            });
            prepareHiddenFields();
        });
    });
}(window, document));
</script>
<script>
(function (window, document) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var config = window.messageProjectOpenDebugConfig || {};
        var output = document.getElementById('message-project-open-debug-log');
        if (!output || !config.enabled) {
            return;
        }

        var storageKey = String(config.storageKey || 'mt_message_project_open_debug').trim() || 'mt_message_project_open_debug';
        var root = document.querySelector('[data-message-channel-root]');
        var form = document.querySelector('[data-message-form]');
        var projectOpenLinks = Array.prototype.slice.call(document.querySelectorAll('[data-open-project]'));

        var truncate = function (value, max) {
            var raw = String(value || '');
            return raw.length > max ? raw.slice(0, max) + '...' : raw;
        };

        var safeParse = function (raw) {
            try {
                return JSON.parse(String(raw || '').trim() || 'null');
            } catch (error) {
                return null;
            }
        };

        var readQuery = function () {
            try {
                return new window.URLSearchParams(window.location.search || '');
            } catch (error) {
                return null;
            }
        };

        var readStorage = function () {
            try {
                return safeParse(window.sessionStorage ? window.sessionStorage.getItem(storageKey) : '') || {};
            } catch (error) {
                return {};
            }
        };

        var writeStorage = function (value) {
            try {
                if (window.sessionStorage) {
                    window.sessionStorage.setItem(storageKey, JSON.stringify(value || {}));
                }
            } catch (error) {
            }
        };

        var summarizeScene = function (raw, mode) {
            var text = String(raw || '');
            var parsed = safeParse(text);
            if (!parsed || typeof parsed !== 'object') {
                return {
                    bytes: text.length,
                    parseOk: false
                };
            }

            if (mode === 'layers') {
                return {
                    bytes: text.length,
                    parseOk: Array.isArray(parsed),
                    count: Array.isArray(parsed) ? parsed.length : 0
                };
            }

            return {
                bytes: text.length,
                parseOk: Array.isArray(parsed.layers),
                count: Array.isArray(parsed.layers) ? parsed.layers.length : 0,
                canvas: {
                    width: parsed.canvas && parsed.canvas.width ? Number(parsed.canvas.width) : 0,
                    height: parsed.canvas && parsed.canvas.height ? Number(parsed.canvas.height) : 0,
                    backgroundImage: parsed.canvas && parsed.canvas.backgroundImage ? String(parsed.canvas.backgroundImage) : ''
                }
            };
        };

        var describeElement = function (element) {
            if (!element) {
                return {};
            }

            return {
                tag: String((element.tagName || '')).toLowerCase(),
                id: String(element.id || ''),
                name: String(element.name || ''),
                href: String(element.getAttribute('href') || ''),
                text: truncate(element.textContent || '', 120),
                classes: String(element.className || ''),
                projectId: String(element.getAttribute('data-project-id') || ''),
                projectName: String(element.getAttribute('data-project-name') || '')
            };
        };

        var appendStoredEntry = function (step, detail) {
            var stored = readStorage();
            var entries = Array.isArray(stored.entries) ? stored.entries : [];
            entries.push({
                ts: new Date().toISOString(),
                step: step,
                detail: detail || {}
            });
            stored.entries = entries.slice(-40);
            stored.last_step = step;
            stored.last_updated_at = new Date().toISOString();
            writeStorage(stored);
        };

        var buildSnapshot = function (reason) {
            var query = readQuery();
            var projectIdInput = form ? form.querySelector('input[name="project_id"]') : null;
            var projectNameInput = document.getElementById('project_name');
            var activeChannelInput = document.getElementById('active_channel');
            var sendNotificationInput = form ? form.querySelector('input[name="send_notification"]') : null;
            var sendEmailInput = form ? form.querySelector('input[name="send_email"]') : null;
            var sendWhatsAppInput = form ? form.querySelector('input[name="send_whatsapp"]') : null;
            var sceneInput = document.getElementById('scene_json');
            var fabricSceneInput = document.getElementById('fabric_scene_json');
            var editorLayersInput = document.getElementById('editor_layers_json');
            var heroPathInput = document.getElementById('current_hero_image_path');
            var whatsappPathInput = document.getElementById('current_whatsapp_image_path');
            var whatsappMessageInput = document.getElementById('whatsapp_message');
            var whatsappModeInput = document.getElementById('whatsapp_mode');
            var whatsappBootFallbackAppliedInput = document.getElementById('whatsapp_boot_fallback_applied');
            var whatsappBootFallbackFieldsInput = document.getElementById('whatsapp_boot_fallback_fields');
            var whatsappButtonEnabledInput = document.getElementById('whatsapp_button_enabled');
            var whatsappLinkPreviewEnabledInput = document.getElementById('whatsapp_link_preview_enabled');
            var whatsappButtonTitleInput = document.getElementById('whatsapp_button_title');
            var whatsappButtonLabelInput = document.getElementById('whatsapp_button_label');
            var whatsappButtonUrlInput = document.getElementById('whatsapp_button_url');
            var whatsappButtonFooterInput = document.getElementById('whatsapp_button_footer');
            var whatsappPreviewRoot = document.querySelector('[data-whatsapp-preview-root]');
            var stageRoot = document.querySelector('[data-whatsapp-stage]');
            var previewWrap = document.querySelector('[data-whatsapp-image-preview-wrap]');
            var previewImage = document.querySelector('[data-whatsapp-image-preview-main]');
            var captionRoot = document.querySelector('[data-whatsapp-caption]');
            var buttonPreviewRoot = document.querySelector('[data-whatsapp-button-preview]');
            var linkPreviewRoot = document.querySelector('[data-whatsapp-link-preview]');
            var connectionRoot = document.querySelector('[data-whatsapp-connection]');
            var connectionBadge = document.querySelector('[data-whatsapp-connection-badge]');
            var connectionMeta = document.querySelector('[data-whatsapp-connection-meta]');
            var connectionError = document.querySelector('[data-whatsapp-connection-error]');
            var activeProjectCard = document.querySelector('.message-project-card.is-active [data-project-id]');
            var visiblePanels = Array.prototype.slice.call(document.querySelectorAll('[data-message-channel-panel]'))
                .filter(function (panel) { return panel && !panel.hidden; })
                .map(function (panel) { return String(panel.getAttribute('data-message-channel-panel') || ''); });

            return {
                reason: reason,
                capturedAt: new Date().toISOString(),
                locationHref: window.location.href,
                query: {
                    project: query ? String(query.get('project') || '') : '',
                    channel: query ? String(query.get('channel') || '') : ''
                },
                rootActiveTab: root ? String(root.getAttribute('data-active-tab') || '') : '',
                rootDefaultTab: root ? String(root.getAttribute('data-default-tab') || '') : '',
                visiblePanels: visiblePanels,
                form: {
                    projectId: projectIdInput ? String(projectIdInput.value || '') : '',
                    projectName: projectNameInput ? String(projectNameInput.value || '') : '',
                    activeChannel: activeChannelInput ? String(activeChannelInput.value || '') : '',
                    activeProjectCardId: activeProjectCard ? String(activeProjectCard.getAttribute('data-project-id') || '') : '',
                    sendNotificationChecked: !!(sendNotificationInput && sendNotificationInput.checked),
                    sendEmailChecked: !!(sendEmailInput && sendEmailInput.checked),
                    sendWhatsAppValue: sendWhatsAppInput ? String(sendWhatsAppInput.value || '') : ''
                },
                editor: {
                    bootProjectId: window.messageEditorConfig ? String(window.messageEditorConfig.bootProjectId || '') : '',
                    currentHeroImageUrl: window.messageEditorConfig ? String(window.messageEditorConfig.currentHeroImageUrl || '') : '',
                    heroImagePath: heroPathInput ? String(heroPathInput.value || '') : '',
                    sceneSummary: summarizeScene(sceneInput ? sceneInput.value : '', 'scene'),
                    fabricSceneSummary: summarizeScene(fabricSceneInput ? fabricSceneInput.value : '', 'scene'),
                    editorLayersSummary: summarizeScene(editorLayersInput ? editorLayersInput.value : '', 'layers')
                },
                whatsapp: {
                    connectionStatus: connectionRoot ? String(connectionRoot.getAttribute('data-status') || '') : '',
                    connectionBadge: connectionBadge ? String(connectionBadge.textContent || '').trim() : '',
                    connectionMeta: connectionMeta ? String(connectionMeta.textContent || '').trim() : '',
                    connectionError: connectionError && !connectionError.hidden ? String(connectionError.textContent || '').trim() : '',
                    mode: whatsappModeInput ? String(whatsappModeInput.value || '') : '',
                    messageBytes: whatsappMessageInput ? String(whatsappMessageInput.value || '').length : 0,
                    messagePreview: whatsappMessageInput ? truncate(whatsappMessageInput.value || '', 220) : '',
                    bootFallbackApplied: whatsappBootFallbackAppliedInput ? String(whatsappBootFallbackAppliedInput.value || '') === '1' : false,
                    bootFallbackFields: whatsappBootFallbackFieldsInput
                        ? String(whatsappBootFallbackFieldsInput.value || '').split(',').filter(function (value) { return String(value || '').trim() !== ''; })
                        : [],
                    imagePath: whatsappPathInput ? String(whatsappPathInput.value || '') : '',
                    imagePreviewSrc: previewImage ? String(previewImage.getAttribute('src') || '') : '',
                    previewImagePersistedSrc: previewImage ? String(previewImage.getAttribute('data-persisted-src') || '') : '',
                    stageHasImage: stageRoot ? String(stageRoot.getAttribute('data-has-image') || '') : '',
                    previewWrapHidden: previewWrap ? !!previewWrap.hidden : null,
                    captionHasContent: captionRoot ? String(captionRoot.getAttribute('data-has-content') || '') : '',
                    buttonEnabledValue: whatsappButtonEnabledInput ? String(whatsappButtonEnabledInput.value || '') : '',
                    linkPreviewEnabledValue: whatsappLinkPreviewEnabledInput ? String(whatsappLinkPreviewEnabledInput.value || '') : '',
                    buttonPreviewHidden: buttonPreviewRoot ? !!buttonPreviewRoot.hidden : null,
                    linkPreviewHidden: linkPreviewRoot ? !!linkPreviewRoot.hidden : null,
                    previewRootMode: whatsappPreviewRoot ? String(whatsappPreviewRoot.getAttribute('data-whatsapp-mode') || '') : '',
                    buttonTitle: whatsappButtonTitleInput ? String(whatsappButtonTitleInput.value || '') : '',
                    buttonLabel: whatsappButtonLabelInput ? String(whatsappButtonLabelInput.value || '') : '',
                    buttonUrl: whatsappButtonUrlInput ? String(whatsappButtonUrlInput.value || '') : '',
                    buttonFooter: whatsappButtonFooterInput ? String(whatsappButtonFooterInput.value || '') : ''
                },
                projectLinks: projectOpenLinks.slice(0, 12).map(function (link) {
                    return {
                        projectId: String(link.getAttribute('data-project-id') || ''),
                        projectName: String(link.getAttribute('data-project-name') || ''),
                        href: String(link.getAttribute('href') || '')
                    };
                })
            };
        };

        projectOpenLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                var stored = readStorage();
                stored.last_clicked_at = new Date().toISOString();
                stored.last_clicked_project_id = String(link.getAttribute('data-project-id') || '');
                stored.last_clicked_project_name = String(link.getAttribute('data-project-name') || '');
                stored.last_clicked_href = String(link.getAttribute('href') || '');
                stored.last_clicked_channel = root ? String(root.getAttribute('data-active-tab') || '') : '';
                writeStorage(stored);

                appendStoredEntry('client: click_open_project', {
                    link: describeElement(link),
                    snapshot: buildSnapshot('click_open_project')
                });
            });
        });

        var bootSnapshot = buildSnapshot('after_reload');
        appendStoredEntry('client: page_boot_after_reload', {
            snapshot: bootSnapshot
        });

        var storedAfterBoot = readStorage();
        var combined = {
            generatedAt: new Date().toISOString(),
            serverBoot: config.serverBoot || {},
            storedTrace: storedAfterBoot,
            currentBoot: bootSnapshot,
            diagnosis: {
                queryProjectMatchesHiddenProject: bootSnapshot.query.project !== ''
                    ? bootSnapshot.query.project === bootSnapshot.form.projectId
                    : null,
                queryChannelMatchesActiveTab: bootSnapshot.query.channel !== ''
                    ? bootSnapshot.query.channel === bootSnapshot.rootActiveTab
                    : null,
                whatsappBootFallbackApplied: !!(bootSnapshot.whatsapp && bootSnapshot.whatsapp.bootFallbackApplied),
                whatsappLooksEmpty: bootSnapshot.whatsapp.imagePath === ''
                    && bootSnapshot.whatsapp.messageBytes === 0
                    && bootSnapshot.whatsapp.buttonUrl === '',
                emailSceneHasLayers: (bootSnapshot.editor.fabricSceneSummary && bootSnapshot.editor.fabricSceneSummary.count ? bootSnapshot.editor.fabricSceneSummary.count : 0) > 0
            }
        };

        output.textContent = JSON.stringify(combined, null, 2);
    });
}(window, document));
</script>

<?php require BASE_PATH . '/includes/admin_footer.php'; ?>
