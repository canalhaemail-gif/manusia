<?php
declare(strict_types=1);

require_once __DIR__ . '/customer_message_scene.php';
require_once __DIR__ . '/customer_message_render_cache.php';

function customer_message_store_settings(): array
{
    static $settings = null;

    if ($settings === null) {
        $settings = fetch_store_settings();
    }

    return is_array($settings) ? $settings : [];
}

function customer_message_store_name(): string
{
    return (string) (customer_message_store_settings()['nome_estabelecimento'] ?? APP_NAME);
}

function customer_message_store_logo_url(): ?string
{
    $logoPath = trim((string) (customer_message_store_settings()['logo'] ?? ''));

    if ($logoPath === '') {
        return null;
    }

    return absolute_app_url(ltrim($logoPath, '/'));
}

function customer_message_first_name(?string $name): string
{
    $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    return (string) ($parts[0] ?? 'Cliente');
}

function customer_message_slugify_token(string $value): string
{
    $value = strtolower(trim($value));
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = is_string($converted) && $converted !== '' ? $converted : $value;
    $value = preg_replace('/[^a-z]/', '', $value) ?? '';

    return $value;
}

function customer_message_guess_gender(?string $name): ?string
{
    $firstName = customer_message_slugify_token(customer_message_first_name($name));

    if ($firstName === '') {
        return null;
    }

    $femaleNames = [
        'ana', 'maria', 'julia', 'beatriz', 'amanda', 'sophia', 'sofia', 'isabela',
        'larissa', 'marina', 'camila', 'valentina', 'gabriela', 'fernanda', 'luana',
    ];
    $maleNames = [
        'lucas', 'pedro', 'joao', 'gabriel', 'enzo', 'caio', 'matheus', 'mateus',
        'miguel', 'arthur', 'rafael', 'bruno', 'felipe', 'vinicius', 'gustavo',
    ];

    if (in_array($firstName, $femaleNames, true)) {
        return 'female';
    }

    if (in_array($firstName, $maleNames, true)) {
        return 'male';
    }

    if (str_ends_with($firstName, 'a')) {
        return 'female';
    }

    if (str_ends_with($firstName, 'o')) {
        return 'male';
    }

    return null;
}

function customer_message_gender_variant(?string $name, string $male, string $female, string $neutral): string
{
    return match (customer_message_guess_gender($name)) {
        'female' => $female,
        'male' => $male,
        default => $neutral,
    };
}

function customer_message_missing_you_greeting(array $customer = []): string
{
    return match (customer_message_guess_gender((string) ($customer['nome'] ?? ''))) {
        'female' => 'Oi, sumida!',
        'male' => 'Oi, sumido!',
        default => 'Oi, sentimos sua falta!',
    };
}

function customer_message_welcome_phrase(array $customer = []): string
{
    return customer_message_gender_variant(
        (string) ($customer['nome'] ?? ''),
        'Seja muito bem-vindo',
        'Seja muito bem-vinda',
        'Seja muito bem-vinda(o)'
    );
}

function customer_message_normalize_link(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $queryString = trim((string) parse_url($value, PHP_URL_QUERY));
    $querySuffix = $queryString !== '' ? ('?' . $queryString) : '';

    if (preg_match('~^/?promocoes(?:\.php)?/?(?:\?.*)?$~i', $value) === 1) {
        return promotions_url() . $querySuffix;
    }

    if (preg_match('~^https?://~i', $value) === 1) {
        $parsed = parse_url($value);
        $path = trim((string) ($parsed['path'] ?? ''));
        $host = strtolower(trim((string) ($parsed['host'] ?? '')));
        $appHost = strtolower(trim((string) parse_url(absolute_app_url(), PHP_URL_HOST)));

        if ($host !== '' && $appHost !== '' && $host === $appHost && preg_match('~^/promocoes(?:\.php)?/?$~i', $path) === 1) {
            return absolute_promotions_url() . $querySuffix;
        }
    }

    if (preg_match('~^https?://~i', $value) === 1) {
        return $value;
    }

    if (str_starts_with($value, '/')) {
        return $value;
    }

    return app_url(ltrim($value, '/'));
}

function customer_message_absolute_link(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $queryString = trim((string) parse_url($value, PHP_URL_QUERY));
    $querySuffix = $queryString !== '' ? ('?' . $queryString) : '';

    if (preg_match('~^/?promocoes(?:\.php)?/?(?:\?.*)?$~i', $value) === 1) {
        return absolute_promotions_url() . $querySuffix;
    }

    if (preg_match('~^https?://~i', $value) === 1) {
        $parsed = parse_url($value);
        $path = trim((string) ($parsed['path'] ?? ''));
        $host = strtolower(trim((string) ($parsed['host'] ?? '')));
        $appHost = strtolower(trim((string) parse_url(absolute_app_url(), PHP_URL_HOST)));

        if ($host !== '' && $appHost !== '' && $host === $appHost && preg_match('~^/promocoes(?:\.php)?/?$~i', $path) === 1) {
            return absolute_promotions_url() . $querySuffix;
        }
    }

    if (preg_match('~^https?://~i', $value) === 1) {
        return $value;
    }

    return absolute_app_url(ltrim($value, '/'));
}

function customer_message_absolute_asset_url(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    if (preg_match('~^https?://~i', $value) === 1) {
        return $value;
    }

    return absolute_app_url(ltrim($value, '/'));
}

function customer_message_hero_position_options(): array
{
    return [
        'top-left' => 'Superior esquerda',
        'top-center' => 'Superior centro',
        'top-right' => 'Superior direita',
        'center-left' => 'Centro esquerda',
        'center' => 'Centro',
        'center-right' => 'Centro direita',
        'bottom-left' => 'Inferior esquerda',
        'bottom-center' => 'Inferior centro',
        'bottom-right' => 'Inferior direita',
    ];
}

function customer_message_normalize_hero_position(?string $position): string
{
    $position = trim((string) $position);
    $options = customer_message_hero_position_options();

    return array_key_exists($position, $options) ? $position : 'bottom-left';
}

function customer_message_clamp_int($value, int $min, int $max, int $fallback): int
{
    if (!is_numeric($value)) {
        return $fallback;
    }

    $value = (int) round((float) $value);

    if ($value < $min) {
        return $min;
    }

    if ($value > $max) {
        return $max;
    }

    return $value;
}

function customer_message_color_value($value, string $fallback): string
{
    $value = trim((string) $value);

    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1) {
        return strtolower($value);
    }

    return $fallback;
}

function customer_message_bool_value($value, bool $fallback): bool
{
    if ($value === null) {
        return $fallback;
    }

    if (is_bool($value)) {
        return $value;
    }

    return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
}

function customer_message_editor_defaults(): array
{
    return [
        'show_title' => true,
        'show_body' => true,
        'show_button' => true,
        'show_image_hotspot' => false,
        'title_x' => 8,
        'title_y' => 10,
        'title_width' => 72,
        'body_x' => 8,
        'body_y' => 30,
        'body_width' => 72,
        'title_size' => 78,
        'body_size' => 24,
        'title_line_height' => 104,
        'body_line_height' => 175,
        'title_align' => 'left',
        'body_align' => 'left',
        'title_bold' => true,
        'body_bold' => false,
        'title_italic' => false,
        'body_italic' => false,
        'title_uppercase' => false,
        'body_uppercase' => false,
        'title_shadow' => 'strong',
        'body_shadow' => 'soft',
        'title_color' => '#fff7f0',
        'body_color' => '#2c1917',
        'button_x' => 24,
        'button_y' => 82,
        'button_width' => 26,
        'button_height' => 11,
        'button_font_size' => 24,
        'image_hotspot_x' => 33,
        'image_hotspot_y' => 47,
        'image_hotspot_width' => 34,
        'image_hotspot_height' => 32,
        'image_link_url' => '',
        'button_label' => '',
    ];
}

function customer_message_editor_align_value($value, string $fallback): string
{
    $value = trim((string) $value);

    return in_array($value, ['left', 'center', 'right'], true) ? $value : $fallback;
}

function customer_message_editor_shadow_value($value, string $fallback): string
{
    $value = trim((string) $value);

    return in_array($value, ['off', 'soft', 'strong'], true) ? $value : $fallback;
}

function customer_message_editor_normalize_text_content(string $text, bool $preserveParagraphs = true): string
{
    $normalized = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
    $normalized = preg_replace('/[ \t]+/u', ' ', $normalized) ?? $normalized;

    $lines = explode("\n", $normalized);
    $lines = array_map(
        static function (string $line): string {
            return trim($line);
        },
        $lines
    );

    $normalized = implode("\n", $lines);

    if ($preserveParagraphs) {
        $normalized = preg_replace("/\n{3,}/", "\n\n", $normalized) ?? $normalized;
    } else {
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    }

    return trim($normalized);
}

function customer_message_editor_settings(array $context = []): array
{
    $defaults = customer_message_editor_defaults();
    $settings = array_key_exists('email_editor', $context) && is_array($context['email_editor'])
        ? (array) $context['email_editor']
        : $context;

    return [
        'show_title' => customer_message_bool_value($settings['show_title'] ?? null, $defaults['show_title']),
        'show_body' => customer_message_bool_value($settings['show_body'] ?? null, $defaults['show_body']),
        'show_button' => customer_message_bool_value($settings['show_button'] ?? null, $defaults['show_button']),
        'show_image_hotspot' => customer_message_bool_value($settings['show_image_hotspot'] ?? null, $defaults['show_image_hotspot']),
        'title_x' => customer_message_clamp_int($settings['title_x'] ?? null, 0, 86, $defaults['title_x']),
        'title_y' => customer_message_clamp_int($settings['title_y'] ?? null, 0, 88, $defaults['title_y']),
        'title_width' => customer_message_clamp_int($settings['title_width'] ?? null, 12, 90, $defaults['title_width']),
        'body_x' => customer_message_clamp_int($settings['body_x'] ?? null, 0, 86, $defaults['body_x']),
        'body_y' => customer_message_clamp_int($settings['body_y'] ?? null, 0, 90, $defaults['body_y']),
        'body_width' => customer_message_clamp_int($settings['body_width'] ?? null, 12, 90, $defaults['body_width']),
        'title_size' => customer_message_clamp_int($settings['title_size'] ?? null, 22, 86, $defaults['title_size']),
        'body_size' => customer_message_clamp_int($settings['body_size'] ?? null, 12, 34, $defaults['body_size']),
        'title_line_height' => customer_message_clamp_int($settings['title_line_height'] ?? null, 80, 220, $defaults['title_line_height']),
        'body_line_height' => customer_message_clamp_int($settings['body_line_height'] ?? null, 100, 260, $defaults['body_line_height']),
        'title_align' => customer_message_editor_align_value($settings['title_align'] ?? null, $defaults['title_align']),
        'body_align' => customer_message_editor_align_value($settings['body_align'] ?? null, $defaults['body_align']),
        'title_bold' => customer_message_bool_value($settings['title_bold'] ?? null, $defaults['title_bold']),
        'body_bold' => customer_message_bool_value($settings['body_bold'] ?? null, $defaults['body_bold']),
        'title_italic' => customer_message_bool_value($settings['title_italic'] ?? null, $defaults['title_italic']),
        'body_italic' => customer_message_bool_value($settings['body_italic'] ?? null, $defaults['body_italic']),
        'title_uppercase' => customer_message_bool_value($settings['title_uppercase'] ?? null, $defaults['title_uppercase']),
        'body_uppercase' => customer_message_bool_value($settings['body_uppercase'] ?? null, $defaults['body_uppercase']),
        'title_shadow' => customer_message_editor_shadow_value($settings['title_shadow'] ?? null, $defaults['title_shadow']),
        'body_shadow' => customer_message_editor_shadow_value($settings['body_shadow'] ?? null, $defaults['body_shadow']),
        'title_color' => customer_message_color_value($settings['title_color'] ?? null, $defaults['title_color']),
        'body_color' => customer_message_color_value($settings['body_color'] ?? null, $defaults['body_color']),
        'button_x' => customer_message_clamp_int($settings['button_x'] ?? null, 0, 85, $defaults['button_x']),
        'button_y' => customer_message_clamp_int($settings['button_y'] ?? null, 0, 92, $defaults['button_y']),
        'button_width' => customer_message_clamp_int($settings['button_width'] ?? null, 10, 70, $defaults['button_width']),
        'button_height' => customer_message_clamp_int($settings['button_height'] ?? null, 6, 26, $defaults['button_height']),
        'button_font_size' => customer_message_clamp_int($settings['button_font_size'] ?? null, 12, 96, $defaults['button_font_size']),
        'image_hotspot_x' => customer_message_clamp_int($settings['image_hotspot_x'] ?? null, 0, 90, $defaults['image_hotspot_x']),
        'image_hotspot_y' => customer_message_clamp_int($settings['image_hotspot_y'] ?? null, 0, 94, $defaults['image_hotspot_y']),
        'image_hotspot_width' => customer_message_clamp_int($settings['image_hotspot_width'] ?? null, 4, 90, $defaults['image_hotspot_width']),
        'image_hotspot_height' => customer_message_clamp_int($settings['image_hotspot_height'] ?? null, 4, 90, $defaults['image_hotspot_height']),
        'image_link_url' => trim((string) ($settings['image_link_url'] ?? $defaults['image_link_url'])),
        'button_label' => trim((string) ($settings['button_label'] ?? $defaults['button_label'])),
    ];
}

function customer_message_editor_layers(array $context = []): array
{
    $rawLayers = $context['email_editor_layers'] ?? $context['editor_layers'] ?? [];

    if (is_string($rawLayers)) {
        $decoded = json_decode($rawLayers, true);
        $rawLayers = is_array($decoded) ? $decoded : [];
    }

    if (!is_array($rawLayers)) {
        $rawLayers = [];
    }

    if ($rawLayers === [] && isset($context['editor_layers_json'])) {
        $json = $context['editor_layers_json'];
        if (is_string($json) && trim($json) !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $rawLayers = $decoded;
            }
        }
    }

    if ($rawLayers === []) {
        return [];
    }

    $defaults = customer_message_editor_defaults();
    $normalized = [];

    foreach ($rawLayers as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $type = trim((string) ($item['type'] ?? ''));

        if (!in_array($type, ['title', 'body', 'button', 'hotspot'], true)) {
            continue;
        }

        $defaultX = match ($type) {
            'title' => $defaults['title_x'],
            'body' => $defaults['body_x'],
            'button' => $defaults['button_x'],
            'hotspot' => $defaults['image_hotspot_x'],
        };
        $defaultY = match ($type) {
            'title' => $defaults['title_y'],
            'body' => $defaults['body_y'],
            'button' => $defaults['button_y'],
            'hotspot' => $defaults['image_hotspot_y'],
        };

        $content = customer_message_editor_normalize_text_content(
            (string) ($item['content'] ?? $item['label'] ?? ''),
            $type !== 'title'
        );
        $linkUrl = customer_message_absolute_link((string) ($item['link_url'] ?? ''));
        $id = preg_replace('/[^a-z0-9_-]/i', '', (string) ($item['id'] ?? ('layer_' . $index))) ?? ('layer_' . $index);

        if (in_array($type, ['title', 'body', 'button'], true) && $content === '') {
            continue;
        }

        if ($type === 'hotspot' && $linkUrl === null) {
            continue;
        }

        $normalized[] = [
            'id' => $id,
            'type' => $type,
            'content' => $content,
            'link_url' => $linkUrl,
            'x' => customer_message_clamp_int($item['x'] ?? null, 0, 90, $defaultX),
            'y' => customer_message_clamp_int($item['y'] ?? null, 0, 94, $defaultY),
            'width' => customer_message_clamp_int($item['width'] ?? null, 4, 90, $defaults['image_hotspot_width']),
            'height' => customer_message_clamp_int($item['height'] ?? null, 4, 90, $defaults['image_hotspot_height']),
            'font_size' => customer_message_clamp_int(
                $item['font_size'] ?? null,
                $type === 'title' ? 22 : 12,
                $type === 'title' ? 86 : 34,
                $type === 'title' ? $defaults['title_size'] : $defaults['body_size']
            ),
            'line_height' => customer_message_clamp_int(
                $item['line_height'] ?? null,
                $type === 'title' ? 80 : 100,
                $type === 'title' ? 220 : 260,
                $type === 'title' ? $defaults['title_line_height'] : $defaults['body_line_height']
            ),
            'align' => in_array($type, ['title', 'body'], true)
                ? customer_message_editor_align_value(
                    $item['align'] ?? null,
                    $type === 'title' ? $defaults['title_align'] : $defaults['body_align']
                )
                : 'left',
            'bold' => in_array($type, ['title', 'body'], true)
                ? customer_message_bool_value(
                    $item['bold'] ?? null,
                    $type === 'title' ? $defaults['title_bold'] : $defaults['body_bold']
                )
                : false,
            'italic' => in_array($type, ['title', 'body'], true)
                ? customer_message_bool_value(
                    $item['italic'] ?? null,
                    $type === 'title' ? $defaults['title_italic'] : $defaults['body_italic']
                )
                : false,
            'uppercase' => in_array($type, ['title', 'body'], true)
                ? customer_message_bool_value(
                    $item['uppercase'] ?? null,
                    $type === 'title' ? $defaults['title_uppercase'] : $defaults['body_uppercase']
                )
                : false,
            'shadow' => in_array($type, ['title', 'body'], true)
                ? customer_message_editor_shadow_value(
                    $item['shadow'] ?? null,
                    $type === 'title' ? $defaults['title_shadow'] : $defaults['body_shadow']
                )
                : 'off',
        ];
    }

    return $normalized;
}

