<?php
namespace app\index\controller;
ini_set('max_execution_time', '1800');
ini_set('post_max_size', '0');
ini_set('memory_limit', '-1');

use app\common\controller\Base;
use app\index\model\News as NewsModel;
use app\index\crawl\Crawl as CrawlHelper;

class Crawl extends Base
{
    public function index()
    {
        $jsonFiles = json_decode(file_get_contents('./json/files.json'), true);
        $this->assign('json_files', array_keys($jsonFiles));
        return $this->fetch();
    }

    public function crawlall(CrawlHelper $crawl)
    {
        $name = input('json_name', 'eastday');
        $msg = $crawl->crawlAll($name);
        //$this->assign('msg', $msg);
        //return $this->fetch();
        echo $msg;
    }

    public function crawl(CrawlHelper $crawl)
    {
        $name = 'eastday';
        $crawl->crawl($name, '');
    }

    public function test(CrawlHelper $crawl)
    {
        $name = 'eastday';
        $crawl->test();
    }


}
