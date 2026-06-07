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
        add_action('wp_abilities_api_init', array($this, 'register_create_post_ability'));
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
                'description' => __('Returns a post/page raw block markup as html by post_id. Use list-posts to find post_id first. The returned html can be modified and passed straight back to update-post as the html parameter.', 'block-design-abilities'),
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
                        'success'   => array('type' => 'boolean'),
                        'post_id'   => array('type' => 'integer'),
                        'post_name' => array('type' => 'string'),
                        'title'     => array('type' => 'string'),
                        'post_type' => array('type' => 'string'),
                        'url'       => array('type' => 'string'),
                        'html'      => array('type' => 'string'),
                        'error'     => array('type' => 'string'),
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
     * Returns a single post or page as raw block markup (html).
     *
     * The raw serialized block markup is returned as-is so it can be passed
     * back to update-post as the html parameter for round-trip editing.
     * Types other than post and page are rejected.
     *
     * @param array{
     *     post_id: int,
     * } $input Ability input parameters.
     *
     * @return array{
     *     success:    bool,
     *     post_id?:   int,
     *     post_name?: string,
     *     title?:     string,
     *     post_type?: string,
     *     url?:       string,
     *     html?:      string,
     *     error?:     string,
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

        return array(
            'success'   => true,
            'post_id'   => $post->ID,
            'post_name' => $post->post_name,
            'title'     => $post->post_title,
            'post_type' => $post->post_type,
            'url'       => get_permalink($post->ID),
            'html'      => $post->post_content,
        );
    }

    /**
     * Registers the 'block-design-abilities/create-post' ability to the Abilities API.
     *
     * The ability accepts title (required), and html, post_type, post_status
     * (optional) parameters. Creates a new post or page and returns its id and url.
     * Only post and page types can be created; create-template should be used
     * for template types.
     *
     * @return void
     */
    public function register_create_post_ability()
    {
        wp_register_ability(
            'block-design-abilities/create-post',
            array(
                'label'       => __('Create Post or Page', 'block-design-abilities'),
                'description' => __('Creates a new post/page. Provide title and optionally html. The html is converted to blocks server-side, avoiding innerHTML/attributes validation errors. Returns the new post_id for use with get-post/update-post.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'required'   => array('title'),
                    'properties' => array(

                        'title' => array(
                            'type'        => 'string',
                            'description' => __('The title of the new post/page.', 'block-design-abilities'),
                        ),

                        'html' => array(
                            'type'        => 'string',
                            'description' => __('Optional. Serialized block markup (WordPress block comment format) for the initial content. Converted to blocks server-side.', 'block-design-abilities'),
                        ),

                        'post_type' => array(
                            'type'        => 'string',
                            'enum'        => array('post', 'page'),
                            'description' => __('"post" (default) or "page".', 'block-design-abilities'),
                        ),

                        'post_status' => array(
                            'type'        => 'string',
                            'enum'        => array('draft', 'publish', 'pending', 'private'),
                            'description' => __('Publication status. Default "draft".', 'block-design-abilities'),
                        ),

                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success'   => array('type' => 'boolean'),
                        'post_id'   => array('type' => 'integer'),
                        'post_type' => array('type' => 'string'),
                        'status'    => array('type' => 'string'),
                        'url'       => array('type' => 'string'),
                        'error'     => array('type' => 'string'),
                    ),
                ),

                'execute_callback'    => array($this, 'create_post'),
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
                'meta' => array('mcp' => array('public' => true)),
            )
        );
    }

    /**
     * Creates a new post or page.
     *
     * If html is provided it is converted to blocks via
     * Block_Design_Abilities::resolve_blocks(), then re-serialized and saved as
     * post_content; an empty post is created otherwise. post_type defaults to
     * "post" and post_status defaults to "draft".
     *
     * @param array{
     *     title:        string,
     *     html?:        string,
     *     post_type?:   string,
     *     post_status?: string,
     * } $input Ability input parameters.
     *
     * @return array{
     *     success:    bool,
     *     post_id?:   int,
     *     post_type?: string,
     *     status?:    string,
     *     url?:       string,
     *     error?:     string,
     * }
     */
    public function create_post(array $input): array
    {
        $title = isset($input['title']) ? sanitize_text_field($input['title']) : '';

        if (empty($title)) {
            return array(
                'success' => false,
                'error'   => __('A non-empty title is required to create a post/page.', 'block-design-abilities'),
            );
        }

        $post_type   = (isset($input['post_type']) && in_array($input['post_type'], array('post', 'page'), true))
            ? $input['post_type']
            : 'post';
        $post_status = (isset($input['post_status']) && in_array($input['post_status'], array('draft', 'publish', 'pending', 'private'), true))
            ? $input['post_status']
            : 'draft';

        $serialized_content = '';

        // Content is optional; only resolve/serialize blocks when html is provided.
        if (! empty($input['html'])) {
            $blocks = Block_Design_Abilities::resolve_blocks($input);
            if (is_wp_error($blocks)) {
                return array('success' => false, 'error' => $blocks->get_error_message());
            }

            foreach ($blocks as $block) {
                $serialized_content .= serialize_block($block);
            }

            if (empty(trim($serialized_content))) {
                return array(
                    'success' => false,
                    'error'   => __('Block serialization failed. Check your block structure.', 'block-design-abilities'),
                );
            }
        }

        $result = wp_insert_post(
            array(
                'post_title'   => $title,
                'post_content' => $serialized_content,
                'post_type'    => $post_type,
                'post_status'  => $post_status,
            ),
            true
        );

        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'error'   => $result->get_error_message(),
            );
        }

        return array(
            'success'   => true,
            'post_id'   => (int) $result,
            'post_type' => $post_type,
            'status'    => $post_status,
            'url'       => get_permalink($result),
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
                'description' => __('Saves content to a post/page. Provide post_id and html. The html is converted to blocks server-side, avoiding innerHTML/attributes validation errors.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'required'   => array('post_id', 'html'),
                    'properties' => array(

                        'post_id' => array(
                            'type'        => 'integer',
                            'description' => __('Post/page ID from get-post.', 'block-design-abilities'),
                        ),

                        'html' => array(
                            'type'        => 'string',
                            'description' => __('Serialized block markup (WordPress block comment format). Use the output of get-post for round-trip editing. Replaces existing content entirely.', 'block-design-abilities'),
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
                        'success'   => array('type' => 'boolean'),
                        'post_id'   => array('type' => 'integer'),
                        'post_type' => array('type' => 'string'),
                        'url'       => array('type' => 'string'),
                        'error'     => array('type' => 'string'),
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
     * The html input is converted to blocks via Block_Design_Abilities::resolve_blocks()
     * (which routes serialized block markup through parse_blocks() and raw HTML through
     * the html-to-blocks converter), then re-serialized and saved as post_content.
     * Returns an error if serialization results in empty content. If title
     * is provided, the title is also updated.
     *
     * @param array{
     *     post_id: int,
     *     html:    string,
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

        $blocks = Block_Design_Abilities::resolve_blocks($input);
        if (is_wp_error($blocks)) {
            return array('success' => false, 'error' => $blocks->get_error_message());
        }

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
            'success'   => true,
            'post_id'   => $post_id,
            'post_type' => $post->post_type,
            'url'       => get_permalink($post_id),
        );
    }
}