function customer_message_prepare_editor_layers(array $context = [], array $customer = [], array $tokenContext = []): array
{
    $layers = customer_message_editor_layers($context);

    if ($layers === []) {
        return [];
    }

    foreach ($layers as &$layer) {
        if (isset($layer['content']) && $layer['content'] !== '') {
            $layer['content'] = customer_message_editor_normalize_text_content(
                customer_message_apply_tokens((string) $layer['content'], $customer, $tokenContext),
                ($layer['type'] ?? '') !== 'title'
            );
        }

        if (!empty($layer['link_url'])) {
            $resolvedLink = customer_message_apply_tokens((string) $layer['link_url'], $customer, $tokenContext);
            $layer['link_url'] = customer_message_absolute_link($resolvedLink);
        }
    }
    unset($layer);

    return $layers;
}

function customer_message_editor_click_url(array $options = [], ?string $fallbackLink = null): ?string
{
    $editor = customer_message_editor_settings((array) ($options['editor'] ?? []));
    $artLinkUrl = trim((string) ($editor['image_link_url'] ?? ''));

    if ($artLinkUrl !== '') {
        return $artLinkUrl;
    }

    foreach (customer_message_editor_layers($options) as $layer) {
        if (!empty($layer['link_url'])) {
            return (string) $layer['link_url'];
        }
    }

    $fallbackLink = trim((string) $fallbackLink);

    return $fallbackLink !== '' ? $fallbackLink : null;
}

function customer_message_hero_text_layout(string $position = 'bottom-left'): array
{
    $position = customer_message_normalize_hero_position($position);
    $map = [
        'top-left' => [
            'align' => 'left',
            'vertical' => 'top',
            'wrapper_margin' => '0 auto 0 0',
            'padding' => '34px 34px 34px',
            'max_width' => '440px',
        ],
        'top-center' => [
            'align' => 'center',
            'vertical' => 'top',
            'wrapper_margin' => '0 auto',
            'padding' => '34px 34px 34px',
            'max_width' => '520px',
        ],
        'top-right' => [
            'align' => 'right',
            'vertical' => 'top',
            'wrapper_margin' => '0 0 0 auto',
            'padding' => '34px 34px 34px',
            'max_width' => '440px',
        ],
        'center-left' => [
            'align' => 'left',
            'vertical' => 'middle',
            'wrapper_margin' => '0 auto 0 0',
            'padding' => '34px',
            'max_width' => '450px',
        ],
        'center' => [
            'align' => 'center',
            'vertical' => 'middle',
            'wrapper_margin' => '0 auto',
            'padding' => '34px',
            'max_width' => '560px',
        ],
        'center-right' => [
            'align' => 'right',
            'vertical' => 'middle',
            'wrapper_margin' => '0 0 0 auto',
            'padding' => '34px',
            'max_width' => '450px',
        ],
        'bottom-center' => [
            'align' => 'center',
            'vertical' => 'bottom',
            'wrapper_margin' => '0 auto',
            'padding' => '34px 34px 38px',
            'max_width' => '560px',
        ],
        'bottom-left' => [
            'align' => 'left',
            'vertical' => 'bottom',
            'wrapper_margin' => '0 auto 0 0',
            'padding' => '34px 34px 38px',
            'max_width' => '470px',
        ],
        'bottom-right' => [
            'align' => 'right',
            'vertical' => 'bottom',
            'wrapper_margin' => '0 0 0 auto',
            'padding' => '34px 34px 38px',
            'max_width' => '470px',
        ],
    ];

    return $map[$position] ?? $map['bottom-left'];
}

function customer_message_presets(): array
{
    return [
        'manual' => [
            'label' => 'Manual',
            'description' => 'Mensagem livre para aviso, campanha ou recado rapido.',
            'title' => '',
            'message' => '',
            'link_url' => '',
            'recipient_mode' => 'all',
            'eyebrow' => 'Moda Tropical',
            'cta_label' => 'Abrir mensagem',
            'theme' => 'manual',
        ],
        'ofertao' => [
            'label' => 'Ofertao',
            'description' => 'Campanha forte para jogar o cliente direto nas promocoes.',
            'title' => 'Ofertao liberado para voce, {{primeiro_nome}}',
            'message' => "Selecionamos pecas em promocao com aquele ar de oportunidade boa demais para deixar passar.\nEntre agora, confira os destaques e aproveite enquanto durarem os tamanhos.",
            'link_url' => promotions_path(),
            'recipient_mode' => 'all',
            'eyebrow' => 'Ofertas da semana',
            'cta_label' => 'Ver promocoes',
            'theme' => 'ofertao',
        ],
        'descontos_incriveis' => [
            'label' => 'Descontos incriveis',
            'description' => 'Visual mais elegante para campanhas de desconto e promo.',
            'title' => 'Descontos incriveis chegaram por aqui',
            'message' => "Tem novidade boa te esperando na nossa area de promocoes.\nPasse la para descobrir pecas selecionadas com preco especial e aproveitar antes que acabem.",
            'link_url' => promotions_path(),
            'recipient_mode' => 'all',
            'eyebrow' => 'Desconto especial',
            'cta_label' => 'Garantir desconto',
            'theme' => 'descontos_incriveis',
        ],
        'oi_sumido' => [
            'label' => 'Oi, sumido(a)',
            'description' => 'Reengajamento para quem ficou um tempo sem comprar.',
            'title' => '{{saudacao_sumido}}',
            'message' => "A loja mudou bastante desde a sua ultima visita.\nTem novidade, promocoes e pecas escolhidas para fazer voce voltar a passear pela vitrine com calma.",
            'link_url' => promotions_path(),
            'recipient_mode' => 'inactive_45',
            'eyebrow' => 'Sentimos sua falta',
            'cta_label' => 'Voltar para a loja',
            'theme' => 'oi_sumido',
        ],
        'boas_vindas' => [
            'label' => 'Boas-vindas',
            'description' => 'Recepcao elegante para clientes que acabaram de entrar.',
            'title' => '{{bem_vindo_ou_vinda}}, {{primeiro_nome}}',
            'message' => "Sua conta ja esta pronta para descobrir novidades, acompanhar pedidos e aproveitar cupons especiais.\nAproveite para conhecer os destaques da loja e salvar suas pecas favoritas.",
            'link_url' => promotions_path(),
            'recipient_mode' => 'customer',
            'eyebrow' => 'Bem-vinda a loja',
            'cta_label' => 'Conhecer a loja',
            'theme' => 'boas_vindas',
        ],
        'estoque_novo' => [
            'label' => 'Estoque novo',
            'description' => 'Aviso automatico quando uma peca favorita volta ao estoque.',
            'title' => 'A peca que voce salvou voltou ao estoque',
            'message' => "Tem reposicao no ar e a sua peca favorita esta disponivel novamente.\nSe quiser garantir, vale correr porque os tamanhos podem sair rapido.",
            'link_url' => '',
            'recipient_mode' => 'customer',
            'eyebrow' => 'Estoque renovado',
            'cta_label' => 'Ver peca',
            'theme' => 'estoque_novo',
        ],
    ];
}

function customer_message_theme(string $kind = 'manual'): array
{
    $themes = [
        'manual' => [
            'hero' => 'linear-gradient(135deg,#1f1530 0%,#4b2b73 54%,#f59a23 100%)',
            'highlight' => '#f59a23',
            'button' => 'linear-gradient(135deg,#f59a23 0%,#ff7b52 100%)',
            'surface' => '#fff9f3',
            'text' => '#241a2d',
            'muted' => '#665b74',
        ],
        'ofertao' => [
            'hero' => 'linear-gradient(135deg,#351223 0%,#6d1732 52%,#f68f3a 100%)',
            'highlight' => '#ffcf8f',
            'button' => 'linear-gradient(180deg,#c98764 0%,#a76140 100%)',
            'surface' => '#fff6f2',
            'text' => '#231516',
            'muted' => '#4f3732',
        ],
        'descontos_incriveis' => [
            'hero' => 'linear-gradient(135deg,#12251f 0%,#1d5c4a 52%,#f7b955 100%)',
            'highlight' => '#9be59f',
            'button' => 'linear-gradient(135deg,#3ecf8e 0%,#f7b955 100%)',
            'surface' => '#f6fff8',
            'text' => '#16261c',
            'muted' => '#4e6557',
        ],
        'oi_sumido' => [
            'hero' => 'linear-gradient(135deg,#17151f 0%,#3a2952 50%,#f29b5d 100%)',
            'highlight' => '#f0c28f',
            'button' => 'linear-gradient(135deg,#8b5cf6 0%,#f29b5d 100%)',
            'surface' => '#fbf7ff',
            'text' => '#241d30',
            'muted' => '#675d76',
        ],
        'boas_vindas' => [
            'hero' => 'linear-gradient(135deg,#151b29 0%,#2e476f 52%,#f0a36d 100%)',
            'highlight' => '#ffd3a4',
            'button' => 'linear-gradient(135deg,#d97a6c 0%,#f0a36d 100%)',
            'surface' => '#fff8f4',
            'text' => '#231a24',
            'muted' => '#675864',
        ],
        'estoque_novo' => [
            'hero' => 'linear-gradient(135deg,#122019 0%,#1f5d49 52%,#ffc86c 100%)',
            'highlight' => '#bff5c8',
            'button' => 'linear-gradient(135deg,#57ca8f 0%,#ffc86c 100%)',
            'surface' => '#f7fff8',
            'text' => '#19241c',
            'muted' => '#526457',
        ],
    ];

    return $themes[$kind] ?? $themes['manual'];
}

function customer_message_tokens(array $customer = [], array $context = []): array
{
    $productUrl = trim((string) ($context['product_url'] ?? ''));
    $promotionUrl = trim((string) ($context['promotions_url'] ?? absolute_promotions_url()));
    $storeUrl = trim((string) ($context['store_url'] ?? absolute_app_url('index.php')));

    return [
        '{{nome}}' => trim((string) ($customer['nome'] ?? 'Cliente')),
        '{{primeiro_nome}}' => customer_message_first_name((string) ($customer['nome'] ?? '')),
        '{{loja}}' => customer_message_store_name(),
        '{{saudacao_sumido}}' => customer_message_missing_you_greeting($customer),
        '{{sumido_ou_sumida}}' => customer_message_gender_variant((string) ($customer['nome'] ?? ''), 'sumido', 'sumida', 'de volta'),
        '{{bem_vindo_ou_vinda}}' => customer_message_welcome_phrase($customer),
        '{{promocoes_url}}' => $promotionUrl,
        '{{loja_url}}' => $storeUrl,
        '{{produto_nome}}' => trim((string) ($context['product_name'] ?? '')),
        '{{produto_url}}' => $productUrl,
    ];
}

function customer_message_apply_tokens(string $text, array $customer = [], array $context = []): string
{
    return strtr($text, customer_message_tokens($customer, $context));
}

function customer_message_prepare_content(
    string $title,
    string $message,
    ?string $linkUrl,
    array $customer = [],
    string $kind = 'manual',
    array $context = []
): array {
    $presets = customer_message_presets();
    $preset = $presets[$kind] ?? $presets['manual'];
    $context = $context + [
        'promotions_url' => absolute_promotions_url(),
        'store_url' => absolute_app_url('index.php'),
    ];

    $resolvedTitle = trim(customer_message_apply_tokens($title, $customer, $context));
    $resolvedMessage = trim(customer_message_apply_tokens($message, $customer, $context));
    $resolvedLink = customer_message_apply_tokens((string) ($linkUrl ?? ''), $customer, $context);
    $normalizedLink = customer_message_normalize_link($resolvedLink);
    $heroImage = trim((string) ($context['hero_image_url'] ?? $context['hero_image_path'] ?? ''));
    $heroPosition = customer_message_normalize_hero_position((string) ($context['hero_text_position'] ?? 'bottom-left'));
    $editor = customer_message_editor_settings($context);
    $editorLayers = customer_message_prepare_editor_layers($context, $customer, $context);
    $layout = trim((string) ($context['email_layout'] ?? ($heroImage !== '' ? 'editor' : 'default')));
    $ctaLabel = trim((string) ($context['cta_label'] ?? $preset['cta_label'] ?? 'Abrir mensagem'));

    if ($editor['button_label'] !== '') {
        $ctaLabel = customer_message_apply_tokens($editor['button_label'], $customer, $context);
    }

    $imageHotspotUrl = trim(customer_message_apply_tokens((string) ($editor['image_link_url'] ?? ''), $customer, $context));
    $editor['image_link_url'] = customer_message_absolute_link($imageHotspotUrl);
    $scene = customer_message_scene_from_context($context + [
        'title' => $resolvedTitle,
        'message' => $resolvedMessage,
        'link_url' => $normalizedLink,
        'button_label' => $ctaLabel,
        'hero_image_path' => $heroImage,
        'email_editor' => $editor,
        'email_editor_layers' => $editorLayers,
    ]);

    return [
        'kind' => $kind,
        'title' => $resolvedTitle !== '' ? $resolvedTitle : trim((string) ($preset['title'] ?? 'Mensagem')),
        'message' => $resolvedMessage,
        'link_url' => $normalizedLink,
        'email_link_url' => customer_message_absolute_link($resolvedLink),
        'eyebrow' => trim((string) ($context['eyebrow'] ?? $preset['eyebrow'] ?? customer_message_store_name())),
        'cta_label' => $ctaLabel,
        'theme' => customer_message_theme((string) ($context['theme'] ?? $preset['theme'] ?? $kind)),
        'hero_image_path' => $heroImage,
        'hero_image_url' => customer_message_absolute_asset_url($heroImage),
        'hero_text_position' => $heroPosition,
        'layout' => $layout === 'editor' ? 'editor' : 'default',
        'editor' => $editor,
        'editor_layers' => $editorLayers,
        'scene' => $scene,
        'scene_json' => json_encode($scene, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
    ];
}

function customer_message_format_html_body(string $message, string $mutedColor, int $fontSize = 16, string $paragraphSpacing = '16px'): string
{
    $paragraphs = preg_split('/\R{2,}|\n/u', trim($message), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    if ($paragraphs === []) {
        return '';
    }

    $html = '';

    foreach ($paragraphs as $paragraph) {
        $html .= '<p style="margin:0 0 ' . e($paragraphSpacing) . ';color:' . e($mutedColor) . ';font-size:' . e((string) $fontSize) . 'px;line-height:1.75;">'
            . nl2br(e(trim($paragraph)))
            . '</p>';
    }

    return $html;
}

function customer_message_editor_font_path(bool $bold = false, bool $italic = false): ?string
{
    if ($bold && $italic) {
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-BoldOblique.ttf',
            'C:\\Windows\\Fonts\\arialbi.ttf',
        ];
    } elseif ($bold) {
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            'C:\\Windows\\Fonts\\arialbd.ttf',
        ];
    } elseif ($italic) {
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Oblique.ttf',
            'C:\\Windows\\Fonts\\ariali.ttf',
        ];
    } else {
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
        ];
    }

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function customer_message_uppercase(string $text): string
{
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($text, 'UTF-8');
    }

    return strtr(strtoupper($text), [
        'á' => 'Á',
        'à' => 'À',
        'â' => 'Â',
        'ã' => 'Ã',
        'ä' => 'Ä',
        'é' => 'É',
        'è' => 'È',
        'ê' => 'Ê',
        'ë' => 'Ë',
        'í' => 'Í',
        'ì' => 'Ì',
        'î' => 'Î',
        'ï' => 'Ï',
        'ó' => 'Ó',
        'ò' => 'Ò',
        'ô' => 'Ô',
        'õ' => 'Õ',
        'ö' => 'Ö',
        'ú' => 'Ú',
        'ù' => 'Ù',
        'û' => 'Û',
        'ü' => 'Ü',
        'ç' => 'Ç',
        'ñ' => 'Ñ',
    ]);
}

function customer_message_editor_apply_text_style(string $text, array $style = []): string
{
    $text = customer_message_editor_sanitize_raster_text($text);

    if (!empty($style['uppercase'])) {
        $text = customer_message_uppercase($text);
    }

    return $text;
}

