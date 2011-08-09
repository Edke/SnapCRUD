<?php

namespace SnapCRUD\DataFeeds;

/**
 * Interface IDataFeed
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 */
interface IDataFeed {
    public function applyLimit($length, $offset = null);

    public function getCount();

    public function getIterator();

    public function getFormValues($id);

    public function getEmptyValues();

    public function getItemName($id);

    public function setItemName($mixed);

    public function orderBy($row, $sorting);

    public function beginTransaction();

    public function commitTransaction();

    public function rollbackTransaction();

    public function save($values, $id);

    public function deleteRows($values);

    public function where($query, $value);

    public function cloneFromActive($key);

    public function changeActive($key);

    public function setPrimaryKey($pk);
    
    public function getColumnFromRow($row, $column);
    
    public function getAggregate($columns);
}
