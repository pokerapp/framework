<?php
/**
 * 单元测试引导文件
 *
 * @copyright (C) 2019 pokerapp.cn
 * @license   https://pokerapp.cn/license
 * @author    Levine <phplevine@gmail.com>
 */
error_reporting(E_ALL | E_STRICT);

date_default_timezone_set('PRC');

setlocale(LC_ALL, 'C');
mb_language('uni');
mb_regex_encoding('UTF-8');
mb_internal_encoding('UTF-8');

require_once dirname(__DIR__) . '/vendor/autoload.php';
