<?php

namespace top\library;

use top\library\database\Base;
use top\library\exception\DatabaseException;

/**
 * 数据库操作类
 * @author topnuomi 2018年11月21日
 */
class Database
{

    /**
     * 数据库连接
     * @var Base
     */
    private static $connection = null;

    /**
     * 当前类实例
     * @var array
     */
    private static $instance = [];

    /**
     * 数据库配置
     * @var array
     */
    private $config = [];

    /**
     * 当前操作的表
     * @var string
     */
    private $table = '';

    /**
     * 当前表的主键
     * @var string
     */
    private $pk = '';

    /**
     * 别名
     * @var null
     */
    private $alias = null;

    /**
     * 数据去重
     * @var null
     */
    private $distinct = false;

    /**
     * 操作的字段
     * @var null
     */
    private $field = null;

    /**
     * 条件
     * @var array
     */
    private $where = [];

    /**
     * 排序
     * @var null
     */
    private $order = null;

    /**
     * 范围
     * @var null
     */
    private $limit = null;

    /**
     * 多表
     * @var array
     */
    private $join = [];

    /**
     * Database constructor.
     * @param $table
     * @param $pk
     * @param $prefix
     * @throws DatabaseException
     */
    private function __construct($table, $pk, $prefix)
    {
        // 获取配置
        $this->config = config('db');
        // 当前操作表名
        $this->table = $this->getTableName($prefix, $table);
        // 当前操作表主键
        $this->pk = $pk;
        if (!self::$connection) { // 保证只有一个数据库连接
            // 设置数据库驱动
            $driver = $this->config['driver'] ? $this->config['driver'] : 'MySQLi';
            $class = '\\top\\library\\database\\driver\\' . $driver;
            if (class_exists($class)) {
                // 获取数据库驱动实例
                self::$connection = $class::instance()->connect($this->config);
            } else throw new DatabaseException('不存在的数据库驱动：' . $driver);
        }
    }

    /**
     * 获取表名
     * @param $prefix
     * @param $table
     * @return string
     */
    private function getTableName($prefix, $table)
    {
        // 无前缀
        if ($prefix === false) {
            $tableName = $table;
        } elseif (!$prefix) {
            $tableName = $this->config['prefix'] . $table;
        } else {
            $tableName = $prefix . $table;
        }
        return $tableName;
    }

    /**
     * 指定表
     * @param $table
     * @param string $pk
     * @param string $prefix
     * @return $this
     */
    public static function table($table = '', $pk = '', $prefix = '')
    {
        $ident = $prefix . $table;
        if (!isset(self::$instance[$ident])) {
            self::$instance[$ident] = new self($table, $pk, $prefix);
        }
        return self::$instance[$ident];
    }

    /**
     * 设置表别名
     * @param $name
     * @return \top\library\Database
     */
    public function alias($name)
    {
        $this->alias = $name;
        return $this;
    }

    /**
     * @param $flag
     * @return \top\library\Database
     */
    public function distinct($flag = true)
    {
        $this->distinct = $flag ? true : false;
        return $this;
    }

    /**
     * 设置操作字段
     * @param $field
     * @return \top\library\Database
     */
    public function field($field)
    {
        $this->field = $field;
        return $this;
    }

    /**
     * 设置条件
     * @return \top\library\Database
     */
    public function where()
    {
        $where = func_get_args();
        if (!empty($where)) {
            switch (count($where)) {
                case 3:
                    $this->where[] = [
                        $where[0] => [
                            $where[1],
                            $where[2]
                        ]
                    ];
                    break;
                case 2:
                    $this->where[] = [
                        $where[0] => $where[1]
                    ];
                    break;
                default:
                    $this->where[] = $where[0];
                    break;
            }
        }
        return $this;
    }

    /**
     * 设置排序
     * @return \top\library\Database
     */
    public function order()
    {
        $order = func_get_args();
        if (!empty($order)) {
            if (count($order) > 1) {
                $this->order = $order[0] . ' ' . $order[1];
            } else {
                $this->order = $order[0];
            }
        }
        return $this;
    }

    /**
     * 设置记录范围
     * @return \top\library\Database
     */
    public function limit()
    {
        $limit = func_get_args();
        if (!empty($limit)) {
            if (count($limit) > 1) {
                $this->limit = $limit[0] . ', ' . $limit[1];
            } else {
                $this->limit = $limit[0];
            }
        }
        return $this;
    }

    /**
     * 多表
     *
     * @param $table
     * @param $on
     * @param string $type
     * @return \top\library\Database
     */
    public function join($table, $on, $type = 'INNER')
    {
        $tableName = null;
        if (is_array($table) && isset($table[0]) && isset($table[1])) {
            $tableName = $table[0] . $table[1];
        } else {
            $tableName = $this->config['prefix'] . $table;
        }
        $this->join[] = [
            $tableName,
            $on,
            $type
        ];
        return $this;
    }

