<?php

namespace SnapCRUD\Select\DataGrid;

use Nette\Utils\Html;

/**
 * DataGridControl
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 */
class DataGridControl extends \SnapCRUD\Select\BaseGridControl {

    /**
     * DataGrid properties
     */
    protected $columns = array(), $rows = array();

    /**
     * Add column to DataGrid
     * @param string $name
     * @param string $label
     * @return Column
     */
    public function addColumn($name, $label = null) {
        $element = new Controls\Column($this, $name, $label);
        $safeName = $element->getName('safe');

        if ($this->hasColumn($safeName)) {
            throw new \Exception("column '" . $element->getName('definition') . "' already exists");
        }
        if ($this->useSort === false) {
            $element->unsortable();
        }
        $this->columns[$safeName] = $element;
        return $this->columns[$safeName];
    }

    /**
     * Add row to DataGrid
     * @param string $name
     * @return Row
     */
    public function addRow($name) {
        $element = new Row($this, $name);

        if ($this->hasRow($name)) {
            throw new \Exception("row '" . $name . "' already exists");
        }
        $this->rows[$name] = $element;
        return $this->rows[$name];
    }

    /**
     * Determines whether column with name exists
     * @param string $name
     * @return boolean
     */
    public function hasColumn($name) {
        $name = str_replace('.', '__', $name);
        return key_exists($name, $this->getColumns());
    }

    /**
     * Determines whether row with name exists
     * @param string $name
     * @return boolean
     */
    public function hasRow($name) {
        return key_exists($name, $this->getRows());
    }

    /**
     * Get columns
     * @return array of Column
     */
    public function getColumns() {
        return $this->columns;
    }

    /**
     * Get column with $name
     * @return Column
     */
    public function getColumn($name) {
        $name = str_replace('.', '__', $name);
        return $this->hasColumn($name) ? $this->columns[$name] : false;
    }

    /**
     * Get rows
     * @return array of Row
     */
    public function getRows() {
        return $this->rows;
    }

