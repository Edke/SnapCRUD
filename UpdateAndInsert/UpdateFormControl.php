<?php

namespace SnapCRUD\UpdateAndInsert;

use Nette\Forms\Controls\SubmitButton;

/**
 * UpdateFormControl
 *
 * @author       Eduard Kracmar <kracmar@dannax.sk>
 * @copyright    Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 */
class UpdateFormControl extends BaseFormControl
{
    const STATE_EDIT = 'edit';
    const STATE_UPDATE = 'update';

    /**
     * Events
     */
    public $onSave;

    public function createComponentForm()
    {
        $control = $this;
        $form = parent::createComponentForm();

        # default submit buttons
        $form->setCurrentGroup();

        $form->addSubmit('apply', _('Update'))
            ->onClick[] = function (SubmitButton $button) use ($control)
        {
            $values = (object)$button->getForm()->getValues();

            $control->context->datafeed->beginTransaction();

            $control->onSave(&$values);
            if ($control->getForm()->hasErrors()) {
                $control->context->datafeed->rollbackTransaction();
                return;
            }
            $control->context->datafeed->commitTransaction();

            $control->getPresenter()->flashMessage('Record was successfully updated.', 'ok');

            #gridBacklink handling
            $backlink = $control->getPresenter()->getParam('_bl');
            if ($backlink) {
                $control->getPresenter()->_bl = '';
                $control->getPresenter()->restoreBacklink($backlink);
            } else {
                $control->getPresenter()->redirect($control->getDestinationOnSuccess());
            }
        };

        $form->addSubmit('cancel', _('Cancel'))
            ->setValidationScope(FALSE)
            ->onClick[] = function (SubmitButton $button) use ($control)
        {
            $control->getPresenter()->flashMessage('Record updating was canceled, record was not modified.', 'warning');

            #gridBacklink handling
            $backlink = $control->getPresenter()->getParam('_bl');
            if ($backlink) {
                $control->getPresenter()->_bl = '';
                $control->getPresenter()->restoreBacklink($backlink);
            } else {
                $control->getPresenter()->redirect($control->getDestinationOnCancel());
            }
        };

        return $form;
    }

    public function attached($control)
    {
        parent::attached($control);

        # state
        if (!$this->getForm()->isSubmitted()) {
            $this->state = UpdateFormControl::STATE_EDIT;
        } elseif ($this->getForm()->isSubmitted()) {
            $this->state = UpdateFormControl::STATE_UPDATE;
        } else {
            throw new Exception('Unable to determine state');
        }

        # defaults
        if ($this->state == UpdateFormControl::STATE_EDIT) {
            $defaults = new \Nette\ArrayHash();
            $this->onEdit(&$defaults);
            $this->getForm()->setDefaults((array)$defaults);
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

}
