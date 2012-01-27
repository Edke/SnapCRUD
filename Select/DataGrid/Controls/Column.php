<?php

namespace SnapCRUD\Select\DataGrid\Controls;

use Nette\Utils\Html;

/**
 * Column element for DataGridControl
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 * @package    DataGrid2
 */
class Column extends \Nette\Object {

    /**
     * Core attributes
     */
    protected $parent, $name, $alias, $conditions = array();
    /**
     * Properties
     */
    protected $width;
    protected $sortable = true, $headerClasses = array(), $visibility = true;
    public $label;
    public $bodyClasses = array(), $bodyLinkCb, $bodyLinkMask, $bodyLinkModalCb, $bodyOnClickCb, $bodyContentCb, $bodyHtml, $bodyContentHelper, $bodyContent, $bodyTitleCb;
    public $footerClasses = array(), $footerContentCb, $footerContent, $footerContentHelper;
    /**
     * Aggregate functionality
     */
    public $footerAggregate;
    /**
     * Casting
     */
    protected $cast, $castAlias;

    /**
     * Constructor
     * @param DataGridControl $parent
     * @param string $name
     * @param string $label
     */
    public function __construct(\SnapCRUD\Select\DataGrid\DataGridControl $parent, $name, $label= null) {

        #alias handling
        if (preg_match('#^([^.]+)\.(.+)$#', $name, $matches)) {
            $this->alias = $matches[1];
            $this->name = $matches[2];
        } else {
            $this->name = $name;
        }
        $this->parent = $parent;
        $this->label = $label;
    }

    /**
     * Get name
     * @param string $mode (sql|definition|safe)
     * @return string
     */
    public function getName($mode = null) {

        switch ($mode) {
            case 'sql':
            case 'definition':
                return $this->alias ? $this->alias . '.' . $this->name : $this->name;

            case 'safe':
                return $this->alias ? $this->alias . '__' . $this->name : $this->name;

            default:
                return $this->name;
        }
    }

    /**
     * Get Parent
     * @return DataGridControl
     */
    protected function getParent() {
        return $this->parent;
    }

    /**
     * Get column conditions
     * @return array
     */
    public function getConditions() {
        return $this->conditions;
    }

    /**
     * Get col for colgroup to define column's width
     * @return Html
     */
    public function getCol() {
        return Html::el('col')
                ->width($this->width);
    }

    /**
     * Get class name for current column order state
     * @return string|null
     */
    public function getHeaderClass() {
        $classes = $this->headerClasses;
        /* $this->headerClasses[] = '<?php '.$self.'->getSortOrderClass();  ?>'; */


        if (isset($this->getParent()->getContext()->sessionSection->orderBy) &&
                isset($this->getParent()->getContext()->sessionSection->orderBy->{$this->getName('sql')})) {
            $currentDirection = $this->getParent()->getContext()->sessionSection->orderBy->{$this->getName('sql')};


            $classes[] = $this->getParent()->getSortOrderClass($currentDirection);
        }
        return implode(' ', $classes);
    }

    public function getFooterClass() {
        return $this->footerClasses ? ' class="' . implode(' ', $this->footerClasses) . '"' : '';
    }

    /**
     * Get footer content
     * @return mixed
     */
    public function getFooterContent() {
        return $this->footerContent;
    }

    /**
     * Get body cell html based on row data
     * @param mixed $data
     * @return Html
     */
    public function getBody($data) {
        if (!$this->bodyHtml) {
            $this->bodyHtml = Html::el('td');

            foreach ($this->bodyClasses as $class) {
                $this->bodyHtml->class($class, true);
            }
        }

        $el = clone $this->bodyHtml;

        # on click
        if ($this->bodyOnClickCb && ($link = \call_user_func($this->bodyOnClickCb, $data))) {
            $el->onClick("window.location='" . $link . "'")
                    ->class('clickable', true);
        }

        # content
        if ($this->bodyContent) {
            $content = $this->bodyContent;
        } elseif ($this->bodyContentCb) {
            $content = \call_user_func($this->bodyContentCb, $data, $this);
        } else {
            //$getter = 'get' . \ucfirst($this->getName());
            $content = $this->getParent()->getContext()->datafeed->getColumnFromRow($data, $this->getName());
        }

        # helper
        if ($this->bodyContentHelper) {
            if (is_string($this->bodyContentHelper) && $this->getParent()->hasHelper($this->bodyContentHelper)) {
                $content = \call_user_func($this->getParent()->getHelper($this->bodyContentHelper), $content);
            } elseif (\is_callable($this->bodyContentHelper)) {
                $content = \call_user_func($this->bodyContentHelper, $content);
            } else {
                throw new \Exception("Invalid/unregistred content helper '" . $this->bodyContentHelper . "'");
            }
        }

        # title
        if ($this->bodyTitleCb) {
            $el->title(\call_user_func($this->bodyTitleCb, $data));
        }

        # adding to element
        if ($this->bodyLinkModalCb) {
            $el->add(Html::el('a')->href(\call_user_func($this->bodyLinkModalCb, $data))->data('modal', 1)->setHtml($content));
        } elseif ($this->bodyLinkCb) {
            $el->add(Html::el('a')->href(\call_user_func($this->bodyLinkCb, $data))->setHtml($content));
        } else {
            $el->setHtml($content);
        }
        return $el;
    }

