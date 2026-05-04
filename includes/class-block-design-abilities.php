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
}
