<?php

namespace SnapCRUD\Select\ImageGrid;


/**
 * ImageGridControl
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 * @package    Frontend
 */
class ImageGridControl extends \SnapCRUD\Select\BaseGridControl {

    /**
     * Nazov sablony
     * @var string
     */
    protected $templateFile = 'ImageGridControl.latte';
    /**
     * Pole riadkov
     * @var array of ImageSortColumn
     */
    protected $sortColumns = array();
    /**
     * Pocet prispevkov na stranke
     * @var integer
     */
    protected $itemsPerPage = 28;
    /**
     * Callback pre vytvorenie obsahu
     * @var callback
     */
    protected $callbackContent;
    /**
     * Callback pre vytvorenie labelu pre thumb
     * @var callback
     */
    protected $callbackLabel;
    /**
     * Callback pre vytvorenie linku na doubleclik,
     * @var callback
     */
    protected $callbackSelect;
    /**
     * Pole na triediace stlpce
     * @var array
     */
    protected $sort = array();


    /**
     * Vytvorenie noveho stlpca v gride
     * @param string $name
     * @param string $label
     * @param string $desctiption
     * @return DataGridColumn
     */
    public function addSortColumn($name, $label= null) {

        if (key_exists($name, $this->sortColumns)) {
            throw new Exception("column '$name' already exists");
        }

        $this->sortColumns[$name] = new Controls\SortColumn($this, $name, $label);
        return $this->sortColumns[$name];
    }

    /**
     * Nastavi callback pre obsah
     * @param callback $callback
     * @return ImageGridControl
     */
    public function setCallbackContent($callback) {
        $this->callbackContent = $callback;
        return $this;
    }

    /**
     * Nastavi callback pre label
     * @param callback $callback
     * @return ImageGridControl
     */
    public function setCallbackLabel($callback) {
        $this->callbackLabel = $callback;
        return $this;
    }

    /**
     * Nastavi callback pre vytvorenie linku na doubleclickl
     * @param callback $callback
     * @return ImageGridControl
     */
    public function setCallbackSelect($callback) {
        $this->callbackSelect = $callback;
        return $this;
    }

    /**
     * Validacia zadefinovanych stlpcov, nazvy sa porovnavaju so stlpcami z vykonaneho dataSource
     * @param DibiResult $row
     * @return void
     */
    protected function validate($row) {
        foreach ($this->sortColumns as $sortColumn) {
            # obycajny column alebo cellCallback
            if (!isset($row->{$sortColumn->getName()}) and !$this->model) {
                throw new Exception("column '{$sortColumn->getName()}' not found in query");
            }
        }

        # TODO doriesit overenie stlpcov cez orderby
        foreach ($this->getNamespace()->orderBy as $field => $direction) {
            //var_dump($field);
        }
    }

    /**
     * renderovanie controlu
     * @return void
     */
    public function render() {
        $this->template->sort = $this->sort;
        $this->template->body = $this->body;
        $this->template->form = $this->form;
        $this->template->imageGrid = $this;

        parent::render();
    }

    /**
     * Vytvori grid
     * @todo potrebne rozdelit do mensich celkov, aby bolo mozne jednoducho dedit a vytvorit tak TreeGrid
     *       alebo ImageGrid
     */
    public function createModel() {
        parent::createModel();

        #header & body meta, colgroup
        foreach ($this->sortColumns as $sortColumn) {
            $this->sort[] = $sortColumn->templatize();
        }

        # apply cond
        $this->computeConditions();

        # paginator
        $this->paginator = new Paginator;
        $this->paginator->itemsPerPage = $this->itemsPerPage;
        $this->paginator->setItemCount($this->model ? count($this->dataSource->getDataSource()) : count($this->dataSource));
        $this->paginator->setPage($this->getNamespace()->page);

        # paginator limits
        $this->dataSource->applyLimit($this->paginator->getLength(), $this->paginator->getOffset());

        # order by
        foreach ($this->namespace->orderBy as $orderField => $orderDirection) {
            $this->dataSource->orderBy($orderField, $orderDirection);
        }

        # rows from $this->rows
        # zatial odstranene
        # rows from dataSource and body
        $source = $this->model ? $this->dataSource->get() : $this->dataSource;

        $first = true;
        foreach ($source as $row) {
            if ($first) {
                $this->hasContent = true;
                $this->validate($row);
                $first = false;
            }

            if ($this->callbackContent) {
                $content = call_user_func($this->callbackContent, $row);
            } else {
                throw new Exception("unable to find content for item id '$row->id'");
            }

            if ($this->callbackLabel) {
                $label = call_user_func($this->callbackLabel, $row);
            } else {
                throw new Exception("unable to find label for item id '$row->id'");
            }

            # TODO dorobit naplnenie rows, teda dalsich infacii pre thumb
            if ($this->callbackSelect === false) {
                $ondblclick = null;
            } elseif ($this->callbackSelect) {
                $ondblclick = call_user_func($this->callbackSelect, $row);
            } else {
                $ondblclick = $this->getPresenter()->link($this->formAction, $row->id);
            }

            $this->body[] = (object) array(
                        'checkbox' => $this->form->addCheckbox($row->id, null)->control,
                        'content' => $content,
                        'ondblclick' => $ondblclick,
                        'label' => $label,
                        'lines' => null,
            );
        }
        $this->modelCreated = true;
    }

    /**
     * Nastavi defaultne sortovanie pre dany stlpec, len ak nebol nastaveny priznak $this->getNamespace()->orderByModified (zmeneny order cez signal)
     * Kontroluje sa aj situacia, ak v singleColumnSort mode sa snazime definovat sort pre viac ako 1 stlpec
     *
     * @param string $field
     * @param string $direction "asc/desc"
     * @return void
     */
    public function setDefaultOrder($field, $direction) {
        if (!$this->getNamespace()->orderByModified) {

            if (!is_string($field)) {
                throw new Exception("invalid column name '$field'");
            }

            if ($direction != 'asc' and $direction != 'desc') {
                throw new Exception("invalid direction '$direction' for field '$field'");
            }

            # in single mode
            if ($this->singleColumnSort) {
                if ($this->orderByCounter > 0) {
                    throw new Exception("multiple default sort definitions in SingleColumnSort mode");
                }
                unset($this->getNamespace()->orderBy);
            }

            if (!isset($this->getNamespace()->orderBy)) {
                $this->getNamespace()->orderBy = (object) array();
            }

            $this->getNamespace()->orderBy->{$field} = $direction;
        }

        $this->orderByCounter++;
    }

}
