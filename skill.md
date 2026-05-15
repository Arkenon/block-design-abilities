# WordPress Block Design Skill
> A guide to converting design into a pattern using block-design-mcp + wp-blockmarkup MCP tools, aligned with the active theme's design system.

---

## Workflow

```
0. Read active theme design tokens (get-global-styles)
1. Analyze the design
2. Get block markup examples using mcp__wp-blockmarkup__get_block_markup if necessary
3. Write the block markup using the theme's actual preset slugs and values
4. Validate with mcp__wp-blockmarkup__validate_markup until VALID
5. Save to database with block-design-abilities/create-pattern
```

---

## Step 0: Read the Active Theme's Design Tokens

**Always do this first**, before writing any markup. Call:

```json
{
  "ability_name": "block-design-abilities/get-global-styles",
  "parameters": { "origin": "all", "sections": ["settings", "styles"] }
}
```

From the response, extract and use:

| What to look for | Where in the response | How to use in markup |
|---|---|---|
| Color palette slugs + hex values | `settings.color.palette` | `"textColor": "slug"` → class `has-{slug}-color` |
| Font family slugs | `settings.typography.fontFamilies` | `"fontFamily": "slug"` → class `has-{slug}-font-family` |
| Font size slugs + values | `settings.typography.fontSizes` | `"fontSize": "slug"` → class `has-{slug}-font-size` |
| Spacing scale slugs + values | `settings.spacing.spacingSizes` | `"var:preset|spacing|{slug}"` → `var(--wp--preset--spacing--{slug})` |
| Shadow presets | `settings.shadow.presets` | `"shadow": "var:preset|shadow|{slug}"` (top-level only) |
| Layout sizes | `settings.layout` | `contentSize` and `wideSize` for reference |

**Preset slugs generate CSS classes and custom properties — do not write inline styles for them.**

Custom hex values that do not exist as presets should be passed via the `style` object.

---

## MCP Tools

### Discover Abilities
```
mcp__block-design-mcp__mcp-adapter-discover-abilities
```

### Create Pattern
```json
{
  "ability_name": "block-design-abilities/create-pattern",
  "parameters": {
    "title": "Pattern Title",
    "description": "Short description",
    "html": "<!-- block markup -->",
    "categories": ["hero"],
    "sync_status": "unsynced"
  }
}
```
Common category slugs: `hero`, `team`, `about`, `contact`, `faq`, `cards`  
New category slugs are created automatically if they do not exist.

### Update Existing Pattern
```json
{
  "ability_name": "block-design-abilities/update-pattern",
  "parameters": { "post_id": 36, "html": "..." }
}
```

### Read (Round-trip Editing)

`get-pattern`, `get-post`, `get-template` return `html` (string).  
That HTML can be passed directly to the corresponding `update-*` ability:

```
list-patterns  → get-pattern (html)  → modify html → update-pattern (html)
list-posts     → get-post (html)     → modify html → update-post (html)
list-templates → get-template (html) → modify html → add-or-update-template (html)
```

### Validation
Pass raw block HTML to `mcp__wp-blockmarkup__validate_markup`.  
Do not proceed until it returns **VALID**. Common errors:
- Invalid block comment JSON
- Incorrect preset slug format (correct: `"var:preset|spacing|40"`)
- Attribute mismatch with inline style

---

## Block Markup Rules

### Using Theme Preset Slugs

Always prefer preset slugs over hardcoded values when the theme defines them:

**Text color (preset):**
```json
{"textColor": "primary"}
```
```html
class="has-primary-color has-text-color"
```

**Text color (custom hex — not in preset):**
```json
{"style": {"color": {"text": "#555555"}}}
```
```html
class="has-text-color" style="color:#555555"
```

**Background (preset):**
```json
{"backgroundColor": "soft"}
```
```html
class="has-soft-background-color has-background"
```

**Background (custom hex):**
```json
{"style": {"color": {"background": "#F0EEE8"}}}
```
```html
class="has-background" style="background-color:#F0EEE8"
```

**Font family (preset slug):**
```json
{"fontFamily": "font-sans"}
```
```html
class="has-font-sans-font-family"
```
> Do NOT add inline `font-family` style — the CSS class is sufficient.

**Font size (preset slug):**
```json
{"fontSize": "large"}
```
```html
class="has-large-font-size"
```

**Spacing (preset slug) — in JSON vs CSS:**
```
JSON attribute:  "var:preset|spacing|60"
CSS inline:      var(--wp--preset--spacing--60)
```

### Border

**Full border (custom color):**
```json
"style": {"border": {"color": "#DDDDDD", "width": "1px", "radius": "20px"}}
```
```html
class="has-border-color" style="border-color:#DDDDDD;border-radius:20px;border-width:1px"
```

**Single side (left accent):**
```json
"style": {"border": {"left": {"color": "#336655", "style": "solid", "width": "4px"}, "radius": "16px"}}
```
```html
style="border-radius:16px;border-left-color:#336655;border-left-style:solid;border-left-width:4px"
```

