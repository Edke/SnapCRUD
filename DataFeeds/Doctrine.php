<?php

namespace SnapCRUD\DataFeeds;

use Nette\DI,
    SnapCRUD\Tools\EntityTools;

/**
 * DataFeed for Doctrine 2
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 */
class Doctrine implements IDataFeed {

    /**
     * Definicia stlpca, podla ktoreho sa sklada nazov riadku
     * Pouziva sa napriklad pri zobrazeni zaznamov, ktore sa maju mazat
     * @var mixed string (nazov stlpca) alebo array ( nazov sa sklada ako concate stlpcov v poli)
     */
    private $itemName;
    /**
     * Query pre vyskladanie nazvu riadku
     * V pripade, ze predchadzajuci mechanizmus cez $itemName nepostacuje (napriklad nazov sa sklada z viacerych
     * stlpcov z viacerych tabuliek a je potrebne pouzit LEFT JOIN), zada sa cele query, ktore obsahuje modifikatory
     * %n (nahradi sa nazvom tabulky, teda $this->presenterName) a %i (nahradi sa id-ckom riadku)
     * @var string
     */
    private $itemNameQuery;
    /**
     * @var string
     */
    private $entityName;
    /**
     * @var string
     */
    private $entityAlias;
    /**
     * @var \Doctrine\ORM\QueryBuilder
     */
    private $queryBuilder = array();
    /**
     * @var string
     */
    private $active = 'master';
    /**
     * @var string
     */
    private $primaryKey = 'id';
    /**
     * @var DI\Container
     */
    private $context;

    /**
     * @param DI\Container $context
     * @param string $entityName 
     */
    public function __construct(DI\Container $context, $entityName) {
        if (\preg_match('#^([^ ]+)\s+(.+)$#', $entityName, $match)) {
            $this->entityName = $match[1];
            $this->entityAlias = $match[2];
        } else {
            $this->entityName = $entityName;
        }

        $this->context = new DI\Container;

        # lazy doctrine
        $this->context->addService('doctrine', function() use ($context) {
                    return $context->getService('doctrine');
                });

        # lazy database
        $this->context->addService('database', function() use ($context) {
                    return $context->getService('database');
                });
    }

    public function applyLimit($limit, $offset = null) {
        $this->getQueryBuilder()->setMaxResults($limit);
        if ($offset > 0) {
            $this->getQueryBuilder()->setFirstResult($offset);
        }
    }

    public function orderBy($row, $sorting = 'asc') {
        $this->getQueryBuilder()->addOrderBy($row, $sorting);
    }

