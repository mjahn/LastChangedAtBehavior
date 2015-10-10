<?php

/**
 * @license MIT License
 */

namespace Propel\Generator\Behavior\LastChangedAt;

use Propel\Generator\Model\Behavior;

/**
 * Gives a model class the ability to update the timestamp of 
 * a field named 'last_changed_at' with the latest timestamp 
 * of any related model-class
 *
 * @author Martin Jahn
 */
class LastChangedAtBehavior extends Behavior
{
    protected $parameters = [
        'last_changed_column' => 'last_changed_at',
        'last_changed_at' => ''
    ];


    protected function withLastChangedAt()
    {
        return !$this->booleanValue($this->getParameter('disable_last_changed_at'));
    }

    /**
     * Add the last_changed_at<column to the current table
     */
    public function modifyTable()
    {
        $table = $this->getTable();

        if ($this->withLastChangedAt() && !$table->hasColumn($this->getParameter('last_changed_column'))) {
            $table->addColumn(array(
                'name' => $this->getParameter('last_changed_column'),
                'type' => 'TIMESTAMP'
            ));
        }
    }

    /**
     * Get the setter of the columns of the behavior
     *
     * @param  string $column One of the behavior columns, 'last_changed_at'
     * @return string The related setter, 'setLastChangedOn'
     */
    protected function getColumnSetter($column)
    {
        return 'set' . $this->getColumnForParameter($column)->getPhpName();
    }

    protected function getColumnConstant($columnName, $builder)
    {
        return $builder->getColumnConstant($this->getColumnForParameter($columnName));
    }

    /**
     * Add code in ObjectBuilder::preUpdate
     *
     * @return string The code to put at the hook
     */
    public function preUpdate($builder)
    {
        if ($this->withLastChangedAt()) {
            return "if (\$this->isModified() && !\$this->isColumnModified(" . $this->getColumnConstant('last_changed_column', $builder) . ")) {
    \$this->" . $this->getColumnSetter('last_changed_column') . "(time());
}";
        }

        return '';
    }

    /**
     * Add code in ObjectBuilder::preInsert
     *
     * @return string The code to put at the hook
     */
    public function preInsert($builder)
    {
        $script = '';

        if ($this->withLastChangedAt()) {
            $script .= "
if (!\$this->isColumnModified(" . $this->getColumnConstant('last_changed_column', $builder) . ")) {
    \$this->" . $this->getColumnSetter('last_changed_column') . "(time());
}";
        }

        return $script;
    }

    public function objectMethods($builder)
    {
        if (!$this->withLastChangedAt()) {
            return '';
        }

        return "
/**
 * Mark the current object so that the update date doesn't get updated during next save
 *
 * @return     \$this|" . $builder->getObjectClassName() . " The current object (for fluent API support)
 */
public function keepLastChangedDateUnchanged()
{
    \$this->modifiedColumns[" . $this->getColumnConstant('last_changed_column', $builder) . "] = true;

    return \$this;
}
";
    }

    public function queryMethods($builder)
    {
        $queryClassName = $builder->getQueryClassName();

        $script = '';

        if ($this->withLastChangedAt()) {
            $lastChangedColumnConstant = $this->getColumnConstant('last_changed_column', $builder);
            $script .= "
/**
 * Filter by the latest updated
 *
 * @param      int \$nbDays Maximum age of the latest update in days
 *
 * @return     \$this|$queryClassName The current query, for fluid interface
 */
public function recentlyChanged(\$nbDays = 7)
{
    return \$this->addUsingAlias($lastChangedColumnConstant, time() - \$nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
}

/**
 * Order by update date desc
 *
 * @return     \$this|$queryClassName The current query, for fluid interface
 */
public function lastChangedFirst()
{
    return \$this->addDescendingOrderByColumn($lastChangedColumnConstant);
}

/**
 * Order by update date asc
 *
 * @return     \$this|$queryClassName The current query, for fluid interface
 */
public function firstChangedFirst()
{
    return \$this->addAscendingOrderByColumn($lastChangedColumnConstant);
}
";
        }

        return $script;
    }
}
