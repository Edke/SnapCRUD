<?php

namespace SnapCRUD\DataFeeds;

/**
 * DataFeed for Dibi
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 */
class Dibi implements IDataFeed {

    private $tableName;
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
     * @var \DibiDataSource
     */
    private $dibiDataSource = array();
    /**
     * @var string
     */
    private $active = 'master';
    /**
     * @var DI\Container
     */
    private $context;

    /**
     * @param DI\Container $context
     * @param string $entityName 
     */
    public function __construct(DI\Container $context, $tableName) {
        $this->tableName = $tableName;

        $this->context = new DI\Container;

        # lazy dibi
        $this->context->addService('dibi', function() use ($context) {
                    return $context->getService('dibi');
                });
    }

    public function applyLimit($limit, $offset = null) {
        $this->getDibiDataSource()->applyLimit($limit, $offset);
    }

    public function orderBy($row, $sorting = 'asc') {
        $this->getDibiDataSource()->orderBy($row, $sorting);
    }

    public function getIterator() {
        $this->getDibiDataSource()->getIterator();
    }

    public function setQuery($query) {
        $this->dibiDataSource[$this->active] = $this->connection->dataSource($query, $this->tableName);
    }

    public function getCount() {
        return count($this->getDibiDataSource());
    }

    public function getItemName($id) {
        $id = (int) $id;
        if (!($id > 0))
            throw new Exception('id is invalid');

        if ($this->itemNameQuery) {
            if (!is_string($this->itemNameQuery))
                throw new Exception('itemNameQuerconnectiony is invalid');

            return $this->getConnection()->fetchSingle($this->itemNameQuery, $this->tableName, $id);
        }
        else {
            if (!(is_string($this->itemName) or is_array($this->itemName)))
                throw new Exception('itemName is invalid');

            elseif (is_string($this->itemName)) {
                return $this->getConnection()->fetchSingle('select %n', $this->itemName, 'from %n', $this->tableName
                        , 'where id=%i', $id);
            } else {
                $concat = implode(" || ' ' || ", $this->itemName);
                return $this->getConnection()->fetchSingle("select $concat from %n", $this->tableName
                        , 'where id=%i', $id);
            }
        }
    }

    /**
     * Connection getter
     * @return \DibiConnection
     */
    public function getConnection() {
        return $this->context->dibi;
    }

    public function beginTransaction() {
        $this->getConnection()->begin();
    }

    public function commitTransaction() {
        $this->getConnection()->commit();
    }

    public function rollbackTransaction() {
        $this->getConnection()->rollback();
    }

    /**
     * @param \stdClass $values
     * @param integer $id
     * @return integer
     */
    public function save($values, $id) {
        if (!($values instanceof \stdClass or $values instanceof \Nette\ArrayHash)) {
            throw new \InvalidArgumentException();
        }

        if ($id > 0) {
            $this->getConnection()->query('update %n set', $this->tableName, (array) $values, 'where id=%i', $id);
            return $id;
        } else {
            $this->getConnection()->query('insert into %n', $this->tableName, (array) $values);
            return $this->getConnection()->insertId();
        }
    }

    /**
     * @param integer $id
     * @return \DibiRow
     */
    public function getFormValues($id) {
        return $this->getConnection()
                ->fetch("select * from %n where id = %i", $this->tableName, $id);
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
     * @param array $values
     * @return DibiDriverException|integer
     */
    public function deleteRows($values) {
        $counter = 0;
        try {
            foreach ($values as $key => $value) {
                if ($value) {
                    $this->getConnection()->query("delete from %n", $this->tableName, 'where id=%i', $key);
                    $counter++;
                }
            }
            return $counter;
        } catch (DibiDriverException $e) {
            return $e;
        }
    }

    /**
     * Clone new queryBuilder from active
     * @param string $key
     * @return this
     */
    public function cloneFromActive($key) {
        $this->dibiDataSource[$key] = clone $this->getDibiDataSource();
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

    public function getDibiDataSource($key = null) {
        if (is_null($key)) {
            $key = $this->active;
        }

        if (!\key_exists($key, $this->dibiDataSource)) {
            $this->dibiDataSource[$key] = $this->getConnection()->dataSource();
        }
        return $this->dibiDataSource[$key];
    }

    public function where($query, $value) {
        $this->getDibiDataSource()->where($query, $value);
    }

}
