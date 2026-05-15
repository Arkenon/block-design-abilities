# WordPress Block Design Skill
> A guide to converting design into a pattern using Fleks theme + block-design-mcp + wp-blockmarkup MCP tools.

---

## Workflow

```
1. Analyze the design
2. Get an example using mcp__wp-blockmarkup__get_block_markup if necessary
3. Write the block markup
4. Validate with mcp__wp-blockmarkup__validate_markup until VALID
5. Save to database with block-design-abilities/create-pattern
```

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
Category suggestions: `hero`, `team`, `about`, `fleks-cards`, `contact`, `faq`

### Update Existing Pattern
```json
{
  "ability_name": "block-design-abilities/update-pattern",
  "parameters": { "post_id": 36, "html": "..." }
}
```

### Read (Round-trip Editing)

`get-pattern`, `get-post`, `get-template` yetenekleri `html` (string) döndürür.
Bu HTML, `update-*` yeteneklerine doğrudan gönderilebilir:

```
list-patterns → get-pattern (html) → modify html → update-pattern (html)
list-posts    → get-post (html)    → modify html → update-post (html)
list-templates → get-template (html) → modify html → add-or-update-template (html)
```

### Validation
Pass raw block HTML to the `markup` parameter of `mcp__wp-blockmarkup__validate_markup`.
Do not proceed until it returns **VALID**. Common errors:
- Invalid block comment JSON
- Incorrect preset slug (like `var:preset|spacing|40`)
- Attribute mismatch with inline style

---

## Fleks Theme Design Tokens

### Color Presets (theme.json)
| Slug | Hex | Usage |
|------|-----|----------|
| `base` | `#FFFEFD` | Page background |
| `contrast` | `#18191a` | Dark text |
| `primary` | `#009E66` | Primary green accent |
| `hover` | `#00B380` | Hover state |
| `soft` | `#f0f9ee` | Light green background |

### Design Colors (Custom — Not Preset)
| Color | Hex | Usage |
|------|-----|----------|
| Cream background | `#F7F4EE` | Section bg |
| White card | `#FFFFFF` | Card bg |
| Card border | `#E5E2DC` | Thin border |
| Dark forest green | `#3C5A3F` | Left-border accent, CTA button |
| Sage green | `#8BA680` | Large numbers, label |
| Warm terracotta | `#A07840` | Colored span inside heading |
| Dark red | `#7A3D3D` | Alternative left-border |
| Gold | `#C49A38` | Alternative left-border |
| Medium gray | `#666666` | Content text |
| Light gray | `#9B9B9B` | Footnotes, label |

### Font Families (Slug → Class)
| Slug | CSS Class | Font |
|------|-----------|------|
| `font-sen` | `has-font-sen-font-family` | Sen (heading default) |
| `font-poppins` | `has-font-poppins-font-family` | Poppins (text default) |
| `font-system-serif` | `has-font-system-serif-font-family` | Baskerville/Georgia/Times (serif heading) |

### Font Sizes (Slug → Size → CSS Class)
| Slug | Size | Class |
|------|-------|-------|
| `xxx-small` | 0.625rem | `has-xxx-small-font-size` |
| `xx-small` | 0.75rem | `has-xx-small-font-size` |
| `x-small` | 0.875rem | `has-x-small-font-size` |
| `small` | 1rem | `has-small-font-size` |
| `medium` | 1.125rem | `has-medium-font-size` |
| `medium-plus` | 1.25rem | `has-medium-plus-font-size` |
| `medium-plus-plus` | 1.5rem | `has-medium-plus-plus-font-size` |
| `large` | 1.75rem | `has-large-font-size` |
| `large-plus` | 2rem | `has-large-plus-font-size` |
| `x-large` | 2.5rem | `has-x-large-font-size` |
| `xx-large` | 3rem | `has-xx-large-font-size` |
| `xxx-large` | 3.5rem | `has-xxx-large-font-size` |
| `huge-hg` | 4rem | `has-huge-hg-font-size` |

### Spacing Values (Slug → Approximate Size)
| Slug | Size |
|------|-------|
| `10` | 0.313rem |
| `20` | 0.75rem |
| `30` | ~1.5rem (fluid) |
| `40` | ~2.25rem (fluid) |
| `50` | ~3.4rem (fluid) |
| `60` | ~4.5rem (fluid) |
| `70` | ~6.7rem (fluid) |

In JSON: `"var:preset|spacing|40"` — In CSS: `var(--wp--preset--spacing--40)`

### Layout
- `contentSize`: 740px
- `wideSize`: 1280px (`align:"wide"` uses this size)

---

## Block Markup Rules

### Color Attribute Formats

**Preset (with slug):**
```json
{"textColor": "contrast"}
```
```html
class="has-contrast-color has-text-color"
```

