<?php

namespace App\Enums;

enum PackageFormat: string
{
    case Composer = 'composer';
    case Npm = 'npm';

    public function label(): string
    {
        return match ($this) {
            self::Composer => 'Composer (PHP)',
            self::Npm => 'NPM (Node.js)',
        };
    }

    public function manifestFile(): string
    {
        return match ($this) {
            self::Composer => 'composer.json',
            self::Npm => 'package.json',
        };
    }

    public function archiveExtension(): string
    {
        return match ($this) {
            self::Composer => 'zip',
            self::Npm => 'tgz',
        };
    }
}
