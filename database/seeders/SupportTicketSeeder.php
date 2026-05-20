<?php

namespace Database\Seeders;

use App\Models\SupportTicket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class SupportTicketSeeder extends Seeder
{
    private const TOTAL_TICKETS = 7000;

    private const CATEGORIES = ['billing', 'technical', 'outage', 'general', 'account'];

    private const CUSTOMER_TIERS = ['free', 'starter', 'professional', 'enterprise'];

    private const PRIORITY_MAP = [
        'critical' => ['outage'],
        'high'     => ['billing', 'technical'],
        'medium'   => ['account', 'general'],
        'low'      => ['general'],
    ];

    private const KEYWORDS = [
        'critical' => ['down', 'outage', 'urgent', 'broken', 'not working', 'emergency'],
        'high'     => ['failed', 'error', 'issue', 'problem', 'wrong charge', 'refund'],
        'medium'   => ['question', 'help', 'how to', 'update', 'change'],
        'low'      => ['feedback', 'suggestion', 'wondering', 'curious'],
    ];

    public function run(): void
    {
        $tickets = [];
        $now = now();

        for ($i = 0; $i < self::TOTAL_TICKETS; $i++) {
            $priority = $this->resolvePriority();
            $category = $this->resolveCategory($priority);
            $tier     = $this->resolveTier($priority);

            $tickets[] = [
                'subject'                    => $this->generateSubject($priority),
                'body'                       => $this->generateBody($priority),
                'category'                   => $category,
                'customer_tier'              => $tier,
                'response_time_expectation'  => $this->resolveResponseTime($priority),
                'priority'                   => $priority,
                'predicted_priority'         => null,
                'confidence_score'           => null,
                'triage_status'              => 'pending',
                'created_at'                 => $now,
                'updated_at'                 => $now,
            ];

            // Insert in chunks to avoid memory issues
            if (count($tickets) === 500) {
                SupportTicket::insert($tickets);
                $tickets = [];
            }
        }

        if (!empty($tickets)) {
            SupportTicket::insert($tickets);
        }
    }

    private function resolvePriority(): string
    {
        // Weighted distribution: critical 10%, high 25%, medium 40%, low 25%
        $rand = rand(1, 100);

        return match(true) {
            $rand <= 10 => 'critical',
            $rand <= 35 => 'high',
            $rand <= 75 => 'medium',
            default     => 'low',
        };
    }

    private function resolveCategory(string $priority): string
    {
        // Categories skew toward their natural priority
        $biased = self::PRIORITY_MAP[$priority];

        return rand(1, 100) <= 70
            ? Arr::random($biased)
            : Arr::random(self::CATEGORIES);
    }

    private function resolveTier(string $priority): string
    {
        // Enterprise skews toward higher priority
        if (in_array($priority, ['critical', 'high']) && rand(1, 100) <= 60) {
            return Arr::random(['professional', 'enterprise']);
        }

        return Arr::random(self::CUSTOMER_TIERS);
    }

    private function resolveResponseTime(string $priority): int
    {
        // Lower response time expectation = higher urgency
        return match($priority) {
            'critical' => rand(1, 2),
            'high'     => rand(2, 8),
            'medium'   => rand(8, 24),
            'low'      => rand(24, 72),
        };
    }

    private function generateSubject(string $priority): string
    {
        $keyword = Arr::random(self::KEYWORDS[$priority]);
        $topics  = [
            'my account', 'the dashboard', 'payment processing',
            'login', 'API integration', 'billing cycle', 'service',
        ];

        return ucfirst("{$keyword} with " . Arr::random($topics));
    }

    private function generateBody(string $priority): string
    {
        $keyword = Arr::random(self::KEYWORDS[$priority]);

        return "I am experiencing a {$keyword} situation. Please assist as soon as possible. "
            . "This has been ongoing and requires immediate attention.";
    }
}