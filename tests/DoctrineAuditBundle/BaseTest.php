<?php

namespace DH\DoctrineAuditBundle\Tests;

use DH\DoctrineAuditBundle\AuditConfiguration;
use DH\DoctrineAuditBundle\AuditManager;
use DH\DoctrineAuditBundle\EventSubscriber\AuditSubscriber;
use DH\DoctrineAuditBundle\EventSubscriber\CreateSchemaListener;
use DH\DoctrineAuditBundle\Helper\AuditHelper;
use DH\DoctrineAuditBundle\Reader\AuditReader;
use DH\DoctrineAuditBundle\User\TokenStorageUserProvider;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Tools\SchemaTool;
use Gedmo;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

abstract class BaseTest extends TestCase
{
    /**
     * @var null|Connection
     */
    protected static $conn;

    /**
     * @var EntityManager
     */
    protected $em;

    protected $fixturesPath;

    protected $auditConfiguration;

    protected $auditManager;

    /**
     * @var SchemaTool
     */
    private $schemaTool;

    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\Tools\ToolsException
     */
    public function setUp(): void
    {
        $this->getEntityManager();
        $this->getSchemaTool();
        $this->setUpEntitySchema();
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function tearDown(): void
    {
        $this->tearDownEntitySchema();
        $this->em = null;
        $this->schemaTool = null;
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     *
     * @return SchemaTool
     */
    protected function getSchemaTool(): SchemaTool
    {
        if (null !== $this->schemaTool) {
            return $this->schemaTool;
        }

        return $this->schemaTool = new SchemaTool($this->getEntityManager());
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\Tools\ToolsException
     */
    protected function setUpEntitySchema(): void
    {
        $classes = $this->getEntityManager()->getMetadataFactory()->getAllMetadata();

        $this->getSchemaTool()->createSchema($classes);
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    protected function tearDownEntitySchema(): void
    {
        $classes = $this->getEntityManager()->getMetadataFactory()->getAllMetadata();

        $this->getSchemaTool()->dropSchema($classes);
    }

    protected function getAuditConfiguration(): AuditConfiguration
    {
        return $this->auditConfiguration;
    }

    protected function setAuditConfiguration(AuditConfiguration $configuration): void
    {
        $this->auditConfiguration = $configuration;
    }

    protected function createAuditConfiguration(array $options = []): AuditConfiguration
    {
        $container = new ContainerBuilder();

        $auditConfiguration = new AuditConfiguration(
            array_merge([
                'table_prefix' => '',
                'table_suffix' => '_audit',
                'ignored_columns' => [],
                'entities' => [],
            ], $options),
            new TokenStorageUserProvider(new Security($container)),
            new RequestStack(),
            new FirewallMap($container, [])
        );

        return $auditConfiguration;
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     *
     * @return EntityManager
     */
    protected function getEntityManager(): EntityManager
    {
        if (null !== $this->em) {
            return $this->em;
        }

        $config = new Configuration();
        $config->setMetadataCacheImpl(new ArrayCache());
        $config->setQueryCacheImpl(new ArrayCache());
        $config->setProxyDir(__DIR__.'/Proxies');
        $config->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_EVAL);
        $config->setProxyNamespace('DH\DoctrineAuditBundle\Tests\Proxies');
        $config->addFilter('soft-deleteable', Gedmo\SoftDeleteable\Filter\SoftDeleteableFilter::class);

        $fixturesPath = \is_array($this->fixturesPath) ? $this->fixturesPath : [$this->fixturesPath];
        $config->setMetadataDriverImpl($config->newDefaultAnnotationDriver($fixturesPath, false));

        Gedmo\DoctrineExtensions::registerAnnotations();

        $connection = $this->getSharedConnection();
//        $connection = $this->getConnection();

        $this->setAuditConfiguration($this->createAuditConfiguration());
        $configuration = $this->getAuditConfiguration();

        $this->auditManager = new AuditManager($configuration, new AuditHelper($configuration));

        // get rid of more global state
        $evm = $connection->getEventManager();
        foreach ($evm->getListeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                $evm->removeEventListener([$event], $listener);
            }
        }
        $evm->addEventSubscriber(new AuditSubscriber($this->auditManager));
        $evm->addEventSubscriber(new CreateSchemaListener($this->auditManager));
        $evm->addEventSubscriber(new Gedmo\SoftDeleteable\SoftDeleteableListener());

        $this->em = EntityManager::create($connection, $config);

        return $this->em;
    }

    protected function getSharedConnection(): Connection
    {
        if (null === self::$conn) {
            self::$conn = $this->getConnection();
        }

        if (false === self::$conn->ping()) {
            self::$conn->close();
            self::$conn->connect();
        }

        return self::$conn;
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     *
     * @return null|Connection
     */
    protected function getConnection(): Connection
    {
        if (null !== self::$conn) {
            self::$conn->close();
            self::$conn = null;
        }

        $params = $this->getConnectionParameters();

        if (isset(
            $GLOBALS['db_type'],
            $GLOBALS['db_username'],
            $GLOBALS['db_password'],
            $GLOBALS['db_host'],
            $GLOBALS['db_name'],
            $GLOBALS['db_port']
        )) {
            $tmpParams = $params;
            $dbname = $params['dbname'];
            unset($tmpParams['dbname']);

            $conn = DriverManager::getConnection($tmpParams);
            $platform = $conn->getDatabasePlatform();

            if ($platform->supportsCreateDropDatabase()) {
                $conn->getSchemaManager()->dropAndCreateDatabase($dbname);
            } else {
                $sm = $conn->getSchemaManager();
                $schema = $sm->createSchema();
                $stmts = $schema->toDropSql($conn->getDatabasePlatform());
                foreach ($stmts as $stmt) {
                    $conn->exec($stmt);
                }
            }

            $conn->close();
        }

        return DriverManager::getConnection($params);
    }

    protected function getConnectionParameters(): array
    {
        if (isset(
            $GLOBALS['db_type'],
            $GLOBALS['db_username'],
            $GLOBALS['db_password'],
            $GLOBALS['db_host'],
            $GLOBALS['db_name'],
            $GLOBALS['db_port']
        )) {
            $params = [
                'driver' => $GLOBALS['db_type'],
                'user' => $GLOBALS['db_username'],
                'password' => $GLOBALS['db_password'],
                'host' => $GLOBALS['db_host'],
                'dbname' => $GLOBALS['db_name'],
                'port' => $GLOBALS['db_port'],
            ];
        } else {
            $params = [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ];
        }

        return $params;
    }

    protected function getReader(AuditConfiguration $configuration = null): AuditReader
    {
        return new AuditReader($configuration ?? $this->createAuditConfiguration(), $this->getEntityManager());
    }
}
