<?php
/**
 * Part of Windwalker project. 
 *
 * @copyright  Copyright (C) 2014 - 2015 LYRASOFT. All rights reserved.
 * @license    GNU Lesser General Public License version 3 or later.
 */

namespace Windwalker\Database\Driver\Postgresql;

use Windwalker\Database\Command\AbstractTable;
use Windwalker\Database\Schema\Column;
use Windwalker\Database\Schema\Key;
use Windwalker\Query\Mysql\MysqlQueryBuilder;

/**
 * Class PostgresqlTable
 *
 * @since 2.0
 */
class PostgresqlTable extends AbstractTable
{
	/**
	 * A cache to store Table columns.
	 *
	 * @var array
	 */
	protected $columnCache = array();

	/**
	 * Property columns.
	 *
	 * @var  Column[]
	 */
	protected $columns = array();

	/**
	 * Property indexes.
	 *
	 * @var  Key[]
	 */
	protected $indexes = array();

	/**
	 * Property primary.
	 *
	 * @var  array
	 */
	protected $primary = array();

	/**
	 * create
	 *
	 * @param bool  $ifNotExists
	 * @param array $options
	 *
	 * @return  static
	 */
	public function create($ifNotExists = true, $options = array())
	{
		$defaultOptions = array(
			'auto_increment' => 1,
			'engine' => 'InnoDB',
			'default_charset' => 'utf8'
		);

		$options = array_merge($defaultOptions, $options);

		$columns = array();

		foreach ($this->columns as $column)
		{
			$length = $column->getLength();

			$length = $length ? '(' . $length . ')' : null;

			$columns[$column->getName()] = MysqlQueryBuilder::build(
				$column->getType() . $length,
				$column->getSigned() ? '' : 'UNSIGNED',
				$column->getAllowNull() ? '' : 'NOT NULL',
				$column->getDefault() ? 'DEFAULT ' . $this->db->quote($column->getDefault()) : '',
				$column->getAutoIncrement() ? 'AUTO_INCREMENT' : '',
				$column->getComment() ? 'COMMENT ' . $this->db->quote($column->getComment()) : ''
			);
		}

		$keys = array();

		foreach ($this->indexes as $index)
		{
			$keys[$index->getName()] = array(
				'type' => $index->getType(),
				'name' => $index->getName(),
				'columns' => $index->getColumns(),
				'comment' => $index->getComment() ? 'COMMENT ' . $this->db->quote($index->getComment()) : ''
			);
		}

		$this->doCreate($columns, $this->primary, $keys, $options['auto_increment'], $ifNotExists, $options['engine'], $options['default_charset']);

		return $this;
	}

	/**
	 * update
	 *
	 * @return  static
	 */
	public function update()
	{
		foreach ($this->columns as $column)
		{
			$length = $column->getLength();

			$length = $length ? '(' . $length . ')' : null;

			$query = MysqlQueryBuilder::addColumn(
				$this->table,
				$column->getName(),
				$column->getType() . $length,
				$column->getSigned(),
				$column->getAllowNull(),
				$column->getDefault(),
				$column->getPosition(),
				$column->getComment()
			);

			$this->db->setQuery($query)->execute();
		}

		foreach ($this->indexes as $index)
		{
			$query = MysqlQueryBuilder::addIndex(
				$this->table,
				$index->getType(),
				$index->getName(),
				$index->getColumns(),
				$index->getComment()
			);

			$this->db->setQuery($query)->execute();
		}

		return $this;
	}

	/**
	 * save
	 *
	 * @param bool  $ifNotExists
	 * @param array $options
	 *
	 * @return  $this
	 */
	public function save($ifNotExists = true, $options = array())
	{
		if ($this->exists())
		{
			$this->update();
		}
		else
		{
			$this->create($ifNotExists, $options);
		}

		return $this;
	}

	/**
	 * drop
	 *
	 * @param bool   $ifNotExists
	 * @param string $option
	 *
	 * @return  static
	 */
	public function drop($ifNotExists = true, $option = '')
	{
		$query = MysqlQueryBuilder::dropTable($this->table, $ifNotExists, $option);

		$this->db->setQuery($query)->execute();

		return $this;
	}

	/**
	 * reset
	 *
	 * @return  static
	 */
	public function reset()
	{
		$this->columns = array();
		$this->primary = array();
		$this->indexes = array();

		return $this;
	}

	/**
	 * exists
	 *
	 * @return  boolean
	 */
	public function exists()
	{
		$database = $this->db->getDatabase();

		return $database->tableExists($this->table);
	}

	/**
	 * getDetail
	 *
	 * @return  array|boolean
	 */
	public function getDetail()
	{
		return $this->db->getDatabase()->getTableDetail($this->table);
	}

