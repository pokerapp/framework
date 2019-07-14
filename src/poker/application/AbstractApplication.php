<?php
/**
 * @copyright (C) 2019 pokerapp.cn
 * @license   https://pokerapp.cn/license
 */
namespace poker\application;

use poker\container\Container;

use function rtrim;
use function dirname;
use function microtime;

/**
 * 抽象应用程序
 *
 * @author Levine <phplevine@gmail.com>
 */
abstract class AbstractApplication
{
	/**
	 * 应用程序实例
	 *
	 * @var \poker\application\AbstractApplication
	 */
	protected static $instance;

	/**
	 * 应用程序开始时间
	 *
	 * @var float
	 */
	protected $startTime;

	/**
	 * 应用程序根路径地址
	 *
	 * @var string
	 */
	protected $rootPath;

	/**
	 * 容器实例
	 *
	 * @var \poker\container\Container
	 */
	protected $container;

	/**
	 * 构造函数
	 *
	 * @param string $rootPath 应用程序根路径地址
	 */
	public function __construct(string $rootPath)
	{
		$this->startTime = microtime(true);
		$this->rootPath  = rtrim($rootPath, '\\/');
		$this->container = Container::getInstance();

		// 应用程序初始化
		$this->initialize();
	}

	/**
	 * 返回应用程序实例
	 *
	 * @param  string                                 $rootPath 应用程序根路径地址
	 * @return \poker\application\AbstractApplication
	 */
	public static function getInstance(string $rootPath = null): AbstractApplication
	{
		if (null === static::$instance) {
			static::$instance = new static($rootPath ?: dirname($_SERVER['DOCUMENT_ROOT']));
		}

		return static::$instance;
	}

	/**
	 * 应用程序初始化
	 */
	protected function initialize(): void
	{
		$this->container->registerInstance([AbstractApplication::class, 'app'], $this);
	}

	/**
	 * 运行应用程序
	 */
	abstract public function run();
}
