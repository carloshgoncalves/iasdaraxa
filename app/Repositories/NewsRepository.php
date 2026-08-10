<?php

declare(strict_types=1);

namespace IasdCentral\Repositories;

use IasdCentral\Core\Config;

final class NewsRepository extends BaseRepository
{
    public function currentPublicItems(int $limit = 3): array
    {
        $local = [];
        try {
            $local = $this->fetchAll(
                "SELECT title, summary, image_url, source_url, published_at
                 FROM public_news
                 WHERE status = 'Ativo'
                   AND published_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                 ORDER BY published_at DESC
                 LIMIT {$limit}"
            );
        } catch (\Throwable $e) {
            $local = [];
        }

        if ($local !== []) {
            return $local;
        }

        return $this->feedItems($limit);
    }

    private function feedItems(int $limit): array
    {
        $feedUrl = (string) Config::get('news.feed_url', '');
        if ($feedUrl === '') {
            return [];
        }

        $context = stream_context_create(['http' => ['timeout' => 3, 'user_agent' => 'IASD Central']]);
        $xml = @file_get_contents($feedUrl, false, $context);
        if ($xml === false) {
            return [];
        }

        $feed = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$feed || empty($feed->channel->item)) {
            return [];
        }

        $maxAgeDays = (int) Config::get('news.max_age_days', 14);
        $minTime = strtotime("-{$maxAgeDays} days") ?: 0;
        $items = [];

        foreach ($feed->channel->item as $item) {
            $published = strtotime((string) $item->pubDate);
            if (!$published || $published < $minTime) {
                continue;
            }

            $items[] = [
                'title' => trim((string) $item->title),
                'summary' => trim(strip_tags((string) $item->description)),
                'image_url' => null,
                'source_url' => trim((string) $item->link),
                'published_at' => date('Y-m-d H:i:s', $published),
            ];

            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }
}