**Right divider:**
```json
"style": {"border": {"right": {"color": "#DDDDDD", "style": "solid", "width": "1px"}}}
```
```html
style="border-right-color:#DDDDDD;border-right-style:solid;border-right-width:1px"
```

### Layout and blockGap Rule

| Layout Type | blockGap in Inline Style? |
|-------------|--------------------------|
| `constrained` | **NO** — CSS custom property, omit from inline style |
| `flex` | **YES** — Add as `gap:0.625rem` |

```html
<!-- constrained: NO gap inline style -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group">

<!-- flex: YES gap inline style -->
<!-- wp:group {"style":{"spacing":{"blockGap":"0.625rem"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group" style="gap:0.625rem">
```

### shadow Attribute
Must be **top-level** — never inside the `style` object:
```json
{"shadow": "var:preset|shadow|lg"}   ✓
{"style": {"shadow": "..."}}          ✗
```

---

## Block Examples (Verified)

> Color, font, and spacing values in examples below are **illustrative**.  
> Replace with actual preset slugs or hex values read from the active theme's `get-global-styles`.

### core/paragraph
```html
<!-- wp:paragraph {"style":{"color":{"text":"#555555"},"typography":{"lineHeight":"1.7"}},"fontSize":"small"} -->
<p class="wp-block-paragraph has-text-color has-small-font-size" style="color:#555555;line-height:1.7">Text</p>
<!-- /wp:paragraph -->
```

### core/heading
```html
<!-- wp:heading {"level":2,"fontFamily":"font-serif","style":{"typography":{"fontWeight":"400","lineHeight":"1.3"}},"textColor":"contrast","fontSize":"xx-large"} -->
<h2 class="wp-block-heading has-contrast-color has-text-color has-font-serif-font-family has-xx-large-font-size" style="font-weight:400;line-height:1.3">Heading</h2>
<!-- /wp:heading -->
```

**Colored span inside heading** (no block attribute, only inline HTML):
```html
<h2 ...>Normal text <span style="color:#A07840">Colored text</span></h2>
```

**Italic inside heading:**
```html
<h1 ...>Normal <em style="color:#336655">Italic accent</em> continues</h1>
```

### core/group (section wrapper)
```html
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"color":{"background":"#F5F3EE"}},"layout":{"type":"constrained"},"metadata":{"name":"Section Name"}} -->
<section class="wp-block-group alignfull has-background" style="background-color:#F5F3EE;padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--40)">
```

### core/group (white card)
```html
<!-- wp:group {"align":"wide","style":{"border":{"radius":"20px","width":"1px","color":"#E0DDDA"},"spacing":{"padding":{"top":"3rem","right":"3rem","bottom":"3rem","left":"3rem"}},"color":{"background":"#FFFFFF"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide has-background has-border-color" style="background-color:#FFFFFF;border-color:#E0DDDA;border-radius:20px;border-width:1px;padding-top:3rem;padding-right:3rem;padding-bottom:3rem;padding-left:3rem">
```

### core/columns
```html
<!-- wp:columns {"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|40","left":"var:preset|spacing|50"}}}} -->
<div class="wp-block-columns">
```

### core/column
```html
<!-- wp:column {"verticalAlignment":"center","width":"55%"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:55%">
```

### core/list (NO sub-block — just `<li>`)
```html
<!-- wp:list {"style":{"color":{"text":"#555555"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"fontSize":"small"} -->
<ul class="wp-block-list has-text-color has-small-font-size" style="color:#555555;margin-top:0;margin-bottom:0"><li>Item 1</li><li>Item 2</li></ul>
<!-- /wp:list -->
```

### core/separator
```html
<!-- wp:separator {"style":{"color":{"background":"#DDDDDD"},"spacing":{"margin":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
<hr class="wp-block-separator has-alpha-channel-opacity has-background" style="background-color:#DDDDDD;margin-top:var(--wp--preset--spacing--30);margin-bottom:var(--wp--preset--spacing--30)"/>
<!-- /wp:separator -->
```
> ⚠️ Element is `<hr>` — not `<div>`.

### core/button (filled, custom color)
```html
<!-- wp:button {"style":{"border":{"radius":"50px"},"color":{"background":"#336655","text":"#FFFFFF"},"spacing":{"padding":{"top":"0.875rem","right":"2rem","bottom":"0.875rem","left":"2rem"}}},"fontSize":"small"} -->
<div class="wp-block-button has-custom-font-size has-small-font-size"><a class="wp-block-button__link has-text-color has-background wp-element-button" style="border-radius:50px;color:#FFFFFF;background-color:#336655;padding-top:0.875rem;padding-right:2rem;padding-bottom:0.875rem;padding-left:2rem">Button Text</a></div>
<!-- /wp:button -->
```

