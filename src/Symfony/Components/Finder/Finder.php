<?php

namespace Symfony\Components\Finder;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Finder allows to build rules to find files and directories.
 *
 * It is a thin wrapper around several specialized iterator classes.
 *
 * All rules may be invoked several times, except for ->in() method.
 * Some rules are cumulative (->name() for example) whereas others are destructive
 * (most recent value is used, ->maxDepth() method for example).
 *
 * All methods return the current Finder object to allow easy chaining:
 *
 * $finder = new Finder();
 * $iterator = $finder->files()->name('*.php')->in(__DIR__);
 *
 * @package    Symfony
 * @subpackage Components_Finder
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Finder implements \IteratorAggregate
{
    protected $mode        = 0;
    protected $names       = array();
    protected $notNames    = array();
    protected $exclude     = array();
    protected $filters     = array();
    protected $minDepth    = 0;
    protected $maxDepth    = INF;
    protected $sizes       = array();
    protected $followLinks = false;
    protected $sort        = false;
    protected $ignoreVCS   = true;
    protected $dirs        = array();
    protected $minDate     = false;
    protected $maxDate     = false;

    /**
     * Restricts the matching to directories only.
     *
     * @return Symfony\Components\Finder The current Finder instance
     */
    public function directories()
    {
        $this->mode = Iterator\FileTypeFilterIterator::ONLY_DIRECTORIES;

        return $this;
    }

    /**
     * Restricts the matching to files only.
     *
     * @return Symfony\Components\Finder The current Finder instance
     */
    public function files()
    {
        $this->mode = Iterator\FileTypeFilterIterator::ONLY_FILES;

        return $this;
    }

    /**
     * Sets the maximum directory depth.
     *
     * The Finder will descend at most $level levels of directories below the starting point.
     *
     * @param  int $level The max depth
     *
     * @return Symfony\Components\Finder The current Finder instance
     *
     * @see Symfony\Components\Finder\Iterator\LimitDepthFilterIterator
     */
    public function maxDepth($level)
    {
        $this->maxDepth = (double) $level;

        return $this;
    }

    /**
     * Sets the minimum directory depth.
     *
     * The Finder will start matching at level $level.
     *
     * @param  int $level The min depth
     *
     * @return Symfony\Components\Finder The current Finder instance
     *
     * @see Symfony\Components\Finder\Iterator\LimitDepthFilterIterator
     */
    public function minDepth($level)
    {
        $this->minDepth = (integer) $level;

        return $this;
    }

    /**
     * Sets the maximum date (last modified) for a file or directory.
     *
     * The date must be something that strtotime() is able to parse:
     *
     *   $finder->maxDate('yesterday');
     *   $finder->maxDate('2 days ago');
     *   $finder->maxDate('now - 2 hours');
     *   $finder->maxDate('2005-10-15');
     *
     * @param  string $date A date
     *
     * @return Symfony\Components\Finder The current Finder instance
     *
     * @see Symfony\Components\Finder\Iterator\DateRangeFilterIterator
     */
    public function maxDate($date)
    {
        if (false === $this->maxDate = @strtotime($date)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid date'));
        }

        return $this;
    }

    /**
     * Sets the minimum date (last modified) for a file or a directory.
     *
     * The date must be something that strtotime() is able to parse (@see maxDate()).
     *
     * @param  string $date A date
     *
     * @return Symfony\Components\Finder The current Finder instance
     *
     * @see Symfony\Components\Finder\Iterator\DateRangeFilterIterator
     */
    public function minDate($date)
    {
        if (false === $this->minDate = @strtotime($date)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid date'));
        }

        return $this;
    }

    /**
     * Adds rules that files must match.
     *
     * You can use patterns (delimited with / sign), globs or simple strings.
     *
     * $finder->name('*.php')
     * $finder->name('/\.php$/') // same as above
     * $finder->name('test.php')
     *
     * @param  string $pattern A pattern (a regexp, a glob, or a string)
     *
     * @return Symfony\Components\Finder The current Finder instance
     *
     * @see Symfony\Components\Finder\Iterator\FilenameFilterIterator
     */
    public function name($pattern)
    {
        $this->names[] = $pattern;

        return $this;
    }

    /**
     * Adds rules that files must not match.
     *
     * @param  string $pattern A pattern (a regexp, a glob, or a string)
     *
     * @return Symfony\Components\Finder The current Finder instance
     *
     * @see Symfony\Components\Finder\Iterator\FilenameFilterIterator
     */
    public function notName($pattern)
    {
        $this->notNames[] = $pattern;

        return $this;
    }

    /**
     * Adds tests for file sizes.
     *
     * $finder->size('> 10K');
     * $finder->size('<= 1Ki');
     * $finder->size(4);
     *
     * @param string $size A size range string
     *
     * @return Symfony\Components\Finder The current Finder instance
     *
     * @see Symfony\Components\Finder\Iterator\SizeRangeFilterIterator
     * @see Symfony\Components\Finder\NumberCompare
     */
    public function size($size)
    {
        $this->sizes[] = new NumberCompare($size);

        return $this;
    }

    /**
     * Excludes directories.
     *
     * @param  string $dir A directory to exclude
     *
     * @return Symfony\Components\Finder The current Finder instance
     *
     * @see Symfony\Components\Finder\Iterator\ExcludeDirectoryFilterIterator
     */
    public function exclude($dir)
    {
        $this->exclude[] = $dir;

        return $this;
    }

    /**
     * Forces the finder to ignore version control directories.
     *
     * @return Symfony\Components\Finder The current Finder instance
     *
     * @see Symfony\Components\Finder\Iterator\IgnoreVcsFilterIterator
     */
    public function ignoreVCS($ignoreVCS)
    {
        $this->ignoreVCS = (Boolean) $ignoreVCS;

        return $this;
    }

    /**
     * Sorts files and directories by an anonymous function.
     *
     * The anonymous function receives two \SplFileInfo instances to compare.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @param  Closure $closure An anonymous function
     *
     * @return Symfony\Components\Finder The current Finder instance
     *
     * @see Symfony\Components\Finder\Iterator\SortableIterator
     */
    public function sort(\Closure $closure)
    {
        $this->sort = $closure;

        return $this;
    }

    /**
     * Sorts files and directories by name.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return Symfony\Components\Finder The current Finder instance
     *
     * @see Symfony\Components\Finder\Iterator\SortableIterator
     */
    public function sortByName()
    {
        $this->sort = Iterator\SortableIterator::SORT_BY_NAME;

        return $this;
    }

    /**
     * Sorts files and directories by type (directories before files), then by name.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return Symfony\Components\Finder The current Finder instance
     *
     * @see Symfony\Components\Finder\Iterator\SortableIterator
     */
    public function sortByType()
    {
        $this->sort = Iterator\SortableIterator::SORT_BY_TYPE;

        return $this;
    }

    /**
     * Filters the iterator with an anonymous function.
     *
     * The anonymous function receives a \SplFileInfo and must return false
     * to remove files.
     *
     * @param  Closure $closure An anonymous function
     *
     * @return Symfony\Components\Finder The current Finder instance
     *
     * @see Symfony\Components\Finder\Iterator\CustomFilterIterator
     */
    public function filter(\Closure $closure)
    {
        $this->filters[] = $closure;

        return $this;
    }

    /**
     * Forces the following of symlinks.
     *
     * @return Symfony\Components\Finder The current Finder instance
     */
    public function followLinks()
    {
        $this->followLinks = true;

        return $this;
    }

    /**
     * Searches files and directories which match defined rules.
     *
     * @param  string|array $dirs A directory path or an array of directories
     *
     * @return Symfony\Components\Finder The current Finder instance
     *
     * @throws \InvalidArgumentException if one of the directory does not exist
     */
    public function in($dirs)
    {
        if (!is_array($dirs)) {
            $dirs = array($dirs);
        }

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                throw new \InvalidArgumentException(sprintf('The "%s" directory does not exist.', $dir));
            }
        }

        $this->dirs = array_merge($this->dirs, $dirs);

        return $this;
    }

    /**
     * Returns an Iterator for the current Finder configuration.
     *
     * This method implements the IteratorAggregate interface.
     *
     * @return \Iterator An iterator
     *
     * @throws \LogicException if the in() method has not been called
     */
    public function getIterator()
    {
        if (0 === count($this->dirs)) {
            throw new \LogicException('You must call the in() method before iterating over a Finder.');
        }

        if (1 === count($this->dirs)) {
            return $this->searchInDirectory($this->dirs[0]);
        }

        $iterator = new \AppendIterator();
        foreach ($this->dirs as $dir) {
            $iterator->append($this->searchInDirectory($dir));
        }

        return $iterator;
    }

    protected function searchInDirectory($dir)
    {
        $flags = \FilesystemIterator::SKIP_DOTS;

        if ($this->followLinks) {
            $flags |= \FilesystemIterator::FOLLOW_SYMLINKS;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, $flags), \RecursiveIteratorIterator::SELF_FIRST);

        if ($this->minDepth > 0 || $this->maxDepth < INF) {
            $iterator = new Iterator\LimitDepthFilterIterator($iterator, $this->minDepth, $this->maxDepth);
        }

        if ($this->mode) {
            $iterator = new Iterator\FileTypeFilterIterator($iterator, $this->mode);
        }

        if ($this->exclude) {
            $iterator = new Iterator\ExcludeDirectoryFilterIterator($iterator, $this->exclude);
        }

        if ($this->ignoreVCS) {
            $iterator = new Iterator\IgnoreVcsFilterIterator($iterator);
        }

        if ($this->names || $this->notNames) {
            $iterator = new Iterator\FilenameFilterIterator($iterator, $this->names, $this->notNames);
        }

        if ($this->sizes) {
            $iterator = new Iterator\SizeRangeFilterIterator($iterator, $this->sizes);
        }

        if (false !== $this->minDate || false !== $this->maxDate) {
            $iterator = new Iterator\DateRangeFilterIterator($iterator, $this->minDate, $this->maxDate);
        }

        if ($this->filters) {
            $iterator = new Iterator\CustomFilterIterator($iterator, $this->filters);
        }

        if ($this->sort) {
            $iterator = new Iterator\SortableIterator($iterator, $this->sort);
        }

        return $iterator;
    }
}
