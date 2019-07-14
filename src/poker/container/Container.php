<?php
/**
 * @copyright (C) 2019 pokerapp.cn
 * @license   https://pokerapp.cn/license
 */
namespace poker\container;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionParameter;
use poker\container\traits\ContainerAwareTrait;
use poker\container\exceptions\ContainerException;
use poker\container\exceptions\UnableToInstantiateException;
use poker\container\exceptions\UnableToResolveParameterException;

use function is_int;
use function vsprintf;
use function is_array;
use function array_merge;
use function array_replace;
use function array_values;

/**
 * 控制容器的反转
 *
 * @author Levine <phplevine@gmail.com>
 */
class Container
{
	/**
	 * 容器自身单例实例
	 *
	 * @var \poker\container\Container
	 */
	protected static $instance;

	/**
	 * 注册的类型提示
	 *
	 * @var array
	 */
	protected $hints = [];

	/**
	 * 别名
	 *
	 * @var array
	 */
	protected $aliases = [];

	/**
	 * 单例实例
	 *
	 * @var array
	 */
	protected $instances = [];

	/**
	 * 实例替换程序
	 *
	 * @var array
	 */
	protected $replacers = [];

	/**
	 * 上下文依赖关系
	 *
	 * @var array
	 */
	protected $dependencies = [];

	/**
	 * 返回容器自身单例实例
	 *
	 * @return \poker\container\Container
	 */
	public static function getInstance()
	{
		if (null === static::$instance) {
			static::$instance = new static;
		}

		return static::$instance;
	}

	/**
	 * 检查是否在容器中注册了类
	 *
	 * @param  string $class 类名
	 * @return bool
	 */
	public function has(string $class): bool
	{
		$class = $this->resolveAlias($class);

		return (isset($this->hints[$class]) || isset($this->instances[$class]));
	}

	/**
	 * 如果一个类已注册为单例则返回TRUE，否则返回FALSE
	 *
	 * @param  string $class 类名
	 * @return bool
	 */
	public function isSingleton(string $class): bool
	{
		$class = $this->resolveAlias($class);

		return isset($this->instances[$class]) || (isset($this->hints[$class]) && $this->hints[$class]['singleton'] === true);
	}

	/**
	 * 即使该类已注册为单例，也返回一个新的类实例
	 *
	 * @param  string $class      类名
	 * @param  array  $parameters 构造函数参数
	 * @return object
	 */
	public function getFresh(string $class, array $parameters = []): object
	{
		return $this->get($class, $parameters, false);
	}

	/**
	 * 注册一个类型提示
	 *
	 * @param string|array    $hint      包含类型提示和别名的类型提示或数组
	 * @param string|\Closure $class     类名或闭包
	 * @param bool            $singleton 是否每次都应该返回相同的实例
	 */
	public function register($hint, $class, bool $singleton = false): void
	{
		$this->hints[$this->parseHint($hint)] = ['class' => $class, 'singleton' => $singleton];
	}

	/**
	 * 注册一个类型提示并每次返回相同的实例
	 *
	 * @param string|array    $hint  包含类型提示和别名的类型提示或数组
	 * @param string|\Closure $class 类名或闭包
	 */
	public function registerSingleton($hint, $class): void
	{
		$this->register($hint, $class, true);
	}

	/**
	 * 注册一个单例实例
	 *
	 * @param string|array $hint     包含类型提示和别名的类型提示或数组
	 * @param object       $instance 类实例
	 */
	public function registerInstance($hint, object $instance): void
	{
		$this->instances[$this->parseHint($hint)] = $instance;
	}

	/**
	 * 替换一个已注册的类型提示
	 *
	 * @param  string                                         $hint      类型提示
	 * @param  string|\Closure                                $class     类名或闭包
	 * @param  bool                                           $singleton 是否替换一个单例
	 * @throws \poker\container\exceptions\ContainerException
	 */
	public function replace(string $hint, $class, bool $singleton = false): void
	{
		$hint = $this->resolveAlias($hint);

		if (!isset($this->hints[$hint])) {
			throw new ContainerException(vsprintf('Unable to replace [ %s ] as it hasn\'t been registered.', [$hint]));
		}

		$this->hints[$hint]['class'] = $class;

		if ($singleton) {
			unset($this->instances[$hint]);
		}

		$this->replaceInstances($hint);
	}

	/**
	 * 注册替换程序
	 *
	 * @param string      $hint      类型提示
	 * @param callable    $replacer  实例替换程序
	 * @param string|null $eventName 事件名称
	 */
	public function onReplace(string $hint, callable $replacer, ?string $eventName = null): void
	{
		$hint = $this->resolveAlias($hint);

		$eventName === null ? ($this->replacers[$hint][] = $replacer) : ($this->replacers[$hint][$eventName] = $replacer);
	}

	/**
	 * 替换一个已注册的单例类型提示
	 *
	 * @param string          $hint  类型提示
	 * @param string|\Closure $class 类名或闭包
	 */
	public function replaceSingleton(string $hint, $class): void
	{
		$this->replace($hint, $class, true);
	}