**Custom (with hex):**
```json
{"style": {"color": {"text": "#555555"}}}
```
```html
class="has-text-color" style="color:#555555"
```

**Preset background:**
```json
{"backgroundColor": "primary"}
```
```html
class="has-primary-background-color has-background"
```

**Custom background:**
```json
{"style": {"color": {"background": "#F7F4EE"}}}
```
```html
class="has-background" style="background-color:#F7F4EE"
```

### Border

**Full border (custom color):**
```json
"style": {"border": {"color": "#E5E2DC", "width": "1px", "radius": "20px"}}
```
```html
class="has-border-color" style="border-color:#E5E2DC;border-radius:20px;border-width:1px"
```

**Single side (left accent):**
```json
"style": {"border": {"left": {"color": "#3C5A3F", "style": "solid", "width": "4px"}, "radius": "16px"}}
```
```html
style="border-radius:16px;border-left-color:#3C5A3F;border-left-style:solid;border-left-width:4px"
```

**Right divider line:**
```json
"style": {"border": {"right": {"color": "#E5E2DC", "style": "solid", "width": "1px"}}}
```
```html
style="border-right-color:#E5E2DC;border-right-style:solid;border-right-width:1px"
```

### Layout and blockGap Rule

| Layout Type | Is blockGap written to Inline Style? |
|-------------|--------------------------------------|
| `constrained` | **NO** — CSS custom property, do not add inline style |
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
Do **not** put inside the `style` object. Use top-level or do not use at all:
```json
{"shadow": "var:preset|shadow|lg"}   ✓
{"style": {"shadow": "..."}}          ✗
```

---

## Block Examples (Verified)

### core/paragraph
```html
<!-- wp:paragraph {"style":{"color":{"text":"#666666"},"typography":{"lineHeight":"1.7"}},"fontSize":"x-small"} -->
<p class="wp-block-paragraph has-text-color has-x-small-font-size" style="color:#666666;line-height:1.7">Text</p>
<!-- /wp:paragraph -->
```

### core/heading
```html
<!-- wp:heading {"level":2,"fontFamily":"font-system-serif","style":{"typography":{"fontWeight":"400","lineHeight":"1.3"}},"textColor":"contrast","fontSize":"xxx-large"} -->
<h2 class="wp-block-heading has-contrast-color has-text-color has-font-system-serif-font-family has-xxx-large-font-size" style="font-weight:400;line-height:1.3">Heading</h2>
<!-- /wp:heading -->
```

**Colored span inside heading** (no attribute, only inline):
```html
<h2 ...>Normal text <span style="color:#A07840">Colored text</span></h2>
```

**Italic inside heading:**
```html
<h1 ...>Normal <em style="color:#3C5A3F">Italic green</em> continues</h1>
```

### core/group (section wrapper)
```html
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"color":{"background":"#F7F4EE"}},"layout":{"type":"constrained"},"metadata":{"name":"Section Name"}} -->
<section class="wp-block-group alignfull has-background" style="background-color:#F7F4EE;padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--40)">
```

### core/group (white card)
```html
<!-- wp:group {"align":"wide","style":{"border":{"radius":"20px","width":"1px","color":"#E5E2DC"},"spacing":{"padding":{"top":"3rem","right":"3rem","bottom":"3rem","left":"3rem"}},"color":{"background":"#FFFFFF"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide has-background has-border-color" style="background-color:#FFFFFF;border-color:#E5E2DC;border-radius:20px;border-width:1px;padding-top:3rem;padding-right:3rem;padding-bottom:3rem;padding-left:3rem">
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
<!-- wp:list {"style":{"color":{"text":"#555555"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"fontSize":"x-small"} -->
<ul class="wp-block-list has-text-color has-x-small-font-size" style="color:#555555;margin-top:0;margin-bottom:0"><li>Item 1</li><li>Item 2</li></ul>
<!-- /wp:list -->
```

### core/separator
```html
<!-- wp:separator {"style":{"color":{"background":"#E5E2DC"},"spacing":{"margin":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
<hr class="wp-block-separator has-alpha-channel-opacity has-background" style="background-color:#E5E2DC;margin-top:var(--wp--preset--spacing--30);margin-bottom:var(--wp--preset--spacing--30)"/>
<!-- /wp:separator -->
```
> ⚠️ Element `<hr>` — not `<div>`.

### core/button (filled, custom color)
```html
<!-- wp:button {"style":{"border":{"radius":"50px"},"color":{"background":"#3C5A3F","text":"#FFFFFF"},"spacing":{"padding":{"top":"0.875rem","right":"2rem","bottom":"0.875rem","left":"2rem"}}},"fontSize":"x-small"} -->
<div class="wp-block-button has-custom-font-size has-x-small-font-size"><a class="wp-block-button__link has-text-color has-background wp-element-button" style="border-radius:50px;color:#FFFFFF;background-color:#3C5A3F;padding-top:0.875rem;padding-right:2rem;padding-bottom:0.875rem;padding-left:2rem">Button Text</a></div>
<!-- /wp:button -->
```

