<?php

namespace App\Services;

use App\Helpers\Open_Database;
use PDOException;

/**
 * 远程数据库服务类
 * 提供更高级别的远程数据库操作功能
 */
class RemoteDatabaseService {
    
    /**
     * 临时查询远程MySQL数据库
     * @param array $config 数据库配置
     * @param string $sql SQL查询
     * @param array $params 查询参数
     * @return array 查询结果
     */
    public static function queryRemoteMysql(array $config, string $sql, array $params = []) {
        try {
            $db = Open_Database::getInstance(array_merge(['driver' => 'mysql'], $config));
            return $db->query($sql, $params);
        } catch (PDOException $e) {
            // 记录错误日志并返回错误信息
            \log::error('Remote MySQL query failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 临时查询远程PostgreSQL数据库
     * @param array $config 数据库配置
     * @param string $sql SQL查询
     * @param array $params 查询参数
     * @return array 查询结果
     */
    public static function queryRemotePostgresql(array $config, string $sql, array $params = []) {
        try {
            $db = Open_Database::getInstance(array_merge(['driver' => 'pgsql'], $config));
            return $db->query($sql, $params);
        } catch (PDOException $e) {
            // 记录错误日志并返回错误信息
            \log::error('Remote PostgreSQL query failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 执行远程数据库事务
     * @param array $config 数据库配置
     * @param callable $callback 事务回调函数
     * @return mixed 回调函数的返回值
     */
    public static function transaction(array $config, callable $callback) {
        $db = Open_Database::getInstance($config);
        
        try {
            $db->beginTransaction();
            $result = $callback($db);
            $db->commit();
            return $result;
        } catch (\Exception $e) {
            $db->rollBack();
            \log::error('Remote database transaction failed: ' . $e->getMessage());
            throw $e;
        }
    }
}