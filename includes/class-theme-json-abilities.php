<?php

class Block_Design_Abilities_Theme_Json
{
    public function __construct()
    {
        add_action('wp_abilities_api_init', array($this, 'register_get_theme_json_ability'));
    }

    public function register_get_theme_json_ability()
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

                'execute_callback'    => array($this, 'get_theme_json'),
                'permission_callback' => function () {
                    return current_user_can('edit_theme_options');
                },
                'meta' => array('mcp' => array('public' => true)),
            )
        );
    }

    public function get_theme_json(array $input = array()): array
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
}
