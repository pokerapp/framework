<?php
/**
 * @copyright (C) 2019 pokerapp.cn
 * @license   https://pokerapp.cn/license
 */
namespace poker\application;

/**
 * 抽象应用程序
 *
 * @author Levine <phplevine@gmail.com>
 */
abstract class AbstractApplication
{
	/**
	 * 运行应用程序
	 */
	abstract public function run();
}
