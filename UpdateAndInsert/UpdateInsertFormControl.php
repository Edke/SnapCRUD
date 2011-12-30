<?php

namespace SnapCRUD\UpdateAndInsert;

use Nette\Forms\Controls\SubmitButton;

/**
 * UpdateInsertFormControl
 *
 * @author       Eduard Kracmar <kracmar@dannax.sk>
 * @copyright    Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 */
class UpdateInsertFormControl extends BaseFormControl
{
    const STATE_EDIT = 'edit';
    const STATE_ADD = 'add';
    const STATE_UPDATE = 'update';
    const STATE_INSERT = 'insert';

    /** Events */
    public $onAdd, $onBeforeSave, $onBeforeUpdate, $onBeforeInsert, $onAfterUpdate, $onAfterInsert, $onAfterSave, $onRestore;
    /**
     * @var FileTransaction
     */
    protected $fileTransaction;

    /** @var integer */
    protected $id;

    public function __construct($id)
    {
        parent::__construct();
        $this->id = (integer) $id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @inheritdoc
     */
    public function createComponentForm()
    {
        $control = $this;
        $form = parent::createComponentForm();

        # default submit buttons
        $form->setCurrentGroup();

        $form->addSubmit('apply', _('Add'))
            ->onClick[] = function (SubmitButton $button) use ($control)
        {
            $id = $control->getId();
            $values = (object)$button->getForm()->getValues();

            $control->context->datafeed->beginTransaction();

            if ($id > 0) {
                $current = $control->context->datafeed->getRow($id);
                $control->onBeforeUpdate(&$values);
            } else {
                $current = $control->context->datafeed->getEmptyValues();
                $control->onBeforeInsert(&$values);
            }
            $control->onBeforeSave(&$values);
            if ($control->getForm()->hasErrors())
                return;

            # fileapp processing
            foreach ($values as $key => $value) {
                if ($button->getForm()->offsetExists($key)) {
                    $formControl = $button->getForm()->getComponent($key);
                    if ($formControl instanceof \Nette\Forms\AppFile) {
                        $values[$key] = $control->handleFile($formControl, !isset($current->$key) ? null : $current->$key);
                    }
                }
            }

            $result = $control->context->datafeed->save($values, $id);

            if ($id > 0) {
                $control->onAfterUpdate($result);
            } else {
                $control->onAfterInsert($result);
            }
            $control->onAfterSave($result);

            if ($control->getForm()->hasErrors()) {
                $control->context->datafeed->rollbackTransaction();
                return;
            }
            $control->context->datafeed->commitTransaction();

            $text = ($id > 0) ? _('Record was successfully updated.') : _('Record was succesfully added.');
            $control->getPresenter()->flashMessage($text);

            #gridBacklink handling
            $backlink = $control->getPresenter()->getParam('_bl');
            if ($backlink) {
                $control->getPresenter()->_bl = '';
                $control->getPresenter()->restoreBacklink($backlink);
            } else {
                $control->getPresenter()->redirect($control->getDestinationOnSuccess());
            }
        };

        $form->addSubmit('cancel', 'Cancel')
            ->setValidationScope(FALSE)
            ->onClick[] = function (SubmitButton $button) use ($control)
        {
            /** @var \SnapCRUD\UpdateAndInsert\BaseFormControl $control */

            # TODO checking if form was changed
            $id = $control->getId();

            $text = ($id > 0) ? _('Adding new record was canceled.') : _('Updating record was canceled, no record was modified.');
            $control->getPresenter()->flashMessage($text, 'warning');

            #gridBacklink handling
            $backlink = $control->getPresenter()->getParam('_bl');
            if ($backlink) {
                $control->getPresenter()->_bl = '';
                $control->getPresenter()->restoreBacklink($backlink);
            } else {
                $control->getPresenter()->redirect($control->getDestinationOnSuccess());
            }
        };
        return $form;
    }


    public function attached($control)
    {
        parent::attached($control);

        $presenter = $control->getPresenter();

        /** DISABLED AS OBSOLETE
        if ($presenter->getParam('restore')) {
        $defaults = $presenter->getNamespace()->saved_form;
        $this->onRestore(&$defaults);
        $this->getForm()->setDefaults($defaults);
        } elseif ($presenter->getParam('return')) {
        $defaults = $presenter->getNamespace()->saved_form;
        $this->getForm()->setDefaults($defaults);
        } */

        # state
        if (!$this->getForm()->isSubmitted() and $this->id == 0) {
            $this->state = UpdateInsertFormControl::STATE_ADD;
        } elseif (!$this->getForm()->isSubmitted() and $this->id != 0) {
            $this->state = UpdateInsertFormControl::STATE_EDIT;
        } elseif ($this->getForm()->isSubmitted() and $this->id == 0) {
            $this->state = UpdateInsertFormControl::STATE_INSERT;
        } elseif ($this->getForm()->isSubmitted() and $this->id != 0) {
            $this->state = UpdateInsertFormControl::STATE_UPDATE;
        } else {
            throw new \Exception('Unable to determine state');
        }

        # defaults
        switch ($this->state) {
            case UpdateInsertFormControl::STATE_EDIT:
            case UpdateInsertFormControl::STATE_UPDATE:
                # TODO configurable key
                $defaults = $this->context->datafeed->getFormValues($this->id);
                $this->onEdit(&$defaults);
                $this->getForm()->setDefaults((array)$defaults);
                break;

            case UpdateInsertFormControl::STATE_ADD:
            case UpdateInsertFormControl::STATE_INSERT:
                $defaults = $this->context->datafeed->getEmptyValues();
                $this->onAdd(&$defaults);
                $this->getForm()->setDefaults((array)$defaults);
                break;
        }

        # title and submit caption while update/edit
        if ($this->id > 0) {
            $this->setTitle(\tc("Editing record '%s'", $this->context->datafeed->getItemName($this->id)));
            # change caption if update
            $this->getForm()->getComponent('apply')->caption = _('Update');

        }
        # title while add/insert
        else {
            $this->setTitle(_('New record'));
        }

        # refresh DependentSelects
        foreach ($this->getForm()->getControls() as $control)
        {
            if ($control instanceof \DependentSelectBox\DependentMultiSelectBox
                or $control instanceof \DependentSelectBox\DependentSelectBox
            ) {
                $control->refresh();
            }
        }
    }

    /**
     * Getter for file transaction
     * @return FileTransaction
     */
    public function getFileTransaction()
    {
        if (!$this->fileTransaction) {
            $this->fileTransaction = new FileTransaction();
        }
        return $this->fileTransaction;
    }

}
