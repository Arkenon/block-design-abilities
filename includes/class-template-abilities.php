<?php
defined('ABSPATH') || exit;

/**
 * Block Design Abilities – Template abilities.
 *
 * Registers three abilities for block theme template management to the WordPress Abilities API:
 * list-templates, get-template, add-or-update-template.
 *
 * Templates can come from two sources:
 *  - Theme file : Not yet saved to the database, accessed via slug.
 *  - Database   : Registered as wp_template CPT, updated via post_id.
 *
 * Permission requirement: edit_theme_options.
 *
 * @package Block_Design_Abilities
 * @since   1.0.0
 */
class Block_Design_Abilities_Templates
{
    /**
     * Initializes the class; binds all ability registration methods
     * listening to the wp_abilities_api_init hook.
     */
    public function __construct()
    {
        add_action('wp_abilities_api_init', array($this, 'register_list_templates_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_get_template_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_update_template_ability'));
    }

    /**
     * Registers the 'block-design-abilities/list-templates' ability to the Abilities API.
     *
     * The ability takes no parameters; it returns all templates of the active theme
     * along with slug, title, and (if available) post_id information.
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
     * Lists all block templates of the active theme.
     *
     * Returns an empty array if get_block_templates() function is not available.
     * Templates saved to the database will have a post_id field; this field
     * is not included for those read only from theme files.
     * Results are sorted alphabetically by slug.
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
     * Registers the 'block-design-abilities/get-template' ability to the Abilities API.
     *
     * The ability accepts a slug (required) parameter and returns the
     * parsed block array of the template.
     * Returns an empty block array if the template is not found.
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
     * Returns a single template as a parsed block array.
     *
     * The template ID is formed in "theme-slug//template-slug" format and
     * fetched using get_block_template(). Content is parsed using parse_blocks();
     * nodes with empty blockName are removed from the result.
     * post_id is returned only if the template is saved to the database.
     *
     * @param array{
     *     slug: string,
     * } $input Ability input parameters.
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
     * Registers the 'block-design-abilities/add-or-update-template' ability to the Abilities API.
     *
     * The ability accepts blocks (required), post_id, or slug (must choose one)
     * parameters. post_id and slug cannot be provided at the same time.
     *
     * @return void
     */
    public function register_update_template_ability()
    {
        wp_register_ability(
            'block-design-abilities/add-or-update-template',
            array(
                'label'       => __('Add or Update Template', 'block-design-abilities'),
                'description' => __('Saves content to a template. Provide html (preferred — avoids block validation errors) or a blocks array, along with post_id or slug (not both).', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'required'   => array(),
                    'properties' => array(

                        'post_id' => array(
                            'type'        => 'integer',
                            'description' => __('DB post ID of the template. Update existing template if post_id is provided.', 'block-design-abilities'),
                        ),

                        'slug' => array(
                            'type'        => 'string',
                            'description' => __('Template slug. Use this to create (duplicate) new template from theme file.', 'block-design-abilities'),
                        ),

                        'html' => array(
                            'type'        => 'string',
                            'description' => __('Raw HTML content. Automatically converted to blocks — preferred over blocks parameter because it prevents innerHTML/attributes mismatches that cause block validation errors. Provide html or blocks, not both.', 'block-design-abilities'),
                        ),

                        'blocks' => array(
                            'type'        => 'array',
                            'description' => __('Full updated block array. Use html instead to avoid block validation errors. Same structure as returned by get-template. Replaces template content entirely.', 'block-design-abilities')
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
     * Saves or updates a template.
     *
     * - If post_id is provided: existing wp_template post is updated using wp_update_post().
     * - If slug is provided: the template from the theme file is copied to the database
     *   for the first time (wp_insert_post), creating a DB record independent of theme updates.
     * - Returns an error if both parameters are provided simultaneously.
     * - The block array is serialized using serialize_block(); empty result returns an error.
     *
     * @param array{
     *     blocks:   array<int, array<string, mixed>>,
     *     post_id?: int,
     *     slug?:    string,
     * } $input Ability input parameters.
     *
     * @return array{
     *     success:  bool,
     *     post_id?: int,
     *     error?:   string,
     * }
     */
    public function update_template(array $input): array
    {
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
        } elseif (! empty($input['slug'])) {
            // Template from theme file — not yet saved to DB
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

            $post_id = null; // Will be created below with wp_insert_post

        } else {
            return array(
                'success' => false,
                'error'   => __('Either post_id or slug must be provided.', 'block-design-abilities'),
            );
        }

        $blocks = Block_Design_Abilities::resolve_blocks($input);
        if (is_wp_error($blocks)) {
            return array('success' => false, 'error' => $blocks->get_error_message());
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
