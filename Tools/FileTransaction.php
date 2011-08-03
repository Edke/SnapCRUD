<?php

namespace DannaxTools;

use Nette\Object;

/**
 * FileTransaction - commiting and rolling back file transactions
 *
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 */
class FileTransaction extends Object {

    /**
     * @var array
     */
    protected $files, $dirs;
    protected $tmpBase = '/tmp/';
    protected $tmpDir;

    public function __construct() {
        Environment::getApplication()->getPresenter()->onShutdown[] = array($this, 'trash');
    }

    /**
     *
     * @param string $source
     * @param string $destination
     * @param string $basedir
     * @return string
     */
    public function copy($source, $destination, $basedir) {

        # dirname needs to be created
        if (!file_exists($basedir)) {
            throw new Exception("Basedir '$basedir' does not exists.");
        }

        if (!is_writable($basedir)) {
            throw new Exception("Basedir '$basedir' is not writable.");
        }

        if (!preg_match('#\/$#', $basedir)) {
            throw new Exception("Basedir '$basedir' does not ends with slash.");
        }

        if (preg_match('#^/#', $destination)) {
            throw new Exception("Destination '$destination' can't begin with slash.");
        }

        # creating directories and rollback
        $_destination = $destination;
        $dirs = array();
        while (dirname($_destination) != '.') {
            $_destination = dirname($_destination);
            $dirs[] = $_destination;
        }
        if (count($dirs) > 0) {
            asort($dirs);
            $this->mkdir($basedir, $dirs);
        }
        #Debug::barDump($dirs);
        # searching for valid filename
        $basename = basename($destination);
        $mask = preg_match('#(.+)\.([^\.]+)$#', $basename, $match) ? $match[1] . '%s.' . $match[2] : $basename . '%s.';

        $counter = '';
        do {
            $_destination = dirname($destination) . '/' . sprintf($mask, (($counter) ? '-' : '') . $counter);
            $counter = (int) $counter;
            $counter++;
        } while (file_exists($basedir . $_destination));

        # move file to final destination
        if (!copy($source, $basedir . $_destination)) {
            throw new Exception("Unable to copy file '$source' to destination '$basedir$_destination'.");
        }

        # saves destination and source for rollback
        $this->store($basedir . $_destination);
        $this->store($source, true);
        //Debug::barDump($this->files);

        return $_destination;
    }

    protected function store($file, $temporary = false) {
        $this->files[$file] = $temporary;
    }

    /**
     * @param string $basedir
     * @param array $dirs
     */
    protected function mkdir($basedir, $dirs, $temporary = false) {
        if (is_string($dirs)) {
            $dirs = array($dirs);
        }

        foreach ($dirs as $dir) {
            if (!file_exists($basedir . $dir)) {
                $this->dirs[$basedir . $dir] = $temporary;
                if (!mkdir($basedir . $dir, 0777)) {
                    throw new Exception("Unable to create directory '$basedir.$dir'.");
                }
            }
        }
        #Debug::barDump($this->dirs);
    }

    public function trash() {
        foreach ($this->files as $file => $temporary) {
            if (!unlink($file)) {
                trigger_error("Files rollback failed, unable to unlink file '$file', possible futher orphaned filed and dirs.", E_USER_WARNING);
            }
        }
        $this->files = array();

        $dirs = $this->dirs;
        krsort($dirs);
        foreach ($dirs as $dir => $temporary) {
            if (!rmdir($dir)) {
                trigger_error("Dirs rollback failed, unable to unlink dir '$dir', possible futher orphaned dirs.", E_USER_WARNING);
            }
        }
        $this->dirs = array();
        //Debug::barDump('Files and dirs rollback executed succesfully');
    }

    /**
     * Do not trash non-temporary files and dirs
     */
    public function commit() {
        foreach ($this->files as $file => $temporary) {
            if (!$temporary) {
                unset($this->files[$file]);
            }
        }

        foreach ($this->dirs as $dir => $temporary) {
            if (!$temporary) {
                unset($this->dirs[$dir]);
            }
        }
    }

    /**
     *
     * @return string
     */
    public function getTemporaryDir() {
        if (!$this->tmpDir) {
            $tmpDir = md5(time(true)) . '/';
            $this->mkdir($this->tmpBase, $tmpDir, true);
            $this->tmpDir = $this->tmpBase . $tmpDir;
        }
        return $this->tmpDir;
    }

}
