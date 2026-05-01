<?php
defined('ABSPATH') || exit;

/**
 * Block Design Abilities – Pattern yetenekleri.
 *
 * WordPress Abilities API'ye pattern ile ilgili beş yetenek kaydeder:
 * list-patterns, get-pattern, update-pattern, duplicate-pattern, create-pattern.
 *
 * Desteklenen kaynak türleri:
 *  - registry : Tema/eklenti tarafından PHP ile kaydedilen, salt okunur pattern'ler.
 *  - database  : wp_block CPT olarak veritabanında depolanan, düzenlenebilir pattern'ler.
 *
 * @package Block_Design_Abilities
 * @since   1.0.0
 */
class Block_Design_Abilities_Patterns
{
    /**
     * Sınıfı başlatır; wp_abilities_api_init kancasını dinleyen tüm
     * yetenek kayıt metodlarını bağlar.
     */
    public function __construct()
    {
        add_action('wp_abilities_api_init', array($this, 'register_list_patterns_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_get_pattern_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_update_pattern_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_duplicate_pattern_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_create_pattern_ability'));
    }

    /**
     * 'block-design-abilities/list-patterns' yeteneğini Abilities API'ye kaydeder.
     *
     * Yetenek; source, category ve search parametrelerini kabul eder,
     * sonuç olarak registry ve database pattern listelerini döndürür.
     *
     * @return void
     */
    public function register_list_patterns_ability()
    {
        wp_register_ability(
            'block-design-abilities/list-patterns',
            array(
                'label'       => __('List Patterns', 'block-design-abilities'),
                'description' => __('Returns block patterns from two sources: registry (theme/plugin, read-only, identified by slug) and database (wp_block posts, editable, identified by post_id). Use get-pattern to retrieve full content.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(

                        'source' => array(
                            'type'        => 'string',
                            'enum'        => array('all', 'registry', 'database'),
                            'description' => __('"all" (default), "registry", or "database".', 'block-design-abilities'),
                        ),

                        'category' => array(
                            'type'        => 'string',
                            'description' => __('Filter registry patterns by category slug.', 'block-design-abilities'),
                        ),

                        'search' => array(
                            'type'        => 'string',
                            'description' => __('Filter by keyword in title.', 'block-design-abilities'),
                        ),

                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success'           => array('type' => 'boolean'),
                        'registry_patterns' => array('type' => 'array'),
                        'database_patterns' => array('type' => 'array'),
                        'totals'            => array('type' => 'object'),
                        'error'             => array('type' => 'string'),
                    ),
                ),

                'execute_callback'    => array($this, 'list_patterns'),
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
                'meta' => array('mcp' => array('public' => true)),
            )
        );
    }

    /**
     * Pattern listesini döndürür.
     *
     * Kaynak türüne göre WP_Block_Patterns_Registry'den (registry) ve/veya
     * wp_block CPT sorgusundan (database) pattern'leri toplar. "core/" önekli
     * registry pattern'leri her zaman hariç tutulur.
     *
     * @param array{
     *     source?:   string,
     *     category?: string,
     *     search?:   string,
     * } $input Yetenek giriş parametreleri.
     *
     * @return array{
     *     success:           bool,
     *     registry_patterns: array<int, array<string, mixed>>,
     *     database_patterns: array<int, array<string, mixed>>,
     *     totals:            array{registry: int, database: int},
     * }
     */
    public function list_patterns(array $input = array()): array
    {
        $source   = isset($input['source']) ? $input['source'] : 'all';
        $category = isset($input['category']) ? sanitize_text_field($input['category']) : '';
        $search   = isset($input['search']) ? strtolower(sanitize_text_field($input['search'])) : '';

        $registry_patterns = array();
        $database_patterns = array();


        if (in_array($source, array('all', 'registry'), true)) {
            $all_registered = WP_Block_Patterns_Registry::get_instance()->get_all_registered();

            foreach ($all_registered as $pattern) {

                if ($category && (empty($pattern['categories']) || ! in_array($category, $pattern['categories'], true))) {
                    continue;
                }

                if ($search && strpos(strtolower($pattern['title']), $search) === false) {
                    continue;
                }
                if (strpos($pattern['name'], 'core/') === 0) {
                    continue;
                }

                $registry_patterns[] = array(
                    'name'        => $pattern['name'],
                    'title'       => $pattern['title'],
                    'description' => $pattern['description'] ?? '',
                    'source'      => $pattern['source'] ?? 'theme',
                    'categories'  => $pattern['categories'] ?? array(),
                );
            }
        }

        if (in_array($source, array('all', 'database'), true)) {
            $query_args = array(
                'post_type'      => 'wp_block',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            );

            if ($search) {
                $query_args['s'] = $search;
            }

            $db_posts = get_posts($query_args);

            foreach ($db_posts as $post) {
                $terms      = get_the_terms($post->ID, 'wp_pattern_category');
                $categories = ($terms && ! is_wp_error($terms))
                    ? wp_list_pluck($terms, 'slug')
                    : array();
                $sync_meta   = get_post_meta($post->ID, 'wp_pattern_sync_status', true);
                $sync_status = ($sync_meta === 'unsynced') ? 'unsynced' : 'synced';

                $database_patterns[] = array(
                    'post_id'     => $post->ID,
                    'title'       => $post->post_title,
                    'post_name'   => $post->post_name,
                    'sync_status' => $sync_status,
                    'categories'  => $categories,
                    'modified'    => $post->post_modified,
                );
            }
        }

        return array(
            'success'           => true,
            'registry_patterns' => $registry_patterns,
            'database_patterns' => $database_patterns,
            'totals'            => array(
                'registry' => count($registry_patterns),
                'database' => count($database_patterns),
            ),
        );
    }

    /**
     * 'block-design-abilities/get-pattern' yeteneğini Abilities API'ye kaydeder.
     *
     * Yetenek; source (zorunlu), slug veya post_id parametrelerini kabul eder,
     * sonuç olarak ayrıştırılmış blok dizisini döndürür.
     *
     * @return void
     */
    public function register_get_pattern_ability()
    {
        wp_register_ability(
            'block-design-abilities/get-pattern',
            array(
                'label'       => __('Get Pattern', 'block-design-abilities'),
                'description' => __('Returns a pattern\'s parsed block array. source="registry": fetch by slug (read-only). source="database": fetch by post_id (editable via update-pattern).', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'required'   => array('source'),
                    'properties' => array(

                        'source' => array(
                            'type'        => 'string',
                            'enum'        => array('registry', 'database'),
                            'description' => __('"registry" (fetch by slug) or "database" (fetch by post_id).', 'block-design-abilities'),
                        ),

                        'slug' => array(
                            'type'        => 'string',
                            'description' => __('Pattern slug from list-patterns. Required when source="registry".', 'block-design-abilities'),
                        ),

                        'post_id' => array(
                            'type'        => 'integer',
                            'description' => __('wp_block post ID from list-patterns. Required when source="database".', 'block-design-abilities'),
                        ),

                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success'     => array('type' => 'boolean'),
                        'source'      => array('type' => 'string'),
                        'post_id'     => array('type' => 'integer'),
                        'slug'        => array('type' => 'string'),
                        'title'       => array('type' => 'string'),
                        'sync_status' => array('type' => 'string'),
                        'is_editable' => array('type' => 'boolean'),
                        'blocks'      => array('type' => 'array'),
                        'block_count' => array('type' => 'integer'),
                        'error'       => array('type' => 'string'),
                    ),
                ),

                'execute_callback'    => array($this, 'get_pattern'),
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
                'meta' => array('mcp' => array('public' => true)),
            )
        );
    }

    /**
     * Tek bir pattern'i ayrıştırılmış blok dizisi olarak döndürür.
     *
     * source="registry" ise slug ile WP_Block_Patterns_Registry'den,
     * source="database" ise post_id ile wp_block gönderisinden çeker.
     * İçerik parse_blocks() ile ayrıştırılır; blockName değeri boş olan
     * düğümler (yorum blokları vb.) sonuçtan çıkarılır.
     *
     * @param array{
     *     source:   string,
     *     slug?:    string,
     *     post_id?: int,
     * } $input Yetenek giriş parametreleri.
     *
     * @return array{
     *     success:      bool,
     *     source?:      string,
     *     slug?:        string,
     *     post_id?:     int,
     *     title?:       string,
     *     sync_status?: string,
     *     is_editable?: bool,
     *     blocks?:      array<int, array<string, mixed>>,
     *     block_count?: int,
     *     error?:       string,
     * }
     */
    public function get_pattern(array $input): array
    {
        $source = $input['source'];

        // --- Registry pattern ---
        if ($source === 'registry') {
            if (empty($input['slug'])) {
                return array('success' => false, 'error' => __('slug is required when source is "registry".', 'block-design-abilities'));
            }

            $slug    = sanitize_text_field($input['slug']);
            $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered($slug);

            if (! $pattern) {
                return array(
                    'success' => false,
                    'error'   => sprintf(__('Registry pattern "%s" not found. Use list-patterns to see available patterns.', 'block-design-abilities'), $slug),
                );
            }

            $raw_content   = $pattern['content'];
            $parsed_blocks = array_values(array_filter(parse_blocks($raw_content), fn($b) => ! empty($b['blockName'])));

            return array(
                'success'     => true,
                'source'      => 'registry',
                'slug'        => $pattern['name'],
                'title'       => $pattern['title'],
                'is_editable' => false,
                'blocks'      => $parsed_blocks,
                'block_count' => count($parsed_blocks),
            );
        }

        // --- Database pattern ---
        if ($source === 'database') {
            if (empty($input['post_id'])) {
                return array('success' => false, 'error' => __('post_id is required when source is "database".', 'block-design-abilities'));
            }

            $post_id = absint($input['post_id']);
            $post    = get_post($post_id);

            if (! $post || $post->post_type !== 'wp_block') {
                return array(
                    'success' => false,
                    'error'   => sprintf(__('Database pattern with post_id %d not found.', 'block-design-abilities'), $post_id),
                );
            }

            $raw_content   = $post->post_content;
            $parsed_blocks = array_values(array_filter(parse_blocks($raw_content), fn($b) => ! empty($b['blockName'])));

            $sync_meta   = get_post_meta($post->ID, 'wp_pattern_sync_status', true);
            $sync_status = ($sync_meta === 'unsynced') ? 'unsynced' : 'synced';

            return array(
                'success'     => true,
                'source'      => 'database',
                'post_id'     => $post->ID,
                'slug'        => $post->post_name,
                'title'       => $post->post_title,
                'sync_status' => $sync_status,
                'is_editable' => true,
                'blocks'      => $parsed_blocks,
                'block_count' => count($parsed_blocks),
            );
        }

        return array('success' => false, 'error' => __('Invalid source. Must be "registry" or "database".', 'block-design-abilities'));
    }

    /**
     * 'block-design-abilities/update-pattern' yeteneğini Abilities API'ye kaydeder.
     *
     * Yetenek; post_id ve blocks (zorunlu), title (isteğe bağlı)
     * parametrelerini kabul eder. Yalnızca wp_block türündeki gönderiler
     * güncellenebilir; registry pattern'leri salt okunurdur.
     *
     * @return void
     */
    public function register_update_pattern_ability()
    {
        wp_register_ability(
            'block-design-abilities/update-pattern',
            array(
                'label'       => __('Update Pattern', 'block-design-abilities'),
                'description' => __('Updates a database pattern (wp_block). Call get-pattern (source="database") first, edit the blocks, then pass them here with post_id.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'     => 'object',
                    'required' => array('post_id', 'blocks'),
                    'properties' => array(

                        'post_id' => array(
                            'type'        => 'integer',
                            'description' => __('wp_block post ID from list-patterns or get-pattern.', 'block-design-abilities'),
                        ),

                        'blocks' => array(
                            'type'        => 'array',
                            'description' => __('Full updated block array from get-pattern. Replaces existing content entirely.', 'block-design-abilities'),
                            'items'       => array('type' => 'object'),
                        ),

                        'title' => array(
                            'type'        => 'string',
                            'description' => __('Optional. Updates the pattern title if provided.', 'block-design-abilities'),
                        ),

                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success'            => array('type' => 'boolean'),
                        'post_id'            => array('type' => 'integer'),
                        'title'              => array('type' => 'string'),
                        'sync_status'        => array('type' => 'string'),
                        'error'              => array('type' => 'string'),
                    ),
                ),

                'execute_callback'    => array($this, 'update_pattern'),
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
                'meta' => array('mcp' => array('public' => true)),
            )
        );
    }

    /**
     * Veritabanındaki bir pattern'i (wp_block) günceller.
     *
     * Gelen blok dizisi serialize_block() ile serileştirilir ve
     * wp_update_post() ile gönderi içeriğinin yerine yazılır.
     * Boş serileştirme sonucu hata döndürür.
     *
     * @param array{
     *     post_id: int,
     *     blocks:  array<int, array<string, mixed>>,
     *     title?:  string,
     * } $input Yetenek giriş parametreleri.
     *
     * @return array{
     *     success:      bool,
     *     post_id?:     int,
     *     title?:       string,
     *     sync_status?: string,
     *     error?:       string,
     * }
     */
    public function update_pattern(array $input): array
    {
        $post_id = absint($input['post_id']);
        $blocks  = $input['blocks'];

        $post = get_post($post_id);

        if (! $post || $post->post_type !== 'wp_block') {
            return array(
                'success' => false,
                'error'   => sprintf(__('Database pattern with post_id %d not found. Only wp_block posts can be updated. Registry patterns are read-only.', 'my-plugin'), $post_id),
            );
        }

        $previous_content = $post->post_content;

        $serialized_content = '';
        foreach ($blocks as $block) {
            $serialized_content .= serialize_block($block);
        }

        if (empty(trim($serialized_content))) {
            return array('success' => false, 'error' => __('Block serialization failed.', 'my-plugin'));
        }

        $update_args = array(
            'ID'           => $post_id,
            'post_content' => $serialized_content,
        );

        if (! empty($input['title'])) {
            $update_args['post_title'] = sanitize_text_field($input['title']);
        }

        $result = wp_update_post($update_args);

        if (is_wp_error($result)) {
            return array('success' => false, 'error' => $result->get_error_message());
        }

        $sync_meta   = get_post_meta($post_id, 'wp_pattern_sync_status', true);
        $sync_status = ($sync_meta === 'unsynced') ? 'unsynced' : 'synced';

        return array(
            'success'            => true,
            'post_id'            => $post_id,
            'title'              => get_post($post_id)->post_title,
            'sync_status'        => $sync_status
        );
    }

    /**
     * 'block-design-abilities/duplicate-pattern' yeteneğini Abilities API'ye kaydeder.
     *
     * Yetenek; slug (zorunlu), title ve sync_status (isteğe bağlı)
     * parametrelerini kabul eder. Registry pattern'ini bir wp_block gönderisine
     * kopyalayarak düzenlenebilir hale getirir.
     *
     * @return void
     */
    public function register_duplicate_pattern_ability()
    {
        wp_register_ability(
            'block-design-abilities/duplicate-pattern',
            array(
                'label'       => __('Duplicate Pattern', 'block-design-abilities'),
                'description' => __('Copies a read-only registry pattern into a database wp_block post, making it editable. Workflow: list-patterns → duplicate-pattern → update-pattern.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'     => 'object',
                    'required' => array('slug'),
                    'properties' => array(

                        'slug' => array(
                            'type'        => 'string',
                            'description' => __('Registry pattern slug from list-patterns.', 'block-design-abilities'),
                        ),

                        'title' => array(
                            'type'        => 'string',
                            'description' => __('Optional. Custom title. Defaults to original title + " (Copy)".', 'block-design-abilities'),
                        ),

                        'sync_status' => array(
                            'type'        => 'string',
                            'enum'        => array('synced', 'unsynced'),
                            'description' => __('Default "unsynced". "synced" = shared component updated everywhere when changed.', 'block-design-abilities'),
                        ),

                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success'            => array('type' => 'boolean'),
                        'post_id'            => array('type' => 'integer'),
                        'title'              => array('type' => 'string'),
                        'slug'               => array('type' => 'string'),
                        'sync_status'        => array('type' => 'string'),
                        'original_slug'      => array('type' => 'string'),
                        'serialized_content' => array('type' => 'string'),
                        'error'              => array('type' => 'string'),
                    ),
                ),

                'execute_callback'    => array($this, 'duplicate_pattern'),
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
                'meta' => array('mcp' => array('public' => true)),
            )
        );
    }

    /**
     * Salt okunur bir registry pattern'ini veritabanına (wp_block) kopyalar.
     *
     * Kopyalama sırasında orijinal içerik olduğu gibi aktarılır; başlık
     * belirtilmezse orijinal başlığa " (Copy)" eki eklenir. Kategoriler
     * wp_pattern_category taksonomisine atanır. sync_status meta değeri
     * istenirse "unsynced" olarak kaydedilir.
     *
     * @param array{
     *     slug:         string,
     *     title?:       string,
     *     sync_status?: string,
     * } $input Yetenek giriş parametreleri.
     *
     * @return array{
     *     success?:            bool,
     *     post_id?:            int,
     *     title?:              string,
     *     slug?:               string,
     *     sync_status?:        string,
     *     original_slug?:      string,
     *     serialized_content?: string,
     *     error?:              string,
     * }
     */
    public function duplicate_pattern(array $input): array
    {
        $slug = sanitize_text_field($input['slug']);

        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered($slug);

        if (! $pattern) {
            return array(
                'success' => false,
                'error'   => sprintf(
                    __('Registry pattern "%s" not found. Use list-patterns (source: "registry") to see available patterns.', 'block-design-abilities'),
                    $slug
                ),
            );
        }

        $title = ! empty($input['title'])
            ? sanitize_text_field($input['title'])
            : $pattern['title'] . __(' (Copy)', 'block-design-abilities');
        $sync_status = (isset($input['sync_status']) && $input['sync_status'] === 'synced')
            ? 'synced'
            : 'unsynced';

        $content = $pattern['content'];

        $post_id = wp_insert_post(array(
            'post_type'    => 'wp_block',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => $content,
        ));

        if (is_wp_error($post_id)) {
            return array('success' => false, 'error' => $post_id->get_error_message());
        }

        if ($sync_status === 'unsynced') {
            update_post_meta($post_id, 'wp_pattern_sync_status', 'unsynced');
        }

        if (! empty($pattern['categories'])) {
            wp_set_object_terms($post_id, $pattern['categories'], 'wp_pattern_category');
        }

        return array(
            'success'            => true,
            'post_id'            => $post_id,
            'title'              => $title,
            'slug'               => get_post($post_id)->post_name,
            'sync_status'        => $sync_status,
            'original_slug'      => $slug,
            'serialized_content' => $content,
        );
    }

    /**
     * 'block-design-abilities/create-pattern' yeteneğini Abilities API'ye kaydeder.
     *
     * Yetenek; title ve blocks (zorunlu), description, categories ve
     * sync_status (isteğe bağlı) parametrelerini kabul eder. Oluşturulan
     * pattern Site Editor > "My Patterns" bölümünde görünür.
     *
     * @return void
     */
    public function register_create_pattern_ability()
    {
        wp_register_ability(
            'block-design-abilities/create-pattern',
            array(
                'label'       => __('Create Pattern', 'block-design-abilities'),
                'description' => __('Creates a new wp_block pattern from scratch. Use get-theme-json first to get available color/font/spacing tokens. The pattern appears in Site Editor under "My Patterns".', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'     => 'object',
                    'required' => array('title', 'blocks'),
                    'properties' => array(

                        'title' => array(
                            'type'        => 'string',
                            'description' => __('Pattern title (e.g. "Pricing Table").', 'block-design-abilities'),
                        ),

                        'description' => array(
                            'type'        => 'string',
                            'description' => __('Optional. Short description.', 'block-design-abilities'),
                        ),

                        'blocks' => array(
                            'type'        => 'array',
                            'description' => __('Block array. Same WP_Block_Parser_Block structure as returned by get-pattern. Wrap in core/group for a self-contained unit.', 'block-design-abilities'),
                            'items'       => array('type' => 'object'),
                        ),

                        'categories' => array(
                            'type'        => 'array',
                            'items'       => array('type' => 'string'),
                            'description' => __('Optional. wp_pattern_category slugs. Non-existent slugs are created automatically.', 'block-design-abilities'),
                        ),

                        'sync_status' => array(
                            'type'        => 'string',
                            'enum'        => array('synced', 'unsynced'),
                            'description' => __('Default "unsynced". "synced" = shared component updated everywhere when changed.', 'block-design-abilities'),
                        ),

                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success'            => array('type' => 'boolean'),
                        'post_id'            => array('type' => 'integer'),
                        'title'              => array('type' => 'string'),
                        'slug'               => array('type' => 'string'),
                        'sync_status'        => array('type' => 'string'),
                        'categories'         => array('type' => 'array'),
                        'serialized_content' => array('type' => 'string'),
                        'error'              => array('type' => 'string'),
                    ),
                ),

                'execute_callback'    => array($this, 'create_pattern'),
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
                'meta' => array('mcp' => array('public' => true)),
            )
        );
    }

    /**
     * Sıfırdan yeni bir wp_block pattern oluşturur.
     *
     * Blok dizisi serialize_block() ile serileştirilir ve wp_insert_post()
     * ile veritabanına kaydedilir. Belirtilen kategori slug'ları
     * wp_pattern_category taksonomisinde yoksa otomatik olarak oluşturulur.
     * Boş serileştirme sonucu hata döndürür.
     *
     * @param array{
     *     title:        string,
     *     blocks:       array<int, array<string, mixed>>,
     *     description?: string,
     *     categories?:  string[],
     *     sync_status?: string,
     * } $input Yetenek giriş parametreleri.
     *
     * @return array{
     *     success?:            bool,
     *     post_id?:            int,
     *     title?:              string,
     *     slug?:               string,
     *     sync_status?:        string,
     *     categories?:         string[],
     *     serialized_content?: string,
     *     error?:              string,
     * }
     */
    public function create_pattern(array $input): array
    {
        $title       = sanitize_text_field($input['title']);
        $blocks      = $input['blocks'];
        $description = isset($input['description']) ? sanitize_text_field($input['description']) : '';
        $categories  = isset($input['categories']) ? array_map('sanitize_text_field', $input['categories']) : array();
        $sync_status = (isset($input['sync_status']) && $input['sync_status'] === 'synced')
            ? 'synced'
            : 'unsynced';

        $serialized_content = '';
        foreach ($blocks as $block) {
            $serialized_content .= serialize_block($block);
        }

        if (empty(trim($serialized_content))) {
            return array('success' => false, 'error' => __('Block serialization failed. Check your block structure.', 'block-design-abilities'));
        }

        $post_id = wp_insert_post(array(
            'post_type'    => 'wp_block',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => $serialized_content,
            'post_excerpt' => $description,
        ));

        if (is_wp_error($post_id)) {
            return array('success' => false, 'error' => $post_id->get_error_message());
        }

        if ($sync_status === 'unsynced') {
            update_post_meta($post_id, 'wp_pattern_sync_status', 'unsynced');
        }
        if (! empty($categories)) {
            $term_ids = array();
            foreach ($categories as $cat_slug) {
                $term = get_term_by('slug', $cat_slug, 'wp_pattern_category');
                if (! $term) {
                    // Kategori yoksa oluştur
                    $new_term = wp_insert_term(
                        ucwords(str_replace('-', ' ', $cat_slug)),
                        'wp_pattern_category',
                        array('slug' => $cat_slug)
                    );
                    if (! is_wp_error($new_term)) {
                        $term_ids[] = $new_term['term_id'];
                    }
                } else {
                    $term_ids[] = $term->term_id;
                }
            }
            if (! empty($term_ids)) {
                wp_set_object_terms($post_id, $term_ids, 'wp_pattern_category');
            }
        }

        return array(
            'success'            => true,
            'post_id'            => $post_id,
            'title'              => $title,
            'slug'               => get_post($post_id)->post_name,
            'sync_status'        => $sync_status,
            'categories'         => $categories,
            'serialized_content' => $serialized_content,
        );
    }
}
