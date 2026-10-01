<?php

namespace Sakura\API;

class MyAnimeList
{
	private $username;
	private $sort;

	public function __construct()
	{
		$this->username = iro_opt('my_anime_list_username');
		$this->sort = iro_opt('my_anime_list_sort');
	}

	/**
	 * Get anime list with username from https://myanimelist.net/
	 * @author siroi <mrgaopw@hotmail.com>
	 * @author KotoriK
	 * @author cocdeshijie <cocdeshijie@berkeley.edu>
	 */
	function get_data()
	{
		$bangumi_cache = iro_opt('bangumi_cache', true);
		$cache_key = 'myanimelist_cache_' . md5((string) $this->username . '|' . (string) $this->sort);

		if ($bangumi_cache) {
			$cached_content = json_decode(get_transient($cache_key), true);
			if (is_array($cached_content) && (empty($cached_content) || isset($cached_content[0]['anime_url']))) {
				return $cached_content;
			}
		}

		$username = $this->username;
		$sort = $this->sort;
		switch ($sort) {
			case 1:
				$sort = 'order=16&order2=5&status=7';
				break;
			case 2:
				$sort = 'order=5&status=7';
				break;
			case 3:
				$sort = 'order=16&status=7';
				break;
		}

		$all_items = array();
		$offset = 0;
		$batch_size = 300;
		$batch_count = 0;
		do {
			$url = add_query_arg('offset', $offset, "https://myanimelist.net/animelist/$username/load.json?$sort");
			$args = [
				'headers' => [
					'Host' => 'myanimelist.net',
					'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.97'
				]
			];
			$response = wp_remote_get($url, $args);
			if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
				return false;
			}
			$body = wp_remote_retrieve_body($response);
			$items = json_decode($body, true);
			if (JSON_ERROR_NONE !== json_last_error() || !is_array($items) || (!empty($items) && !isset($items[0]['anime_url']))) {
				return false;
			}
			$all_items = array_merge($all_items, $items);
			$received = count($items);
			$offset += $received;
			$batch_count++;
		} while ($received >= $batch_size && $batch_count < 100);

		if ($received >= $batch_size) {
			return false;
		}

		if ($bangumi_cache) {
			auto_update_cache($cache_key, wp_json_encode($all_items));
		}

		return $all_items;
	}

	public function get_all_items($page = 1, $pagination_url = '')
	{
		$page = max(1, absint($page));
		$resp = $this->get_data();
		if ($resp === false)
		{
			return "<div>" . __('Backend error', 'sakurairo') . "</div>";
		}
		else
		{
			$html = '';
			$item_count = count($resp);
			$total_episodes = 0;
			foreach ((array)$resp as $item)
			{
				$total_episodes += $item['num_watched_episodes'];
			}
			$per_page = 12;
			$total_pages = (int) ceil($item_count / $per_page);
			$page = $total_pages > 0 ? min($page, $total_pages) : 1;
			$items = array_slice($resp, ($page - 1) * $per_page, $per_page);
			foreach ($items as $item)
			{
				$html .= MyAnimeList::get_item_details($item);
			}
			$top_info = '<div class="bangumi-list-summary">' .
			            __('Following ', 'sakurairo') . $item_count . __(' anime.', 'sakurairo') .
			            __(' Watched ', 'sakurairo') . $total_episodes . __(' episodes.', 'sakurairo') .
			            '</div>';
			$html = $top_info . $html;
			if ($total_pages > 1 && $pagination_url) {
				$html .= \koyori_render_pagination(
					$page,
					$total_pages,
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

	private static function get_item_details(array $item)
	{

		return '<div class="column">' .
		       '<a class="bangumi-item" href="https://myanimelist.net' . $item['anime_url'] . '/" target="_blank" rel="nofollow">'
		       .lazyload_img(MyAnimeList::get_image($item['anime_image_path']),'bangumi-image',array('alt'=>$item['anime_title'])).
		       '<div class="bangumi-info">' .
		       '<h3 class="bangumi-title" title="' . $item['anime_title'] . '">' . $item['anime_title'] . '</h2>' .
		       '<div class="bangumi-summary"> ' . $item['anime_title_eng'] . ' </div>' .
		       '<div class="bangumi-status">' .
		       MyAnimeList::bangumi_status($item) .
		       '</div>' .
		       '</div>' .
		       '</a>' .
		       '</div>';
	}

	private static function get_image(string $image_path)
	{
		preg_match('/\/anime(.*?)\./', $image_path, $output);
		return "https://cdn.myanimelist.net/images/anime/$output[1].jpg";
	}

	private static function bangumi_status(array $item)
	{
		switch($item['status'])
		{
			case 2: // Completed
			{
				return '<div class="bangumi-status-bar" style="width: 100%; background: #5cb85c;"></div>' .
				       '<p>' .
				       __('Finished ', 'sakurairo') . $item['num_watched_episodes'] . '/' . $item['anime_num_episodes'] .
				       '</p>';
			}
			case 1: // Watching
			{
				return '<div class="bangumi-status-bar" style="width: '.
				       MyAnimeList::status_percent($item) .
				       '%; background: #0275d8;"></div>' .
				       '<p>' .
				       __('Watching ', 'sakurairo') . $item['num_watched_episodes'] . '/' . $item['anime_num_episodes'] .
				       '</p>';
			}
			case 6: // Plan to watch
			{
				return '<div class="bangumi-status-bar" style="width: 100%; background: #969ea4;"></div>' .
				       '<p>' .
				       __('Planning to Watch ', 'sakurairo') .
				       '</p>';
			}
			case 4: // Dropped
			{
				return '<div class="bangumi-status-bar" style="width: '.
				       MyAnimeList::status_percent($item) .
				       '%; background: #d9534f;"></div>' .
				       '<p>' .
				       __('Dropped ', 'sakurairo') . $item['num_watched_episodes'] . '/' . $item['anime_num_episodes'] .
				       '</p>';
			}
			case 3: // On Hold
			{
				return '<div class="bangumi-status-bar" style="width: '.
				       MyAnimeList::status_percent($item) .
				       '%; background: #f0ad4e;"></div>' .
				       '<p>' .
				       __('Paused ', 'sakurairo') . $item['num_watched_episodes'] . '/' . $item['anime_num_episodes'] .
				       '</p>';
			}
			default: // TODO: other possible status code
			{
				return '';
			}
		}
	}

	private static function status_percent(array $item)
	{
		if ($item['anime_num_episodes'] == 0)
		{
			return 0;
		}
		else
		{
			return ($item['num_watched_episodes']/$item['anime_num_episodes']) * 100;
		}
	}
}