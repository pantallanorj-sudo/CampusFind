<?php
/** Suggests existing reports that look like a new one. It only warns - it never blocks a submission. */
final class DuplicateDetectionService
{
    private static function words($text)
    {
        $text = preg_replace('/[^a-z0-9\s]+/', ' ', text_lower($text));
        $out = array();
        foreach (preg_split('/\s+/', trim($text)) as $w) {
            if (strlen($w) < 3) {
                continue;
            }
            if (strlen($w) > 3 && substr($w, -1) === 's') {
                $w = substr($w, 0, -1);
            }
            $out[$w] = true;
        }
        return array_keys($out);
    }

    /** 0-100: shared words relative to the shorter text ("blue umbrella" vs "umbrella" = 100). */
    public static function similarity($a, $b)
    {
        $x = self::words($a);
        $y = self::words($b);
        $smaller = min(count($x), count($y));
        if ($smaller === 0) {
            return 0;
        }
        return (int) round(count(array_intersect($x, $y)) / $smaller * 100);
    }

    private static function dayNumber($ymd)
    {
        return (int) floor(gmmktime(0, 0, 0, (int) substr($ymd, 5, 2), (int) substr($ymd, 8, 2), (int) substr($ymd, 0, 4)) / 86400);
    }

    /** Up to 5 possible duplicates (each row gets a 'score'), best first. */
    public static function find($type, $categoryId, $name, $location, $dateOccurred, $excludeId = 0)
    {
        $rows = db_all("SELECT r.id, r.item_name, r.location, r.date_occurred, r.status, r.report_type, r.image_path, r.created_at, c.name AS category_name "
            . "FROM item_reports r JOIN categories c ON c.id = r.category_id "
            . "WHERE r.report_type = ? AND r.category_id = ? AND r.status IN ('open','claim_pending') AND r.id <> ? ORDER BY r.created_at DESC LIMIT 200",
            array($type, (int) $categoryId, (int) $excludeId));
        $matches = array();
        foreach ($rows as $r) {
            $nameScore = self::similarity($name, $r['item_name']);
            $locScore = self::similarity($location, $r['location']);
            $gap = abs(self::dayNumber($dateOccurred) - self::dayNumber($r['date_occurred']));
            $dateScore = ($gap === 0) ? 100 : (($gap <= 3) ? 75 : (($gap <= 7) ? 50 : (($gap <= 14) ? 25 : 0)));
            $score = (int) round($nameScore * 0.55 + $locScore * 0.25 + $dateScore * 0.20);
            if ($nameScore >= 50 && $score >= 55) {
                $r['score'] = $score;
                $matches[] = $r;
            }
        }
        $top = array();
        while (count($top) < 5 && count($matches) > 0) {
            $best = 0;
            for ($i = 1; $i < count($matches); $i++) {
                if ($matches[$i]['score'] > $matches[$best]['score']) {
                    $best = $i;
                }
            }
            if ($matches[$best]['score'] < 0) {
                break;
            }
            $top[] = $matches[$best];
            $matches[$best]['score'] = -1;
            $left = 0;
            foreach ($matches as $m) {
                if ($m['score'] >= 0) {
                    $left++;
                }
            }
            if ($left === 0) {
                break;
            }
        }
        return $top;
    }
}