	/**
	 * create
	 *
	 * @param string $columns
	 * @param array  $pks
	 * @param array  $keys
	 * @param int    $autoIncrement
	 * @param bool   $ifNotExists
	 * @param string $engine
	 * @param string $defaultCharset
	 *
	 * @return  $this
	 */
	public function doCreate($columns, $pks = array(), $keys = array(), $autoIncrement = null, $ifNotExists = true,
		$engine = 'InnoDB', $defaultCharset = 'utf8')
	{
		$query = MysqlQueryBuilder::createTable($this->table, $columns, $pks, $keys, $autoIncrement, $ifNotExists, $engine, $defaultCharset);

		$this->db->setQuery($query)->execute();

		return $this;
	}

	/**
	 * addColumn
	 *
	 * @param string $name
	 * @param string $type
	 * @param bool   $signed
	 * @param bool   $allowNull
	 * @param string $default
	 * @param string $comment
	 * @param array  $options
	 *
	 * @return  static
	 */
	public function addColumn($name, $type = 'text', $signed = true, $allowNull = true, $default = '', $comment = '', $options = array())
	{
		$column = $name;

		if (!($column instanceof Column))
		{
			$column = new Column($name, $type, $signed, $allowNull, $default, $comment, $options);
		}

		$type   = MysqlType::getType($column->getType());
		$length = $column->getLength() ? : MysqlType::getLength($type);

		$column->type($type)
			->length($length);

		if ($column->isPrimary())
		{
			$this->primary[] = $column->getName();
		}

		$this->columns[] = $column;

		return $this;
	}

	/**
	 * dropColumn
	 *
	 * @param string $name
	 *
	 * @return  mixed
	 */
	public function dropColumn($name)
	{
		$query = MysqlQueryBuilder::dropColumn($this->table, $name);

		$this->db->setQuery($query)->execute();

		return $this;
	}

	/**
	 * modifyColumn
	 *
	 * @param string|Column $name
	 * @param string $type
	 * @param bool   $signed
	 * @param bool   $allowNull
	 * @param string $default
	 * @param string $comment
	 * @param array  $options
	 *
	 * @return  static
	 */
	public function modifyColumn($name, $type = 'text', $signed = true, $allowNull = true, $default = '', $comment = '', $options = array())
	{
		if ($name instanceof Column)
		{
			$column = $name;
			$length = $column->getLength();
			$length = $length ? '(' . $length . ')' : null;

			$name      = $column->getName();
			$type      = $column->getType() . $length;
			$signed    = $column->getSigned();
			$allowNull = $column->getAllowNull();
			$default   = $column->getDefault();
			$position  = $column->getPosition();
			$comment   = $column->getComment();
		}
		else
		{
			$position = isset($options['position']) ? $options['position'] : null;
		}

		$query = MysqlQueryBuilder::modifyColumn(
			$this->table,
			$name,
			$type,
			$signed,
			$allowNull,
			$default,
			$position,
			$comment
		);

		$this->db->setQuery($query)->execute();

		return $this;
	}

	/**
	 * changeColumn
	 *
	 * @param string $oldName
	 * @param string|Column  $newName
	 * @param string $type
	 * @param bool   $signed
	 * @param bool   $allowNull
	 * @param string $default
	 * @param string $comment
	 * @param array  $options
	 *
	 * @return  static
	 */
	public function changeColumn($oldName, $newName, $type = 'text', $signed = true, $allowNull = true, $default = '', $comment = '', $options = array())
	{
		if ($newName instanceof Column)
		{
			$column = $newName;
			$length = $column->getLength();
			$length = $length ? '(' . $length . ')' : null;

			$newName   = $column->getName();
			$type      = $column->getType() . $length;
			$signed    = $column->getSigned();
			$allowNull = $column->getAllowNull();
			$default   = $column->getDefault();
			$position  = $column->getPosition();
			$comment   = $column->getComment();
		}
		else
		{
			$position = isset($options['position']) ? $options['position'] : null;
		}

		$query = MysqlQueryBuilder::changeColumn(
			$this->table,
			$oldName,
			$newName,
			$type,
			$signed,
			$allowNull,
			$default,
			$position,
			$comment
		);

		$this->db->setQuery($query)->execute();

		return $this;
	}

	/**
	 * addIndex
	 *
	 * @param string       $type
	 * @param string       $name
	 * @param array|string $columns
	 * @param string       $comment
	 * @param array        $options
	 *
	 * @throws  \InvalidArgumentException
	 * @return  mixed
	 */
	public function addIndex($type, $name = null, $columns = array(), $comment = null, $options = array())
	{
		if (!$columns)
		{
			throw new \InvalidArgumentException('No columns given.');
		}

		$columns = (array) $columns;

		$name = $name ? : $columns[0];

		$index = new Key;

		$index->setName($name)
			->setType($type)
			->setColumns($columns)
			->setComment($comment);

		$this->indexes[] = $index;

		return $this;
	}

	/**
	 * dropIndex
	 *
	 * @param string  $type
	 * @param string  $name
	 *
	 * @return  mixed
	 */
	public function dropIndex($type, $name)
	{
		if ($type == Key::TYPE_PRIMARY)
		{
			$name = null;
		}

		$query = MysqlQueryBuilder::dropIndex($this->table, $type, $name);

		$this->db->setQuery($query)->execute();

		return $this;
	}

