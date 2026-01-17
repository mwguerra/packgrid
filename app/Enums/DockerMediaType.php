<?php

namespace App\Enums;

enum DockerMediaType: string
{
    case ManifestV2 = 'application/vnd.docker.distribution.manifest.v2+json';
    case ManifestList = 'application/vnd.docker.distribution.manifest.list.v2+json';
    case OciManifest = 'application/vnd.oci.image.manifest.v1+json';
    case OciIndex = 'application/vnd.oci.image.index.v1+json';
    case ContainerConfig = 'application/vnd.docker.container.image.v1+json';
    case LayerTarGzip = 'application/vnd.docker.image.rootfs.diff.tar.gzip';
    case LayerTar = 'application/vnd.docker.image.rootfs.diff.tar';
    case OciLayerTarGzip = 'application/vnd.oci.image.layer.v1.tar+gzip';
    case OciLayerTar = 'application/vnd.oci.image.layer.v1.tar';
    case OciConfig = 'application/vnd.oci.image.config.v1+json';

    public function label(): string
    {
        return match ($this) {
            self::ManifestV2 => 'Docker Manifest v2',
            self::ManifestList => 'Docker Manifest List',
            self::OciManifest => 'OCI Manifest',
            self::OciIndex => 'OCI Index',
            self::ContainerConfig => 'Container Config',
            self::LayerTarGzip => 'Layer (tar.gzip)',
            self::LayerTar => 'Layer (tar)',
            self::OciLayerTarGzip => 'OCI Layer (tar.gzip)',
            self::OciLayerTar => 'OCI Layer (tar)',
            self::OciConfig => 'OCI Config',
        };
    }

    public function isManifest(): bool
    {
        return match ($this) {
            self::ManifestV2, self::ManifestList, self::OciManifest, self::OciIndex => true,
            default => false,
        };
    }

    public function isConfig(): bool
    {
        return match ($this) {
            self::ContainerConfig, self::OciConfig => true,
            default => false,
        };
    }

    public function isLayer(): bool
    {
        return match ($this) {
            self::LayerTarGzip, self::LayerTar, self::OciLayerTarGzip, self::OciLayerTar => true,
            default => false,
        };
    }

    public static function acceptableManifestTypes(): array
    {
        return [
            self::ManifestV2->value,
            self::ManifestList->value,
            self::OciManifest->value,
            self::OciIndex->value,
        ];
    }
}
