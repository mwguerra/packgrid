<?php

namespace App\Enums;

enum PackageFormat: string
{
    case Composer = 'composer';
    case Npm = 'npm';
    case Git = 'git';

    public function label(): string
    {
        return match ($this) {
            self::Composer => 'Composer (PHP)',
            self::Npm => 'NPM (Node.js)',
            self::Git => 'Git Clone',
        };
    }

    public function manifestFile(): string
    {
        return match ($this) {
            self::Composer => 'composer.json',
            self::Npm => 'package.json',
            self::Git => '',
        };
    }

    public function archiveExtension(): string
    {
        return match ($this) {
            self::Composer => 'zip',
            self::Npm => 'tgz',
            self::Git => '',
        };
    }
}
