<?php
defined('ABSPATH') || exit;

/**
 * Block Design Abilities – Global Styles abilities.
 *
 * Registers two abilities to the WordPress Abilities API:
 *  - get-global-styles   : Reads the active theme's design tokens.
 *  - update-global-styles: Partially updates styles via the wp_global_styles post.
 *
 * Permission requirement: edit_theme_options.
 *
 * @package Block_Design_Abilities
 * @since   1.0.1
 */
class Block_Design_Abilities_Global_Styles
{
    public function __construct()
    {
        add_action('wp_abilities_api_init', array($this, 'register_abilities'));
    }

    public function register_abilities()
    {
        $this->register_get_global_styles_ability();
        $this->register_update_global_styles_ability();
    }

    // -------------------------------------------------------------------------
    // get-global-styles
    // -------------------------------------------------------------------------

    public function register_get_global_styles_ability()
    {
        wp_register_ability(
            'block-design-abilities/get-global-styles',
            array(
                'label'       => __('Get Global Styles', 'block-design-abilities'),
                'description' => __('Returns the active theme\'s design tokens from theme.json (colors, typography, spacing). Use this before editing templates or patterns to know available preset slugs for block attributes.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(

                        'origin' => array(
                            'type'        => 'string',
                            'enum'        => array('all', 'base'),
                            'description' => __('"all" (default): theme + user customizations merged. "base": theme file only.', 'block-design-abilities'),
                        ),

                        'sections' => array(
                            'type'        => 'array',
                            'items'       => array(
                                'type' => 'string',
                                'enum' => array('settings', 'styles', 'user_overrides', 'theme_info'),
                            ),
                            'description' => __('Sections to return. Omit for all. "settings": color/font/spacing tokens. "styles": global CSS. "user_overrides": Site Editor changes. "theme_info": theme metadata.', 'block-design-abilities'),
                        ),

                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success'        => array('type' => 'boolean'),
                        'theme_info'     => array('type' => 'object'),
                        'settings'       => array('type' => 'object'),
                        'styles'         => array('type' => 'object'),
                        'user_overrides' => array('type' => 'object'),
                        'error'          => array('type' => 'string'),
                    ),
                ),

                'execute_callback'    => array($this, 'get_global_styles'),
                'permission_callback' => function () {
                    return current_user_can('edit_theme_options');
                },
                'meta' => array('mcp' => array('public' => true)),
            )
        );
    }

    /**
     * @param array{origin?: string, sections?: string[]} $input
     * @return array{
     *     success: bool,
     *     theme_info?: array{name: string, stylesheet: string, version: string, is_block_theme: bool, has_theme_json: bool, has_user_overrides: bool},
     *     settings?: array<string, mixed>,
     *     styles?: array<string, mixed>,
     *     user_overrides?: array{post_id: int, settings: array<string, mixed>, styles: array<string, mixed>}|null,
     *     error?: string,
     * }
     */
    public function get_global_styles(array $input = array()): array
    {
        $origin   = isset($input['origin']) && $input['origin'] === 'base' ? 'base' : 'all';
        $sections = isset($input['sections']) ? $input['sections'] : array('settings', 'styles', 'user_overrides', 'theme_info');

        $theme  = wp_get_theme();
        $result = array('success' => true);

        if (in_array('theme_info', $sections, true)) {
            $has_user_overrides = false;
            $user_cpt           = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles($theme);
            if (! empty($user_cpt) && ! empty($user_cpt['post_content'])) {
                $decoded            = json_decode($user_cpt['post_content'], true);
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

        if (in_array('settings', $sections, true)) {
            $context            = $origin === 'base' ? array('origin' => 'base') : array();
            $result['settings'] = wp_get_global_settings(array(), $context);
        }

        if (in_array('styles', $sections, true)) {
            $context          = $origin === 'base' ? array('origin' => 'base') : array();
            $result['styles'] = wp_get_global_styles(array(), $context);
        }

        if (in_array('user_overrides', $sections, true)) {
            $user_cpt = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles($theme);

            if (empty($user_cpt) || empty($user_cpt['post_content'])) {
                $result['user_overrides'] = null;
            } else {
                $decoded  = json_decode($user_cpt['post_content'], true);
                $settings = $decoded['settings'] ?? array();
                $styles   = $decoded['styles']   ?? array();

                $result['user_overrides'] = (empty($settings) && empty($styles))
                    ? null
                    : array(
                        'post_id'  => (int) $user_cpt['ID'],
                        'settings' => $settings,
                        'styles'   => $styles,
                    );
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // update-global-styles
    // -------------------------------------------------------------------------

    public function register_update_global_styles_ability()
    {
        wp_register_ability(
            'block-design-abilities/update-global-styles',
            array(
                'label'       => __('Update Global Styles', 'block-design-abilities'),
                'description' => __('Merges the provided settings and/or styles into the active theme\'s user overrides (wp_global_styles post). Only supplied keys are changed; everything else is preserved. Creates the post if it does not yet exist.', 'block-design-abilities'),
                'category'    => 'block-design-abilities',

                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(

                        'settings' => array(
                            'type'        => 'object',
                            'description' => __('Partial settings object to deep-merge into global settings (e.g. {"color":{"palette":[...]}}).', 'block-design-abilities'),
                        ),

                        'styles' => array(
                            'type'        => 'object',
                            'description' => __('Partial styles object to deep-merge into global styles (e.g. {"typography":{"fontSize":"1rem"}}).', 'block-design-abilities'),
                        ),

                    ),
                ),

                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'success' => array('type' => 'boolean'),
                        'post_id' => array('type' => 'integer'),
                        'created' => array('type' => 'boolean'),
                        'error'   => array('type' => 'string'),
                    ),
                ),

                'execute_callback'    => array($this, 'update_global_styles'),
                'permission_callback' => function () {
                    return current_user_can('edit_theme_options');
                },
                'meta' => array('mcp' => array('public' => true)),
            )
        );
    }

    /**
     * @param array{settings?: array<string, mixed>, styles?: array<string, mixed>} $input
     * @return array{success: bool, post_id?: int, created?: bool, error?: string}
     */
    public function update_global_styles(array $input = array()): array
    {
        if (empty($input['settings']) && empty($input['styles'])) {
            return array('success' => false, 'error' => 'At least one of "settings" or "styles" must be provided.');
        }

        $theme    = wp_get_theme();
        $user_cpt = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles($theme);

        $current = array();
        if (! empty($user_cpt) && ! empty($user_cpt['post_content'])) {
            $current = json_decode($user_cpt['post_content'], true) ?: array();
        }

        if (isset($input['settings'])) {
            $current['settings'] = $this->deep_merge($current['settings'] ?? array(), $input['settings']);
        }

        if (isset($input['styles'])) {
            $current['styles'] = $this->deep_merge($current['styles'] ?? array(), $input['styles']);
        }

        $created = false;

        if (! empty($user_cpt['ID'])) {
            $post_id = wp_update_post(array(
                'ID'           => (int) $user_cpt['ID'],
                'post_content' => wp_json_encode($current),
            ));
        } else {
            $created = true;
            $post_id = wp_insert_post(array(
                'post_name'    => 'wp-global-styles-' . $theme->get_stylesheet(),
                'post_title'   => 'Custom Styles',
                'post_type'    => 'wp_global_styles',
                'post_status'  => 'publish',
                'post_content' => wp_json_encode($current),
            ));
        }

        if (is_wp_error($post_id) || ! $post_id) {
            $message = is_wp_error($post_id) ? $post_id->get_error_message() : 'Failed to save global styles.';
            return array('success' => false, 'error' => $message);
        }

        WP_Theme_JSON_Resolver::clean_cached_data();

        return array(
            'success' => true,
            'post_id' => (int) $post_id,
            'created' => $created,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Recursive deep merge — $override values win on scalar conflicts.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function deep_merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deep_merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}
