<?php
namespace app\index\controller;
ini_set('max_execution_time', '1800');

use app\common\controller\Base;
use app\common\model\News;
use think\Db;

class Ydyl extends Base
{
    public function insertIntoDb($d)
    {
        Db::table('jt_news')->insert([
            'news_title' => $d['title'],
            'news_columnid' => $d['columnid'],
            'news_columnviceid' => $d['columnviceid'],
            'news_auto' => 1,
            'news_source' => $d['source'],
            'news_content' => $d['content'],
            'news_scontent' => $d['scontent'],
            'news_img' => $d['img'],
            'news_pic_type' => 1,
            'news_time' => $d['pdate'],
            'news_back' => 0,
            'news_open' => 1,
            'comment_status' => 1
        ]);
    }

    public function getHtml($url)
    {
        $context = stream_context_create(array('http' => array('ignore_errors' => true)));
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
        if (!empty($content_arr)) {
            $content = preg_replace("/<img(.*)src=\"\/(.*)\"/U", '<img\1src="http://www.ydyljs.cn/\2"', $content_arr[1]);
            dump($content);
        }
    }

    private function getLinks1($url, $pages)
    {
        $links = [];
        for ($page = 1; $page <= $pages; $page++) {
            $html = $this->getHtml($url . "&cur_page=" . $page);
            preg_match("/<div class=\"wtfz_list_right left\">(.*)<\/ul>/U", $html, $list);
            $items = preg_split("/<li>/U", $list[1]);
            foreach ($items as $k => $item) {
                if ($k > 0) {
                    preg_match("/<a.*href=\"(.*)\"/U", $item, $a_arr);
                    $l = (empty($a_arr)) ? '' : 'https://www.yidaiyilu.gov.cn' . $a_arr[1];
                    preg_match("/<h1>(.*)<\/h1>/U", $item, $title);
                    $title = (empty($title)) ? '' : trim($title[1]);
                    preg_match("/<p.*?>(.*)<\/p>/U", $item, $scontent);
                    $scontent = (empty($scontent)) ? '' : $scontent[1];
                    preg_match("/<span.*?>(\d{4}-\d{2}-\d{2})<\/span>/U", $item, $pdate);
                    $pdate = (empty($pdate)) ? '' : $pdate[1];
                    array_push($links, [
                        'link' => $l,
                        'title' => $title,
                        'scontent' => $scontent,
                        'pdate' => $pdate
                    ]);
                }
            }
        }
        return $links;
    }

    private function crawlNews1($articles, $category, $cid, $cvid)
    {
        $data = [];
        foreach ($articles as $k => $article) {
            $url = $article['link'];
            if ($url == '') continue;
            $html = $this->getHtml($url);
            preg_match("/<span class=\"main_content_date szty2\">\s*来源：(.*)<\/span>/U", $html, $source);
            $source = (empty($source)) ? '' : trim(strip_tags($source[1]));
            preg_match("/<div class=\"content_left left\">(.*)<div class=\"blank18\"/U", $html, $content_arr);
            $content = !empty($content_arr) ? $content_arr[1] : '';
            if ($article['title'] == '' || $content == '') continue;
            $article['pdate'] = strtotime($article['pdate']);
            $article['source'] = $source;
            $content = preg_replace("/<img(.*)src=\"\/(.*)\"/U", '<img\1src="https://www.yidaiyilu.gov.cn/\2"', $content_arr[1]);
            $article['content'] = $content;
            preg_match("/<img.*src=\"(.*)\"/U", $content, $img);
            if (empty($img)) {
                $article['img'] = '';
            } else {
                $img = $img[1];
                preg_match("/^http/", $img, $fullpathimg);
                $article['img'] = empty($fullpathimg) ? 'https://www.yidaiyilu.gov.cn' . $img : $img;
            }

            if(empty($article['scontent'])){
                $scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
                $scontent = mb_substr($scontent, 0, 50, 'utf8') . "...";
                $article['scontent'] = $scontent;
            }

            $article['category'] = $category;
            $article['columnid'] = $cid;
            $article['columnviceid'] = $cvid;
            array_push($data, $article);
            $this->insertIntoDb($article);
        }
        //dump($data);
    }

    public function crawlBuweiNews()
    {
        $this->crawlNews1($this->getBuweiLinks(), '部委动态', 78, 77);
    }

    public function getBuweiLinks()
    {
        return $this->getLinks1("https://www.yidaiyilu.gov.cn/info/iList.jsp?cat_id=10003", 61);
    }

    public function crawlDifangNews()
    {
        $this->crawlNews1($this->getDifangLinks(), '地方动态', 79, 77);
    }

    public function getDifangLinks()
    {
        return $this->getLinks1("https://www.yidaiyilu.gov.cn/info/iList.jsp?cat_id=10004", 280);
    }
}