	/**
	 * rename
	 *
	 * @param string  $newName
	 * @param boolean $returnNew
	 *
	 * @return  $this
	 */
	public function rename($newName, $returnNew = true)
	{
		$this->db->setQuery('RENAME TABLE ' . $this->db->quoteName($this->table) . ' TO ' . $this->db->quoteName($newName));

		$this->db->execute();

		if ($returnNew)
		{
			return $this->db->getTable($newName);
		}

		return $this;
	}

	/**
	 * Locks a table in the database.
	 *
	 * @return  static  Returns this object to support chaining.
	 *
	 * @since   2.0
	 * @throws  \RuntimeException
	 */
	public function lock()
	{
		$this->db->setQuery('LOCK TABLES ' . $this->db->quoteName($this->table) . ' WRITE');

		return $this;
	}

	/**
	 * unlock
	 *
	 * @return  static  Returns this object to support chaining.
	 *
	 * @throws  \RuntimeException
	 */
	public function unlock()
	{
		$this->db->setQuery('UNLOCK TABLES')->execute();

		return $this;
	}

	/**
	 * Method to truncate a table.
	 *
	 * @return  static
	 *
	 * @since   2.0
	 * @throws  \RuntimeException
	 */
	public function truncate()
	{
		$this->db->setQuery('TRUNCATE TABLE ' . $this->db->quoteName($this->table))->execute();

		return $this;
	}

	/**
	 * Get table columns.
	 *
	 * @param bool $refresh
	 *
	 * @return  array Table columns with type.
	 */
	public function getColumns($refresh = false)
	{
		if (empty($this->columnCache) || $refresh)
		{
			$this->columnCache = array_keys($this->getColumnDetails());
		}

		return $this->columnCache;
	}

	/**
	 * getColumnDetails
	 *
	 * @param bool $full
	 *
	 * @return  mixed
	 */
	public function getColumnDetails($full = true)
	{
		$query = MysqlQueryBuilder::showTableColumns($this->table, $full);

		return $this->db->setQuery($query)->loadAll('Field');
	}

	/**
	 * getColumnDetail
	 *
	 * @param string $column
	 * @param bool   $full
	 *
	 * @return  mixed
	 */
	public function getColumnDetail($column, $full = true)
	{
		$query = MysqlQueryBuilder::showTableColumns($this->table, $full, 'Field = ' . $this->db->quote($column));

		return $this->db->setQuery($query)->loadOne();
	}

	/**
	 * getIndexes
	 *
	 * @return  mixed
	 */
	public function getIndexes()
	{
		// Get the details columns information.
		$this->db->setQuery('SHOW KEYS FROM ' . $this->db->quoteName($this->table));

		return $this->db->loadAll();
	}

	/**
	 * Method to get property Primary
	 *
	 * @return  array
	 */
	public function getPrimary()
	{
		return $this->primary;
	}

	/**
	 * Method to set property primary
	 *
	 * @param   array $primary
	 *
	 * @return  static  Return self to support chaining.
	 */
	public function setPrimary($primary)
	{
		$this->primary = (array) $primary;

		return $this;
	}

	/**
	 * Get the details list of sequences for a table.
	 *
	 * @param   string  $table  The name of the table.
	 *
	 * @return  array  An array of sequences specification for the table.
	 *
	 * @since   2.1
	 * @throws  \RuntimeException
	 */
	public function getTableSequences($table)
	{
		// To check if table exists and prevent SQL injection
		$tableList = $this->db->getDatabase()->getTables();

		if ( in_array($table, $tableList) )
		{
			$name = array('s.relname', 'n.nspname', 't.relname', 'a.attname', 'info.data_type',
				'info.minimum_value', 'info.maximum_value', 'info.increment', 'info.cycle_option');

			$as = array('sequence', 'schema', 'table', 'column', 'data_type',
				'minimum_value', 'maximum_value', 'increment', 'cycle_option');

			if (version_compare($this->db->getVersion(), '9.1.0') >= 0)
			{
				$name[] .= 'info.start_value';
				$as[] .= 'start_value';
			}

			// Get the details columns information.
			$query = $this->db->getQuery(true);

			$query->select($this->db->quoteName($name, $as))
				->from('pg_class AS s')
				->leftJoin("pg_depend d ON d.objid=s.oid AND d.classid='pg_class'::regclass AND d.refclassid='pg_class'::regclass")
				->leftJoin('pg_class t ON t.oid=d.refobjid')
				->leftJoin('pg_namespace n ON n.oid=t.relnamespace')
				->leftJoin('pg_attribute a ON a.attrelid=t.oid AND a.attnum=d.refobjsubid')
				->leftJoin('information_schema.sequences AS info ON info.sequence_name=s.relname')
				->where("s.relkind='S' AND d.deptype='a' AND t.relname=" . $this->db->quote($table));

			$this->db->setQuery($query);

			$seq = $this->db->loadAll();

			return $seq;
		}

		return false;
	}
}
