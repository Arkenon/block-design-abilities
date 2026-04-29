<?php

class Block_Design_Abilities_Templates
{
    public function __construct()
    {
        add_action('wp_abilities_api_init', array($this, 'register_list_templates_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_get_template_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_update_template_ability'));
    }

    public function register_list_templates_ability()
    {
        wp_register_ability(
            'block-design-abilities/list-templates',
            array(
                'label'       => __('List Templates', 'block-design-abilities'),
                'description' => __('Returns all available templates for the active theme — including both theme file templates (from the templates/ directory) and database-customized templates. Use this to discover template slugs. Then call get-template with the desired slug to retrieve its full block structure. The "source" field tells you whether a template comes from the theme files or has been customized and saved to the database.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(

                        'success' => array(
                            'type'        => 'boolean',
                            'description' => __('Whether the retrieval was successful.', 'block-design-abilities'),
                        ),

                        'theme' => array(
                            'type'        => 'string',
                            'description' => __('The active theme slug.', 'block-design-abilities'),
                        ),

                        'templates' => array(
                            'type'        => 'array',
                            'description' => __('All available templates. Use slug when calling get-template.', 'block-design-abilities'),
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

                                    'description' => array(
                                        'type'        => 'string',
                                        'description' => __('Short description of the template purpose.', 'block-design-abilities'),
                                    ),

                                    'source' => array(
                                        'type'        => 'string',
                                        'enum'        => array('theme', 'custom', 'plugin'),
                                        'description' => __('"theme" = unmodified theme file template. "custom" = modified and saved to database. "plugin" = registered by a plugin. Only "custom" templates have a post_id.', 'block-design-abilities'),
                                    ),

                                    'post_id' => array(
                                        'type'        => 'integer',
                                        'description' => __('Database post ID. Only present when source is "custom". Use this when calling update-template.', 'block-design-abilities'),
                                    ),

                                    'is_custom' => array(
                                        'type'        => 'boolean',
                                        'description' => __('Whether this is a custom (user-created) template rather than a standard theme template.', 'block-design-abilities'),
                                    ),

                                ),
                            ),
                        ),

                        'total' => array(
                            'type'        => 'integer',
                            'description' => __('Total number of templates found.', 'block-design-abilities'),
                        ),

                        'error' => array(
                            'type'        => 'string',
                            'description' => __('Error message if success is false.', 'block-design-abilities'),
                        ),

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

    public function list_templates(): array
    {
        if (! function_exists('get_block_templates')) {
            return array(
                'success' => false,
                'error'   => __('get_block_templates() is not available. WordPress 5.9+ required.', 'block-design-abilities'),
            );
        }

        $block_templates = get_block_templates(array(), 'wp_template');

        if (empty($block_templates)) {
            return array(
                'success'   => true,
                'theme'     => get_stylesheet(),
                'templates' => array(),
                'total'     => 0,
            );
        }

        $templates = array_map(function ($tpl) {
            $item = array(
                'slug'        => $tpl->slug,
                'title'       => $tpl->title,
                'description' => $tpl->description,
                'source'      => $tpl->source,      // 'theme' | 'custom' | 'plugin'
                'is_custom'   => $tpl->is_custom,
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
            'success'   => true,
            'theme'     => get_stylesheet(),
            'templates' => $templates,
            'total'     => count($templates),
        );
    }

    public function register_get_template_ability()
    {
        wp_register_ability(
            'block-design-abilities/get-template',
            array(
                'label'       => __('Get Template', 'block-design-abilities'),
                'description' => __('Retrieves a single block template by slug and returns its content as a parsed block array. Use list-templates to find available slugs first. After reviewing the blocks array, edit it and pass it to update-template.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'required'   => array('slug'),
                    'properties' => array(
                        'slug' => array(
                            'type'        => 'string',
                            'description' => __('Template slug (e.g. "front-page", "archive"). Obtain this from list-templates.', 'block-design-abilities'),
                        ),
                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success'     => array('type' => 'boolean'),
                        'slug'        => array('type' => 'string'),
                        'title'       => array('type' => 'string'),
                        'description' => array('type' => 'string'),
                        'source'      => array('type' => 'string'),
                        'post_id'     => array('type' => 'integer'),
                        'raw_content' => array('type' => 'string'),
                        'blocks'      => array('type' => 'array', 'items' => array('type' => 'object')),
                        'block_count' => array('type' => 'integer'),
                        'error'       => array('type' => 'string'),
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

    public function get_template(array $input): array
    {
        $slug = sanitize_title($input['slug']);

        // Format: 'theme-slug//template-slug'
        $template_id = get_stylesheet() . '//' . $slug;

        $template = get_block_template($template_id, 'wp_template');

        if (! $template) {
            return array(
                'success' => false,
                'error'   => sprintf(
                    __('Template "%s" not found. Use list-templates to see available templates.', 'block-design-abilities'),
                    $slug
                ),
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
            'success'     => true,
            'slug'        => $template->slug,
            'title'       => $template->title,
            'description' => $template->description,
            'source'      => $template->source,
            'raw_content' => $raw_content,
            'blocks'      => $parsed_blocks,
            'block_count' => count($parsed_blocks),
        );

        // Only add post_id if the template has a DB record
        if (! empty($template->wp_id)) {
            $result['post_id'] = (int) $template->wp_id;
        }

        return $result;
    }

    public function register_update_template_ability()
    {
        wp_register_ability(
            'block-design-abilities/update-template',
            array(
                'label'       => __('Update Template', 'block-design-abilities'),
                'description' => __('Saves an updated block array to an existing WordPress template. Always call get-template first to retrieve the current block structure, make your edits, then call this with the modified blocks array and the post_id returned by get-template.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'required'   => array('post_id', 'blocks'),
                    'properties' => array(

                        'post_id' => array(
                            'type'        => 'integer',
                            'description' => __('DB post ID of the template. Use this if the template has been customized before (source: "custom"). Returned by list-templates and get-template.', 'block-design-abilities'),
                        ),

                        'slug' => array(
                            'type'        => 'string',
                            'description' => __('Template slug (e.g. "front-page"). Required when post_id is not available — i.e. when source is "theme" and the template has never been saved to DB. Returned by list-templates.', 'block-design-abilities'),
                        ),

                        'blocks' => array(
                            'type'        => 'array',
                            'description' => __('The full updated block array to save. This replaces the existing template content entirely. Structure is identical to the blocks returned by get-template.', 'block-design-abilities'),
                            'items'       => array(
                                'type'       => 'object',
                                'required'   => array('blockName', 'attrs', 'innerBlocks', 'innerHTML', 'innerContent'),
                                'properties' => array(
                                    'blockName'    => array(
                                        'type'        => 'string',
                                        'description' => __('Block name (e.g. "core/group", "core/template-part").', 'block-design-abilities'),
                                    ),
                                    'attrs'        => array(
                                        'type'        => 'object',
                                        'description' => __('Block attributes as key-value pairs.', 'block-design-abilities'),
                                    ),
                                    'innerBlocks'  => array(
                                        'type'        => 'array',
                                        'description' => __('Nested child blocks.', 'block-design-abilities'),
                                        'items'       => array('type' => 'object'),
                                    ),
                                    'innerHTML'    => array(
                                        'type'        => 'string',
                                        'description' => __('Raw HTML inside the block comment delimiters.', 'block-design-abilities'),
                                    ),
                                    'innerContent' => array(
                                        'type'        => 'array',
                                        'description' => __('Ordered list of string fragments and null markers.', 'block-design-abilities'),
                                        'items'       => array(),
                                    ),
                                ),
                            ),
                        ),

                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success' => array(
                            'type'        => 'boolean',
                            'description' => __('Whether the update was successful.', 'block-design-abilities'),
                        ),
                        'post_id' => array(
                            'type'        => 'integer',
                            'description' => __('The updated template post ID.', 'block-design-abilities'),
                        ),
                        'post_name' => array(
                            'type'        => 'string',
                            'description' => __('The slug of the updated template.', 'block-design-abilities'),
                        ),
                        'previous_content' => array(
                            'type'        => 'string',
                            'description' => __('Raw block markup before the update, for rollback reference.', 'block-design-abilities'),
                        ),
                        'serialized_content' => array(
                            'type'        => 'string',
                            'description' => __('The new serialized block markup saved to the database.', 'block-design-abilities'),
                        ),
                        'error' => array(
                            'type'        => 'string',
                            'description' => __('Error message if success is false.', 'block-design-abilities'),
                        ),
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

    public function update_template(array $input): array
    {
        $blocks = $input['blocks'];

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
            'post_id'            => $post_id,
            'slug'               => $input['slug'] ?? get_post($post_id)->post_name,
            'previous_content'   => $previous_content,
            'serialized_content' => $serialized_content,
        );
    }
}
