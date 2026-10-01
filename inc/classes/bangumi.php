<?php

namespace Sakura\API;

class BangumiAPI
{
    private $apiUrl = 'https://api.bgm.tv';
    private $userID;
    private $collectionApi;

    public function __construct($userID)
    {
        if (empty($userID)) {
            throw new \InvalidArgumentException('User ID is required.');
        }

        $this->userID = $userID;
        $this->collectionApi = $this->apiUrl . '/v0/users/' . $this->userID . '/collections';
        $this->cache_content = get_transient('bangumi_cache_' . md5((string) $this->userID));
    }

    public function getCollections()
    {

        $collDataArr = [];

        $collDataArr = $this->fetchCollections();

        $collArr = $this->get_data($collDataArr);

        return $collArr;
    }

    private function get_data($collDataArr)
    {
        $collArr = [];
        foreach ($collDataArr as $value) {
            $collArr[] = [
                'name' => $value['subject']['name'],
                'name_cn' => $value['subject']['name_cn'],
                'date' => $value['subject']['date'],
                'summary' => $value['subject']['short_summary'],
                'url' => 'https://bgm.tv/subject/' . $value['subject']['id'],
                'images' => $value['subject']['images']['large'] ?? '',
                'eps' => $value['subject']['eps'] ?? 0,
                'ep_status' => $value['ep_status'] ?? 0,
            ];
        }
        return $collArr;
    }

    private function fetchCollections()
    {
        $bangumi_cache = iro_opt('bangumi_cache', true);
        $cache_key = 'bangumi_cache_' . md5((string) $this->userID);
        $collData = null;

        if ($bangumi_cache) {
            $cachedData = get_transient($cache_key);
            $collData = $cachedData ? json_decode($cachedData, true) : null;
            if (!isset($collData['data']) || !is_array($collData['data'])) {
                $collData = null;
            }
        }

        if ($collData === null) {
            $all_data = array();
            $offset = 0;
            $limit = 50;
            $total = 0;
            $batch_count = 0;

            do {
                $url = add_query_arg(
                    array(
                        'subject_type' => 2,
                        'limit' => $limit,
                        'offset' => $offset,
                    ),
                    $this->collectionApi
                );
                $response = $this->http_get_contents($url);
                if (false === $response) {
                    throw new \RuntimeException(__('Bangumi backend request failed.', 'sakurairo'));
                }
                $batch = json_decode($response, true);
                if (JSON_ERROR_NONE !== json_last_error() || !is_array($batch) || !isset($batch['data']) || !is_array($batch['data']) || !array_key_exists('total', $batch)) {
                    throw new \RuntimeException(__('Bangumi backend returned invalid data.', 'sakurairo'));
                }
                $batch_data = $batch['data'];
                $all_data = array_merge($all_data, $batch_data);
                $batch_total = absint($batch['total'] ?? 0);
                $total = max($total, $batch_total);
                $previous_offset = $offset;
                $offset += count($batch_data);
                $batch_count++;
            } while (!empty($batch_data) && $offset < $total && $offset > $previous_offset && $batch_count < 100);

            if ($offset < $total) {
                throw new \RuntimeException(__('Bangumi backend returned incomplete data.', 'sakurairo'));
            }

            $unique_data = array();
            foreach ($all_data as $item) {
                $subject_id = absint($item['subject']['id'] ?? 0);
                $unique_key = $subject_id > 0 ? (string) $subject_id : md5(wp_json_encode($item));
                $unique_data[$unique_key] = $item;
            }
            $all_data = array_values($unique_data);

            $collData = array(
                'data' => $all_data,
                'total' => count($all_data),
                'limit' => $limit,
                'offset' => 0,
            );
            if ($bangumi_cache) {
                auto_update_cache($cache_key, wp_json_encode($collData));
            }
        }

        if (isset($collData['data']) && is_array($collData['data'])) {
            return array_values(array_filter($collData['data'], function ($item) {
                return in_array((int) ($item['type'] ?? 0), array(2, 3), true) && (int) ($item['subject_type'] ?? 0) === 2;
            }));
        }

        return array();
    }

    private function http_get_contents($url)
    {
        $response = wp_remote_get($url, ['user-agent' => 'mirai-mamori/Sakurairo(https://github.com/mirai-mamori/Sakurairo):WordPressTheme']);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            return wp_remote_retrieve_body($response);
        }

        return false;
    }
}

class BangumiList
{
    public function get_bgm_items($userID, $page = 1, $pagination_url = '')
    {
        $page = max(1, absint($page));
        if (empty($userID)) {
            return '<p>' . __('Bangumi ID not set.', 'sakurairo') . '</p>';
        }

        try {
            $bgmAPI = new BangumiAPI($userID);
            $collections = $bgmAPI->getCollections(true, true);

            if (empty($collections)) {
                return '<p>' . __('No data', 'sakurairo') . '</p>';
            }

            $total = count($collections); // 总条目数
            $perPage = 12; // 每页条目数
            $totalPages = (int) ceil($total / $perPage); // 总页数
            $page = $totalPages > 0 ? min($page, $totalPages) : 1;
            $offset = ($page - 1) * $perPage;
            $collections = array_slice($collections, $offset, $perPage); // 当前页数据

            $html = '';
            foreach ($collections as $item) {
                $html .= '<div class="column">';
                $html .= '<a class="bangumi-item" href="' . esc_url($item['url']) . '" target="_blank" rel="nofollow">';
                $html .= '<img class="lazyload bangumi-image" data-src="' . esc_url($item['images']) . '" alt="' . esc_attr($item['name']) . '" onerror="imgError(this)" src="' . esc_url($item['images']) . '">';
                $html .= '<noscript><img class="bangumi-image" src="' . esc_url($item['images']) . '" alt="' . esc_attr($item['name']) . '"></noscript>';
                $html .= '<div class="bangumi-info">';
                $html .= '<h3 class="bangumi-title" title="' . esc_attr($item['name_cn'] ?: $item['name']) . '">' . esc_html($item['name_cn'] ?: $item['name']) . '</h3>';
                $html .= '<div class="bangumi-date">' . __('Release date: ', 'sakurairo') . esc_html($item['date']) . '</div>';
                $html .= '<div class="bangumi-status">';
                $progress = (!empty($item['eps']) && $item['eps'] > 0)
                    ? ($item['ep_status'] / $item['eps']) * 100
                    : 0;
                $html .= '<div class="bangumi-status-bar" style="width: ' . esc_attr($progress) . '%"></div>';
                $html .= '<p>' . __('Watch progress: ', 'sakurairo') . esc_html($item['ep_status'] . '/' . $item['eps']) . '</p>';
                $html .= '<div class="bangumi-summary">' . esc_html($item['summary'] ?: __('No introduction yet', 'sakurairo')) . '</div>';
                $html .= '</div></div></a></div>';
            }

            if ($totalPages > 1 && $pagination_url) {
                $html .= \koyori_render_pagination(
                    $page,
                    $totalPages,
                    $pagination_url,
                    'bangumi_page',
                    array(
                        'aria_label' => __('Bangumi pagination', 'sakurairo'),
                        'legacy_prefix' => 'bangumi',
                    )
                );
            }

            return $html;
        } catch (\Exception $e) {
            return '<p>' . __('An error occured: ', 'sakurairo') . esc_html($e->getMessage()) . '</p>';
        }
    }
}