function customer_message_editor_shadow_spec(string $level = 'off'): array
{
    return match (customer_message_editor_shadow_value($level, 'off')) {
        'soft' => ['alpha' => 96, 'offset' => 1],
        'strong' => ['alpha' => 82, 'offset' => 3],
        default => ['alpha' => 127, 'offset' => 0],
    };
}

function customer_message_editor_image_source_path(array $options = []): ?string
{
    $relativePath = trim((string) ($options['hero_image_path'] ?? ''));

    if ($relativePath !== '') {
        $absolute = BASE_PATH . '/' . ltrim($relativePath, '/');

        if (is_file($absolute)) {
            return $absolute;
        }
    }

    return null;
}

function customer_message_editor_load_gd_image(string $path)
{
    $type = @exif_imagetype($path);

    return match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG => @imagecreatefrompng($path),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
}

function customer_message_editor_hex_to_rgba(string $hex, int $alpha = 0): array
{
    $hex = ltrim(customer_message_color_value($hex, '#ffffff'), '#');

    return [
        'r' => hexdec(substr($hex, 0, 2)),
        'g' => hexdec(substr($hex, 2, 2)),
        'b' => hexdec(substr($hex, 4, 2)),
        'a' => max(0, min(127, $alpha)),
    ];
}

function customer_message_editor_color_allocate($image, string $hex, int $alpha = 0): int
{
    $rgba = customer_message_editor_hex_to_rgba($hex, $alpha);

    return imagecolorallocatealpha($image, $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a']);
}

function customer_message_editor_text_width(string $font, int $size, string $text): int
{
    $box = imagettfbbox($size, 0, $font, $text);

    if (!is_array($box)) {
        return 0;
    }

    $minX = min($box[0], $box[2], $box[4], $box[6]);
    $maxX = max($box[0], $box[2], $box[4], $box[6]);

    return (int) ($maxX - $minX);
}

function customer_message_editor_graphemes(string $text): array
{
    if ($text === '') {
        return [];
    }

    if (preg_match_all('/\X/u', $text, $matches) === 1 && !empty($matches[0])) {
        return $matches[0];
    }

    return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

function customer_message_editor_is_emoji_cluster(string $cluster): bool
{
    return preg_match('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $cluster) === 1;
}

function customer_message_editor_emoji_render_size(int $fontSize): int
{
    return max(16, (int) round($fontSize * 1.18));
}

function customer_message_editor_unicode_codepoints(string $text): array
{
    if ($text === '') {
        return [];
    }

    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $points = [];

    foreach ($chars as $char) {
        if (function_exists('mb_convert_encoding')) {
            $encoded = mb_convert_encoding($char, 'UCS-4BE', 'UTF-8');
        } else {
            $encoded = @iconv('UTF-8', 'UCS-4BE', $char);
        }

        if (!is_string($encoded) || strlen($encoded) !== 4) {
            continue;
        }

        $unpacked = unpack('N', $encoded);
        if (is_array($unpacked) && isset($unpacked[1])) {
            $points[] = strtolower(dechex((int) $unpacked[1]));
        }
    }

    return $points;
}

function customer_message_editor_emoji_cache_dir(): string
{
    return BASE_PATH . '/storage/messages/emoji-cache';
}

function customer_message_editor_emoji_asset_path(string $emoji): ?string
{
    $variants = [];
    $codepoints = customer_message_editor_unicode_codepoints($emoji);

    if ($codepoints === []) {
        return null;
    }

    $variants[] = implode('-', $codepoints);
    $variants[] = implode('-', array_values(array_filter($codepoints, static fn (string $point): bool => $point !== 'fe0f')));

    $variants = array_values(array_unique(array_filter($variants)));
    $cacheDir = customer_message_editor_emoji_cache_dir();

    if (!is_dir($cacheDir) && !mkdir($cacheDir, 0775, true) && !is_dir($cacheDir)) {
        return null;
    }

    foreach ($variants as $variant) {
        $localPath = $cacheDir . '/' . $variant . '.png';
        if (is_file($localPath)) {
            return $localPath;
        }

        $url = 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/' . rawurlencode($variant) . '.png';
        $context = stream_context_create([
            'http' => [
                'timeout' => 6,
                'user_agent' => 'ModaTropical/1.0',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $contents = @file_get_contents($url, false, $context);

        if ($contents !== false && $contents !== '') {
            @file_put_contents($localPath, $contents);
            if (is_file($localPath)) {
                return $localPath;
            }
        }
    }

    return null;
}

function customer_message_editor_text_width_rich(string $font, int $size, string $text): int
{
    $width = 0;
    $buffer = '';

    foreach (customer_message_editor_graphemes($text) as $cluster) {
        if (customer_message_editor_is_emoji_cluster($cluster)) {
            if ($buffer !== '') {
                $width += customer_message_editor_text_width($font, $size, $buffer);
                $buffer = '';
            }
            $width += customer_message_editor_emoji_render_size($size);
            continue;
        }

        $buffer .= $cluster;
    }

    if ($buffer !== '') {
        $width += customer_message_editor_text_width($font, $size, $buffer);
    }

    return $width;
}

function customer_message_editor_wrap_lines(string $text, string $font, int $size, int $maxWidth): array
{
    $paragraphs = preg_split('/\R+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    if ($paragraphs === []) {
        return [];
    }

    $lines = [];

    foreach ($paragraphs as $paragraph) {
        $tokens = preg_split('/(\s+)/u', trim($paragraph), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $line = '';

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            if (preg_match('/^\s+$/u', $token) === 1) {
                if ($line !== '') {
                    $line .= ' ';
                }
                continue;
            }

            $candidate = $line === '' ? $token : $line . $token;

            if ($line !== '' && customer_message_editor_text_width_rich($font, $size, $candidate) > $maxWidth) {
                $lines[] = rtrim($line);
                $line = $token;
            } else {
                $line = $candidate;
            }
        }

        if (trim($line) !== '') {
            $lines[] = rtrim($line);
        }

        $lines[] = '';
    }

    if ($lines !== []) {
        array_pop($lines);
    }

    return $lines;
}

function customer_message_editor_sanitize_raster_text(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
    $text = is_string($converted) && $converted !== '' ? $converted : $text;
    $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
    $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

    return trim($text);
}

function customer_message_editor_draw_wrapped_text(
    $image,
    array $lines,
    string $font,
    int $size,
    int $x,
    int $y,
    int $color,
    int $lineHeight,
    ?int $shadowColor = null,
    int $shadowOffset = 0,
    ?int $maxWidth = null,
    string $align = 'left'
): int
{
    $cursorY = $y;

    foreach ($lines as $line) {
        if ($line === '') {
            $cursorY += (int) round($lineHeight * 0.55);
            continue;
        }

        $lineWidth = customer_message_editor_text_width_rich($font, $size, $line);
        $lineStartX = $x;

        if ($maxWidth !== null && $maxWidth > 0) {
            if ($align === 'center') {
                $lineStartX = $x + (int) round(max(0, $maxWidth - $lineWidth) / 2);
            } elseif ($align === 'right') {
                $lineStartX = $x + max(0, $maxWidth - $lineWidth);
            }
        }

        $drawRichLine = static function (int $offsetX, int $offsetY, int $drawColor) use ($image, $line, $font, $size, $lineStartX, $cursorY): void {
            $cursorX = $lineStartX + $offsetX;
            $buffer = '';
            $emojiSize = customer_message_editor_emoji_render_size($size);

            $flushBuffer = static function () use (&$buffer, &$cursorX, $font, $size, $image, $cursorY, $drawColor, $offsetY): void {
                if ($buffer === '') {
                    return;
                }

                imagettftext($image, $size, 0, $cursorX, $cursorY + $offsetY, $drawColor, $font, $buffer);
                $cursorX += customer_message_editor_text_width($font, $size, $buffer);
                $buffer = '';
            };

            foreach (customer_message_editor_graphemes($line) as $cluster) {
                if (!customer_message_editor_is_emoji_cluster($cluster)) {
                    $buffer .= $cluster;
                    continue;
                }

                $flushBuffer();

                $emojiPath = customer_message_editor_emoji_asset_path($cluster);
                if ($emojiPath !== null) {
                    $emojiImage = @imagecreatefrompng($emojiPath);
                    if ($emojiImage !== false) {
                        imagealphablending($emojiImage, true);
                        imagesavealpha($emojiImage, true);
                        $top = (int) round(($cursorY + $offsetY) - $emojiSize + max(0, round($size * 0.22)));
                        imagecopyresampled(
                            $image,
                            $emojiImage,
                            $cursorX,
                            $top,
                            0,
                            0,
                            $emojiSize,
                            $emojiSize,
                            imagesx($emojiImage),
                            imagesy($emojiImage)
                        );
                        imagedestroy($emojiImage);
                        $cursorX += $emojiSize;
                        continue;
                    }
                }
            }

            $flushBuffer();
        };

        if ($shadowColor !== null && $shadowOffset > 0) {
            $drawRichLine($shadowOffset, $shadowOffset, $shadowColor);
        }

        $drawRichLine(0, 0, $color);
        $cursorY += $lineHeight;
    }

    return $cursorY;
}

function customer_message_editor_draw_rounded_rectangle($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
{
    imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
}

function customer_message_editor_render_flat_art(
    string $title,
    string $message,
    ?string $linkUrl,
    array $options = [],
    ?array &$debug = null
): ?array {
    $debug = [
        'renderer' => 'flat_art',
        'status' => 'starting',
    ];

    if (!extension_loaded('gd') || !function_exists('imagettftext')) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'gd_unavailable';
        return null;
    }

    $sourcePath = customer_message_editor_image_source_path($options);
    $debug['source_path'] = $sourcePath;

    if ($sourcePath === null) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'source_image_missing';
        return null;
    }

    $image = customer_message_editor_load_gd_image($sourcePath);

    if (!$image) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'source_image_unreadable';
        return null;
    }

    imagealphablending($image, true);
    imagesavealpha($image, true);

    $width = imagesx($image);
    $height = imagesy($image);
    $editor = customer_message_editor_settings((array) ($options['editor'] ?? []));
    $extraLayers = customer_message_editor_layers($options);
    $titleFont = customer_message_editor_font_path((bool) $editor['title_bold'], (bool) $editor['title_italic']);
    $bodyFont = customer_message_editor_font_path((bool) $editor['body_bold'], (bool) $editor['body_italic']);
    $buttonFont = customer_message_editor_font_path(true, false);
    $title = customer_message_editor_apply_text_style($title, [
        'uppercase' => (bool) $editor['title_uppercase'],
    ]);
    $message = customer_message_editor_apply_text_style($message, [
        'uppercase' => (bool) $editor['body_uppercase'],
    ]);
    $ctaLabel = customer_message_editor_sanitize_raster_text((string) ($options['cta_label'] ?? 'Abrir mensagem'));

    if ($titleFont === null || $bodyFont === null) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'font_missing';
        imagedestroy($image);
        return null;
    }

    if ($buttonFont === null) {
        $buttonFont = $titleFont;
    }

    $baseTitleWidth = (int) round($width * (((int) $editor['title_width']) / 100));
    $baseBodyWidth = (int) round($width * (((int) $editor['body_width']) / 100));
    $baseButtonWidth = (int) round($width * (((int) $editor['button_width']) / 100));
    $baseButtonHeight = (int) round($height * (((int) $editor['button_height']) / 100));
    $maxTextWidth = (int) round($width * 0.74);

    if ($editor['show_title']) {
        $titleMaxWidth = max(160, $baseTitleWidth);
        $titleLines = customer_message_editor_wrap_lines($title, $titleFont, (int) $editor['title_size'], $titleMaxWidth);
        $titleX = (int) round($width * ($editor['title_x'] / 100));
        $titleY = (int) round($height * ($editor['title_y'] / 100)) + (int) $editor['title_size'];
        $titleColor = customer_message_editor_color_allocate($image, (string) $editor['title_color'], 0);
        $titleShadowSpec = customer_message_editor_shadow_spec((string) $editor['title_shadow']);
        $titleShadow = $titleShadowSpec['offset'] > 0
            ? customer_message_editor_color_allocate($image, '#000000', (int) $titleShadowSpec['alpha'])
            : null;
        $titleLineHeight = (int) round(((int) $editor['title_size']) * (((int) $editor['title_line_height']) / 100));
        customer_message_editor_draw_wrapped_text(
            $image,
            $titleLines,
            $titleFont,
            (int) $editor['title_size'],
            $titleX,
            $titleY,
            $titleColor,
            $titleLineHeight,
            $titleShadow,
            (int) $titleShadowSpec['offset'],
            $titleMaxWidth,
            (string) $editor['title_align']
        );
    }

    if ($editor['show_body']) {
        $bodyMaxWidth = max(180, $baseBodyWidth);
        $bodyLines = customer_message_editor_wrap_lines($message, $bodyFont, (int) $editor['body_size'], max(120, $bodyMaxWidth));

        if ($bodyLines !== []) {
            $bodyX = (int) round($width * ($editor['body_x'] / 100));
            $bodyY = (int) round($height * ($editor['body_y'] / 100)) + (int) $editor['body_size'];
            $bodyLineHeight = (int) round(((int) $editor['body_size']) * (((int) $editor['body_line_height']) / 100));
            $bodyColor = customer_message_editor_color_allocate($image, (string) $editor['body_color'], 0);
            $bodyShadowSpec = customer_message_editor_shadow_spec((string) $editor['body_shadow']);
            $bodyShadow = $bodyShadowSpec['offset'] > 0
                ? customer_message_editor_color_allocate($image, '#000000', (int) $bodyShadowSpec['alpha'])
                : null;

            customer_message_editor_draw_wrapped_text(
                $image,
                $bodyLines,
                $bodyFont,
                (int) $editor['body_size'],
                $bodyX,
                $bodyY,
                $bodyColor,
                $bodyLineHeight,
                $bodyShadow,
                (int) $bodyShadowSpec['offset'],
                max(120, $bodyMaxWidth),
                (string) $editor['body_align']
            );
        }
    }

    if ($editor['show_button'] && $linkUrl !== null && trim($linkUrl) !== '') {
        $buttonLabel = $ctaLabel !== '' ? $ctaLabel : 'Abrir mensagem';
        $buttonFontSize = customer_message_clamp_int($editor['button_font_size'] ?? null, 12, 96, max(16, min(22, (int) round($width / 30))));
        $buttonTextWidth = customer_message_editor_text_width($buttonFont, $buttonFontSize, $buttonLabel);
        $buttonPaddingX = 34;
        $buttonHeight = max(54, $baseButtonHeight);
        $buttonWidth = max($buttonTextWidth + ($buttonPaddingX * 2), $baseButtonWidth);
        $buttonX = (int) round($width * ($editor['button_x'] / 100));
        $buttonY = (int) round($height * ($editor['button_y'] / 100));
        $buttonBg = customer_message_editor_color_allocate($image, '#efc96b', 0);
        $buttonText = customer_message_editor_color_allocate($image, '#4d2c0f', 0);
        $buttonShadow = customer_message_editor_color_allocate($image, '#8c5630', 88);

        customer_message_editor_draw_rounded_rectangle(
            $image,
            $buttonX,
            $buttonY,
            min($width - 12, $buttonX + $buttonWidth),
            min($height - 12, $buttonY + $buttonHeight),
            (int) round($buttonHeight / 2),
            $buttonShadow
        );

        customer_message_editor_draw_rounded_rectangle(
            $image,
            $buttonX,
            max(0, $buttonY - 4),
            min($width - 12, $buttonX + $buttonWidth),
            min($height - 16, $buttonY + $buttonHeight - 4),
            (int) round($buttonHeight / 2),
            $buttonBg
        );

        imagettftext(
            $image,
            $buttonFontSize,
            0,
            $buttonX + $buttonPaddingX,
            $buttonY + (int) round($buttonHeight / 2) + (int) round($buttonFontSize / 2) - 6,
            $buttonText,
            $buttonFont,
            $buttonLabel
        );
    }

    foreach ($extraLayers as $layer) {
        if ($layer['type'] === 'title') {
            $layerFontSize = (int) ($layer['font_size'] ?? $editor['title_size']);
            $layerWidth = max(160, (int) round($width * (((int) $layer['width']) / 100)));
            $layerText = customer_message_editor_apply_text_style((string) $layer['content'], [
                'uppercase' => !empty($layer['uppercase']),
            ]);
            $layerFont = customer_message_editor_font_path(!empty($layer['bold']), !empty($layer['italic'])) ?: $titleFont;
            $layerLines = customer_message_editor_wrap_lines($layerText, $layerFont, $layerFontSize, $layerWidth);
            $layerX = (int) round($width * (((int) $layer['x']) / 100));
            $layerY = (int) round($height * (((int) $layer['y']) / 100)) + $layerFontSize;
            $layerColor = customer_message_editor_color_allocate($image, (string) $editor['title_color'], 0);
            $layerShadowSpec = customer_message_editor_shadow_spec((string) ($layer['shadow'] ?? $editor['title_shadow']));
            $layerShadow = $layerShadowSpec['offset'] > 0
                ? customer_message_editor_color_allocate($image, '#000000', (int) $layerShadowSpec['alpha'])
                : null;
            $layerLineHeight = (int) round($layerFontSize * (((int) ($layer['line_height'] ?? $editor['title_line_height'])) / 100));
            customer_message_editor_draw_wrapped_text(
                $image,
                $layerLines,
                $layerFont,
                $layerFontSize,
                $layerX,
                $layerY,
                $layerColor,
                $layerLineHeight,
                $layerShadow,
                (int) $layerShadowSpec['offset'],
                $layerWidth,
                (string) ($layer['align'] ?? $editor['title_align'])
            );
            continue;
        }

        if ($layer['type'] === 'body') {
            $layerFontSize = (int) ($layer['font_size'] ?? $editor['body_size']);
            $layerWidth = max(180, (int) round($width * (((int) $layer['width']) / 100)));
            $layerText = customer_message_editor_apply_text_style((string) $layer['content'], [
                'uppercase' => !empty($layer['uppercase']),
            ]);
            $layerFont = customer_message_editor_font_path(!empty($layer['bold']), !empty($layer['italic'])) ?: $bodyFont;
            $bodyLines = customer_message_editor_wrap_lines($layerText, $layerFont, $layerFontSize, max(120, $layerWidth));

            if ($bodyLines === []) {
                continue;
            }

            $bodyX = (int) round($width * (((int) $layer['x']) / 100));
            $bodyY = (int) round($height * (((int) $layer['y']) / 100)) + $layerFontSize;
            $bodyLineHeight = (int) round($layerFontSize * (((int) ($layer['line_height'] ?? $editor['body_line_height'])) / 100));
            $bodyColor = customer_message_editor_color_allocate($image, (string) $editor['body_color'], 0);
            $bodyShadowSpec = customer_message_editor_shadow_spec((string) ($layer['shadow'] ?? $editor['body_shadow']));
            $bodyShadow = $bodyShadowSpec['offset'] > 0
                ? customer_message_editor_color_allocate($image, '#000000', (int) $bodyShadowSpec['alpha'])
                : null;

            customer_message_editor_draw_wrapped_text(
                $image,
                $bodyLines,
                $layerFont,
                $layerFontSize,
                $bodyX,
                $bodyY,
                $bodyColor,
                $bodyLineHeight,
                $bodyShadow,
                (int) $bodyShadowSpec['offset'],
                max(120, $layerWidth),
                (string) ($layer['align'] ?? $editor['body_align'])
            );
            continue;
        }

        if ($layer['type'] === 'button') {
            $layerButtonLabel = customer_message_editor_sanitize_raster_text((string) $layer['content']);

            if ($layerButtonLabel === '') {
                continue;
            }

            $buttonFontSize = customer_message_clamp_int(
                $layer['font_size'] ?? null,
                12,
                96,
                max(16, min(22, (int) round($width / 30)))
            );
            $buttonTextWidth = customer_message_editor_text_width($buttonFont, $buttonFontSize, $layerButtonLabel);
            $buttonPaddingX = 34;
            $buttonHeight = max(54, (int) round($height * (((int) $layer['height']) / 100)));
            $buttonWidth = max($buttonTextWidth + ($buttonPaddingX * 2), (int) round($width * (((int) $layer['width']) / 100)));
            $buttonX = (int) round($width * (((int) $layer['x']) / 100));
            $buttonY = (int) round($height * (((int) $layer['y']) / 100));
            $buttonBg = customer_message_editor_color_allocate($image, '#efc96b', 0);
            $buttonText = customer_message_editor_color_allocate($image, '#4d2c0f', 0);
            $buttonShadow = customer_message_editor_color_allocate($image, '#8c5630', 88);

            customer_message_editor_draw_rounded_rectangle(
                $image,
                $buttonX,
                $buttonY,
                min($width - 12, $buttonX + $buttonWidth),
                min($height - 12, $buttonY + $buttonHeight),
                (int) round($buttonHeight / 2),
                $buttonShadow
            );

            customer_message_editor_draw_rounded_rectangle(
                $image,
                $buttonX,
                max(0, $buttonY - 4),
                min($width - 12, $buttonX + $buttonWidth),
                min($height - 16, $buttonY + $buttonHeight - 4),
                (int) round($buttonHeight / 2),
                $buttonBg
            );

            imagettftext(
                $image,
                $buttonFontSize,
                0,
                $buttonX + $buttonPaddingX,
                $buttonY + (int) round($buttonHeight / 2) + (int) round($buttonFontSize / 2) - 6,
                $buttonText,
                $buttonFont,
                $layerButtonLabel
            );
        }
    }

    $outputDirectory = BASE_PATH . '/uploads/messages';

    if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'output_dir_unavailable';
        $debug['output_directory'] = $outputDirectory;
        imagedestroy($image);
        return null;
    }

    $outputFileName = 'rendered-email-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.png';
    $outputPath = $outputDirectory . '/' . $outputFileName;

    if (!imagepng($image, $outputPath, 6)) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'imagepng_failed';
        $debug['output_path'] = $outputPath;
        imagedestroy($image);
        return null;
    }

    imagedestroy($image);

    $debug['status'] = 'success';
    $debug['output_path'] = $outputPath;
    $debug['output_relative_path'] = 'uploads/messages/' . $outputFileName;
    $debug['output_width'] = $width;
    $debug['output_height'] = $height;

    return [
        'path' => 'uploads/messages/' . $outputFileName,
        'absolute_url' => absolute_app_url('uploads/messages/' . $outputFileName),
        'width' => $width,
        'height' => $height,
        'renderer' => 'flat_art',
    ];
}

function customer_message_scene_renderer_node_binary(): ?string
{
    $candidates = DIRECTORY_SEPARATOR === '\\'
        ? [
            'C:\\Program Files\\nodejs\\node.exe',
            'node',
        ]
        : [
            '/usr/bin/node',
            '/usr/bin/nodejs',
            'node',
        ];

    foreach ($candidates as $candidate) {
        if ($candidate === 'node') {
            return $candidate;
        }

        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function customer_message_scene_renderer_script_path(): string
{
    return BASE_PATH . '/scripts/render_message_scene.mjs';
}

function customer_message_scene_renderer_composition_script_path(): string
{
    return BASE_PATH . '/scripts/render_composicao.js';
}

function customer_message_scene_renderer_template_path(): string
{
    return BASE_PATH . '/templates/message-renderer.html';
}

function customer_message_scene_renderer_temp_dir(): string
{
    $candidates = [
        BASE_PATH . '/storage/messages/render-runtime',
        BASE_PATH . '/uploads/messages/.render-runtime',
        rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'modatropical-render-runtime',
    ];

    foreach ($candidates as $candidate) {
        if (customer_message_scene_renderer_prepare_dir($candidate)) {
            return $candidate;
        }
    }

    return $candidates[0];
}

function customer_message_scene_renderer_prepare_dir(string $path): bool
{
    $path = trim($path);

    if ($path === '') {
        return false;
    }

    if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
        return false;
    }

    if (!is_writable($path)) {
        @chmod($path, 0775);
    }

    return is_dir($path) && is_writable($path);
}

function customer_message_scene_renderer_debug_clip(string $value, int $limit = 2200): string
{
    $value = trim($value);

    if ($value === '' || strlen($value) <= $limit) {
        return $value;
    }

    return substr($value, 0, $limit) . '...[truncated]';
}

function customer_message_scene_renderer_make_absolute_asset(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (preg_match('~^(?:https?|file)://~i', $value) === 1) {
        return $value;
    }

    return absolute_app_url(ltrim($value, '/'));
}

function customer_message_scene_renderer_relative_path(string $absolutePath): ?string
{
    $absolutePath = str_replace('\\', '/', trim($absolutePath));
    $basePath = str_replace('\\', '/', rtrim(BASE_PATH, '/'));

    if ($absolutePath === '' || $basePath === '' || !str_starts_with($absolutePath, $basePath . '/')) {
        return null;
    }

    return ltrim(substr($absolutePath, strlen($basePath)), '/');
}

function customer_message_scene_renderer_local_asset_path(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (str_starts_with(strtolower($value), 'file://')) {
        $value = preg_replace('~^file://~i', '', $value) ?? '';
    }

    if (preg_match('~^https?://~i', $value) === 1) {
        $appBase = rtrim(absolute_app_url(), '/');

        if ($appBase === '' || !str_starts_with($value, $appBase . '/')) {
            return '';
        }

        $value = substr($value, strlen($appBase) + 1);
    }

    $isAbsoluteFilesystemPath = DIRECTORY_SEPARATOR === '\\'
        ? preg_match('~^[A-Za-z]:[\\\\/]~', $value) === 1
        : str_starts_with($value, '/');

    if ($isAbsoluteFilesystemPath) {
        if (!is_file($value)) {
            return '';
        }

        return customer_message_scene_renderer_relative_path($value) ?? $value;
    }

    $relativePath = ltrim(str_replace('\\', '/', $value), '/');

    if ($relativePath === '') {
        return '';
    }

    return is_file(BASE_PATH . '/' . $relativePath) ? $relativePath : '';
}

function customer_message_scene_render_image_dimensions(string $path): array
{
    if (!is_file($path)) {
        return ['width' => 0, 'height' => 0];
    }

    $size = @getimagesize($path);

    if (!is_array($size)) {
        return ['width' => 0, 'height' => 0];
    }

    return [
        'width' => (int) ($size[0] ?? 0),
        'height' => (int) ($size[1] ?? 0),
    ];
}

function customer_message_scene_renderer_exec(string $command, array $env = []): array
{
    if (!function_exists('proc_open')) {
        return [
            'exit_code' => 127,
            'stdout' => '',
            'stderr' => 'proc_open indisponivel',
        ];
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $environment = null;
    if ($env !== []) {
        $environment = array_merge($_ENV, $_SERVER, $env);
    }

    $process = @proc_open($command, $descriptors, $pipes, BASE_PATH, $environment);

    if (!is_resource($process)) {
        return [
            'exit_code' => 127,
            'stdout' => '',
            'stderr' => 'falha ao iniciar processo de render',
        ];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exit_code' => (int) $exitCode,
        'stdout' => is_string($stdout) ? trim($stdout) : '',
        'stderr' => is_string($stderr) ? trim($stderr) : '',
    ];
}

function customer_message_scene_render_composition(
    array $scene,
    array $tokenValues = [],
    array $options = [],
    ?array &$debug = null
): ?array
{
    $debug = [
        'renderer' => 'composition',
        'status' => 'starting',
    ];
    $nodeBinary = customer_message_scene_renderer_node_binary();
    $scriptPath = customer_message_scene_renderer_composition_script_path();
    $debug['node_binary'] = $nodeBinary;
    $debug['script_path'] = $scriptPath;

    if ($nodeBinary === null || !is_file($scriptPath)) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'node_or_script_missing';
        return null;
    }

    $scene = customer_message_scene_normalize($scene);
    $backgroundImage = customer_message_scene_renderer_local_asset_path(
        (string) ($scene['canvas']['backgroundImage'] ?? '')
    );

    if ($backgroundImage === '') {
        $backgroundImage = customer_message_scene_renderer_local_asset_path(
            (string) ($options['hero_image_path'] ?? '')
        );
    }

    $debug['background_image'] = $backgroundImage;
    $debug['scene_layer_count'] = isset($scene['layers']) && is_array($scene['layers'])
        ? count($scene['layers'])
        : 0;

    if ($backgroundImage === '') {
        $debug['status'] = 'failed';
        $debug['reason'] = 'background_missing';
        return null;
    }

    $scene['canvas']['backgroundImage'] = $backgroundImage;

    $tempDir = customer_message_scene_renderer_temp_dir();
    $outputDirectory = BASE_PATH . '/uploads/messages';
    $debug['temp_dir'] = $tempDir;
    $debug['output_directory'] = $outputDirectory;

    if (!customer_message_scene_renderer_prepare_dir($tempDir)) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'temp_dir_unavailable';
        return null;
    }

    if (!customer_message_scene_renderer_prepare_dir($outputDirectory)) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'output_dir_unavailable';
        return null;
    }

    $renderId = date('YmdHis') . '-' . bin2hex(random_bytes(4));
    $scenePath = $tempDir . '/scene-composition-' . $renderId . '.json';
    $tokenPath = $tempDir . '/tokens-composition-' . $renderId . '.json';
    $manifestPath = $tempDir . '/manifest-composition-' . $renderId . '.json';
    $outputTempPath = $tempDir . '/render-composition-' . $renderId . '.jpg';
    $outputFileName = 'rendered-email-' . $renderId . '.jpg';
    $outputPath = $outputDirectory . '/' . $outputFileName;

    $cleanup = static function () use ($scenePath, $tokenPath, $manifestPath, $outputTempPath): void {
        foreach ([$scenePath, $tokenPath, $manifestPath, $outputTempPath] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    };

    try {
        $normalizedTokenValues = [];
        foreach ($tokenValues as $key => $value) {
            if (!is_scalar($key) || is_array($value) || is_object($value)) {
                continue;
            }

            $normalizedTokenValues[(string) $key] = (string) $value;
        }

        $sceneJson = json_encode($scene, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tokenJson = json_encode($normalizedTokenValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($sceneJson) || !is_string($tokenJson)) {
            $debug['status'] = 'failed';
            $debug['reason'] = 'json_encode_failed';
            return null;
        }

        if (
            @file_put_contents($scenePath, $sceneJson) === false
            || @file_put_contents($tokenPath, $tokenJson) === false
        ) {
            $debug['status'] = 'failed';
            $debug['reason'] = 'temp_file_write_failed';
            $debug['scene_path'] = $scenePath;
            $debug['token_path'] = $tokenPath;
            $debug['temp_dir_writable'] = is_writable($tempDir);
            return null;
        }

        $command = escapeshellarg($nodeBinary)
            . ' ' . escapeshellarg($scriptPath)
            . ' --scene ' . escapeshellarg($scenePath)
            . ' --output ' . escapeshellarg($outputTempPath)
            . ' --manifest ' . escapeshellarg($manifestPath)
            . ' --tokens-file ' . escapeshellarg($tokenPath)
            . ' --render-types text'
            . ' --text-filter all'
            . ' --format jpeg'
            . ' --quality 86';

        $debug['command'] = $command;

        $execution = customer_message_scene_renderer_exec($command);
        $debug['execution'] = [
            'exit_code' => (int) ($execution['exit_code'] ?? 0),
            'stdout' => customer_message_scene_renderer_debug_clip((string) ($execution['stdout'] ?? '')),
            'stderr' => customer_message_scene_renderer_debug_clip((string) ($execution['stderr'] ?? '')),
        ];

        if (($execution['exit_code'] ?? 1) !== 0 || !is_file($outputTempPath)) {
            $debug['status'] = 'failed';
            $debug['reason'] = !is_file($outputTempPath) ? 'renderer_output_missing' : 'renderer_exec_failed';
            error_log('customer_message_scene_render_composition failed: ' . ($execution['stderr'] ?: $execution['stdout']));
            return null;
        }

        $renderResult = null;
        $stdout = trim((string) ($execution['stdout'] ?? ''));
        if ($stdout !== '') {
            $decoded = json_decode($stdout, true);
            if (is_array($decoded)) {
                $renderResult = $decoded;
            }
        }

        if (!@rename($outputTempPath, $outputPath)) {
            if (!@copy($outputTempPath, $outputPath)) {
                $debug['status'] = 'failed';
                $debug['reason'] = 'move_output_failed';
                return null;
            }
            @unlink($outputTempPath);
        }

        $imageDimensions = customer_message_scene_render_image_dimensions($outputPath);
        $hotspots = [];

        if (is_array($renderResult['hotspots'] ?? null)) {
            $hotspots = $renderResult['hotspots'];
        } elseif (is_file($manifestPath)) {
            $manifestDecoded = json_decode((string) @file_get_contents($manifestPath), true);
            if (is_array($manifestDecoded['hotspots'] ?? null)) {
                $hotspots = $manifestDecoded['hotspots'];
            }
        }

        $normalizedHotspots = [];
        foreach ($hotspots as $hotspot) {
            if (!is_array($hotspot)) {
                continue;
            }

            $href = customer_message_absolute_link((string) ($hotspot['hrefRaw'] ?? ''));
            $width = max(0, (int) round((float) ($hotspot['width'] ?? 0)));
            $height = max(0, (int) round((float) ($hotspot['height'] ?? 0)));

            if ($href === null || $width <= 0 || $height <= 0) {
                continue;
            }

            $normalizedHotspots[] = [
                'id' => trim((string) ($hotspot['id'] ?? '')),
                'hrefRaw' => $href,
                'left' => max(0, (int) round((float) ($hotspot['left'] ?? 0))),
                'top' => max(0, (int) round((float) ($hotspot['top'] ?? 0))),
                'width' => $width,
                'height' => $height,
            ];
        }

        $width = max(
            0,
            (int) ($renderResult['outputSize']['width'] ?? 0),
            (int) ($imageDimensions['width'] ?? 0)
        );
        $height = max(
            0,
            (int) ($renderResult['outputSize']['height'] ?? 0),
            (int) ($imageDimensions['height'] ?? 0)
        );

        $debug['status'] = 'success';
        $debug['output_path'] = $outputPath;
        $debug['output_relative_path'] = 'uploads/messages/' . $outputFileName;
        $debug['output_width'] = $width;
        $debug['output_height'] = $height;
        $debug['hotspot_count'] = count($normalizedHotspots);
        $debug['layer_metrics'] = is_array($renderResult['layerMetrics'] ?? null)
            ? array_values((array) $renderResult['layerMetrics'])
            : [];

        return [
            'path' => 'uploads/messages/' . $outputFileName,
            'absolute_url' => absolute_app_url('uploads/messages/' . $outputFileName),
            'width' => $width,
            'height' => $height,
            'hotspots' => $normalizedHotspots,
            'renderer' => 'composition',
        ];
    } finally {
        $cleanup();
    }
}

function customer_message_scene_render_browser(
    array $scene,
    array $tokenValues = [],
    ?array &$debug = null
): ?array
{
    $debug = [
        'renderer' => 'browser',
        'status' => 'starting',
    ];
    $nodeBinary = customer_message_scene_renderer_node_binary();
    $scriptPath = customer_message_scene_renderer_script_path();
    $templatePath = customer_message_scene_renderer_template_path();
    $debug['node_binary'] = $nodeBinary;
    $debug['script_path'] = $scriptPath;
    $debug['template_path'] = $templatePath;

    if ($nodeBinary === null || !is_file($scriptPath) || !is_file($templatePath)) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'node_script_or_template_missing';
        return null;
    }

    $scene = customer_message_scene_normalize($scene);
    $scene['canvas']['backgroundImage'] = customer_message_scene_renderer_make_absolute_asset(
        (string) ($scene['canvas']['backgroundImage'] ?? '')
    );

    $tempDir = customer_message_scene_renderer_temp_dir();
    $outputDirectory = BASE_PATH . '/uploads/messages';
    $debug['temp_dir'] = $tempDir;
    $debug['output_directory'] = $outputDirectory;

    if (!customer_message_scene_renderer_prepare_dir($tempDir)) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'temp_dir_unavailable';
        return null;
    }

    if (!customer_message_scene_renderer_prepare_dir($outputDirectory)) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'output_dir_unavailable';
        return null;
    }

    $renderId = date('YmdHis') . '-' . bin2hex(random_bytes(4));
    $scenePath = $tempDir . '/scene-' . $renderId . '.json';
    $tokenPath = $tempDir . '/tokens-' . $renderId . '.json';
    $outputTempPath = $tempDir . '/render-' . $renderId . '.png';
    $outputFileName = 'rendered-email-' . $renderId . '.png';
    $outputPath = $outputDirectory . '/' . $outputFileName;

    $cleanup = static function () use ($scenePath, $tokenPath, $outputTempPath): void {
        foreach ([$scenePath, $tokenPath, $outputTempPath] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    };

    try {
        $sceneJson = json_encode($scene, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tokenJson = json_encode($tokenValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($sceneJson) || !is_string($tokenJson)) {
            $debug['status'] = 'failed';
            $debug['reason'] = 'json_encode_failed';
            return null;
        }

        if (@file_put_contents($scenePath, $sceneJson) === false || @file_put_contents($tokenPath, $tokenJson) === false) {
            $debug['status'] = 'failed';
            $debug['reason'] = 'temp_file_write_failed';
            $debug['scene_path'] = $scenePath;
            $debug['token_path'] = $tokenPath;
            $debug['temp_dir_writable'] = is_writable($tempDir);
            return null;
        }

        $command = escapeshellarg($nodeBinary)
            . ' ' . escapeshellarg($scriptPath)
            . ' --input ' . escapeshellarg($scenePath)
            . ' --output ' . escapeshellarg($outputTempPath)
            . ' --tokens ' . escapeshellarg($tokenPath)
            . ' --template ' . escapeshellarg($templatePath);

        $debug['command'] = $command;

        $execution = customer_message_scene_renderer_exec($command);
        $debug['execution'] = [
            'exit_code' => (int) ($execution['exit_code'] ?? 0),
            'stdout' => customer_message_scene_renderer_debug_clip((string) ($execution['stdout'] ?? '')),
            'stderr' => customer_message_scene_renderer_debug_clip((string) ($execution['stderr'] ?? '')),
        ];

        if (($execution['exit_code'] ?? 1) !== 0 || !is_file($outputTempPath)) {
            $debug['status'] = 'failed';
            $debug['reason'] = !is_file($outputTempPath) ? 'renderer_output_missing' : 'renderer_exec_failed';
            error_log('customer_message_scene_render_browser failed: ' . ($execution['stderr'] ?: $execution['stdout']));
            return null;
        }

        if (!@rename($outputTempPath, $outputPath)) {
            if (!@copy($outputTempPath, $outputPath)) {
                $debug['status'] = 'failed';
                $debug['reason'] = 'move_output_failed';
                return null;
            }
            @unlink($outputTempPath);
        }

        $debug['status'] = 'success';
        $debug['output_path'] = $outputPath;
        $debug['output_relative_path'] = 'uploads/messages/' . $outputFileName;
        $dimensions = customer_message_scene_render_image_dimensions($outputPath);
        $debug['output_width'] = (int) ($dimensions['width'] ?? 0);
        $debug['output_height'] = (int) ($dimensions['height'] ?? 0);

        return [
            'path' => 'uploads/messages/' . $outputFileName,
            'absolute_url' => absolute_app_url('uploads/messages/' . $outputFileName),
            'width' => (int) ($dimensions['width'] ?? 0),
            'height' => (int) ($dimensions['height'] ?? 0),
            'hotspots' => [],
            'renderer' => 'browser',
        ];
    } finally {
        $cleanup();
    }
}

function customer_message_editor_render_art_hotspots(array $hotspots, int $artWidth, int $artHeight): string
{
    if ($artWidth <= 0 || $artHeight <= 0 || $hotspots === []) {
        return '';
    }

    $html = '';

    foreach ($hotspots as $hotspot) {
        if (!is_array($hotspot)) {
            continue;
        }

        $href = customer_message_absolute_link((string) ($hotspot['hrefRaw'] ?? ''));
        $width = max(0, (int) round((float) ($hotspot['width'] ?? 0)));
        $height = max(0, (int) round((float) ($hotspot['height'] ?? 0)));

        if ($href === null || $width <= 0 || $height <= 0) {
            continue;
        }

        $leftPercent = max(0, min(100, (((float) ($hotspot['left'] ?? 0)) / $artWidth) * 100));
        $topPercent = max(0, min(100, (((float) ($hotspot['top'] ?? 0)) / $artHeight) * 100));
        $widthPercent = max(0, min(100, ($width / $artWidth) * 100));
        $heightPercent = max(0, min(100, ($height / $artHeight) * 100));

        if ($widthPercent <= 0 || $heightPercent <= 0) {
            continue;
        }

        $html .= '<a href="' . e($href) . '"'
            . ' style="position:absolute;display:block;left:' . e(number_format($leftPercent, 4, '.', '')) . '%;'
            . 'top:' . e(number_format($topPercent, 4, '.', '')) . '%;'
            . 'width:' . e(number_format($widthPercent, 4, '.', '')) . '%;'
            . 'height:' . e(number_format($heightPercent, 4, '.', '')) . '%;'
            . 'z-index:3;background:rgba(255,255,255,0.001);font-size:0;line-height:0;text-decoration:none;">&nbsp;</a>';
    }

    return $html;
}

function customer_message_editor_normalize_art_hotspots(array $hotspots, int $artWidth, int $artHeight): array
{
    if ($artWidth <= 0 || $artHeight <= 0) {
        return [];
    }

    $normalized = [];

    foreach ($hotspots as $hotspot) {
        if (!is_array($hotspot)) {
            continue;
        }

        $href = customer_message_absolute_link((string) ($hotspot['hrefRaw'] ?? ''));
        $left = max(0, (int) round((float) ($hotspot['left'] ?? 0)));
        $top = max(0, (int) round((float) ($hotspot['top'] ?? 0)));
        $width = max(0, (int) round((float) ($hotspot['width'] ?? 0)));
        $height = max(0, (int) round((float) ($hotspot['height'] ?? 0)));

        if ($href === null || $width <= 0 || $height <= 0) {
            continue;
        }

        if ($left >= $artWidth || $top >= $artHeight) {
            continue;
        }

        $right = min($artWidth, $left + $width);
        $bottom = min($artHeight, $top + $height);
        $clampedWidth = max(0, $right - $left);
        $clampedHeight = max(0, $bottom - $top);

        if ($clampedWidth <= 0 || $clampedHeight <= 0) {
            continue;
        }

        $normalized[] = [
            'href' => $href,
            'left' => $left,
            'top' => $top,
            'width' => $clampedWidth,
            'height' => $clampedHeight,
            'right' => $right,
            'bottom' => $bottom,
        ];
    }

    usort(
        $normalized,
        static function (array $a, array $b): int {
            return [$a['top'], $a['left'], $a['bottom'], $a['right'], $a['href']]
                <=> [$b['top'], $b['left'], $b['bottom'], $b['right'], $b['href']];
        }
    );

    return $normalized;
}

function customer_message_editor_art_slice_cache_dir(): ?string
{
    $path = BASE_PATH . '/uploads/messages/render-cache/hotspot-slices';

    return customer_message_scene_renderer_prepare_dir($path) ? $path : null;
}

function customer_message_editor_art_slice_format(string $sourcePath): string
{
    return match (@exif_imagetype($sourcePath)) {
        IMAGETYPE_JPEG => 'jpg',
        default => 'png',
    };
}

function customer_message_editor_write_art_slice($image, string $outputPath, string $format): bool
{
    return match ($format) {
        'jpg' => @imagejpeg($image, $outputPath, 86),
        default => @imagepng($image, $outputPath, 6),
    };
}

function customer_message_editor_render_art_hotspot_slices(
    string $sourcePath,
    string $altText,
    array $hotspots,
    int $artWidth,
    int $artHeight,
    ?array &$debug = null
): ?string
{
    $debug = [
        'status' => 'starting',
        'source_path' => $sourcePath,
    ];

    if (!is_file($sourcePath) || $artWidth <= 0 || $artHeight <= 0) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'source_missing_or_invalid_dimensions';
        return null;
    }

    $normalizedHotspots = customer_message_editor_normalize_art_hotspots($hotspots, $artWidth, $artHeight);
    $debug['hotspot_count'] = count($normalizedHotspots);

    if ($normalizedHotspots === []) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'no_valid_hotspots';
        return null;
    }

    $xBreaks = [0, $artWidth];
    $yBreaks = [0, $artHeight];

    foreach ($normalizedHotspots as $hotspot) {
        $xBreaks[] = $hotspot['left'];
        $xBreaks[] = $hotspot['right'];
        $yBreaks[] = $hotspot['top'];
        $yBreaks[] = $hotspot['bottom'];
    }

    $xBreaks = array_values(array_unique(array_map('intval', $xBreaks)));
    $yBreaks = array_values(array_unique(array_map('intval', $yBreaks)));
    sort($xBreaks, SORT_NUMERIC);
    sort($yBreaks, SORT_NUMERIC);

    $columnCount = max(0, count($xBreaks) - 1);
    $rowCount = max(0, count($yBreaks) - 1);
    $debug['grid_columns'] = $columnCount;
    $debug['grid_rows'] = $rowCount;

    if ($columnCount <= 0 || $rowCount <= 0 || ($columnCount * $rowCount) > 64) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'grid_invalid_or_too_dense';
        return null;
    }

    $sourceImage = customer_message_editor_load_gd_image($sourcePath);
    if (!$sourceImage) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'gd_load_failed';
        return null;
    }

    $cacheDirectory = customer_message_editor_art_slice_cache_dir();
    if ($cacheDirectory === null) {
        imagedestroy($sourceImage);
        $debug['status'] = 'failed';
        $debug['reason'] = 'cache_dir_unavailable';
        return null;
    }

    $format = customer_message_editor_art_slice_format($sourcePath);
    $debug['slice_format'] = $format;
    $sourceSignature = json_encode(
        [
            'source' => customer_message_scene_renderer_relative_path($sourcePath) ?? $sourcePath,
            'mtime' => @filemtime($sourcePath) ?: 0,
            'size' => @filesize($sourcePath) ?: 0,
            'hotspots' => $normalizedHotspots,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    $baseHash = sha1(is_string($sourceSignature) ? $sourceSignature : ($sourcePath . '|' . $artWidth . 'x' . $artHeight));

    $rows = [];
    $firstAltPending = trim($altText) !== '';
    $sliceCount = 0;

    for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex += 1) {
        $top = (int) $yBreaks[$rowIndex];
        $bottom = (int) $yBreaks[$rowIndex + 1];
        $height = max(0, $bottom - $top);

        if ($height <= 0) {
            continue;
        }

        $segments = [];

        for ($columnIndex = 0; $columnIndex < $columnCount; $columnIndex += 1) {
            $left = (int) $xBreaks[$columnIndex];
            $right = (int) $xBreaks[$columnIndex + 1];
            $width = max(0, $right - $left);

            if ($width <= 0) {
                continue;
            }

            $href = null;
            foreach ($normalizedHotspots as $hotspot) {
                if (
                    $left >= $hotspot['left']
                    && $right <= $hotspot['right']
                    && $top >= $hotspot['top']
                    && $bottom <= $hotspot['bottom']
                ) {
                    $href = $hotspot['href'];
                    break;
                }
            }

            $lastSegmentIndex = count($segments) - 1;
            if ($lastSegmentIndex >= 0) {
                $lastSegment = $segments[$lastSegmentIndex];
                if (($lastSegment['href'] ?? null) === $href && (int) ($lastSegment['right'] ?? -1) === $left) {
                    $segments[$lastSegmentIndex]['right'] = $right;
                    $segments[$lastSegmentIndex]['width'] = max(0, $right - (int) $segments[$lastSegmentIndex]['left']);
                    continue;
                }
            }

            $segments[] = [
                'href' => $href,
                'left' => $left,
                'right' => $right,
                'width' => $width,
            ];
        }

        $cells = [];

        foreach ($segments as $segment) {
            $left = (int) ($segment['left'] ?? 0);
            $width = max(0, (int) ($segment['width'] ?? 0));
            $right = $left + $width;
            $href = isset($segment['href']) && is_string($segment['href']) ? $segment['href'] : null;

            if ($width <= 0) {
                continue;
            }

            $sliceHash = sha1($baseHash . '|' . $left . '|' . $top . '|' . $width . '|' . $height . '|' . $format);
            $sliceAbsolutePath = $cacheDirectory . '/' . $sliceHash . '.' . $format;

            if (!is_file($sliceAbsolutePath)) {
                $sliceImage = imagecreatetruecolor($width, $height);
                if (!$sliceImage) {
                    imagedestroy($sourceImage);
                    $debug['status'] = 'failed';
                    $debug['reason'] = 'slice_create_failed';
                    return null;
                }

                if ($format !== 'jpg') {
                    imagealphablending($sliceImage, false);
                    imagesavealpha($sliceImage, true);
                    $transparent = imagecolorallocatealpha($sliceImage, 0, 0, 0, 127);
                    imagefill($sliceImage, 0, 0, $transparent);
                }

                if (!imagecopy($sliceImage, $sourceImage, 0, 0, $left, $top, $width, $height)) {
                    imagedestroy($sliceImage);
                    imagedestroy($sourceImage);
                    $debug['status'] = 'failed';
                    $debug['reason'] = 'slice_copy_failed';
                    return null;
                }

                if (!customer_message_editor_write_art_slice($sliceImage, $sliceAbsolutePath, $format)) {
                    imagedestroy($sliceImage);
                    imagedestroy($sourceImage);
                    $debug['status'] = 'failed';
                    $debug['reason'] = 'slice_write_failed';
                    $debug['slice_path'] = $sliceAbsolutePath;
                    return null;
                }

                imagedestroy($sliceImage);
            }

            $relativeSlicePath = customer_message_scene_renderer_relative_path($sliceAbsolutePath);
            if ($relativeSlicePath === null) {
                imagedestroy($sourceImage);
                $debug['status'] = 'failed';
                $debug['reason'] = 'slice_relative_path_failed';
                return null;
            }

            $cells[] = [
                'href' => $href,
                'src' => absolute_app_url($relativeSlicePath),
                'width' => $width,
                'height' => $height,
                'width_percent' => ($width / $artWidth) * 100,
                'alt' => $firstAltPending ? trim($altText) : '',
            ];

            $firstAltPending = false;
            $sliceCount += 1;
        }

        if ($cells !== []) {
            $rows[] = $cells;
        }
    }

    imagedestroy($sourceImage);

    if ($rows === []) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'slice_rows_empty';
        return null;
    }

    $debug['slice_count'] = $sliceCount;
    $debug['status'] = 'success';

    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;border-spacing:0;table-layout:fixed;margin:0 auto;">';

    foreach ($rows as $cells) {
        $html .= '<tr>';

        foreach ($cells as $cell) {
            $imageHtml = '<img src="' . e($cell['src']) . '" alt="' . e($cell['alt']) . '" width="' . e((string) $cell['width']) . '" height="' . e((string) $cell['height']) . '" style="display:block;width:100%;height:auto;border:0;margin:0;padding:0;">';

            if (is_string($cell['href']) && $cell['href'] !== '') {
                $imageHtml = '<a href="' . e($cell['href']) . '" style="display:block;text-decoration:none;line-height:0;font-size:0;">' . $imageHtml . '</a>';
            }

            $html .= '<td valign="top" style="padding:0;margin:0;font-size:0;line-height:0;vertical-align:top;width:' . e(number_format((float) $cell['width_percent'], 4, '.', '')) . '%;">'
                . $imageHtml
                . '</td>';
        }

        $html .= '</tr>';
    }

    $html .= '</table>';

    return $html;
}

