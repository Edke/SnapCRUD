<?php

namespace SnapCRUD\Select\Controls;

/**
 * Condition
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 * @package    DataGrid2
 */
class Condition extends \Nette\Object {

    /**
     * Parent
     * @var GridControl
     */
    protected $parent;
    /**
     * Condition query
     * @var string
     */
    protected $query;
    /**
     * Callback
     * @var callback
     */
    protected $callback;

    /**
     * Constructor
     * @param GridControl $parent
     * @param string $query
     */
    public function __construct($parent, $query = null) {
        $this->parent = $parent;
        $this->query = $query;
    }

    /**
     * Determines whether condition has callback
     * @return boolean
     */
    public function hasCallback() {
        return isset($this->callback);
    }

    /**
     * Get callback
     * @return callback
     */
    public function getCallback() {
        return $this->callback;
    }

    /**
     * Set callback
     * @param callback $callback
     * @return this
     */
    public function setCallback($callback) {
        $this->callback = $callback;
        return $this;
    }

    /**
     * Get Parent
     * @return GridControl
     */
    public function getParent() {
        return $this->parent;
    }

    /**
     * Get Query
     * @return string
     */
    public function getQuery() {
        if ($this->getParent()->getReflection()->getShortName() == 'Column') {
            return \str_replace('%n', $this->getParent()->getName('sql'), $this->query);
        } else {
            return $this->query;
        }
    }

}
