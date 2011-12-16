<?php

namespace SnapCRUD;

use Nette\Reflection\ClassType,
Nette\DI;

/**
 * BaseControl
 * @author       Eduard Kracmar <kracmar@dannax.sk>
 * @copyright    Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 * @abstract
 */
abstract class BaseControl extends \Nette\Application\UI\Control
{

    /**
     * @var string
     */
    protected $templateFilename;
    /**
     * @var DI\Container
     */
    protected $context;
    protected $ident;

    protected function attached($presenter)
    {
        parent::attached($presenter);
        $this->setContext($presenter->getContext());
    }

    /**
     * Sets context
     * @param DI\Container $context
     * @return this
     */
    public function setContext(DI\Container $context)
    {
        $this->context = new DI\Container();
        $this->context->params['productionMode'] = $context->params['productionMode'];

        $this->ident = \preg_replace('#[\\\/:]+#', '_', ClassType::from($this)->getNamespaceName() . '_' .
            $this->getPresenter()->getName() . '_' .
            $this->getPresenter()->getAction() . '_' .
            $this->getName());

        # lazy cache
        $control = $this;
        $this->context->addService('cache', function() use ($control, $context)
        {
            return $control->createServiceCache($context);
        });

        # lazy session
        $this->context->addService('sessionSection', function() use ($control, $context)
        {
            return $control->createServiceSessionSection($context);
        });

        # lazy cacheStorage
        $this->context->addService('cacheStorage', function() use ($context)
        {
            return $context->cacheStorage;
        });

        # lazy translator
        if ($context->hasService('translator')) {
            $this->context->addService('translator', function() use ($context)
            {
                return $context->translator;
            });
        }

        # lazy texy
        if ($context->hasService('texy')) {
            $this->context->addService('texy', function() use ($context)
            {
                return $context->texy;
            });
        }

        # lazy latteEngine
        if ($context->hasService('latteEngine')) {
            $this->context->addService('latteEngine', function() use ($context)
            {
                return $context->latteEngine;
            });
        }

        # lazy doctrine
        if ($context->hasService('doctrine')) {
            $this->context->addService('doctrine', function() use ($context)
            {
                return $context->doctrine;
            });
        }
        # lazy Nette\Database
        if ($context->hasService('database')) {
            $this->context->addService('database', function() use ($context)
            {
                return $context->database;
            });
        }

        # lazy datafeed
        if ($context->hasService('datafeed')) {
            $this->context->addService('datafeed', function() use ($context)
            {
                return $context->datafeed;
            });
        }

        $this->context->params['wwwDir'] = $context->params['wwwDir'];

        return $this;
    }

    /**
     * Gets context
     * @return DI\Container
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Factory for cache (in case of reconfiguring and easy overloading)
     * @param DI\Container $context
     */
    public function createServiceCache(DI\Container $context)
    {
        return new \Nette\Caching\Cache($context->cacheStorage, $this->ident);
    }

    /**
     * Factory for session section (in case of reconfiguring and easy overloading)
     * @param DI\Container $context
     */
    public function createServiceSessionSection(DI\Container $context)
    {
        return $context->session->getSection($this->ident);
    }

    protected function getAntecessorsFilenames()
    {
        $pointer = ClassType::from($this);
        $result = array();
        do {
            array_push($result, $pointer->getFileName());
            $pointer = $pointer->getParentClass();
        } while ($pointer !== null);

        return $result;
    }

    /**
     * Gets template filename
     * @return string
     */
    protected function getTemplateFilename()
    {
        if (!$this->templateFilename) {
            $this->templateFilename = dirname(ClassType::from($this)->getFileName()) . '/' . ClassType::from($this)->getShortName() . '.latte';
        }
        return $this->templateFilename;
    }

    /**
     * Sets template file
     * @var string $templateFilename
     * @return this
     */
    public function setTemplateFilename($templateFilename)
    {
        $this->templateFilename = $templateFilename;
        return $this;
    }

    /**
     * Build cache key
     */
    protected function buildKey()
    {
        $args = \func_get_args();
        $parts = array();
        foreach ($args as $arg) {
            if (is_array($arg)) {
                $parts[] = implode('|', $arg);
            } else {
                $parts[] = $arg;
            }
        }
        return md5($this->getPresenter()->getContext()->params['application']['md5Salt'] . implode('|', $parts));
    }

    public function createTemplate($class = null)
    {
        $template = parent::createTemplate($class);

        $template->setTranslator($this->context->translator);

        # helpers
        $template->registerHelper('texy', array($this->context->texy, 'process'));
        return $template;
    }

    /**
     * @inheritdoc
     * @param  \Nette\Templating\Template
     * @return void
     */
    public function templatePrepareFilters($template)
    {
        $template->registerFilter($this->context->latteEngine);
    }

}
