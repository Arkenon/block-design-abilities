<?php

/**
 * Plugin Name:       Block Design Abilities
 * Description:       Create or modify block templates, pages, posts or patterns using AI.
 * Author:            Arkenon
 * Version:           1.0.0
 * Network:           true
 * Author URI:        https://github.com/Arkenon
 * Text Domain:       block-design-abilities
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.opensource.org/licenses/GPL-2.0
 * GitHub Plugin URI: https://github.com/Arkenon/block-design-abilities
 * Requires at least: 7.0
 * Requires PHP:      8.0
 */


// AI → list-templates()
//         ↓
// [
//   { slug: "front-page", source: "custom", post_id: 42 },  ← exists in DB
//   { slug: "archive",    source: "theme"               },  ← theme file only
//   { slug: "single",     source: "theme"               },  ← theme file only
// ]
//         ↓
// AI → get-template( slug: "archive" )
//         ↓
// get_block_template("theme-slug//archive") → reads the theme file
// { slug: "archive", source: "theme", post_id: NONE, blocks: [...] }
//         ↓
// AI edits the blocks
//         ↓
// AI → update-template( slug: "archive", blocks: [...] )
//         ↓
// post_id absent → wp_insert_post() → saved to DB for the first time
// This template's source is now "custom" ✅


add_action('wp_abilities_api_categories_init', 'block_design_abilities_register_ability_category');

function block_design_abilities_register_ability_category()
{
    wp_register_ability_category(
        'block-design-abilities',
        array(
            'label'       => 'Block Design Abilities',
            'description' => 'Abilities for Block Design Abilities MCP content analysis.',
        )
    );
}


add_action('wp_abilities_api_init', 'block_design_abilities_register_list_templates_ability');

function block_design_abilities_register_list_templates_ability()
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

            'execute_callback'    => 'block_design_abilities_list_templates',
            'permission_callback' => function () {
                return current_user_can('edit_theme_options');
            },
            'meta' => array('mcp' => array('public' => true)),
        )
    );
}

function block_design_abilities_list_templates(): array
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

add_action('wp_abilities_api_init', 'block_design_abilities_register_get_template_ability');

function block_design_abilities_register_get_template_ability()
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

            'execute_callback'    => 'block_design_abilities_get_template',
            'permission_callback' => function () {
                return current_user_can('edit_theme_options');
            },
            'meta' => array('mcp' => array('public' => true)),
        )
    );
}

function block_design_abilities_get_template(array $input): array
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



add_action('wp_abilities_api_init', 'block_design_abilities_register_update_template_ability');

function block_design_abilities_register_update_template_ability()
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

            'execute_callback'    => 'block_design_abilities_update_template',
            'permission_callback' => function () {
                return current_user_can('edit_theme_options');
            },
            'meta' => array('mcp' => array('public' => true)),
        )
    );
}

function block_design_abilities_update_template(array $input): array
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


// I know the title    => list-posts(s: "contact")    => get-post(post_id) => update-post(post_id, blocks)
// I don't know title  => list-posts(post_type: "page") => get-post(post_id) => update-post(post_id, blocks)
// Many pages exist    => list-posts(paged: 2)          => get-post(post_id) => update-post(post_id, blocks)

add_action('wp_abilities_api_init', 'block_design_abilities_register_list_posts_ability');

