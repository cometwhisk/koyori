<?php
/**
 * ChatGPT集成功能主文件
 */
namespace IROChatGPT;

// 安全检查
if (!defined('ABSPATH')) {
    exit;
}

// 加载基本钩子和功能
require_once dirname(__FILE__) . '/hooks.php';
