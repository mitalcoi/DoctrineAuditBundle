<?php

namespace DH\DoctrineAuditBundle\Tests;

use DH\DoctrineAuditBundle\AuditConfiguration;
use DH\DoctrineAuditBundle\AuditManager;
use DH\DoctrineAuditBundle\Helper\AuditHelper;
use DH\DoctrineAuditBundle\Reader\AuditReader;
use DH\DoctrineAuditBundle\Tests\Fixtures\Core\Author;
use DH\DoctrineAuditBundle\Tests\Fixtures\Core\Post;
use DH\DoctrineAuditBundle\Tests\Fixtures\Core\Tag;

/**
 * @covers \DH\DoctrineAuditBundle\AuditConfiguration
 * @covers \DH\DoctrineAuditBundle\AuditManager
 * @covers \DH\DoctrineAuditBundle\EventSubscriber\AuditSubscriber
 * @covers \DH\DoctrineAuditBundle\EventSubscriber\CreateSchemaListener
 * @covers \DH\DoctrineAuditBundle\Helper\AuditHelper
 * @covers \DH\DoctrineAuditBundle\Helper\DoctrineHelper
 * @covers \DH\DoctrineAuditBundle\Helper\UpdateHelper
 * @covers \DH\DoctrineAuditBundle\Reader\AuditEntry
 * @covers \DH\DoctrineAuditBundle\Reader\AuditReader
 * @covers \DH\DoctrineAuditBundle\User\TokenStorageUserProvider
 * @covers \DH\DoctrineAuditBundle\User\User
 */
