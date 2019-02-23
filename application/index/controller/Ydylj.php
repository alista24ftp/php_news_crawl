<?php
namespace app\index\controller;
ini_set('max_execution_time', '1800');

use app\common\controller\Base;
use app\common\model\News;
use think\Db;

class Ydylj extends Base
{
    public function insertIntoDb($d)
    {
        Db::table('jt_news')->insert([
            'news_title'=>$d['title'],
            'news_columnid'=>$d['columnid'],
            'news_columnviceid'=>$d['columnviceid'],
            'news_auto'=>1,
            'news_source'=>$d['source'],
            'news_content'=>$d['content'],
            'news_scontent'=>$d['scontent'],
            'news_img'=>$d['img'],
            'news_pic_type'=>1,
            'news_time'=>$d['pdate'],
            'news_back'=>0,
            'news_open'=>1,
            'comment_status'=>1
        ]);
    }

    public function getHtml($url)
    {
        $context = stream_context_create(array('http'=>array('ignore_errors'=>true)));
        $html = @file_get_contents($url, false, $context);
        //$html = iconv("gb2312", "utf-8//IGNORE",$html);
        $html = preg_replace("/[\t\n\r]+/", "", $html);
        return $html;
    }

    public function testArticle()
    {
        $html = $this->getHtml("http://www.ydyljs.cn/ydyl/content/?534.html");
        preg_match("/(<div class=\"body_box\".*<span class=\"height_10\">)/U", $html, $body);
        preg_match("/来源.*<\/div>(<p.*)<span class=\"height_10\"/", $body[1], $content_arr);
        if(!empty($content_arr)){
            $content = preg_replace("/<img(.*)src=\"\/(.*)\"/U", '<img\1src="http://www.ydyljs.cn/\2"', $content_arr[1]);
            dump($content);
        }
    }

    private function getLinks1($url, $pages)
    {
        $links = [];
        for($page=1; $page<=$pages; $page++){
            $html = $this->getHtml($url . $page . ".html");
            $items = preg_split("/<li class=\"(one)|(two)\"\s*>/U", $html);
            foreach($items as $k => $item){
                if($k>0){
                    preg_match("/<a.*href='(.*)'/U", $item, $a_arr);
                    $l = (empty($a_arr)) ? '' : 'http://www.ydyljs.cn' . $a_arr[1];
                    preg_match("/<a.*title='(.*)'/U", $item, $title);
                    $title = (empty($title)) ? '' : trim($title[1]);
                    preg_match("/(\d{4}-\d{2}-\d{2})/U", $item, $pdate);
                    $pdate = (empty($pdate)) ? '' : $pdate[1];
                    array_push($links,[
                        'link'=>$l,
                        'title'=>$title,
                        'pdate'=>$pdate
                    ]);
                }
            }
        }
        return $links;
    }

    private function crawlNews1($articles, $category, $cid, $cvid)
    {
        $data = [];
        foreach($articles as $k => $article){
            $url = $article['link'];
            if($url == '') continue;
            $html = $this->getHtml($url);
            preg_match("/(<div class=\"body_box\".*<span class=\"height_10\">)/U", $html, $body);
            preg_match("/来源：(.*)<\//U", $body[1], $source);
            $source = (empty($source)) ? '' : trim(strip_tags($source[1]));
            preg_match("/(<div class=\"au_so\">.*)<span class=\"height_10\"/", $body[1], $content_arr);
            $content = !empty($content_arr) ? $content_arr[1] : '';
            if($article['title'] == '' || $content == '') continue;
            $article['pdate'] = strtotime($article['pdate']);
            $article['source'] = $source;
            $content = preg_replace("/<img(.*)src=\"\/(.*)\"/U", '<img\1src="http://www.ydyljs.cn/\2"', $content_arr[1]);
            $article['content'] = $content;
            preg_match("/<img.*src=\"(.*)\"/U", $content, $img);
            if(empty($img)){
                $article['img'] = '';
            }else{
                $img = $img[1];
                preg_match("/^http/", $img, $fullpathimg);
                $article['img'] = empty($fullpathimg) ? 'http://www.ydyljs.cn'.$img : $img;
            }

            $scontent = '';
            $scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
            $scontent = mb_substr($scontent,0,50,'utf8')."...";
            $article['scontent'] = $scontent;
            $article['category'] = $category;
            $article['columnid'] = $cid;
            $article['columnviceid'] = $cvid;
            array_push($data, $article);
            $this->insertIntoDb($article);
        }
        //dump($data);
    }

