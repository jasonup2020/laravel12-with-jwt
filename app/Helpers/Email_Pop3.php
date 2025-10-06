<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Helpers;
use Illuminate\Support\Facades\Mail;
/**
 * Description of EmailPop3
 *
 * @author Administrator
 */
class Email_Pop3 {
    //put your code here
    
    /**
     * POP3 连接配置（示例，实际从数据库或配置文件获取用户的 POP3 信息）
     * @var array
     */
    protected $pop3Config = [
        'host' => 'pop.163.com',    // POP3 服务器地址（如 163: pop.163.com，QQ: pop.qq.com）
        'port' => 995,              // 端口（SSL 通常为 995）
        'username' => 'user@163.com',// 用户邮箱
        'password' => 'password',   // 邮箱密码/授权码
        'ssl' => true               // 是否启用 SSL
    ];

    
    
    public function setConfig($host="pop.163.com",$port=995,$username="user@163.com",$password="password",$ssl="true") {
        $this->pop3Config["host"]=$host;
        $this->pop3Config["port"]=$port;
        $this->pop3Config["username"]=$username;
        $this->pop3Config["password"]=$password;
        $this->pop3Config["ssl"]=$ssl;
    }
    
    
    public function getConfig() {
        return $this->pop3Config;
    }
    
    /**
     * 通过 POP3 协议获取收件箱邮件列表
     * @return array 邮件 ID 数组（用于后续获取具体邮件）
     */
    public function getPop3EmailList(): array
    {
        $connection = $this->connectPop3Server();
        
        
        
        
        print_r(["getPop3EmailList->connection"=>$connection]);
        
        if (!$connection) {
            return ['error' => 'POP3 连接失败'];
        }

        $emailCount = imap_num_msg($connection);
        $emailIds = [];
        for ($i = 1; $i <= $emailCount; $i++) {
            $emailIds[] = imap_uid($connection, $i); // 使用 UID 作为唯一标识
        }

        imap_close($connection);
        return $emailIds;
    }

    
    
    public function getPop3() {
        $hostname = '{imap.mailtrap.io:143/imap}';
        $username = 'your-email@example.com';
        $password = 'your-password';

        /* 打开 IMAP 连接 */
        $inbox = imap_open($hostname,$username,$password) or die('Cannot connect to Mail: ' . imap_last_error());

        /* 获取邮件数量 */
        $emailsCount = imap_num_msg($inbox);

        for($i = 1; $i <= $emailsCount; $i++) {
            $header = imap_headerinfo($inbox, $i);
            $overview = imap_fetch_overview($inbox, $i, 0);
            echo "Email number $i Date: " . $overview[0]->date . '<br>';
            echo "Subject: " . $header->subject . '<br>';
            echo "From: " . $header->from[0]->mailbox . '@' . $header->from[0]->host . '<br>';
            echo "To: " . $header->to[0]->mailbox . '@' . $header->to[0]->host . '<br><br>';
        }

        /* 关闭连接 */
        imap_close($inbox);
    }
    /**
     * 通过 POP3 获取单封邮件详情（并解析为数据库需要的格式）
     * @param string $uid 邮件 UID
     * @return array 解析后的邮件数据（可直接插入 emails 表）
     */
    public function getPop3Message(string $uid): array
    {
        $connection = $this->connectPop3Server();
        if (!$connection) {
            return ['error' => 'POP3 连接失败'];
        }

        $msgNumber = imap_msgno($connection, $uid); // 通过 UID 获取邮件序号
        $header = imap_headerinfo($connection, $msgNumber);
        $body = $this->parsePop3Body($connection, $msgNumber);

        // 解析发件人
        $fromEmail = $header->from[0]->mailbox . '@' . $header->from[0]->host;

        // 解析收件人（可能多个）
        $toEmails = [];
        if (!empty($header->to)) {
            foreach ($header->to as $to) {
                $toEmails[] = $to->mailbox . '@' . $to->host;
            }
        }

        imap_close($connection);

        return [
            'email_id' => $uid,
            'provider' => 'pop3', // 标记为 POP3 来源
            'from_email' => $fromEmail,
            'to_emails' => $toEmails,
            'subject' => imap_utf8($header->subject), // 处理乱码
            'content' => $body,
            'sent_at' => $header->date,
            'is_read' => $header->seen // 是否已读（imap 中 seen 标记表示已读）
        ];
    }

    /**
     * 连接 POP3 服务器（私有方法）
     * @return resource|false 连接资源或 false（失败）
     */
    private function connectPop3Server()
    {
        $host = $this->pop3Config['host'];
        $port = $this->pop3Config['port'];
        $ssl = $this->pop3Config['ssl'] ? '/ssl' : '';
        $connectionString = "{$host}:{$port}/pop3{$ssl}/novalidate-cert"; // novalidate-cert 跳过证书验证（测试用）

        return imap_open("{{$connectionString}}INBOX", 
            $this->pop3Config['username'], 
            $this->pop3Config['password']);
    }

    /**
     * 解析 POP3 邮件正文（处理多部分 MIME 内容）
     * @param resource $connection IMAP 连接资源
     * @param int $msgNumber 邮件序号
     * @return string 解析后的正文（优先 HTML，无则取纯文本）
     */
    private function parsePop3Body($connection, int $msgNumber): string
    {
        $structure = imap_fetchstructure($connection, $msgNumber);
        $body = '';

        if ($structure->type == TYPEMULTIPART) {
            // 多部分邮件（HTML + 纯文本）
            $htmlPart = imap_fetchbody($connection, $msgNumber, '1.2'); // HTML 通常在 1.2 部分
            $textPart = imap_fetchbody($connection, $msgNumber, '1.1'); // 纯文本通常在 1.1 部分

            if (!empty($htmlPart)) {
                $body = imap_utf8($htmlPart);
            } elseif (!empty($textPart)) {
                $body = imap_utf8($textPart);
            }
        } else {
            // 单部分邮件
            $body = imap_utf8(imap_body($connection, $msgNumber));
        }

        return $body;
    }

    /**
     * 动态设置POP3配置（新增方法）
     */
    public function setPop3Config(array $config): void
    {
        $this->pop3Config = array_merge($this->pop3Config, $config);
    }
}
