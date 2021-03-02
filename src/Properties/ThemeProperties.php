<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Properties;

/**
 * Class ThemeProperties
 *
 * @package Inpsyde\Modularity\Properties
 *
 * @psalm-suppress PossiblyFalseArgument, InvalidArgument
 */
class ThemeProperties extends BaseProperties
{
    /**
     * Additional properties specific for themes.
     */
    public const PROP_STATUS = 'status';
    public const PROP_TAGS = 'tags';
    public const PROP_TEMPLATE = 'template';
    /**
     * Available methods of Properties::__call()
     * from theme headers.
     *
     * @link https://developer.wordpress.org/reference/classes/wp_theme/
     */
    private const THEME_METHODS = [
        self::PROP_AUTHOR => 'Author',
        self::PROP_AUTHOR_URI => 'Author URI',
        self::PROP_DESCRIPTION => 'Description',
        self::PROP_DOMAIN_PATH => 'Domain Path',
        self::PROP_NAME => 'Theme Name',
        self::PROP_TEXTDOMAIN => 'Text Domain',
        self::PROP_URI => 'Theme URI',
        self::PROP_VERSION => 'Version',
        self::PROP_REQUIRES_WP => 'Requires at least',
        self::PROP_REQUIRES_PHP => 'Requires PHP',

        // additional headers
        self::PROP_STATUS => 'Status',
        self::PROP_TAGS => 'Tags',
        self::PROP_TEMPLATE => 'Template',
    ];

    /**
     * @param string $themeDirectory
     *
     * @return ThemeProperties
     */
    public static function new(string $themeDirectory): ThemeProperties
    {
        if (!function_exists('wp_get_theme')) {
            require_once ABSPATH . 'wp-includes/theme.php';
        }

        /** @var \WP_Theme $theme */
        $theme = wp_get_theme($themeDirectory);
        $properties = Properties::DEFAULT_PROPERTIES;

        foreach (self::THEME_METHODS as $key => $themeKey) {
            /** @psalm-suppress DocblockTypeContradiction */
            $properties[$key] = $theme->get($themeKey) ?? '';
        }

        $baseName = $theme->get_stylesheet();
        $basePath = $theme->get_template_directory();
        $baseUrl = (string) trailingslashit($theme->get_stylesheet_directory_uri());

        return new self(
            $baseName,
            $basePath,
            $baseUrl,
            $properties
        );
    }

    /**
     * If the theme is published.
     *
     * @return string
     */
    public function status(): string
    {
        return (string) $this->get(self::PROP_STATUS);
    }

    /**
     * @return array
     */
    public function tags(): array
    {
        return (array) $this->get(self::PROP_TAGS);
    }

    public function template(): string
    {
        return (string) $this->get(self::PROP_TEMPLATE);
    }
}