function customer_message_editor_email_button_layer(array $scene, array $editor, ?string $linkUrl, string $ctaLabel): ?array
{
    $scene = customer_message_scene_normalize($scene);
    $layersByRole = function_exists('customer_message_scene_layers_by_role')
        ? customer_message_scene_layers_by_role($scene)
        : [];
    $buttonLayer = is_array($layersByRole['button'] ?? null) ? $layersByRole['button'] : null;

    if ($buttonLayer === null) {
        foreach ((array) ($scene['layers'] ?? []) as $layer) {
            if (!is_array($layer) || ($layer['visible'] ?? true) === false) {
                continue;
            }

            if ((string) ($layer['type'] ?? '') !== 'button') {
                continue;
            }

            $buttonLayer = $layer;
            break;
        }
    }

    $resolvedHref = customer_message_absolute_link((string) ($buttonLayer['hrefRaw'] ?? ''));
    if ($resolvedHref === null) {
        $resolvedHref = customer_message_absolute_link((string) ($linkUrl ?? ''));
    }

    if ($buttonLayer !== null) {
        $buttonLayer['hrefRaw'] = $resolvedHref ?? '';
        $buttonLayer['textRaw'] = trim((string) ($buttonLayer['textRaw'] ?? '')) !== ''
            ? trim((string) ($buttonLayer['textRaw'] ?? ''))
            : (trim((string) ($editor['button_label'] ?? '')) !== '' ? trim((string) ($editor['button_label'] ?? '')) : trim($ctaLabel));
        $buttonLayer['fontSize'] = customer_message_clamp_int(
            $buttonLayer['fontSize'] ?? null,
            12,
            96,
            customer_message_clamp_int($editor['button_font_size'] ?? null, 12, 96, 24)
        );

        if (trim((string) ($buttonLayer['hrefRaw'] ?? '')) === '') {
            return null;
        }

        return $buttonLayer;
    }

    if (empty($editor['show_button']) || $resolvedHref === null || $resolvedHref === '') {
        return null;
    }

    $canvasWidth = max(1, (int) ($scene['canvas']['width'] ?? 1080));
    $canvasHeight = max(1, (int) ($scene['canvas']['height'] ?? 1620));

    return [
        'id' => 'legacy_editor_button',
        'type' => 'button',
        'role' => 'button',
        'visible' => true,
        'textRaw' => trim((string) ($editor['button_label'] ?? '')) !== '' ? trim((string) ($editor['button_label'] ?? '')) : trim($ctaLabel),
        'hrefRaw' => $resolvedHref,
        'x' => (int) round($canvasWidth * (((int) ($editor['button_x'] ?? 24)) / 100)),
        'y' => (int) round($canvasHeight * (((int) ($editor['button_y'] ?? 82)) / 100)),
        'width' => (int) round($canvasWidth * (((int) ($editor['button_width'] ?? 26)) / 100)),
        'height' => (int) round($canvasHeight * (((int) ($editor['button_height'] ?? 11)) / 100)),
        'fontSize' => customer_message_clamp_int($editor['button_font_size'] ?? null, 12, 96, 24),
    ];
}

