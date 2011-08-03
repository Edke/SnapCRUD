<?php

namespace SnapCRUD\Select\ImageGrid\Controls;

/**
 * SortColumn
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 */
class SortColumn extends \Nette\Object {

    /**
     * Rodic
     * @var DataGridControl
     */
    protected $parent;
    /**
     * meno prvku
     * @var string
     */
    protected $name;
    /**
     * popis prvku
     * @var string
     */
    protected $label;

    /**
     * Konstruktor
     * @param DataGridControl $parent
     * @param string $name
     * @param string $label
     * @param string $description
     */
    public function __construct($parent, $name, $label) {
        $this->parent = $parent;
        $this->name = $name;
        $this->label = $label;
    }

    /**
     * je podla tohto stlpca triedene ?
     * @return boolean
     */
    protected function isSorted() {
        return ( isset($this->parent->getNamespace()->orderBy->{$this->name}) ) ? true : false;
    }

    /**
     * vrati sortovaciu ikonku
     * @return string
     */
    protected function getIcon() {
        if (isset($this->parent->getNamespace()->orderBy->{$this->name})) {
            return $this->parent->getNamespace()->orderBy->{$this->name};
        } else {
            return 'none';
        }
    }

    /**
     * vrati nazov prvku
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * nastavi defaultny sort order
     * @param string $direction
     * @return ImageSortColumn
     */
    public function setDefaultOrder($direction) {
        if (empty($this->name)) {
            throw new Exception('column name must be defined');
        }

        $this->parent->setDefaultOrder($this->name, $direction);
        return $this;
    }

    /**
     * vytvori objekt pre sablonu
     * @return stdClass
     */
    public function templatize() {
        return (object) array(
            'name' => $this->name,
            'label' => $this->label,
            'name' => $this->name,
            'sorted' => $this->isSorted(),
            'icon' => $this->getIcon(),
        );
    }

}