function block_design_abilities_register_list_posts_ability()
{
    wp_register_ability(
        'block-design-abilities/list-posts',
        array(
            'label'       => __('List Posts and Pages', 'block-design-abilities'),
            'description' => __('Returns a paginated list of posts and/or pages. Supports keyword search via the "s" parameter — use this when you know (part of) the title instead of browsing the full list. Returns post_id and post_name for use with get-post. Does NOT return block content — call get-post for that.', 'block-design-abilities'),
            'category'    => 'block-design-abilities',

            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(

                    'post_type' => array(
                        'type'        => 'string',
                        'enum'        => array('post', 'page', 'any'),
                        'description' => __('Filter by post type. Use "post" for blog posts, "page" for pages, "any" for both. Defaults to "any".', 'block-design-abilities'),
                    ),

                    'posts_per_page' => array(
                        'type'        => 'integer',
                        'description' => __('Number of results to return per page. Default is 10, maximum is 50.', 'block-design-abilities'),
                    ),

                    'paged' => array(
                        'type'        => 'integer',
                        'description' => __('Page number for pagination. Default is 1. Use with posts_per_page to browse through results.', 'block-design-abilities'),
                    ),

                    's' => array(
                        'type'        => 'string',
                        'description' => __('Keyword search. Searches post title and content. Use this when you know the name of the post/page you are looking for — avoids unnecessary pagination. Example: "contact" will match "Contact Us", "Contact Page" etc.', 'block-design-abilities'),
                    ),

                    'orderby' => array(
                        'type'        => 'string',
                        'enum'        => array('title', 'date', 'modified', 'ID'),
                        'description' => __('Field to sort results by. Default is "title".', 'block-design-abilities'),
                    ),

                    'order' => array(
                        'type'        => 'string',
                        'enum'        => array('ASC', 'DESC'),
                        'description' => __('Sort direction. Default is "ASC".', 'block-design-abilities'),
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

                    'posts' => array(
                        'type'        => 'array',
                        'description' => __('List of matching posts/pages. Use post_id when calling get-post.', 'block-design-abilities'),
                        'items'       => array(
                            'type'       => 'object',
                            'properties' => array(
                                'post_id' => array(
                                    'type'        => 'integer',
                                    'description' => __('The database ID. Use this when calling get-post or update-post.', 'block-design-abilities'),
                                ),
                                'post_name' => array(
                                    'type'        => 'string',
                                    'description' => __('The URL slug of the post/page.', 'block-design-abilities'),
                                ),
                                'title' => array(
                                    'type'        => 'string',
                                    'description' => __('The title of the post/page.', 'block-design-abilities'),
                                ),
                                'post_type' => array(
                                    'type'        => 'string',
                                    'description' => __('Whether this is a "post" or "page".', 'block-design-abilities'),
                                ),
                                'status' => array(
                                    'type'        => 'string',
                                    'description' => __('Post status (e.g. publish, draft).', 'block-design-abilities'),
                                ),
                                'modified' => array(
                                    'type'        => 'string',
                                    'description' => __('Last modified date in Y-m-d H:i:s format.', 'block-design-abilities'),
                                ),
                                'url' => array(
                                    'type'        => 'string',
                                    'description' => __('The public URL of the post/page.', 'block-design-abilities'),
                                ),
                            ),
                        ),
                    ),

                    'pagination' => array(
                        'type'        => 'object',
                        'description' => __('Pagination metadata.', 'block-design-abilities'),
                        'properties'  => array(
                            'total_posts' => array(
                                'type'        => 'integer',
                                'description' => __('Total number of matching posts across all pages.', 'block-design-abilities'),
                            ),
                            'total_pages' => array(
                                'type'        => 'integer',
                                'description' => __('Total number of pages available.', 'block-design-abilities'),
                            ),
                            'current_page' => array(
                                'type'        => 'integer',
                                'description' => __('The current page number.', 'block-design-abilities'),
                            ),
                            'posts_per_page' => array(
                                'type'        => 'integer',
                                'description' => __('Number of results per page.', 'block-design-abilities'),
                            ),
                            'has_more' => array(
                                'type'        => 'boolean',
                                'description' => __('Whether there are more pages to fetch.', 'block-design-abilities'),
                            ),
                        ),
                    ),

                    'error' => array(
                        'type'        => 'string',
                        'description' => __('Error message if success is false.', 'block-design-abilities'),
                    ),

                ),
            ),

            'execute_callback'    => 'block_design_abilities_list_posts',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'meta' => array('mcp' => array('public' => true)),
        )
    );
}

