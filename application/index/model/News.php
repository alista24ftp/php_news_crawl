<?php
namespace app\index\model;

use think\Model;
use think\Db;

class News extends Model
{
    protected $pk = 'n_id';
    protected $table = 'jt_news';

    public function insertIntoDb($allNews)
    {
        foreach($allNews as $k=>$news){
            $allNews[$k] = $this->toTableFormat($news);
        }

        //dump($allNews); exit;

        Db::startTrans();
        try{
            Db::name('news')->insertAll($allNews);
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            throw $e;
        }
    }

    public function getMostRecentTime($columnviceid)
    {
        $mostRecentTime = $this->where(['news_columnviceid'=>$columnviceid])
            ->max('news_time');
        return $mostRecentTime;
    }

    private function toTableFormat($news)
    {
        return [
            'news_title'=>trim($news['title']),
            'news_columnid'=>$news['columnid'],
            'news_columnviceid'=>$news['columnviceid'],
            'news_auto'=>1,
            'news_source'=>$news['source'],
            'news_content'=>trim($news['content']),
            'news_scontent'=>trim($news['scontent']),
            'news_img'=>$news['image'],
            'news_pic_type'=>1,
            'news_time'=>$news['pdate'],
            'news_back'=>0,
            'news_open'=>1,
            'comment_status'=>1
        ];
    }
}
