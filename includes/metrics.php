<?php
/**
 * Computes avg likes, avg comments, engagement rate, and posting consistency
 * from an Apify "latestPosts" array + the profile's follower count.
 *
 * Pinned posts (isPinned: true) are EXCLUDED from every calculation here.
 * Pinned posts are manually chosen by the creator to show off their best
 * content — including them would inflate engagement averages and would
 * also distort the posting-consistency math (a pinned post from months ago
 * isn't part of their actual current posting rhythm).
 */
function calculate_engagement(array $latestPosts, int $followersCount): array
{
    $posts = array_filter($latestPosts, fn($post) => empty($post['isPinned']));
    $posts = array_values($posts);
    $pinnedExcluded = count($latestPosts) - count($posts);

    $count = count($posts);

    if ($count === 0 || $followersCount <= 0) {
        return [
            'avg_likes'              => 0,
            'avg_comments'           => 0,
            'engagement_rate'        => 0,
            'posts_analyzed'         => 0,
            'pinned_posts_excluded'  => $pinnedExcluded,
            'posts_per_week'         => null,
            'is_inconsistent'        => false,
        ];
    }

    $totalLikes    = 0;
    $totalComments = 0;

    foreach ($posts as $post) {
        $totalLikes    += (int) ($post['likesCount'] ?? 0);
        $totalComments += (int) ($post['commentsCount'] ?? 0);
    }

    $avgLikes    = $totalLikes / $count;
    $avgComments = $totalComments / $count;
    $engagement  = (($avgLikes + $avgComments) / $followersCount) * 100;

    $consistency = calculate_posting_consistency($posts);

    return [
        'avg_likes'             => round($avgLikes, 2),
        'avg_comments'          => round($avgComments, 2),
        'engagement_rate'       => round($engagement, 4),
        'posts_analyzed'        => $count,
        'pinned_posts_excluded' => $pinnedExcluded,
        'posts_per_week'        => $consistency['posts_per_week'],
        'is_inconsistent'       => $consistency['is_inconsistent'],
    ];
}

/**
 * Estimates posting frequency (posts per week) from a list of already
 * pinned-filtered posts, using the span between the oldest and newest
 * timestamp in the batch. More than 7 posts/week is flagged as
 * inconsistent (erratic/spammy cadence) — lower and steadier is better.
 */
function calculate_posting_consistency(array $posts): array
{
    $timestamps = [];
    foreach ($posts as $post) {
        if (!empty($post['timestamp'])) {
            $ts = strtotime($post['timestamp']);
            if ($ts !== false) {
                $timestamps[] = $ts;
            }
        }
    }

    if (count($timestamps) < 2) {
        // Not enough data points to estimate a cadence
        return ['posts_per_week' => null, 'is_inconsistent' => false];
    }

    sort($timestamps);
    $oldest = $timestamps[0];
    $newest = end($timestamps);
    $spanDays = ($newest - $oldest) / 86400;

    if ($spanDays <= 0) {
        return ['posts_per_week' => null, 'is_inconsistent' => false];
    }

    $intervals = count($timestamps) - 1; // gaps between posts, not post count itself
    $postsPerWeek = round(($intervals / $spanDays) * 7, 2);

    return [
        'posts_per_week'  => $postsPerWeek,
        // Kept for reference/future filtering — true if EITHER under-active
        // (<2/week) or over-active/spammy (>7/week). The dashboard itself
        // renders its own icon per band rather than relying on this flag.
        'is_inconsistent' => $postsPerWeek < 2 || $postsPerWeek > 7,
    ];
}