    public function getBodyTemplate() {
        $columnName = $this->hasCast() ? $this->castAlias : $this->getName('safe');
        $self = '$control->getColumn("' . $this->getName('safe') . '")';
        $el = Html::el('td');

        foreach ($this->bodyClasses as $class) {
            $el->class($class, true);
        }

        # on click
        if ($this->bodyOnClickCb) {
            $el->onClick("window.location='<?= \call_user_func(" . $self . ", \$row) ?>'")
                    ->class('clickable', true);
        }

        # content
        $contentInner = $this->bodyContentCb ?
            $self . '->getBodyContentCb($row)':
            '$row->' . $columnName;
        $content = $this->bodyContentHelper ?
            '<?= '.$this->getParent()->getHelperClass().'::'.$this->bodyContentHelper .'('.$contentInner.') ?>' :
            '<?= '.$contentInner. ' ?>';

        \Nette\Diagnostics\Debugger::barDump($content);


        # anchor envelope
        if ($this->bodyLinkCb) {
            $el->add('<a href="<?= ' . $self . '->getBodyLinkCb($row) ?>">' . $content . '</a>');
        } else {
            $el->add($content);
        }

        return (string) $el;
    }

    public function getBodyLinkCb($data) {
        if ($this->bodyLinkMask) {
            return sprintf($this->bodyLinkMask, \call_user_func($this->bodyLinkCb, $data, $this));
        } else {
            return \call_user_func($this->bodyLinkCb, $data, $this);
        }
    }

    public function setBodyLinkMask($mask, $needle) {
        $this->bodyLinkMask = str_replace($needle, '%s', $mask);
        return $this;
    }

    public function getBodyContentCb($data) {
        return \call_user_func($this->bodyContentCb, $data, $this);
    }

    public function getBodyContent($data) {
        # content
        if ($this->bodyContent) {
            $content = $this->bodyContent;
        } elseif ($this->bodyContentCb) {
            $content = \call_user_func($this->bodyContentCb, $data, $this);
        } else {
            //$getter = 'get' . \ucfirst($this->getName());
            $content = $this->getParent()->getContext()->datafeed->getColumnFromRow($data, $this->getName());
        }

        # helper
        if ($this->bodyContentHelper) {
            if (is_string($this->bodyContentHelper) && $this->getParent()->hasHelper($this->bodyContentHelper)) {
                $content = \call_user_func($this->getParent()->getHelper($this->bodyContentHelper), $content);
            } elseif (\is_callable($this->bodyContentHelper)) {
                $content = \call_user_func($this->bodyContentHelper, $content);
            } else {
                throw new \Exception("Invalid/unregistred content helper '" . $this->bodyContentHelper . "'");
            }
        }


        return $content;
    }

    /**
     * Determinees
     */

    /**
     * Determines whether column has defined footer
     * @return boolean
     */
    public function hasFooter() {
        return $this->footerContent || $this->footerContentCb || $this->footerAggregate;
    }

    /**
     * Determines whether column is visible
     * @return boolean
     */
    public function isVisible() {
        return $this->visibility;
    }

    /**
     * Determines whether column is sortable
     * @return boolean
     */
    public function isSortable() {
        return true && $this->sortable;
    }

    /**
     * Property setters
     */

    /**
     * Add condition attached to column and it's name
     * @example Doctrine: '%n = ?'
     * @example DibiDataSource: '%n = %b'
     * @return this
     */
    public function addCondition($query) {
        $this->conditions[] = new \SnapCRUD\Select\Controls\Condition($this, $query);
        return $this;
    }

