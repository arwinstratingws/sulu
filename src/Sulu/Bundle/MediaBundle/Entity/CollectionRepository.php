<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\MediaBundle\Entity;

use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Sulu\Component\Media\SystemCollections\SystemCollectionManagerInterface;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Component\Security\Authorization\AccessControl\SecuredEntityRepositoryTrait;

/**
 * CollectionRepository.
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class CollectionRepository extends NestedTreeRepository implements CollectionRepositoryInterface
{
    use SecuredEntityRepositoryTrait;

    /**
     * {@inheritdoc}
     */
    public function findCollectionById($id)
    {
        $dql = sprintf(
            'SELECT n, collectionMeta, defaultMeta, collectionType, collectionParent, parentMeta, collectionChildren
                 FROM %s AS n
                     LEFT JOIN n.meta AS collectionMeta
                     LEFT JOIN n.defaultMeta AS defaultMeta
                     LEFT JOIN n.type AS collectionType
                     LEFT JOIN n.parent AS collectionParent
                     LEFT JOIN n.children AS collectionChildren
                     LEFT JOIN collectionParent.meta AS parentMeta
                 WHERE n.id = :id',
            $this->_entityName
        );

        $query = new Query($this->_em);
        $query->setDQL($dql);
        $query->setParameter('id', $id);
        $result = $query->getResult();

        if (count($result) === 0) {
            return;
        }

        return $result[0];
    }

    /**
     * {@inheritdoc}
     */
    public function findCollectionSet(
        $depth = 0,
        $filter = [],
        CollectionInterface $collection = null,
        $sortBy = [],
        UserInterface $user = null,
        $permission = null
    ) {
        $ids = $this->getIdsQuery($depth, $filter, $sortBy, $collection)->getScalarResult();

        $queryBuilder = $this->createQueryBuilder('collection')
            ->addSelect('collectionMeta')
            ->addSelect('defaultMeta')
            ->addSelect('collectionType')
            ->addSelect('collectionParent')
            ->addSelect('parentMeta')
            ->addSelect('collectionChildren')
            ->leftJoin('collection.meta', 'collectionMeta')
            ->leftJoin('collection.defaultMeta', 'defaultMeta')
            ->leftJoin('collection.type', 'collectionType')
            ->leftJoin('collection.parent', 'collectionParent')
            ->leftJoin('collection.children', 'collectionChildren')
            ->leftJoin('collectionParent.meta', 'parentMeta')
            ->where('collection.id IN (:ids)')
            ->setParameter('ids', $ids);

        if ($sortBy !== null && is_array($sortBy) && count($sortBy) > 0) {
            foreach ($sortBy as $column => $order) {
                $queryBuilder->addOrderBy(
                    'collectionMeta.' . $column,
                    (strtolower($order) === 'asc' ? 'ASC' : 'DESC')
                );
            }
        }

        $queryBuilder->addOrderBy('collection.id', 'ASC');

        if ($user !== null && $permission != null) {
            $this->addAccessControl($queryBuilder, $user, $permission, Collection::class, 'collection');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * {@inheritdoc}
     */
    public function count($depth = 0, $filter = [], CollectionInterface $collection = null)
    {
        $ids = $this->getIdsQuery($depth, $filter, [], $collection, 'DISTINCT collection.id')->getScalarResult();
        try {
            return count($ids);
        } catch (NoResultException $e) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findCollections($filter = [], $limit = null, $offset = null, $sortBy = [])
    {
        list($parent, $depth, $search) = [
            isset($filter['parent']) ? $filter['parent'] : null,
            isset($filter['depth']) ? $filter['depth'] : null,
            isset($filter['search']) ? $filter['search'] : null,
        ];

        try {
            $qb = $this->createQueryBuilder('collection')
                ->leftJoin('collection.meta', 'collectionMeta')
                ->leftJoin('collection.defaultMeta', 'defaultMeta')
                ->leftJoin('collection.type', 'type')
                ->leftJoin('collection.parent', 'parent')
                ->leftJoin('collection.children', 'children')
                ->addSelect('collectionMeta')
                ->addSelect('defaultMeta')
                ->addSelect('type')
                ->addSelect('parent')
                ->addSelect('children');

            if ($sortBy !== null && is_array($sortBy) && count($sortBy) > 0) {
                foreach ($sortBy as $column => $order) {
                    $qb->addOrderBy('collectionMeta.' . $column, strtolower($order) === 'asc' ? 'ASC' : 'DESC');
                }
            }
            $qb->addOrderBy('collection.id', 'ASC');

            if ($parent !== null) {
                $qb->andWhere('parent.id = :parent');
            }
            if ($depth !== null) {
                $qb->andWhere('collection.depth <= :depth');
            }
            if ($search !== null) {
                $qb->andWhere('collectionMeta.title LIKE :search');
            }
            if ($offset !== null) {
                $qb->setFirstResult($offset);
            }
            if ($limit !== null) {
                $qb->setMaxResults($limit);
            }

            $query = $qb->getQuery();
            if ($parent !== null) {
                $query->setParameter('parent', $parent);
            }
            if ($depth !== null) {
                $query->setParameter('depth', intval($depth));
            }
            if ($search !== null) {
                $query->setParameter('search', '%' . $search . '%');
            }

            return new Paginator($query);
        } catch (NoResultException $ex) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findCollectionBreadcrumbById($id)
    {
        try {
            $sql = sprintf(
                'SELECT n, collectionMeta, defaultMeta
                 FROM %s AS p,
                      %s AS n
                        LEFT JOIN n.meta AS collectionMeta
                        LEFT JOIN n.defaultMeta AS defaultMeta
                 WHERE p.id = :id AND p.lft > n.lft AND p.rgt < n.rgt
                 ORDER BY n.lft',
                $this->_entityName,
                $this->_entityName
            );

            $query = new Query($this->_em);
            $query->setDQL($sql);
            $query->setParameter('id', $id);

            return $query->getResult();
        } catch (NoResultException $ex) {
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findCollectionByKey($key)
    {
        $queryBuilder = $this->createQueryBuilder('collection')
            ->leftJoin('collection.meta', 'collectionMeta')
            ->leftJoin('collection.defaultMeta', 'defaultMeta')
            ->where('collection.key = :key');

        $query = $queryBuilder->getQuery();
        $query->setParameter('key', $key);

        try {
            return $query->getSingleResult();
        } catch (NoResultException $ex) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findTree($id, $locale)
    {
        $subQueryBuilder = $this->createQueryBuilder('subCollection')
            ->select('subCollection.id')
            ->leftJoin($this->_entityName, 'c', Join::WITH, 'c.id = :id')
            ->andWhere('subCollection.lft <= c.lft AND subCollection.rgt > c.lft');

        $queryBuilder = $this->createQueryBuilder('collection')
            ->addSelect('meta')
            ->addSelect('defaultMeta')
            ->addSelect('type')
            ->addSelect('parent')
            ->leftJoin('collection.meta', 'meta', Join::WITH, 'meta.locale = :locale')
            ->leftJoin('collection.defaultMeta', 'defaultMeta')
            ->innerJoin('collection.type', 'type')
            ->leftJoin('collection.parent', 'parent')
            ->where(sprintf('parent.id IN (%s)', $subQueryBuilder->getDQL()))
            ->orWhere('parent.id is NULL')
            ->orderBy('collection.lft')
            ->setParameter('id', $id)
            ->setParameter('locale', $locale);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * {@inheritdoc}
     */
    public function findCollectionTypeById($id)
    {
        $queryBuilder = $this->createQueryBuilder('collection')
            ->select('collectionType.key')
            ->leftJoin('collection.type', 'collectionType')
            ->where('collection.id = :id')
            ->setParameter('id', $id);

        return $queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * Returns the basic query which selects the ids of a collection for a given
     * set of parameters.
     *
     * @param int $depth
     * @param array $filter
     * @param array $sortBy
     * @param CollectionInterface|null $collection
     * @param string $select
     *
     * @return Query
     */
    private function getIdsQuery(
        $depth = 0,
        $filter = [],
        $sortBy = [],
        CollectionInterface $collection = null,
        $select = 'collection.id'
    ) {
        $queryBuilder = $this->createQueryBuilder('collection')
            ->select($select)
            ->leftJoin('collection.children', 'collectionChildren')
            ->where('collection.depth <= :depth1 OR collectionChildren.depth <= :depth2');

        $collectionDepth = $collection !== null ? $collection->getDepth() : 0;
        $queryBuilder->setParameter('depth1', $collectionDepth + $depth);
        $queryBuilder->setParameter('depth2', $depth + 1);

        if ($collection !== null) {
            $queryBuilder->andWhere('collection.lft BETWEEN :lft AND :rgt AND collection.id != :id');
            $queryBuilder->setParameter('lft', $collection->getLft());
            $queryBuilder->setParameter('rgt', $collection->getRgt());
            $queryBuilder->setParameter('id', $collection->getId());
        }

        if (array_key_exists('search', $filter) && $filter['search'] !== null ||
            array_key_exists('locale', $filter) ||
            count($sortBy) > 0
        ) {
            $queryBuilder->leftJoin('collection.meta', 'collectionMeta');
            $queryBuilder->leftJoin('collection.defaultMeta', 'defaultMeta');
        }

        if (array_key_exists('search', $filter) && $filter['search'] !== null) {
            $queryBuilder->andWhere('collectionMeta.title LIKE :search OR defaultMeta.locale != :locale');
            $queryBuilder->setParameter('search', '%' . $filter['search'] . '%');
        }

        if (array_key_exists('locale', $filter)) {
            $queryBuilder->andWhere('collectionMeta.locale = :locale OR defaultMeta.locale != :locale');
            $queryBuilder->setParameter('locale', $filter['locale']);
        }

        if (array_key_exists('systemCollections', $filter) && !$filter['systemCollections']) {
            $queryBuilder->leftJoin('collection.type', 'collectionType');
            $queryBuilder->andWhere('collectionType.key != :type');
            $queryBuilder->setParameter('type', SystemCollectionManagerInterface::COLLECTION_TYPE);
        }

        if (count($sortBy) > 0) {
            foreach ($sortBy as $column => $order) {
                $queryBuilder->addOrderBy(
                    'collectionMeta.' . $column,
                    (strtolower($order) === 'asc' ? 'ASC' : 'DESC')
                );
            }
        }

        $queryBuilder->addOrderBy('collection.id', 'ASC');

        if (array_key_exists('limit', $filter)) {
            $queryBuilder->setMaxResults($filter['limit']);
        }
        if (array_key_exists('offset', $filter)) {
            $queryBuilder->setFirstResult($filter['offset']);
        }

        return $queryBuilder->getQuery();
    }
}
