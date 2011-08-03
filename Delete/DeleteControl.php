<?php

namespace SnapCRUD\Delete;

use Nette\Utils\Html;

/**
 * DeleteControl
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 */
class DeleteControl extends \SnapCRUD\BaseControl {

    /**
     * Events
     * @var array
     */
    public $onBefore, $onAfter;
    /**
     * @var string
     */
    protected $returningAction = 'default';
    /**
     * @var array
     */
    protected $rows;

    /**
     * @return \Nette\Application\UI\Form 
     */
    public function createComponentForm() {
        $this->rows = $this->getPresenter()->getNamespace()->delete;
        if (!is_array($this->rows)) {
            throw new \Exception('invalid rows for delete');
        }

        $text = \nt("Deleting of record", count($this->rows));
        $this->getPresenter()->getWorkFlow()->add($text);

        $form = new \Nette\Application\UI\Form($this, 'form');
        $form->setTranslator($this->context->translator);

        $text = \nt("Selected record", count($this->rows));
        $form->addGroup($text);
        $defaults = array();
        foreach ($this->rows as $value) {
            $form->addCheckbox((string) $value, $this->context->datafeed->getItemName($value));
            $defaults[$value] = true;
        }
        $form->setCurrentGroup();

        $form->addSubmit('delete', 'Confirm deletion')
                ->onClick[] = array($this, 'onDelete');

        $form->addSubmit('cancel', 'Cancel')
                ->onClick[] = array($this, 'onCancel');

        $form->setDefaults($defaults);

        return $form;
    }

    /**
     * @return \Nette\Application\UI\Form
     */
    public function getForm() {
        return $this['form'];
    }

    /**
     */
    public function render() {
        // setup custom rendering
        $renderer = $this->getForm()->getRenderer();
        $renderer->wrappers['form']['container'] = NULL;
        $renderer->wrappers['form']['errors'] = FALSE;
        $renderer->wrappers['control']['errors'] = FALSE;
        $renderer->wrappers['group']['container'] = 'fieldset';
        $renderer->wrappers['group']['label'] = 'legend';
        $renderer->wrappers['pair']['container'] = 'tr';
        $renderer->wrappers['controls']['container'] = Html::el('table')->class('formlayout');
        $renderer->wrappers['control']['container'] = Html::el('td');
        $renderer->wrappers['control']['.odd'] = 'odd';
        $renderer->wrappers['label']['container'] = Html::el('td');
        $renderer->wrappers['label']['suffix'] = FALSE;

        $this->template->setFile($this->getTemplateFilename());
        $this->template->records = count($this->rows);
        $this->template->form = $this->getForm();
        $this->template->formControl = $this;

        ob_start();
        $this->template->render();

        echo ob_get_clean();
    }

    /**
     * @param \Nette\Forms\Controls\SubmitButton $button
     */
    public function onCancel(\Nette\Forms\Controls\SubmitButton $button) {
        unset($this->getPresenter()->getNamespace()->delete);
        $this->getPresenter()->flashMessage('Deleting of records has been canceled.', 'warning');

        #backlink handling
        $lastId = $this->getPresenter()->getNamespace()->lastId;
        if ($lastId) {
            $this->getPresenter()->redirect($this->returningAction, $lastId);
        }
        $this->getPresenter()->redirect($this->returningAction);
    }

    /**
     * @param \Nette\Forms\Controls\SubmitButton $button
     */
    public function onDelete(\Nette\Forms\Controls\SubmitButton $button) {
        $values = (object) $button->getForm()->getValues();

        $this->onBefore(&$values);

        $this->context->datafeed->beginTransaction();
        $result = $this->context->datafeed->deleteRows($values);
        if (\is_object($result)) {
            $this->context->datafeed->rollbackTransaction();
            $message = tc("Error occured during deleting records from db: %s", $result->getMessage());
            $this->getPresenter()->flashMessage($message);
            $this->getPresenter()->redirect($this->returningAction);
        }
        $this->onAfter(&$values);
        $this->context->datafeed->commitTransaction();

        if ($result > 0) {
            $message = \nt("Selected record was successfully deleted.", $result);
            $this->getPresenter()->flashMessage($message, 'error');
        }

        #backlink handling
        $lastId = $this->getPresenter()->getNamespace()->lastId;
        if ($lastId) {
            $this->getPresenter()->redirect($this->returningAction, $lastId);
        }
        $this->getPresenter()->redirect($this->returningAction);
    }

    /**
     * Sets redirecting action after events, default is "default"
     *
     * @param string $action
     */
    public function setReturningAction($action) {
        $this->returningAction = $action;
    }

}
