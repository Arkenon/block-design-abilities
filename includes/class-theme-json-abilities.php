<?php
defined('ABSPATH') || exit;

/**
 * Block Design Abilities – Theme JSON yetenekleri.
 *
 * WordPress Abilities API'ye tema tasarım token'larını okumak için
 * bir yetenek kaydeder: get-theme-json.
 *
 * Döndürülen bölümler:
 *  - settings       : Renk, tipografi, aralık preset'leri (wp_get_global_settings).
 *  - styles         : Global CSS değerleri (wp_get_global_styles).
 *  - user_overrides : Site Editor'da yapılan kullanıcı özelleştirmeleri.
 *  - theme_info     : Tema meta verisi (ad, versiyon, blok tema bayrağı vb.).
 *
 * İzin gereksinimi: edit_theme_options.
 *
 * @package Block_Design_Abilities
 * @since   1.0.0
 */
class Block_Design_Abilities_Theme_Json
{
    /**
     * Sınıfı başlatır; wp_abilities_api_init kancasını dinleyen
     * yetenek kayıt metodunu bağlar.
     */
    public function __construct()
    {
        add_action('wp_abilities_api_init', array($this, 'register_get_theme_json_ability'));
    }

    /**
     * 'block-design-abilities/get-theme-json' yeteneğini Abilities API'ye kaydeder.
     *
     * Yetenek; origin ("all" | "base") ve sections (isteğe bağlı dizi)
     * parametrelerini kabul eder. Template veya pattern düzenlemeden önce
     * mevcut preset slug'larını öğrenmek için kullanılır.
     *
     * @return void
     */
    public function register_get_theme_json_ability()
    {
        wp_register_ability(
            'block-design-abilities/get-theme-json',
            array(
                'label'       => __('Get Theme JSON', 'block-design-abilities'),
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

                'execute_callback'    => array($this, 'get_theme_json'),
                'permission_callback' => function () {
                    return current_user_can('edit_theme_options');
                },
                'meta' => array('mcp' => array('public' => true)),
            )
        );
    }

    /**
     * Aktif temanın theme.json tasarım token'larını döndürür.
     *
     * - origin="all"  : wp_get_global_settings/styles ile tema + kullanıcı
     *                    özelleştirmeleri birleştirilmiş olarak döner.
     * - origin="base" : Yalnızca tema dosyasındaki değerler döner.
     *
     * sections belirtilmezse tüm bölümler (settings, styles,
     * user_overrides, theme_info) dahil edilir.
     * user_overrides, Site Editor'da herhangi bir değişiklik yapılmamışsa null döner.
     *
     * @param array{
     *     origin?:   string,
     *     sections?: string[],
     * } $input Yetenek giriş parametreleri.
     *
     * @return array{
     *     success:          bool,
     *     theme_info?:      array{
     *         name:               string,
     *         stylesheet:         string,
     *         version:            string,
     *         is_block_theme:     bool,
     *         has_theme_json:     bool,
     *         has_user_overrides: bool,
     *     },
     *     settings?:        array<string, mixed>,
     *     styles?:          array<string, mixed>,
     *     user_overrides?:  array{post_id: int, settings: array<string, mixed>, styles: array<string, mixed>}|null,
     *     error?:           string,
     * }
     */
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