    public function crawlSilkNews()
    {
        $this->crawlNews1($this->getSilkNewsLinks(), '思路新闻', 63, 62);
    }

    public function getSilkNewsLinks()
    {
        return $this->getLinks1("http://www.ydyljs.cn/ydyl/list/?1_", 5);
    }

    public function crawlZhuantiNews()
    {
        $this->crawlNews1($this->getZhuantiLinks(), '专题报道', 66, 62);
    }

    public function getZhuantiLinks()
    {
        return $this->getLinks1("http://www.ydyljs.cn/ydyl/list/?2_", 2);
    }

    public function crawlChanyeNews()
    {
        $this->crawlNews1($this->getChanyeLinks(), "产业园区", 75, 72);
    }

    public function getChanyeLinks()
    {
        return $this->getLinks1("http://www.ydyljs.cn/ydyl/list/?3_", 2);
    }

    public function crawlQiyeNews()
    {
        $this->crawlNews1($this->getQiyeLinks(), "企业风采", 68, 67);
    }

    public function getQiyeLinks()
    {
        return $this->getLinks1("http://www.ydyljs.cn/ydyl/list/?4_", 1);
    }

    public function crawlXiangmuNews()
    {
        $this->crawlNews1($this->getXiangmuLinks(), "项目咨询", 71, 67);
    }

    public function getXiangmuLinks()
    {
        return $this->getLinks1("http://www.ydyljs.cn/ydyl/list/?5_", 1);
    }

    public function crawlTravelNews()
    {
        $this->crawlNews1($this->getTravelLinks(), "旅游文化", 80, 77);
    }

    public function getTravelLinks()
    {
        return $this->getLinks1("http://www.ydyljs.cn/ydyl/list/?6_", 2);
    }

    public function crawlSilkViewNews()
    {
        $this->crawlNews1($this->getSilkViewLinks(), "丝绸视角", 65, 62);
    }

    public function getSilkViewLinks()
    {
        return $this->getLinks1("http://www.ydyljs.cn/ydyl/list/?7_", 2);
    }

    public function crawlSilkForumNews()
    {
        $this->crawlNews1($this->getSilkForumLinks(), "丝绸论坛", 64, 62);
    }

    public function getSilkForumLinks()
    {
        return $this->getLinks1("http://www.ydyljs.cn/ydyl/list/?9_", 1);
    }

    public function crawlPolicyNews()
    {
        $this->crawlNews1($this->getPolicyLinks(), "政策咨询", 74, 72);
    }

    public function getPolicyLinks()
    {
        return $this->getLinks1("http://www.ydyljs.cn/ydyl/list/?10_", 1);
    }

    public function crawlSocietyNews()
    {
        $this->crawlNews1($this->getSocietyLinks(), "社会建设", 73, 72);
    }

    public function getSocietyLinks()
    {
        return $this->getLinks1("http://www.ydyljs.cn/ydyl/list/?11_", 1);
    }

    public function crawlKejiaoNews()
    {
        $this->crawlNews1($this->getKejiaoLinks(), "科教文卫", 81, 77);
    }

    public function getKejiaoLinks()
    {
        return $this->getLinks1("http://www.ydyljs.cn/ydyl/list/?12_", 2);
    }

    public function crawlShangmaoNews()
    {
        $this->crawlNews1($this->getShangmaoLinks(), "商贸交流", 69, 67);
    }

    public function getShangmaoLinks()
    {
        return $this->getLinks1("http://www.ydyljs.cn/ydyl/list/?13_", 1);
    }

    public function crawlFundNews()
    {
        $this->crawlNews1($this->getFundLinks(), "资金融通", 70, 67);
    }

    public function getFundLinks()
    {
        return $this->getLinks1("http://www.ydyljs.cn/ydyl/list/?14_", 1);
    }

    public function crawlHuiwuNews()
    {
        $this->crawlNews1($this->getHuiwuLinks(), "会务展览", 76, 72);
    }

    public function getHuiwuLinks()
    {
        return $this->getLinks1("http://www.ydyljs.cn/ydyl/list/?15_", 1);
    }


}