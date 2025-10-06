<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace App\Helpers;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PhpImap\Mailbox;

class Mailbox_Pop3
{
    private $mailbox;
    private $config;

    public function __construct()
    {
        $this->config = [
            'host' => env('POP3_HOST'),
            'port' => env('POP3_PORT', 995),
            'ssl' => env('POP3_SSL', true),
            'username' => env('POP3_USERNAME'),
            'password' => env('POP3_PASSWORD'),
            'validate_cert' => env('POP3_VALIDATE_CERT', true),
            'attachments_dir' => storage_path('app/attachments/'),
        ];
    }

    private function connect()
    {
        try {
            $sslFlag = $this->config['ssl'] ? '/ssl' : '';
            $validateCert = $this->config['validate_cert'] ? '' : '/novalidate-cert';
            
            $mailbox = new Mailbox(
                "{" . $this->config['host'] . ":" . $this->config['port'] . "/pop3$sslFlag$validateCert}INBOX",
                $this->config['username'],
                $this->config['password'],
                $this->config['attachments_dir']
            );
            
            $this->mailbox = $mailbox;
            return true;
        } catch (Exception $e) {
            Log::error('POP3 connection failed: ' . $e->getMessage());
            return false;
        }
    }

    public function getMails($maxResults = 10)
    {
        if (!$this->connect()) {
            return ['error' => '无法连接到POP3服务器'];
        }
        
        try {
            $mailIds = $this->mailbox->searchMailbox('ALL');
            
            if (!$mailIds) {
                return [];
            }
            
            // 获取最新的邮件
            rsort($mailIds);
            $mailIds = array_slice($mailIds, 0, $maxResults);
            
            $mails = [];
            foreach ($mailIds as $mailId) {
                try {
                    $mail = $this->mailbox->getMail($mailId);
                    
                    $mails[] = [
                        'id' => $mailId,
                        'subject' => $mail->subject,
                        'from' => $this->formatFrom($mail->from),
                        'date' => $mail->date,
                        'body' => $this->getMailBody($mail),
                        'attachments' => $this->formatAttachments($mail->getAttachments()),
                    ];
                } catch (Exception $e) {
                    Log::error("获取邮件 $mailId 失败: " . $e->getMessage());
                }
            }
            
            return $mails;
        } catch (Exception $e) {
            Log::error('获取邮件列表失败: ' . $e->getMessage());
            return ['error' => '获取邮件列表失败'];
        } finally {
            // 关闭连接
            if ($this->mailbox) {
                $this->mailbox->disconnect();
            }
        }
    }

    private function formatFrom($from)
    {
        if (is_array($from) && count($from) > 0) {
            $from = $from[0];
            return "{$from->personal} <{$from->mailbox}@{$from->host}>";
        }
        return '未知发件人';
    }

    private function getMailBody($mail)
    {
        // 优先使用HTML内容
        if (!empty($mail->textHtml)) {
            return $mail->textHtml;
        }
        
        // 否则使用纯文本内容
        return nl2br($mail->textPlain);
    }

    private function formatAttachments($attachments)
    {
        $formatted = [];
        foreach ($attachments as $attachment) {
            $formatted[] = [
                'name' => $attachment->name,
                'size' => $this->formatBytes($attachment->size),
                'path' => $attachment->filePath,
            ];
        }
        return $formatted;
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}    