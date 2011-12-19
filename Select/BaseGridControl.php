<?php

namespace SnapCRUD\Select;

use Nette\Application\UI\Control,
    Nette\Application\UI\Form,
    Nette\Utils\Paginator,
    Nette\Forms\Controls\SubmitButton,
    Nette\DI;

/**
 * BaseGridControl
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 * @abstract
 */
abstract class BaseGridControl extends \SnapCRUD\BaseControl {

    /**
     * Configuring properties
     */
    protected $useSort = true, $useSingleColumnSort = true, $usePaginator = true, $defaultOrderByCounter = 0,
    $defaultOrderByAscClass = 'headerSortUp', $defaultOrderByDescClass = 'headerSortDown',
    $autorefreshSignal, $autorefreshInterval, $buildOnlyWhenSearchSet = false, $checkboxesContainer;
    /**
     * Core
     */
    protected $paginator, $content;
    /**
     * Determinatees
     */
    protected $hasContent = false, $hasCheckboxes = false, $hasToolbar = false, $builded = false, $hasSearch = false, $hasSearchSet = false;
    /**
     * Paginator properties
     */
    protected $itemsPerPage = 20, $quadrants = 4, $surround = 2;
    /**
     * Controls
     */
    protected $form, $defaultAddAction = 'form', $defaultEditAction = 'form', $defaultDeleteAction = 'delete';
    /**
     * Conditions
     */
    protected $conditions = array();
    /**
     * Helpers
     */
    protected $helpers = array();
    /**
     * Buttons
     */
    protected $buttons = array();

    //TODO  solve backlinking
    //$this->context->sessionSection->backlink = $this->getPresenter()->getParam('id');
    //$this->context->sessionSection->backlink= $this->getPresenter()->getApplication()->storeRequest();
    //TODO move to it's getter
    //    if (!isset($this->context->sessionSection->orderByModified)) {
    //	$this->context->sessionSection->orderByModified = false;
    //    }

    /**
     * Render grid
     * @return string
     */
    public function render() {
        if (!$this->isBuilded()) {
            $this->build();
        }

        if ($this->context->sessionSection->search) {
            $this->getSearchControls()->setDefaults($this->context->sessionSection->search);
        }

        ob_start();
        $this->template->setFile($this->getTemplateFilename());
        $this->template->render();
        echo ob_get_clean();
    }

    /**
     * Build grid
     */
    public function build() {

    }

    /**
     * Apply grid's conditions, query added in callback
     */
    protected function applyConditions() {
        foreach ($this->conditions as $condition) {
            if ($condition->hasCallback()) {
                call_user_func($condition->getCallback(), $this);
            }
        }
    }

    /**
     * Register helper
     * @param string $name
     * @param array $callback
     * @return this
     */
    public function registerHelper($name, $callback) {
        if ($this->hasHelper($name)) {
            throw new \Exception("Helper '$name' is already registered.");
        }
        $this->helpers[$name] = $callback;
        return $this;
    }

    /**
     * Determines whether helper with $name
     * @param string $name
     * @return boolean
     */
    public function hasHelper($name) {
        return \key_exists($name, $this->helpers);
    }

    /**
     * Get helper
     * @param string $name
     * @return callback
     */
    public function getHelper($name) {
        return $this->hasHelper($name) ? $this->helpers[$name] : false;
    }

    /**
     * Gets datafeed
     * @return \SnapCRUD\DataFeeds\IDataFeed
     */
    public function getDatafeed() {
        return $this->context->datafeed;
    }

    /**
     * Controls form
     */

    /**
     * Add button to toolbar
     *
     * @param string $name
     * @param string $label
     * @param callback $callback
     * @param string $cardinality
     * @return Button
     */
    public function addButton($name, $label, $callback = null, $cardinality = null) {
        $this->hasToolbar = true;
        $this->hasCheckboxes = true;

        $element = new SubmitButton($label);
        if ($callback) {
            $element->onClick[] = $callback;
        }

        if ($cardinality) {
            $element->getControlPrototype()
                    ->class($cardinality, true);
        }

        $this->getToolbar()->addComponent($element, $name);

        $button = new Controls\Button($this, $name, $element);
        $this->buttons[$name] = $button;

        return $button;
    }