function block_design_abilities_list_posts(array $input = array()): array
{
    $post_type      = isset($input['post_type']) ? $input['post_type'] : 'any';
    $posts_per_page = isset($input['posts_per_page']) ? min(absint($input['posts_per_page']), 50) : 10;
    $paged          = isset($input['paged']) ? max(absint($input['paged']), 1) : 1;
    $search         = isset($input['s']) ? sanitize_text_field($input['s']) : '';
    $orderby        = isset($input['orderby']) ? $input['orderby'] : 'title';
    $order          = isset($input['order']) ? strtoupper($input['order']) : 'ASC';

    // If post_type is "any", fetch both post types
    $resolved_post_type = ($post_type === 'any') ? array('post', 'page') : $post_type;

    $query_args = array(
        'post_type'      => $resolved_post_type,
        'post_status'    => 'publish',
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
        'orderby'        => $orderby,
        'order'          => $order,
    );

    if (! empty($search)) {
        $query_args['s'] = $search;
    }

    $query = new WP_Query($query_args);

    if (! $query->have_posts()) {
        return array(
            'success' => true,
            'posts'   => array(),
            'pagination' => array(
                'total_posts'    => 0,
                'total_pages'    => 0,
                'current_page'   => $paged,
                'posts_per_page' => $posts_per_page,
                'has_more'       => false,
            ),
        );
    }

    $posts = array_map(function ($post) {
        return array(
            'post_id'   => $post->ID,
            'post_name' => $post->post_name,
            'title'     => $post->post_title,
            'post_type' => $post->post_type,
            'status'    => $post->post_status,
            'modified'  => $post->post_modified,
            'url'       => get_permalink($post->ID),
        );
    }, $query->posts);

    return array(
        'success' => true,
        'posts'   => $posts,
        'pagination' => array(
            'total_posts'    => (int) $query->found_posts,
            'total_pages'    => (int) $query->max_num_pages,
            'current_page'   => $paged,
            'posts_per_page' => $posts_per_page,
            'has_more'       => $paged < $query->max_num_pages,
        ),
    );
}


add_action('wp_abilities_api_init', 'block_design_abilities_register_get_post_ability');

