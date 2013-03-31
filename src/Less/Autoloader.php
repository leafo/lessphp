<?php

/**
 * Less_Autoloader implements a small PHP 5 autoloader for LessPHP using PEAR naming convention.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class Less_Autoloader
{
	/**
	 * LessHP source directory
	 *
	 * @var string
	 */
	protected $dirname;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->dirname = dirname(dirname(__FILE__));
	}

	/**
	 * Load a LessPHP class dynamically
	 *
	 * @param $class
	 * @return bool
	 */
	public function load($class)
	{
		$file = $this->dirname.'/'.strtr($class, '_', '/').'.php';

		if (file_exists($file)) {
			require $file;
			return true;
		}
	}

	/**
	 * Register the autoloader
	 */
	public function register()
	{
		spl_autoload_register(array($this, 'load'));
	}
}