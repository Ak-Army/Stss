<?php

class dotNotationTest extends \PHPUnit_Framework_TestCase
{
    public function testSet()
    {
        $d = new \Stss\Context('', []);
        $d->setVar('one', 1);
        $this->assertEquals(['one' => 1], $d->getVars());
    }
    public function testSetOverride()
    {
        $d = new \Stss\Context('', ['one' => 1]);
        $d->setVar('one', 2);
        $this->assertEquals(['one' => 2], $d->getVars());
    }
    public function testSetPath()
    {
        $d = new \Stss\Context('', ['one' => ['two' => 1]]);
        $d->setVar('one.two', 2);
        $this->assertEquals(['one' => ['two' => 2]], $d->getVars());
    }
    public function testPathAppend()
    {
        $d = new \Stss\Context('', ['one' => ['two' => 1]]);
        $d->setVar('one.other', 1);
        $this->assertEquals(['one' => ['two' => 1, 'other' => 1]], $d->getVars());
    }
    public function testSetAppend()
    {
        $d = new \Stss\Context('', ['one' => ['two' => 1]]);
        $d->setVar('two', 2);
        $this->assertEquals(['one' => ['two' => 1], 'two' => 2], $d->getVars());
    }
    public function testSetAppendArray()
    {
        $d = new \Stss\Context('', ['one' => ['two' => 1]]);
        $d->setVar('one', ['two' => 2]);
        $this->assertEquals(['one' => ['two' => 2]], $d->getVars());
    }
    public function testSetOverrideAndAppend()
    {
        $d = new \Stss\Context('', ['one' => ['two' => 1]]);
        $d->setVar('one', ['two' => 2, 'other' => 3]);
        $this->assertEquals(['one' => ['two' => 2, 'other' => 3]], $d->getVars());
    }
    public function testSetOverrideByArray()
    {
        $d = new \Stss\Context('', ['one' => ['two' => 1]]);
        $d->setVar('one', ['other' => 3]);
        $this->assertEquals(['one' => ['other' => 3]], $d->getVars());
    }
    public function testSetPathByDoubleDots()
    {
        $d = new \Stss\Context('', ['one' => ['two' => ['three' => 1]]]);
        $d->setVar('one.two.three', 3);
        $this->assertEquals(['one' => ['two' => ['three' => 3]]], $d->getVars());
    }
    public function testGet()
    {
        $d = new \Stss\Context('', ['one' => ['two' => ['three' => 1]]]);
        $this->assertEquals(['two' => ['three' => 1]], $d->getVar('one'));
        $this->assertEquals(['three' => 1], $d->getVar('one.two'));
        $this->assertEquals(1, $d->getVar('one.two.three'));
        $this->assertEquals(false, $d->getVar('one.two.three.next', false));
    }
    public function testHave()
    {
        $d = new \Stss\Context('', ['one' => ['two' => ['three' => 1]]]);
        $this->assertTrue($d->hasVar('one'));
        $this->assertTrue($d->hasVar('one.two'));
        $this->assertTrue($d->hasVar('one.two.three'));
        $this->assertFalse($d->hasVar('one.two.three.false'));
        $this->assertFalse($d->hasVar('one.false.three'));
        $this->assertFalse($d->hasVar('false'));
    }
}
