<?php
/**
 * @copyright (C) 2019 pokerapp.cn
 * @license   https://pokerapp.cn/license
 */
namespace poker\tests;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * 基础测试用例
 *
 * @author Levine <phplevine@gmail.com>
 */
abstract class TestCase extends PHPUnitTestCase
{
	use MockeryPHPUnitIntegration;
}