function customer_message_editor_email_layout_cache_dir(): ?string
{
    $path = BASE_PATH . '/uploads/messages/render-cache/email-button-layout';

    return customer_message_scene_renderer_prepare_dir($path) ? $path : null;
}

function customer_message_editor_crop_art_region(
    string $sourcePath,
    int $left,
    int $top,
    int $width,
    int $height,
    ?array &$debug = null
): ?array {
    $debug = [
        'status' => 'starting',
        'source_path' => $sourcePath,
        'left' => $left,
        'top' => $top,
        'width' => $width,
        'height' => $height,
    ];

    if (!is_file($sourcePath) || $width <= 0 || $height <= 0) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'source_missing_or_invalid_region';
        return null;
    }

    $cacheDirectory = customer_message_editor_email_layout_cache_dir();
    if ($cacheDirectory === null) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'cache_dir_unavailable';
        return null;
    }

    $format = customer_message_editor_art_slice_format($sourcePath);
    $signature = json_encode(
        [
            'source' => customer_message_scene_renderer_relative_path($sourcePath) ?? $sourcePath,
            'mtime' => @filemtime($sourcePath) ?: 0,
            'size' => @filesize($sourcePath) ?: 0,
            'rect' => [$left, $top, $width, $height],
            'format' => $format,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    $hash = sha1(is_string($signature) ? $signature : ($sourcePath . '|' . $left . '|' . $top . '|' . $width . '|' . $height));
    $sliceAbsolutePath = $cacheDirectory . '/' . $hash . '.' . $format;

    if (!is_file($sliceAbsolutePath)) {
        $sourceImage = customer_message_editor_load_gd_image($sourcePath);
        if (!$sourceImage) {
            $debug['status'] = 'failed';
            $debug['reason'] = 'gd_load_failed';
            return null;
        }

        $sliceImage = imagecreatetruecolor($width, $height);
        if (!$sliceImage) {
            imagedestroy($sourceImage);
            $debug['status'] = 'failed';
            $debug['reason'] = 'slice_create_failed';
            return null;
        }

        if ($format !== 'jpg') {
            imagealphablending($sliceImage, false);
            imagesavealpha($sliceImage, true);
            $transparent = imagecolorallocatealpha($sliceImage, 0, 0, 0, 127);
            imagefill($sliceImage, 0, 0, $transparent);
        }

        if (!imagecopy($sliceImage, $sourceImage, 0, 0, $left, $top, $width, $height)) {
            imagedestroy($sliceImage);
            imagedestroy($sourceImage);
            $debug['status'] = 'failed';
            $debug['reason'] = 'slice_copy_failed';
            return null;
        }

        if (!customer_message_editor_write_art_slice($sliceImage, $sliceAbsolutePath, $format)) {
            imagedestroy($sliceImage);
            imagedestroy($sourceImage);
            $debug['status'] = 'failed';
            $debug['reason'] = 'slice_write_failed';
            return null;
        }

        imagedestroy($sliceImage);
        imagedestroy($sourceImage);
    }

    $relativeSlicePath = customer_message_scene_renderer_relative_path($sliceAbsolutePath);
    if ($relativeSlicePath === null) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'slice_relative_path_failed';
        return null;
    }

    $debug['status'] = 'success';
    $debug['relative_path'] = $relativeSlicePath;

    return [
        'relative_path' => $relativeSlicePath,
        'absolute_url' => absolute_app_url($relativeSlicePath),
        'width' => $width,
        'height' => $height,
    ];
}

