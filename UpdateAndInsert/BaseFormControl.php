<?php

namespace SnapCRUD\UpdateAndInsert;

/**
 * BaseFormControl
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 * @abstract
 */
abstract class BaseFormControl extends \SnapCRUD\BaseControl {

    /**
     * Saves state of control (add, edit, update)
     * @var string
     */
    protected $state;
    /**
     * Nazov defaultneho action, ktory budu volat tlacitka add/cancel
     * @var string
     */
    protected $gridAction = 'default';
    /**
     * Events
     */
    public $onEdit;

    /**
     * Gets template filename
     * @return string
     */
    protected function getTemplateFilename() {
        if (!$this->templateFilename) {
            $this->templateFilename = __DIR__ . '/FormControl.latte';
        }
        return $this->templateFilename;
    }

    /**
     * Sets template
     * @param string $filename Sets
     * @return this
     */
    public function setTemplateFile($filename) {
        $this->template->setFile($filename);
        return $this;
    }

    /**
     * renderovanie obsahu controlu
     */
    public function render() {
        if (!(isset($this->template->title) and $this->template->title != '')) {
            $this->template->title = $this->getPresenter()->getWorkFlow()->getLast();
        }


        $this->template->setFile($this->getTemplateFilename());


        if ($this->template->getFile() == null) {
            $this->setTemplateFile(__DIR__ . '/FormControl.latte');
        }
        $this->template->form = $this->getForm();
        ob_start();

        $this->template->render();
        echo ob_get_clean();
    }

    /**
     * Gets form
     * @return \Nette\Application\UI\Form
     */
    public function getForm() {
        return $this['form'];
    }

    public function createComponentForm() {
        $form = new \Nette\Application\UI\Form($this, 'form');
        $form->getElementPrototype()->class('gridform');
        return $form;
    }

    /**
     * Setter, zmeni defaultny grid action, ak je iny ako "default"
     *
     * @param string $action
     */
    public function setDefaultGridAction($action) {
        $this->gridAction = $action;
    }

    /**
     * Returns state
     * @return string
     */
    public function getState() {
        return $this->state;
    }

    /**
     * Getter for file transaction
     * @return FileTransaction
     */
    public function getFileTransaction() {
        if (!$this->fileTransaction) {
            $this->fileTransaction = new FileTransaction();
        }
        return $this->fileTransaction;
    }

    public function addWorkflow($workflow) {
        $this->getPresenter()->getWorkFlow()->add($workflow);
    }

    public function setTitle($title) {
        $this->template->title = $title;
    }

}