    /**
     * Gets form
     * @return Form
     */
    public function getForm() {
        if (!$this->form) {
            $this->form = new Form($this, 'form');
            $this->form->setTranslator($this->context->translator);
            $this->form->addContainer('toolbar');
            $this->form->addContainer('searchControls');

            $presenter = $this->getPresenter();
            $grid = $this;
            $searchSubmits = $this->form->addContainer('searchSubmits');
            $searchSubmits->addSubmit('search', 'Search')
                    ->onClick[] = function(SubmitButton $button) {
                        $button->getForm()->getParent()->getContext()->sessionSection->page = 1;
                        $button->getForm()->getParent()->getContext()->sessionSection->search = $button->getForm()
                                ->getParent()
                                ->getSearchControls()
                                ->getValues();
                        $button->getForm()->getPresenter()->redirect('this');
                    };
            //$this->search['apply']->getControlPrototype();
            $searchSubmits->addSubmit('erase', 'Reset')
                    ->onClick[] = function(SubmitButton $button) {
                        unset($button->getForm()->getParent()->getContext()->sessionSection->search);
                        $button->getForm()
                                ->getPresenter()
                                ->redirect('this');
                    };
            //$this->search['erase']->getControlPrototype();
        }
        return $this->form;
    }

    /**
     * Gets toolbar container
     * @return \Nette\Forms\Container
     */
    public function getToolbar() {
        return $this->getForm()->offsetGet('toolbar');
    }

    /**
     * Gets container for search controls
     * @return \Nette\Forms\Container
     */
    public function getSearchControls() {
        return $this->getForm()->offsetGet('searchControls');
    }

    /**
     * Gets container for search buttons
     * @return \Nette\Forms\Container
     */
    public function getSearchSubmits() {
        return $this->getForm()->offsetGet('searchSubmits');
    }

    /**
     * Gets container for checkboxes
     * @return \Nette\Forms\Container
     */
    public function getCheckboxes() {
        $container = $this->checkboxesContainer . 'checkboxes';
        if (!$this->getForm()->offsetExists($container)) {
            $this->getForm()->addContainer($container);
        }
        return $this->getForm()->offsetGet($container);
    }

    /**
     * Callback for Add button
     * @param SubmitButton $button
     */
    public function controls_onAdd(SubmitButton $button) {
        $this->getPresenter()->redirect($this->defaultAddAction);
    }

    /**
     * Callback for Edit button
     * @param SubmitButton $button
     */
    public function controls_onEdit(SubmitButton $button) {
        $this->getPresenter()->redirect($this->defaultEditAction, $this->getSelectedRow());
    }

    /**
     * Callback for Delete button
     * @param SubmitButton $button
     */
    public function controls_onDelete(SubmitButton $button) {
        $key = $this->getPresenter()->storeValues($this->getSelectedRows());
        $this->getPresenter()->redirect($this->defaultDeleteAction, array('sl' => $key));
    }

    /**
     * Creates checkbox for item and returns control's Html
     * @param integer $rowId
     * @param boolean $hidden
     * @return Html
     */
    public function createCheckbox($rowId, $hidden= false) {
        if ($hidden) {
            throw new \Nette\NotImplementedException('Hidden checkbox not implemented yet');
        }
        $checkbox = $this->getCheckboxes()
                ->addCheckbox($rowId, 'Checkbox for row ' . $rowId)
                ->setOption('gridrendered', true);
        return $checkbox->getControl();
    }

    /**
     * Adds search control
     * @param string $name
     * @param \Nette\ComponentModel\IComponent $control
     * @return this
     */
    public function addSearch($name, \Nette\ComponentModel\IComponent $control) {
        $this->hasSearch = true;
        $this->getSearchControls()->addComponent($control, $name);
        return $this;
    }

    /**
     * Get current search values
     * @return array
     */
    public function getSearchValues() {
        $search = $this->context->sessionSection->search;
        return $search instanceof \Nette\ArrayHash && count($search) ? $search : array();
    }

    /**
     * Getters
     */

    /**
     * Get sort class for direction
     * @param striong $direction (asc|desc)
     * @return string
     */
    public function getSortOrderClass($direction) {
        if ($direction == 'asc') {
            return $this->defaultOrderByAscClass;
        } elseif ($direction == 'desc') {
            return $this->defaultOrderByDescClass;
        } else {
            throw new \Exception('Invalid direction');
        }
    }

    /**
     * Gets selected row
     * @return integer
     */
    public function getSelectedRow() {
        $selected = $this->getSelectedRows();
        if (count($selected) > 1) {
            throw new \Nette\Application\AbortException('Selected more than 1 row');
        } elseif (count($selected) < 1) {
            throw new \Nette\Application\AbortException('At least one row needs to be selected');
        }
        return $selected[0];
    }

    /**
     * Gets array of selected rows
     * @return array
     */
    public function getSelectedRows() {
        $this->build();
        $selected = array();
        foreach ($this->getCheckboxes()->getValues() as $id => $checked) {
            if ($checked) {
                $selected[] = (integer) $id;
            }
        }
        return $selected;
    }

    /**
     * Get table
     * @return string
     */
    public function getContent() {
        return $this->content;
    }

    public function getPage() {
        if (!$this->context->sessionSection->page) {

            $this->context->sessionSection->page = 1;
        }
        return $this->context->sessionSection->page;
    }

