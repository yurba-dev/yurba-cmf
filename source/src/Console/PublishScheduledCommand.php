<?php

namespace Yurba\Cmf\Console;

use Illuminate\Console\Command;
use Yurba\Cmf\Facades\Yurba;

class PublishScheduledCommand extends Command
{
    protected $name = 'yurba:publish-scheduled';

    protected $description = 'Publish records whose scheduled time has arrived';

    public function handle(): int
    {
        $total = 0;

        foreach (Yurba::resources() as $res) {
            $p = $res->publishing();
            if (! $p || empty($p['status']) || empty($p['date'])) {
                continue;
            }

            $count = $res->query()
                ->where($p['status'], $p['scheduled'] ?? 'scheduled')
                ->whereNotNull($p['date'])
                ->where($p['date'], '<=', now())
                ->update([$p['status'] => $p['published'] ?? 'publish']);

            if ($count > 0) {
                $this->components->info($res->pluralLabel().": published {$count} scheduled record(s).");
                $total += $count;
            }
        }

        if ($total == 0) {
            $this->components->info('Nothing due to publish.');
        }

        return self::SUCCESS;
    }
}
