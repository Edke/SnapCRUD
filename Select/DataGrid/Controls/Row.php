<?php

namespace SnapCRUD\Select\DataGrid\Controls;

use Nette\Utils\Html;

/**
 * Row element for DataGridControl
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 * @package    DataGrid2
 */
class Row extends \Nette\Object {

    /**
     * Core attributes
     */
    protected $parent, $name, $cells;
    /**
     * Properties
     */
    protected $useChecboxesColumn = false;
    /*
      protected $sortable = true, $headerClasses = array(), $label;
      protected $bodyClasses = array(), $bodyLinkCb, $bodyOnClickCb, $bodyContentCb, $bodyHtml;
      protected $footerClasses = array(), $footerContentCb, $footerContent; */

    /**
     * Constructor
     * @param DataGridControl $parent
     * @param string $name
     */
    public function __construct(DataGridControl $parent, $name) {
        $this->name = $name;
        $this->parent = $parent;
    }

    /**
     * Get name
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Get Parent
     * @return DataGridControl
     */
    protected function getParent() {
        return $this->parent;
    }

    /**
     * Add new RowCell in chain
     * @return RowCell
     */
    public function addCell() {
        $cell = new RowCell($this);
        $this->cells[] = $cell;
        return $cell;
    }

    /**
     * Get row's cells
     * @return array of RowCell
     */
    public function getCells() {
        return $this->cells;
    }

    /**
     * Get first cell
     * @return RowCell
     */
    protected function getFirstCell() {
        if (!isset($this->cells[0])) {
            $this->addCell();
        }
        return $this->cells[0];
    }

    /**
     * Get row's html
     * @return Html
     */
    public function getHtml() {
        #validate colspans
        $colspan = 0;
        $checkboxes = 0;
        foreach ($this->getCells() as $cell) {
            $colspan += $cell->getColspan();
        }

        if ($this->useChecboxesColumn == true && $this->getParent()->hasCheckboxes()) {
            $checkboxes = 1;
        }

        if (!($colspan == (1 + $checkboxes) && count($this->cells) == 1) &&
                !($colspan > 0 && $colspan == (count($this->getParent()->getColumns()) + $checkboxes))) {
            throw new \Exception("Invalid colspan scenario for row '" . $this->getParent()->getName() . "'");
        }

        $row = Html::el('tr');
        if ($this->useChecboxesColumn == false && $this->getParent()->hasCheckboxes()) {
            $row->add(Html::el('td')->setHtml('&nbsp;'));
        }

        foreach ($this->getCells() as $cell) {
            $row->add($cell->getHtml());
        }

        return $row;
    }

    /**
     * Configurators
     */

    /**
     * Add body class
     * @param string $class
     * @return this
     */
    public function addClass($class) {
        $this->getFirstCell()->addClass($class);
        return $this;
    }

    /**
     * Set callback for content of first cell
     * @param callback $callback
     * @return this
     */
    public function setContentCb($callback) {
        $this->getFirstCell()->setContentCb($callback);
        return $this;
    }

    /**
     * Set content for first cell
     * @param string $content
     * @return this
     */
    public function setContent($content) {
        $this->getFirstCell()->setContent($content);
        return $this;
    }

    /**
     * Set callback to create link (a href) for first cell
     * @param callback $callback
     * @return this
     */
    public function setLinkCb($callback) {
        $this->getFirstCell()->setLinkCb($callback);
        return $this;
    }

    /**
     * Set callback to create onClick link
     * @param callback $callback
     * @return this
     */
    public function setOnClickCb($callback) {
        $this->getFirstCell()->setOnClickCb($callback);
        return $this;
    }

    /**
     * Set colspan for cell
     * @param integer $colspan
     * @return RowCell
     */
    public function setColspan($colspan) {
        $this->getFirstCell()->setColspan($colspan);
        return $this;
    }

    /**
     * Use column for checboxes if exists
     * @return this
     */
    public function useCheckboxesColumn() {
        $this->useChecboxesColumn = true;
        return $this;
    }

}
