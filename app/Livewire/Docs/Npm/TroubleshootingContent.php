<?php

namespace App\Livewire\Docs\Npm;

use App\Filament\Schemas\Components\BulletList;
use App\Filament\Schemas\Components\CodeBlock;
use App\Filament\Schemas\Components\ErrorSection;
use App\Filament\Schemas\Components\SearchComponent;
use App\Filament\Schemas\Components\TextContent;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

class TroubleshootingContent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public function showCopiedNotification(string $label = 'Content'): void
    {
        Notification::make()
            ->title("{$label} copied to clipboard")
            ->success()
            ->send();
    }

    public function getNpmrcExampleProperty(): string
    {
        return "@myorg:registry=https://packgrid.test/npm/\n//packgrid.test/npm/:_authToken=your-token-here";
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                SearchComponent::make()
                    ->heading('npm Error Solutions')
                    ->placeholder('Search npm errors...')
                    ->githubUrl('https://github.com/mwguerra/packgrid/issues')
                    ->haystack([
                        // E401 Unauthorized
                        ErrorSection::make()
                            ->searchId('e401')
                            ->errorIcon('heroicon-o-key')
                            ->errorTitle('E401 Unauthorized')
                            ->errorDescription('Authentication failed with Bearer token')
                            ->errorMessage('npm ERR! code E401
npm ERR! 401 Unauthorized - GET https://packgrid.test/npm/@myorg/package')
                            ->solutionSchema([
                                TextContent::make('This error occurs when npm cannot authenticate with Packgrid. Check your <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">.npmrc</code> configuration:'),
                                BulletList::make([
                                    'Verify the token in .npmrc is correct and active',
                                    'Ensure the registry URL matches your Packgrid server',
                                    'Check that the _authToken line uses the correct host',
                                ]),
                                TextContent::make('Your .npmrc should look like:'),
                                CodeBlock::make($this->npmrcExample)
                                    ->copyLabel('.npmrc'),
                            ]),

                        // E404 Package Not Found
                        ErrorSection::make()
                            ->searchId('e404')
                            ->errorIcon('heroicon-o-cube')
                            ->errorTitle('E404 Package Not Found')
                            ->errorDescription('Package does not exist or not synced')
                            ->errorMessage('npm ERR! code E404
npm ERR! 404 Not Found - GET https://packgrid.test/npm/@myorg/package')
                            ->solutionSchema([
                                BulletList::make([
                                    'Verify the package name matches the "name" field in package.json',
                                    'Ensure the repository is registered in Packgrid',
                                    'Run a <strong>Sync</strong> on the repository in Packgrid',
                                    'Check that the scope in your install command matches the package scope',
                                ]),
                            ]),

                        // E400 Bad Request (URL encoding)
                        ErrorSection::make()
                            ->searchId('e400')
                            ->errorIcon('heroicon-o-exclamation-triangle')
                            ->errorTitle('E400 Bad Request')
                            ->errorDescription('URL encoding issue with scoped packages')
                            ->errorMessage('npm ERR! code E400
npm ERR! 400 Bad Request - GET https://packgrid.test/npm/%40myorg%2Fpackage')
                            ->solutionSchema([
                                TextContent::make('This usually indicates a URL encoding issue with scoped package names. Packgrid should handle URL-encoded scopes (like <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">%40</code> for <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">@</code>).'),
                                BulletList::make([
                                    'Verify Packgrid is running the latest version',
                                    'Check that the registry URL in .npmrc ends with a trailing slash',
                                    'Try clearing npm cache: <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">npm cache clean --force</code>',
                                ]),
                            ]),

                        // SSL Certificate Error
                        ErrorSection::make()
                            ->searchId('ssl')
                            ->errorIcon('heroicon-o-shield-exclamation')
                            ->errorTitle('SSL Certificate Error')
                            ->errorDescription('Self-signed certificate issues')
                            ->errorMessage('npm ERR! code UNABLE_TO_GET_ISSUER_CERT_LOCALLY
npm ERR! unable to get local issuer certificate')
                            ->solutionSchema([
                                TextContent::make('For local development with self-signed certificates, you can configure npm to skip SSL verification:'),
                                CodeBlock::make('npm config set strict-ssl false')
                                    ->copyLabel('Command'),
                                TextContent::make('Or add to your .npmrc:'),
                                CodeBlock::make('strict-ssl=false')
                                    ->copyLabel('.npmrc'),
                                TextContent::make('<strong>Warning:</strong> Only use this for local development. In production, use valid SSL certificates.'),
                            ]),

                        // Registry Scope Mismatch
                        ErrorSection::make()
                            ->searchId('scope')
                            ->errorIcon('heroicon-o-at-symbol')
                            ->errorTitle('Registry Scope Mismatch')
                            ->errorDescription('Package installed from wrong registry')
                            ->errorMessage('npm ERR! code E404
npm ERR! 404 Not Found - GET https://registry.npmjs.org/@myorg/package')
                            ->solutionSchema([
                                TextContent::make('npm is trying to fetch from the public registry instead of Packgrid. Check your .npmrc scope configuration:'),
                                BulletList::make([
                                    'The scope line must match your package scope exactly (e.g., <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">@myorg</code>)',
                                    'The .npmrc file must be in your project root or home directory',
                                    'Try specifying the registry explicitly: <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">npm install @myorg/package --registry=https://packgrid.test/npm/</code>',
                                ]),
                            ]),

                        // Tarball Download Failed
                        ErrorSection::make()
                            ->searchId('tarball')
                            ->errorIcon('heroicon-o-archive-box')
                            ->errorTitle('Tarball Download Failed')
                            ->errorDescription('Failed to download package tarball')
                            ->errorMessage('npm ERR! code EINTEGRITY
npm ERR! sha512 integrity checksum failed')
                            ->solutionSchema([
                                BulletList::make([
                                    'Clear npm cache: <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">npm cache clean --force</code>',
                                    'Delete node_modules and package-lock.json, then reinstall',
                                    'Ensure Packgrid has access to the GitHub repository',
                                    'Check that the repository has been synced recently',
                                ]),
                            ]),

                        // Invalid package.json
                        ErrorSection::make()
                            ->searchId('packagejson')
                            ->errorIcon('heroicon-o-document-text')
                            ->errorTitle('Invalid package.json')
                            ->errorDescription('Package.json missing or invalid')
                            ->errorMessage('Repository sync failed: package.json not found or invalid')
                            ->solutionSchema([
                                TextContent::make('The repository must have a valid package.json with a scoped name:'),
                                CodeBlock::make('{
  "name": "@myorg/package-name",
  "version": "1.0.0",
  "main": "index.js"
}')
                                    ->copyLabel('package.json'),
                                BulletList::make([
                                    'The "name" field must be a scoped package name',
                                    'The package.json must be valid JSON',
                                    'Ensure the file is in the repository root',
                                ]),
                            ]),
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.docs.npm.troubleshooting-content');
    }
}
