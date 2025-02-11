<?php

declare(strict_types=1);

namespace Yiisoft\Log\Target\Db\Tests;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Cache\Cache;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection as SqlLiteConnection;
use Yiisoft\Definitions\DynamicReference;
use Yiisoft\Definitions\Reference;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\Provider;
use Yiisoft\Log\Logger;
use Yiisoft\Log\Target\Db\DbTarget;
use Yiisoft\Profiler\Profiler;
use Yiisoft\Profiler\ProfilerInterface;
use Yiisoft\Yii\Db\Migration\Informer\MigrationInformerInterface;
use Yiisoft\Yii\Db\Migration\Informer\NullMigrationInformer;

use function dirname;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected const DB_FILE = __DIR__ . '/runtime/test.sq3';

    private ?Container $container = null;

    protected function tearDown(): void
    {
        $db = $this
            ->getContainer()
            ->get(ConnectionInterface::class);

        foreach ($db
                     ->getSchema()
                     ->getTableNames() as $tableName) {
            $db
                ->createCommand()
                ->dropTable($tableName)
                ->execute();
        }

        unset($this->container);
    }

    protected function getContainer(): Container
    {
        if ($this->container === null) {
            $config = ContainerConfig::create()
                ->withDefinitions([
                    Aliases::class => [
                        '__construct()' => [
                            '@root' => dirname(__DIR__, 2),
                            '@runtime' => __DIR__ . '/runtime',
                            '@yiisoft/yii/db/migration' => '@root',
                        ],
                    ],

                    CacheInterface::class => [
                        'class' => Cache::class,
                        '__construct()' => [Reference::to(ArrayCache::class)],
                    ],

                    Logger::class => static fn (ContainerInterface $container) => new Logger([
                        new DbTarget($container->get(ConnectionInterface::class), 'test-table-1'),
                        new DbTarget($container->get(ConnectionInterface::class), 'test-table-2'),
                    ]),

                    LoggerInterface::class => Logger::class,

                    ConnectionInterface::class => [
                        'class' => SqlLiteConnection::class,
                        '__construct()' => [
                            'sqlite:' . self::DB_FILE,
                        ],
                        'setLogger()' => [DynamicReference::to(Logger::class)],
                    ],

                    MigrationInformerInterface::class => NullMigrationInformer::class,
                    EventDispatcherInterface::class => Dispatcher::class,
                    ListenerProviderInterface::class => Provider::class,
                    ProfilerInterface::class => Profiler::class,
                ]);
            $this->container = new Container($config);
        }

        return $this->container;
    }
}
