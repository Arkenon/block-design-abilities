<?php
defined('ABSPATH') || exit;

/**
 * Block Design Abilities – Post/Page abilities.
 *
 * Registers three abilities for post and page management to the WordPress Abilities API:
 * list-posts, get-post, update-post.
 *
 * Supported post types: post, page.
 * wp_block (pattern) or template types are not handled by this class.
 *
 * @package Block_Design_Abilities
 * @since   1.0.0
 */
class Block_Design_Abilities_Posts
{
    /**
     * Initializes the class; binds all ability registration methods
     * listening to the wp_abilities_api_init hook.
     */
    public function __construct()
    {
        add_action('wp_abilities_api_init', array($this, 'register_list_posts_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_get_post_ability'));
        add_action('wp_abilities_api_init', array($this, 'register_update_post_ability'));
    }

    /**
     * Registers the 'block-design-abilities/list-posts' ability to the Abilities API.
     *
     * The ability accepts post_type, posts_per_page, paged, s, orderby, and order
     * parameters. Returns a post list along with pagination information.
     *
     * @return void
     */
    public function register_list_posts_ability()
    {
        wp_register_ability(
            'block-design-abilities/list-posts',
            array(
                'label'       => __('List Posts and Pages', 'block-design-abilities'),
                'description' => __('Returns a paginated list of posts/pages. Use "s" to search by title instead of browsing. Returns post_id for use with get-post.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(

                        'post_type' => array(
                            'type'        => 'string',
                            'enum'        => array('post', 'page', 'any'),
                            'description' => __('"post", "page", or "any" (default).', 'block-design-abilities'),
                        ),

                        'posts_per_page' => array(
                            'type'        => 'integer',
                            'description' => __('Results per page. Default 10, max 50.', 'block-design-abilities'),
                        ),

                        'paged' => array(
                            'type'        => 'integer',
                            'description' => __('Page number. Default 1.', 'block-design-abilities'),
                        ),

                        's' => array(
                            'type'        => 'string',
                            'description' => __('Keyword search in title and content.', 'block-design-abilities'),
                        ),

                        'orderby' => array(
                            'type'        => 'string',
                            'enum'        => array('title', 'date', 'modified', 'ID'),
                            'description' => __('Sort field. Default "title".', 'block-design-abilities'),
                        ),

                        'order' => array(
                            'type'        => 'string',
                            'enum'        => array('ASC', 'DESC'),
                            'description' => __('Sort direction. Default "ASC".', 'block-design-abilities'),
                        ),

                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success'    => array('type' => 'boolean'),
                        'posts'      => array('type' => 'array'),
                        'pagination' => array('type' => 'object'),
                        'error'      => array('type' => 'string'),
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

    /**
     * Returns the post/page list with pagination.
     *
     * When post_type="any" is provided, both post and page types are queried.
     * posts_per_page value is limited to a maximum of 50. If no results are found,
     * an empty array and a pagination object with zero totals are returned.
     *
     * @param array{
     *     post_type?:      string,
     *     posts_per_page?: int,
     *     paged?:          int,
     *     s?:              string,
     *     orderby?:        string,
     *     order?:          string,
     * } $input Ability input parameters.
     *
     * @return array{
     *     success:    bool,
     *     posts:      array<int, array<string, mixed>>,
     *     pagination: array{
     *         total_posts:    int,
     *         total_pages:    int,
     *         current_page:   int,
     *         posts_per_page: int,
     *         has_more:       bool,
     *     },
     *     error?: string,
     * }
     */
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

    /**
     * Registers the 'block-design-abilities/get-post' ability to the Abilities API.
     *
     * The ability accepts post_id (required) parameter and returns the
     * parsed block array. Only post and page types are supported;
     * get-template should be used for template types.
     *
     * @return void
     */
    public function register_get_post_ability()
    {
        wp_register_ability(
            'block-design-abilities/get-post',
            array(
                'label'       => __('Get Post or Page', 'block-design-abilities'),
                'description' => __('Returns a post/page block structure by post_id. Use list-posts to find post_id first. Edit the returned blocks and pass them to update-post.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'required'   => array('post_id'),
                    'properties' => array(
                        'post_id' => array(
                            'type'        => 'integer',
                            'description' => __('Post/page ID from list-posts.', 'block-design-abilities'),
                        ),
                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success'     => array('type' => 'boolean'),
                        'post_id'     => array('type' => 'integer'),
                        'post_name'   => array('type' => 'string'),
                        'title'       => array('type' => 'string'),
                        'post_type'   => array('type' => 'string'),
                        'url'         => array('type' => 'string'),
                        'blocks'      => array('type' => 'array'),
                        'block_count' => array('type' => 'integer'),
                        'error'       => array('type' => 'string'),
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

    /**
     * Returns a single post or page as a parsed block array.
     *
     * Content is parsed using parse_blocks(); nodes with empty blockName
     * (whitespace blocks, etc.) are removed from the result.
     * Types other than post and page are rejected.
     *
     * @param array{
     *     post_id: int,
     * } $input Ability input parameters.
     *
     * @return array{
     *     success:      bool,
     *     post_id?:     int,
     *     post_name?:   string,
     *     title?:       string,
     *     post_type?:   string,
     *     url?:         string,
     *     blocks?:      array<int, array<string, mixed>>,
     *     block_count?: int,
     *     error?:       string,
     * }
     */
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
            'blocks'      => $parsed_blocks,
            'block_count' => count($parsed_blocks),
        );
    }

    /**
     * Registers the 'block-design-abilities/update-post' ability to the Abilities API.
     *
     * The ability accepts post_id and blocks (required), and title (optional)
     * parameters. Only post and page types can be updated;
     * update-template should be used for template types.
     *
     * @return void
     */
    public function register_update_post_ability()
    {
        wp_register_ability(
            'block-design-abilities/update-post',
            array(
                'label'       => __('Update Post or Page', 'block-design-abilities'),
                'description' => __('Saves an updated block array to a post/page. Call get-post first, edit the blocks, then pass them here with post_id.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'required'   => array('post_id', 'blocks'),
                    'properties' => array(

                        'post_id' => array(
                            'type'        => 'integer',
                            'description' => __('Post/page ID from get-post.', 'block-design-abilities'),
                        ),

                        'blocks' => array(
                            'type'        => 'array',
                            'description' => __('Full updated block array from get-post. Replaces existing content entirely.', 'block-design-abilities'),
                            'items'       => array('type' => 'object'),
                        ),

                        'title' => array(
                            'type'        => 'string',
                            'description' => __('Optional. Updates the post/page title if provided.', 'block-design-abilities'),
                        ),

                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success'            => array('type' => 'boolean'),
                        'post_id'            => array('type' => 'integer'),
                        'post_type'          => array('type' => 'string'),
                        'url'                => array('type' => 'string'),
                        'error'              => array('type' => 'string'),
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

    /**
     * Updates the block content of a post or page.
     *
     * The incoming block array is serialized using serialize_block() and
     * overwrites the post content using wp_update_post().
     * Returns an error if serialization results in empty content. If title
     * is provided, the title is also updated.
     *
     * @param array{
     *     post_id: int,
     *     blocks:  array<int, array<string, mixed>>,
     *     title?:  string,
     * } $input Ability input parameters.
     *
     * @return array{
     *     success:    bool,
     *     post_id?:   int,
     *     post_type?: string,
     *     url?:       string,
     *     error?:     string,
     * }
     */
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
            'url'                => get_permalink($post_id)
        );
    }
}
