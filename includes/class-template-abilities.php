<?php
defined('ABSPATH') || exit;

/**
 * Block Design Abilities – Template yetenekleri.
 *
 * WordPress Abilities API'ye blok tema şablonu yönetimi için üç yetenek kaydeder:
 * list-templates, get-template, add-or-update-template.
 *
 * Şablonlar iki kaynaktan gelebilir:
 *  - Tema dosyası : Henüz veritabanına kaydedilmemiş, slug ile erişilir.
 *  - Veritabanı   : wp_template CPT olarak kayıtlı, post_id ile güncellenir.
 *
 * İzin gereksinimi: edit_theme_options.
 *
 * @package Block_Design_Abilities
 * @since   1.0.0
 */
class Block_Design_Abilities_Templates
{
    /**
     * Sınıfı başlatır; wp_abilities_api_init kancasını dinleyen tüm
     * yetenek kayıt metodlarını bağlar.
     */
    public function __construct()
    {
        add_action('wp_abilities_api_init', array($this, 'register_list_templates_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_get_template_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_update_template_ability'));
    }

    /**
     * 'block-design-abilities/list-templates' yeteneğini Abilities API'ye kaydeder.
     *
     * Yetenek parametre almaz; aktif temanın tüm şablonlarını slug, title
     * ve (varsa) post_id bilgileriyle döndürür.
     *
     * @return void
     */
    public function register_list_templates_ability()
    {
        wp_register_ability(
            'block-design-abilities/list-templates',
            array(
                'label'       => __('List Templates', 'block-design-abilities'),
                'description' => __('Returns all available templates for the active theme.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'theme' => array(
                            'type'        => 'string',
                            'description' => __('The active theme slug.', 'block-design-abilities'),
                        ),
                        'templates' => array(
                            'type'        => 'array',
                            'description' => __('All available templates.', 'block-design-abilities'),
                            'items'       => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'slug' => array(
                                        'type'        => 'string',
                                        'description' => __('Unique slug of the template (e.g. "front-page", "archive", "single"). Use this when calling get-template.', 'block-design-abilities'),
                                    ),
                                    'title' => array(
                                        'type'        => 'string',
                                        'description' => __('Human-readable title of the template.', 'block-design-abilities'),
                                    ),                                
                                    'post_id' => array(
                                        'type'        => 'integer',
                                        'description' => __('Database post ID. (If the template is saved to the database) Use this when calling update-template.', 'block-design-abilities'),
                                    )
                                ),
                            ),
                        )
                    ),
                ),
                'execute_callback'    => array($this, 'list_templates'),
                'permission_callback' => function () {
                    return current_user_can('edit_theme_options');
                },
                'meta' => array('mcp' => array('public' => true)),
            )
        );
    }

    /**
     * Aktif temanın tüm blok şablonlarını listeler.
     *
     * get_block_templates() fonksiyonu mevcut değilse boş dizi döner.
     * Veritabanına kaydedilmiş şablonlarda post_id alanı bulunur; yalnızca
     * tema dosyasından okunanlar için bu alan eklenmez.
     * Sonuç slug değerine göre alfabetik sıralanır.
     *
     * @return array{
     *     theme?:     string,
     *     templates:  array<int, array{slug: string, title: string, post_id?: int}>,
     * }
     */
    public function list_templates(): array
    {
        if (! function_exists('get_block_templates')) {
            return array(
                'templates' => array(),
            );
        }

        $block_templates = get_block_templates(array(), 'wp_template');

        if (empty($block_templates)) {
            return array(
                'theme'     => get_stylesheet(),
                'templates' => array(),
            );
        }

        $templates = array_map(function ($tpl) {
            $item = array(
                'slug'        => $tpl->slug,
                'title'       => $tpl->title
            );

            // post_id is only present for templates saved to the database
            if (! empty($tpl->wp_id)) {
                $item['post_id'] = (int) $tpl->wp_id;
            }

            return $item;
        }, $block_templates);

        // Sort alphabetically
        usort($templates, fn($a, $b) => strcmp($a['slug'], $b['slug']));

        return array(
            'theme'     => get_stylesheet(),
            'templates' => $templates,
        );
    }

    /**
     * 'block-design-abilities/get-template' yeteneğini Abilities API'ye kaydeder.
     *
     * Yetenek; slug (zorunlu) parametresini kabul eder ve şablonun
     * ayrıştırılmış blok dizisini döndürür.
     * Şablon bulunamazsa boş blok dizisi döner.
     *
     * @return void
     */
    public function register_get_template_ability()
    {
        wp_register_ability(
            'block-design-abilities/get-template',
            array(
                'label'       => __('Get Template', 'block-design-abilities'),
                'description' => __('Returns a template\'s block structure by slug. Use list-templates first to get slugs. Edit the returned blocks and pass them to update-template.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'required'   => array('slug'),
                    'properties' => array(
                        'slug' => array(
                            'type'        => 'string',
                            'description' => __('Template slug from list-templates.', 'block-design-abilities'),
                        ),
                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'slug'        => array('type' => 'string'),
                        'title'       => array('type' => 'string'),
                        'post_id'     => array('type' => 'integer'),
                        'blocks'      => array('type' => 'array'),
                    ),
                ),

                'execute_callback'    => array($this, 'get_template'),
                'permission_callback' => function () {
                    return current_user_can('edit_theme_options');
                },
                'meta' => array('mcp' => array('public' => true)),
            )
        );
    }

    /**
     * Tek bir şablonu ayrıştırılmış blok dizisi olarak döndürür.
     *
     * Şablon ID'si "tema-slug//template-slug" formatında oluşturularak
     * get_block_template() ile çekilir. İçerik parse_blocks() ile ayrıştırılır;
     * blockName değeri boş olan düğümler sonuçtan çıkarılır.
     * post_id, yalnızca şablon veritabanına kaydedilmişse döndürülür.
     *
     * @param array{
     *     slug: string,
     * } $input Yetenek giriş parametreleri.
     *
     * @return array{
     *     slug?:    string,
     *     title?:   string,
     *     post_id?: int,
     *     blocks:   array<int, array<string, mixed>>,
     * }
     */
    public function get_template(array $input): array
    {
        $slug = sanitize_title($input['slug']);

        // Format: 'theme-slug//template-slug'
        $template_id = get_stylesheet() . '//' . $slug;

        $template = get_block_template($template_id, 'wp_template');

        if (! $template) {
            return array(
                'blocks' => array()
            );
        }

        $raw_content = $template->content;

        $parsed_blocks = parse_blocks($raw_content);

        // Remove whitespace-only blocks
        $parsed_blocks = array_values(
            array_filter($parsed_blocks, function ($block) {
                return ! empty($block['blockName']);
            })
        );

        $result = array(
            'slug'        => $template->slug,
            'title'       => $template->title,        
            'blocks'      => $parsed_blocks        
        );

        // Only add post_id if the template has a DB record
        if (! empty($template->wp_id)) {
            $result['post_id'] = (int) $template->wp_id;
        }

        return $result;
    }

    /**
     * 'block-design-abilities/add-or-update-template' yeteneğini Abilities API'ye kaydeder.
     *
     * Yetenek; blocks (zorunlu), post_id veya slug (birini seçmeli)
     * parametrelerini kabul eder. post_id ve slug aynı anda verilemez.
     *
     * @return void
     */
    public function register_update_template_ability()
    {
        wp_register_ability(
            'block-design-abilities/add-or-update-template',
            array(
                'label'       => __('Add or Update Template', 'block-design-abilities'),
                'description' => __('Saves an updated block array to a template. Always call get-template first, edit the returned blocks, then pass them here with post_id or slug. Do not use both post_id and slug simultaneously.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'required'   => array('blocks'),
                    'properties' => array(

                        'post_id' => array(
                            'type'        => 'integer',
                            'description' => __('DB post ID of the template. Update existing template if post_id is provided.', 'block-design-abilities'),
                        ),

                        'slug' => array(
                            'type'        => 'string',
                            'description' => __('Template slug. Use this to create (duplicate) new template from theme file.', 'block-design-abilities'),
                        ),

                        'blocks' => array(
                            'type'        => 'array',
                            'description' => __('Full updated block array. Same structure as returned by get-template. Replaces template content entirely.', 'block-design-abilities')
                        ),

                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success'            => array('type' => 'boolean'),
                        'error'              => array('type' => 'string'),
                        'post_id'            => array('type' => 'integer'),
                    ),
                ),

                'execute_callback'    => array($this, 'update_template'),
                'permission_callback' => function () {
                    return current_user_can('edit_theme_options');
                },
                'meta' => array('mcp' => array('public' => true)),
            )
        );
    }

    /**
     * Bir şablonu kaydeder veya günceller.
     *
     * - post_id sağlanırsa: mevcut wp_template gönderisi wp_update_post() ile güncellenir.
     * - slug sağlanırsa: tema dosyasındaki şablon ilk kez veritabanına kopyalanır
     *   (wp_insert_post), böylece tema güncellemelerinden bağımsız bir DB kaydı oluşur.
     * - Her iki parametre aynı anda verilirse hata döner.
     * - Blok dizisi serialize_block() ile serileştirilir; boş sonuç hata döndürür.
     *
     * @param array{
     *     blocks:   array<int, array<string, mixed>>,
     *     post_id?: int,
     *     slug?:    string,
     * } $input Yetenek giriş parametreleri.
     *
     * @return array{
     *     success:  bool,
     *     post_id?: int,
     *     error?:   string,
     * }
     */
    public function update_template(array $input): array
    {
        $blocks = $input['blocks'];

        // Check if both post_id and slug are provided
        if (! empty($input['post_id']) && ! empty($input['slug'])) {
            return array(
                'success' => false,
                'error'   => __('Provide either post_id or slug, not both. Post_id for update, slug for create.', 'block-design-abilities'),
            );
        }

        // If post_id is provided, update directly
        // otherwise save to DB for the first time using slug (overrides the theme file)
        if (! empty($input['post_id'])) {
            $post_id = absint($input['post_id']);
            $post    = get_post($post_id);

            if (! $post || $post->post_type !== 'wp_template') {
                return array(
                    'success' => false,
                    'error'   => sprintf(
                        __('Template with post_id %d not found.', 'block-design-abilities'),
                        $post_id
                    ),
                );
            }

            $previous_content = $post->post_content;
        } elseif (! empty($input['slug'])) {
            // Template from theme file — not yet saved to DB
            // Read the existing content first
            $template_id = get_stylesheet() . '//' . sanitize_title($input['slug']);
            $template    = get_block_template($template_id, 'wp_template');

            if (! $template) {
                return array(
                    'success' => false,
                    'error'   => sprintf(
                        __('Template "%s" not found.', 'block-design-abilities'),
                        $input['slug']
                    ),
                );
            }

            $previous_content = $template->content;
            $post_id          = null; // Will be created below with wp_insert_post

        } else {
            return array(
                'success' => false,
                'error'   => __('Either post_id or slug must be provided.', 'block-design-abilities'),
            );
        }

        // Serialize the block array
        $serialized_content = '';
        foreach ($blocks as $block) {
            $serialized_content .= serialize_block($block);
        }

        if (empty(trim($serialized_content))) {
            return array(
                'success' => false,
                'error'   => __('Block serialization failed.', 'block-design-abilities'),
            );
        }

        if ($post_id) {
            // Update the existing DB record
            $result = wp_update_post(array(
                'ID'           => $post_id,
                'post_content' => $serialized_content,
            ));

            if (is_wp_error($result)) {
                return array('success' => false, 'error' => $result->get_error_message());
            }
        } else {
            // Create a DB record that overrides the theme file for the first time
            $slug    = sanitize_title($input['slug']);
            $post_id = wp_insert_post(array(
                'post_type'    => 'wp_template',
                'post_status'  => 'publish',
                'post_name'    => $slug,
                'post_title'   => $template->title,
                'post_content' => $serialized_content,
                'tax_input'    => array(
                    'wp_theme' => array(get_stylesheet()),
                ),
            ));

            if (is_wp_error($post_id)) {
                return array('success' => false, 'error' => $post_id->get_error_message());
            }
        }

        return array(
            'success'            => true,
            'post_id'            => $post_id
        );
    }
}
