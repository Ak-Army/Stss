<?php


class selectorsTest extends PHPUnit_Framework_TestCase
{
    public function testSelectorToXpath()
    {
        $compiler = new \Stss\Compiler();
        $reflection = new \ReflectionClass(get_class($compiler));
        $method = $reflection->getMethod('selectorToXpath');
        $method->setAccessible(true);

        $this->assertEquals($method->invokeArgs($compiler, array('foo')), 'descendant::foo');
        $this->assertEquals($method->invokeArgs($compiler, array('foo, bar')), 'descendant::foo|descendant::bar');
        $this->assertEquals($method->invokeArgs($compiler, array('foo bar')), 'descendant::foo/descendant::bar');
        $this->assertEquals($method->invokeArgs($compiler, array('foo    bar')), 'descendant::foo/descendant::bar');
        $this->assertEquals($method->invokeArgs($compiler, array('foo > bar')), 'descendant::foo/bar');
        $this->assertEquals($method->invokeArgs($compiler, array('foo >bar')), 'descendant::foo/bar');
        $this->assertEquals($method->invokeArgs($compiler, array('foo>bar')), 'descendant::foo/bar');
        $this->assertEquals($method->invokeArgs($compiler, array('foo> bar')), 'descendant::foo/bar');
        $this->assertEquals($method->invokeArgs($compiler, array('div#foo')), 'descendant::div[@id="foo"]');
        $this->assertEquals($method->invokeArgs($compiler, array('#foo')), 'descendant::*[@id="foo"]');
        $this->assertEquals($method->invokeArgs($compiler, array('div.foo')), 'descendant::div[contains(concat(" ",@class," ")," foo ")]');
        $this->assertEquals($method->invokeArgs($compiler, array('.foo')), 'descendant::*[contains(concat(" ",@class," ")," foo ")]');
        $this->assertEquals($method->invokeArgs($compiler, array('[id]')), 'descendant::*[@id]');
        $this->assertEquals($method->invokeArgs($compiler, array('[id=bar]')), 'descendant::*[@id="bar"]');
        $this->assertEquals($method->invokeArgs($compiler, array('foo[id=bar]')), 'descendant::foo[@id="bar"]');
        $this->assertEquals($method->invokeArgs($compiler, array('[style=color: red; border: 1px solid black;]')), 'descendant::*[@style="color: red; border: 1px solid black;"]');
        $this->assertEquals($method->invokeArgs($compiler, array('foo[style=color: red; border: 1px solid black;]')), 'descendant::foo[@style="color: red; border: 1px solid black;"]');
        $this->assertEquals($method->invokeArgs($compiler, array(':button')), 'descendant::input[@type="button"]');
        $this->assertEquals($method->invokeArgs($compiler, array('textarea')), 'descendant::textarea');
        $this->assertEquals($method->invokeArgs($compiler, array(':submit')), 'descendant::input[@type="submit"]');
        $this->assertEquals($method->invokeArgs($compiler, array(':first-child')), 'descendant::*/*[position()=1]');
        $this->assertEquals($method->invokeArgs($compiler, array('div:first-child')), 'descendant::*/div[position()=1]');
        $this->assertEquals($method->invokeArgs($compiler, array(':last-child')), 'descendant::*/*[position()=last()]');
        $this->assertEquals($method->invokeArgs($compiler, array(':nth-last-child(2)')), 'descendant::[position()=(last() - (2 - 1))]');
        $this->assertEquals($method->invokeArgs($compiler, array('div:last-child')), 'descendant::*/div[position()=last()]');
        $this->assertEquals($method->invokeArgs($compiler, array(':nth-child(2)')), 'descendant::*/*[position()=2]');
        $this->assertEquals($method->invokeArgs($compiler, array('div:nth-child(2)')), 'descendant::*/*[position()=2 and self::div]');
        $this->assertEquals($method->invokeArgs($compiler, array('foo + bar')), 'descendant::foo/following-sibling::bar[position()=1]');
        $this->assertEquals($method->invokeArgs($compiler, array('li:contains(Foo)')), 'descendant::li[contains(string(.),"Foo")]');
        $this->assertEquals($method->invokeArgs($compiler, array('foo bar baz')), 'descendant::foo/descendant::bar/descendant::baz');
        $this->assertEquals($method->invokeArgs($compiler, array('foo + bar + baz')), 'descendant::foo/following-sibling::bar[position()=1]/following-sibling::baz[position()=1]');
        $this->assertEquals($method->invokeArgs($compiler, array('foo > bar > baz')), 'descendant::foo/bar/baz');
        $this->assertEquals($method->invokeArgs($compiler, array('p ~ p ~ p')), 'descendant::p/following-sibling::p/following-sibling::p');
        $this->assertEquals($method->invokeArgs($compiler, array('div#article p em')), 'descendant::div[@id="article"]/descendant::p/descendant::em');
        $this->assertEquals($method->invokeArgs($compiler, array('div.foo:first-child')), 'descendant::div[contains(concat(" ",@class," ")," foo ")][position()=1]');
        $this->assertEquals($method->invokeArgs($compiler, array('form#login > input[type=hidden]._method')), 'descendant::form[@id="login"]/input[@type="hidden"][contains(concat(" ",@class," ")," _method ")]');
    }
}
