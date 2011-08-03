<?php

namespace SnapCRUD\Select\DataGrid\Controls;

use Nette\Utils\Html;

/**
 * RowCell element for Row of DataGridControl
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 * @package    DataGrid2
 */
class RowCell extends \Nette\Object {

    /**
     * Core attributes
     */
    protected $parent;
    /**
     * Properties
     */
    protected $classes = array(), $colspan = 1, $content = '&nbsp;', $contentCb, $linkCb, $onClickCb;

    /**
     * Constructor
     * @param Row $parent
     */
    public function __construct(Row $parent) {
        $this->parent = $parent;
    }

    /**
     * Get Parent
     * @return DataGridControl
     */
    protected function getParent() {
        return $this->parent;
    }

    /**
     * Add new cell in chain, keeps fluent interface
     * @return RowCell
     */
    public function addCell() {
        return $this->getParent()->addCell();
    }

    /**
     * Get cells html
     * @return Html
     */
    public function getHtml() {
        $el = Html::el('td')
                ->colspan($this->colspan > 1 ? $this->colspan : null);

        foreach ($this->classes as $class) {
            $el->class($class, true);
        }

        if ($this->onClickCb) {
            $el->onClick("window.location='" . \call_user_func($this->onClickCb) . "'")
                    ->class('clickable', true);
        }

        if ($this->contentCb) {
            $content = \call_user_func($this->contentCb);
        } else {
            $content = $this->content;
        }

        if ($this->linkCb) {
            $el->add(Html::el('a')->href(\call_user_func($this->linkCb))->setHtml($content));
        } else {
            $el->setHtml($content);
        }
        return $el;
    }

    public function getColspan() {
        return $this->colspan;
    }

    /**
     * Determinees
     */
    /**
     * Configurators
     */

    /**
     * Add body class
     * @param string $class
     * @return this
     */
    public function addClass($class) {
        $this->classes[] = $class;
        return $this;
    }

    /**
     * Set callback for content
     * @param callback $callback
     * @return this
     */
    public function setContentCb($callback) {
        $this->contentCb = $callback;
        return $this;
    }

    /**
     * Set content
     * @param string $content
     * @return this
     */
    public function setContent($content) {
        $this->content = $content;
        return $this;
    }

    /**
     * Set callback to create link (a href)
     * @param callback $callback
     * @return this
     */
    public function setLinkCb($callback) {
        $this->linkCb = $callback;
        return $this;
    }

    /**
     * Set callback to create onClick link
     * @param callback $callback
     * @return this
     */
    public function setOnClickCb($callback) {
        $this->onClickCb = $callback;
        return $this;
    }

    /**
     * Set colspan for cell
     * @param integer $colspan
     * @return RowCell
     */
    public function setColspan($colspan) {
        $this->colspan = (integer) $colspan;
        return $this;
    }

}
