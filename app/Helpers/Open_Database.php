<?php

namespace App\Helpers;

use PDO;
use PDOException;

/**
 * 自定义临时访问远程数据库类
 * 支持多种数据库类型的临时连接和查询操作
 */
class Open_Database {
    /**
     * # 示例1：基本查询
    use App\Helpers\Open_Database;

    // 配置远程数据库连接
    $config = [
        'host' => 'remote-host.example.com',
        'port' => 3306,
        'database' => 'remote_db',
        'username' => 'remote_user',
        'password' => 'remote_password',
        'driver' => 'mysql',
        'charset' => 'utf8mb4'
    ];

    try {
        // 获取数据库连接实例
        $db = Open_Database::getInstance($config);
        
        // 执行查询
        $users = $db->query("SELECT * FROM users WHERE status = ? LIMIT 10", [1]);
        
        // 处理结果
        foreach ($users as $user) {
            // 处理用户数据
            echo $user['name'] . PHP_EOL;
        }
        
        // 插入数据
        $affectedRows = $db->execute(
            "INSERT INTO logs (message, created_at) VALUES (?, ?)",
            ['Remote query executed', date('Y-m-d H:i:s')]
        );
        
        echo "Inserted $affectedRows row(s)" . PHP_EOL;
        
    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage() . PHP_EOL;
    }

    // 示例2：使用服务类进行查询
    use App\Services\RemoteDatabaseService;

    try {
        $users = RemoteDatabaseService::queryRemoteMysql($config, "SELECT * FROM users WHERE created_at > ?", [date('Y-m-d', strtotime('-7 days'))]);
        print_r($users);
    } catch (PDOException $e) {
        echo "Service error: " . $e->getMessage() . PHP_EOL;
    }

    // 示例3：事务处理
    use App\Services\RemoteDatabaseService;

    try {
        $result = RemoteDatabaseService::transaction($config, function($db) {
            // 更新用户状态
            $db->execute("UPDATE users SET status = 2 WHERE id = ?", [123]);
            
            // 记录操作日志
            $db->execute("INSERT INTO audit_logs (user_id, action, created_at) VALUES (?, ?, ?)", 
                [123, 'status_updated', date('Y-m-d H:i:s')]
            );
            
            return true;
        });
        
        if ($result) {
            echo "Transaction completed successfully" . PHP_EOL;
        }
    } catch (Exception $e) {
        echo "Transaction failed: " . $e->getMessage() . PHP_EOL;
    }
     **/

    /**
     * PDO连接实例
     * @var PDO
     */
    private $connection;
    
    /**
     * 连接配置
     * @var array
     */
    private $config;
    
    /**
     * 连接超时时间
     * @var int
     */
    protected $timeout = 30;
    
    /**
     * 连接过期时间
     * @var int
     */
    protected $expireTime;
    
    /**
     * 单例实例数组
     * @var array
     */
    static private $_instance = array();
    
    /**
     * 连接标识
     * @var string
     */
    private $key;
    
    /**
     * 构造函数
     * @param array $config 数据库连接配置
     * @param array $options 连接选项
     */
    private function __construct($config = [], $options = array()) {
        // 设置默认配置
        $defaultConfig = [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => '',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ];
        
        // 合并配置
        $this->config = array_merge($defaultConfig, $config);
        $this->timeout = isset($options['timeout']) ? $options['timeout'] : 30;
        
        // 创建数据库连接
        $this->connect();
        
        // 设置过期时间
        $this->expireTime = time() + $this->timeout;
    }
    
    /**
     * 获取单例实例
     * @param array $config 数据库连接配置
     * @param array $options 连接选项
     * @return Open_Database
     */
    public static function getInstance($config = [], $options = array()) {
        // 生成唯一标识
        $key = md5(serialize($config) . serialize($options));
        
        // 检查实例是否存在或已过期
        if (!(static::$_instance[$key] instanceof self) || time() > static::$_instance[$key]->expireTime) {
            static::$_instance[$key] = new self($config, $options);
            static::$_instance[$key]->key = $key;
        }
        
        return static::$_instance[$key];
    }
    
