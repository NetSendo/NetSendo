<?php

namespace Database\Seeders;

use App\Models\CampaignBenchmark;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CampaignBenchmarkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Industry benchmark data based on various marketing studies (Mailchimp, HubSpot, etc.)
     */
    public function run(): void
    {
        $benchmarks = [
            // eCommerce
            [
                'industry' => 'ecommerce',
                'campaign_type' => null,
                'avg_open_rate' => 15.68,
                'avg_click_rate' => 2.01,
                'avg_conversion_rate' => 1.5,
                'avg_unsubscribe_rate' => 0.27,
                'recommended_messages' => 5,
                'recommended_timeline_days' => 14,
                'best_practices' => [
                    'Use product images in emails',
                    'Include customer reviews',
                    'Create urgency with limited-time offers',
                    'Segment by purchase history',
                ],
            ],
            [
                'industry' => 'ecommerce',
                'campaign_type' => 'sales',
                'avg_open_rate' => 14.5,
                'avg_click_rate' => 2.5,
                'avg_conversion_rate' => 2.0,
                'avg_unsubscribe_rate' => 0.35,
                'recommended_messages' => 4,
                'recommended_timeline_days' => 7,
                'best_practices' => [
                    'Start with value proposition',
                    'Build urgency gradually',
                    'Include social proof',
                    'End with strong CTA',
                ],
            ],
            [
                'industry' => 'ecommerce',
                'campaign_type' => 'reactivation',
                'avg_open_rate' => 12.0,
                'avg_click_rate' => 1.5,
                'avg_conversion_rate' => 0.8,
                'avg_unsubscribe_rate' => 0.5,
                'recommended_messages' => 3,
                'recommended_timeline_days' => 21,
                'best_practices' => [
                    'Acknowledge the absence',
                    'Offer exclusive comeback discount',
                    'Show what they missed',
                    'Make unsubscribing easy',
                ],
            ],

            // SaaS
            [
                'industry' => 'saas',
                'campaign_type' => null,
                'avg_open_rate' => 21.29,
                'avg_click_rate' => 2.45,
                'avg_conversion_rate' => 1.8,
                'avg_unsubscribe_rate' => 0.4,
                'recommended_messages' => 6,
                'recommended_timeline_days' => 21,
                'best_practices' => [
                    'Focus on solving problems',
                    'Share case studies',
                    'Offer free trials or demos',
                    'Use drip campaigns for nurturing',
                ],
            ],
            [
                'industry' => 'saas',
                'campaign_type' => 'onboarding',
                'avg_open_rate' => 35.0,
                'avg_click_rate' => 5.0,
                'avg_conversion_rate' => 15.0,
                'avg_unsubscribe_rate' => 0.2,
                'recommended_messages' => 7,
                'recommended_timeline_days' => 14,
                'best_practices' => [
                    'Welcome immediately after signup',
                    'Guide through key features step-by-step',
                    'Celebrate small wins',
                    'Offer personalized tips based on usage',
                ],
            ],

            // Education
            [
                'industry' => 'education',
                'campaign_type' => null,
                'avg_open_rate' => 23.42,
                'avg_click_rate' => 2.90,
                'avg_conversion_rate' => 2.5,
                'avg_unsubscribe_rate' => 0.21,
                'recommended_messages' => 5,
                'recommended_timeline_days' => 14,
                'best_practices' => [
                    'Lead with value and insights',
                    'Share success stories',
                    'Offer free resources',
                    'Build authority through content',
                ],
            ],

            // Finance
            [
                'industry' => 'finance',
                'campaign_type' => null,
                'avg_open_rate' => 21.56,
                'avg_click_rate' => 2.72,
                'avg_conversion_rate' => 1.2,
                'avg_unsubscribe_rate' => 0.25,
                'recommended_messages' => 4,
                'recommended_timeline_days' => 14,
                'best_practices' => [
                    'Build trust through transparency',
                    'Use clear, jargon-free language',
                    'Include security badges',
                    'Personalize based on financial goals',
                ],
            ],

            // Health & Wellness
            [
                'industry' => 'health',
                'campaign_type' => null,
                'avg_open_rate' => 21.48,
                'avg_click_rate' => 2.69,
                'avg_conversion_rate' => 1.8,
                'avg_unsubscribe_rate' => 0.30,
                'recommended_messages' => 5,
                'recommended_timeline_days' => 14,
                'best_practices' => [
                    'Focus on transformation stories',
                    'Include before/after examples',
                    'Provide actionable tips',
                    'Build community feeling',
                ],
            ],

            // Travel
            [
                'industry' => 'travel',
                'campaign_type' => null,
                'avg_open_rate' => 20.44,
                'avg_click_rate' => 2.25,
                'avg_conversion_rate' => 1.0,
                'avg_unsubscribe_rate' => 0.24,
                'recommended_messages' => 4,
                'recommended_timeline_days' => 10,
                'best_practices' => [
                    'Use stunning visuals',
                    'Create FOMO with limited deals',
                    'Personalize by travel preferences',
                    'Include destination guides',
                ],
            ],

            // B2B Services
            [
                'industry' => 'b2b',
                'campaign_type' => null,
                'avg_open_rate' => 15.14,
                'avg_click_rate' => 2.13,
                'avg_conversion_rate' => 0.5,
                'avg_unsubscribe_rate' => 0.20,
                'recommended_messages' => 6,
                'recommended_timeline_days' => 30,
                'best_practices' => [
                    'Focus on ROI and business outcomes',
                    'Share industry reports',
                    'Use case studies extensively',
                    'Offer consultations or demos',
                ],
            ],
            [
                'industry' => 'b2b',
                'campaign_type' => 'lead_nurturing',
                'avg_open_rate' => 18.0,
                'avg_click_rate' => 2.5,
                'avg_conversion_rate' => 0.8,
                'avg_unsubscribe_rate' => 0.15,
                'recommended_messages' => 8,
                'recommended_timeline_days' => 45,
                'best_practices' => [
                    'Provide educational content at each stage',
                    'Score leads based on engagement',
                    'Align with sales team timing',
                    'Address common objections',
                ],
            ],

            // Default / Other
            [
                'industry' => 'other',
                'campaign_type' => null,
                'avg_open_rate' => 18.0,
                'avg_click_rate' => 2.0,
                'avg_conversion_rate' => 1.0,
                'avg_unsubscribe_rate' => 0.25,
                'recommended_messages' => 5,
                'recommended_timeline_days' => 14,
                'best_practices' => [
                    'Know your audience deeply',
                    'Test subject lines',
                    'Personalize when possible',
                    'Always provide value',
                ],
            ],
        ];

        foreach ($benchmarks as $benchmark) {
            CampaignBenchmark::updateOrCreate(
                [
                    'industry' => $benchmark['industry'],
                    'campaign_type' => $benchmark['campaign_type'],
                ],
                $benchmark
            );
        }
    }
}
