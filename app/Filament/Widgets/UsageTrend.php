<?php

namespace App\Filament\Widgets;

use App\Enums\DockerActivityType;
use App\Models\DockerActivity;
use App\Models\DownloadLog;
use App\Support\PackgridSettings;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class UsageTrend extends ChartWidget
{
    protected ?string $pollingInterval = '120s';

    protected int|string|array $columnSpan = 'full';

    /** Number of days of history to plot. */
    protected const DAYS = 14;

    public function getHeading(): ?string
    {
        return __('widget.usage.heading');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $start = now()->startOfDay()->subDays(self::DAYS - 1);

        $labels = [];
        $keys = [];
        for ($i = 0; $i < self::DAYS; $i++) {
            $day = $start->copy()->addDays($i);
            $keys[] = $day->format('Y-m-d');
            $labels[] = $day->format('M j');
        }

        $downloads = $this->dailyCounts(DownloadLog::query()->where('created_at', '>=', $start));

        $datasets = [[
            'label' => __('widget.usage.downloads'),
            'data' => array_map(fn (string $key): int => $downloads[$key] ?? 0, $keys),
            'borderColor' => '#f59e0b',
            'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
            'fill' => true,
            'tension' => 0.3,
        ]];

        if (PackgridSettings::dockerEnabled()) {
            $pulls = $this->dailyCounts(
                DockerActivity::query()
                    ->where('type', DockerActivityType::Pull)
                    ->where('created_at', '>=', $start)
            );

            $datasets[] = [
                'label' => __('widget.usage.docker_pulls'),
                'data' => array_map(fn (string $key): int => $pulls[$key] ?? 0, $keys),
                'borderColor' => '#3b82f6',
                'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                'fill' => true,
                'tension' => 0.3,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    /**
     * Count rows per calendar day, keyed by Y-m-d. Uses DATE() which is portable
     * across SQLite, MySQL and PostgreSQL.
     *
     * @return array<string, int>
     */
    protected function dailyCounts(Builder $query): array
    {
        return $query
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day')
            ->map(fn ($value): int => (int) $value)
            ->toArray();
    }
}
