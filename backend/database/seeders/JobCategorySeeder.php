<?php

namespace Database\Seeders;

use App\Models\JobCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JobCategorySeeder extends Seeder
{
    use WithoutModelEvents;

    private const EXPERIENCE = ['0-1', '1-3', '3-5', '5-8', '8+'];

    /**
     * Seed the initial job categories with their skill-specific field schemas.
     *
     * Field types used by the portal renderer and server validator:
     * text, url, textarea, select, multiselect, file.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Graphic Designer',
                'slug' => 'graphic-designer',
                'icon' => 'palette',
                'description' => 'Create visual identities, marketing collateral, and branded assets for NCC campaigns.',
                'fields' => [
                    ['key' => 'design_tools', 'label' => 'Design tools known', 'type' => 'multiselect', 'required' => true, 'options' => ['Photoshop', 'Illustrator', 'Figma', 'Canva', 'InDesign']],
                    ['key' => 'portfolio_url', 'label' => 'Portfolio link (Behance / Dribbble / website)', 'type' => 'url', 'required' => false],
                    ['key' => 'years_experience', 'label' => 'Years of experience', 'type' => 'select', 'required' => true, 'options' => self::EXPERIENCE],
                ],
            ],
            [
                'name' => 'Web Designer',
                'slug' => 'web-designer',
                'icon' => 'layout',
                'description' => 'Design responsive, accessible website layouts and landing pages that bring NCC content online.',
                'fields' => [
                    ['key' => 'design_tools', 'label' => 'Tools / frameworks', 'type' => 'multiselect', 'required' => true, 'options' => ['Figma', 'Webflow', 'WordPress', 'HTML', 'CSS', 'Tailwind']],
                    ['key' => 'live_sites', 'label' => 'Live site / sample link', 'type' => 'url', 'required' => true],
                    ['key' => 'years_experience', 'label' => 'Years of experience', 'type' => 'select', 'required' => true, 'options' => self::EXPERIENCE],
                ],
            ],
            [
                'name' => 'Developer',
                'slug' => 'developer',
                'icon' => 'code',
                'description' => 'Build and maintain software, APIs, and tooling that power the association’s platforms.',
                'fields' => [
                    ['key' => 'languages', 'label' => 'Languages / stacks known', 'type' => 'multiselect', 'required' => true, 'options' => ['JavaScript / TypeScript', 'PHP', 'Python', 'Java', 'C#', 'Go', 'Ruby', 'Swift', 'Kotlin', 'Dart', 'C++', 'Other']],
                    ['key' => 'github_url', 'label' => 'GitHub / project link', 'type' => 'url', 'required' => false],
                    ['key' => 'preferred_stack', 'label' => 'Preferred stack', 'type' => 'select', 'required' => true, 'options' => ['Frontend', 'Backend', 'Full-stack', 'Mobile']],
                    ['key' => 'years_experience', 'label' => 'Years of experience', 'type' => 'select', 'required' => true, 'options' => self::EXPERIENCE],
                ],
            ],
            [
                'name' => 'DevOps',
                'slug' => 'devops',
                'icon' => 'server',
                'description' => 'Automate infrastructure, CI/CD, and keep NCC platforms reliable and secure.',
                'fields' => [
                    ['key' => 'tools', 'label' => 'Tools known', 'type' => 'multiselect', 'required' => true, 'options' => ['Docker', 'Kubernetes', 'Ansible', 'Terraform', 'Jenkins', 'GitHub Actions', 'GitLab CI', 'AWS', 'Azure', 'GCP', 'Other']],
                    ['key' => 'infrastructure_notes', 'label' => 'Key infrastructure / work experience', 'type' => 'textarea', 'required' => false],
                    ['key' => 'certifications', 'label' => 'Certification file (PDF only, max 5MB)', 'type' => 'file', 'required' => false, 'accept' => 'application/pdf', 'max_mb' => 5],
                ],
            ],
            [
                'name' => 'Content Creator',
                'slug' => 'content-creator',
                'icon' => 'camera',
                'description' => 'Produce engaging social content that grows NCC’s reach across short-form platforms.',
                'fields' => [
                    ['key' => 'platforms', 'label' => 'Platforms active on', 'type' => 'multiselect', 'required' => true, 'options' => ['YouTube', 'Instagram', 'TikTok', 'Facebook', 'LinkedIn', 'Other']],
                    ['key' => 'platform_links', 'label' => 'Channel / profile handles or links', 'type' => 'textarea', 'required' => true],
                    ['key' => 'niche', 'label' => 'Content niche (e.g. tech, fitness, education)', 'type' => 'text', 'required' => true],
                    ['key' => 'sample_links', 'label' => 'Sample content links', 'type' => 'textarea', 'required' => false],
                ],
            ],
            [
                'name' => 'Content Writer',
                'slug' => 'content-writer',
                'icon' => 'pen',
                'description' => 'Craft clear, compelling copy — from news pieces to campaign narratives.',
                'fields' => [
                    ['key' => 'writing_samples', 'label' => 'Writing samples (paste links or describe)', 'type' => 'textarea', 'required' => false],
                    ['key' => 'languages_written', 'label' => 'Languages written in', 'type' => 'multiselect', 'required' => true, 'options' => ['English', 'Nepali']],
                    ['key' => 'niche', 'label' => 'Niche / genre', 'type' => 'select', 'required' => true, 'options' => ['News / Journalism', 'Technical', 'Marketing / Copywriting', 'Creative', 'Academic', 'Other']],
                ],
            ],
            [
                'name' => 'Videographer',
                'slug' => 'videographer',
                'icon' => 'video',
                'description' => 'Shoot and light NCC events, ceremonies, and stories with a professional eye.',
                'fields' => [
                    ['key' => 'equipment', 'label' => 'Equipment owned (optional)', 'type' => 'text', 'required' => false],
                    ['key' => 'demo_reel', 'label' => 'Demo reel / video link (YouTube / Vimeo / Drive)', 'type' => 'url', 'required' => true],
                    ['key' => 'years_experience', 'label' => 'Years of experience', 'type' => 'select', 'required' => true, 'options' => self::EXPERIENCE],
                ],
            ],
            [
                'name' => 'Video Editor',
                'slug' => 'video-editor',
                'icon' => 'scissors',
                'description' => 'Cut and polish footage into finished, publish-ready NCC films and shorts.',
                'fields' => [
                    ['key' => 'software', 'label' => 'Editing software known', 'type' => 'multiselect', 'required' => true, 'options' => ['Premiere Pro', 'DaVinci Resolve', 'After Effects', 'Final Cut Pro', 'CapCut']],
                    ['key' => 'demo_reel', 'label' => 'Demo reel / video link', 'type' => 'url', 'required' => true],
                    ['key' => 'years_experience', 'label' => 'Years of experience', 'type' => 'select', 'required' => true, 'options' => self::EXPERIENCE],
                ],
            ],
        ];

        foreach ($categories as $index => $category) {
            JobCategory::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'icon' => $category['icon'],
                    'description' => $category['description'],
                    'field_schema' => [
                        'fields' => $category['fields'],
                    ],
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }
}