    public function getIterator() {
        return $this->getQueryBuilder()->getQuery()->getResult();
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    public function getEntityManager() {
        return $this->context->doctrine;
    }

    /**
     * Clone new queryBuilder from active
     * @param string $key
     * @return this
     */
    public function cloneFromActive($key) {
        $this->queryBuilder[$key] = clone $this->getQueryBuilder();
        return $this;
    }

    /**
     * Change active
     * @param string $key
     * @return DoctrineDataFeed Chage
     */
    public function changeActive($key) {
        $this->active = $key;
        return $this;
    }

    /**
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function getQueryBuilder($key = null) {
        if (is_null($key)) {
            $key = $this->active;
        }

        if (!\key_exists($key, $this->queryBuilder)) {
            $this->queryBuilder[$key] = $this->getEntityManager()->createQueryBuilder();
        }
        return $this->queryBuilder[$key];
    }

    public function getCount() {
        $qb = clone $this->getQueryBuilder();
        return $qb->select('count(' . $this->entityAlias . ')')
                ->getQuery()
                ->getSingleScalarResult();
    }

    public function getItemName($id) {
        $id = (int) $id;
        if (!($id > 0))
            throw new Exception($this->primaryKey . ' is invalid');

        if (isset($this->itemName)) {
            if (is_string($this->itemName)) {
                return $this->getEntityManager()
                        ->createQueryBuilder()
                        ->select($this->itemName)
                        ->from($this->entityName, $this->entityAlias)
                        ->where($this->entityAlias . '.' . $this->primaryKey . ' = ?1')
                        ->setParameter(1, $id)
                        ->getQuery()
                        ->getSingleScalarResult();
            } elseif (is_array($this->itemName)) {
                $concat = implode(" || ' ' || ", $this->itemName);
                return $this->getDibi()->fetchSingle("select $concat from %n", $this->tableName
                        , 'where ' . $this->primaryKey . '=%i', $id);
            } else {
                throw new Exception('itemName is invalid');
            }
        } else {
            $entity = $this->getEntityManager()->find($this->entityName, $id);
            return $entity->getItemName();
        }
    }

    public function beginTransaction() {
        //$this->getEntityManager()->getConnection()->beginTransaction();
    }

    public function commitTransaction() {
        $this->getEntityManager()->flush();
        //$this->getEntityManager()->getConnection()->commit();
    }

    public function rollbackTransaction() {
        //$this->getEntityManager()->getConnection()->rollback();
    }

    /**
     * @param \stdClass|\Nette\ArrayHash $values
     * @param integer $id
     * @return null|integer
     */
    public function save($values, $id) {
        if (!($values instanceof \stdClass or $values instanceof \Nette\ArrayHash)) {
            throw new \InvalidArgumentException();
        }

        if ($id > 0) {
            $entity = $this->getEntityManager()->find($this->entityName, $id);
            EntityTools::fill($entity, $values);
        } else {
            $entity = new $this->entityName;
            EntityTools::fill($entity, $values);
            $this->getEntityManager()->persist($entity);
        }
        return $entity;
    }

    /**
     * @param integer $id
     * @return \stdClass
     */
    public function getFormValues($id) {
        $entity = $this->getEntityManager()->find($this->entityName, $id);
        return $entity ? (object) EntityTools::toArray($entity) : new \stdClass();
    }

    public function getEntity($id) {
        return $this->getEntityManager()->find($this->entityName, $id);
    }

    /**
     * @return \stdClass
     */
    public function getEmptyValues() {
        return new \stdClass();
    }

    /**
     * @param string|array $mixed
     */
    public function setItemName($mixed) {
        $this->itemName = $mixed;
    }

    /**
     * @param string $query
     */
    public function setItemNameQuery($query) {
        $this->itemNameQuery = $query;
    }

    /**
     * Connection getter
     * @return \DibiConnection
     */
    public function getDatabase() {
        return $this->context->database;
    }

    /**
     * @param array $values
     * @return DibiDriverException|integer
     * @todo get exception type, then complete try/catch
     */
    public function deleteRows($values) {
        $counter = 0;
        try {
            foreach ($values as $key => $value) {
                if ($value) {
                    $entity = $this->getEntityManager()->find($this->entityName, $key);
                    $this->getEntityManager()->remove($entity);
                    $counter++;
                }
            }
            $this->getEntityManager()->flush();
            return $counter;
        } catch (\PDOException $e) {
            return $e;
        }
    }

    /**
     * Add condition to QueryBuilder
     * @param string $query
     * @param mixed $value
     */
    public function where($query, $value) {
        $keys = $this->getQueryBuilder()
                ->getParameters();
        $key = (\is_array($keys) && count($keys)) ? \max(array_keys($keys)) + 1 : 1;
        $query = str_replace('?', '?' . $key, $query);
        $this->getQueryBuilder()
                ->andWhere($query)
                ->setParameter($key, $value);
    }

    /**
     * Set primary key name
     * @param string $pk
     * @return this
     */
    public function setPrimaryKey($pk) {
        $this->primaryKey = $pk;
        return $this;
    }

    public function getColumnFromRow($row, $column) {
        $getter = 'get' . ucfirst($column);
        return $row->$getter();
    }

}