	/**
	 * 替换一个单例实例
	 *
	 * @param  string                                         $hint     类型提示
	 * @param  object                                         $instance 类实例
	 * @throws \poker\container\exceptions\ContainerException
	 */
	public function replaceInstance(string $hint, object $instance): void
	{
		$hint = $this->resolveAlias($hint);

		if (!isset($this->instances[$hint])) {
			throw new ContainerException(vsprintf('Unable to replace [ %s ] as it hasn\'t been registered.', [$hint]));
		}

		$this->instances[$hint] = $instance;

		$this->replaceInstances($hint);
	}

	/**
	 * 注册一个上下文依赖关系
	 *
	 * @param string $class          类
	 * @param string $interface      接口
	 * @param string $implementation 实现
	 */
	public function registerContextualDependency(string $class, string $interface, string $implementation): void
	{
		$this->dependencies[$class][$interface] = $implementation;
	}

	/**
	 * 返回一个类实例
	 *
	 * @param  string $class         类名
	 * @param  array  $parameters    构造函数参数
	 * @param  bool   $reuseInstance 是否重用现有实例
	 * @return object
	 */
	public function get(string $class, array $parameters = [], bool $reuseInstance = true): object
	{
		$class = $this->resolveAlias($class);

		// 如果存在单例实例，直接返回它
		if ($reuseInstance && isset($this->instances[$class])) {
			return $this->instances[$class];
		}

		// 创建新实例
		$instance = $this->factory($this->resolveHint($class), $parameters);

		// 如果实例注册为单例，则存储该实例
		if ($reuseInstance && isset($this->hints[$class]) && $this->hints[$class]['singleton']) {
			$this->instances[$class] = $instance;
		}

		// 返回的实例
		return $instance;
	}

	/**
	 * 工厂创建一个类实例
	 *
	 * @param  string|\Closure $class      类名或闭包
	 * @param  array           $parameters 构造函数参数
	 * @return object
	 */
	public function factory($class, array $parameters = []): object
	{
		// 实例化类
		if ($class instanceof Closure) {
			$instance = $this->closureFactory($class, $parameters);
		} else {
			$instance = $this->reflectionFactory($class, $parameters);
		}

		// 如果类可以识别容器，则使用setter注入容器
		if ($this->isContainerAware($instance)) {
			$instance->setContainer($this);
		}

		// 返回的实例
		return $instance;
	}

	/**
	 * 执行可调用并注入其依赖项
	 *
	 * @param  callable $callable   可调用
	 * @param  array    $parameters 参数
	 * @return mixed
	 */
	public function call(callable $callable, array $parameters = [])
	{
		if (is_array($callable)) {
			$reflection = new ReflectionMethod($callable[0], $callable[1]);
		} else {
			$reflection = new ReflectionFunction($callable);
		}

		return $callable(...$this->resolveParameters($reflection->getParameters(), $parameters));
	}

	/**
	 * 使用工厂闭包创建一个类实例
	 *
	 * @param  \Closure $factory    类名或闭包
	 * @param  array    $parameters 构造函数参数
	 * @return object
	 */
	protected function closureFactory(Closure $factory, array $parameters): object
	{
		// 将容器作为第一个参数传递，然后传递所提供的参数
		return $factory(...array_merge([$this], $parameters));
	}

	/**
	 * 使用反射创建一个类实例
	 *
	 * @param  string                                                   $class      类名
	 * @param  array                                                    $parameters 构造函数参数
	 * @throws \poker\container\exceptions\UnableToInstantiateException
	 * @return object
	 */
	protected function reflectionFactory(string $class, array $parameters): object
	{
		$class = new ReflectionClass($class);

		// 检查是否可以实例化该类
		if (!$class->isInstantiable()) {
			throw new UnableToInstantiateException(vsprintf('Unable to create a [ %s ] instance.', [$class->getName()]));
		}

		// 获取类的构造函数
		$constructor = $class->getConstructor();

		// 如果没有构造函数，只返回一个新实例
		if ($constructor === null) {
			return $class->newInstance();
		}

		// 该类有一个构造函数，因此将使用解析的参数返回一个新实例
		return $class->newInstanceArgs($this->resolveParameters($constructor->getParameters(), $parameters, $class));
	}

	/**
	 * 检查类是否可识别容器
	 *
	 * @param  object $class 类实例
	 * @return bool
	 */
	protected function isContainerAware(object $class): bool
	{
		$traits = ClassInspector::getTraits($class);

		return isset($traits[ContainerAwareTrait::class]);
	}

	/**
	 * 解析一个类型提示
	 *
	 * @param  string          $hint 类型提示
	 * @return string|\Closure
	 */
	protected function resolveHint(string $hint)
	{
		return $this->hints[$hint]['class'] ?? $hint;
	}

	/**
	 * 根据别名返回名称。如果不存在别名只返回收到的值
	 *
	 * @param  string $alias 别名
	 * @return string
	 */
	protected function resolveAlias(string $alias): string
	{
		return $this->aliases[$alias] ?? $alias;
	}