function customer_message_editor_render_real_email_button(
    string $href,
    string $label,
    int $width,
    int $height,
    int $fontSize
): string {
    $backgroundUrl = 'https://files.manuscdn.com/user_upload_by_module/session_file/310519663565489241/ksArwtVtypUlKkEr.webp';
    $safeLabel = trim($label) !== '' ? trim($label) : 'Abrir mensagem';
    $buttonWidth = max(120, $width);
    $buttonHeight = max(44, $height);
    $buttonFontSize = customer_message_clamp_int($fontSize, 12, 96, 24);
    $buttonRadius = max(22, (int) round($buttonHeight / 2));

    return '<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;width:' . e((string) $buttonWidth) . 'px;margin:0 auto;">'
        . '<tr>'
        . '<td align="center" background="' . e($backgroundUrl) . '" bgcolor="#b36b3d" style="background-image:url(\'' . e($backgroundUrl) . '\');background-size:cover;background-position:center;height:' . e((string) $buttonHeight) . 'px;border-radius:' . e((string) $buttonRadius) . 'px;" valign="middle">'
        . '<!--[if gte mso 9]>'
        . '<v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . e($href) . '" style="height:' . e((string) $buttonHeight) . 'px;v-text-anchor:middle;width:' . e((string) $buttonWidth) . 'px;" arcsize="50%" stroke="f" fillcolor="#b36b3d">'
        . '<v:fill type="tile" src="' . e($backgroundUrl) . '" color="#b36b3d" />'
        . '<w:anchorlock/>'
        . '<center>'
        . '<![endif]-->'
        . '<a href="' . e($href) . '" style="display:block;width:' . e((string) $buttonWidth) . 'px;height:' . e((string) $buttonHeight) . 'px;line-height:' . e((string) $buttonHeight) . 'px;color:#ffffff;font-family:\'Helvetica Neue\',Arial,sans-serif;font-size:' . e((string) $buttonFontSize) . 'px;font-weight:700;text-align:center;text-decoration:none;-webkit-text-size-adjust:none;mso-line-height-rule:exactly;">'
        . e($safeLabel)
        . '</a>'
        . '<!--[if gte mso 9]>'
        . '</center>'
        . '</v:roundrect>'
        . '<![endif]-->'
        . '</td>'
        . '</tr>'
        . '</table>';
}

function customer_message_editor_render_art_with_email_button(
    string $sourcePath,
    string $altText,
    array $buttonLayer,
    int $artWidth,
    int $artHeight,
    int $displayWidth,
    ?array &$debug = null
): ?string {
    $debug = [
        'status' => 'starting',
        'source_path' => $sourcePath,
    ];

    if (!is_file($sourcePath) || $artWidth <= 0 || $artHeight <= 0 || $displayWidth <= 0) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'source_missing_or_invalid_dimensions';
        return null;
    }

    $href = customer_message_absolute_link((string) ($buttonLayer['hrefRaw'] ?? ''));
    $label = trim((string) ($buttonLayer['textRaw'] ?? ''));
    $left = max(0, min($artWidth - 1, (int) round((float) ($buttonLayer['x'] ?? 0))));
    $top = max(0, min($artHeight - 1, (int) round((float) ($buttonLayer['y'] ?? 0))));
    $width = max(1, min($artWidth - $left, (int) round((float) ($buttonLayer['width'] ?? 0))));
    $height = max(1, min($artHeight - $top, (int) round((float) ($buttonLayer['height'] ?? 0))));
    $fontSize = customer_message_clamp_int($buttonLayer['fontSize'] ?? null, 12, 96, 24);

    if ($href === null || $label === '') {
        $debug['status'] = 'failed';
        $debug['reason'] = 'button_missing_label_or_link';
        return null;
    }

    $rowSliceDebug = [];
    $rowSlice = customer_message_editor_crop_art_region($sourcePath, 0, $top, $artWidth, $height, $rowSliceDebug);
    $debug['row_slice'] = $rowSliceDebug;
    if ($rowSlice === null) {
        $debug['status'] = 'failed';
        $debug['reason'] = 'row_slice_failed';
        return null;
    }

    $topSlice = null;
    if ($top > 0) {
        $topSlice = customer_message_editor_crop_art_region($sourcePath, 0, 0, $artWidth, $top);
    }

    $bottomHeight = max(0, $artHeight - ($top + $height));
    $bottomSlice = null;
    if ($bottomHeight > 0) {
        $bottomSlice = customer_message_editor_crop_art_region($sourcePath, 0, $top + $height, $artWidth, $bottomHeight);
    }

    $scale = $displayWidth / $artWidth;
    $displayTopHeight = $topSlice ? max(1, (int) round(((int) $topSlice['height']) * $scale)) : 0;
    $displayRowHeight = max(1, (int) round($height * $scale));
    $displayBottomHeight = $bottomSlice ? max(1, (int) round(((int) $bottomSlice['height']) * $scale)) : 0;
    $displayLeftWidth = max(0, (int) round($left * $scale));
    $displayButtonWidth = max(1, (int) round($width * $scale));
    $displayButtonHeight = max(1, $displayRowHeight);
    $displayFontSize = max(12, (int) round($fontSize * $scale));
    $displayRightWidth = max(0, $displayWidth - $displayLeftWidth - $displayButtonWidth);
    $buttonHtml = customer_message_editor_render_real_email_button($href, $label, $displayButtonWidth, $displayButtonHeight, $displayFontSize);

    $debug['status'] = 'success';
    $debug['button'] = [
        'left' => $left,
        'top' => $top,
        'width' => $width,
        'height' => $height,
        'font_size' => $fontSize,
    ];

    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;border-spacing:0;table-layout:fixed;margin:0 auto;">';

    if ($topSlice !== null) {
        $html .= '<tr><td style="padding:0;font-size:0;line-height:0;">'
            . '<img src="' . e((string) $topSlice['absolute_url']) . '" alt="' . e($altText) . '" width="' . e((string) $displayWidth) . '" height="' . e((string) $displayTopHeight) . '" style="display:block;width:100%;height:auto;border:0;margin:0;padding:0;">'
            . '</td></tr>';
    }

    $html .= '<tr><td background="' . e((string) $rowSlice['absolute_url']) . '" bgcolor="#efe4d4" height="' . e((string) $displayRowHeight) . '" style="padding:0;font-size:0;line-height:0;background-image:url(\'' . e((string) $rowSlice['absolute_url']) . '\');background-repeat:no-repeat;background-position:center top;background-size:100% 100%;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;border-spacing:0;table-layout:fixed;">'
        . '<tr>';

    if ($displayLeftWidth > 0) {
        $html .= '<td width="' . e((string) $displayLeftWidth) . '" style="width:' . e((string) $displayLeftWidth) . 'px;padding:0;font-size:0;line-height:0;">&nbsp;</td>';
    }

    $html .= '<td width="' . e((string) $displayButtonWidth) . '" align="center" valign="middle" style="width:' . e((string) $displayButtonWidth) . 'px;padding:0;font-size:0;line-height:0;vertical-align:middle;">'
        . $buttonHtml
        . '</td>';

    if ($displayRightWidth > 0) {
        $html .= '<td width="' . e((string) $displayRightWidth) . '" style="width:' . e((string) $displayRightWidth) . 'px;padding:0;font-size:0;line-height:0;">&nbsp;</td>';
    }

    $html .= '</tr></table></td></tr>';

    if ($bottomSlice !== null) {
        $html .= '<tr><td style="padding:0;font-size:0;line-height:0;">'
            . '<img src="' . e((string) $bottomSlice['absolute_url']) . '" alt="" width="' . e((string) $displayWidth) . '" height="' . e((string) $displayBottomHeight) . '" style="display:block;width:100%;height:auto;border:0;margin:0;padding:0;">'
            . '</td></tr>';
    }

    $html .= '</table>';

    return $html;
}

