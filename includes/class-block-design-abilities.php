<?php

defined('ABSPATH') || exit;
class Block_Design_Abilities
{
    public function __construct()
    {
        add_action('wp_abilities_api_categories_init', array($this, 'register_ability_category'));

        new Block_Design_Abilities_Templates();
        new Block_Design_Abilities_Posts();
        new Block_Design_Abilities_Global_Styles();
        new Block_Design_Abilities_Patterns();
    }

    public function register_ability_category()
    {
        wp_register_ability_category(
            'block-design-abilities',
            array(
                'label'       => 'Block Design Abilities',
                'description' => 'Abilities for Block Design Abilities MCP content analysis.',
            )
        );
    }

    /**
     * Resolves block array from ability input.
     *
     * When 'html' is provided, parses serialized block markup (WordPress block comment format)
     * using WordPress core parse_blocks(). Falls back to 'blocks' array if 'html' is absent.
     *
     * @param array $input Ability input parameters.
     * @return array|WP_Error Block array on success, WP_Error on failure.
     */
    public static function resolve_blocks(array $input)
    {
        if (! empty($input['html'])) {
            $blocks = parse_blocks($input['html']);
            $blocks = array_values(array_filter($blocks, function ($block) {
                return ! empty($block['blockName']);
            }));
            if (empty($blocks)) {
                return new WP_Error('parse_failed', __('No valid blocks found in the provided block markup.', 'block-design-abilities'));
            }
            return $blocks;
        }

        if (! empty($input['blocks'])) {
            return $input['blocks'];
        }

        return new WP_Error('missing_input', __('Either html or blocks must be provided.', 'block-design-abilities'));
    }
}
