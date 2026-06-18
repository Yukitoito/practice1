<?php

declare(strict_types=1);

namespace Recall\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Recall\Domain\Stats;
use Recall\Http\Json;
use Recall\Http\Serializer;
use Recall\Infrastructure\Persistence\CardRepository;

final readonly class StatsController
{
    public function __construct(
        private CardRepository $cards,
        private ReviewRepository $reviews,
        private Serializer $serializer,
    ) {}

    public function index(Request $request, Response $response): Response
    {
        $now = new DateTimeImmutable('now');
        $today = Day::today($now);
        $weekLater = $today->plusDays(Interval::ofDays(7));

        $stats = new Stats(
            dueToday: count($this->cards->dueOn($today)),
            dueWeek: count($this->cards->dueOn($weekLater)),
            streak: $this->calculateStreak(),
            total: $this->cards->count(),
        );

        return Json::write($response, $this->serializer->serialize($stats));
    }

    private function calculateStreak(): int
    {
        $dates = $this->getUniqueActivityDates();

        if ($dates === []) {
            return 0;
        }

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        if ($dates[0] === $yesterday || $dates[0] === $today) {
            return $this->countStreakFromDate($dates, $dates[0]);
        }

        return 0;
    }

    /** @return array<string> */
    private function getUniqueActivityDates(): array
    {
        $dates = [];
        foreach ($this->reviews->all() as $review) {
            $dates[$review->createdAt()->format('Y-m-d')] = true;
        }
        $result = array_keys($dates);
        rsort($result);
        return $result;
    }

    /** @param array<string> $dates */
    private function countStreakFromDate(array $dates, string $startDate): int
    {
        $streak = 1;
        $expected = new DateTimeImmutable($startDate);

        for ($i = 1, $len = count($dates); $i < $len; $i++) {
            $expected = $expected->modify('-1 day');
            if ($dates[$i] !== $expected->format('Y-m-d')) {
                break;
            }
            $streak++;
        }

        return $streak;
    }
}
