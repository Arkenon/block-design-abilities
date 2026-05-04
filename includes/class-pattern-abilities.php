<?php
defined('ABSPATH') || exit;

/**
 * Block Design Abilities – Pattern abilities.
 *
 * Registers five pattern-related abilities to the WordPress Abilities API:
 * list-patterns, get-pattern, update-pattern, duplicate-pattern, create-pattern.
 *
 * Supported source types:
 *  - registry : Read-only patterns registered via PHP by the theme/plugin.
 *  - database : Editable patterns stored in the database as wp_block CPT.
 *
 * @package Block_Design_Abilities
 * @since   1.0.0
 */
class Block_Design_Abilities_Patterns
{
    /**
     * Initializes the class; binds all ability registration methods
     * listening to the wp_abilities_api_init hook.
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
     * Registers the 'block-design-abilities/list-patterns' ability to the Abilities API.
     *
     * The ability accepts source, category, and search parameters,
     * and returns registry and database pattern lists as results.
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
     * Returns the pattern list.
     *
     * Collects patterns from WP_Block_Patterns_Registry (registry) and/or
     * wp_block CPT query (database) based on the source type. Registry patterns
     * prefixed with "core/" are always excluded.
     *
     * @param array{
     *     source?:   string,
     *     category?: string,
     *     search?:   string,
     * } $input Ability input parameters.
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
     * Registers the 'block-design-abilities/get-pattern' ability to the Abilities API.
     *
     * The ability accepts source (required), slug, or post_id parameters,
     * and returns the parsed block array as a result.
     *
     * @return void
     */
    public function register_get_pattern_ability()
    {
        wp_register_ability(
            'block-design-abilities/get-pattern',
            array(
                'label'       => __('Get Pattern', 'block-design-abilities'),
                'description' => __('Returns a pattern\'s raw block markup as html. source="registry": fetch by slug (read-only). source="database": fetch by post_id (editable via update-pattern). The returned html can be modified and passed straight back to update-pattern as the html parameter.', 'block-design-abilities'),
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
                        'html'        => array('type' => 'string'),
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
     * Returns a single pattern as raw block markup (html).
     *
     * Fetches from WP_Block_Patterns_Registry if source="registry" with slug,
     * or from wp_block post if source="database" with post_id.
     * The raw serialized block markup is returned as-is so it can be passed
     * back to update-pattern as the html parameter for round-trip editing.
     *
     * @param array{
     *     source:   string,
     *     slug?:    string,
     *     post_id?: int,
     * } $input Ability input parameters.
     *
     * @return array{
     *     success:      bool,
     *     source?:      string,
     *     slug?:        string,
     *     post_id?:     int,
     *     title?:       string,
     *     sync_status?: string,
     *     is_editable?: bool,
     *     html?:        string,
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

            return array(
                'success'     => true,
                'source'      => 'registry',
                'slug'        => $pattern['name'],
                'title'       => $pattern['title'],
                'is_editable' => false,
                'html'        => $pattern['content'],
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
                'html'        => $post->post_content,
            );
        }

        return array('success' => false, 'error' => __('Invalid source. Must be "registry" or "database".', 'block-design-abilities'));
    }

    /**
     * Registers the 'block-design-abilities/update-pattern' ability to the Abilities API.
     *
     * The ability accepts post_id and blocks (required), and title (optional)
     * parameters. Only posts of type wp_block can be updated;
     * registry patterns are read-only.
     *
     * @return void
     */
    public function register_update_pattern_ability()
    {
        wp_register_ability(
            'block-design-abilities/update-pattern',
            array(
                'label'       => __('Update Pattern', 'block-design-abilities'),
                'description' => __('Updates a database pattern (wp_block). Provide post_id and html. The html is converted to blocks server-side, avoiding innerHTML/attributes validation errors.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'     => 'object',
                    'required' => array('post_id', 'html'),
                    'properties' => array(

                        'post_id' => array(
                            'type'        => 'integer',
                            'description' => __('wp_block post ID from list-patterns or get-pattern.', 'block-design-abilities'),
                        ),

                        'html' => array(
                            'type'        => 'string',
                            'description' => __('Pattern markup. Accepts raw HTML (will be converted to blocks) or serialized block markup returned by get-pattern (round-trip). Replaces existing content entirely.', 'block-design-abilities'),
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
     * Updates a pattern (wp_block) in the database.
     *
     * The html input is converted to blocks via Block_Design_Abilities::resolve_blocks()
     * (which routes serialized block markup through parse_blocks() and raw HTML through
     * the html-to-blocks converter), then re-serialized and saved as post_content.
     * Returns an error if serialization results in empty content.
     *
     * @param array{
     *     post_id: int,
     *     html:    string,
     *     title?:  string,
     * } $input Ability input parameters.
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

        $post = get_post($post_id);

        if (! $post || $post->post_type !== 'wp_block') {
            return array(
                'success' => false,
                'error'   => sprintf(__('Database pattern with post_id %d not found. Only wp_block posts can be updated. Registry patterns are read-only.', 'my-plugin'), $post_id),
            );
        }

        $blocks = Block_Design_Abilities::resolve_blocks($input);
        if (is_wp_error($blocks)) {
            return array('success' => false, 'error' => $blocks->get_error_message());
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
     * Registers the 'block-design-abilities/duplicate-pattern' ability to the Abilities API.
     *
     * The ability accepts slug (required), title, and sync_status (optional)
     * parameters. Makes a registry pattern editable by copying it to a wp_block post.
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
                        'success'       => array('type' => 'boolean'),
                        'post_id'       => array('type' => 'integer'),
                        'title'         => array('type' => 'string'),
                        'slug'          => array('type' => 'string'),
                        'sync_status'   => array('type' => 'string'),
                        'original_slug' => array('type' => 'string'),
                        'html'          => array('type' => 'string'),
                        'error'         => array('type' => 'string'),
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
     * Copies a read-only registry pattern into the database (wp_block).
     *
     * During copying, the original content is transferred as is; if no title
     * is specified, " (Copy)" is appended to the original title. Categories
     * are assigned to the wp_pattern_category taxonomy. sync_status meta value
     * is saved as "unsynced" if requested.
     *
     * @param array{
     *     slug:         string,
     *     title?:       string,
     *     sync_status?: string,
     * } $input Ability input parameters.
     *
     * @return array{
     *     success?:       bool,
     *     post_id?:       int,
     *     title?:         string,
     *     slug?:          string,
     *     sync_status?:   string,
     *     original_slug? : string,
     *     html?:          string,
     *     error?:         string,
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
            'success'       => true,
            'post_id'       => $post_id,
            'title'         => $title,
            'slug'          => get_post($post_id)->post_name,
            'sync_status'   => $sync_status,
            'original_slug' => $slug,
            'html'          => $content,
        );
    }

    /**
     * Registers the 'block-design-abilities/create-pattern' ability to the Abilities API.
     *
     * The ability accepts title and blocks (required), description, categories, and
     * sync_status (optional) parameters. Created pattern appears in
     * Site Editor > "My Patterns" section.
     *
     * @return void
     */
    public function register_create_pattern_ability()
    {
        wp_register_ability(
            'block-design-abilities/create-pattern',
            array(
                'label'       => __('Create Pattern', 'block-design-abilities'),
                'description' => __('Creates a new wp_block pattern from scratch. Provide title and html. The pattern appears in Site Editor under "My Patterns".', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'     => 'object',
                    'required' => array('title', 'html'),
                    'properties' => array(

                        'title' => array(
                            'type'        => 'string',
                            'description' => __('Pattern title (e.g. "Pricing Table").', 'block-design-abilities'),
                        ),

                        'description' => array(
                            'type'        => 'string',
                            'description' => __('Optional. Short description.', 'block-design-abilities'),
                        ),

                        'html' => array(
                            'type'        => 'string',
                            'description' => __('Pattern markup. Accepts raw HTML (will be converted to blocks) or serialized block markup (round-trip from get-pattern). Wrap in a single root element (e.g. core/group when round-tripping) for a self-contained unit.', 'block-design-abilities'),
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
                        'success'     => array('type' => 'boolean'),
                        'post_id'     => array('type' => 'integer'),
                        'title'       => array('type' => 'string'),
                        'slug'        => array('type' => 'string'),
                        'sync_status' => array('type' => 'string'),
                        'categories'  => array('type' => 'array'),
                        'html'        => array('type' => 'string'),
                        'error'       => array('type' => 'string'),
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
     * Creates a new wp_block pattern from scratch.
     *
     * The html input is converted to blocks via Block_Design_Abilities::resolve_blocks(),
     * re-serialized with serialize_block() and saved with wp_insert_post(). If the
     * specified category slugs do not exist in the wp_pattern_category taxonomy,
     * they are created automatically. Returns an error if serialization results
     * in empty content.
     *
     * @param array{
     *     title:        string,
     *     html:         string,
     *     description?: string,
     *     categories?:  string[],
     *     sync_status?: string,
     * } $input Ability input parameters.
     *
     * @return array{
     *     success?:     bool,
     *     post_id?:     int,
     *     title?:       string,
     *     slug?:        string,
     *     sync_status?: string,
     *     categories?:  string[],
     *     html?:        string,
     *     error?:       string,
     * }
     */
    public function create_pattern(array $input): array
    {
        $title       = sanitize_text_field($input['title']);
        $description = isset($input['description']) ? sanitize_text_field($input['description']) : '';
        $categories  = isset($input['categories']) ? array_map('sanitize_text_field', $input['categories']) : array();
        $sync_status = (isset($input['sync_status']) && $input['sync_status'] === 'synced')
            ? 'synced'
            : 'unsynced';

        $blocks = Block_Design_Abilities::resolve_blocks($input);
        if (is_wp_error($blocks)) {
            return array('success' => false, 'error' => $blocks->get_error_message());
        }

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
                    // Create category if it doesn't exist
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
            'success'     => true,
            'post_id'     => $post_id,
            'title'       => $title,
            'slug'        => get_post($post_id)->post_name,
            'sync_status' => $sync_status,
            'categories'  => $categories,
            'html'        => $serialized_content,
        );
    }
}
