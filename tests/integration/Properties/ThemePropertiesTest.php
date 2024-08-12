<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Integration\Properties;

use Inpsyde\Modularity\Properties\ThemeProperties;
use Inpsyde\WpDbLess\PHPUnit\WpDbLessTestCase;

/**
 * @runTestsInSeparateProcesses
 */
final class ThemePropertiesTest extends WpDbLessTestCase
{
    public function setUp(): void
    {
        define('WP_CONTENT_DIR', getenv('RESOURCES_DIR'));
        parent::setUp();
    }

    public function testThemeProperties(): void
    {
        $sut = ThemeProperties::new( '/modularity-theme' );
        self::assertSame('https://syde.com', $sut->authorUri());
    }
}