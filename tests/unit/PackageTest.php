<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests\Unit;

use Brain\Monkey;
use Inpsyde\Modularity\Module\ExtendingModule;
use Inpsyde\Modularity\Module\FactoryModule;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\Package;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Properties\Properties;
use Inpsyde\Modularity\Tests\TestCase;
use Psr\Container\ContainerInterface;

class PackageTest extends TestCase
{
    /**
     * @test
     */
    public function testBasic(): void
    {
        $expectedName = 'foo';
        $propertiesStub = $this->stubProperties($expectedName);

        $package = Package::new($propertiesStub);

        static::assertTrue($package->statusIs(Package::STATUS_IDLE));
        static::assertTrue($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_BOOTED));
        static::assertSame($expectedName, $package->name());
        static::assertInstanceOf(Properties::class, $package->properties());
        static::assertInstanceOf(ContainerInterface::class, $package->container());
        static::assertEmpty($package->modulesStatus()[Package::MODULES_ALL]);
    }

    /**
     * @test
     * @dataProvider provideHookNameSuffix
     *
     * @param string $suffix
     * @param string $baseName
     * @param string $expectedHookName
     */
    public function testHookName(string $suffix, string $baseName, string $expectedHookName): void
    {
        $propertiesStub = $this->stubProperties($baseName);
        $package = Package::new($propertiesStub);
        static::assertSame($expectedHookName, $package->hookName($suffix));
    }

    /**
     * @return \Generator
     */
    public static function provideHookNameSuffix(): \Generator
    {
        $expectedName = 'baseName';
        $baseHookName = 'inpsyde.modularity.' . $expectedName;
        yield 'no suffix' => [
            '',
            $expectedName,
            $baseHookName,
        ];

        yield 'failed boot' => [
            Package::ACTION_FAILED_BOOT,
            $expectedName,
            $baseHookName . '.' . Package::ACTION_FAILED_BOOT,
        ];

        yield 'init' => [
            Package::ACTION_INIT,
            $expectedName,
            $baseHookName . '.' . Package::ACTION_INIT,
        ];

        yield 'ready' => [
            Package::ACTION_READY,
            $expectedName,
            $baseHookName . '.' . Package::ACTION_READY,
        ];
    }

    /**
     * @test
     */
    public function testBootWithEmptyModule(): void
    {
        $expectedId = 'my-module';

        $moduleStub = $this->stubModule($expectedId);
        $propertiesStub = $this->stubProperties('name', false);

        $package = Package::new($propertiesStub)->addModule($moduleStub);

        static::assertTrue($package->boot());
        static::assertTrue($package->moduleIs($expectedId, Package::MODULE_NOT_ADDED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_EXTENDED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_ADDED));

        // booting again fails, but does not throw because debug is false
        static::assertFalse($package->boot());
    }

    /**
     * @test
     */
    public function testBootWithServiceModule(): void
    {
        $moduleId = 'my-service-module';
        $serviceId = 'service-id';

        $module = $this->stubModule($moduleId, ServiceModule::class);
        $module->expects('services')->andReturn($this->stubServices($serviceId));

        $package = Package::new($this->stubProperties())->addModule($module);

        static::assertTrue($package->boot());
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_NOT_ADDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXTENDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->container()->has($serviceId));
    }

    /**
     * @test
     */
    public function testBootWithFactoryModule(): void
    {
        $moduleId = 'my-factory-module';
        $factoryId = 'factory-id';

        $module = $this->stubModule($moduleId, FactoryModule::class);
        $module->expects('factories')->andReturn($this->stubServices($factoryId));

        $package = Package::new($this->stubProperties())->addModule($module);

        static::assertTrue($package->boot());
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_NOT_ADDED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXTENDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->container()->has($factoryId));
    }

    /**
     * @test
     */
    public function testBootWithExtendingModuleWithNonExistingService(): void
    {
        $moduleId = 'my-extension-module';
        $extensionId = 'extension-id';

        $module = $this->stubModule($moduleId, ExtendingModule::class);
        $module->expects('extensions')->andReturn($this->stubServices($extensionId));

        $package = Package::new($this->stubProperties())->addModule($module);

        static::assertTrue($package->boot());
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_NOT_ADDED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXTENDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        // false because extending a service not in container
        static::assertFalse($package->container()->has($extensionId));
    }

    /**
     * @test
     */
    public function testBootWithExtendingModuleWithExistingService(): void
    {
        $moduleId = 'my-extension-module';
        $serviceId = 'service-id';

        $module = $this->stubModule($moduleId, ServiceModule::class, ExtendingModule::class);
        $module->expects('services')->andReturn($this->stubServices($serviceId));
        $module->expects('extensions')->andReturn($this->stubServices($serviceId));

        $package = Package::new($this->stubProperties())->addModule($module);

        static::assertTrue($package->boot());
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_NOT_ADDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_REGISTERED_FACTORIES));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXTENDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->container()->has($serviceId));
    }

    /**
     * @test
     */
    public function testBootWithExecutableModule(): void
    {
        $moduleId = 'executable-module';
        $module = $this->stubModule($moduleId, ExecutableModule::class);
        $module->expects('run')->andReturn(true);

        $package = Package::new($this->stubProperties())->addModule($module);

        static::assertTrue($package->boot());
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXECUTED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXECUTION_FAILED));
    }

    /**
     * Test, when the ExecutableModule::run() return false, that the state is correctly set.
     *
     * @test
     */
    public function testBootWithExecutableModuleFailed(): void
    {
        $moduleId = 'executable-module';
        $module = $this->stubModule($moduleId, ExecutableModule::class);
        $module->expects('run')->andReturn(false);

        $package = Package::new($this->stubProperties())->addModule($module);

        static::assertTrue($package->boot());
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXECUTED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXECUTION_FAILED));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function testBootPassingModulesEmitDeprecation(): void
    {
        $module1 = $this->stubModule('module_1', ServiceModule::class);
        $module1->allows('services')->andReturn($this->stubServices('service_1'));

        $package = Package::new($this->stubProperties('test', true));

        $this->convertDeprecationsToExceptions();
        try {
            $count = 0;
            $package->boot($module1);
        } catch (\Throwable $throwable) {
            $count++;
            $this->assertThrowableMessageMatches($throwable, 'boot().+?deprecated.+?1\.7');
        } finally {
            static::assertSame(1, $count);
        }
    }

    /**
     * @test
     */
    public function testAddModuleFailsAfterBuild(): void
    {
        $package = Package::new($this->stubProperties('test', true))->build();

        $this->expectExceptionMessageMatches("/can't add module/i");

        $package->addModule($this->stubModule());
    }

    /**
     * @test
     */
    public function testPropertiesCanBeRetrievedFromContainer(): void
    {
        $expected = $this->stubProperties();
        $actual = Package::new($expected)->build()->container()->get(Package::PROPERTIES);

        static::assertSame($expected, $actual);
    }

    /**
     * Test, when multiple modules are added, and debug is true, 'modules' status is set correctly.
     *
     * @test
     */
    public function testStatusForMultipleModulesWhenDebug(): void
    {
        $emptyModule = $this->stubModule('empty');
        $emptyServicesModule = $this->stubModule('empty_services', ServiceModule::class);
        $emptyFactoriesModule = $this->stubModule('empty_factories', FactoryModule::class);
        $emptyExtensionsModule = $this->stubModule('empty_extensions', ExtendingModule::class);

        $servicesModule = $this->stubModule('service', ServiceModule::class);
        $servicesModule->expects('services')->andReturn($this->stubServices('S1', 'S2'));

        $factoriesModule = $this->stubModule('factory', FactoryModule::class);
        $factoriesModule->expects('factories')->andReturn($this->stubServices('F'));

        $extendingModule = $this->stubModule('extension', ExtendingModule::class);
        $extendingModule->expects('extensions')->andReturn($this->stubServices('E'));

        $multiModule = $this->stubModule(
            'multi',
            ServiceModule::class,
            ExtendingModule::class,
            FactoryModule::class
        );
        $multiModule->expects('services')->andReturn($this->stubServices('MS1'));
        $multiModule->expects('factories')->andReturn($this->stubServices('MF1', 'MF2'));
        $multiModule->expects('extensions')->andReturn($this->stubServices('ME1', 'ME2'));

        $package = Package::new($this->stubProperties('name', true))
            ->addModule($emptyModule)
            ->addModule($extendingModule)
            ->addModule($emptyServicesModule)
            ->addModule($emptyFactoriesModule)
            ->addModule($emptyExtensionsModule)
            ->addModule($servicesModule)
            ->addModule($multiModule)
            ->addModule($factoriesModule);

        static::assertTrue($package->build()->boot());

        $expectedStatus = [
            Package::MODULES_ALL => [
                'empty not-added',
                'extension extended (E)',
                'extension added',
                'empty_services not-added',
                'empty_factories not-added',
                'empty_extensions not-added',
                'service registered (S1, S2)',
                'service added',
                'multi registered (MS1)',
                'multi registered-factories (MF1, MF2)',
                'multi extended (ME1, ME2)',
                'multi added',
                'factory registered-factories (F)',
                'factory added',
            ],
            Package::MODULE_NOT_ADDED => [
                'empty',
                'empty_services',
                'empty_factories',
                'empty_extensions',
            ],
            Package::MODULE_REGISTERED => [
                'service',
                'multi',
            ],
            Package::MODULE_REGISTERED_FACTORIES => [
                'multi',
                'factory',
            ],
            Package::MODULE_EXTENDED => [
                'extension',
                'multi',
            ],
            Package::MODULE_ADDED => [
                'extension',
                'service',
                'multi',
                'factory',
            ],
        ];

        $actualStatus = $package->modulesStatus();

        ksort($expectedStatus, SORT_STRING);
        ksort($actualStatus, SORT_STRING);

        static::assertSame($expectedStatus, $actualStatus);
    }

    /**
     * Test, when multiple modules are added, and debug is false, 'modules' status is set correctly.
     *
     * @test
     */
    public function testStatusForMultipleModulesWhenNotDebug(): void
    {
        $emptyModule = $this->stubModule('empty');
        $emptyServicesModule = $this->stubModule('empty_services', ServiceModule::class);
        $emptyFactoriesModule = $this->stubModule('empty_factories', FactoryModule::class);
        $emptyExtensionsModule = $this->stubModule('empty_extensions', ExtendingModule::class);

        $servicesModule = $this->stubModule('service', ServiceModule::class);
        $servicesModule->expects('services')->andReturn($this->stubServices('S1', 'S2'));

        $factoriesModule = $this->stubModule('factory', FactoryModule::class);
        $factoriesModule->expects('factories')->andReturn($this->stubServices('F'));

        $extendingModule = $this->stubModule('extension', ExtendingModule::class);
        $extendingModule->expects('extensions')->andReturn($this->stubServices('E'));

        $multiModule = $this->stubModule(
            'multi',
            ServiceModule::class,
            ExtendingModule::class,
            FactoryModule::class
        );
        $multiModule->expects('services')->andReturn($this->stubServices('MS1'));
        $multiModule->expects('factories')->andReturn($this->stubServices('MF1', 'MF2'));
        $multiModule->expects('extensions')->andReturn($this->stubServices('ME1', 'ME2'));

        $package = Package::new($this->stubProperties('name', false))
            ->addModule($emptyModule)
            ->addModule($extendingModule)
            ->addModule($emptyServicesModule)
            ->addModule($emptyFactoriesModule)
            ->addModule($emptyExtensionsModule)
            ->addModule($servicesModule)
            ->addModule($multiModule)
            ->addModule($factoriesModule);

        static::assertTrue($package->build()->boot());

        $expectedStatus = [
            Package::MODULES_ALL => [
                'empty ' . Package::MODULE_NOT_ADDED,
                'extension ' . Package::MODULE_EXTENDED,
                'extension ' . Package::MODULE_ADDED,
                'empty_services ' . Package::MODULE_NOT_ADDED,
                'empty_factories ' . Package::MODULE_NOT_ADDED,
                'empty_extensions ' . Package::MODULE_NOT_ADDED,
                'service ' . Package::MODULE_REGISTERED,
                'service ' . Package::MODULE_ADDED,
                'multi ' . Package::MODULE_REGISTERED,
                'multi ' . Package::MODULE_REGISTERED_FACTORIES,
                'multi ' . Package::MODULE_EXTENDED,
                'multi ' . Package::MODULE_ADDED,
                'factory ' . Package::MODULE_REGISTERED_FACTORIES,
                'factory ' . Package::MODULE_ADDED,
            ],
            Package::MODULE_NOT_ADDED => [
                'empty',
                'empty_services',
                'empty_factories',
                'empty_extensions',
            ],
            Package::MODULE_REGISTERED => [
                'service',
                'multi',
            ],
            Package::MODULE_REGISTERED_FACTORIES => [
                'multi',
                'factory',
            ],
            Package::MODULE_EXTENDED => [
                'extension',
                'multi',
            ],
            Package::MODULE_ADDED => [
                'extension',
                'service',
                'multi',
                'factory',
            ],
        ];

        $actualStatus = $package->modulesStatus();

        ksort($expectedStatus, SORT_STRING);
        ksort($actualStatus, SORT_STRING);

        static::assertSame($expectedStatus, $actualStatus);
    }

    /**
     * Test we can connect services across packages.
     *
     * @test
     */
    public function testPackageConnection(): void
    {
        $module1 = $this->stubModule('module_1', ServiceModule::class);
        $module1->expects('services')->andReturn($this->stubServices('service_1'));
        $package1 = Package::new($this->stubProperties('package_1', false))
            ->addModule($module1);

        $module2 = $this->stubModule('module_2', ServiceModule::class);
        $module2->expects('services')->andReturn($this->stubServices('service_2'));
        $package2 = Package::new($this->stubProperties('package_2', false))
            ->addModule($module2);

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_PACKAGE_CONNECTED))
            ->once()
            ->with($package1->name(), Package::STATUS_IDLE, false);

        $package1->boot();

        $connected = $package2->connect($package1);
        $package2->boot();

        static::assertTrue($connected);
        static::assertSame(['package_1' => true], $package2->connectedPackages());
        // retrieve a Package 1's service from Package 2's container.
        static::assertInstanceOf(\ArrayObject::class, $package2->container()->get('service_1'));
    }

    /**
     * Test we can not connect services when the package how call connect is booted.
     *
     * @test
     */
    public function testPackageConnectionFailsIfBootedWithDebugOff(): void
    {
        $module1 = $this->stubModule('module_1', ServiceModule::class);
        $module1->expects('services')->andReturn($this->stubServices('service_1'));
        $package1 = Package::new($this->stubProperties('package_1', false))
            ->addModule($module1);

        $module2 = $this->stubModule('module_2', ServiceModule::class);
        $module2->expects('services')->andReturn($this->stubServices('service_2'));
        $package2 = Package::new($this->stubProperties('package_2', false))
            ->addModule($module2);

        $package1->boot();
        $package2->boot();

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_FAILED_CONNECTION))
            ->once()
            ->with($package1->name(), \Mockery::type(\WP_Error::class));

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_FAILED_BUILD))
            ->once()
            ->whenHappen(
                function (\Throwable $throwable): void {
                    $this->assertThrowableMessageMatches($throwable, 'failed connect.+?booted');
                }
            );

        $connected = $package2->connect($package1);

        static::assertFalse($connected);
        static::assertSame(['package_1' => false], $package2->connectedPackages());
    }

    /**
     * Test we can not connect services when the package how call connect is booted.
     *
     * @test
     */
    public function testPackageConnectionFailsIfBootedWithDebugOn(): void
    {
        $module1 = $this->stubModule('module_1', ServiceModule::class);
        $module1->expects('services')->andReturn($this->stubServices('service_1'));
        $package1 = Package::new($this->stubProperties('package_1', true))
            ->addModule($module1);

        $module2 = $this->stubModule('module_2', ServiceModule::class);
        $module2->expects('services')->andReturn($this->stubServices('service_2'));
        $package2 = Package::new($this->stubProperties('package_2', true))
            ->addModule($module2);

        $package1->boot();
        $package2->boot();

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_FAILED_CONNECTION))
            ->once()
            ->with($package1->name(), \Mockery::type(\WP_Error::class));

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_FAILED_BUILD))
            ->once()
            ->whenHappen(
                function (\Throwable $throwable): void {
                    $this->assertThrowableMessageMatches($throwable, 'failed connect.+?booted');
                }
            );

        $this->expectExceptionMessageMatches('/failed connect.+?booted/i');
        $package2->connect($package1);
    }

    /**
     * Test we can connect services even if target package is not booted yet.
     *
     * @test
     */
    public function testPackageConnectionWithProxyContainer(): void
    {
        $module1 = $this->stubModule('module_1', ServiceModule::class);
        $module1->expects('services')->andReturn($this->stubServices('service_1'));
        $package1 = Package::new($this->stubProperties('package_1', false))
            ->addModule($module1);

        $module2 = $this->stubModule('module_2', ServiceModule::class);
        $module2->expects('services')->andReturn($this->stubServices('service_2'));
        $package2 = Package::new($this->stubProperties('package_2', false))
            ->addModule($module2);

        $connected = $package2->connect($package1);
        $package2->boot();

        static::assertTrue($connected);
        static::assertSame(['package_1' => true], $package2->connectedPackages());

        // We successfully connect before booting, but we need to boot to retrieve services
        $package1->boot();
        $container = $package2->container();

        // retrieve a Package 1's service from Package 2's container.
        $connectedService = $container->get('service_1');
        // retrieve the Package 1's properties from Package 2's container.
        $connectedProperties = $container->get('package_1.' . Package::PROPERTIES);

        static::assertInstanceOf(\ArrayObject::class, $connectedService);
        static::assertInstanceOf(Properties::class, $connectedProperties);
        static::assertSame('package_1', $connectedProperties->baseName());
    }

    /**
     * Test that connecting packages not booted, fails when accessing services
     *
     * @test
     */
    public function testPackageConnectionWithProxyContainerFailsIfNoBoot(): void
    {
        $module1 = $this->stubModule('module_1', ServiceModule::class);
        $module1->expects('services')->andReturn($this->stubServices('service_1'));
        $package1 = Package::new($this->stubProperties('package_1', false))
            ->addModule($module1);

        $module2 = $this->stubModule('module_2', ServiceModule::class);
        $module2->expects('services')->andReturn($this->stubServices('service_2'));
        $package2 = Package::new($this->stubProperties('package_2', false))
            ->addModule($module2);

        $connected = $package2->connect($package1);
        $package2->boot();

        static::assertTrue($connected);
        static::assertSame(['package_1' => true], $package2->connectedPackages());

        // we got a "not found" exception because `PackageProxyContainer::has()` return false,
        // because $package1 is not booted
        $this->expectExceptionMessageMatches('/not found/i');
        $package2->container()->get('service_1');
    }

    /**
     * Test we can connect packages once.
     *
     * @test
     */
    public function testPackageCanOnlyBeConnectedOnceDebugOff(): void
    {
        $module1 = $this->stubModule('module_1', ServiceModule::class);
        $module1->expects('services')->andReturn($this->stubServices('service_1'));
        $package1 = Package::new($this->stubProperties('package_1', false))
            ->addModule($module1);

        $module2 = $this->stubModule('module_2', ServiceModule::class);
        $module2->expects('services')->andReturn($this->stubServices('service_2'));
        $package2 = Package::new($this->stubProperties('package_2', false))
            ->addModule($module2);

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_PACKAGE_CONNECTED))
            ->once();

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_FAILED_CONNECTION))
            ->once()
            ->with($package1->name(), \Mockery::type(\WP_Error::class));

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_FAILED_BUILD))
            ->once()
            ->whenHappen(
                function (\Throwable $throwable): void {
                    $this->assertThrowableMessageMatches($throwable, 'failed connect.+?already');
                }
            );

        $connected1 = $package2->connect($package1);

        static::assertTrue($package2->isPackageConnected($package1->name()));

        $connected2 = $package2->connect($package1);

        static::assertTrue($connected1);
        static::assertFalse($connected2);
    }

    /**
     * Test we can connect packages once.
     *
     * @test
     */
    public function testPackageCanOnlyBeConnectedOnceDebugOn(): void
    {
        $module1 = $this->stubModule('module_1', ServiceModule::class);
        $module1->expects('services')->andReturn($this->stubServices('service_1'));
        $package1 = Package::new($this->stubProperties('package_1', false))
            ->addModule($module1);

        $module2 = $this->stubModule('module_2', ServiceModule::class);
        $module2->expects('services')->andReturn($this->stubServices('service_2'));
        $package2 = Package::new($this->stubProperties('package_2', true))
            ->addModule($module2);

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_PACKAGE_CONNECTED))
            ->once();

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_FAILED_CONNECTION))
            ->once()
            ->with($package1->name(), \Mockery::type(\WP_Error::class));

        Monkey\Actions\expectDone($package2->hookName(Package::ACTION_FAILED_BUILD))
            ->once()
            ->whenHappen(
                function (\Throwable $throwable): void {
                    $this->assertThrowableMessageMatches($throwable, 'failed connect.+?already');
                }
            );

        static::assertTrue($package2->connect($package1));

        static::expectExceptionMessageMatches('/failed connect.+?already/i');
        $package2->connect($package1);
    }

    /**
     * Test we can not connect packages with themselves.
     *
     * @test
     */
    public function testPackageCanNotBeConnectedWithThemselves(): void
    {
        $module1 = $this->stubModule('module_1', ServiceModule::class);
        $module1->expects('services')->andReturn($this->stubServices('service_1'));
        $package1 = Package::new($this->stubProperties('package_1', true))
            ->addModule($module1);

        $action = $package1->hookName(Package::ACTION_FAILED_CONNECTION);
        Monkey\Actions\expectDone($action)->never();

        static::assertFalse($package1->connect($package1));
    }

    /**
     * Test getting services from a built package works, but getting services from
     * a connected built package fails.
     */
    public function testGettingServicesFromBuiltConnectedPackageFails(): void
    {
        $package1 = $this->stubSimplePackage('1');
        $package2 = $this->stubSimplePackage('2');
        $package3 = $this->stubSimplePackage('3');

        $connected2 = $package1->connect($package2);
        $connected3 = $package1->connect($package3);

        // Note only P2 is "booted", while P1 and P3 are "built".
        $package1->build();
        $package2->boot();
        $package3->build();

        // Test connection was successful
        static::assertTrue($connected2);
        static::assertTrue($connected3);
        static::assertTrue($package1->isPackageConnected($package2->name()));
        static::assertTrue($package1->isPackageConnected($package3->name()));

        // We can get containers of all three packages
        $container1 = $package1->container();
        $container2 = $package2->container();
        $container3 = $package3->container();

        // And we can get services from all three containers if called directly
        static::assertSame('service_1', $container1->get('service_1')['id']);
        static::assertSame('service_2', $container2->get('service_2')['id']);
        static::assertSame('service_3', $container3->get('service_3')['id']);

        // And we can use Package 1 to get a service from the connected+booted Package 2
        $package1->container()->get('service_2');
        // However, we get an exception when getting a service from the connected+built Package 3
        /** TODO: Do we consider this a bug? See https://github.com/inpsyde/modularity/pull/49 */
        $this->expectExceptionMessageMatches('/service_3.+not found/i');
        $package1->container()->get('service_3');
    }

    /**
     * @test
     *
     * phpcs:disable Inpsyde.CodeQuality.NestingLevel
     */
    public function testBuildResolveServices(): void
    {
        // phpcs:enable phpcs:disable Inpsyde.CodeQuality.NestingLevel
        $module = new class () implements ServiceModule, ExtendingModule, ExecutableModule
        {
            public function id(): string
            {
                return 'test-module';
            }

            public function services(): array
            {
                return [
                    'dependency' => static function (): object {
                        return (object) ['x' => 'Works!'];
                    },
                    'service' => static function (ContainerInterface $container): object {
                        $works = $container->get('dependency')->x;

                        return new class (['works?' => $works]) extends \ArrayObject
                        {
                        };
                    },
                ];
            }

            public function extensions(): array
            {
                return [
                    'service' => function (\ArrayObject $current): object {
                        return new class ($current)
                        {
                            public \ArrayObject $object; // phpcs:ignore

                            public function __construct(\ArrayObject $object)
                            {
                                $this->object = $object;
                            }

                            public function works(): string
                            {
                                return $this->object->offsetGet('works?');
                            }
                        };
                    },
                ];
            }

            public function run(ContainerInterface $container): bool
            {
                throw new \Error('This should not run!');
            }
        };

        $actual = Package::new($this->stubProperties())
            ->addModule($module)
            ->build()
            ->container()
            ->get('service')
            ->works();

        static::assertSame('Works!', $actual);
    }

    /**
     * @test
     */
    public function testBuildPassingModulesToBoot(): void
    {
        $module1 = $this->stubModule('module_1', ServiceModule::class);
        $module1->expects('services')->andReturn($this->stubServices('service_1'));

        $module2 = $this->stubModule('module_2', ServiceModule::class);
        $module2->expects('services')->andReturn($this->stubServices('service_2'));

        $module3 = $this->stubModule('module_3', ServiceModule::class);
        $module3->expects('services')->andReturn($this->stubServices('service_3'));

        $package = Package::new($this->stubProperties('test', true))
            ->addModule($module1)
            ->addModule($module2)
            ->build();

        $this->ignoreDeprecations();
        $package->boot($module2, $module3);

        $container = $package->container();

        static::assertSame('service_1', $container->get('service_1')['id']);
        static::assertSame('service_2', $container->get('service_2')['id']);
        static::assertSame('service_3', $container->get('service_3')['id']);
    }

    /**
     * @test
     */
    public function testBootFailsIfPassingNotAddedModulesAfterContainer(): void
    {
        $module1 = $this->stubModule('module_1', ServiceModule::class);
        $module1->expects('services')->andReturn($this->stubServices('service_1'));

        $module2 = $this->stubModule('module_2', ServiceModule::class);
        $module2->expects('services')->andReturn($this->stubServices('service_2'));

        $module3 = $this->stubModule('module_3', ServiceModule::class);
        $module3->allows('services')->andReturn($this->stubServices('service_3'));

        $package = Package::new($this->stubProperties('test', true))
            ->addModule($module1)
            ->addModule($module2)
            ->build();

        $container = $package->container();

        static::assertSame('service_1', $container->get('service_1')['id']);
        static::assertSame('service_2', $container->get('service_2')['id']);

        $this->expectExceptionMessageMatches("/can't add module module_3/i");
        $this->ignoreDeprecations();
        $package->boot($module2, $module3);
    }

    /**
     * When an exception happen inside `Package::boot()` and debug is off, we expect the exception
     * to be caught, an "boot failed" action to be failed, and the Package to be in errored status.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnBootDebugModeOff(): void
    {
        $exception = new \Exception('Test');

        $module = $this->stubModule('id', ExecutableModule::class);
        $module->expects('run')->andThrow($exception);

        $package = Package::new($this->stubProperties())->addModule($module);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))
            ->once()
            ->with($exception);

        static::assertFalse($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_FAILED));
    }

    /**
     * When an exception happen inside `Package::boot()` and debug is of, we expect it to bubble up.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnBootDebugModeOn(): void
    {
        $exception = new \Exception('Test');

        $module = $this->stubModule('id', ExecutableModule::class);
        $module->expects('run')->andThrow($exception);

        $package = Package::new($this->stubProperties('basename', true))->addModule($module);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))
            ->once()
            ->with($exception);

        $this->expectExceptionObject($exception);
        $package->boot();
    }

    /**
     * When multiple calls to `Package::addPackage()` throw an exception, and debug is off, we
     * expect none of them to bubble up, and the first to cause the "build failed" action.
     * We also expect the Package to be in errored status.
     * We expect all other `Package::addPackage()` exceptions to do not fire action hook.
     * We expect Package::build()` to fail without doing anything. Finally, when `Package::boot()`
     * is called, we expect the action "boot failed" to be called, and the passed exception to have
     * an exception hierarchy with all the thrown exceptions.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnAddModuleDebugModeOff(): void
    {
        $exception = new \Exception('Test 1');

        $module1 = $this->stubModule('one', ServiceModule::class);
        $module1->expects('services')->andThrow($exception);

        $module2 = $this->stubModule('two', ServiceModule::class);
        $module2->expects('services')->never();

        $package = Package::new($this->stubProperties());

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))
            ->once()
            ->whenHappen(
                static function (\Throwable $throwable) use ($exception, $package): void {
                    static::assertSame($exception, $throwable);
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))
            ->once()
            ->whenHappen(
                function (\Throwable $throwable) use ($exception, $package): void {
                    $this->assertThrowableMessageMatches($throwable, 'boot application');
                    $previous = $throwable->getPrevious();
                    $this->assertThrowableMessageMatches($previous, 'build package');
                    $previous = $previous->getPrevious();
                    $this->assertThrowableMessageMatches($previous, 'add module two');
                    static::assertSame($exception, $previous->getPrevious());
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        static::assertFalse($package->addModule($module1)->addModule($module2)->build()->boot());
        static::assertTrue($package->statusIs(Package::STATUS_FAILED));
    }

    /**
     * The same as the test above, but this time we call `Package::boot()` directly, instead of
     * `$package->build()->boot()`, but the expectations are identical.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnAddModuleWithoutBuildDebugModeOff(): void
    {
        $exception = new \Exception('Test 1');

        $module1 = $this->stubModule('one', ServiceModule::class);
        $module1->expects('services')->andThrow($exception);

        $module2 = $this->stubModule('two', ServiceModule::class);
        $module2->expects('services')->never();

        $package = Package::new($this->stubProperties());

        $connected = Package::new($this->stubProperties());
        $connected->boot();

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))
            ->once()
            ->whenHappen(
                static function (\Throwable $throwable) use ($exception, $package): void {
                    static::assertSame($exception, $throwable);
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))
            ->once()
            ->whenHappen(
                function (\Throwable $throwable) use ($exception, $package): void {
                    $this->assertThrowableMessageMatches($throwable, 'boot application');
                    $previous = $throwable->getPrevious();
                    $this->assertThrowableMessageMatches($previous, 'build package');
                    $previous = $previous->getPrevious();
                    $this->assertThrowableMessageMatches($previous, 'failed connect.+?errored');
                    $previous = $previous->getPrevious();
                    $this->assertThrowableMessageMatches($previous, 'add module two');
                    static::assertSame($exception, $previous->getPrevious());
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        $package = $package->addModule($module1)->addModule($module2);

        static::assertFalse($package->connect($connected));
        static::assertFalse($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_FAILED));
    }

    /**
     * When `Package::build()` throws an exception, and debug is off, we expect it to be caught, the
     * "build failed" action to be fired, and the Package to be in errored status. When after that
     * `Package::boot()` is called we expect the action "boot failed" to be called passing an
     * exception whose "previous" is the exception thrown by `Package::build()`.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnBuildDebugModeOff(): void
    {
        $exception = new \Exception('Test');

        $package = Package::new($this->stubProperties());

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->andThrow($exception);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))
            ->once()
            ->whenHappen(
                static function (\Throwable $throwable) use ($exception, $package): void {
                    static::assertSame($exception, $throwable);
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))
            ->once()
            ->whenHappen(
                function (\Throwable $throwable) use ($exception, $package): void {
                    $this->assertThrowableMessageMatches($throwable, 'boot application');
                    static::assertSame($exception, $throwable->getPrevious());
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        static::assertFalse($package->build()->boot());
        static::assertTrue($package->statusIs(Package::STATUS_FAILED));
    }

    /**
     * When `Package::build()` throws an exception, and debug is on, we expect it to bubble up.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnBuildDebugModeOn(): void
    {
        $exception = new \Exception('Test');

        $package = Package::new($this->stubProperties('basename', true));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->andThrow($exception);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))
            ->once()
            ->whenHappen(
                static function (\Throwable $throwable) use ($exception, $package): void {
                    static::assertSame($exception, $throwable);
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->never();

        $this->expectExceptionObject($exception);
        $package->build()->boot();
    }
}