### core/button (outline, pill)
```html
<!-- wp:button {"className":"is-style-outline","style":{"border":{"radius":"50px"},"spacing":{"padding":{"top":"0.875rem","right":"2rem","bottom":"0.875rem","left":"2rem"}}},"fontSize":"small"} -->
<div class="wp-block-button is-style-outline has-custom-font-size has-small-font-size"><a class="wp-block-button__link wp-element-button" style="border-radius:50px;padding-top:0.875rem;padding-right:2rem;padding-bottom:0.875rem;padding-left:2rem">Button Text</a></div>
<!-- /wp:button -->
```

### core/buttons (wrapper)
```html
<!-- wp:buttons {"style":{"spacing":{"blockGap":"0.5rem"}}} -->
<div class="wp-block-buttons">
  ...buttons...
</div>
<!-- /wp:buttons -->
```

---

## Icons: Use UTF-8 Emoji

Prefer emojis inside `core/paragraph` instead of SVG/HTML blocks. The user can change them if they wish.

```html
<!-- wp:paragraph {"textColor":"primary","style":{"spacing":{"margin":{"bottom":"1rem"}}},"fontSize":"large"} -->
<p class="wp-block-paragraph has-primary-color has-text-color has-large-font-size" style="margin-bottom:1rem">🧠</p>
<!-- /wp:paragraph -->
```

| Emoji | Use Case |
|-------|----------------|
| 🧠 | Brain / Attention / Cognitive |
| 🩺 | Medical / Clinical |
| 🤍 | Heart / Psychology / Counseling |
| 👤 | Profile / Person / User |
| 🎓 | Education / Certificate |
| 🏆 | Award / Achievement |
| 💬 | Opinion / Testimonial |
| 📅 | Appointment / Date |
| ✓ | Check / Feature list |
| → | CTA link |
| • | List item (alternative to core/list) |
| " " | Quote marks |

**Note:** A native Icon block is expected in WordPress 7; this section will be updated then.

---

## Images: Use Placeholders

Use a placeholder image in the `core/image` block; the user will replace it with their own image later.

**When saving to the database via MCP `create-pattern`:** use the full URL.

**When creating a pattern as a PHP file:** use the theme constant to build the path:

```php
<!-- wp:image {"id":1,"aspectRatio":"3/2","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"border":{"radius":"16px"}}} -->
<figure class="wp-block-image size-full has-custom-border">
  <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/placeholder_horizontal.jpg' ) ?>" alt="" class="wp-image-1" style="border-radius:16px;aspect-ratio:3/2;object-fit:cover"/>
</figure>
<!-- /wp:image -->
```

> Use `get_template_directory_uri()` (works for any theme) instead of a theme-specific constant.  
> Check that the placeholder file exists in the active theme before referencing it; if not, use a generic public image URL.

---

## Pattern Structure Template

The section background and card border colors below are **generic placeholders** — replace with the active theme's actual preset slugs or hex values from `get-global-styles`.

```html
<!-- OUTER SECTION -->
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"constrained"},"metadata":{"name":"Section Name"}} -->
<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--40)">

  <!-- INNER CARD — White, bordered, rounded -->
  <!-- wp:group {"align":"wide","style":{"border":{"radius":"20px","width":"1px","color":"#E0DDDA"},"spacing":{"padding":{"top":"3rem","right":"3rem","bottom":"3rem","left":"3rem"}},"color":{"background":"#FFFFFF"}},"layout":{"type":"constrained"}} -->
  <div class="wp-block-group alignwide has-background has-border-color" style="background-color:#FFFFFF;border-color:#E0DDDA;border-radius:20px;border-width:1px;padding-top:3rem;padding-right:3rem;padding-bottom:3rem;padding-left:3rem">

    <!-- Content goes here -->

  </div>
  <!-- /wp:group -->

</section>
<!-- /wp:group -->
```

---

## Pattern as a PHP File (themes/{active-theme}/patterns/)

```php
<?php
/**
 * Title: Pattern Title
 * Slug: {theme-slug}/pattern-slug
 * Categories: cards
 * Keywords: keyword, words
 */
?>

<!-- block markup goes here -->
```

- The file goes in `wp-content/themes/{active-theme}/patterns/`
- The slug prefix should match the active theme slug (e.g. `mytheme/hero-section`)
- New category slugs are created automatically

---

## Common Mistakes

| Mistake | Correction |
|------|---------|
| Hardcoding a specific theme's color values | Read `get-global-styles` first; use preset slugs when available |
| Using `<div>` for `core/separator` | Use `<hr>` |
| Adding `<!-- wp:list-item -->` inside `core/list` | Just `<li>` is enough |
| Adding `gap:...` inline style in Constrained layout | Only add in flex layout |
| Putting `"shadow":"..."` inside the `style` object | Top-level attribute or do not use |
| Adding inline `font-family` style for a preset font | CSS class is enough, do not add inline style |
| Running create-pattern without validating | Always validate first |
| Writing text with special characters directly to HTML | Use HTML entity or UTF-8 |
| Using a theme-specific URI constant in PHP pattern files | Use `get_template_directory_uri()` instead |