    /**
     * Add search control to search attached to column
     * @param \Nette\ComponentModel\IComponent $control
     * @return Column
     */
    public function addSearch(\Nette\ComponentModel\IComponent $control) {
        $name = $this->getName('safe');
        $this->getParent()->addSearch($name, $control);
        return $this;
    }

    /**
     * Set column as unsortable
     * @return this
     */
    public function unsortable() {
        $this->sortable = false;
        return $this;
    }

    /**
     * Add body class
     * @param string $class
     * @return this
     */
    public function addBodyClass($class) {
        $this->bodyClasses[] = $class;
        return $this;
    }

    /**
     * Add header class
     * @param string $class
     * @return this
     */
    public function addHeaderClass($class) {
        $this->headerClasses[] = $class;
        return $this;
    }

    /**
     * Add footer class
     * @return this
     */
    public function addFooterClass($class) {
        $this->footerClasses[] = $class;
        return $this;
    }

    /**
     * Add body, header and footer class
     * @return this
     */
    public function addClass($class) {
        $this->addHeaderClass($class)
                ->addBodyClass($class)
                ->addFooterClass($class);
        return $this;
    }

    /**
     * Set with for colgroups
     * @param integer $width
     * @return this
     */
    public function setWidth($width) {
        $this->width = $width;
        return $this;
    }

    /**
     * Set callback for footer content
     * @param callback $callback
     * @return this
     */
    public function setFooterContentCb($callback) {
        $this->footerContentCb = $callback;
        return $this;
    }

    /**
     * Sets aggregate function for footer
     * @return this
     */
    public function setFooterAggregate($aggregate) {
        $this->footerAggregate = $aggregate;
        return $this;
    }

    /**
     * Set content for footer
     * @param string $content
     * @return this
     */
    public function setFooterContent($content) {
        $this->footerContent = $content;
        return $this;
    }

    /**
     * Set helper (formatter) of footer content
     * @param callback $callback
     * @return this
     */
    public function setFooterContentHelper($callback) {
        $this->footerContentHelper = $callback;
        return $this;
    }

    /**
     * Set callback to create link (a href)
     * @param callback $callback
     * @return this
     */
    public function setBodyLinkCb($callback) {
        $this->bodyLinkCb = $callback;
        return $this;
    }

    /**
     * Set callback to create modal link (a href)
     * @param callback $callback
     * @return this
     */
    public function setBodyLinkModalCb($callback) {
        $this->bodyLinkModalCb = $callback;
        return $this;
    }

    /**
     * Set content for body cell
     * @param string $content
     * @return this
     */
    public function setBodyContent($content) {
        $this->bodyContent = $content;
        return $this;
    }

    /**
     * Set callback to create onClick link
     * @param callback $callback
     * @return this
     */
    public function setBodyOnClickCb($callback) {
        $this->bodyOnClickCb = $callback;
        return $this;
    }

    /**
     * Set callback to create content for body cell
     * @param callback $callback
     * @return this
     */
    public function setBodyContentCb($callback) {
        $this->bodyContentCb = $callback;
        return $this;
    }

    /**
     * Set helper (formatter) of content
     * @param callback $callback
     * @return this
     */
    public function setBodyContentHelper($callback) {
        $this->bodyContentHelper = $callback;
        return $this;
    }

    /**
     * Set callback to create body cell title
     * @param callback $callback
     * @return Column
     */
    public function setBodyTitleContentCb($callback) {
        $this->bodyTitleCb = $callback;
        return $this;
    }

    /**
     * Set default orderBy and it's direction
     * @param string $direction (asc|desc)
     * @return this
     */
    public function setDefaultOrder($direction) {
        $this->getParent()->setDefaultOrderBy($this->getName('sql'), $direction);
        return $this;
    }

    /**
     * Set visibility of column
     * @param string $direction (asc|desc)
     * @return this
     */
    public function setVisibility($visibility) {
        $this->visibility = $visibility;
        return $this;
    }

    /**
     * Sets casting of column
     * @param string $cast
     * @param string $castAlias
     * @return this
     */
    public function setCast($cast, $castAlias) {
        $this->cast = $cast;
        $this->castAlias = $castAlias;
        return $this;
    }

    /**
     * Determines whether column has casting defined
     * @return boolean
     */
    public function hasCast() {
        return $this->cast !== null;
    }

    /**
     * Gets formatted casting of column
     * @return string
     */
    public function getCast() {
        return $this->cast . ' AS ' . $this->castAlias;
    }

}