### core/button (outline, pill)
```html
<!-- wp:button {"className":"is-style-outline","style":{"border":{"radius":"50px"},"spacing":{"padding":{"top":"0.875rem","right":"2rem","bottom":"0.875rem","left":"2rem"}}},"fontSize":"x-small"} -->
<div class="wp-block-button is-style-outline has-custom-font-size has-x-small-font-size"><a class="wp-block-button__link wp-element-button" style="border-radius:50px;padding-top:0.875rem;padding-right:2rem;padding-bottom:0.875rem;padding-left:2rem">Button Text</a></div>
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
<!-- wp:paragraph {"style":{"color":{"text":"#3C5A3F"},"spacing":{"margin":{"bottom":"1rem"}}},"fontSize":"large"} -->
<p class="wp-block-paragraph has-text-color has-large-font-size" style="color:#3C5A3F;margin-bottom:1rem">🧠</p>
<!-- /wp:paragraph -->
```

| Emoji | Use Case |
|-------|----------------|
| 🧠 | Brain / Attention / Cognitive |
| 🩺 | Medical / ADHD / Clinical |
| 🤍 | Heart / Psychology / Counseling |
| 👤 | Profile / Person / User |
| 🎓 | Education / Certificate |
| 🏆 | Award / Achievement |
| 💬 | Opinion / Testimonial |
| 📅 | Appointment / Date |
| ✓ | Check / Feature list |
| → | CTA link read |
| • | List item (instead of core/list) |
| " " | Quote start/end |

**Note:** A native Icon block is expected in WordPress 7; this section will be updated then.

---

## Images: Use Placeholders

Use the theme's placeholder in the `core/image` block; the user will replace it with their own image later.

```html
<!-- wp:image {"id":1,"aspectRatio":"3/2","scale":"cover","sizeSlug":"full","linkDestination":"none","style":{"border":{"radius":"16px"}}} -->
<figure class="wp-block-image size-full has-custom-border">
  <img src="<?php echo esc_url(FLEKS_URI.'/assets/img/placeholder_horizontal.jpg') ?>" alt="" class="wp-image-1" style="border-radius:16px;aspect-ratio:3/2;object-fit:cover"/>
</figure>
<!-- /wp:image -->
```

Available placeholders (`themes/fleks/assets/img/`):
- `placeholder_horizontal.jpg` — For horizontal (3:2) images

> **If creating a pattern as a file**, use PHP `esc_url(FLEKS_URI . '...')`. If saving to the database with MCP `create-pattern`, use the full URL.

---

## Pattern Structure Template

```html
<!-- OUTER SECTION — Cream background -->
<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"color":{"background":"#F7F4EE"}},"layout":{"type":"constrained"},"metadata":{"name":"Section Name"}} -->
<section class="wp-block-group alignfull has-background" style="background-color:#F7F4EE;padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--40)">

  <!-- INNER CARD — White, bordered, rounded -->
  <!-- wp:group {"align":"wide","style":{"border":{"radius":"20px","width":"1px","color":"#E5E2DC"},"spacing":{"padding":{"top":"3rem","right":"3rem","bottom":"3rem","left":"3rem"}},"color":{"background":"#FFFFFF"}},"layout":{"type":"constrained"}} -->
  <div class="wp-block-group alignwide has-background has-border-color" style="background-color:#FFFFFF;border-color:#E5E2DC;border-radius:20px;border-width:1px;padding-top:3rem;padding-right:3rem;padding-bottom:3rem;padding-left:3rem">

    <!-- Content goes here -->

  </div>
  <!-- /wp:group -->

</section>
<!-- /wp:group -->
```

---

## Pattern as a File (themes/fleks/patterns/)

```php
<?php
/**
 * Title: Pattern Title
 * Slug: fleks/pattern-slug
 * Categories: fleks-cards
 * Keywords: keyword, words
 */
?>

<!-- block markup goes here -->
```

Available category slugs: `fleks-cards`, `fleks-two-columns`, `fleks-cards`  
New categories are created automatically.

---

## Common Mistakes

| Mistake | Correction |
|------|---------|
| Using `<div>` for `core/separator` | Use `<hr>` |
| Adding `<!-- wp:list-item -->` inside `core/list` | Just `<li>` is enough |
| Adding `gap:...` inline style in Constrained layout | Only add in flex layout |
| Putting `"shadow":"..."` inside the `style` object | Top-level attribute or do not use |
| Adding inline `font-family` style for a preset font | CSS class is enough, do not add inline style |
| Running create-pattern without validating | Always validate first |
| Writing text with special characters directly to HTML | Use HTML entity or UTF-8 |