    /**
     * Get Paginator
     * @return Paginator
     */
    public function getPaginator() {
        if (!$this->paginator) {
            $paginator = new Paginator;
            $paginator->setItemsPerPage($this->itemsPerPage);
            $paginator->setItemCount($this->context->datafeed->getCount());
            $paginator->setPage($this->getPage());
            $this->paginator = $paginator;
        }
        return $this->paginator;
    }

    /**
     * Get array of paginator steps
     * @return array
     */
    public function getPaginatorSteps() {
        $paginator = $this->getPaginator();
        $steps = range(max($paginator->getFirstPage(), $paginator->getPage() - $this->surround), min($paginator->getLastPage(), $paginator->getPage() + $this->surround));
        $quadrants = ($paginator->getPageCount() - 1) / $this->quadrants;
        for ($i = 0; $i <= $this->quadrants; $i++) {
            $steps[] = round($quadrants * $i) + $paginator->getFirstPage();
        }
        sort($steps);
        return array_values(array_unique($steps));
        ;
    }

    /**
     * Grid determinatees
     */

    /**
     * Determines whether grid has any content
     * @return boolean
     */
    public function hasContent() {
        return $this->hasContent && true;
    }

    /**
     * Determines whether grid has checkbox for each item
     * @return boolean
     */
    public function hasCheckboxes() {
        return $this->hasCheckboxes && true;
    }

    /**
     * Determines whether grid has toolbar
     * @return boolean
     */
    public function hasToolbar() {
        return $this->hasToolbar && true;
    }

    /**
     * Determines whether grid has search set
     * @return boolean
     */
    public function hasSearch() {
        return $this->hasSearch && true;
    }

    /**
     * Determines whether grid table is builded
     * @return boolean
     */
    public function isBuilded() {
        return $this->builded && true;
    }

    /**
     * Determines whether orderBy was modified or to use defaults
     * @return boolean
     */
    public function isOrderByModified() {
        if (!isset($this->context->sessionSection->orderByModified)) {
            $this->context->sessionSection->orderByModified = false;
        }
        return $this->context->sessionSection->orderByModified;
    }

    /**
     * Determines whether use paginator or show all records
     * @return boolean
     */
    public function isPaginated() {
        return $this->usePaginator && true;
    }

    /**
     * Grid configurators
     */

    /**
     * Turn on single column sorting
     * @return this
     */
    public function useSingleColumnSort() {
        $this->useSingleColumnSort = true;
        return $this;
    }

    /**
     * Turn on multi column sorting
     * @return this
     */
    public function useMultiColumnSort() {
        $this->useSingleColumnSort = false;
        return $this;
    }

    /**
     * Switch paginator use
     * @param boolean $use
     * @return this
     */
    public function usePaginator($use) {
        $this->usePaginator = $use;
        return $this;
    }

    /**
     * Force to use checkboxes even if no buttons and toolbar
     * @param boolean $state
     * @return this
     */
    public function useCheckboxes($state = true) {
        $this->hasCheckboxes = $state;
        return $this;
    }

    /**
     * Set count of items per page
     * @param integer $itemsPerPage
     * @return this
     */
    public function setItemsPerPage($itemsPerPage) {
        $this->itemsPerPage = $itemsPerPage;
        return $this;
    }

    /**
     * Set grid's title
     * @param string $title
     * @return this
     */
    public function setTitle($title) {
        $this->template->title = $title;
        return $this;
    }

    /**
     * Sets custom form
     * @param Form $form
     * @return this
     */
    public function setForm(Form $form) {
        $this->form = $form;
        return $this;
    }

    /**
     * Add default buttons to toolbar
     * @param string $name
     * @return this
     */
    public function addDefaultButtons($name = null) {
        $name = is_null($name) ? '' : ' ' . $name;

        $this->addButton('add', 'Add' . $name, array($this, 'controls_onAdd'))
                ->setButtonSet('main');

        $this->addButton('edit', 'Edit' . $name, array($this, 'controls_onEdit'), 'for_one')
                ->setButtonSet('main');

        $this->addButton('delete', 'Delete' . $name, array($this, 'controls_onDelete'), 'for_many')
                ->setButtonSet('main');
        return $this;
    }

    /**
     * Set default action for add button
     * @param string $action
     * @return this
     */
    public function setDefaultAddAction($action) {
        $this->defaultAddAction = $action;
        return $this;
    }

    /**
     * Set default action for edit button
     * @param string $action
     * @return this
     */
    public function setDefaultEditAction($action) {
        $this->defaultEditAction = $action;
        return $this;
    }

