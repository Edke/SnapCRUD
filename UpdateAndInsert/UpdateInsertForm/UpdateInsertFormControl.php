<?php

namespace SnapCRUD\UpdateAndInsert\UpdateInsertForm;

use Nette\Forms\Controls\SubmitButton;

/**
 * UpdateInsertFormControl
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 */
class UpdateInsertFormControl extends \SnapCRUD\UpdateAndInsert\BaseFormControl {
    const STATE_EDIT = 'edit';
    const STATE_ADD = 'add';
    const STATE_UPDATE = 'update';
    const STATE_INSERT = 'insert';


    /**
     * Events
     */
    public $onAdd, $onBeforeSave, $onBeforeUpdate, $onBeforeInsert, $onAfterUpdate, $onAfterInsert, $onAfterSave, $onRestore;
    /**
     * @var FileTransaction
     */
    protected $fileTransaction;

    /**
     * Konstruktor
     * @param \Nette\ComponentModel\IContainer $parent
     * @param string $name
     */
    public function __construct(\Nette\ComponentModel\IContainer $parent, $name) {
        parent::__construct($parent, $name);

        # state
        $id = (int) $this->getPresenter()->getParam('id');
        if (!$this->getForm()->isSubmitted() and $id == 0) {
            $this->state = UpdateInsertFormControl::STATE_ADD;
        } elseif (!$this->getForm()->isSubmitted() and $id != 0) {
            $this->state = UpdateInsertFormControl::STATE_EDIT;
        } elseif ($this->getForm()->isSubmitted() and $id == 0) {
            $this->state = UpdateInsertFormControl::STATE_INSERT;
        } elseif ($this->getForm()->isSubmitted() and $id != 0) {
            $this->state = UpdateInsertFormControl::STATE_UPDATE;
        } else {
            throw new \Exception('Unable to determine state');
        }

        # workflow
        $id = (int) $this->getPresenter()->getParam('id');
        if ($id == 0) {
            $this->getPresenter()->getWorkFlow()->add(_('New record'));
        } else {
            $this->getPresenter()->getWorkFlow()->add(\tc("Editing record '%s'", $this->context->datafeed->getItemName($id)));
        }
    }

    /**
     * @inheritdoc
     */
    public function createComponentForm() {
        $form = parent::createComponentForm();

        # default submit buttons
        $form->setCurrentGroup();
        $id = (int) $this->getPresenter()->getParam('id');

        $applyText = $id > 0 ? _('Update') : _('Add');
        $form->addSubmit('apply', $applyText)
                ->onClick[] = array($this, 'form_onApply');

        $form->addSubmit('cancel', 'Cancel')
                        ->setValidationScope(FALSE)
                ->onClick[] = array($this, 'form_onCancel');
        return $form;
    }

    public function setDefaultValues() {
        if ($this->getPresenter()->getParam('restore')) {
            $defaults = $this->getPresenter()->getNamespace()->saved_form;
            $this->onRestore(&$defaults);
            $this->getForm()->setDefaults($defaults);
        } elseif ($this->getPresenter()->getParam('return')) {
            $defaults = $this->getPresenter()->getNamespace()->saved_form;
            $this->getForm()->setDefaults($defaults);
        } elseif ($this->state == UpdateInsertFormControl::STATE_EDIT) {
            # TODO nastavitelny kluc
            $id = (int) $this->getPresenter()->getParam('id');
            $defaults = $this->context->datafeed->getFormValues($id);
            $this->onEdit(&$defaults);
            $this->getForm()->setDefaults((array) $defaults);
        } elseif ($this->state == UpdateInsertFormControl::STATE_ADD) {
            $defaults = $this->context->datafeed->getEmptyValues();
            $this->onAdd(&$defaults);
            $this->getForm()->setDefaults((array) $defaults);
        }
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

    /**
     * Handler onCancel pre view Form
     *
     * Pri zruseni pridavania/upravovania formulara sa vracia na view Default a naplni message
     *
     * @todo overenie zmeneneho formulara
     * @param SubmitButton $button
     */
    public function form_onCancel(SubmitButton $button) {
        $id = (int) $this->getParam('id');

        $text = ($id > 0) ? _('Adding new record was canceled.') : _('Updating record was canceled, no record was modified.');
        $this->getPresenter()->flashMessage($text, 'warning');

        #gridBacklink handling
        $backlink = $this->getPresenter()->getParam('_bl');
        if ($backlink) {
            $this->getPresenter()->_bl = '';
            $this->getPresenter()->restoreBacklink($backlink);
        } else {
            $this->getPresenter()->redirect($this->gridAction);
        }
    }

    public function form_onApply(SubmitButton $button) {
        $id = (int) $this->getPresenter()->getParam('id');
        $values = (object) $button->getForm()->getValues();

        $this->context->datafeed->beginTransaction();

        if ($id > 0) {
            $current = $this->context->datafeed->getRow($id);
            $this->onBeforeUpdate(&$values);
        } else {
            $current = $this->context->datafeed->getEmptyValues();
            $this->onBeforeInsert(&$values);
        }
        $this->onBeforeSave(&$values);
        if ($this->getForm()->hasErrors())
            return;

        # fileapp processing
        foreach ($values as $key => $value) {
            if ($button->getForm()->offsetExists($key)) {
                $control = $button->getForm()->getComponent($key);
                if ($control instanceof \Nette\Forms\AppFile) {
                    $values[$key] = $this->handleFile($control, !isset($current->$key) ? null : $current->$key);
                }
            }
        }

        $result = $this->context->datafeed->save($values, $id);

        if ($id > 0) {
            $this->onAfterUpdate($result);
        } else {
            $this->onAfterInsert($result);
        }
        $this->onAfterSave($result);

        if ($this->getForm()->hasErrors()) {
            $this->context->datafeed->rollbackTransaction();
            return;
        }
        $this->context->datafeed->commitTransaction();

        $text = ($id > 0) ? _('Record was successfully updated.') : _('Record was succesfully added.');
        $this->getPresenter()->flashMessage($text);

        #gridBacklink handling
        $backlink = $this->getPresenter()->getParam('_bl');
        if ($backlink) {
            $this->getPresenter()->_bl = '';
            $this->getPresenter()->restoreBacklink($backlink);
        } else {
            $this->getPresenter()->redirect($this->gridAction);
        }
    }

}
