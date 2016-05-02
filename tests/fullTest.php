<?php

class fullTest extends PHPUnit_Framework_TestCase
{
    public function testLoadHTMLWithEntities()
    {
        $template = '
                <div>&nbsp; &lt;</div>
        ';

        $template = new \Stss\Context($template);

        $this->assertEquals('<div>'.html_entity_decode('&nbsp;').' &lt;</div>', $template->getHtml());
    }
    public function testLoadHTMLUnclosed()
    {
        $template = '
                <div><img src="foo.jpg"></div>
        ';

        $template = new \Stss\Context($template);

        $this->assertEquals('<div><img src="foo.jpg" /></div>', $template->getHtml());
    }

    public function testContentSimple()
    {
        $template = '
                <ul><li>TEST1</li></ul>
        ';
        $stss = 'ul li {content: $user;}';
        $datas['user'] = 'tom';

        $sTss = \Stss\Template::getInstance(array('cache' => false));

        $this->assertEquals('<ul><li>tom</li></ul>', $sTss->render($template, $stss, $datas)->getHtml());
    }

    public function testContentObject()
    {
        $template = '
                <ul><li>TEST1</li></ul>
        ';
        $stss = 'ul li {content: $user.name;}';

        $datas['user']['name'] = 'tom';

        $sTss = \Stss\Template::getInstance(array('cache' => false));

        $this->assertEquals('<ul><li>tom</li></ul>', $sTss->render($template, $stss, $datas)->getHtml());
    }

    public function testForTo()
    {
        $template = '
                <ul><li>TEST1</li></ul>
        ';
        //When using repeat to repeat some data, set the content to the data for the iteration
        $stss = 'ul li {
             @for $i from 1 to 3 {
                content: $i;
             }
        }';
        $datas = array();

        $sTss = \Stss\Template::getInstance(array('cache' => false));

        $this->assertEquals('<ul><li>1</li><li>2</li><li>3</li></ul>', $sTss->render($template, $stss, $datas)->getHtml());
    }

    public function testForThrough()
    {
        $template = '
                <ul><li>TEST1</li></ul>
        ';
        //When using repeat to repeat some data, set the content to the data for the iteration
        $stss = 'ul li {
             @for $i from 1 through 3 {
                content: $i;
             }
        }';
        $datas = array();

        $sTss = \Stss\Template::getInstance(array('cache' => false));

        $this->assertEquals('<ul><li>1</li><li>2</li></ul>', $sTss->render($template, $stss, $datas)->getHtml());
    }

    public function testForStep()
    {
        $template = '
                <ul><li>TEST1</li></ul>
        ';
        //When using repeat to repeat some data, set the content to the data for the iteration
        $stss = 'ul li {
             @for $i from 1 to 4 step 2 {
                content: $i;
             }
        }';
        $datas = array();

        $sTss = \Stss\Template::getInstance(array('cache' => false));

        $this->assertEquals('<ul><li>1</li><li>3</li></ul>', $sTss->render($template, $stss, $datas)->getHtml());
    }

    public function testWhile()
    {
        $template = '
                <ul><li>TEST1</li></ul>
        ';
        //When using repeat to repeat some data, set the content to the data for the iteration
        $stss = 'ul li {
             @while $i < 3 {
                content: $i;
                $i: $i + 1;
             }
        }';
        $datas = array('i' => 1);

        $sTss = \Stss\Template::getInstance(array('cache' => false));

        $this->assertEquals('<ul><li>1</li><li>2</li></ul>', $sTss->render($template, $stss, $datas)->getHtml());
    }

    public function testIf()
    {
        $template = '
                <div><span>1</span><span>2</span></div>
        ';
        //When using repeat to repeat some data, set the content to the data for the iteration
        $stss = 'div span{
            @if content() == 1 {
               content: 3;
            }
        }';
        $datas = array();

        $sTss = \Stss\Template::getInstance(array('cache' => false));
        $this->expectOutputString('');
        $this->assertEquals('<div><span>3</span><span>2</span></div>', $sTss->render($template, $stss, $datas)->getHtml());
    }

    public function testElse()
    {
        $template = '
                <div><span>1</span><span>2</span></div>
        ';
        //When using repeat to repeat some data, set the content to the data for the iteration
        $stss = 'div span{
            @if content() == 1 {
               content: 3;
            }
            @else {
                content: 5;
            }
        }';
        $datas = array();

        $sTss = \Stss\Template::getInstance(array('cache' => false));
        $this->expectOutputString('');
        $this->assertEquals('<div><span>3</span><span>5</span></div>', $sTss->render($template, $stss, $datas)->getHtml());
    }

    public function testElseIf()
    {
        $template = '
                <div><span>1</span><span>2</span><span>3</span></div>
        ';
        //When using repeat to repeat some data, set the content to the data for the iteration
        $stss = 'div span{
            @if content() == 1 {
               content: 3;
            }
            @elseif content() == 3 {
                content: 5;
            }
        }';
        $datas = array();

        $sTss = \Stss\Template::getInstance(array('cache' => false));
        $this->expectOutputString('');
        $this->assertEquals('<div><span>3</span><span>2</span><span>5</span></div>', $sTss->render($template, $stss, $datas)->getHtml());
    }

    public function testEach()
    {
        $template = '
                <div><span>1</span></div>
        ';
        //When using repeat to repeat some data, set the content to the data for the iteration
        $stss = 'div span{
            @each a in $myArray {
                content: $a;
            }
        }';
        $datas = array('myArray' => array(1, 2, 3));

        $sTss = \Stss\Template::getInstance(array('cache' => false));
        $this->expectOutputString('');
        $this->assertEquals('<div><span>1</span><span>2</span><span>3</span></div>', $sTss->render($template, $stss, $datas)->getHtml());
    }

    public function testEachKey()
    {
        $template = '
                <div><span>1</span></div>
        ';
        //When using repeat to repeat some data, set the content to the data for the iteration
        $stss = 'div span{
            @each a,key in $myArray {
                content: $key " " $a;
            }
        }';
        $datas = array('myArray' => array('alma' => 1, 'barack' => 2, 'citrom' => 3));

        $sTss = \Stss\Template::getInstance(array('cache' => false));
        $this->expectOutputString('');
        $this->assertEquals('<div><span>alma 1</span><span>barack 2</span><span>citrom 3</span></div>', $sTss->render($template, $stss, $datas)->getHtml());
    }
}
