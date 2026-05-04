<?php

/**
 * Plugin Name:       Block Design Abilities
 * Description:       Create or modify block templates, pages, posts or patterns using AI.
 * Author:            Arkenon
 * Version:           1.0.1
 * Network:           true
 * Author URI:        https://github.com/Arkenon
 * Text Domain:       block-design-abilities
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.opensource.org/licenses/GPL-2.0
 * GitHub Plugin URI: https://github.com/Arkenon/block-design-abilities
 * Requires at least: 6.9
 * Requires PHP:      7.4
 */

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-template-abilities.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-post-abilities.php';
require_once plugin_dir_path(__FILE__) . 'includes/global-styles-abilities.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-pattern-abilities.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-block-design-abilities.php';

new Block_Design_Abilities();
