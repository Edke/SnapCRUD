<?php

namespace SnapCRUD\DataFeeds;

use Nette\DI;

/**
 * DataFeed for Nette\Database
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 */
class NetteDatabase implements IDataFeed {

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
    private $table;

    /**
     * @var DI\Container
     */
    private $context;

    /**
     * @var string
     */
    private $primaryKey = 'id';

    /**
     * @var string
     */
    private $active = 'master';

    /**
     * @var array of Nette\Database\Table\Selection
     */
    private $selections = array();

    /**
     * @param DI\Container $context
     * @param string $table
     */
    public function __construct(DI\Container $context, $table) {
        $this->table = $table;

        $this->context = new DI\Container;

        # lazy database
        $this->context->addService('database', function() use ($context) {
                    return $context->getService('database');
                });
    }

    /**
     * @param string $key
     * @return \Nette\Database\Table\Selection
     */
    public function getSelection($key = null, $table = null, $setActive = false) {
        if (is_null($key)) {
            $key = $this->active;
        }
        
        if ( is_null($table)) {
            $table = $this->table;
        }
        
        if ( $setActive) {
            $this->active = $key;
        }

        if (!\key_exists($key, $this->selections)) {
            $this->selections[$key] = $this->context->database->table($table);
        }
        return $this->selections[$key];
    }

    public function applyLimit($limit, $offset = null) {
        $this->getSelection()->limit($limit, $offset);
        return $this;
    }

    public function orderBy($row, $sorting = 'asc') {
        $this->getSelection()->order($row . ' ' . $sorting);
        return $this;
    }

    public function getIterator() {
        return $this->getSelection();
    }

    /**
     * @return \Nette\Database\Connection
     */
    public function getDatabase() {
        return $this->context->database;
    }

    /**
     * Clone new queryBuilder from active
     * @param string $key
     * @return this
     */
    public function cloneFromActive($key) {
        $this->selections[$key] = clone $this->getSelection();
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

    public function getCount() {
        return $this->getSelection()->count("*");
    }

    public function getItemName($id) {
        $id = (int) $id;
        if (!($id > 0))
            throw new Exception($this->primaryKey . ' is invalid');

        if (isset($this->itemName)) {
            if (is_string($this->itemName)) {
                $primaryKey = $this->getDatabase()->databaseReflection->getPrimary($this->table);
                $selection = clone $this->getSelection('master');
                $row = $selection
                        ->select($this->itemName)
                        ->where($primaryKey . ' = ?', $id)
                        ->fetch();
                return $row && isset($row[$this->itemName]) ? $row[$this->itemName] : null;
            } elseif (is_array($this->itemName)) {
                $concat = implode(" || ' ' || ", $this->itemName);
                return $this->getDibi()->fetchSingle("select $concat from %n", $this->tableName
                                , 'where ' . $this->primaryKey . '=%i', $id);
            } else {
                throw new Exception('itemName is invalid');
            }
        } else {
            $entity = $this->getSelection()->get($id);
        }
    }

    public function beginTransaction() {
        $this->getDatabase()->beginTransaction();
    }

    public function commitTransaction() {
        $this->getDatabase()->commit();
    }

    public function rollbackTransaction() {
        $this->getDatabase()->rollBack();
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
        
        $meta = array();
        foreach($this->getDatabase()->query("
                    SELECT attname, typname, typlen
                    FROM pg_attribute, pg_class, pg_type 
                    WHERE pg_class.oid = attrelid
                        AND atttypid=pg_type.oid
                        AND attnum>0 AND relname = ?", $this->table) as $row) {
            $meta[$row->attname] = $row;
        }

        if ($id > 0) {
            $row = $this->getSelection()->get($id);
            # update row values
            foreach ($values as $column => $value) {
                if ($row->offsetExists($column)) {
                    $row->offsetSet($column, $this->normalizeRow($value, $meta[$column]->typname));
                }
            }
            $row->update();
        } else {
            $insert = array();
            # filter values
            foreach($values as $column => $value) {
                if ( key_exists($column, $meta)) {
                    $insert[$column] = $this->normalizeRow($value, $meta[$column]->typname);
                }
            }
            $row = $this->getSelection()->insert($insert);
            
            # fix of lastinsertid for pg
            $pk = $this->getDatabase()->databaseReflection->getPrimary($this->table);
            $row[$pk] = $this->getDatabase()->lastInsertId($this->table.'_'.$pk.'_seq');
        }
        return $row;
    }
    
    /**
     * Normalizes value
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    private function normalizeRow($value, $type) {
        if ($value === '') {
            $value = null;
        }
        return $value;
    }

    /**
     * @param integer $id
     * @return \stdClass
     */
    public function getFormValues($id) {
        $selection = clone $this->getSelection();
        $row = $selection->get($id);
        return $row ? $row->toArray() : new \stdClass();
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
     * @todo get exception type, then complete try/catch
     */
    public function deleteRows($values) {
        try {
            $counter = 0;
            $valid = array();
            foreach ($values as $key => $value) {
                if ($value) {
                    $valid[] = $key;
                }
            }
            if (count($valid)) {
                $counter = $this->getSelection()->where($this->primaryKey, $valid)->delete();
            }
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
    public function where($condition, $parameters = array()) {
        $this->getSelection()->where($condition, $parameters);
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
        return $row->{$column};
    }

    
    /**
     * Executes aggregation
     * @param string $function 
     */
    public function aggregation($function) {
        return $this->getSelection()->aggregation($function);
    }
}
