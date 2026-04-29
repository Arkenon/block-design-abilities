<?php

class Block_Design_Abilities_Posts
{
    public function __construct()
    {
        add_action('wp_abilities_api_init', array($this, 'register_list_posts_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_get_post_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_update_post_ability'));
    }

    public function register_list_posts_ability()
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

                'execute_callback'    => array($this, 'list_posts'),
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
                'meta' => array('mcp' => array('public' => true)),
            )
        );
    }

    public function list_posts(array $input = array()): array
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

    public function register_get_post_ability()
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

                'execute_callback'    => array($this, 'get_post'),
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
                'meta' => array('mcp' => array('public' => true)),
            )
        );
    }

    public function get_post(array $input): array
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

    public function register_update_post_ability()
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

                'execute_callback'    => array($this, 'update_post'),
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
                'meta' => array('mcp' => array('public' => true)),
            )
        );
    }

    public function update_post(array $input): array
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
}
