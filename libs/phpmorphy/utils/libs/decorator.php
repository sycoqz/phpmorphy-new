<?php

// ////////////////////////////////////////
// lmbDecorator.class.php
// ////////////////////////////////////////
/*
 * Limb PHP Framework
 *
 * @link http://limb-project.com
 * @copyright  Copyright &copy; 2004-2007 BIT(http://bit-creative.com)
 * @license    LGPL http://www.gnu.org/copyleft/lesser.html
 */
// lmb_require('limb/core/src/lmbDecoratorGenerator.class.php');

/**
 * class lmbDecorator.
 *
 * @version $Id$
 */
class lmbDecorator
{
    protected $original;

    public static function generate($class, $decorator_class = null)
    {
        $generator = new lmbDecoratorGenerator;

        return $generator->generate($class, $decorator_class);
    }

    public function __construct($original)
    {
        $this->original = $original;
    }

    protected function ___invoke($method, $args = [])
    {
        return call_user_func_array([$this->original, $method], $args);
    }
}

// ////////////////////////////////////////
// lmbDecoratorGenerator.class.php
// ////////////////////////////////////////
/*
 * Limb PHP Framework
 *
 * @link http://limb-project.com
 * @copyright  Copyright &copy; 2004-2007 BIT(http://bit-creative.com)
 * @license    LGPL http://www.gnu.org/copyleft/lesser.html
 */
// code is based on MockGenerator class from SimpleTest test suite
// lmb_require('limb/core/src/lmbReflectionHelper.class.php');

/**
 * class lmbDecoratorGenerator.
 *
 * @version $Id$
 */
class lmbDecoratorGenerator
{
    protected $_class;

    protected $_decorator_class;

    protected $_decorator_base;

    public function generate($class, $decorator_class = null, $decorator_base = 'lmbDecorator')
    {
        $this->_class = $class;

        if (is_null($decorator_class)) {
            $this->_decorator_class = $class.'Decorator';
        } else {
            $this->_decorator_class = $decorator_class;
        }

        $this->_decorator_base = $decorator_base;

        if (class_exists($this->_decorator_class)) {
            return false;
        }

        $methods = [];

        return eval($this->_createClassCode().' return true;');
    }

    protected function _createClassCode()
    {
        $implements = '';
        $interfaces = lmbReflectionHelper::getInterfaces($this->_class);
        if (function_exists('spl_classes')) {
            $interfaces = array_diff($interfaces, ['Traversable']);
        }

        if (count($interfaces) > 0) {
            $implements = 'implements '.implode(', ', $interfaces);
        }

        $code = 'class '.$this->_decorator_class.' extends '.$this->_decorator_base." $implements {\n";
        $code .= "    function __construct(\$original) {\n";
        $code .= "        parent :: __construct(\$original);\n";
        $code .= "    }\n";
        $code .= $this->_createHandlerCode();
        $code .= "}\n";

        return $code;
    }

    protected function _createHandlerCode()
    {
        $code = '';
        $methods = lmbReflectionHelper::getMethods($this->_class);
        $base_methods = lmbReflectionHelper::getMethods($this->_decorator_base);
        foreach ($methods as $method) {
            if ($this->_isMagicMethod($method)) {
                continue;
            }

            if (in_array($method, $base_methods)) {
                continue;
            }

            $code .= '    '.lmbReflectionHelper::getSignature($this->_class, $method)." {\n";
            $code .= "        \$args = func_get_args();\n";
            $code .= "        return \$this->___invoke(\"$method\", \$args);\n";
            $code .= "    }\n";
        }

        return $code;
    }

    protected function _isMagicMethod($method)
    {
        return in_array(strtolower($method), ['__construct', '__destruct', '__clone']);
    }
}

// ////////////////////////////////////////
// lmbReflectionHelper.class.php
// ////////////////////////////////////////
/*
 * Limb PHP Framework
 *
 * @link http://limb-project.com
 * @copyright  Copyright &copy; 2004-2007 BIT(http://bit-creative.com)
 * @license    LGPL http://www.gnu.org/copyleft/lesser.html
 */

/**
 * class lmbReflectionHelper.
 *
 * @version $Id$
 */
class lmbReflectionHelper
{
    public static function getMethods($name)
    {
        return array_unique(get_class_methods($name));
    }

    public static function getInterfaces($name)
    {
        $reflection = new ReflectionClass($name);
        if ($reflection->isInterface()) {
            return [$name];
        }

        return self::_onlyParents($reflection->getInterfaces());
    }

    public static function getInterfaceMethods($name)
    {
        $methods = [];
        foreach (self::getInterfaces($name) as $interface) {
            $methods = array_merge($methods, get_class_methods($interface));
        }

        return array_unique($methods);
    }

    protected function _isInterfaceMethod($name, $method)
    {
        return in_array($method, self::getInterfaceMethods($name));
    }

    public static function getParent($name)
    {
        $reflection = new ReflectionClass($name);
        $parent = $reflection->getParentClass();
        if ($parent) {
            return $parent->getName();
        }

        return false;
    }

    public static function isAbstract($name)
    {
        $reflection = new ReflectionClass($name);

        return $reflection->isAbstract();
    }

    protected static function _onlyParents($interfaces)
    {
        $parents = [];
        foreach ($interfaces as $interface) {
            foreach ($interfaces as $possible_parent) {
                if ($interface->getName() == $possible_parent->getName()) {
                    continue;
                }

                if ($interface->isSubClassOf($possible_parent)) {
                    break;
                }
            }
            $parents[] = $interface->getName();
        }

        return $parents;
    }

    public static function getSignature($name, $method)
    {
        if ($method == '__get') {
            return 'function __get($key)';
        }

        if ($method == '__set') {
            return 'function __set($key, $value)';
        }

        if (! is_callable([$name, $method])) {
            return "function $method()";
        }

        if (self::_isInterfaceMethod($name, $method)) {
            return self::_getFullSignature($name, $method);
        }

        return "function $method()";
    }

    protected static function _getFullSignature($name, $method_name)
    {
        $interface = new ReflectionClass($name);
        $method = $interface->getMethod($method_name);
        $reference = $method->returnsReference() ? '&' : '';

        return "function $reference $method_name(".
              implode(', ', self::_getParameterSignatures($method)).
              ')';
    }

    protected static function _getParameterSignatures($method)
    {
        $signatures = [];
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getClass();
            $signatures[] =
                (! is_null($type) ? $type->getName().' ' : '').
                ($parameter->isPassedByReference() ? '&' : '').
                '$'.self::_suppressSpurious($parameter->getName()).
                ($parameter->isOptional() ? ' = null' : '');
        }

        return $signatures;
    }

    protected function _suppressSpurious($name)
    {
        return str_replace(['[', ']', ' '], '', $name);
    }
}
