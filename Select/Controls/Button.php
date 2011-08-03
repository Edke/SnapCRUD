<?php

namespace SnapCRUD\Select\Controls;

use Nette\Utils\Html;

/**
 * Button element for DataGridControl
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 * @package    DataGrid2
 */
class Button extends \Nette\Object {

    /**
     * Core attributes
     */
    private $parent, $name, $submit;

    /**
     * Constructor
     * @param DataGridControl $parent
     * @param string $name
     */
    public function __construct($parent, $name, $submit) {
        $this->name = $name;
        $this->parent = $parent;
        $this->submit = $submit;
    }

    /**
     * Gets submit
     * @return \Nette\Forms\Controls\SubmitButton
     */
    private function getSubmit() {
        return $this->submit;
    }

    /**
     * Set buttonset
     * @param string $buttonset
     * @return this
     */
    public function setButtonset($buttonset) {
        $this->getSubmit()
                ->getControlPrototype()
                ->data('buttonset', $buttonset);
        return $this;
    }

    /**
     * Change button to dropdown master
     */
    public function dropdown() {
        $this->getSubmit()
                ->getControlPrototype()
                ->data('dropdown', 'toolbar[' . $this->name . ']');
        return $this;
    }

    /**
     * Set dropdown
     * @param string $dropdown
     * @return this
     */
    public function setDropdown($dropdown) {
        $this->getSubmit()
                ->getControlPrototype()
                ->data('dropdown', 'toolbar[' . $dropdown . ']');
        return $this;
    }

    /**
     * Set modal link
     * @param string $modalLink
     * @return this
     */
    public function setModalLink($modalLink) {
        $this->getSubmit()
                ->getControlPrototype()
                ->data('modal-link', $modalLink);
        return $this;
    }

}
