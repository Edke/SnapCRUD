<?php

namespace SnapCRUD\Delete;

use Nette\Utils\Html,
Nette\Forms\Controls\SubmitButton;

/**
 * DeleteControl
 *
 * @author       Eduard Kracmar <kracmar@dannax.sk>
 * @copyright    Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 */
class DeleteControl extends \SnapCRUD\BaseControl
{

    /**
     * Events
     * @var array
     */
    public $onBefore, $onAfter;

    /** @var string */
    private $destinationReturn = 'default';

    /**
     * @var array
     */
    protected $rows;
    protected $unlinkQueue = array();


    /**
     * @return \Nette\Application\UI\Form
     */
    public function createComponentForm()
    {
        $control = $this;

        $text = \nt("Deleting of record", count($this->rows));
        $this->template->title = $text;

        $form = new \Nette\Application\UI\Form($this, 'form');
        $form->setTranslator($this->context->translator);

        $form->addHidden('key');
        $key = $this->getPresenter()->getParam('sl');
        if ($key == null) {
            $key = $form['key']->getValue();
        } else {
            $form['key']->setValue($key);
        }
        $this->rows = $this->getPresenter()->restoreValues($key);
        if ($this->rows == null) {
            $this->getPresenter()->flashMessage('Unable to restore delete rows.', 'warning');
            $this->getPresenter()->redirect('default');
        }

        $text = \nt("Selected record", count($this->rows));
        $form->addGroup($text);
        $container = $form->addContainer('rows');
        $defaults = array();
        foreach ($this->rows as $value) {
            $container->addCheckbox((string)$value, $this->context->datafeed->getItemName($value));
            $defaults['rows'][$value] = true;
        }
        $form->setCurrentGroup();

        $form->addSubmit('delete', 'Confirm deletion')
            ->onClick[] = function (SubmitButton $button) use ($control)
        {
            /** @var \SnapCRUD\Delete\DeleteControl $control */

            $values = (object)$button->getForm()->getComponent('rows')->getValues();

            foreach ($control->onBefore as $callable) {
                $callable($values);
            }

            $control->context->datafeed->beginTransaction();
            $result = $control->context->datafeed->deleteRows($values);
            if (\is_object($result)) {
                $control->context->datafeed->rollbackTransaction();
                $message = tc("Error occured during deleting records from db: %s", $result->getMessage());
                $control->getPresenter()->flashMessage($message);
                $control->getPresenter()->redirect($control->getDestinationReturn());
            }
            foreach ($control->onAfter as $callable) {
                $callable($values);
            }
            $control->context->datafeed->commitTransaction();

            if ($result > 0) {
                $message = \nt("Selected record was successfully deleted.", $result);
                $control->getPresenter()->flashMessage($message, 'error');
            }
            $control->getPresenter()->destroyValues($control->getForm()->getComponent('key')->getValue());

            # TODO backlink handling
            $control->getPresenter()->redirect($control->getDestinationReturn());
        };

        $form->addSubmit('cancel', 'Cancel')
            ->onClick[] = function (SubmitButton $button) use ($control)
        {
            /** @var \SnapCRUD\Delete\DeleteControl $control */

            $control->getPresenter()->destroyValues($control->getForm()->getComponent('key')->getValue());
            $control->getPresenter()->flashMessage('Deleting of records has been canceled.', 'warning');

            # TODO backlink handling
            $control->getPresenter()->redirect($control->getDestinationReturn());
        };

        $form->setDefaults($defaults);

        return $form;
    }

    /**
     * Gets selected rows to be deleted
     * @return array
     */
    public function getSelectedRows()
    {
        $result = array();
        foreach ($this->getForm()->getComponent('rows')->getControls() as $control) {
            if ($control->getValue() == true) {
                $result[] = $control->getName();
            }
        }
        return $result;
    }

    /**
     * @return \Nette\Application\UI\Form
     */
    public function getForm()
    {
        return $this['form'];
    }

    /**
     */
    public function render()
    {
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

        $template = $this->template;
        $template->setFile($this->getTemplateFilename());
        $template->records = count($this->rows);

        //$this->template->form = $this->getForm();
        //$this->template->formControl = $this;

        echo $template->render();
    }

    /**
     * Add file to unlink queue
     * @param mixed $file
     */
    public function addFileToUnlink($files)
    {
        if (is_string($files)) {
            $files = array($files);
        }

        foreach ($files as $file) {
            # relative
            if (preg_match('#^[^/]#', $file)) {
                $file = $this->context->parameters['wwwDir'] . '/' . $file;
            }
            $this->unlinkQueue[] = $file;
        }
    }

    /**
     * Unlink queue
     * @return void
     */
    public function unlinkQueue()
    {
        foreach ($this->unlinkQueue as $file) {
            $res = unlink($file);
            if ($res === false) {
                error_log(date('[Y-m-d H:i:s] ') . "Unable to delete file ($file).\n", 3, Nette\Diagnostics\Debugger::$logDirectory . 'backend.log');
            }
        }
    }

    /**
     * @param $destinationReturn
     * @return this
     */
    public function setDestinationReturn($destinationReturn)
    {

        $this->destinationReturn = $destinationReturn;
        return $this;
    }

    /**
     * @return string
     */
    public function getDestinationReturn()
    {
        return $this->destinationReturn;
    }

}
