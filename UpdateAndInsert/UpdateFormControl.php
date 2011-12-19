<?php

namespace SnapCRUD\UpdateAndInsert;

/**
 * UpdateFormControl
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 */
class UpdateFormControl extends BaseFormControl {
    const STATE_EDIT = 'edit';
    const STATE_UPDATE = 'update';


    /**
     * Events
     */
    public $onSave;

    /**
     * Konstruktor
     * @param IComponentContainer $parent
     * @param string $name
     */
    public function __construct(\Nette\ComponentModel\IContainer $parent, $name) {
        parent::__construct($parent, $name);

        # state
        if (!$this->getForm()->isSubmitted()) {
            $this->state = UpdateFormControl::STATE_EDIT;
        } elseif ($this->getForm()->isSubmitted()) {
            $this->state = UpdateFormControl::STATE_UPDATE;
        } else {
            throw new Exception('Unable to determine state');
        }
    }

    public function createComponentForm() {
        $form = parent::createComponentForm();

        $form->setCurrentGroup();

        $form->addSubmit('apply', _('Update'))
                ->onClick[] = array($this, 'form_onApply');

        $form->addSubmit('cancel', _('Cancel'))
                        ->setValidationScope(FALSE)
                ->onClick[] = array($this, 'form_onCancel');
        return $form;
    }

    public function setDefaultValues() {
        if ($this->state == UpdateFormControl::STATE_EDIT) {
            $defaults = new \Nette\ArrayHash();
            $this->onEdit(&$defaults);
            $this->getForm()->setDefaults((array) $defaults);
        }
    }

    /**
     * Handler onCancel pre view Form
     *
     * Pri zruseni pridavania/upravovania formulara sa vracia na view Default a naplni message
     *
     * @todo overenie zmeneneho formulara
     * @param SubmitButton $button
     */
    public function form_onCancel(\Nette\Forms\Controls\SubmitButton $button) {
        $this->getPresenter()->flashMessage('Record updating was canceled, record was not modified.', 'warning');

        #gridBacklink handling
        $backlink = $this->getPresenter()->getParam('_bl');
        if ($backlink) {
            $this->getPresenter()->_bl = '';
            $this->getPresenter()->restoreBacklink($backlink);
        } else {
            $this->getPresenter()->redirect($this->gridAction);
        }
    }

    public function form_onApply(\Nette\Forms\Controls\SubmitButton $button) {
        $values = (object) $button->getForm()->getValues();

        $this->context->datafeed->beginTransaction();

        $this->onSave(&$values);
        if ($this->getForm()->hasErrors()) {
            $this->context->datafeed->rollbackTransaction();
            return;
        }
        $this->context->datafeed->commitTransaction();

        $this->getPresenter()->flashMessage('Record was successfully updated.', 'ok');

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
