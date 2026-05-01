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

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-template-abilities.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-post-abilities.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-theme-json-abilities.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-pattern-abilities.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-block-design-abilities.php';

new Block_Design_Abilities();