    /**
     * 连接数据库
     * @throws PDOException
     */
    private function connect() {
        $dsn = $this->getDsn();
        $username = $this->config['username'];
        $password = $this->config['password'];
        $options = $this->getPdoOptions();
        
        try {
            $this->connection = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new PDOException('Database connection failed: ' . $e->getMessage(), (int)$e->getCode());
        }
    }
    
    /**
     * 获取DSN字符串
     * @return string
     */
    private function getDsn() {
        $driver = $this->config['driver'];
        
        switch ($driver) {
            case 'mysql':
                $dsn = "mysql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']};charset={$this->config['charset']}";
                break;
            case 'pgsql':
                $dsn = "pgsql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']};user={$this->config['username']};password={$this->config['password']}";
                break;
            case 'sqlite':
                $dsn = "sqlite:{$this->config['database']}";
                break;
            case 'sqlsrv':
                $dsn = "sqlsrv:Server={$this->config['host']},{$this->config['port']};Database={$this->config['database']}";
                break;
            default:
                throw new PDOException("Unsupported database driver: {$driver}");
        }
        
        return $dsn;
    }
    
    /**
     * 获取PDO选项
     * @return array
     */
    private function getPdoOptions() {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        // MySQL特定设置
        if ($this->config['driver'] === 'mysql') {
            if ($this->config['strict']) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET sql_mode='STRICT_ALL_TABLES'";
            }
            
            // SSL选项
            if (!empty($this->config['ssl_ca'])) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $this->config['ssl_ca'];
            }
        }
        
        return $options;
    }
    
    /**
     * 执行查询并返回所有结果
     * @param string $sql SQL查询语句
     * @param array $params 绑定参数
     * @return array 查询结果
     */
    public function query($sql, $params = array()) {
        $this->checkConnection();
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new PDOException('Query failed: ' . $e->getMessage(), (int)$e->getCode());
        }
    }
    
    /**
     * 执行查询并返回单条结果
     * @param string $sql SQL查询语句
     * @param array $params 绑定参数
     * @return array|null 查询结果或null
     */
    public function queryOne($sql, $params = array()) {
        $this->checkConnection();
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new PDOException('Query failed: ' . $e->getMessage(), (int)$e->getCode());
        }
    }
    
    /**
     * 执行非查询SQL语句（插入、更新、删除）
     * @param string $sql SQL语句
     * @param array $params 绑定参数
     * @return int 受影响的行数
     */
    public function execute($sql, $params = array()) {
        $this->checkConnection();
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new PDOException('Execution failed: ' . $e->getMessage(), (int)$e->getCode());
        }
    }
    
    /**
     * 获取最后插入的ID
     * @param string $name 序列名称（仅PostgreSQL需要）
     * @return string
     */
    public function lastInsertId($name = null) {
        $this->checkConnection();
        return $this->connection->lastInsertId($name);
    }
    
    /**
     * 开始事务
     * @return bool
     */
    public function beginTransaction() {
        $this->checkConnection();
        return $this->connection->beginTransaction();
    }
    
    /**
     * 提交事务
     * @return bool
     */
    public function commit() {
        $this->checkConnection();
        return $this->connection->commit();
    }
    
    /**
     * 回滚事务
     * @return bool
     */
    public function rollBack() {
        $this->checkConnection();
        return $this->connection->rollBack();
    }
    
    /**
     * 检查连接是否有效，无效则重新连接
     */
    private function checkConnection() {
        // 检查连接是否已过期或断开
        if (!isset($this->connection) || time() > $this->expireTime) {
            $this->connect();
            $this->expireTime = time() + $this->timeout;
        } else {
            // 尝试执行简单查询以确认连接有效
            try {
                $this->connection->query('SELECT 1');
            } catch (PDOException $e) {
                $this->connect();
                $this->expireTime = time() + $this->timeout;
            }
        }
    }
    
    /**
     * 关闭连接
     */
    public function close() {
        $this->connection = null;
        if (isset($this->key)) {
            unset(static::$_instance[$this->key]);
        }
    }
    
    /**
     * 获取原生PDO连接实例
     * @return PDO
     */
    public function getPdo() {
        $this->checkConnection();
        return $this->connection;
    }
    
    /**
     * 防止克隆
     */
    private function __clone() {}
    
    /**
     * 析构函数，自动关闭连接
     */
    public function __destruct() {
        $this->close();
    }
}