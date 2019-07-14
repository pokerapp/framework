<?php
/**
 * @copyright (C) 2019 pokerapp.cn
 * @license   https://pokerapp.cn/license
 */
namespace poker\container\traits;

use RuntimeException;
use poker\container\Container;

use function vsprintf;

/**
 * 容器识别trait
 *
 * @author Levine <phplevine@gmail.com>
 *
 * @property \poker\application\AbstractApplication $app
 */
trait ContainerAwareTrait
{
	/**
	 * 容器实例
	 *
	 * @var \poker\container\Container
	 */
	protected $container;

	/**
	 * 已解析对象的数组或对已解析对象的引用
	 *
	 * @var array
	 */
	protected $resolved = [];

	/**
	 * 设置容器实例
	 *
	 * @param \poker\container\Container $container 容器实例
	 */
	public function setContainer(Container $container): void
	{
		$this->container = $container;
	}

	/**
	 * 使用魔术方法从容器中解析项目
	 *
	 * @param  string            $key
	 * @throws \RuntimeException
	 * @return mixed
	 */
	public function __get(string $key)
	{
		if (isset($this->resolved[$key])) {
			return $this->resolved[$key];
		}

		if ($this->container->has($key) === false) {
			throw new RuntimeException(vsprintf('Unable to resolve [ %s ].', [$key]));
		}

		$resolved = $this->container->get($key);

		if ($this->container->isSingleton($key) === false) {
			return $resolved;
		}

		return $this->resolved[$key] = $resolved;
	}
}