    /**
     * 插入记录
     *
     * @param array $data
     * @return int|boolean
     */
    public function insert($data)
    {
        $result = self::$connection->insert($this->table, $data);
        return $result;
    }

    /**
     * 查询一条记录
     * @param bool $param
     * @return mixed
     */
    public function find($param = false)
    {
        (is_callable($param)) && $param($this);
        if (!is_bool($param) && !is_callable($param)) {
            $this->where = array_merge($this->where, [
                [($this->alias ? $this->alias . '.' : '') . $this->getPk() => $param],
            ]);
        }
        $result = self::$connection->find(
            $this->table,
            $this->alias,
            $this->distinct,
            $this->field,
            $this->join,
            $this->where,
            $this->order
        );
        $this->_reset();
        return $result;
    }

    /**
     * 查询所有记录
     *
     * @param callable|string|bool $param
     * @return array|boolean
     */
    public function select($param = false)
    {
        (is_callable($param)) && $param($this);
        if (!is_bool($param) && !is_callable($param)) {
            $this->where = array_merge($this->where, [
                [($this->alias ? $this->alias . '.' : '') . $this->getPk() => $param],
            ]);
        }
        $result = self::$connection->select(
            $this->table,
            $this->alias,
            $this->distinct,
            $this->field,
            $this->join,
            $this->where,
            $this->order,
            $this->limit
        );
        $this->_reset();
        foreach ($result as $k => $v)
            $result[$k] = $v;
        return $result;
    }

    /**
     * 更新记录
     *
     * @param array $data
     * @param callable|string|bool $param
     * @return int|boolean
     */
    public function update($data, $param = false)
    {
        (is_callable($param)) && $param($this);
        if (!is_bool($param) && !is_callable($param)) {
            $this->where = array_merge($this->where, [
                [($this->alias ? $this->alias . '.' : '') . $this->getPk() => $param],
            ]);
        }
        $result = self::$connection->update(
            $this->table,
            $this->alias,
            $this->join,
            $this->where,
            $this->order,
            $this->limit,
            $data
        );
        $this->_reset();
        return $result;
    }

    /**
     * 删除记录
     *
     * @param callable|string|bool $param
     * @return int|boolean
     */
    public function delete($param = false)
    {
        (is_callable($param)) && $param($this);
        if (!is_bool($param) && !is_callable($param)) {
            $this->where = array_merge($this->where, [
                [($this->alias ? $this->alias . '.' : '') . $this->getPk() => $param],
            ]);
        }
        $result = self::$connection->delete(
            $this->table,
            $this->alias,
            $this->join,
            $this->where,
            $this->order,
            $this->limit
        );
        $this->_reset();

        return $result;
    }

    /**
     * 公共方法 （sum、avg等等使用函数包裹字段的方法）
     *
     * @param $param
     * @param $type
     * @return mixed
     */
    public function common($param, $type)
    {
        (is_callable($param)) && $param($this);
        if (empty($this->field) && $param && !is_callable($param)) {
            $this->field = $param;
        }
        $result = self::$connection->common(
            $this->table,
            $this->alias,
            $this->field,
            $this->join,
            $this->where,
            $type
        );
        $this->_reset();

        return $result;
    }

    /**
     * 执行一条SQL
     * @param $query
     * @param array $params
     * @return bool|\PDOStatement
     */
    public function query($query, $params = [])
    {
        return self::$connection->query($query, $params);
    }

    /**
     * 开启事务
     */
    public function begin()
    {
        self::$connection->begin();
    }

    /**
     * 提交
     */
    public function commit()
    {
        self::$connection->commit();
    }

    /**
     * 回滚
     */
    public function rollback()
    {
        self::$connection->rollback();
    }

    /**
     * 返回PDO
     * @return \PDO
     */
    public function getPDO()
    {
        return self::$connection->getPDO();
    }

    /**
     * 获取最后执行的SQL语句
     *
     * @return string
     */
    public function sql()
    {
        return self::$connection->sql();
    }

    /**
     * 重置查询条件
     */
    private function _reset()
    {
        $this->distinct = false;
        $this->field = null;
        $this->join = [];
        $this->where = [];
        $this->order = null;
        $this->limit = null;
    }

    /**
     * 获取主键
     *
     * @return string
     */
    private function getPk()
    {
        if (!$this->pk) {
            $pk = self::$connection->getPk($this->table, $this->config['dbname']);
            return ($pk) ? $pk : 'id';
        }
        return $this->pk;
    }

    private function __clone()
    {
    }

}
