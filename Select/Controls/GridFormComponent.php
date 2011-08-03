<?php

namespace SnapCRUD\Select\Controls;

/**
 * GridFormComponent, form component to render grids inside forms
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 */
class GridFormComponent extends \Nette\Forms\Controls\Checkbox {

    protected $grid;

    /**
     * @param string $label
     * @param GridControl $grid
     */
    public function __construct($label, $grid) {
        parent::__construct(null);
        $this->grid = $grid;
        $this->grid->build();
    }

    /**
     * Form container extension method. Do not call directly.
     *
     * @param FormContainer $form
     * @param string $name
     * @param string $label
     * @param GridControl $grid
     * @return GridFormComponent
     */
    public static function addGrid(\Nette\Forms\Container $form, $name, $label, $grid) {
        $grid->setControls($form);
        $currentGroup = $form->getCurrentGroup();
        if ($currentGroup) {
            $grid->setCheckboxesGroup($currentGroup->getOption('label'));
        }
        return $form[$name] = new self($label, $grid);
    }

    public function getControl() {
        $this->setOption('rendered', TRUE);
        $control = $this->grid->getContent();
        /* $rules = self::exportRules($this->rules);
          $rules = substr(json_encode($rules), 1, -1);
          $rules = preg_replace('#"([a-z0-9]+)":#i', '$1:', $rules);
          $rules = preg_replace('#(?<!\\\\)"([^\\\\\',]*)"#i', "'$1'", $rules);
          $control->data['nette-rules'] = $rules ? $rules : NULL; */
        return $control;
    }

    public function getGrid() {
        return $this->grid;
    }

}
