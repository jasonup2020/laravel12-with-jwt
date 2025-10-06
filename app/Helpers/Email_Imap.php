<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

use App\Mail\CustomMail; // 新增：假设已创建自定义邮件类


class Email_Imap {

    protected $imapStream; // IMAP 连接资源
    protected $server;      // IMAP 服务器地址（如 imap.qq.com）
    protected $port;        // 端口（如 993）
    protected $encryption;  // 加密方式（如 ssl）
    protected $username;    // 邮箱账号
    protected $password;    // 密码/授权码

    /**
     * 构造函数：初始化 IMAP 配置
     * @param string $server IMAP 服务器地址（如 imap.outlook.com）
     * @param int $port 端口（如 993）
     * @param string $encryption 加密方式（如 ssl）
     * @param string $username 邮箱账号
     * @param string $password 密码/授权码
     * @param bool $novalidateCert 是否跳过证书验证（默认 false）
     */
    public function __construct(
            string $server,
            int $port,
            string $encryption,
            string $username,
            string $password,
            bool $novalidateCert = false
    ) {
        $this->server = $server;
        $this->port = $port;
        $this->encryption = $encryption;
        $this->username = $username;
        $this->password = $password;
        $this->novalidateCert = $novalidateCert;
    }

    
//    public function setConfig($host="pop.163.com",$port=995,$username="user@163.com",$password="password",$ssl="true") {
//        $this->pop3Config["host"]=$host;
//        $this->pop3Config["port"]=$port;
//        $this->pop3Config["username"]=$username;
//        $this->pop3Config["password"]=$password;
//        $this->pop3Config["ssl"]=$ssl;
//    }
    
    
    /**
     * 连接 IMAP 服务器
     * @return bool 连接成功返回 true，失败返回 false
     */
    public function connect(): bool {
        $serverString = sprintf(
                '{%s:%d/imap/%s%s}INBOX',
                $this->server,
                $this->port,
                $this->encryption,
                $this->novalidateCert ? '/novalidate-cert' : ''
        );

        Log::info('IMAP 连接尝试', [
            'server' => $serverString,
            'username' => $this->username,
            'password' => $this->password
        ]);

        $this->imapStream = imap_open($serverString, $this->username, $this->password);
        if (!$this->imapStream) {
            Log::error('IMAP 连接失败', ['error' => imap_last_error()]);
            return false;
        }
        return true;
    }

    /**
     * 获取邮箱中邮件总数
     * @return int 邮件总数
     */
    public function getMessageCount(): int {
        return imap_num_msg($this->imapStream);
    }

    /**
     * 搜索未读邮件 UID 列表
     * @return array|false 未读邮件 UID 数组（失败返回 false）
     */
    public function searchUnseenMails() {
        return imap_search($this->imapStream, 'UNSEEN', SE_UID);
    }

    /**
     * 获取最近 N 封邮件的 UID 列表
     * @param int $limit 最大数量（默认 5）
     * @return array UID 数组
     */
    public function getRecentUids(int $limit = 5): array {
        $uids = imap_sort($this->imapStream, SORTARRIVAL, 1, SE_UID);
        return is_array($uids) ? array_slice($uids, 0, $limit) : [];
    }

    /**
     * 获取邮件详情（主题、发件人、日期、内容预览）
     * @param string $uid 邮件 UID
     * @return array 邮件详情数组
     */
    public function getMailDetails(string $uid): array {
        $msgNo = imap_msgno($this->imapStream, $uid);
        $headers = imap_headerinfo($this->imapStream, $msgNo);
        $structure = imap_fetchstructure($this->imapStream, $msgNo);

        Log::info("EmailImap->getMailDetails($uid)");
        $content = '';
        if (isset($structure->parts) && count($structure->parts) > 0) {
            foreach ($structure->parts as $index => $part) {
                if (strtolower($part->subtype) === 'plain' && $part->type === 0) {
                    $body = imap_fetchbody($this->imapStream, $msgNo, $index + 1);
                    
                    Log::info("EmailImap->getMailDetails($uid)",["body"=>$body]);
                    $content = $this->decodeBody($body, $part->encoding);
//                    Log::info("EmailImap->getMailDetails($uid)",["decodeBody"=>$content]);
                    break;
                }
            }
        }

        return [
            'uid' => $uid,
            'subject' => $headers->subject ?? '(无主题)',
            'from' => $headers->fromaddress ?? '未知',
            'date' => $headers->date ?? '未知',
            'content' => $content ?? '未知',
            'preview' => substr(strip_tags($content), 0, 100) . '...'
        ];
    }

    /**
     * 标记邮件为已读
     * @param string $uid 邮件 UID
     * @return bool 操作成功返回 true
     */
    public function markAsRead(string $uid): bool {
        return imap_setflag_full($this->imapStream, $uid, '\\Seen', ST_UID);
    }

    /**
     * 获取邮箱文件夹列表
     * @return array 文件夹名称数组
     */
    public function getFolders(): array {
        $folders = imap_list($this->imapStream, $this->getServerString(), '*');
        return array_map(function ($folder) {
            return str_replace($this->getServerString(), '', $folder);
        }, $folders);
    }

