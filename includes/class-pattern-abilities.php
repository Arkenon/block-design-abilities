<?php

class Block_Design_Abilities_Patterns
{
    public function __construct()
    {
        add_action('wp_abilities_api_init', array($this, 'register_list_patterns_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_get_pattern_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_update_pattern_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_duplicate_pattern_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_create_pattern_ability'));
    }

    public function register_list_patterns_ability()
    {
        wp_register_ability(
            'block-design-abilities/list-patterns',
            array(
                'label'       => __('List Patterns', 'block-design-abilities'),
                'description' => __('Returns all available block patterns from two sources: (1) theme/plugin-registered patterns from the pattern registry (source: "theme" or "plugin"), and (2) user-created patterns stored in the database as wp_block posts (source: "user"). Registry patterns are identified by their slug (name). DB patterns are identified by their post_id. Use get-pattern with the appropriate identifier to retrieve full block content.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(

                        'source' => array(
                            'type'        => 'string',
                            'enum'        => array('all', 'registry', 'database'),
                            'description' => __('"all" (default) returns both registry and DB patterns. "registry" returns only theme/plugin patterns. "database" returns only user-created wp_block patterns.', 'block-design-abilities'),
                        ),

                        'category' => array(
                            'type'        => 'string',
                            'description' => __('Optional. Filter registry patterns by category slug (e.g. "featured", "text", "header"). Has no effect on database patterns.', 'block-design-abilities'),
                        ),

                        'search' => array(
                            'type'        => 'string',
                            'description' => __('Optional. Filter results by keyword in title. Applied to both sources.', 'block-design-abilities'),
                        ),

                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(

                        'success' => array(
                            'type'        => 'boolean',
                            'description' => __('Whether the retrieval was successful.', 'block-design-abilities'),
                        ),

                        'registry_patterns' => array(
                            'type'        => 'array',
                            'description' => __('Patterns registered by the active theme or plugins. These cannot be directly edited and saved back — they are read-only from a DB perspective. To "edit" one, you would duplicate it into a DB pattern. Use the "name" (slug) field with get-pattern.', 'block-design-abilities'),
                            'items'       => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'name'        => array('type' => 'string',  'description' => __('Unique slug. Use this when calling get-pattern.', 'block-design-abilities')),
                                    'title'       => array('type' => 'string',  'description' => __('Human-readable title.', 'block-design-abilities')),
                                    'description' => array('type' => 'string',  'description' => __('Short description.', 'block-design-abilities')),
                                    'source'      => array('type' => 'string',  'description' => __('"theme" or "plugin".', 'block-design-abilities')),
                                    'categories'  => array('type' => 'array', 'items' => array('type' => 'string'), 'description' => __('Category slugs.', 'block-design-abilities')),
                                ),
                            ),
                        ),

                        'database_patterns' => array(
                            'type'        => 'array',
                            'description' => __('User-created patterns stored in the database (wp_block post type). These can be edited and updated. Use post_id with get-pattern and update-pattern.', 'block-design-abilities'),
                            'items'       => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'post_id'     => array('type' => 'integer', 'description' => __('DB post ID. Use this when calling get-pattern or update-pattern.', 'block-design-abilities')),
                                    'title'       => array('type' => 'string',  'description' => __('Pattern title.', 'block-design-abilities')),
                                    'post_name'   => array('type' => 'string',  'description' => __('URL slug.', 'block-design-abilities')),
                                    'sync_status' => array('type' => 'string',  'description' => __('"synced" or "unsynced". Synced patterns share content across all uses; unsynced are independent copies.', 'block-design-abilities')),
                                    'categories'  => array('type' => 'array', 'items' => array('type' => 'string'), 'description' => __('wp_pattern_category taxonomy term slugs.', 'block-design-abilities')),
                                    'modified'    => array('type' => 'string',  'description' => __('Last modified date.', 'block-design-abilities')),
                                ),
                            ),
                        ),

                        'totals' => array(
                            'type'        => 'object',
                            'properties'  => array(
                                'registry' => array('type' => 'integer', 'description' => __('Number of registry patterns returned.', 'block-design-abilities')),
                                'database' => array('type' => 'integer', 'description' => __('Number of database patterns returned.', 'block-design-abilities')),
                            ),
                        ),

                        'error' => array('type' => 'string'),
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

    public function register_get_pattern_ability()
    {
        wp_register_ability(
            'block-design-abilities/get-pattern',
            array(
                'label'       => __('Get Pattern', 'block-design-abilities'),
                'description' => __('Retrieves a single block pattern and returns its parsed block array. For database patterns (source: "database"), provide post_id. For registry patterns (source: "registry"), provide slug (name). Only database patterns can be updated with update-pattern — registry patterns are read-only theme/plugin files.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'required'   => array('source'),
                    'properties' => array(

                        'source' => array(
                            'type'        => 'string',
                            'enum'        => array('registry', 'database'),
                            'description' => __('"registry" to fetch a theme/plugin pattern by slug. "database" to fetch a user-created wp_block pattern by post_id.', 'block-design-abilities'),
                        ),

                        'slug' => array(
                            'type'        => 'string',
                            'description' => __('Required when source is "registry". The pattern name/slug (e.g. "mytheme/hero"). Obtain from list-patterns.', 'block-design-abilities'),
                        ),

                        'post_id' => array(
                            'type'        => 'integer',
                            'description' => __('Required when source is "database". The wp_block post ID. Obtain from list-patterns.', 'block-design-abilities'),
                        ),

                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success'     => array('type' => 'boolean'),
                        'source'      => array('type' => 'string', 'description' => __('"registry" or "database".', 'block-design-abilities')),
                        'post_id'     => array('type' => 'integer', 'description' => __('Only present for database patterns. Use this when calling update-pattern.', 'block-design-abilities')),
                        'slug'        => array('type' => 'string',  'description' => __('Pattern slug/name.', 'block-design-abilities')),
                        'title'       => array('type' => 'string'),
                        'sync_status' => array('type' => 'string',  'description' => __('"synced" or "unsynced". Only for database patterns.', 'block-design-abilities')),
                        'is_editable' => array('type' => 'boolean', 'description' => __('Whether this pattern can be updated. True only for database patterns.', 'block-design-abilities')),
                        'raw_content' => array('type' => 'string',  'description' => __('Raw serialized block markup. For reference only.', 'block-design-abilities')),
                        'blocks'      => array(
                            'type'        => 'array',
                            'description' => __('Parsed block array. For database patterns, edit this and pass to update-pattern. For registry patterns, this is read-only.', 'block-design-abilities'),
                            'items'       => array('type' => 'object'),
                        ),
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
                'raw_content' => $raw_content,
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
                'raw_content' => $raw_content,
                'blocks'      => $parsed_blocks,
                'block_count' => count($parsed_blocks),
            );
        }

        return array('success' => false, 'error' => __('Invalid source. Must be "registry" or "database".', 'block-design-abilities'));
    }

    public function register_update_pattern_ability()
    {
        wp_register_ability(
            'block-design-abilities/update-pattern',
            array(
                'label'       => __('Update Pattern', 'block-design-abilities'),
                'description' => __('Updates an existing database pattern (wp_block post type). Only user-created database patterns can be updated — registry patterns from themes/plugins are read-only files. Always call get-pattern first (source: "database") to retrieve the current block structure, edit it, then call this ability with the modified blocks array.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'     => 'object',
                    'required' => array('post_id', 'blocks'),
                    'properties' => array(

                        'post_id' => array(
                            'type'        => 'integer',
                            'description' => __('The wp_block post ID of the pattern to update. Obtain from list-patterns or get-pattern.', 'my-plugin'),
                        ),

                        'blocks' => array(
                            'type'        => 'array',
                            'description' => __('The full updated block array. Replaces existing content entirely. Use blocks from get-pattern as your starting point.', 'my-plugin'),
                            'items'       => array('type' => 'object'),
                        ),

                        'title' => array(
                            'type'        => 'string',
                            'description' => __('Optional. If provided, updates the pattern title as well.', 'my-plugin'),
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
                        'previous_content'   => array('type' => 'string', 'description' => __('Raw content before update, for rollback reference.', 'my-plugin')),
                        'serialized_content' => array('type' => 'string', 'description' => __('New serialized content saved to DB.', 'my-plugin')),
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
            'sync_status'        => $sync_status,
            'previous_content'   => $previous_content,
            'serialized_content' => $serialized_content,
        );
    }

    public function register_duplicate_pattern_ability()
    {
        wp_register_ability(
            'block-design-abilities/duplicate-pattern',
            array(
                'label'       => __('Duplicate Pattern', 'block-design-abilities'),
                'description' => __('Creates a database copy of a registry (theme/plugin) pattern, making it editable. Use this when you want to edit a theme pattern — registry patterns are read-only files, so you must duplicate them first. The duplicate is saved as a wp_block post and can then be modified with update-pattern. Workflow: list-patterns → get-pattern (source: "registry") → duplicate-pattern → update-pattern.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'     => 'object',
                    'required' => array('slug'),
                    'properties' => array(

                        'slug' => array(
                            'type'        => 'string',
                            'description' => __('The slug/name of the registry pattern to duplicate (e.g. "mytheme/hero"). Obtain from list-patterns.', 'block-design-abilities'),
                        ),

                        'title' => array(
                            'type'        => 'string',
                            'description' => __('Optional. Custom title for the duplicate. If omitted, defaults to the original title with " (Copy)" appended.', 'block-design-abilities'),
                        ),

                        'sync_status' => array(
                            'type'        => 'string',
                            'enum'        => array('synced', 'unsynced'),
                            'description' => __('Whether the duplicated pattern should be synced or unsynced. Default is "unsynced". Synced patterns share content across all uses; unsynced patterns are independent copies per insertion.', 'block-design-abilities'),
                        ),

                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success'            => array('type' => 'boolean'),
                        'post_id'            => array('type' => 'integer', 'description' => __('DB post ID of the newly created duplicate. Use this with update-pattern.', 'block-design-abilities')),
                        'title'              => array('type' => 'string'),
                        'slug'               => array('type' => 'string',  'description' => __('Auto-generated URL slug for the new DB pattern.', 'block-design-abilities')),
                        'sync_status'        => array('type' => 'string'),
                        'original_slug'      => array('type' => 'string',  'description' => __('The registry slug this was duplicated from.', 'block-design-abilities')),
                        'serialized_content' => array('type' => 'string',  'description' => __('The block markup copied from the original pattern.', 'block-design-abilities')),
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

    public function register_create_pattern_ability()
    {
        wp_register_ability(
            'block-design-abilities/create-pattern',
            array(
                'label'       => __('Create Pattern', 'block-design-abilities'),
                'description' => __('Creates a new block pattern from scratch and saves it to the database as a wp_block post. Construct the blocks array yourself based on the user\'s requirements (e.g. "a pricing table with 3 tiers", "a hero section with heading and CTA button"). Use get-theme-json first to know available color slugs, font sizes, and spacing tokens so your block attrs reference correct preset values. The pattern will appear in the Site Editor under "My Patterns".', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'     => 'object',
                    'required' => array('title', 'blocks'),
                    'properties' => array(

                        'title' => array(
                            'type'        => 'string',
                            'description' => __('Human-readable title for the pattern (e.g. "Pricing Table", "Hero with CTA").', 'block-design-abilities'),
                        ),

                        'description' => array(
                            'type'        => 'string',
                            'description' => __('Optional. Short description of what this pattern is for.', 'block-design-abilities'),
                        ),

                        'blocks' => array(
                            'type'        => 'array',
                            'description' => __('Block array that makes up the pattern. Each block must follow the WP_Block_Parser_Block structure: blockName, attrs, innerBlocks, innerHTML, innerContent. Wrap everything in a core/group block so the pattern is self-contained and moveable as a unit.', 'block-design-abilities'),
                            'items'       => array(
                                'type'     => 'object',
                                'required' => array('blockName', 'attrs', 'innerBlocks', 'innerHTML', 'innerContent'),
                                'properties' => array(
                                    'blockName'    => array('type' => 'string', 'description' => __('Block name (e.g. "core/group", "core/heading", "core/columns").', 'block-design-abilities')),
                                    'attrs'        => array('type' => 'object', 'description' => __('Block attributes (e.g. {"align":"wide","backgroundColor":"primary"}).', 'block-design-abilities')),
                                    'innerBlocks'  => array('type' => 'array', 'items' => array('type' => 'object'), 'description' => __('Nested child blocks, same structure recursively.', 'block-design-abilities')),
                                    'innerHTML'    => array('type' => 'string', 'description' => __('Raw HTML inside the block comment delimiters.', 'block-design-abilities')),
                                    'innerContent' => array('type' => 'array', 'items' => array(), 'description' => __('Ordered string fragments and null markers for inner block injection.', 'block-design-abilities')),
                                ),
                            ),
                        ),

                        'categories' => array(
                            'type'        => 'array',
                            'items'       => array('type' => 'string'),
                            'description' => __('Optional. wp_pattern_category taxonomy slugs to assign (e.g. ["featured", "cta"]). If a slug does not exist it will be created automatically.', 'block-design-abilities'),
                        ),

                        'sync_status' => array(
                            'type'        => 'string',
                            'enum'        => array('synced', 'unsynced'),
                            'description' => __('Whether the pattern should be synced or unsynced. Default is "unsynced". Use "synced" only if the pattern should behave as a shared component updated everywhere when changed.', 'block-design-abilities'),
                        ),

                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success'            => array('type' => 'boolean'),
                        'post_id'            => array('type' => 'integer', 'description' => __('DB post ID of the newly created pattern.', 'block-design-abilities')),
                        'title'              => array('type' => 'string'),
                        'slug'               => array('type' => 'string'),
                        'sync_status'        => array('type' => 'string'),
                        'categories'         => array('type' => 'array', 'items' => array('type' => 'string')),
                        'serialized_content' => array('type' => 'string', 'description' => __('The block markup saved to the database.', 'block-design-abilities')),
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
