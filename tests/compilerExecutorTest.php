<?php

class compilerExecutorTest extends \PHPUnit_Framework_TestCase
{
    public function testExecutor()
    {
        $compiler = new \Stss\Compiler();
        $reflection = new \ReflectionClass(get_class($compiler));
        $method = $reflection->getMethod('execute');
        $method->setAccessible(true);

        $this->assertEquals($method->invokeArgs($compiler, array('$i')), '$context->getVar(\'i\')');
        $this->assertEquals($method->invokeArgs($compiler, array('$i.valami')), '$context->getVar(\'i.valami\')');
        $this->assertEquals($method->invokeArgs($compiler, array('attr(title)')), '$context->callFunc(\'attr\', array(\'title\'))');
        $this->assertEquals($method->invokeArgs($compiler, array('content() == 1')), '$context->callFunc(\'content\', array()) == \'1\'');
        $this->assertEquals($method->invokeArgs($compiler, array('content($valami) == 1')), '$context->callFunc(\'content\', array($context->getVar(\'valami\'))) == \'1\'');
        $this->assertEquals($method->invokeArgs($compiler, array('content(attr(title)) == 1')), '$context->callFunc(\'content\', array($context->callFunc(\'attr\', array(\'title\')))) == \'1\'');
        $this->assertEquals($method->invokeArgs($compiler, array('format($valami numeric 1)')), '$context->callFunc(\'format\', array($context->getVar(\'valami\'), \'numeric\', \'1\'))');
        $this->assertEquals($method->invokeArgs($compiler, array('$i < 1')), '$context->getVar(\'i\') < \'1\'');
        $this->assertEquals($method->invokeArgs($compiler, array('$i+1')), '$context->getVar(\'i\') . \'+1\'');
        $this->assertEquals($method->invokeArgs($compiler, array('teszt content()', true)), '\'teszt\', $context->callFunc(\'content\', array())');
        $this->assertEquals($method->invokeArgs($compiler, array('$key " " $a')), '$context->getVar(\'key\') . \' \' . $context->getVar(\'a\')');
    }
}
