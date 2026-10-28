<?php

namespace Ssr;

use Symfony\Component\Yaml\Yaml;

/**
 * Reads the episode front matter straight from source/_episodes, so config.php
 * can generate the category, tag, month, year and author pages (which Jigsaw has
 * no built-in way to do) and the sidebar can show counts on every page.
 */
class Archive
{
    private static ?array $posts = null;

    public static function posts(): array
    {
        if (self::$posts === null) {
            self::$posts = [];
            foreach (glob(__DIR__ . '/../source/_episodes/*.md') as $file) {
                preg_match('/^---\n(.*?)\n---\n/s', file_get_contents($file), $m);
                self::$posts[] = Yaml::parse($m[1]);
            }
            usort(self::$posts, fn ($a, $b) => $b['date'] <=> $a['date']);
        }

        return self::$posts;
    }

    /** @return array<string, array{name: string, count: int}> slug => term, sorted by name */
    public static function terms(string $taxonomy): array
    {
        $terms = [];
        foreach (self::posts() as $post) {
            foreach ($post[$taxonomy] ?? [] as $slug => $name) {
                $terms[$slug] ??= ['name' => $name, 'count' => 0];
                $terms[$slug]['count']++;
            }
        }
        uasort($terms, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return $terms;
    }

    /** @return array<string, array<string, int>> year => [month (01-12) => post count], newest first */
    public static function months(): array
    {
        $months = [];
        foreach (self::posts() as $post) {
            $months[date('Y', $post['date'])][date('m', $post['date'])] ??= 0;
            $months[date('Y', $post['date'])][date('m', $post['date'])]++;
        }
        foreach ($months as &$year) {
            ksort($year);
        }

        return $months;
    }

    /** Items for the listing collections in config.php: one page per term, month, year and author. */
    public static function listingPages(string $kind): array
    {
        return match ($kind) {
            'category', 'tag' => array_map(
                fn ($slug, $term) => [
                    'filename' => (string) $slug,
                    'kind' => $kind,
                    'key' => (string) $slug,
                    'title' => $term['name'],
                    'heading' => ($kind === 'category' ? 'Category: ' : 'Tag: ') . $term['name'],
                ],
                array_keys($terms = self::terms($kind === 'category' ? 'categories' : 'tags')),
                $terms,
            ),
            'month' => array_merge(...array_map(
                fn ($year, $months) => array_map(fn ($month) => [
                    'filename' => "$year-$month",
                    'kind' => 'month',
                    'key' => "$year-$month",
                    'title' => date('F Y', mktime(0, 0, 0, (int) $month, 1, (int) $year)),
                    'heading' => date('F Y', mktime(0, 0, 0, (int) $month, 1, (int) $year)),
                ], array_keys($months)),
                array_keys($all = self::months()),
                $all,
            )),
            'year' => array_map(fn ($year) => [
                'filename' => (string) $year,
                'kind' => 'year',
                'key' => (string) $year,
                'title' => (string) $year,
                'heading' => (string) $year,
            ], array_keys(self::months())),
            'author' => array_map(fn ($slug) => [
                'filename' => $slug,
                'kind' => 'author',
                'key' => $slug,
                'title' => "Posts by " . self::authorName($slug),
                'heading' => "Posts by " . self::authorName($slug),
            ], array_values(array_unique(array_column(self::posts(), 'author_slug')))),
        };
    }

    public static function authorName(string $slug): string
    {
        foreach (self::posts() as $post) {
            if ($post['author_slug'] === $slug) {
                return $post['author'];
            }
        }

        return $slug;
    }
}
