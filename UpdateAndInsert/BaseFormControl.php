<?php

namespace SnapCRUD\UpdateAndInsert;

use DannaxTools\File;

/**
 * BaseFormControl
 *
 * @author       Eduard Kracmar <kracmar@dannax.sk>
 * @copyright    Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 * @abstract
 */
abstract class BaseFormControl extends \SnapCRUD\BaseControl
{

    /**
     * Saves state of control (add, edit, update)
     * @var string
     */
    protected $state;
    /**
     * default destination link on success
     * @var string
     */
    private $destinationOnSuccess = 'default';
    /**
     * default destination link on cancel
     * @var string
     */
    private $destinationOnCancel = 'default';

    /**
     * Events
     */
    public $onEdit;

    public function __construct()
    {
        parent::__construct();

        $this->unmonitor('Nette\Application\UI\Presenter');
        $this->monitor('Nette\Application\UI\Control');
    }

    /**
     * Gets template filename
     * @return string
     */
    protected function getTemplateFilename()
    {
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
    public function setTemplateFile($filename)
    {
        $this->template->setFile($filename);
        return $this;
    }

    public function render()
    {
        $template = $this->template;
        $this->template->setFile($this->getTemplateFilename());
        echo $this->template->render();
    }

    /**
     * @return \Nette\Application\UI\Form
     */
    public function getForm()
    {
        return $this['form'];
    }

    /**
     * @return \Nette\Application\UI\Form
     */
    public function createComponentForm()
    {
        $form = new \Nette\Application\UI\Form();
        $form->getElementPrototype()->class('gridform');
        return $form;
    }

    /**
     * @param string $destinationOnSuccess
     * @return this
     */
    public function setDestinationOnSuccess($destinationOnSuccess)
    {
        $this->destinationOnSuccess = $destinationOnSuccess;
        return $this;
    }

    /**
     * @return string
     */
    public function getDestinationOnSuccess()
    {
        return $this->destinationOnSuccess;
    }

    /**
     * @param string $destinationOnCancel
     * @return this
     */
    public function setDestinationOnCancel($destinationOnCancel)
    {
        $this->destinationOnCancel = $destinationOnCancel;
        return $this;
    }

    /**
     * @return string
     */
    public function getDestinationOnCancel()
    {
        return $this->destinationOnCancel;
    }

    /**
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param $state
     * @return this
     */
    public function setState($state)
    {
        $this->state = $state;
        return $this;
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

    /**
     * @param string $title
     * @return this
     */
    public function setTitle($title)
    {
        $this->template->title = $title;
        return $this;
    }

    /**
     * @param $control
     * @param $current
     * @return null|string
     * @throws \InvalidArgumentException|\LogicException
     */
    protected function handleFile($control, $current)
    {
        if ($control->getDestPath() == '') {
            throw new \InvalidArgumentException("Path is not defined for (" . $control->getName() . ").");
        }

        $base = $control->getBase() ? $control->getBase() : realpath($this->context->params['wwwDir']) . DIRECTORY_SEPARATOR;
        $full = $base . $control->getDestPath();

        $file = $control->getValue();

        # delete current
        if ($file instanceof \Nette\Web\UploadedFile && $file->getUnlink() && $current) {
            \unlink($base . $current);
            return null;
        }
        # unchanged
        elseif ($file instanceof \Nette\Web\UploadedFile && $file->getUnlink() === false && $current) {
            return $file->name;
        }
        # uploaded new file, replace or add
        elseif (get_class($file) == 'Nette\Http\FileUpload' && $file->size > 0) {
            // replace, unlink current
            if ($current) {
                \unlink($base . $current);
            }

            // add new
            $dest = File::findSafeDestination($full . DIRECTORY_SEPARATOR . $file->name);
            $file->move($dest);

            if (\Nette\Utils\Strings::startsWith($dest, $base)) {
                return substr($dest, strlen($base));
            } else {
                throw new \InvalidArgumentException('Destination lookup failed.');
            }
        }
        # null
        elseif (get_class($file) == 'Nette\Http\FileUpload' && $file->getError() == \UPLOAD_ERR_NO_FILE) {
            return null;
        }
        # unknown case
        else {
            throw new \LogicException('invalid case');
        }
    }




}