    /**
     * Build grid
     */
    public function build() {
        parent::build();

        $control = $this;

        # conditions
        $this->applyConditions();

        # check search values when grid is set to be build only while search set
        $this->hasSearchSet = false;
        if ($this->buildOnlyWhenSearchSet) {
            foreach ($this->getSearchValues() as $value) {
                if (!empty($value)) {
                    $this->hasSearchSet = true;
                }
            }
            if ($this->hasSearchSet === false) {
                return;
            }
        }

        # create feed without pagination and orders
        #$this->context->datafeed->cloneFromActive('raw');
        # paginator limits
        if ($this->isPaginated()) {
            $this->context->datafeed->applyLimit($this->getPaginator()->getLength(), $this->getPaginator()->getOffset());
        }

        # order by
        if ($this->useSort) {
            foreach ($this->context->sessionSection->orderBy as $orderField => $orderDirection) {
                $this->context->datafeed->orderBy($orderField, $orderDirection);
            }
        }

        $cache = new \Nette\Caching\Cache($this->getPresenter()->getContext()->templateCacheStorage, 'SnapCRUD.DataGrid');
        $cacheKey = array($this->getPresenter()->getName(), $this->getPresenter()->getAction(), $this->getName());
        $cached = $cache->load($cacheKey);
        if (!$cached) {
            $file = __FILE__;

            $code = array();
            $code[] = '<?php ob_start(); ?>';
            $code[] = Html::el('table')->width('100%')
                            ->class('datagrid')
                            ->data('autorefresh-signal', $this->autorefreshSignal)
                            ->data('autorefresh-interval', $this->autorefreshInterval)->startTag();

            $colgroup = Html::el('colgroup');
            $header = Html::el('thead');
            $_header = $header->create('tr');
            $footer = Html::el('tfoot');
            $_footer = $footer->create('tr');
            $first = true;
            $hasFooter = false;
            $casting = array();
            foreach ($this->getColumns() as $column) {

                # collecting cast
                if ($column->hasCast()) {
                    $casting[] = $column->getCast();
                }

                # collecting headers, footers
                if ($column->isVisible()) {
                    if ($first && $this->hasCheckboxes()) {
                        $first = false;
                        $colgroup->add(Html::el('col')->width(30));
                        $_header->add(Html::el('th')->class('control')->add(Html::el('input')->type('checkbox')->class('checkbox_selectall')));
                        $_footer->add(Html::el('th')->setHtml('&nbsp;'));
                    }
                    $colgroup->add($column->getCol());
                    $_header->add($column->getHeader());
                    $_footer->add($column->getFooter());
                    if ($column->hasFooter()) {
                        $hasFooter = true;
                    }
                }
            }

            # modifying selection
            if (count($casting)) {
                $code[] = '<?php
$control->context->datafeed->getSelection()->select("*, ' . implode(', ', $casting) . '"); ?>';
            }

            # setting selector
            /*            $code[]= '<?php
              $control->context->datafeed->getSelection()->select("'. implode(',', $columns ) .'"); ?>'; */

            # body content from rows
            #foreach ($this->getRows() as $row) {
            #$body->add($row->getHtml());
            #}
            #if ($hasFooter) {
            #    $this->content->add($footer);
            #}

            $code[] = $colgroup;
            $code[] = $header;
            $code[] = '<tbody>';
            $code[] = '<?php 
        $iterator = 0;
        foreach ($control->context->datafeed->getIterator() as $row) {
            $iterator++;
?>';
            $code[] = '<tr class="<?= ($iterator % 2) ? "even" : "odd" ?>">';
            $code[] = '<?php
$control->setHasContent(true);
if ($control->hasCheckboxes()) {
$id = $control->context->datafeed->getColumnFromRow($row, "id");
    ?><th class="control"><?= $control->createCheckbox($id); ?></th>
<?php } ?>';

            foreach ($this->getColumns() as $column) {
                if ($column->isVisible()) {
                    $code[] = $column->getBodyTemplate();
                }
            }

            $code[] = '<?php } ?>';
            $code[] = '</tbody>';
            $code[] = '</table>';
            $code[] = '<?php $control->setContent(ob_get_contents()); ob_clean(); ?>';

            $code = $cache->save($cacheKey, implode("\n", $code), array(
                        \Nette\Caching\Cache::FILES => array(
                            __FILE__,
                            $this->getPresenter()->getReflection()->getFileName()
                            )));

            \Nette\Utils\LimitedScope::evaluate($code, array('control' => $control));
        } else {
            require $cached['file'];
            fclose($cached['handle']);
        }
        $this->builded = true;
        return;
    }

    /**
     * Get content for ajax refreshing of drid content
     * @return \stdClass
     */
    public function getAutorefreshContent() {

        # conditions
        $this->applyConditions();

        # create feed without pagination and orders
        #$this->context->datafeed->cloneFromActive('raw');
        # paginator limits
        if ($this->isPaginated()) {
            $this->context->datafeed->applyLimit($this->getPaginator()->getLength(), $this->getPaginator()->getOffset());
        }

        # order by
        if ($this->useSort) {
            foreach ($this->context->sessionSection->orderBy as $orderField => $orderDirection) {
                $this->context->datafeed->orderBy($orderField, $orderDirection);
            }
        }

        # body
        $result = new \stdClass;
        $result->rows = array();
        $result->columns = array();
        $result->hasCheckboxes = $this->hasCheckboxes();
        $result->hasFooter = false;

        # body content from rows
        foreach ($this->getRows() as $row) {
            $result->rows[] = (string) $row->getHtml();
        }

        # body content from columns
        foreach ($this->context->datafeed->getIterator() as $row) {
            $bodyRow = array();
            $this->hasContent = true;

            foreach ($this->getColumns() as $column) {
                $body = $column->getBody($row);
                if ($column->isVisible()) {
                    $bodyRow[] = (string) $body;
                }
            }

            $result->columns[$row->getId()] = $bodyRow;
        }

        # tfoot
        $first = true;
        $footer = array();
        $hasFooter = false;
        foreach ($this->getColumns() as $column) {
            if ($column->isVisible()) {
                if ($first && $this->hasCheckboxes()) {
                    $first = false;
                    $footer[] = (string) Html::el('th')->setHtml('&nbsp;');
                }
                $footer[] = (string) $column->getFooter();
                if ($column->hasFooter()) {
                    $hasFooter = true;
                }
            }
        }

        if ($hasFooter) {
            $result->hasFooter = true;
            $result->footer = $footer;
        }
        return $result;
    }

    /**
     * Get empty container
     * @return Html
     */
    public function getEmptyContent() {
        $container = Html::el('');
        $container->add(Html::el('table')->width('100%')
                        ->class('datagrid')->add(Html::el('tr')->add(Html::el('td')->add(Html::el('p')->setText(_('No records found.'))))));
        return $container;
    }

    /**
     * Apply column's conditions
     */
    protected function applyConditions() {
        parent::applyConditions();

        # column conditions
        foreach ($this->getColumns() as $column) {
            foreach ($column->getConditions() as $condition) {
                $columnName = $condition->getParent()->getName('safe');
                if (isset($this->context->sessionSection->search[$columnName])
                        and !empty($this->context->sessionSection->search[$columnName])) {

                    $this->context->datafeed->where($condition->getQuery(), $this->context->sessionSection->search[$columnName]
                    );
                }
            }
        }
    }

}
