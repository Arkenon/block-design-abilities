# Block Design Abilities

A WordPress Abilities API plugin for creating and modifying block templates, pages, posts, and patterns using AI.

- **Author:** Arkenon
- **Version:** 1.0.2
- **License:** GPL v2 or later
- **GitHub:** https://github.com/Arkenon/block-design-abilities

---

## Requirements

| Requirement | Minimum Version |
|---|---|
| WordPress | 6.9+ |
| PHP | 7.4+ |
| Block Theme | Required |

Multisite network compatible.

### Dependencies

[wp-blockmarkup-mcp](https://github.com/pluginslab/wp-blockmarkup-mcp) is a local MCP server that extracts, validates, and indexes every Gutenberg block from WordPress core, WooCommerce, or any block-based plugin you work with. It gives AI tools like Claude Code a verified database of block schemas, attributes, and validated markup examples to query — instead of relying on training data that hallucinates block structures, invents attributes, and produces markup that triggers "Attempt Block Recovery" in the editor.

[mcp-adapter-wordpress-remote](https://github.com/wordpress/mcp-adapter) bridges WordPress's Abilities API with the MCP specification, providing a standardized way for AI agents to interact with WordPress functionality. It includes HTTP and STDIO transport support, comprehensive error handling, and an extensible architecture for custom integrations.

---

## Installation

1. Download .zip file from GitHub. (https://github.com/Arkenon/block-design-abilities)
2. Upload .zip file via WordPress admin panel.
3. Activate the plugin from the WordPress admin panel.
4. The plugin automatically registers all abilities under the `block-design-abilities` category.

---

## MCP Usage

This plugin exposes its abilities via MCP (Model Context Protocol), allowing any MCP-compatible client to interact with your WordPress site directly.

*You can get an example of an MCP client config file (such as Claude Code, Cursor, Codex, Antigravity, or any other MCP-compatible client):

```json
{
  "mcpServers": {
    "wp-blockmarkup": {
      "command": "npx",
      "args": [
        "wp-blockmarkup-mcp"
      ]
    },
    "block-design-mcp": {
      "command": "npx",
      "args": [
        "-y",
        "@automattic/mcp-wordpress-remote@latest"
      ],
      "env": {
        "WP_API_URL": "http://your-site.com/wp-json/mcp/mcp-adapter-default-server",
        "LOG_FILE": "/path/to/logs/mcp-adapter.log",
        "WP_API_USERNAME": "your-username",
        "WP_API_PASSWORD": "your-password"
      }
    }
  }
}
```

| Field | Description |
|---|---|
| `WP_API_URL` | Your site's MCP adapter endpoint. Replace `my-wp-site.com` with your domain. |
| `LOG_FILE` | Optional. Path for MCP transport logs. |
| `WP_API_USERNAME` | WordPress username with sufficient permissions (`edit_posts` / `edit_theme_options`). |
| `WP_API_PASSWORD` | WordPress application password for the user above. |

Once connected, all abilities registered under the `block-design-abilities` category are available to the client automatically.

## Overview

This plugin is built on top of the WordPress Abilities API. It exposes abilities for AI agents across four content types:

| Class | Content Type |
|---|---|
| `Block_Design_Abilities_Templates` | Block Templates (`wp_template`) |
| `Block_Design_Abilities_Posts` | Posts and Pages |
| `Block_Design_Abilities_Patterns` | Block Patterns (`wp_block`) |
| `Block_Design_Abilities_Global_Styles` | Design Tokens (theme.json) |

---

## Abilities

### Templates

Read and write block templates for the active theme.

#### `list-templates`

Returns all block templates for the active theme.

**Permission:** `edit_theme_options`

**Parameters:** None

**Returned Fields:**

| Field | Type | Description |
|---|---|---|
| `theme` | string | Active theme slug |
| `templates` | array | List of templates |
| `templates[].slug` | string | Template slug |
| `templates[].title` | string | Template title |
| `templates[].post_id` | int\|null | Database post ID, if a DB record exists |

Templates are sorted alphabetically by slug. `post_id` is only present for templates previously saved via the Site Editor.

---

#### `get-template`

Returns a single template's serialized block markup.

**Permission:** `edit_theme_options`

**Parameters:**

| Field | Type | Required | Description |
|---|---|---|---|
| `slug` | string | Yes | Template slug from `list-templates` |

**Returned Fields:**

| Field | Type | Description |
|---|---|---|
| `slug` | string | Template slug |
| `title` | string | Template title |
| `post_id` | int\|null | Database post ID, if a DB record exists |
| `html` | string | Serialized block markup (WordPress comment format) |

Whitespace-only (comment) blocks are automatically filtered out.

---

#### `add-or-update-template`

Saves or creates a block template. Provide either `post_id` or `slug`, not both.

**Permission:** `edit_theme_options`

**Parameters:**

| Field | Type | Required | Description |
|---|---|---|---|
| `html` | string | Yes | Serialized block markup (WordPress comment format) |
| `post_id` | int | No | Existing template post ID (for updates) |
| `slug` | string | No | Theme file template slug (to create a new DB record) |

- When `post_id` is provided: updates via `wp_update_post()`.
- When `slug` is provided: creates a new database record from the theme file via `wp_insert_post()`.
- Both cannot be provided simultaneously.

**Returned Fields:**

| Field | Type | Description |
|---|---|---|
| `success` | bool | Operation result |
| `post_id` | int | Created or updated post ID |
| `error` | string\|null | Error message |

---

### Posts and Pages

List, read, and update post and page content.

#### `list-posts`

Returns a paginated list of posts and pages.

**Permission:** `edit_posts`

**Parameters:**

| Field | Type | Default | Description |
|---|---|---|---|
| `post_type` | string | `"any"` | `"post"`, `"page"`, or `"any"` |
| `posts_per_page` | int | `10` | Results per page (max 50) |
| `paged` | int | `1` | Page number |
| `s` | string | — | Keyword search in title/content |
| `orderby` | string | `"title"` | `"title"`, `"date"`, `"modified"`, or `"ID"` |
| `order` | string | `"ASC"` | `"ASC"` or `"DESC"` |

**Returned Fields:**

| Field | Type | Description |
|---|---|---|
| `posts` | array | List of posts |
| `posts[].post_id` | int | Post ID |
| `posts[].post_name` | string | URL slug |
| `posts[].title` | string | Title |
| `posts[].post_type` | string | `post` or `page` |
| `posts[].status` | string | Publication status |
| `posts[].modified` | string | Last modified date |
| `posts[].url` | string | Front-end URL |
| `pagination` | object | Pagination info |
| `pagination.total_posts` | int | Total post count |
| `pagination.total_pages` | int | Total page count |
| `pagination.current_page` | int | Current page |
| `pagination.has_more` | bool | Whether more pages exist |

---

#### `get-post`

Returns a single post or page's serialized block markup.

**Permission:** `edit_posts`

**Parameters:**

| Field | Type | Required | Description |
|---|---|---|---|
| `post_id` | int | Yes | Post or page ID |

Only supports `post` and `page` types; templates cannot be retrieved via this ability.

**Returned Fields:**

| Field | Type | Description |
|---|---|---|
| `post_id` | int | Post ID |
| `post_name` | string | URL slug |
| `title` | string | Title |
| `post_type` | string | Content type |
| `url` | string | Front-end URL |
| `html` | string | Serialized block markup (WordPress comment format) |

---

#### `update-post`

Saves updated block content to a post or page.

**Permission:** `edit_posts`

**Parameters:**

| Field | Type | Required | Description |
|---|---|---|---|
| `post_id` | int | Yes | Post ID to update |
| `html` | string | Yes | Serialized block markup (WordPress comment format) |
| `title` | string | No | New title |

**Returned Fields:**

| Field | Type | Description |
|---|---|---|
| `success` | bool | Operation result |
| `post_id` | int | Post ID |
| `post_type` | string | Content type |
| `url` | string | Front-end URL |
| `error` | string\|null | Error message |

---

### Patterns

List, read, update, duplicate, and create block patterns. Two sources are supported:

- **Registry:** Read-only patterns registered by themes or plugins via PHP.
- **Database:** Editable `wp_block` posts created via the Site Editor.

#### `list-patterns`

Lists patterns from both the registry and the database.

**Permission:** `edit_posts`

**Parameters:**

| Field | Type | Default | Description |
|---|---|---|---|
| `source` | string | `"all"` | `"all"`, `"registry"`, or `"database"` |
| `category` | string | — | Filter registry patterns by category slug |
| `search` | string | — | Filter by keyword in title |

WordPress core patterns prefixed with `core/` are excluded from results.

**Returned Fields:**

| Field | Type | Description |
|---|---|---|
| `registry_patterns` | array | Registered (read-only) patterns |
| `database_patterns` | array | Database patterns (editable) |
| `database_patterns[].sync_status` | string | `"synced"` or `"unsynced"` |
| `database_patterns[].categories` | array | Assigned categories |
| `totals` | object | `registry` and `database` counts |

---

#### `get-pattern`

Returns a single pattern's content as a parsed block array.

**Permission:** `edit_posts`

**Parameters:**

| Field | Type | Required | Description |
|---|---|---|---|
| `source` | string | Yes | `"registry"` or `"database"` |
| `slug` | string | If registry | Pattern slug |
| `post_id` | int | If database | `wp_block` post ID |

**Returned Fields:**

| Field | Type | Description |
|---|---|---|
| `source` | string | Source type |
| `slug` | string | Pattern slug |
| `title` | string | Pattern title |
| `post_id` | int\|null | Database post ID |
| `sync_status` | string\|null | Sync status |
| `is_editable` | bool | Whether the pattern can be edited |
| `html` | string | Serialized block markup (WordPress comment format) |

---

#### `update-pattern`

Saves updated block content to a database pattern. Only `wp_block` posts are editable; registry patterns are read-only.

**Permission:** `edit_posts`

**Parameters:**

| Field | Type | Required | Description |
|---|---|---|---|
| `post_id` | int | Yes | `wp_block` post ID |
| `html` | string | Yes | Serialized block markup (WordPress comment format) |
| `title` | string | No | New title |

**Returned Fields:**

| Field | Type | Description |
|---|---|---|
| `success` | bool | Operation result |
| `post_id` | int | Post ID |
| `title` | string | Pattern title |
| `sync_status` | string | Sync status |
| `error` | string\|null | Error message |

---

#### `duplicate-pattern`

Copies a read-only registry pattern into an editable `wp_block` post.

**Permission:** `edit_posts`

**Parameters:**

| Field | Type | Required | Description |
|---|---|---|---|
| `slug` | string | Yes | Registry pattern slug to copy |
| `title` | string | No | New title (defaults to original + `" (Copy)"`) |
| `sync_status` | string | No | `"synced"` or `"unsynced"` (default: `"unsynced"`) |

Categories from the original pattern are automatically assigned to the new post.

**Returned Fields:**

| Field | Type | Description |
|---|---|---|
| `success` | bool | Operation result |
| `post_id` | int | Created post ID |
| `title` | string | Title |
| `slug` | string | New pattern slug |
| `sync_status` | string | Sync status |
| `original_slug` | string | Original registry slug |
| `html` | string | Serialized block markup (WordPress comment format) |
| `error` | string\|null | Error message |

---

#### `create-pattern`

Creates a new block pattern from scratch.

**Permission:** `edit_posts`

**Parameters:**

| Field | Type | Required | Description |
|---|---|---|---|
| `title` | string | Yes | Pattern title |
| `html` | string | Yes | Serialized block markup (WordPress comment format) |
| `description` | string | No | Pattern description |
| `categories` | array | No | List of category slugs |
| `sync_status` | string | No | `"synced"` or `"unsynced"` (default: `"unsynced"`) |

Category slugs that do not exist are automatically created in the `wp_pattern_category` taxonomy.

**Returned Fields:**

| Field | Type | Description |
|---|---|---|
| `success` | bool | Operation result |
| `post_id` | int | Created post ID |
| `title` | string | Title |
| `slug` | string | Pattern slug |
| `sync_status` | string | Sync status |
| `categories` | array | Assigned categories |
| `html` | string | Serialized block markup (WordPress comment format) |
| `error` | string\|null | Error message |

---

### Global Styles

Read and write the active theme's design tokens and user style overrides.

#### `get-global-styles`

Returns the active theme's design tokens from `theme.json` (colors, typography, spacing, etc.).

**Permission:** `edit_theme_options`

**Parameters:**

| Field | Type | Default | Description |
|---|---|---|---|
| `origin` | string | `"all"` | `"all"`: theme + user customizations merged; `"base"`: theme file only |
| `sections` | array | all | Filter returned sections: `"settings"`, `"styles"`, `"user_overrides"`, `"theme_info"` |

**Returned Sections:**

**`theme_info`**

| Field | Type | Description |
|---|---|---|
| `name` | string | Theme name |
| `stylesheet` | string | Theme stylesheet slug |
| `version` | string | Theme version |
| `is_block_theme` | bool | Whether it is a block theme |
| `has_theme_json` | bool | Whether a `theme.json` file exists |
| `has_user_overrides` | bool | Whether user customizations exist |

**`settings`** — Color, font, and spacing tokens via `wp_get_global_settings()`.

**`styles`** — Global CSS values via `wp_get_global_styles()`.

**`user_overrides`** — Site Editor customizations. Returns `null` when no customizations exist; otherwise contains `post_id`, `settings`, and `styles`.

---

#### `update-global-styles`

Merges the provided settings and/or styles into the active theme's user overrides (`wp_global_styles` post). Only supplied keys are changed; everything else is preserved. Creates the post if it does not yet exist.

**Permission:** `edit_theme_options`

**Parameters:**

| Field | Type | Required | Description |
|---|---|---|---|
| `settings` | object | No* | Partial settings object to deep-merge into global settings (e.g. `{"color":{"palette":[...]}}`) |
| `styles` | object | No* | Partial styles object to deep-merge into global styles (e.g. `{"typography":{"fontSize":"1rem"}}`) |

*At least one of `settings` or `styles` must be provided.

**Returned Fields:**

| Field | Type | Description |
|---|---|---|
| `success` | bool | Operation result |
| `post_id` | int | Created or updated `wp_global_styles` post ID |
| `created` | bool | `true` if a new post was created; `false` if an existing post was updated |
| `error` | string\|null | Error message |

---

## Technical Details

### Block Processing

The plugin works comfortable with [wp-blockmarkup-mcp](https://github.com/pluginslab/wp-blockmarkup-mcp). It uses it to validate and process block markup.

The plugin works comfortable with [wordpress-mcp](https://github.com/Automattic/wordpress-mcp). It uses it to fetch and update posts, pages, templates, theme.json, etc.

Abilities in the plugin, serialize block markup before sending it to the client and save it into the database as a string (block markup).

### Permission Requirements

| Ability Group | Required Capability |
|---|---|
| Templates, Theme JSON | `edit_theme_options` |
| Posts, Pages, Patterns | `edit_posts` |

### Content Types

| Content | WordPress Type | Source |
|---|---|---|
| Templates | `wp_template` | Theme file or database |
| Posts | `post` | Database |
| Pages | `page` | Database |
| Patterns (registered) | Registry | Theme / plugin PHP |
| Patterns (database) | `wp_block` | Database |

### Ability Registration Flow

1. The main plugin file `require`s all class files.
2. `Block_Design_Abilities` (main class) is instantiated.
3. The `block-design-abilities` category is registered on the `wp_abilities_api_categories_init` hook.
4. Each sub-class listens to the `wp_abilities_api_init` hook to register its own abilities.
5. Each ability defines `input_schema`, `output_schema`, `execute_callback`, and `permission_callback`.

---

## File Structure

```
block-design-abilities/
├── block-design-abilities.php              # Entry point; loads all classes
├── includes/
│   ├── class-block-design-abilities.php    # Main class; category registration
│   ├── class-template-abilities.php        # Template abilities (3 abilities)
│   ├── class-post-abilities.php            # Post/page abilities (3 abilities)
│   ├── class-pattern-abilities.php         # Pattern abilities (5 abilities)
│   └── global-styles-abilities.php         # Global styles abilities (2 abilities)
├── README.md
└── LICENSE
```

---

## Credit
[pluginslab/wp-blockmarkup-mcp](https://github.com/pluginslab/wp-blockmarkup-mcp)
[wordpress/mcp-adapter](https://github.com/wordpress/mcp-adapter)

## License

GPL v2 or later. See [LICENSE](LICENSE) for details.