    /**
     * Set autorefresh signal
     * @param string $signal
     */
    public function setAutorefresh($signal, $interval = 3000) {
        $this->autorefreshSignal = $signal;
        $this->autorefreshInterval = $interval;
        return $this;
    }

    public function setContent($content) {
        $this->content = $content;
    }

    /**
     * Set default action for delete button
     * @param string $action
     * @return this
     */
    public function setDefaultDeleteAction($action) {
        $this->defaultDeleteAction = $action;
        return $this;
    }

    /**
     * Set default orderBy used by columns definition
     * @param string $columnSqlName
     * @param string $direction (asc|desc)
     * @return this
     */
    public function setDefaultOrderBy($columnSqlName, $direction) {
        if (!$this->isOrderByModified()) {

            if ($direction != 'asc' and $direction != 'desc') {
                throw new \Exception("invalid direction '$direction' for field '$columnSqlName'");
            }

            if (!isset($this->context->sessionSection->orderBy)) {
                $this->context->sessionSection->orderBy = (object) array();
            }

            # single column mode
            if ($this->useSingleColumnSort) {
                if ($this->defaultOrderByCounter > 0) {
                    throw new \Exception("multiple default sort definitions in SingleColumnSort mode");
                }
                unset($this->context->sessionSection->orderBy);
                $this->context->sessionSection->orderBy = (object) array($columnSqlName => $direction);
            } else {
                $this->context->sessionSection->orderBy->{$columnSqlName} = $direction;
            }
        }
        $this->defaultOrderByCounter++;
        return $this;
    }

    /**
     * Set group name for control's buttons
     * @param string $name
     * @return this
     */
    public function setButtonsGroup($name) {
        throw new \Nette\NotSupportedException();
        $this->buttonsGroup = $name;
        return $this;
    }

    /**
     * Sets flag if datagrid should be build when search is not set
     * @param type $build
     * @return GridControl
     */
    public function setBuildOnlyWithSearchSet($build) {
        $this->buildOnlyWhenSearchSet = $build;
        return $this;
    }

    /**
     * Set checkboxes container preffix
     * @param string $container
     * @return this
     */
    public function setCheckboxesContainer($container) {
        $this->checkboxesContainer = $container;
        return $this;
    }

    /**
     * Disable sorting
     * @return this
     */
    public function disableSorting() {
        $this->useSort = false;
        return $this;
    }

    /**
     * Determines whether to build datagrid when search is not set
     * @return boolean
     */
    public function buildOnlyWhenSearchSet() {
        return true && $this->buildOnlyWhenSearchSet;
    }

    /**
     * Determines whether search is set
     * @return boolean
     */
    public function hasSearchSet() {
        return true && $this->hasSearchSet;
    }

    /**
     * translate message
     * @return <type>
     */
    public function translate($message, $count = null) {
        return $this->context->translator->translate($message, $count);
    }

    /**
     * Handlers
     */

    /**
     * Handler for orderBy
     *
     * Switching order: asc -> desc -> none
     * Sets namespace's flag orderByModified to true, this controls defaults from definition
     *
     * @param string $field
     * @return void
     */
    public function handleOrderBy($field) {
        if (empty($field)) {
            throw new Exception('field required');
        }

        # singleColumnSort
        if ($this->useSingleColumnSort) {
            $orderBy = ( isset($this->context->sessionSection->orderBy->{$field})) ? $this->context->sessionSection->orderBy : new \stdClass;
        }
        # multiColumnSort
        else {
            $orderBy = $this->context->sessionSection->orderBy;
        }

        if (isset($orderBy->{$field}) and $orderBy->{$field} == 'asc') {
            $orderBy->{$field} = 'desc';
        } elseif (isset($orderBy->{$field}) and $orderBy->{$field} == 'desc') {
            unset($orderBy->{$field});
        } else {
            $orderBy->{$field} = 'asc';
        }

        # in single mode, unset namespace
        if ($this->useSingleColumnSort) {
            unset($this->context->sessionSection->orderBy);
        }

        $this->context->sessionSection->orderBy = $orderBy;
        $this->context->sessionSection->orderByModified = true;
        $this->getPresenter()->redirect('this');
    }

    /**
     * Handler for page
     * @param integer $page
     */
    public function handlePage($page) {
        if (!is_numeric($page)) {
            throw new \Nette\Application\AbortException("Invalid handler argument");
        }
        $this->context->sessionSection->page = $page;
        $this->getPresenter()->redirect('this');
    }

    /**
     * Conditions
     */

    /**
     * Add condition with callback to grid
     * @param callback $callback
     * @return Condition
     */
    public function addConditionCb($callback) {
        $condition = new Controls\Condition($this);
        $condition->setCallback($callback);
        $this->conditions[] = $condition;
        return $condition;
    }

    public function setHasContent($hasContent) {
        $this->hasContent = $hasContent;
    }

}