function customer_message_leaf_data_uri(string $side = 'right'): string
{
    $stroke = '%23e8cfc3';

    if ($side === 'left') {
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="220" height="170" viewBox="0 0 220 170" fill="none">
  <g stroke="{$stroke}" stroke-width="2" stroke-linecap="round" opacity="0.7">
    <path d="M10 158C48 120 72 85 86 24"/>
    <path d="M19 151C41 135 60 118 84 92"/>
    <path d="M29 144C54 128 70 114 88 98"/>
    <path d="M34 126C58 132 78 136 102 140"/>
    <path d="M52 108C71 118 92 126 118 132"/>
    <path d="M61 91C77 105 96 117 122 124"/>
    <path d="M72 72C84 93 104 109 130 116"/>
  </g>
</svg>
SVG;
    } else {
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="260" height="190" viewBox="0 0 260 190" fill="none">
  <g stroke="{$stroke}" stroke-width="2" stroke-linecap="round" opacity="0.75">
    <path d="M247 178C204 138 176 99 159 28"/>
    <path d="M235 169C212 151 191 132 166 103"/>
    <path d="M224 160C199 144 179 126 157 110"/>
    <path d="M220 137C193 142 171 146 142 151"/>
    <path d="M200 117C178 126 154 136 126 143"/>
    <path d="M188 98C170 112 149 124 121 132"/>
    <path d="M174 77C161 100 139 117 110 126"/>
  </g>
</svg>
SVG;
    }

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function customer_message_social_html(array $options = []): string
{
    $settings = customer_message_store_settings();
    $whatsPhone = digits_only((string) ($settings['telefone_whatsapp'] ?? ''));
    $whatsLink = $whatsPhone !== ''
        ? whatsapp_link($whatsPhone, 'Ola! Vim pela Moda Tropical.')
        : null;

    $items = [
        ['label' => 'Instagram', 'text' => 'IG', 'href' => null],
        ['label' => 'Facebook', 'text' => 'f', 'href' => null],
        ['label' => 'WhatsApp', 'text' => 'WA', 'href' => $whatsLink],
    ];

    $html = '';

    foreach ($items as $item) {
        $inner = '<span style="display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:999px;border:1px solid rgba(240,169,108,0.28);color:#f0b06b;font-size:14px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;">'
            . e($item['text'])
            . '</span>';

        if (!empty($item['href'])) {
            $html .= '<a href="' . e((string) $item['href']) . '" aria-label="' . e((string) $item['label']) . '" style="display:inline-block;text-decoration:none;">' . $inner . '</a>';
        } else {
            $html .= '<span aria-label="' . e((string) $item['label']) . '" style="display:inline-block;">' . $inner . '</span>';
        }
    }

    return $html;
}


function customer_message_build_editor_email_html(
    string $title,
    string $message,
    ?string $linkUrl,
    array &$options = []
): string {
    $emailFrameMaxWidth = 1080;
    $emailOuterPadding = 12;
    $storeName = trim((string) ($options['store_name'] ?? customer_message_store_name()));
    $ctaLabel = trim((string) ($options['cta_label'] ?? 'Abrir mensagem'));
    $editor = customer_message_editor_settings((array) ($options['editor'] ?? []));
    $heroImageUrl = trim((string) ($options['hero_image_url'] ?? ''));
    $artLinkUrl = trim((string) ($editor['image_link_url'] ?? ''));
    $titleSize = (string) $editor['title_size'];
    $safeTitle = customer_message_editor_apply_text_style($title, [
        'uppercase' => (bool) $editor['title_uppercase'],
    ]);
    $titleAlign = (string) $editor['title_align'];
    $bodyAlign = (string) $editor['body_align'];
    $titleShadowCss = match ((string) $editor['title_shadow']) {
        'strong' => '0 6px 22px rgba(0,0,0,0.30)',
        'soft' => '0 2px 10px rgba(0,0,0,0.16)',
        default => 'none',
    };
    $bodyShadowCss = match ((string) $editor['body_shadow']) {
        'strong' => '0 6px 22px rgba(0,0,0,0.30)',
        'soft' => '0 2px 10px rgba(0,0,0,0.16)',
        default => 'none',
    };
    $bodyHtml = customer_message_format_html_body(
        customer_message_editor_apply_text_style($message, [
            'uppercase' => (bool) $editor['body_uppercase'],
        ]),
        (string) $editor['body_color'],
        (int) $editor['body_size'],
        '14px'
    );
    $socialHtml = customer_message_social_html($options);
    $unsubscribeFooterHtml = trim((string) ($options['unsubscribe_footer_html'] ?? ''));
    $button = '';
    $imageHotspot = '';
    $media = '';
    $surfaceOverlay = '';
    $scene = customer_message_scene_from_context($options);
    $buttonLayer = customer_message_editor_email_button_layer($scene, $editor, $linkUrl, $ctaLabel);
    $sceneForArt = $scene;
    $optionsForArt = $options;
    $hasConfiguredHotspotLink = false;
    foreach ((array) ($scene['layers'] ?? []) as $layer) {
        if (!is_array($layer) || ($layer['visible'] ?? true) === false) {
            continue;
        }

        if ((string) ($layer['type'] ?? '') !== 'hotspot') {
            continue;
        }

        if (trim((string) ($layer['hrefRaw'] ?? '')) === '') {
            continue;
        }

        $hasConfiguredHotspotLink = true;
        break;
    }

    if (!$hasConfiguredHotspotLink && !empty($editor['show_image_hotspot']) && $artLinkUrl !== '') {
        $hasConfiguredHotspotLink = true;
    }

    $renderTrace = [
        'scene_background_image' => (string) ($scene['canvas']['backgroundImage'] ?? ''),
        'scene_layer_count' => isset($scene['layers']) && is_array($scene['layers'])
            ? count($scene['layers'])
            : 0,
        'art_scene_layer_count' => isset($sceneForArt['layers']) && is_array($sceneForArt['layers'])
            ? count($sceneForArt['layers'])
            : 0,
        'button_layer_present' => $buttonLayer !== null,
        'hero_image_path' => (string) ($options['hero_image_path'] ?? ''),
        'hero_image_url' => $heroImageUrl,
        'attempts' => [],
        'final_renderer' => null,
    ];

    if ($heroImageUrl !== '' || trim((string) ($scene['canvas']['backgroundImage'] ?? '')) !== '') {
        $cacheDebug = [];
        $tokenValues = (array) ($options['token_values'] ?? []);
        $renderedArt = customer_message_scene_render_with_cache(
            $sceneForArt,
            $tokenValues,
            [
                'project_id' => (string) ($options['project_id'] ?? ''),
                'project_name' => (string) ($options['project_name'] ?? ''),
                'hero_image_path' => (string) ($options['hero_image_path'] ?? ''),
                'scene_version' => (string) ($options['scene_version'] ?? ''),
                'normalizer_version' => (string) ($options['normalizer_version'] ?? CUSTOMER_MESSAGE_RENDER_CACHE_NORMALIZER_VERSION),
            ],
            static function () use ($sceneForArt, $tokenValues, $options, $optionsForArt, $title, $message, $linkUrl, &$renderTrace) {
                $compositionDebug = [];
                $renderedArt = customer_message_scene_render_composition(
                    $sceneForArt,
                    $tokenValues,
                    [
                        'hero_image_path' => (string) ($options['hero_image_path'] ?? ''),
                    ],
                    $compositionDebug
                );
                $renderTrace['attempts'][] = $compositionDebug;

                if ($renderedArt === null) {
                    $browserDebug = [];
                    $renderedArt = customer_message_scene_render_browser(
                        $sceneForArt,
                        $tokenValues,
                        $browserDebug
                    );
                    $renderTrace['attempts'][] = $browserDebug;
                }

                if ($renderedArt === null) {
                    $flatArtDebug = [];
                    $renderedArt = customer_message_editor_render_flat_art($title, $message, $linkUrl, $optionsForArt, $flatArtDebug);
                    $renderTrace['attempts'][] = $flatArtDebug;
                }

                return $renderedArt;
            },
            $cacheDebug
        );
        $renderTrace['cache'] = $cacheDebug;

        $renderTrace['final_renderer'] = (string) ($renderedArt['renderer'] ?? '');
        $renderTrace['final_path'] = (string) ($renderedArt['path'] ?? '');
        $renderTrace['final_absolute_url'] = (string) ($renderedArt['absolute_url'] ?? '');
        $renderTrace['final_width'] = (int) ($renderedArt['width'] ?? 0);
        $renderTrace['final_height'] = (int) ($renderedArt['height'] ?? 0);
        $renderTrace['render_cache_key'] = (string) ($cacheDebug['cache_key'] ?? '');
        $renderTrace['render_cache_status'] = (string) ($cacheDebug['status'] ?? '');
        $renderTrace['final_hotspot_count'] = is_array($renderedArt['hotspots'] ?? null)
            ? count((array) $renderedArt['hotspots'])
            : 0;
        $options['_debug_editor_render'] = $renderTrace;

        $imageSourceUrl = trim((string) ($renderedArt['absolute_url'] ?? ''));

        if ($imageSourceUrl === '') {
            $imageSourceUrl = $heroImageUrl;
        }

        $clickableUrl = trim((string) customer_message_editor_click_url($options, trim((string) ($linkUrl ?? ''))));
        $artWidth = max(0, (int) ($renderedArt['width'] ?? 0));
        $artHeight = max(0, (int) ($renderedArt['height'] ?? 0));
        $renderedArtPath = trim((string) ($renderedArt['path'] ?? ''));

        if (($artWidth <= 0 || $artHeight <= 0) && $renderedArtPath !== '') {
            $imagePath = BASE_PATH . '/' . ltrim(str_replace('\\', '/', $renderedArtPath), '/');
            $dimensions = customer_message_scene_render_image_dimensions($imagePath);
            $artWidth = max($artWidth, (int) ($dimensions['width'] ?? 0));
            $artHeight = max($artHeight, (int) ($dimensions['height'] ?? 0));
        }

        $sceneCanvasWidth = max(0, (int) ($scene['canvas']['width'] ?? 0));
        $displayBaseWidth = $sceneCanvasWidth > 0
            ? (int) round($sceneCanvasWidth * 1.1)
            : $emailFrameMaxWidth;
        $displayWidth = max(1, min($emailFrameMaxWidth, $displayBaseWidth));
        $displayHeight = ($artWidth > 0 && $artHeight > 0)
            ? max(1, (int) round(($artHeight / $artWidth) * $displayWidth))
            : 0;
        $displayHeightAttr = $displayHeight > 0 ? ' height="' . e((string) $displayHeight) . '"' : '';
        $linkedImage = '<img src="' . e($imageSourceUrl) . '" alt="' . e($title !== '' ? $title : $storeName) . '" width="' . e((string) $displayWidth) . '"' . $displayHeightAttr . ' style="display:block;width:' . e((string) $displayWidth) . 'px;max-width:100%;height:auto;border:0;">';

        $renderedHotspots = (array) ($renderedArt['hotspots'] ?? []);
        $renderedArtAbsolutePath = $renderedArtPath !== ''
            ? BASE_PATH . '/' . ltrim(str_replace('\\', '/', $renderedArtPath), '/')
            : '';
        $buttonHtmlDebug = [];
        $buttonHtml = $buttonLayer !== null
            ? customer_message_editor_render_art_with_email_button(
                $renderedArtAbsolutePath,
                $title !== '' ? $title : $storeName,
                $buttonLayer,
                $artWidth,
                $artHeight,
                $displayWidth,
                $buttonHtmlDebug
            )
            : null;
        $renderTrace['button_html_debug'] = $buttonHtmlDebug;

        if (is_string($buttonHtml) && $buttonHtml !== '') {
            $linkedImage = $buttonHtml;
            $renderTrace['hotspot_link_mode'] = 'html_button_row';
        } else {
            $hotspotSliceDebug = [];
            $hotspotsHtml = customer_message_editor_render_art_hotspot_slices(
                $renderedArtAbsolutePath,
                $title !== '' ? $title : $storeName,
                $renderedHotspots,
                $artWidth,
                $artHeight,
                $hotspotSliceDebug
            );
            $renderTrace['hotspot_slice_debug'] = $hotspotSliceDebug;

            if (is_string($hotspotsHtml) && $hotspotsHtml !== '') {
                $renderTrace['hotspot_link_mode'] = 'sliced_hotspot_only';
                $linkedImage = $hotspotsHtml;
            } elseif ($clickableUrl !== '' && !$hasConfiguredHotspotLink) {
                $linkedImage = '<a href="' . e($clickableUrl) . '" style="display:block;text-decoration:none;">' . $linkedImage . '</a>';
                $renderTrace['hotspot_link_mode'] = 'full_image_link';
            } elseif ($clickableUrl !== '' && $hasConfiguredHotspotLink) {
                $renderTrace['hotspot_link_mode'] = 'configured_but_slice_missing';
            } else {
                $renderTrace['hotspot_link_mode'] = 'none';
            }
        }

        $options['_debug_editor_render'] = $renderTrace;

        $unsubscribeBlock = $unsubscribeFooterHtml !== ''
            ? '<div style="max-width:' . e((string) $emailFrameMaxWidth) . 'px;margin:18px auto 0;padding:0 14px;font-size:12px;line-height:1.6;color:#5b4a44;text-align:center;">'
                . $unsubscribeFooterHtml
            . '</div>'
            : '';

        return '
            <div style="margin:0;padding:' . e((string) $emailOuterPadding) . 'px;background:#efe4d4;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
                    <tr>
                        <td align="center">
                            <table role="presentation" width="' . e((string) $emailFrameMaxWidth) . '" cellpadding="0" cellspacing="0" style="width:100%;max-width:' . e((string) $emailFrameMaxWidth) . 'px;border-collapse:separate;border-spacing:0;background:transparent;border-radius:36px;overflow:hidden;box-shadow:0 30px 60px rgba(33,24,18,0.16);">
                                <tr>
                                    <td style="padding:0;">
                                        ' . $linkedImage . '
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                ' . $unsubscribeBlock . '
            </div>
        ';
    }

    $renderTrace['final_renderer'] = 'html_only';
    $options['_debug_editor_render'] = $renderTrace;

    $media = '<div style="display:block;width:100%;min-height:560px;background:linear-gradient(135deg,#351223 0%,#6d1732 52%,#f68f3a 100%);"></div>';
    $surfaceOverlay = '<div style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0.08),rgba(0,0,0,0.12));"></div>';

    $titleBlock = $editor['show_title']
        ? '<div style="position:absolute;left:' . e((string) $editor['title_x']) . '%;top:' . e((string) $editor['title_y']) . '%;width:' . e((string) $editor['title_width']) . '%;max-width:' . e((string) $editor['title_width']) . '%;z-index:3;text-align:' . e($titleAlign) . ';">'
            . '<h1 style="margin:0;color:' . e((string) $editor['title_color']) . ';font-size:' . e($titleSize) . 'px;line-height:' . e(number_format(((int) $editor['title_line_height']) / 100, 2, '.', '')) . ';font-weight:' . ($editor['title_bold'] ? '800' : '500') . ';font-style:' . ($editor['title_italic'] ? 'italic' : 'normal') . ';letter-spacing:-0.04em;text-transform:' . ($editor['title_uppercase'] ? 'uppercase' : 'none') . ';text-shadow:' . e($titleShadowCss) . ';word-break:break-word;">' . e($safeTitle) . '</h1>'
        . '</div>'
        : '';

    $bodyBlock = $editor['show_body']
        ? '<div style="position:absolute;left:' . e((string) $editor['body_x']) . '%;top:' . e((string) $editor['body_y']) . '%;width:' . e((string) $editor['body_width']) . '%;max-width:' . e((string) $editor['body_width']) . '%;z-index:3;color:' . e((string) $editor['body_color']) . ';font-size:' . e((string) $editor['body_size']) . 'px;line-height:' . e(number_format(((int) $editor['body_line_height']) / 100, 2, '.', '')) . ';text-align:' . e($bodyAlign) . ';font-weight:' . ($editor['body_bold'] ? '800' : '400') . ';font-style:' . ($editor['body_italic'] ? 'italic' : 'normal') . ';text-transform:' . ($editor['body_uppercase'] ? 'uppercase' : 'none') . ';text-shadow:' . e($bodyShadowCss) . ';">' . $bodyHtml . '</div>'
        : '';

    if ($editor['show_button'] && $linkUrl !== null && trim($linkUrl) !== '') {
        $buttonFontSize = customer_message_clamp_int($editor['button_font_size'] ?? null, 12, 96, 18);
        $button = '
            <div style="position:absolute;left:' . e((string) $editor['button_x']) . '%;top:' . e((string) $editor['button_y']) . '%;z-index:2;">
                <a href="' . e($linkUrl) . '" style="display:inline-flex;align-items:center;justify-content:center;min-height:58px;padding:0 30px;border-radius:999px;background:linear-gradient(180deg,#efc96b 0%,#dfaa35 100%);color:#4d2c0f;text-decoration:none;font-size:' . e((string) $buttonFontSize) . 'px;font-weight:800;box-shadow:0 18px 28px rgba(86,45,27,0.18), inset 0 1px 0 rgba(255,255,255,0.38);">'
                    . e($ctaLabel)
                . '</a>
            </div>';
    }

    if ($editor['show_image_hotspot'] && trim((string) ($editor['image_link_url'] ?? '')) !== '') {
        $imageHotspot = '
            <a href="' . e((string) $editor['image_link_url']) . '" style="position:absolute;left:' . e((string) $editor['image_hotspot_x']) . '%;top:' . e((string) $editor['image_hotspot_y']) . '%;width:' . e((string) $editor['image_hotspot_width']) . '%;height:' . e((string) $editor['image_hotspot_height']) . '%;z-index:4;display:block;text-decoration:none;background:rgba(255,255,255,0.001);font-size:0;line-height:0;">&nbsp;</a>';
    }

    $unsubscribeBlock = $unsubscribeFooterHtml !== ''
        ? '<div style="max-width:' . e((string) $emailFrameMaxWidth) . 'px;margin:18px auto 0;padding:0 14px;font-size:12px;line-height:1.6;color:#5b4a44;text-align:center;">'
            . $unsubscribeFooterHtml
        . '</div>'
        : '';

    return '
        <div style="margin:0;padding:' . e((string) $emailOuterPadding) . 'px;background:#efe4d4;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
                <tr>
                    <td align="center">
                        <table role="presentation" width="' . e((string) $emailFrameMaxWidth) . '" cellpadding="0" cellspacing="0" style="width:100%;max-width:' . e((string) $emailFrameMaxWidth) . 'px;border-collapse:separate;border-spacing:0;background:#ffffff;border:1px solid rgba(25,18,18,0.07);border-radius:36px;overflow:hidden;box-shadow:0 30px 60px rgba(33,24,18,0.16);">
                            <tr>
                                <td style="padding:0;">
                                    <div style="position:relative;overflow:hidden;background:#351223;">
                                        ' . $media . '
                                        ' . $surfaceOverlay . '
                                        ' . $titleBlock . '
                                        ' . $bodyBlock . '
                                        ' . $button . '
                                        ' . $imageHotspot . '
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:26px 30px;background:#24181d;color:#f6efe8;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
                                        <tr>
                                            <td style="font-size:14px;line-height:1.6;color:#efe3d5;">
                                                Voce recebeu esta mensagem da <strong style="color:#ffffff;letter-spacing:0.03em;">' . e(strtoupper($storeName)) . '</strong>.
                                            </td>
                                            <td align="right" style="white-space:nowrap;">
                                                <div style="display:inline-flex;align-items:center;gap:10px;">' . $socialHtml . '</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            ' . $unsubscribeBlock . '
        </div>
    ';
}

function customer_message_build_email_html(
    string $title,
    string $message,
    ?string $linkUrl,
    array &$options = []
): string {
    $emailFrameMaxWidth = 1080;
    $emailOuterPadding = 12;
    $storeName = trim((string) ($options['store_name'] ?? customer_message_store_name()));
    $eyebrow = trim((string) ($options['eyebrow'] ?? $storeName));
    $ctaLabel = trim((string) ($options['cta_label'] ?? 'Abrir mensagem'));
    $kind = trim((string) ($options['kind'] ?? 'manual'));
    $theme = customer_message_theme($kind);
    $theme = $options['theme'] ?? $theme;
    $logoUrl = trim((string) ($options['logo_url'] ?? customer_message_store_logo_url()));
    $safeTitle = e($title);
    $bodyHtml = customer_message_format_html_body($message, (string) ($theme['muted'] ?? '#665b74'));
    $leftLeaf = customer_message_leaf_data_uri('left');
    $rightLeaf = customer_message_leaf_data_uri('right');
    $socialHtml = customer_message_social_html($options);
    $heroImageUrl = trim((string) ($options['hero_image_url'] ?? ''));
    $heroLayout = customer_message_hero_text_layout((string) ($options['hero_text_position'] ?? 'bottom-left'));
    $layout = trim((string) ($options['layout'] ?? 'default'));
    $unsubscribeFooterHtml = trim((string) ($options['unsubscribe_footer_html'] ?? ''));

    if ($layout === 'editor') {
        return customer_message_build_editor_email_html($title, $message, $linkUrl, $options);
    }

    $button = '';

    if ($linkUrl !== null && trim($linkUrl) !== '') {
        $button = '
            <div style="margin:34px 0 0;text-align:center;">
                <a href="' . e($linkUrl) . '" style="display:inline-flex;align-items:center;justify-content:center;min-height:62px;padding:0 34px;border-radius:999px;background:linear-gradient(180deg,#efc96b 0%,#dfaa35 100%);color:#4d2c0f;text-decoration:none;font-size:17px;font-weight:800;box-shadow:0 18px 28px rgba(86,45,27,0.18), inset 0 1px 0 rgba(255,255,255,0.38);">'
                    . e($ctaLabel)
                . '</a>
            </div>';
    }

    $logoMarkup = $logoUrl !== ''
        ? '<img src="' . e($logoUrl) . '" alt="' . e($storeName) . '" style="display:block;width:100%;max-width:360px;max-height:210px;margin:0 auto;object-fit:contain;">'
        : '<span style="display:inline-block;font-size:18px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:#ffffff;">' . e($storeName) . '</span>';

    $heroBlock = '
        <tr>
            <td style="padding:34px 46px 52px;background:' . e((string) ($theme['hero'] ?? '#4e1126')) . ';color:#ffffff;">
                <div style="text-align:center;margin:0 0 26px;">' . $logoMarkup . '</div>
                <h1 style="margin:0;max-width:560px;font-family:Inter,Segoe UI,sans-serif;font-size:58px;line-height:1.03;font-weight:500;letter-spacing:-0.04em;color:#ffffff;">' . $safeTitle . '</h1>
            </td>
        </tr>';

    if ($heroImageUrl !== '') {
        $heroBlock = '
        <tr>
            <td background="' . e($heroImageUrl) . '" style="height:430px;padding:' . e((string) $heroLayout['padding']) . ';background:#341425 url(\'' . e($heroImageUrl) . '\') center center / cover no-repeat;color:#ffffff;text-align:' . e((string) $heroLayout['align']) . ';vertical-align:' . e((string) $heroLayout['vertical']) . ';">
                <div style="display:block;width:100%;max-width:' . e((string) $heroLayout['max_width']) . ';margin:' . e((string) $heroLayout['wrapper_margin']) . ';">
                    <h1 style="margin:0;font-family:Inter,Segoe UI,sans-serif;font-size:58px;line-height:1.03;font-weight:500;letter-spacing:-0.04em;color:#ffffff;text-shadow:0 3px 18px rgba(0,0,0,0.34);">' . $safeTitle . '</h1>
                </div>
            </td>
        </tr>';
    }

    $unsubscribeBlock = $unsubscribeFooterHtml !== ''
        ? '<div style="max-width:' . e((string) $emailFrameMaxWidth) . 'px;margin:18px auto 0;padding:0 14px;font-size:12px;line-height:1.6;color:#5b4a44;text-align:center;">'
            . $unsubscribeFooterHtml
        . '</div>'
        : '';

    return '
        <div style="margin:0;padding:' . e((string) $emailOuterPadding) . 'px;background:#efe4d4;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
                <tr>
                    <td align="center">
                        <table role="presentation" width="' . e((string) $emailFrameMaxWidth) . '" cellpadding="0" cellspacing="0" style="width:100%;max-width:' . e((string) $emailFrameMaxWidth) . 'px;border-collapse:separate;border-spacing:0;background:#ffffff;border:1px solid rgba(25,18,18,0.07);border-radius:36px;overflow:hidden;box-shadow:0 30px 60px rgba(33,24,18,0.16);">
                            ' . $heroBlock . '
                            <tr>
                                <td style="padding:46px 46px 42px;background-color:' . e((string) ($theme['surface'] ?? '#fff7f3')) . ';background-image:url(\'' . $leftLeaf . '\'),url(\'' . $rightLeaf . '\');background-position:left bottom,right bottom;background-size:132px auto,228px auto;background-repeat:no-repeat,no-repeat;">
                                    <div style="max-width:620px;">
                                        ' . $bodyHtml . '
                                    </div>
                                    ' . $button . '
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:26px 30px;background:#24181d;color:#f6efe8;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
                                        <tr>
                                            <td style="font-size:14px;line-height:1.6;color:#efe3d5;">
                                                Voce recebeu esta mensagem da <strong style="color:#ffffff;letter-spacing:0.03em;">' . e(strtoupper($storeName)) . '</strong>.
                                            </td>
                                            <td align="right" style="white-space:nowrap;">
                                                <div style="display:inline-flex;align-items:center;gap:10px;">' . $socialHtml . '</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            ' . $unsubscribeBlock . '
        </div>
    ';
}

function customer_message_build_email_text(
    string $title,
    string $message,
    ?string $linkUrl,
    array $options = []
): string {
    $storeName = trim((string) ($options['store_name'] ?? customer_message_store_name()));
    $eyebrow = trim((string) ($options['eyebrow'] ?? $storeName));
    $ctaLabel = trim((string) ($options['cta_label'] ?? 'Abrir mensagem'));

    $text = $storeName . PHP_EOL;
    $text .= str_repeat('=', max(10, strlen($storeName))) . PHP_EOL . PHP_EOL;
    $text .= strtoupper($eyebrow) . PHP_EOL . PHP_EOL;
    $text .= trim($title) . PHP_EOL . PHP_EOL;
    $text .= trim($message) . PHP_EOL;

    if ($linkUrl !== null && trim($linkUrl) !== '') {
        $text .= PHP_EOL . $ctaLabel . ': ' . trim($linkUrl) . PHP_EOL;
    }

    $unsubscribeFooterText = trim((string) ($options['unsubscribe_footer_text'] ?? ''));

    if ($unsubscribeFooterText !== '') {
        $text .= PHP_EOL . PHP_EOL . $unsubscribeFooterText . PHP_EOL;
    }

    return $text;
}

function customer_message_debug_extract_image_urls(string $html): array
{
    if ($html === '') {
        return [];
    }

    preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches);
    $urls = isset($matches[1]) && is_array($matches[1]) ? $matches[1] : [];

    return array_values(array_unique(array_map(
        static fn(string $value): string => trim($value),
        array_filter($urls, static fn($value): bool => is_string($value) && trim($value) !== '')
    )));
}

