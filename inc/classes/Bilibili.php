<?php

namespace Sakura\API;

class Bilibili
{
    private $uid;
    private $cookies;

    public function __construct()
    {
        $this->uid = iro_opt('bilibili_id');
        $this->cookies = iro_opt('bilibili_cookie');
    }
    /**
     * 获取Bilibili用户追番列表
     * @param integer $type 
     * @param integer $page 页数
     * @author siroi <mrgaopw@hotmail.com>
     * @author KotoriK
     */
    function fetch_api(int $type, int $page = 1)
    {
        $uid = $this->uid;
        $cookies = $this->cookies;
        $url = "https://api.bilibili.com/x/space/bangumi/follow/list?type=$type&pn=$page&ps=12&follow_status=0&vmid=$uid";
        $args = array(
            'headers' => array(
                'Cookie' => $cookies,
                'Host' => 'api.bilibili.com',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.97'
            )
        );
        $response = wp_remote_get($url, $args);
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return array('code' => -1);
        }
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        if (JSON_ERROR_NONE !== json_last_error() || !is_array($response_body) || !array_key_exists('code', $response_body)) {
            return array('code' => -1);
        }
        return $response_body;
    }

    public function get_bgm_items($page = 1, $pagination_url = '')
    {
        $page = max(1, absint($page));
        $resp = $this->fetch_api(1, $page);
        $code = $resp['code'] ?? -1;
        switch ($code) {
            case -1://指示在网络请求阶段发生了错误
                return "<div>" . __('Backend error', 'sakurairo') . "</div>";
            case 0: {
                    $bgm = $resp['data'] ?? array();
                    $totalpage = isset($bgm['total']) ? (int) ceil($bgm['total'] / 12) : 0;
                    if (!isset($bgm['list']) || !is_array($bgm['list']) || !isset($bgm['total'])) {
                        return "<div>" . __('Backend error', 'sakurairo') . "</div>";
                    }
                    if ($totalpage > 0 && $page > $totalpage) {
                        $page = $totalpage;
                        $resp = $this->fetch_api(1, $page);
                        if (($resp['code'] ?? -1) !== 0 || !isset($resp['data']['list']) || !isset($resp['data']['total']) || !is_array($resp['data']['list'])) {
                            return "<div>" . __('Backend error', 'sakurairo') . "</div>";
                        }
                        $bgm = $resp['data'];
                    }
                    $lists = $bgm['list'];
                    $html = "";
                    foreach ((array)$lists as $item) {
                        $percent = Bilibili::get_percent($item);
                        $html .= Bilibili::bangumi_item($item, $percent);
                    }
                    if ($totalpage > 1 && $pagination_url) {
                        $html .= \koyori_render_pagination(
                            $page,
                            $totalpage,
                            $pagination_url,
                            'bangumi_page',
                            array(
                                'aria_label' => __('Bangumi pagination', 'sakurairo'),
                                'legacy_prefix' => 'bangumi',
                            )
                        );
                    }
                    return $html;
                }
            case 53013: //用户隐私设置未公开
                //TODO:制作错误页面
                return "<div>" . __('The author seems to have hidden their bangumi list.', 'sakurairo') . "</div>";
        }
    }

    public function get_bfv_items($page = 1, $pagination_url = '')
    {
        $page = max(1, absint($page));
        $resp = $this->fetch_api(2, $page);
        $code = $resp['code'] ?? -1;
        switch ($code) {
            case -1:
                return "<div>" . __('Backend error', 'sakurairo') . "</div>";
            case 0: {
                    $bgm = $resp['data'] ?? array();
                    if (!isset($bgm['total'], $bgm['list']) || !is_array($bgm['list'])) {
                        return "<div>" . __('Backend error', 'sakurairo') . "</div>";
                    }
                    $totalpage = (int) ceil($bgm['total'] / 12);
                    if ($totalpage > 0 && $page > $totalpage) {
                        $page = $totalpage;
                        $resp = $this->fetch_api(2, $page);
                        if (($resp['code'] ?? -1) !== 0 || !isset($resp['data']['total'], $resp['data']['list']) || !is_array($resp['data']['list'])) {
                            return "<div>" . __('Backend error', 'sakurairo') . "</div>";
                        }
                        $bgm = $resp['data'];
                    }
                    $lists = $bgm['list'];
                    $html = "";
                    foreach ((array)$lists as $item) {
                        $percent = Bilibili::get_percent($item);
                        $html .= Bilibili::bangumi_item($item, $percent);
                    }
                    if ($totalpage > 1 && $pagination_url) {
                        $html .= \koyori_render_pagination(
                            $page,
                            $totalpage,
                            $pagination_url,
                            'bangumi_page',
                            array(
                                'aria_label' => __('Bangumi pagination', 'sakurairo'),
                                'legacy_prefix' => 'bangumi',
                            )
                        );
                    }
                    return $html;
                }
        }
    }
    private static function bangumi_item(array $item, $percent)
    {
        //in_array('index_show','new_ep')
        return '<div class="column">' .
            '<a class="bangumi-item" href="https://bangumi.bilibili.com/anime/' . $item['season_id'] . '/" target="_blank" rel="nofollow">'
            .lazyload_img(str_replace('http://', 'https://', $item['cover']),'bangumi-image',array('alt'=>$item['title'],'referrerpolicy'=>"no-referrer")).
            '<div class="bangumi-info">' .
            '<h3 class="bangumi-title" title="' . $item['title'] . '">' . $item['title'] . '</h3>'
            . '<div class="bangumi-summary"> ' . $item['evaluate'] . ' </div>' .
            '<div class="bangumi-status">'
            . '<div class="bangumi-status-bar" style="width: ' . $percent . '%"></div>'
            . '<p>' . ($item['new_ep']['index_show'] ?? '').  '</p>'
            . '</div>'
            . '</div>'
            . '</a>'
            . '</div>';
    }
    private static function get_percent(array $item)
    {
        $percent = 0;
        if (preg_match('/看完/m', $item["progress"], $matches_finish)) {
            $percent = 100;
        } else {
            preg_match('/第(\d+)./m', $item['progress'], $matches_progress);
            if (isset($item["new_ep"]['index_show'])) {
                preg_match('/第(\d+)./m', $item["new_ep"]['index_show'], $matches_new);
            }
            if (isset($matches_progress[1])) {
                $progress = is_numeric($matches_progress[1]) ? $matches_progress[1] : 0;
            } else {
                $progress = 0;
            }
            $total = (isset($matches_new[1]) && is_numeric($matches_new[1])) ? $matches_new[1] : $item['total_count'];
            if ($total == 0) {
                //电影类剧集$total可能得到0
                $percent = 0;
            } else {
                $percent = $progress / $total * 100;
            }
        }
        return $percent;
    }
}