	/**
	 * 解析一个上下文依赖关系
	 *
	 * @param  string $class     类
	 * @param  string $interface 接口
	 * @return string
	 */
	protected function resolveContextualDependency(string $class, string $interface): string
	{
		return $this->dependencies[$class][$interface] ?? $interface;
	}

	/**
	 * 解析提示参数
	 *
	 * @param  string|array $hint 包含类型提示和别名的类型提示或数组
	 * @return string
	 */
	protected function parseHint($hint): string
	{
		if (is_array($hint)) {
			[$hint, $alias] = $hint;

			$this->aliases[$alias] = $hint;
		}

		return $hint;
	}

	/**
	 * 解析一个参数
	 *
	 * @param  \ReflectionParameter                                          $parameter 反射参数实例
	 * @param  \ReflectionClass|null                                         $class     反射类实例
	 * @throws \poker\container\exceptions\UnableToInstantiateException
	 * @throws \poker\container\exceptions\UnableToResolveParameterException
	 * @return mixed
	 */
	protected function resolveParameter(ReflectionParameter $parameter, ?ReflectionClass $class = null)
	{
		// 如果参数是类实例，将尝试使用容器解析它
		if (($parameterClass = $parameter->getClass()) !== null) {
			$parameterClassName = $parameterClass->getName();

			if ($class !== null) {
				$parameterClassName = $this->resolveContextualDependency($class->getName(), $parameterClassName);
			}

			try {
				return $this->get($parameterClassName);
			} catch(UnableToInstantiateException | UnableToResolveParameterException $e) {
				if ($parameter->allowsNull()) {
					return null;
				}

				throw $e;
			}
		}

		// 如果参数有一个默认值，将使直接使用它
		if ($parameter->isDefaultValueAvailable()) {
			return $parameter->getDefaultValue();
		}

		// 该参数可以为空，因此只返回null
		if ($parameter->hasType() && $parameter->allowsNull()) {
			return null;
		}

		// 如果上述条件都不成立，则抛出异常消息
		throw new UnableToResolveParameterException(vsprintf('Unable to resolve the [ $%s ] parameter of [ %s ].', [$parameter->getName(), $this->getDeclaringFunction($parameter)]));
	}

	/**
	 * 解析多个参数
	 *
	 * @param  array                 $reflectionParameters 反射参数
	 * @param  array                 $providedParameters   提供的参数
	 * @param  \ReflectionClass|null $class                反射类实例
	 * @return array
	 */
	protected function resolveParameters(array $reflectionParameters, array $providedParameters, ?ReflectionClass $class = null): array
	{
		if (empty($reflectionParameters)) {
			return array_values($providedParameters);
		}

		// 将提供的参数与使用反射得到的参数合并
		$parameters = $this->mergeParameters($reflectionParameters, $providedParameters);

		// 循环遍历参数并处理需要解析的参数
		foreach ($parameters as $key => $parameter) {
			if ($parameter instanceof ReflectionParameter) {
				$parameters[$key] = $this->resolveParameter($parameter, $class);
			}
		}

		// 返回解析的参数
		return array_values($parameters);
	}

	/**
	 * 将提供的参数与反射参数合并
	 *
	 * @param  array $reflectionParameters 反射参数
	 * @param  array $providedParameters   提供的参数
	 * @return array
	 */
	protected function mergeParameters(array $reflectionParameters, array $providedParameters): array
	{
		// 使反射参数数组关联
		$associativeReflectionParameters = [];
		foreach ($reflectionParameters as $value) {
			$associativeReflectionParameters[$value->getName()] = $value;
		}

		// 使提供的参数数组关联
		$associativeProvidedParameters = [];
		foreach ($providedParameters as $key => $value) {
			if (is_int($key)) {
				$associativeProvidedParameters[$reflectionParameters[$key]->getName()] = $value;
			} else {
				$associativeProvidedParameters[$key] = $value;
			}
		}

		// 返回合并后的参数
		return array_replace($associativeReflectionParameters, $associativeProvidedParameters);
	}

	/**
	 * 返回声明函数的名称
	 *
	 * @param  \ReflectionParameter $parameter 反射参数实例
	 * @return string
	 */
	protected function getDeclaringFunction(ReflectionParameter $parameter): string
	{
		$declaringFunction = $parameter->getDeclaringFunction();

		if ($declaringFunction->isClosure()) {
			return 'Closure';
		}

		if (($class = $parameter->getDeclaringClass()) === null) {
			return $declaringFunction->getName();
		}

		return $class->getName() . '::' . $declaringFunction->getName();
	}

	/**
	 * 替换以前解析的实例
	 *
	 * @param string $hint 类型提示
	 */
	protected function replaceInstances(string $hint): void
	{
		if (isset($this->replacers[$hint])) {
			$instance = $this->get($hint);

			foreach ($this->replacers[$hint] as $replacer) {
				$replacer($instance);
			}
		}
	}
}