function customer_message_send(
    array $customer,
    string $type,
    string $title,
    string $message,
    ?string $linkUrl = null,
    array $options = []
): array {
    $customerId = (int) ($customer['id'] ?? 0);
    $kind = trim((string) ($options['kind'] ?? 'manual'));
    $prepared = customer_message_prepare_content(
        $title,
        $message,
        $linkUrl,
        $customer,
        $kind,
        (array) ($options['context'] ?? [])
    );
    $payload = (array) ($options['payload'] ?? []);
    $sendNotification = array_key_exists('send_notification', $options) ? (bool) $options['send_notification'] : true;
    $sendEmail = array_key_exists('send_email', $options) ? (bool) $options['send_email'] : true;
    $captureDebug = !empty($options['capture_debug']);
    $result = [
        'notification_sent' => false,
        'email' => [
            'success' => false,
            'delivered' => false,
            'logged' => false,
            'log_path' => null,
            'error' => null,
        ],
        'content' => $prepared,
    ];

    if ($sendNotification && $customerId > 0) {
        create_customer_notification(
            $customerId,
            $type,
            $prepared['title'],
            $prepared['message'],
            $prepared['link_url'],
            $payload + ['kind' => $kind]
        );
        $result['notification_sent'] = true;
    }

    if ($sendEmail) {
        $email = trim((string) ($customer['email'] ?? ''));

        if ($email !== '') {
            if (customer_email_marketing_is_opted_out($customer)) {
                $result['email'] = [
                    'success' => true,
                    'delivered' => false,
                    'logged' => false,
                    'log_path' => null,
                    'error' => null,
                    'skipped' => true,
                    'reason' => 'marketing_opt_out',
                ];

                return $result;
            }

            $tokenValues = customer_message_tokens($customer, [
                'promotions_url' => absolute_promotions_url(),
                'store_url' => absolute_app_url('index.php'),
            ] + ((array) ($options['context'] ?? [])));
            $unsubscribeFooterHtml = customer_email_marketing_footer_html($customer, customer_message_store_name());
            $unsubscribeFooterText = customer_email_marketing_footer_text($customer, customer_message_store_name());
            $deliveryHeaders = customer_email_marketing_headers($customer);
            $htmlOptions = [
                'project_id' => (string) (($options['context']['project_id'] ?? $options['project_id'] ?? '')),
                'project_name' => (string) (($options['context']['project_name'] ?? $options['project_name'] ?? '')),
                'store_name' => customer_message_store_name(),
                'eyebrow' => $prepared['eyebrow'],
                'cta_label' => $prepared['cta_label'],
                'kind' => $kind,
                'theme' => $prepared['theme'],
                'logo_url' => customer_message_store_logo_url(),
                'hero_image_path' => $prepared['hero_image_path'],
                'hero_image_url' => $prepared['hero_image_url'],
                'hero_text_position' => $prepared['hero_text_position'],
                'layout' => $prepared['layout'],
                'editor' => $prepared['editor'],
                'email_editor_layers' => $prepared['editor_layers'],
                'scene' => $prepared['scene'],
                'scene_json' => $prepared['scene_json'],
                'token_values' => $tokenValues,
                'unsubscribe_footer_html' => $unsubscribeFooterHtml,
                'customer' => $customer,
            ];
            $htmlBody = customer_message_build_email_html(
                $prepared['title'],
                $prepared['message'],
                $prepared['email_link_url'],
                $htmlOptions
            );
            $textBody = customer_message_build_email_text(
                $prepared['title'],
                $prepared['message'],
                $prepared['email_link_url'],
                [
                    'store_name' => customer_message_store_name(),
                    'eyebrow' => $prepared['eyebrow'],
                    'cta_label' => $prepared['cta_label'],
                    'unsubscribe_footer_text' => $unsubscribeFooterText,
                ]
            );

            $result['email'] = send_email_message(
                $email,
                trim((string) ($customer['nome'] ?? 'Cliente')),
                $prepared['title'],
                $htmlBody,
                $textBody,
                ['headers' => $deliveryHeaders]
            );

            if ($captureDebug) {
                $result['debug'] = [
                    'layout' => $prepared['layout'],
                    'hero_image_path' => $prepared['hero_image_path'],
                    'hero_image_url' => $prepared['hero_image_url'],
                    'scene_json_bytes' => strlen((string) ($prepared['scene_json'] ?? '')),
                    'scene_layer_count' => isset($prepared['scene']['layers']) && is_array($prepared['scene']['layers'])
                        ? count($prepared['scene']['layers'])
                        : 0,
                    'editor_layers_count' => is_array($prepared['editor_layers'] ?? null)
                        ? count($prepared['editor_layers'])
                        : 0,
                    'email_html_bytes' => strlen($htmlBody),
                    'email_text_bytes' => strlen($textBody),
                    'email_image_urls' => customer_message_debug_extract_image_urls($htmlBody),
                    'token_keys' => array_keys($tokenValues),
                    'editor_render_trace' => $htmlOptions['_debug_editor_render'] ?? null,
                ];
            }
        }
    }

    return $result;
}

function customer_send_welcome_message(array $customer): array
{
    $presets = customer_message_presets();
    $preset = $presets['boas_vindas'];

    return customer_message_send(
        $customer,
        'boas_vindas',
        (string) $preset['title'],
        (string) $preset['message'],
        (string) $preset['link_url'],
        [
            'kind' => 'boas_vindas',
            'send_notification' => true,
            'send_email' => true,
            'payload' => ['kind' => 'welcome'],
        ]
    );
}

function customer_favorite_targets_for_product(int $productId): array
{
    if ($productId <= 0) {
        return [];
    }

    ensure_customer_email_marketing_columns();

    if (function_exists('ensure_customer_favorites_table')) {
        ensure_customer_favorites_table();
    }

    $statement = db()->prepare(
        'SELECT DISTINCT c.id, c.nome, c.email
         FROM cliente_favoritos cf
         INNER JOIN clientes c ON c.id = cf.cliente_id
         WHERE cf.produto_id = :produto_id
           AND c.ativo = 1
           AND c.email_marketing_opt_out_at IS NULL
         ORDER BY c.nome ASC'
    );
    $statement->execute(['produto_id' => $productId]);

    return $statement->fetchAll();
}

function customer_send_back_in_stock_alerts(
    int $productId,
    string $productName,
    string $productSlug,
    int $previousStock,
    int $newStock
): int {
    if ($productId <= 0 || $previousStock > 0 || $newStock <= 0) {
        return 0;
    }

    $targets = customer_favorite_targets_for_product($productId);

    if ($targets === []) {
        return 0;
    }

    $productPath = 'produto.php?slug=' . rawurlencode($productSlug);
    $sentCount = 0;

    foreach ($targets as $target) {
        customer_message_send(
            $target,
            'estoque',
            'Seu salvo voltou ao estoque',
            "A peca {{produto_nome}} voltou ao estoque e ja pode ser garantida novamente.\nSe quiser aproveitar a reposicao, vale entrar agora porque os tamanhos podem sair rapido.",
            $productPath,
            [
                'kind' => 'estoque_novo',
                'payload' => [
                    'product_id' => $productId,
                    'kind' => 'back_in_stock',
                ],
                'context' => [
                    'product_name' => $productName,
                    'product_url' => absolute_app_url($productPath),
                ],
            ]
        );
        $sentCount++;
    }

    return $sentCount;
}