function block_design_abilities_register_get_post_ability()
{
    wp_register_ability(
        'block-design-abilities/get-post',
        array(
            'label'       => __('Get Post or Page', 'block-design-abilities'),
            'description' => __('Retrieves a single post or page by post_id and returns its content as a parsed block array. Use list-posts to find the correct post_id first. After reviewing the blocks array, edit it and pass it to update-post.', 'block-design-abilities'),
            'category'    => 'block-design-abilities',

            'input_schema' => array(
                'type'       => 'object',
                'required'   => array('post_id'),
                'properties' => array(
                    'post_id' => array(
                        'type'        => 'integer',
                        'description' => __('The database ID of the post or page to retrieve. Obtain this from list-posts.', 'block-design-abilities'),
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

                    'post_id' => array(
                        'type'        => 'integer',
                        'description' => __('The post ID. Pass this to update-post when saving changes.', 'block-design-abilities'),
                    ),

                    'post_name' => array(
                        'type'        => 'string',
                        'description' => __('The URL slug of the post/page.', 'block-design-abilities'),
                    ),

                    'title' => array(
                        'type'        => 'string',
                        'description' => __('The title of the post/page.', 'block-design-abilities'),
                    ),

                    'post_type' => array(
                        'type'        => 'string',
                        'description' => __('Whether this is a "post" or "page".', 'block-design-abilities'),
                    ),

                    'url' => array(
                        'type'        => 'string',
                        'description' => __('The public URL of the post/page.', 'block-design-abilities'),
                    ),

                    'raw_content' => array(
                        'type'        => 'string',
                        'description' => __('The raw serialized block markup from the database. For reference only — edit the blocks array instead.', 'block-design-abilities'),
                    ),

                    'blocks' => array(
                        'type'        => 'array',
                        'description' => __('Parsed block array (output of parse_blocks). Edit this and pass to update-post. Each item contains blockName, attrs, innerBlocks, innerHTML, and innerContent.', 'block-design-abilities'),
                        'items'       => array(
                            'type'       => 'object',
                            'properties' => array(
                                'blockName'    => array('type' => 'string'),
                                'attrs'        => array('type' => 'object'),
                                'innerBlocks'  => array('type' => 'array', 'items' => array('type' => 'object')),
                                'innerHTML'    => array('type' => 'string'),
                                'innerContent' => array('type' => 'array', 'items' => array()),
                            ),
                        ),
                    ),

                    'block_count' => array(
                        'type'        => 'integer',
                        'description' => __('Total number of top-level blocks in the content.', 'block-design-abilities'),
                    ),

                    'error' => array(
                        'type'        => 'string',
                        'description' => __('Error message if success is false.', 'block-design-abilities'),
                    ),

                ),
            ),

            'execute_callback'    => 'block_design_abilities_get_post',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'meta' => array('mcp' => array('public' => true)),
        )
    );
}

function block_design_abilities_get_post(array $input): array
{
    $post_id = absint($input['post_id']);

    $post = get_post($post_id);

    if (! $post || ! in_array($post->post_type, array('post', 'page'), true)) {
        return array(
            'success' => false,
            'error'   => sprintf(
                __('Post/page with ID %d not found or is not a post/page type. For templates use get-template instead.', 'block-design-abilities'),
                $post_id
            ),
        );
    }

    $raw_content = $post->post_content;

    $parsed_blocks = parse_blocks($raw_content);

    // Remove whitespace-only blocks
    $parsed_blocks = array_values(
        array_filter($parsed_blocks, function ($block) {
            return ! empty($block['blockName']);
        })
    );

    return array(
        'success'     => true,
        'post_id'     => $post->ID,
        'post_name'   => $post->post_name,
        'title'       => $post->post_title,
        'post_type'   => $post->post_type,
        'url'         => get_permalink($post->ID),
        'raw_content' => $raw_content,
        'blocks'      => $parsed_blocks,
        'block_count' => count($parsed_blocks),
    );
}


add_action('wp_abilities_api_init', 'block_design_abilities_register_update_post_ability');

function block_design_abilities_register_update_post_ability()
{
    wp_register_ability(
        'block-design-abilities/update-post',
        array(
            'label'       => __('Update Post or Page', 'block-design-abilities'),
            'description' => __('Saves an updated block array to an existing post or page. Always call get-post first to retrieve the current block structure, make your edits, then call this with the modified blocks array and the post_id. This ability only works on post and page types — for templates use update-template instead.', 'block-design-abilities'),
            'category'    => 'block-design-abilities',

            'input_schema' => array(
                'type'       => 'object',
                'required'   => array('post_id', 'blocks'),
                'properties' => array(

                    'post_id' => array(
                        'type'        => 'integer',
                        'description' => __('The database ID of the post or page to update. Use the post_id returned by get-post.', 'block-design-abilities'),
                    ),

                    'blocks' => array(
                        'type'        => 'array',
                        'description' => __('The full updated block array. Replaces existing content entirely. Use the blocks array from get-post as your starting point.', 'block-design-abilities'),
                        'items'       => array(
                            'type'       => 'object',
                            'required'   => array('blockName', 'attrs', 'innerBlocks', 'innerHTML', 'innerContent'),
                            'properties' => array(
                                'blockName'    => array('type' => 'string'),
                                'attrs'        => array('type' => 'object'),
                                'innerBlocks'  => array('type' => 'array', 'items' => array('type' => 'object')),
                                'innerHTML'    => array('type' => 'string'),
                                'innerContent' => array('type' => 'array', 'items' => array()),
                            ),
                        ),
                    ),

                    'title' => array(
                        'type'        => 'string',
                        'description' => __('Optional. If provided, updates the post/page title as well.', 'block-design-abilities'),
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
                        'description' => __('The updated post/page ID.', 'block-design-abilities'),
                    ),
                    'post_type' => array(
                        'type'        => 'string',
                        'description' => __('The post type that was updated.', 'block-design-abilities'),
                    ),
                    'url' => array(
                        'type'        => 'string',
                        'description' => __('The public URL of the updated post/page.', 'block-design-abilities'),
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

            'execute_callback'    => 'block_design_abilities_update_post',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'meta' => array('mcp' => array('public' => true)),
        )
    );
}

function block_design_abilities_update_post(array $input): array
{
    $post_id = absint($input['post_id']);
    $blocks  = $input['blocks'];

    $post = get_post($post_id);

    if (! $post || ! in_array($post->post_type, array('post', 'page'), true)) {
        return array(
            'success' => false,
            'error'   => sprintf(
                __('Post/page with ID %d not found or is not a post/page type. For templates use update-template instead.', 'block-design-abilities'),
                $post_id
            ),
        );
    }

    $previous_content = $post->post_content;

    $serialized_content = '';
    foreach ($blocks as $block) {
        $serialized_content .= serialize_block($block);
    }

    if (empty(trim($serialized_content))) {
        return array(
            'success' => false,
            'error'   => __('Block serialization failed. Check your updated block structure.', 'block-design-abilities'),
        );
    }

    $update_args = array(
        'ID'           => $post_id,
        'post_content' => $serialized_content,
    );

    // Append post_title if a new title was provided
    if (! empty($input['title'])) {
        $update_args['post_title'] = sanitize_text_field($input['title']);
    }

    $result = wp_update_post($update_args);

    if (is_wp_error($result)) {
        return array(
            'success' => false,
            'error'   => $result->get_error_message(),
        );
    }

    return array(
        'success'            => true,
        'post_id'            => $post_id,
        'post_type'          => $post->post_type,
        'url'                => get_permalink($post_id),
        'previous_content'   => $previous_content,
        'serialized_content' => $serialized_content,
    );
}


// User: "Show my theme's color palette"
// → get-theme-json( sections: ["settings", "theme_info"] )
// → Returns the slugs inside settings.color.palette

// User: "What have I changed in the Site Editor?"
// → get-theme-json( sections: ["user_overrides"] )
// → Returns only the DB overrides; null means no changes have been made

// User: "Apply the primary color to the front-page template"
// → 1. get-theme-json( sections: ["settings"] ) → find the "primary" slug
// → 2. get-template( slug: "front-page" ) → retrieve the blocks array
// → 3. Add { "backgroundColor": "primary" } to the relevant block's attrs
// → 4. update-template( ... ) → save

add_action('wp_abilities_api_init', 'block_design_abilities_get_theme_json_ability');

function block_design_abilities_get_theme_json_ability()
{
    wp_register_ability(
        'block-design-abilities/get-theme-json',
        array(
            'label'       => __('Get Theme JSON', 'block-design-abilities'),
            'description' => __('Returns the active theme\'s design configuration from theme.json — including color palettes, typography, spacing, and style overrides. Returns both the merged final data (theme file + user customizations) and the user-only overrides saved to the database via the Site Editor. Use this to understand available design tokens (colors, font sizes, spacing scale) before editing templates or patterns.', 'block-design-abilities'),
            'category'    => 'block-design-abilities',

            'input_schema' => array(
                'type'       => 'object',
                'properties' => array(

                    'origin' => array(
                        'type'        => 'string',
                        'enum'        => array('all', 'base'),
                        'description' => __('Which data to return. "all" (default) returns theme file + user customizations merged. "base" returns only the theme file data, ignoring Site Editor overrides.', 'block-design-abilities'),
                    ),

                    'sections' => array(
                        'type'        => 'array',
                        'items'       => array(
                            'type' => 'string',
                            'enum' => array('settings', 'styles', 'user_overrides', 'theme_info'),
                        ),
                        'description' => __('Which sections to include in the response. Omit to get all sections. Options: "settings" (color palettes, font sizes, spacing), "styles" (global CSS values), "user_overrides" (only what was changed in Site Editor), "theme_info" (theme name, version, etc).', 'block-design-abilities'),
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

                    'theme_info' => array(
                        'type'        => 'object',
                        'description' => __('Basic information about the active theme.', 'block-design-abilities'),
                        'properties'  => array(
                            'name'       => array('type' => 'string', 'description' => __('Theme display name.', 'block-design-abilities')),
                            'stylesheet' => array('type' => 'string', 'description' => __('Theme slug (stylesheet).', 'block-design-abilities')),
                            'version'    => array('type' => 'string', 'description' => __('Theme version.', 'block-design-abilities')),
                            'is_block_theme' => array('type' => 'boolean', 'description' => __('Whether this is a block (FSE) theme.', 'block-design-abilities')),
                            'has_theme_json' => array('type' => 'boolean', 'description' => __('Whether the theme has a theme.json file.', 'block-design-abilities')),
                            'has_user_overrides' => array('type' => 'boolean', 'description' => __('Whether the user has customized global styles via the Site Editor.', 'block-design-abilities')),
                        ),
                    ),

                    'settings' => array(
                        'type'        => 'object',
                        'description' => __('Merged theme settings (core + theme + user). Contains design tokens: color.palette, typography.fontSizes, typography.fontFamilies, spacing.spacingSizes, etc. Use these slugs when setting block attributes.', 'block-design-abilities'),
                    ),

                    'styles' => array(
                        'type'        => 'object',
                        'description' => __('Merged global styles (core + theme + user). Contains CSS values for color, typography, spacing applied globally and per block.', 'block-design-abilities'),
                    ),

                    'user_overrides' => array(
                        'type'        => 'object',
                        'description' => __('Only the customizations the user has made via the Site Editor (stored in DB as "Custom Styles"). Empty if no customizations have been made. This is the raw JSON from the wp_global_styles post.', 'block-design-abilities'),
                        'properties'  => array(
                            'post_id'  => array('type' => 'integer', 'description' => __('DB post ID of the Custom Styles record.', 'block-design-abilities')),
                            'settings' => array('type' => 'object', 'description' => __('User-overridden settings only.', 'block-design-abilities')),
                            'styles'   => array('type' => 'object', 'description' => __('User-overridden styles only.', 'block-design-abilities')),
                        ),
                    ),

                    'error' => array(
                        'type'        => 'string',
                        'description' => __('Error message if success is false.', 'block-design-abilities'),
                    ),

                ),
            ),

            'execute_callback'    => 'block_design_abilities_get_theme_json',
            'permission_callback' => function () {
                return current_user_can('edit_theme_options');
            },
            'meta' => array('mcp' => array('public' => true)),
        )
    );
}

function block_design_abilities_get_theme_json(array $input = array()): array
{
    $origin   = isset($input['origin']) && $input['origin'] === 'base' ? 'base' : 'all';
    $sections = isset($input['sections']) ? $input['sections'] : array('settings', 'styles', 'user_overrides', 'theme_info');

    $theme = wp_get_theme();

    $result = array('success' => true);

    // --- theme_info ---
    if (in_array('theme_info', $sections, true)) {
        $has_user_overrides = false;
        $user_cpt           = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles($theme);
        if (! empty($user_cpt) && ! empty($user_cpt['post_content'])) {
            $decoded = json_decode($user_cpt['post_content'], true);
            $has_user_overrides = ! empty($decoded['settings']) || ! empty($decoded['styles']);
        }

        $result['theme_info'] = array(
            'name'               => $theme->get('Name'),
            'stylesheet'         => $theme->get_stylesheet(),
            'version'            => $theme->get('Version'),
            'is_block_theme'     => wp_is_block_theme(),
            'has_theme_json'     => wp_theme_has_theme_json(),
            'has_user_overrides' => $has_user_overrides,
        );
    }

    // --- settings ---
    if (in_array('settings', $sections, true)) {
        $context = $origin === 'base' ? array('origin' => 'base') : array();
        $result['settings'] = wp_get_global_settings(array(), $context);
    }

    // --- styles ---
    if (in_array('styles', $sections, true)) {
        $context = $origin === 'base' ? array('origin' => 'base') : array();
        $result['styles'] = wp_get_global_styles(array(), $context);
    }

    // --- user_overrides ---
    if (in_array('user_overrides', $sections, true)) {
        $user_cpt = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles($theme);

        if (empty($user_cpt) || empty($user_cpt['post_content'])) {
            $result['user_overrides'] = null;
        } else {
            $decoded  = json_decode($user_cpt['post_content'], true);
            $settings = $decoded['settings'] ?? array();
            $styles   = $decoded['styles']   ?? array();

            if (empty($settings) && empty($styles)) {
                $result['user_overrides'] = null;
            } else {
                $result['user_overrides'] = array(
                    'post_id'  => (int) $user_cpt['ID'],
                    'settings' => $settings,
                    'styles'   => $styles,
                );
            }
        }
    }

    return $result;
}


add_action('wp_abilities_api_init', 'block_design_abilities_register_list_patterns_ability');

function block_design_abilities_register_list_patterns_ability()
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

            'execute_callback'    => 'block_design_abilities_list_patterns',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'meta' => array('mcp' => array('public' => true)),
        )
    );
}

function block_design_abilities_list_patterns(array $input = array()): array
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


add_action('wp_abilities_api_init', 'block_design_abilities_register_get_pattern_ability');

function block_design_abilities_register_get_pattern_ability()
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

            'execute_callback'    => 'block_design_abilities_get_pattern',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'meta' => array('mcp' => array('public' => true)),
        )
    );
}

function block_design_abilities_get_pattern(array $input): array
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


add_action('wp_abilities_api_init', 'block_design_abilities_register_update_pattern_ability');

function block_design_abilities_register_update_pattern_ability()
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

            'execute_callback'    => 'myplugin_update_pattern',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'meta' => array('show_in_rest' => true),
        )
    );
}

function myplugin_update_pattern(array $input): array
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

add_action('wp_abilities_api_init', 'block_design_abilities_register_duplicate_pattern_ability');

function block_design_abilities_register_duplicate_pattern_ability()
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

            'execute_callback'    => 'block_design_abilities_duplicate_pattern',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'meta' => array('mcp' => array('public' => true)),
        )
    );
}

function block_design_abilities_duplicate_pattern(array $input): array
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

add_action('wp_abilities_api_init', 'block_design_abilities_register_create_pattern_ability');

function block_design_abilities_register_create_pattern_ability()
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

            'execute_callback'    => 'block_design_abilities_create_pattern',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'meta' => array('mcp' => array('public' => true)),
        )
    );
}

function block_design_abilities_create_pattern(array $input): array
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
