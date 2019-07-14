<?php
/**
 * @copyright (C) 2019 pokerapp.cn
 * @license   https://pokerapp.cn/license
 */
namespace poker\container;

use function array_pop;
use function class_uses;
use function get_parent_class;

/**
 * 类检查器
 *
 * @author Levine <phplevine@gmail.com>
 */
class ClassInspector
{
	/**
	 * 返回一个类使用的所有trait的数组
	 *
	 * @param  string|object $class    类名或类实例
	 * @param  bool          $autoload 是否自动加载
	 * @return array
	 */
	public static function getTraits($class, bool $autoload = true): array
	{
		// 获取类及其父类使用的所有trial
		$traits = [];
		do {
			$traits += class_uses($class, $autoload);
		} while($class = get_parent_class($class));

		// 寻找所有的trait
		$search   = $traits;
		$searched = [];
		while (!empty($search)) {
			$trait = array_pop($search);

			if (isset($searched[$trait])) {
				continue;
			}

			$traits += $search += class_uses($trait, $autoload);

			$searched[$trait] = $trait;
		}

		// 返回该类使用的trait的完整列表
		return $traits;
	}
}
