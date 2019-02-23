<?php
namespace app\index\controller;

use app\common\controller\Base;
use QL\QueryList;
use Symfony\Component\DomCrawler\Crawler;

class Test extends Base
{
    public function testQL()
    {
        $ql = new QueryList;
        $html = $ql->get('https://www.sina.com.cn/')->getHtml();
        //dump($html);
        return $html;
    }

    public function testDomCrawler()
    {
        $html = $this->testQL();
        $crawler = new Crawler($html);
        dump($crawler);
    }
}