    /**
     * 关闭 IMAP 连接
     * @return void
     */
    public function close(): void {
        if ($this->imapStream) {
            imap_close($this->imapStream);
        }
    }

    /**
     * 解码邮件正文内容（处理 base64/quoted-printable 编码）
     * @param string $body 原始内容
     * @param int $encoding 编码类型（3=base64，4=quoted-printable）
     * @return string 解码后的内容
     */
    protected function decodeBody(string $body, int $encoding): string {
        switch ($encoding) {
            case 3:
                return base64_decode($body);
            case 4:
                return quoted_printable_decode($body);
            default:
                return $body;
        }
    }

    /**
     * 获取服务器连接字符串（用于文件夹解析）
     * @return string 服务器连接字符串
     */
    protected function getServerString(): string {
        return sprintf(
                '{%s:%d/imap/%s%s}INBOX',
                $this->server,
                $this->port,
                $this->encryption,
                $this->novalidateCert ? '/novalidate-cert' : ''
        );
    }

     /**
     * 获取最新一封邮件的 UID（即最近接收的邮件）
     * @description 通过 IMAP 协议获取邮箱中最新接收的邮件唯一标识（UID）
     * @return string|false 最新邮件 UID（无邮件时返回 false）
     */
    public function getLatestMailUid(): string|false {
        $recentUids = $this->getRecentUids(1); // 获取最近1封邮件的 UID 列表
        if (empty($recentUids)) {
            Log::warning('当前邮箱无邮件，无法获取最新邮件 UID');
            return false;
        }
        return $recentUids[0]; // 返回最新邮件的 UID
    }








    /**
     * 发送邮件（基于 SMTP 协议）
     * @param string|array $to 收件人邮箱（支持单个或数组）
     * @param string $subject 邮件主题
     * @param string $content 邮件正文（HTML 或纯文本）
     * @param array $attachments 附件路径数组（可选）
     * @return bool 发送成功返回 true
     */
    public function sendMail(string|array $to, string $subject, string $content, array $attachments = []): bool
    {
        try {
            // 验证必要参数
            if (empty($to) || empty($subject) || empty($content)) {
                Log::error('邮件发送失败：缺少必要参数（收件人、主题或正文）');
                return false;
            }

            // 使用 Laravel Mail 发送（需提前在 .env 配置 SMTP）
//            $mail = Mail::to($to)
//                        ->subject($subject)
//                        ->html($content);
            

            // 添加附件
            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $mail->attach($filePath);
                }
            }

            // 记录发送日志
            Log::info('邮件发送请求', [
                'to' => is_array($to) ? implode(',', $to) : $to,
                'subject' => $subject,
                'attachments' => count($attachments)
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('邮件发送失败', [
                'error' => $e->getMessage(),
                'to' => is_array($to) ? implode(',', $to) : $to,
                'subject' => $subject
            ]);
            return false;
        }
    }
    
    
    
    
    
    
    
    /**
     * 发送邮件（基于原生 SMTP 协议，使用 PHPMailer）
     * @param string|array $to 收件人邮箱（支持单个或数组）
     * @param string $subject 邮件主题
     * @param string $content 邮件正文（HTML 或纯文本）
     * @param array $attachments 附件路径数组（可选）
     * @return bool 发送成功返回 true
     */
    public function sendTestMail(string|array $to, string $subject, string $content, array $attachments = []): bool
    {
        try {
            // 验证必要参数
            if (empty($to) || empty($subject) || empty($content)) {
                Log::error('邮件发送失败：缺少必要参数（收件人、主题或正文）');
                return false;
            }

            // 初始化 PHPMailer（启用异常）
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            // SMTP 配置（根据实际邮箱调整，示例为 Outlook/Office365 配置）
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';       // SMTP 服务器（如 Gmail 为 smtp.gmail.com）
            $mail->SMTPAuth   = true;                  // 启用 SMTP 认证
            $mail->Username   = $this->username;        // 邮箱账号（如 user@outlook.com）
            $mail->Password   = $this->password;        // 邮箱密码/授权码
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;  // TLS 加密
            $mail->Port       = 587;                    // SMTP 端口（TLS 通常为 587，SSL 为 465）

            // 发件人信息
            $mail->setFrom($this->username, 'Server Email');  // 发件人邮箱和名称

            // 收件人（支持多个）
            if (is_array($to)) {
                foreach ($to as $recipient) {
                    $mail->addAddress($recipient);
                }
            } else {
                $mail->addAddress($to);
            }
            
            // 邮件内容
            $mail->isHTML(true);                         // 启用 HTML 格式
            $mail->Subject = $subject;
            $mail->Body    = $content;

            // 添加附件
            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $mail->addAttachment($filePath);       // 支持绝对路径或相对路径
                }
            }

            // 发送邮件
            $mail->send();

            // 记录成功日志
            Log::info('邮件发送成功', [
                'to' => is_array($to) ? implode(',', $to) : $to,
                'subject' => $subject,
                'attachments' => count($attachments)
            ]);
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            // 记录详细错误（包含 PHPMailer 原生错误信息）
            Log::error('邮件发送失败', [
                'error' => $mail->ErrorInfo,
                'to' => is_array($to) ? implode(',', $to) : $to,
                'subject' => $subject
            ]);
            return false;
        }
    }



}
