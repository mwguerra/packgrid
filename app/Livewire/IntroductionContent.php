<?php

namespace App\Livewire;

use App\Filament\Schemas\Components\AlertBox;
use App\Filament\Schemas\Components\BulletList;
use App\Filament\Schemas\Components\ComparisonTable;
use App\Filament\Schemas\Components\HeroSection;
use App\Filament\Schemas\Components\StatCard;
use App\Filament\Schemas\Components\StatCards;
use App\Filament\Schemas\Components\TextContent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

class IntroductionContent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                HeroSection::make()
                    ->badgeIcon('heroicon-s-server')
                    ->badgeLabel('Self-Hosted')
                    ->title('One GitHub token. Unlimited team access.')
                    ->description('Configure GitHub credentials once here. Distribute simple Packgrid tokens to your team and CI/CD.')
                    ->heroIcon('heroicon-o-cube')
                    ->heroIconGradient('amber', 'orange'),

                StatCards::make()
                    ->cards([
                        StatCard::make()
                            ->icon('heroicon-s-key')
                            ->color('amber')
                            ->title('One Credential')
                            ->description('GitHub token stays here'),
                        StatCard::make()
                            ->icon('heroicon-s-users')
                            ->color('blue')
                            ->title('Team Tokens')
                            ->description('Simple access for everyone'),
                        StatCard::make()
                            ->icon('heroicon-s-cube')
                            ->color('emerald')
                            ->title('Standard Composer')
                            ->description('No plugins required'),
                    ])
                    ->gridColumns(3),

                AlertBox::make()
                    ->warning()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->title('Important Notice')
                    ->description('Please read before using Packgrid in production.')
                    ->items([
                        '<strong>Use at your own risk</strong> — Packgrid is provided as-is, without warranty of any kind',
                        '<strong>Security is your responsibility</strong> — Ensure your server is properly secured with HTTPS and firewall rules',
                        '<strong>Backup your data</strong> — Regularly backup your database and configuration',
                        '<strong>Token management</strong> — Treat Packgrid tokens like passwords. Rotate them regularly and revoke unused ones',
                        '<strong>Not a replacement for proper access control</strong> — Packgrid simplifies distribution, but you should still follow security best practices',
                    ]),

                Section::make('What is Packgrid?')
                    ->icon('heroicon-o-question-mark-circle')
                    ->iconColor('primary')
                    ->description('A simple solution for a common problem')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make('Packgrid is a lightweight, self-hosted <strong>private Composer repository server</strong> designed for developers and teams who need to manage and distribute private PHP packages without relying on third-party services like Private Packagist.'),
                        TextContent::make('Built with <strong>Laravel</strong> and <strong>Filament</strong>, Packgrid follows the philosophy of keeping things simple. It does one thing well: serving your private packages to Composer with proper authentication.'),
                    ]),

                Section::make('The Problem It Solves')
                    ->icon('heroicon-o-light-bulb')
                    ->iconColor('primary')
                    ->description('Why you might need Packgrid')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make('When working with private GitHub repositories as Composer packages, you typically have two options:'),
                        BulletList::make([
                            '<strong>Use GitHub directly</strong> — Works, but requires SSH keys or OAuth tokens on every machine and CI/CD pipeline that needs access',
                            '<strong>Pay for Private Packagist</strong> — Great service, but can be expensive for small teams or personal projects',
                        ])->bulletIcon('heroicon-s-x-mark')->bulletColor('red'),
                        TextContent::make('Packgrid offers a third option:'),
                        BulletList::make([
                            '<strong>Self-host your own Composer server</strong> — Full control over your packages with simple token-based authentication',
                            '<strong>One GitHub token, multiple users</strong> — Configure GitHub access once in Packgrid, then distribute simple Packgrid tokens to your team',
                            '<strong>Works with any Composer project</strong> — Standard Composer repository, no special plugins required',
                        ])->bulletIcon('heroicon-s-check')->bulletColor('emerald'),
                    ]),

                Section::make('How Packgrid Compares')
                    ->icon('heroicon-o-scale')
                    ->iconColor('primary')
                    ->description('An honest comparison with alternatives')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make('There are several solutions for hosting private Composer packages. Here\'s how Packgrid compares:'),
                        ComparisonTable::make()
                            ->products([
                                'packgrid' => ['name' => 'Packgrid', 'highlight' => true],
                                'packagist' => ['name' => 'Private Packagist'],
                                'satis' => ['name' => 'Satis'],
                                'repman' => ['name' => 'Repman'],
                            ])
                            ->features([
                                [
                                    'name' => 'Multi-Protocol',
                                    'description' => 'Composer (PHP) + npm (JS)',
                                    'values' => [
                                        'packgrid' => true,
                                        'packagist' => false,
                                        'satis' => false,
                                        'repman' => false,
                                    ],
                                ],
                                [
                                    'name' => 'Hosting',
                                    'values' => [
                                        'packgrid' => 'Self-hosted',
                                        'packagist' => 'Cloud or Self-hosted',
                                        'satis' => 'Self-hosted',
                                        'repman' => 'Self-hosted',
                                    ],
                                ],
                                [
                                    'name' => 'Cost',
                                    'values' => [
                                        'packgrid' => 'Free',
                                        'packagist' => '€59/mo + €17/user',
                                        'satis' => 'Free',
                                        'repman' => 'Free',
                                    ],
                                ],
                                [
                                    'name' => 'Web Admin Panel',
                                    'values' => [
                                        'packgrid' => true,
                                        'packagist' => true,
                                        'satis' => false,
                                        'repman' => true,
                                    ],
                                ],
                                [
                                    'name' => 'GitHub Integration',
                                    'values' => [
                                        'packgrid' => true,
                                        'packagist' => true,
                                        'satis' => true,
                                        'repman' => true,
                                    ],
                                ],
                                [
                                    'name' => 'GitLab Integration',
                                    'values' => [
                                        'packgrid' => 'planned',
                                        'packagist' => true,
                                        'satis' => true,
                                        'repman' => true,
                                    ],
                                ],
                                [
                                    'name' => 'Bitbucket Integration',
                                    'values' => [
                                        'packgrid' => 'planned',
                                        'packagist' => true,
                                        'satis' => true,
                                        'repman' => true,
                                    ],
                                ],
                                [
                                    'name' => 'Webhooks',
                                    'description' => 'Auto-sync on push',
                                    'values' => [
                                        'packgrid' => 'planned',
                                        'packagist' => true,
                                        'satis' => 'partial',
                                        'repman' => true,
                                    ],
                                ],
                                [
                                    'name' => 'Security Scanning',
                                    'description' => 'Vulnerability alerts',
                                    'values' => [
                                        'packgrid' => 'planned',
                                        'packagist' => true,
                                        'satis' => false,
                                        'repman' => true,
                                    ],
                                ],
                                [
                                    'name' => 'Package Mirroring',
                                    'description' => 'Mirror packagist.org',
                                    'values' => [
                                        'packgrid' => false,
                                        'packagist' => true,
                                        'satis' => true,
                                        'repman' => false,
                                    ],
                                ],
                                [
                                    'name' => 'Team Permissions',
                                    'description' => 'Per-package access control',
                                    'values' => [
                                        'packgrid' => false,
                                        'packagist' => true,
                                        'satis' => false,
                                        'repman' => true,
                                    ],
                                ],
                                [
                                    'name' => 'License Review',
                                    'values' => [
                                        'packgrid' => false,
                                        'packagist' => true,
                                        'satis' => false,
                                        'repman' => false,
                                    ],
                                ],
                                [
                                    'name' => 'Setup Complexity',
                                    'values' => [
                                        'packgrid' => 'Simple',
                                        'packagist' => 'Managed',
                                        'satis' => 'Manual',
                                        'repman' => 'Moderate',
                                    ],
                                ],
                            ]),
                        TextContent::make('<strong>Packgrid\'s niche:</strong> If you need a simple, free, self-hosted solution for distributing private GitHub packages to your team without the complexity of Satis or the cost of Private Packagist, Packgrid is a good fit. For enterprise features like security scanning, license review, or complex team permissions, consider Private Packagist or Repman.'),
                    ]),

                Section::make('Current Features')
                    ->icon('heroicon-o-sparkles')
                    ->iconColor('primary')
                    ->description('What Packgrid can do today')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        BulletList::make([
                            '<strong>GitHub Integration</strong> — Connect your GitHub repositories (public and private) using personal access tokens',
                            '<strong>Token Authentication</strong> — Create and manage access tokens for your team members or CI/CD pipelines',
                            '<strong>Automatic Sync</strong> — Packages are synced from GitHub and served through Composer\'s standard HTTP protocol',
                            '<strong>Simple Admin Panel</strong> — Manage repositories, credentials, and tokens through a clean Filament interface',
                        ])->bulletIcon('heroicon-s-check-circle')->bulletColor('emerald'),
                    ]),

                Section::make('Future Plans')
                    ->icon('heroicon-o-rocket-launch')
                    ->iconColor('primary')
                    ->description('What might come next')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make('Packgrid is intentionally kept simple, but there are some features that might be added in the future:'),
                        BulletList::make([
                            '<strong>GitLab Support</strong> — Connect to self-hosted or gitlab.com repositories',
                            '<strong>Bitbucket Support</strong> — Integrate with Bitbucket Cloud and Server',
                            '<strong>Gitea/Forgejo Support</strong> — Support for self-hosted Git platforms',
                            '<strong>Webhooks</strong> — Automatic sync when you push to your repositories',
                            '<strong>Security Scanning</strong> — Alert on known vulnerabilities in your packages',
                            '<strong>Package Statistics</strong> — Track downloads and usage',
                        ])->bulletIcon('heroicon-o-clock')->bulletColor('blue'),
                        TextContent::make('These features will only be added if they don\'t compromise the simplicity of the project.'),
                    ]),

                Section::make('Getting Started')
                    ->icon('heroicon-o-play')
                    ->iconColor('primary')
                    ->description('Ready to use Packgrid?')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextContent::make('Head over to the <strong>Setup Guide</strong> tab for step-by-step instructions on configuring Composer to use your Packgrid server. If you run into any issues, check the <strong>Troubleshooting</strong> tab for common error solutions.'),
                        BulletList::make([
                            'Add a GitHub credential for private repository access',
                            'Register your repositories in Packgrid',
                            'Create access tokens for your team',
                            'Configure Composer in your projects',
                        ])->bulletIcon('heroicon-s-arrow-right')->bulletColor('amber'),
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.introduction-content');
    }
}