class AuditManagerTest extends CoreTest
{
    public function testInsert(): void
    {
        $em = $this->getEntityManager();
        $configuration = $this->getAuditConfiguration();
        $helper = new AuditHelper($configuration);
        $manager = new AuditManager($configuration, $helper);
        $reader = new AuditReader($configuration, $em);

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $manager->insert($em, $author, [
            'fullname' => [null, 'John Doe'],
            'email' => [null, 'john.doe@gmail.com'],
        ]);

        $audits = $reader->getAudits(Author::class);
        $this->assertCount(1, $audits, 'AuditManager::insert() creates an audit entry.');

        $entry = array_shift($audits);
        $this->assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        $this->assertSame(AuditReader::INSERT, $entry->getType(), 'audit entry type is ok.');
        $this->assertEquals(1, $entry->getUserId(), 'audit entry blame_id is ok.');
        $this->assertSame('dark.vador', $entry->getUsername(), 'audit entry blame_user is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        $this->assertEquals([
            'fullname' => [
                'old' => null,
                'new' => 'John Doe',
            ],
            'email' => [
                'old' => null,
                'new' => 'john.doe@gmail.com',
            ],
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testUpdate(): void
    {
        $em = $this->getEntityManager();
        $configuration = $this->getAuditConfiguration();
        $helper = new AuditHelper($configuration);
        $manager = new AuditManager($configuration, $helper);
        $reader = new AuditReader($configuration, $em);

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $manager->update($em, $author, [
            'fullname' => ['John Doe', 'Dark Vador'],
            'email' => ['john.doe@gmail.com', 'dark.vador@gmail.com'],
        ]);

        $audits = $reader->getAudits(Author::class);
        $this->assertCount(1, $audits, 'AuditManager::update() creates an audit entry.');

        $entry = array_shift($audits);
        $this->assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        $this->assertSame(AuditReader::UPDATE, $entry->getType(), 'audit entry type is ok.');
        $this->assertEquals(1, $entry->getUserId(), 'audit entry blame_id is ok.');
        $this->assertSame('dark.vador', $entry->getUsername(), 'audit entry blame_user is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        $this->assertEquals([
            'fullname' => [
                'old' => 'John Doe',
                'new' => 'Dark Vador',
            ],
            'email' => [
                'old' => 'john.doe@gmail.com',
                'new' => 'dark.vador@gmail.com',
            ],
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testRemove(): void
    {
        $em = $this->getEntityManager();
        $configuration = $this->getAuditConfiguration();
        $helper = new AuditHelper($configuration);
        $manager = new AuditManager($configuration, $helper);
        $reader = new AuditReader($configuration, $em);

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $manager->remove($em, $author, 1);

        $audits = $reader->getAudits(Author::class);
        $this->assertCount(1, $audits, 'AuditManager::remove() creates an audit entry.');

        $entry = array_shift($audits);
        $this->assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        $this->assertSame(AuditReader::REMOVE, $entry->getType(), 'audit entry type is ok.');
        $this->assertEquals(1, $entry->getUserId(), 'audit entry blame_id is ok.');
        $this->assertSame('dark.vador', $entry->getUsername(), 'audit entry blame_user is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        $this->assertEquals([
            'label' => Author::class.'#1',
            'class' => Author::class,
            'table' => $em->getClassMetadata(Author::class)->getTableName(),
            'id' => 1,
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testAssociateOneToMany(): void
    {
        $em = $this->getEntityManager();
        $configuration = $this->getAuditConfiguration();
        $helper = new AuditHelper($configuration);
        $manager = new AuditManager($configuration, $helper);
        $reader = new AuditReader($configuration, $em);

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $post = new Post();
        $post
            ->setId(1)
            ->setAuthor($author)
            ->setTitle('First post')
            ->setBody('Here is the body')
            ->setCreatedAt(new \DateTime())
        ;

        $mapping = [
            'fieldName' => 'posts',
            'mappedBy' => 'author',
            'targetEntity' => Post::class,
            'cascade' => [
                0 => 'persist',
                1 => 'remove',
            ],
            'orphanRemoval' => false,
            'fetch' => 2,
            'type' => 4,
            'inversedBy' => null,
            'isOwningSide' => false,
            'sourceEntity' => Author::class,
            'isCascadeRemove' => true,
            'isCascadePersist' => true,
            'isCascadeRefresh' => false,
            'isCascadeMerge' => false,
            'isCascadeDetach' => false,
        ];

        $manager->associate($em, $author, $post, $mapping);

        $audits = $reader->getAudits(Author::class);
        $this->assertCount(1, $audits, 'AuditManager::associate() creates an audit entry.');

        $entry = array_shift($audits);
        $this->assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        $this->assertSame(AuditReader::ASSOCIATE, $entry->getType(), 'audit entry type is ok.');
        $this->assertEquals(1, $entry->getUserId(), 'audit entry blame_id is ok.');
        $this->assertSame('dark.vador', $entry->getUsername(), 'audit entry blame_user is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        $this->assertEquals([
            'source' => [
                'label' => Author::class.'#1',
                'class' => Author::class,
                'table' => $em->getClassMetadata(Author::class)->getTableName(),
                'id' => 1,
            ],
            'target' => [
                'label' => (string) $post,
                'class' => Post::class,
                'table' => $em->getClassMetadata(Post::class)->getTableName(),
                'id' => 1,
            ],
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testDissociateOneToMany(): void
    {
        $em = $this->getEntityManager();
        $configuration = $this->getAuditConfiguration();
        $helper = new AuditHelper($configuration);
        $manager = new AuditManager($configuration, $helper);
        $reader = new AuditReader($configuration, $em);

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $post = new Post();
        $post
            ->setId(1)
            ->setAuthor($author)
            ->setTitle('First post')
            ->setBody('Here is the body')
            ->setCreatedAt(new \DateTime())
        ;

        $mapping = [
            'fieldName' => 'posts',
            'mappedBy' => 'author',
            'targetEntity' => Post::class,
            'cascade' => ['persist', 'remove'],
            'orphanRemoval' => false,
            'fetch' => 2,
            'type' => 4,
            'inversedBy' => null,
            'isOwningSide' => false,
            'sourceEntity' => Author::class,
            'isCascadeRemove' => true,
            'isCascadePersist' => true,
            'isCascadeRefresh' => false,
            'isCascadeMerge' => false,
            'isCascadeDetach' => false,
        ];

        $manager->dissociate($em, $author, $post, $mapping);

        $audits = $reader->getAudits(Author::class);
        $this->assertCount(1, $audits, 'AuditManager::dissociate() creates an audit entry.');

        $entry = array_shift($audits);
        $this->assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        $this->assertSame(AuditReader::DISSOCIATE, $entry->getType(), 'audit entry type is ok.');
        $this->assertEquals(1, $entry->getUserId(), 'audit entry blame_id is ok.');
        $this->assertSame('dark.vador', $entry->getUsername(), 'audit entry blame_user is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        $this->assertEquals([
            'source' => [
                'label' => Author::class.'#1',
                'class' => Author::class,
                'table' => $em->getClassMetadata(Author::class)->getTableName(),
                'id' => 1,
            ],
            'target' => [
                'label' => (string) $post,
                'class' => Post::class,
                'table' => $em->getClassMetadata(Post::class)->getTableName(),
                'id' => 1,
            ],
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testAssociateManyToMany(): void
    {
        $em = $this->getEntityManager();
        $configuration = $this->getAuditConfiguration();
        $helper = new AuditHelper($configuration);
        $manager = new AuditManager($configuration, $helper);
        $reader = new AuditReader($configuration, $em);

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $post = new Post();
        $post
            ->setId(1)
            ->setAuthor($author)
            ->setTitle('First post')
            ->setBody('Here is the body')
            ->setCreatedAt(new \DateTime())
        ;

        $tag1 = new Tag();
        $tag1
            ->setId(1)
            ->setTitle('techno')
        ;

        $tag2 = new Tag();
        $tag2
            ->setId(2)
            ->setTitle('house')
        ;

        $post->addTag($tag1);
        $post->addTag($tag2);

        $mapping = [
            'fieldName' => 'tags',
            'joinTable' => [
                'name' => 'post__tag',
                'schema' => null,
                'joinColumns' => [
                    [
                        'name' => 'post_id',
                        'unique' => false,
                        'nullable' => false,
                        'onDelete' => null,
                        'columnDefinition' => null,
                        'referencedColumnName' => 'id',
                    ],
                ],
                'inverseJoinColumns' => [
                    [
                        'name' => 'tag_id',
                        'unique' => false,
                        'nullable' => false,
                        'onDelete' => null,
                        'columnDefinition' => null,
                        'referencedColumnName' => 'id',
                    ],
                ],
            ],
            'targetEntity' => Tag::class,
            'mappedBy' => null,
            'inversedBy' => 'posts',
            'cascade' => ['persist', 'remove'],
            'orphanRemoval' => false,
            'fetch' => 2,
            'type' => 8,
            'isOwningSide' => true,
            'sourceEntity' => Post::class,
            'isCascadeRemove' => true,
            'isCascadePersist' => true,
            'isCascadeRefresh' => false,
            'isCascadeMerge' => false,
            'isCascadeDetach' => false,
            'joinTableColumns' => ['post_id', 'tag_id'],
            'relationToSourceKeyColumns' => [
                'post_id' => 'id',
            ],
            'relationToTargetKeyColumns' => [
                'tag_id' => 'id',
            ],
        ];

        $manager->associate($em, $post, $tag1, $mapping);
        $manager->associate($em, $post, $tag2, $mapping);

        $audits = $reader->getAudits(Post::class);
        $this->assertCount(2, $audits, 'AuditManager::associate() creates an audit entry per association.');

        $entry = array_shift($audits);
        $this->assertSame(2, $entry->getId(), 'audit entry ID is ok.');
        $this->assertSame(AuditReader::ASSOCIATE, $entry->getType(), 'audit entry type is ok.');
        $this->assertEquals(1, $entry->getUserId(), 'audit entry blame_id is ok.');
        $this->assertSame('dark.vador', $entry->getUsername(), 'audit entry blame_user is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        $this->assertEquals([
            'source' => [
                'label' => (string) $post,
                'class' => Post::class,
                'table' => $em->getClassMetadata(Post::class)->getTableName(),
                'id' => 1,
            ],
            'target' => [
                'label' => Tag::class.'#2',
                'class' => Tag::class,
                'table' => $em->getClassMetadata(Tag::class)->getTableName(),
                'id' => 2,
            ],
            'table' => 'post__tag',
        ], $entry->getDiffs(), 'audit entry diffs is ok.');

        $entry = array_shift($audits);
        $this->assertSame(1, $entry->getId(), 'audit entry ID is ok.');
        $this->assertSame(AuditReader::ASSOCIATE, $entry->getType(), 'audit entry type is ok.');
        $this->assertEquals(1, $entry->getUserId(), 'audit entry blame_id is ok.');
        $this->assertSame('dark.vador', $entry->getUsername(), 'audit entry blame_user is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        $this->assertEquals([
            'source' => [
                'label' => (string) $post,
                'class' => Post::class,
                'table' => $em->getClassMetadata(Post::class)->getTableName(),
                'id' => 1,
            ],
            'target' => [
                'label' => Tag::class.'#1',
                'class' => Tag::class,
                'table' => $em->getClassMetadata(Tag::class)->getTableName(),
                'id' => 1,
            ],
            'table' => 'post__tag',
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testDissociateManyToMany(): void
    {
        $em = $this->getEntityManager();
        $configuration = $this->getAuditConfiguration();
        $helper = new AuditHelper($configuration);
        $manager = new AuditManager($configuration, $helper);
        $reader = new AuditReader($configuration, $em);

        $author = new Author();
        $author
            ->setId(1)
            ->setFullname('John Doe')
            ->setEmail('john.doe@gmail.com')
        ;

        $post = new Post();
        $post
            ->setId(1)
            ->setAuthor($author)
            ->setTitle('First post')
            ->setBody('Here is the body')
            ->setCreatedAt(new \DateTime())
        ;

        $tag1 = new Tag();
        $tag1
            ->setId(1)
            ->setTitle('techno')
        ;

        $tag2 = new Tag();
        $tag2
            ->setId(2)
            ->setTitle('house')
        ;

        $post->addTag($tag1);
        $post->addTag($tag2);

        $mapping = [
            'fieldName' => 'tags',
            'joinTable' => [
                'name' => 'post__tag',
                'schema' => null,
                'joinColumns' => [
                    [
                        'name' => 'post_id',
                        'unique' => false,
                        'nullable' => false,
                        'onDelete' => null,
                        'columnDefinition' => null,
                        'referencedColumnName' => 'id',
                    ],
                ],
                'inverseJoinColumns' => [
                    [
                        'name' => 'tag_id',
                        'unique' => false,
                        'nullable' => false,
                        'onDelete' => null,
                        'columnDefinition' => null,
                        'referencedColumnName' => 'id',
                    ],
                ],
            ],
            'targetEntity' => Tag::class,
            'mappedBy' => null,
            'inversedBy' => 'posts',
            'cascade' => ['persist', 'remove'],
            'orphanRemoval' => false,
            'fetch' => 2,
            'type' => 8,
            'isOwningSide' => true,
            'sourceEntity' => Post::class,
            'isCascadeRemove' => true,
            'isCascadePersist' => true,
            'isCascadeRefresh' => false,
            'isCascadeMerge' => false,
            'isCascadeDetach' => false,
            'joinTableColumns' => ['post_id', 'tag_id'],
            'relationToSourceKeyColumns' => [
                'post_id' => 'id',
            ],
            'relationToTargetKeyColumns' => [
                'tag_id' => 'id',
            ],
        ];

        $manager->associate($em, $post, $tag1, $mapping);
        $manager->associate($em, $post, $tag2, $mapping);

        $manager->dissociate($em, $post, $tag2, $mapping);

        $audits = $reader->getAudits(Post::class);
        $this->assertCount(3, $audits, 'AuditManager::dissociate() creates an audit entry.');

        $entry = array_shift($audits);
        $this->assertSame(3, $entry->getId(), 'audit entry ID is ok.');
        $this->assertSame(AuditReader::DISSOCIATE, $entry->getType(), 'audit entry type is ok.');
        $this->assertEquals(1, $entry->getUserId(), 'audit entry blame_id is ok.');
        $this->assertSame('dark.vador', $entry->getUsername(), 'audit entry blame_user is ok.');
        $this->assertSame('1.2.3.4', $entry->getIp(), 'audit entry IP is ok.');
        $this->assertEquals([
            'source' => [
                'label' => 'First post',
                'class' => Post::class,
                'table' => $em->getClassMetadata(Post::class)->getTableName(),
                'id' => 1,
            ],
            'target' => [
                'label' => Tag::class.'#2',
                'class' => Tag::class,
                'table' => $em->getClassMetadata(Tag::class)->getTableName(),
                'id' => 2,
            ],
            'table' => 'post__tag',
        ], $entry->getDiffs(), 'audit entry diffs is ok.');
    }

    public function testGetConfiguration(): void
    {
        $em = $this->getEntityManager();
        $configuration = $this->getAuditConfiguration();
        $helper = new AuditHelper($configuration);
        $manager = new AuditManager($configuration, $helper);

        $this->assertInstanceOf(AuditConfiguration::class, $manager->getConfiguration(), 'configuration instanceof AuditConfiguration::class');
    }

    public function testGetHelper(): void
    {
        $em = $this->getEntityManager();
        $configuration = $this->getAuditConfiguration();
        $helper = new AuditHelper($configuration);
        $manager = new AuditManager($configuration, $helper);

        $this->assertInstanceOf(AuditHelper::class, $manager->getHelper(), 'helper instanceof AuditHelper::class');
    }

    protected function setupEntities(): void
    {
    }
}
