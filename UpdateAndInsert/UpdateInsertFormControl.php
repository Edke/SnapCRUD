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

    /** @var int */
    private $id;

    /**
     * Events
     */
    public $onAdd, $onBeforeSave, $onBeforeUpdate, $onBeforeInsert, $onAfterUpdate, $onAfterInsert, $onAfterSave, $onRestore;
    /**
     * @var FileTransaction
     */
    protected $fileTransaction;

    /**
     * @param int $id
     * @return UpdateInsertFormControl
     */
    public function setId($id)
    {
        $this->id = (integer) $id;

        # state
        if (!$this->getForm()->isSubmitted() and $this->id == 0) {
            $this->state = UpdateInsertFormControl::STATE_ADD;
        } elseif (!$this->getForm()->isSubmitted() and $id != 0) {
            $this->state = UpdateInsertFormControl::STATE_EDIT;
        } elseif ($this->getForm()->isSubmitted() and $this->id == 0) {
            $this->state = UpdateInsertFormControl::STATE_INSERT;
        } elseif ($this->getForm()->isSubmitted() and $this->id != 0) {
            $this->state = UpdateInsertFormControl::STATE_UPDATE;
        } else {
            throw new \Exception('Unable to determine state');
        }

        # title
        if ($this->id > 0) {
            $this->setTitle(\tc("Editing record '%s'", $this->context->datafeed->getItemName($id)));

        } else {
            $this->setTitle(_('New record'));
        }
        return $this;
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

        $applyText = $this->id > 0 ? _('Update') : _('Add');
        $form->addSubmit('apply', $applyText)
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

    public function setDefaultValues()
    {
        if ($this->getPresenter()->getParam('restore')) {
            $defaults = $this->getPresenter()->getNamespace()->saved_form;
            $this->onRestore(&$defaults);
            $this->getForm()->setDefaults($defaults);
        } elseif ($this->getPresenter()->getParam('return')) {
            $defaults = $this->getPresenter()->getNamespace()->saved_form;
            $this->getForm()->setDefaults($defaults);
        } elseif ($this->state == UpdateInsertFormControl::STATE_EDIT) {
            # TODO configurable key
            $defaults = $this->context->datafeed->getFormValues($this->id);
            $this->onEdit(&$defaults);
            $this->getForm()->setDefaults((array)$defaults);
        } elseif ($this->state == UpdateInsertFormControl::STATE_ADD) {
            $defaults = $this->context->datafeed->getEmptyValues();
            $this->onAdd(&$defaults);
            $this->getForm()->setDefaults((array)$defaults);
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
